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
 * Unit tests for deleting a single old question version.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

/**
 * Tests the two halves of cleaning up superseded question versions.
 *
 * The delete-old-versions script walked the question bank entries and deleted
 * each entry's superseded versions. A Cloze subquestion has a bank entry and a
 * version history of its own, so its old versions were planned for deletion in
 * their own right -- but deleting the parent version had already taken them,
 * and the script then reported each one as a database error. One run on a real
 * course reported 167 such errors against 9625 successful deletions, with
 * nothing actually left undeleted.
 *
 * Both ends of that are covered here: the plan no longer includes a
 * subquestion's own entry, and a version that has gone regardless is reported
 * as cascaded rather than failed, so the second line of defence is kept honest
 * even though the first should now stop the case arising.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(version_deleter::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class version_deleter_test extends \advanced_testcase {
    /** @var \core_question_generator the question generator. */
    private $generator;

    /** @var int the context of a question bank the entries live in. */
    private int $contextid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // A bank of its own, so that entries_with_old_versions() is asked about
        // this test's questions and not everything the site happens to hold.
        $course = $this->getDataGenerator()->create_course();
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $this->contextid = (int) \context_module::instance($bank->cmid)->id;
    }

    /**
     * Deleting a superseded version reports a deletion, not a cascade.
     */
    public function test_an_ordinary_old_version_is_deleted(): void {
        global $DB;

        $category = $this->generator->create_question_category();
        $first = $this->generator->create_question('shortanswer', null, ['category' => $category->id]);
        $this->generator->update_question($first, null, ['name' => 'Second version']);

        $result = version_deleter::delete((int) $first->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['cascaded'], 'The version was there to be deleted.');
        $this->assertNull($result['error']);
        $this->assertFalse($DB->record_exists('question', ['id' => $first->id]));
    }

    /**
     * A subquestion taken by its parent's deletion is reported as cascaded.
     *
     * This is the case the script met. The old parent version is deleted
     * first, which removes the old subquestion versions recursively, and the
     * walk then reaches the subquestion's own bank entry and tries to delete
     * what has already gone.
     */
    public function test_a_subquestion_taken_with_its_parent_is_cascaded(): void {
        global $DB;

        $category = $this->generator->create_question_category();
        $firstparent = $this->generator->create_question(
            'multianswer',
            'twosubq',
            ['category' => $category->id]
        );
        $this->generator->update_question($firstparent, 'twosubq', ['name' => 'Second version']);

        // The children of the superseded parent version: these are what the
        // script planned to delete under their own bank entries.
        $children = $DB->get_fieldset_select('question', 'id', 'parent = ?', [$firstparent->id]);
        $this->assertNotEmpty($children, 'The Cloze question should have subquestions.');

        // Each child has a bank entry and a version of its own, which is why
        // the walk over bank entries reaches them separately at all.
        foreach ($children as $childid) {
            $this->assertTrue(
                $DB->record_exists('question_versions', ['questionid' => $childid]),
                'A Cloze subquestion is expected to have its own version row.'
            );
        }

        // Deleting the old parent version takes its children with it.
        $parentresult = version_deleter::delete((int) $firstparent->id);
        $this->assertTrue($parentresult['success']);
        $this->assertFalse($parentresult['cascaded']);

        foreach ($children as $childid) {
            $this->assertFalse(
                $DB->record_exists('question', ['id' => $childid]),
                'The parent deletion should have removed its children.'
            );
        }

        // Now the part that used to report a database error.
        foreach ($children as $childid) {
            $result = version_deleter::delete((int) $childid);
            $this->assertTrue($result['success'], 'A version already gone is not a failure.');
            $this->assertTrue($result['cascaded'], 'It went with its parent, so it is cascaded.');
            $this->assertNull($result['error']);
        }
    }

    /**
     * Without the guard, the capability check is what fails.
     *
     * Pinning this down is the point of the fix: core's
     * question_delete_question() returns quietly when the question row has
     * gone, and says so in a comment naming the Cloze case, but
     * question_require_capability_on() re-reads the question and its fallback
     * query uses MUST_EXIST. So the permission check threw one line before
     * reaching the tolerance core provides. If core ever makes the check
     * tolerant, this test fails and the guard can go.
     */
    public function test_the_capability_check_is_what_rejects_a_missing_version(): void {
        global $DB;

        $category = $this->generator->create_question_category();
        $question = $this->generator->create_question('shortanswer', null, ['category' => $category->id]);
        $this->generator->update_question($question, null, ['name' => 'Second version']);
        $missingid = (int) $question->id;

        version_deleter::delete($missingid);
        $this->assertFalse($DB->record_exists('question', ['id' => $missingid]));

        // Deleting what has gone is harmless.
        question_delete_question($missingid);

        // Asking permission to edit it is not.
        try {
            question_require_capability_on($missingid, 'edit');
            $this->fail('The capability check was expected to reject a missing question.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Can\'t find data record in database', $e->getMessage());
        }

        // The debugging() call core makes on the way to its fallback query.
        $this->resetDebugging();
    }

    /**
     * A Cloze subquestion's own bank entry is not planned for cleaning.
     *
     * The subquestion entries are what produced the cascades in the first
     * place. Omitting them means the parent does the work once, in the only
     * order it can be done in, instead of the walk revisiting versions that
     * have already gone.
     */
    public function test_subquestion_entries_are_left_out_of_the_plan(): void {
        global $DB;

        $category = $this->generator->create_question_category(['contextid' => $this->contextid]);
        $parent = $this->generator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $this->generator->update_question($parent, 'twosubq', ['name' => 'Second version']);

        $parententry = (int) $DB->get_field('question_versions', 'questionbankentryid',
            ['questionid' => $parent->id], MUST_EXIST);
        $childids = $DB->get_fieldset_select('question', 'id', 'parent = ?', [$parent->id]);
        $this->assertNotEmpty($childids);
        $childentries = $DB->get_fieldset_select(
            'question_versions',
            'DISTINCT questionbankentryid',
            'questionid ' . $DB->get_in_or_equal($childids, SQL_PARAMS_QM)[0],
            $childids
        );

        $entries = version_deleter::entries_with_old_versions($this->contextid);

        $this->assertArrayHasKey($parententry, $entries, 'The Cloze parent still gets cleaned.');
        foreach ($childentries as $childentry) {
            $this->assertArrayNotHasKey(
                (int) $childentry,
                $entries,
                'A subquestion entry must not be planned in its own right.'
            );
        }
    }

    /**
     * An ordinary question with several versions is still planned.
     *
     * The guard above excludes entries by what they hold, so this checks it
     * has not simply excluded everything.
     */
    public function test_an_ordinary_entry_with_old_versions_is_planned(): void {
        global $DB;

        $category = $this->generator->create_question_category(['contextid' => $this->contextid]);
        $question = $this->generator->create_question('shortanswer', null, ['category' => $category->id]);
        $this->generator->update_question($question, null, ['name' => 'Second version']);

        $entryid = (int) $DB->get_field('question_versions', 'questionbankentryid',
            ['questionid' => $question->id], MUST_EXIST);

        $entries = version_deleter::entries_with_old_versions($this->contextid);

        $this->assertArrayHasKey($entryid, $entries);
        $this->assertSame('2', (string) $entries[$entryid]->versioncount);
    }

    /**
     * An entry with only one version has nothing to clean.
     */
    public function test_a_single_version_entry_is_not_planned(): void {
        global $DB;

        $category = $this->generator->create_question_category(['contextid' => $this->contextid]);
        $question = $this->generator->create_question('shortanswer', null, ['category' => $category->id]);
        $entryid = (int) $DB->get_field('question_versions', 'questionbankentryid',
            ['questionid' => $question->id], MUST_EXIST);

        $this->assertArrayNotHasKey($entryid, version_deleter::entries_with_old_versions($this->contextid));
    }
}
