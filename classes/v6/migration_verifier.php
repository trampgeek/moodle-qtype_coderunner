<?php
// This file is part of CodeRunner - http://coderunner.org.nz
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Checking a migrated question against the columns it was migrated from.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use core_question\local\bank\question_version_status;

/**
 * Compares what each question resolves to now against what it resolved to
 * before the migration.
 *
 * The old side is computed by calling CodeRunner 5's own code — get_prototype()
 * and set_inherited_fields() — rather than by reimplementing it. The old
 * columns are retained until a later release precisely so this can be done, and
 * calling the real code removes any chance of a reimplementation drifting from
 * it.
 *
 * Collected fields are not compared. Template parameters are merged only after
 * each layer has been evaluated in its own language, which would mean running
 * Twig or contacting the sandbox; what is compared is the inputs to that
 * evaluation, which are the raw strings and languages of each layer.
 */
class migration_verifier {
    /** @var string The question type's options table. */
    const OPTIONS = 'question_coderunner_options';

    /** @var array The findings of the last run. */
    private array $result = [];

    /**
     * Compare every migrated question, or a limited number of them.
     *
     * @param int $limit stop after this many questions, or 0 for all of them.
     * @param callable|null $progress called with (done, total) as work proceeds.
     * @return \stdClass with counts and a sample of differences.
     */
    public function verify(int $limit = 0, ?callable $progress = null): \stdClass {
        global $DB;

        $this->result = [
            'checked' => 0,
            'matched' => 0,
            'differing' => 0,
            'broken' => 0,
            'unmigrated' => 0,
            'unverifiable' => 0,
            'differences' => [],
            'brokenbyname' => [],
            'unverifiablebyname' => [],
        ];

        $total = $DB->count_records_select(self::OPTIONS, 'prototypetype = 0');
        $rs = $DB->get_recordset_sql("
            SELECT qco.*, qc.contextid
              FROM {" . self::OPTIONS . "} qco
              JOIN {question} q ON qco.questionid = q.id
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE qco.prototypetype = 0
          ORDER BY qco.id");

        $done = 0;
        foreach ($rs as $row) {
            $this->check_row($row);
            $done++;
            if ($progress && $done % 500 === 0) {
                $progress($done, $total);
            }
            if ($limit && $done >= $limit) {
                break;
            }
        }
        $rs->close();
        if ($progress) {
            $progress($done, $total);
        }

        return (object) $this->result;
    }

    /**
     * Compare one question.
     *
     * @param \stdClass $row the options row, with its question's context id.
     */
    protected function check_row(\stdClass $row): void {
        if ($row->overrides === null) {
            $this->result['unmigrated']++;
            return;
        }
        if ($row->prototypeid === null) {
            // Recorded as broken. It was broken before too, which is the point:
            // a question with no resolvable prototype keeps that state rather
            // than silently acquiring one.
            $this->result['broken']++;
            $name = $row->coderunnertype;
            $this->result['brokenbyname'][$name] = ($this->result['brokenbyname'][$name] ?? 0) + 1;
            return;
        }

        $before = $this->resolve_the_old_way($row);
        if ($before === null) {
            // CodeRunner 5 cannot resolve this question's type, so there is
            // nothing to compare the migration against. That is the state of
            // every question created through the CodeRunner 6 form on a
            // prototype with no CodeRunner 5 prototype question behind it, and
            // it is not a fault: the migration never ran on such a question.
            // It is reported separately rather than counted as a difference,
            // because a difference is a disagreement between two resolutions
            // and here there is only one.
            $this->result['unverifiable']++;
            $name = $row->coderunnertype;
            $this->result['unverifiablebyname'][$name] =
                ($this->result['unverifiablebyname'][$name] ?? 0) + 1;
            return;
        }
        $after = $this->resolve_the_new_way($row);

        $differences = [];
        foreach (field_registry::all() as $name => $field) {
            if (!$field->resolves_to_one_value() || $field->kind === field_kind::INSTANCE) {
                continue;
            }
            if (!$field->incolumn) {
                // A field with no column did not exist in CodeRunner 5, so
                // there is no previous value to compare against. The test case
                // field switches are the case in point: they are new, and the
                // Site defaults prototype turns them all on so that the
                // question form shows every test case field as it does today.
                continue;
            }
            $old = $field->normalise_for_comparison($before->$name ?? null);
            $new = $field->normalise_for_comparison($after->get($name));
            if ($old !== $new) {
                $differences[$name] = ['before' => $old, 'after' => $new];
            }
        }

        $this->result['checked']++;
        if ($differences) {
            $this->record_difference($row, $differences);
        } else {
            $this->result['matched']++;
        }
    }

    /**
     * The effective settings CodeRunner 5 would give this question, computed by
     * its own code.
     *
     * @param \stdClass $row the options row, with its question's context id.
     * @return \stdClass|null the effective values, or null if CodeRunner 5
     *      cannot resolve the prototype.
     */
    protected function resolve_the_old_way(\stdClass $row): ?\stdClass {
        global $CFG;

        $context = \context::instance_by_id((int) $row->contextid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }

        // Not autoloadable: questiontype.php is outside classes/, so the class
        // exists only if something required it. The CLI script does, but this
        // class must not depend on its caller having done so.
        require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

        $prototype = \qtype_coderunner::get_prototype($row->coderunnertype, $context);
        if ($prototype === null || is_array($prototype)) {
            return null;
        }

        $options = clone $row;
        (new \qtype_coderunner())->set_inherited_fields($options, $prototype);
        return $options;
    }

    /**
     * The effective settings CodeRunner 6 gives this question.
     *
     * @param \stdClass $row the options row, carrying its prototype id and
     *      overrides document.
     * @return question_settings the resolved settings.
     */
    protected function resolve_the_new_way(\stdClass $row): question_settings {
        $values = json_decode($row->overrides, true);
        $repo = new \local_coderunner_manager\prototype_repository();

        return $repo->chain_for(
            (int) $row->prototypeid,
            new override_set(is_array($values) ? $values : [], 'the question')
        )->resolve();
    }

    /**
     * Record a question whose settings differ.
     *
     * Only a sample is kept, grouped by the fields that differ, because a
     * systematic fault affects thousands of questions identically and a list of
     * them all is less use than one example and a count.
     *
     * @param \stdClass $row the options row.
     * @param array $differences field name => before and after.
     */
    protected function record_difference(\stdClass $row, array $differences): void {
        $this->result['differing']++;

        $key = implode(',', array_keys($differences));
        if (!isset($this->result['differences'][$key])) {
            $this->result['differences'][$key] = [
                'count' => 0,
                'example' => ['questionid' => $row->questionid, 'type' => $row->coderunnertype],
                'fields' => $differences,
            ];
        }
        $this->result['differences'][$key]['count']++;
    }
}
