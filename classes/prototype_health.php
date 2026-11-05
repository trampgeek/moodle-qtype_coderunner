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
 * Analysis of the health of the prototypes visible from a course.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context_course;
use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_version_status;

/**
 * Finds questions whose prototype cannot be resolved, or resolves ambiguously,
 * plus prototypes that will need attention when prototypes move out of the
 * question bank.
 *
 * The prototype search set is obtained from qtype_coderunner::prototype_contexts()
 * and the matching rules mirror qtype_coderunner::get_prototype(), so that a
 * question reported here is exactly a question that is broken, or would break,
 * at run time.
 *
 * Questions are counted in bulk rather than by calling get_prototype() once per
 * question, so the report stays usable on sites with very large question banks.
 */
class prototype_health {
    /** @var int Report on the latest version of each question only. */
    const LATEST_VERSIONS = 0;

    /** @var int Report on every version of every question. */
    const ALL_VERSIONS = 1;

    /** @var int|null Cached context id of the front page's default question bank. */
    private static $frontpagebankcontextid = null;

    /**
     * Analyse the prototypes visible from one course.
     *
     * @param int $courseid the course to report on.
     * @param int $versions self::LATEST_VERSIONS or self::ALL_VERSIONS.
     * @return \stdClass with fields:
     *      missing     => question rows whose coderunnertype matches no visible prototype,
     *      ambiguous   => question rows whose coderunnertype matches more than one,
     *      duplicates  => one row per prototype name claimed by more than one
     *                     visible prototype, whether or not any question uses it,
     *      shadowed    => course prototype rows whose name is also a site prototype name,
     *      strandedextra => visible prototype rows with a non-empty globalextra,
     *      numquestions  => how many question rows were examined,
     *      numprototypes => how many distinct prototype names were visible,
     *      olderproblems => in LATEST_VERSIONS mode, how many problems exist in
     *                       versions that were not examined; 0 in ALL_VERSIONS mode,
     *      allversions   => true if every version was examined,
     *      legacyuiparams  => question rows with a template parameter whose name is
     *                         also a UI parameter of the question's UI plugin,
     *      prototypeuiparams => the same, for prototypes' own template parameters,
     *      uncheckableparams => question rows whose template parameter names could
     *                           not be determined, so could not be checked.
     */
    public static function analyse($courseid, $versions = self::LATEST_VERSIONS) {
        $coursecontext = context_course::instance($courseid);
        $prototypecontextids = \qtype_coderunner::prototype_contexts($coursecontext);

        // The question contexts are the prototype contexts less the front page's
        // bank, which is searched for prototypes from every course but holds
        // questions belonging to the site course rather than to this one.
        $questioncontextids = array_values(array_diff(
            $prototypecontextids,
            [self::frontpage_bank_contextid()]
        ));

        $prototypes = self::load_prototypes($prototypecontextids);
        $questions = self::load_questions($questioncontextids, $versions);

        // Count the visible prototypes of each type, exactly as get_prototype() would.
        $countbytype = [];
        foreach ($prototypes as $prototype) {
            $type = $prototype->coderunnertype;
            $countbytype[$type] = ($countbytype[$type] ?? 0) + 1;
        }

        $result = new \stdClass();
        $result->missing = [];
        $result->ambiguous = [];
        $result->numquestions = count($questions);
        $result->numprototypes = count($countbytype);

        foreach ($questions as $question) {
            $count = $countbytype[$question->coderunnertype] ?? 0;
            if ($count === 0) {
                $result->missing[] = $question;
            } else if ($count > 1) {
                $question->candidates = self::candidates_of_type($prototypes, $question->coderunnertype);
                $result->ambiguous[] = $question;
            }
        }

        // Duplicated prototype names, reported whether or not a question uses
        // them. The checks above only ever see a duplicate through a question
        // that cannot resolve it, so a duplicated pair nothing uses yet is
        // invisible to them -- while still breaking the next question written
        // against it, and still blocking the migration, which cannot choose
        // between two prototypes of one name.
        $result->duplicates = [];
        foreach ($countbytype as $type => $count) {
            if ($count > 1) {
                $row = new \stdClass();
                $row->coderunnertype = $type;
                $row->count = $count;
                $row->candidates = self::candidates_of_type($prototypes, $type);
                $result->duplicates[] = $row;
            }
        }

        $result->shadowed = self::find_shadowed($prototypes);
        $result->strandedextra = self::find_stranded_globalextra($prototypes);

        // Template parameters that double as UI parameters. Only questions whose
        // prototype resolves unambiguously can be checked, since the UI plugin and
        // the prototype's own template parameters are both inherited.
        $onlyprototype = [];
        foreach ($prototypes as $prototype) {
            $type = $prototype->coderunnertype;
            if (($countbytype[$type] ?? 0) === 1) {
                $onlyprototype[$type] = $prototype;
            }
        }
        // Each layer is judged on its own parameters, so a prototype whose
        // parameters cannot be read does not make every question under it
        // unreadable, and a prototype that clashes is reported once rather
        // than once per dependent question.
        $result->legacyuiparams = [];
        $result->uncheckableparams = [];
        foreach ($questions as $question) {
            $prototype = $onlyprototype[$question->coderunnertype] ?? null;
            if ($prototype === null) {
                continue;  // Already reported as missing or ambiguous.
            }
            [$names, $certain] = self::template_param_names($question);
            if (!$certain) {
                $result->uncheckableparams[] = $question;
                continue;
            }
            $clashes = self::ui_param_clashes(self::resolved_uiplugin($question, $prototype), $names);
            if ($clashes) {
                $question->clashingparams = implode(', ', $clashes);
                $result->legacyuiparams[] = $question;
            }
        }

        $result->prototypeuiparams = [];
        foreach ($prototypes as $prototype) {
            [$names, $certain] = self::template_param_names($prototype);
            if (!$certain) {
                continue;
            }
            $clashes = self::ui_param_clashes(self::resolved_uiplugin($prototype, $prototype), $names);
            if ($clashes) {
                $prototype->clashingparams = implode(', ', $clashes);
                $result->prototypeuiparams[] = $prototype;
            }
        }

        // A report that examined only the latest versions must still say how many
        // problems lie in the versions it skipped, otherwise a clean result reads
        // as "this course is fine" when the upgrade will meet hundreds of
        // unresolvable rows. Counting needs no extra rows loaded: the totals per
        // type less the latest-version rows already in hand give the remainder.
        $result->allversions = ($versions == self::ALL_VERSIONS);
        $result->olderproblems = 0;
        if (!$result->allversions) {
            $latestbytype = [];
            foreach ($questions as $question) {
                $type = $question->coderunnertype;
                $latestbytype[$type] = ($latestbytype[$type] ?? 0) + 1;
            }
            foreach (self::count_all_versions_by_type($questioncontextids) as $type => $total) {
                $older = $total - ($latestbytype[$type] ?? 0);
                if ($older > 0 && ($countbytype[$type] ?? 0) !== 1) {
                    $result->olderproblems += $older;
                }
            }
        }

        return $result;
    }

