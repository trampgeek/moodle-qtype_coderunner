<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Deleting a single old version of a question.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

/**
 * Deletes one superseded question version, reporting what became of it.
 *
 * This lives here rather than in scripts/deleteoldquestionversions.php so that
 * it can be tested: that script calls require_admin() at the top level, so a
 * test cannot include it to reach a function defined inside it.
 */
class version_deleter {
    /**
     * Get all question bank entries with multiple versions in a context.
     *
     * **Entries holding a subquestion are left out.** A Cloze subquestion has a
     * question bank entry and a version history of its own, so it looks from here
     * like an entry to tidy up -- but its versions are not independently
     * deletable. question_delete_question() removes a parent version's children
     * recursively, so deleting the old versions of the Cloze parent deletes the
     * old subquestion versions with it, and planning them separately only revisits
     * what has already gone. Leaving them out means the work is done once, by the
     * parent, in the only order it can be done in.
     *
     * Nothing is left behind by the omission: every version of a subquestion
     * belongs to exactly one version of its parent, so the parent's entry accounts
     * for all of them. The latest subquestion versions survive because the latest
     * parent version does.
     *
     * @param int $contextid the context to search.
     * @return array the bank entries, keyed by entry id.
     */
    public static function entries_with_old_versions(int $contextid): array {
        global $DB;

        // Find all question bank entries that have more than one version.
        $sql = "SELECT qbe.id AS entryid,
                       COUNT(qv.id) AS versioncount,
                       MAX(qv.version) AS latestversion,
                       qbe.questioncategoryid,
                       qc.name AS categoryname,
                       ctx.id AS contextid
                  FROM {question_bank_entries} qbe
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE ctx.id = :contextid
                   AND NOT EXISTS (SELECT 1
                                     FROM {question_versions} subqv
                                     JOIN {question} subq ON subq.id = subqv.questionid
                                    WHERE subqv.questionbankentryid = qbe.id
                                      AND subq.parent <> 0)
              GROUP BY qbe.id, qbe.questioncategoryid, qc.name, ctx.id
                HAVING COUNT(qv.id) > 1
              ORDER BY qc.name, qbe.id";

        return $DB->get_records_sql($sql, ['contextid' => $contextid]);
    }

    /**
     * Delete one old version of a question.
     *
     * **A version that has already gone is cascaded, not failed.** The case is
     * a Cloze subquestion: each one has a question bank entry and a version
     * history of its own, so a caller walking the bank entries plans its old
     * versions for deletion in their own right. But question_delete_question()
     * deletes a parent version's children recursively, so by the time the
     * caller reaches the child's own entry there is nothing left to delete.
     *
     * Core allows for exactly this, returning quietly when the question row has
     * gone and saying so in a comment that names the Cloze case. The capability
     * check does not: question_has_capability_on() re-reads the question, and
     * its fallback query uses MUST_EXIST, so asking permission to edit a
     * question that no longer exists throws "Can't find data record in
     * database". Testing for the row first keeps core's guard from being
     * defeated one line before it is reached.
     *
     * Reported separately from a deletion rather than merged into it, because
     * the two are not the same event and a caller's totals should not imply it
     * removed something that went of its own accord.
     *
     * @param int $questionid the question version to delete.
     * @return array ['success' => bool, 'error' => string|null,
     *      'cascaded' => bool] where cascaded means it had already gone.
     */
    public static function delete(int $questionid): array {
        global $DB;

        try {
            if (!$DB->record_exists('question', ['id' => $questionid])) {
                return ['success' => true, 'error' => null, 'cascaded' => true];
            }
            question_require_capability_on($questionid, 'edit');
            question_delete_question($questionid);
            return ['success' => true, 'error' => null, 'cascaded' => false];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'cascaded' => false];
        }
    }
}