    /**
     * The context id of the front page's default question bank, where site
     * prototypes live. Cached, since it is the same for every course.
     *
     * @return int the context id, or 0 if there is no such bank.
     */
    private static function frontpage_bank_contextid() {
        if (self::$frontpagebankcontextid === null) {
            self::$frontpagebankcontextid = 0;
            if (\qtype_coderunner_util::using_mod_qbank()) {
                $bank = question_bank_helper::get_default_open_instance_system_type(get_site());
                if ($bank !== null) {
                    self::$frontpagebankcontextid = (int) $bank->context->id;
                }
            }
        }
        return self::$frontpagebankcontextid;
    }

    /**
     * Load every prototype visible in the given contexts.
     *
     * The version conditions match qtype_coderunner::get_prototype(): hidden
     * versions are excluded both when finding the most recent version of each
     * prototype and when matching it.
     *
     * @param array $contextids the context ids to search.
     * @return array of prototype rows, keyed by question id.
     */
    private static function load_prototypes(array $contextids) {
        global $DB;

        if (empty($contextids)) {
            return [];
        }
        [$contextcondition, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $params['hidden'] = question_version_status::QUESTION_STATUS_HIDDEN;
        $params['hidden2'] = question_version_status::QUESTION_STATUS_HIDDEN;

        return $DB->get_records_sql("
            SELECT q.id,
                   qco.coderunnertype,
                   qco.prototypetype,
                   qco.globalextra,
                   qco.uiplugin,
                   qco.templateparams,
                   qco.templateparamslang,
                   qco.templateparamsevald,
                   q.name,
                   qc.name AS categoryname,
                   qc.contextid
              FROM {question_coderunner_options} qco
              JOIN {question} q ON qco.questionid = q.id
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE (qv.version = (SELECT MAX(v.version)
                                    FROM {question_versions} v
                                    JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                   WHERE be.id = qbe.id
                                     AND v.status <> :hidden)
                                 )
               AND qv.status <> :hidden2
               AND qco.prototypetype <> 0
               AND qc.contextid $contextcondition
          ORDER BY qco.coderunnertype, q.name", $params);
    }

    /**
     * Load the non-prototype CodeRunner questions in the given contexts.
     *
     * @param array $contextids the context ids to search.
     * @param int $versions self::LATEST_VERSIONS or self::ALL_VERSIONS.
     * @return array of question rows, keyed by question id.
     */
    private static function load_questions(array $contextids, $versions) {
        global $DB;

        if (empty($contextids)) {
            return [];
        }
        [$contextcondition, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        $versioncondition = '';
        if ($versions == self::LATEST_VERSIONS) {
            $params['hidden'] = question_version_status::QUESTION_STATUS_HIDDEN;
            $params['hidden2'] = question_version_status::QUESTION_STATUS_HIDDEN;
            $versioncondition = "
               AND (qv.version = (SELECT MAX(v.version)
                                    FROM {question_versions} v
                                    JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                   WHERE be.id = qbe.id
                                     AND v.status <> :hidden)
                                 )
               AND qv.status <> :hidden2";
        }

        return $DB->get_records_sql("
            SELECT q.id,
                   qco.coderunnertype,
                   qco.uiplugin,
                   qco.templateparams,
                   qco.templateparamslang,
                   qco.templateparamsevald,
                   q.name,
                   qc.name AS categoryname,
                   qc.contextid,
                   qv.version,
                   qbe.id AS bankentryid
              FROM {question_coderunner_options} qco
              JOIN {question} q ON qco.questionid = q.id
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE qco.prototypetype = 0
               AND qc.contextid $contextcondition
               $versioncondition
          ORDER BY qco.coderunnertype, q.name, qv.version", $params);
    }

    /**
     * Count every version of every non-prototype question in the given contexts,
     * grouped by coderunnertype.
     *
     * Used to work out how many problems lie in versions a latest-versions-only
     * report did not examine, without loading those rows.
     *
     * @param array $contextids the context ids to search.
     * @return array coderunnertype => number of question rows of that type.
     */
    private static function count_all_versions_by_type(array $contextids) {
        global $DB;

        if (empty($contextids)) {
            return [];
        }
        [$contextcondition, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        return $DB->get_records_sql_menu("
            SELECT qco.coderunnertype, COUNT(1)
              FROM {question_coderunner_options} qco
              JOIN {question} q ON qco.questionid = q.id
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE qco.prototypetype = 0
               AND qc.contextid $contextcondition
          GROUP BY qco.coderunnertype", $params);
    }

    /**
     * The template parameter names one layer sets.
     *
     * Three sources are tried in order of reliability: the raw field when it is
     * literal JSON; the stored evaluated copy, which in practice is populated
     * and valid for Twig questions as well as the preprocessor languages that
     * install.xml describes, and which is used for names only since its values
     * are a snapshot; and finally, for Twig that only interpolates values,
     * the raw field with interpolations replaced, since interpolation
     * substitutes values and not names.
     *
     * @param \stdClass $layer a question or prototype row.
     * @return array [array of names, bool true if the names are certain].
     */
    private static function template_param_names($layer) {
        $raw = trim($layer->templateparams ?? '');
        if ($raw === '') {
            return [[], true];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return [array_keys($decoded), true];
        }

        $evald = trim($layer->templateparamsevald ?? '');
        if ($evald !== '') {
            $decoded = json_decode($evald, true);
            if (is_array($decoded)) {
                return [array_keys($decoded), true];
            }
        }

        if (!preg_match('/\{%/', $raw)) {
            $stripped = preg_replace('/\{\{.*?\}\}/s', '0', $raw);
            $decoded = json_decode($stripped, true);
            if (is_array($decoded)) {
                return [array_keys($decoded), true];
            }
        }

        // Twig block tags can generate names and nothing readable is stored.
        return [[], false];
    }

    /**
     * The UI plugin a question or prototype resolves to.
     *
     * uiplugin is an inherited field, so a question's own value wins only when
     * it is set, exactly as set_inherited_fields() decides, and the run-time
     * default when nothing is set is the Ace editor.
     *
     * @param \stdClass $layer the question or prototype row.
     * @param \stdClass $prototype the prototype to inherit from.
     * @return string the lower-cased UI plugin name.
     */
    private static function resolved_uiplugin($layer, $prototype) {
        $uiplugin = trim($layer->uiplugin ?? '');
        if ($uiplugin === '') {
            $uiplugin = trim($prototype->uiplugin ?? '');
        }
        return strtolower($uiplugin === '' ? 'ace' : $uiplugin);
    }

    /**
     * Which of the given template parameter names would be picked up as UI
     * parameters by the legacy merge that CodeRunner 6 removes.
     *
     * The match rules are those of qtype_coderunner_ui_parameters::find_key():
     * a UI parameter's own name, or that name prefixed with the UI plugin name
     * and an underscore.
     *
     * @param string $uiplugin the resolved UI plugin name.
     * @param array $names the template parameter names to test.
     * @return array the clashing names.
     */
    private static function ui_param_clashes($uiplugin, array $names) {
        if (empty($names)) {
            return [];
        }

        static $namesbyplugin = [];
        if (!isset($namesbyplugin[$uiplugin])) {
            $known = [];
            try {
                $uiparams = new \qtype_coderunner_ui_parameters($uiplugin);
                foreach ($uiparams->all_names() as $name) {
                    $known[$name] = true;
                    $known["{$uiplugin}_{$name}"] = true;
                }
            } catch (\Exception $e) {
                $known = [];  // Unknown or schema-less UI plugin: nothing to clash with.
            }
            $namesbyplugin[$uiplugin] = $known;
        }

        $clashes = [];
        foreach ($names as $name) {
            if (isset($namesbyplugin[$uiplugin][$name])) {
                $clashes[] = $name;
            }
        }
        return $clashes;
    }

    /**
     * All the visible prototypes of one type. Used to show the author which
     * prototypes are competing when a type resolves ambiguously.
     *
     * @param array $prototypes all visible prototype rows.
     * @param string $type the coderunnertype to filter on.
     * @return array the matching prototype rows.
     */
    private static function candidates_of_type(array $prototypes, $type) {
        $candidates = [];
        foreach ($prototypes as $prototype) {
            if ($prototype->coderunnertype === $type) {
                $candidates[] = $prototype;
            }
        }
        return $candidates;
    }

    /**
     * Find course prototypes whose name is also the name of a site prototype.
     *
     * Such a pair already causes a duplicate-prototype error today, and under
     * the new design names must be unique across a course's prototypes and the
     * site prototypes taken together, so one of the pair will be renamed on
     * migration.
     *
     * @param array $prototypes all visible prototype rows.
     * @return array of course prototype rows that clash with a site prototype.
     */
    private static function find_shadowed(array $prototypes) {
        $frontpage = self::frontpage_bank_contextid();
        if (!$frontpage) {
            return [];
        }

        $sitenames = [];
        foreach ($prototypes as $prototype) {
            if ((int) $prototype->contextid === $frontpage) {
                $sitenames[$prototype->coderunnertype] = true;
            }
        }

        $shadowed = [];
        foreach ($prototypes as $prototype) {
            if ((int) $prototype->contextid !== $frontpage && isset($sitenames[$prototype->coderunnertype])) {
                $shadowed[] = $prototype;
            }
        }
        return $shadowed;
    }

    /**
     * Find prototypes with a non-empty global extra field.
     *
     * A prototype question's own global extra has never had any effect, and is
     * not carried over when prototypes move out of the question bank, so it is
     * listed here for its author to move deliberately.
     *
     * @param array $prototypes all visible prototype rows.
     * @return array of prototype rows with a non-empty globalextra.
     */
    private static function find_stranded_globalextra(array $prototypes) {
        $stranded = [];
        foreach ($prototypes as $prototype) {
            if (trim($prototype->globalextra ?? '') !== '') {
                $stranded[] = $prototype;
            }
        }
        return $stranded;
    }

    /**
     * Whether an analysis found anything worth reporting.
     *
     * @param \stdClass $result the result of analyse().
     * @return bool true if any of the four checks found something.
     */
    public static function has_problems($result) {
        return $result->missing || $result->ambiguous || $result->duplicates || $result->shadowed
            || $result->strandedextra || $result->legacyuiparams || $result->prototypeuiparams;
    }
}
