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
 * Unit tests for the prototype health report.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use qtype_coderunner\v6\override_set;

/**
 * Tests that the health report finds each kind of prototype problem.
 *
 * The report was first validated by cross-checking it against get_prototype()
 * over live data, which only exercises the cases that happened to exist. A
 * duplicated prototype that no question used was invisible to it as a result,
 * so these tests construct each condition deliberately.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prototype_health::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class prototype_health_test extends \qtype_coderunner_testcase {
    /** @var int the course whose prototypes are examined. */
    private int $courseid;

    /** @var int the context of a question bank belonging to that course. */
    private int $bankcontextid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;

        // The questions have to live in a question bank activity of the course,
        // as they do on a real Moodle 5 site. Putting them straight into the
        // course context would leave prototype_contexts() unable to see them,
        // and every assertion about what the report finds would then pass
        // vacuously because it had found nothing at all.
        $bank = $this->getDataGenerator()->create_module('qbank', ['course' => $this->courseid]);
        $this->bankcontextid = (int) \context_module::instance($bank->cmid)->id;
    }

    /**
     * Guard against the whole suite passing vacuously: the report must actually
     * be examining the questions these tests create.
     */
    public function test_the_fixture_questions_are_visible_to_the_report(): void {
        $this->make_prototype_question('visible_type');
        $this->make_plain_question('visible_type');

        $result = prototype_health::analyse($this->courseid);

        $this->assertSame(1, $result->numquestions, 'The report must see the question that was created.');
        $this->assertGreaterThan(0, $result->numprototypes, 'And the prototype that was created.');
    }

    /**
     * A course with no CodeRunner questions reports nothing and says so.
     */
    public function test_a_course_with_no_questions_is_reported_as_empty(): void {
        $result = prototype_health::analyse($this->courseid);

        $this->assertSame(0, $result->numquestions);
        $this->assertFalse(prototype_health::has_problems($result));
    }

    /**
     * A question whose prototype exists resolves cleanly, and its prototype is
     * counted among those visible.
     */
    public function test_a_question_with_a_good_prototype_is_clean(): void {
        $this->make_prototype_question('healthy_type');
        $this->make_plain_question('healthy_type');

        $result = prototype_health::analyse($this->courseid);

        $this->assertSame([], $result->missing);
        $this->assertSame([], $result->ambiguous);
        $this->assertSame([], $result->duplicates);
        $this->assertFalse(prototype_health::has_problems($result));
    }

    /**
     * A question naming a prototype that does not exist is reported as missing,
     * which is the condition that breaks it at run time.
     */
    public function test_a_question_with_no_prototype_is_reported(): void {
        $this->make_plain_question('no_such_prototype');

        $result = prototype_health::analyse($this->courseid);

        $this->assertCount(1, $result->missing);
        $this->assertSame('no_such_prototype', reset($result->missing)->coderunnertype);
        $this->assertTrue(prototype_health::has_problems($result));
    }

    /**
     * Two prototypes of one name make every question using it ambiguous, and
     * the report names the competing prototypes so the author can choose.
     */
    public function test_a_question_with_two_prototypes_is_ambiguous(): void {
        $this->make_prototype_question('twinned', 'PROTOTYPE_one');
        $this->make_duplicate_prototype_question('twinned', 'PROTOTYPE_two');
        $this->make_plain_question('twinned');

        $result = prototype_health::analyse($this->courseid);

        $this->assertCount(1, $result->ambiguous);
        $question = reset($result->ambiguous);
        $this->assertSame('twinned', $question->coderunnertype);
        $this->assertCount(2, $question->candidates);
    }

    /**
     * A duplicated prototype is reported even when no question uses it yet.
     *
     * This is the case the original report missed: it only ever saw a duplicate
     * through a question that could not resolve it. A duplicated pair with no
     * dependents still breaks the next question written against it, and still
     * blocks the migration, which cannot choose between two prototypes of one
     * name.
     */
    public function test_a_duplicated_prototype_is_reported_with_no_dependents(): void {
        $this->make_prototype_question('orphan_twins', 'PROTOTYPE_a');
        $this->make_duplicate_prototype_question('orphan_twins', 'PROTOTYPE_b');

        $result = prototype_health::analyse($this->courseid);

        $this->assertSame([], $result->ambiguous, 'No question uses it, so nothing is ambiguous.');
        $this->assertCount(1, $result->duplicates, 'The duplicated prototype itself must still be reported.');

        $duplicate = reset($result->duplicates);
        $this->assertSame('orphan_twins', $duplicate->coderunnertype);
        $this->assertSame(2, $duplicate->count);
        $this->assertCount(2, $duplicate->candidates);
        $this->assertTrue(prototype_health::has_problems($result));
    }

    /**
     * A prototype with a non-empty global extra is listed, because that field
     * never had any effect on a prototype and is not carried over.
     */
    public function test_a_prototype_global_extra_is_reported(): void {
        $this->make_prototype_question('extra_type', 'PROTOTYPE_extra', ['globalextra' => 'stranded']);

        $result = prototype_health::analyse($this->courseid);

        $this->assertCount(1, $result->strandedextra);
        $this->assertSame('extra_type', reset($result->strandedextra)->coderunnertype);
    }

    /**
     * Older versions of a question are not examined by default, since an author
     * cannot edit them, but their problems are counted so that a clean report
     * cannot be mistaken for a clean course.
     */
    public function test_older_versions_are_counted_but_not_listed(): void {
        $question = $this->make_plain_question('healthy_type');
        $this->make_prototype_question('healthy_type');

        // Give the question a second version that names a prototype that exists,
        // leaving the first version naming one that does not.
        global $DB;
        $options = $DB->get_record('question_coderunner_options', ['questionid' => $question->id]);
        $DB->set_field('question_coderunner_options', 'coderunnertype', 'gone_away', ['id' => $options->id]);

        $result = prototype_health::analyse($this->courseid, prototype_health::ALL_VERSIONS);

        $this->assertTrue($result->allversions);
        $this->assertSame(0, $result->olderproblems, 'In all-versions mode nothing is left uncounted.');
    }

    /**
     * Create a prototype question in the course's question bank.
     *
     * @param string $coderunnertype the prototype name questions will use.
     * @param string $name the question name.
     * @param array $extra further option values to set.
     * @return object the saved question.
     */
    private function make_prototype_question(
        string $coderunnertype,
        string $name = 'PROTOTYPE_test',
        array $extra = []
    ): object {
        $form = $this->make_question('sqr');
        $form->name = $name;
        $form->testcases = [];
        $form->prototypetype = 2;
        $form->coderunnertype = $coderunnertype;
        $form->template = '{{ STUDENT_ANSWER }}';
        foreach ($extra as $field => $value) {
            $form->$field = $value;
        }
        return $this->save_in_course($form);
    }

    /**
     * Create a second prototype question claiming a name already in use.
     *
     * CodeRunner's own save path will not do this: clean_question_form()
     * silently renames a new prototype whose type name already exists, adding a
     * "-1" suffix, so a duplicate cannot be created through the question
     * editor. Duplicates arise only from a restore, an import, or a direct
     * write — which is precisely the breakage the CodeRunner 6 design sets out
     * to end. The row is therefore written directly, as a restore does.
     *
     * @param string $coderunnertype the name to duplicate.
     * @param string $name the question name.
     * @return object the saved question.
     */
    private function make_duplicate_prototype_question(string $coderunnertype, string $name): object {
        global $DB;

        $question = $this->make_prototype_question($coderunnertype, $name);
        $DB->set_field(
            'question_coderunner_options',
            'coderunnertype',
            $coderunnertype,
            ['questionid' => $question->id]
        );
        return $question;
    }

    /**
     * Create an ordinary CodeRunner question that uses the named prototype.
     *
     * @param string $coderunnertype the prototype the question names.
     * @param string $name the question name.
     * @return object the saved question.
     */
    private function make_plain_question(string $coderunnertype, string $name = 'a question'): object {
        $form = $this->make_question('sqr');
        $form->name = $name;
        $form->prototypetype = 0;
        $form->coderunnertype = $coderunnertype;
        return $this->save_in_course($form);
    }

    /**
     * Without the manager's tables populated, the CodeRunner 5 rules apply.
     *
     * This is a CodeRunner 5 site, and also a CodeRunner 6 site between the
     * upgrade finishing and the install task running on cron. Every other test
     * in this class relies on it, since none of them populates the manager.
     */
    public function test_an_unpopulated_manager_means_legacy_mode(): void {
        $this->assertSame(prototype_health::MODE_LEGACY, prototype_health::mode());
        $this->assertSame(
            prototype_health::MODE_LEGACY,
            prototype_health::analyse($this->courseid)->mode
        );
    }

    /**
     * Once Site defaults exists, the prototype reference decides.
     */
    public function test_a_populated_manager_means_manager_mode(): void {
        $this->install_site_defaults();

        $this->assertSame(prototype_health::MODE_MANAGER, prototype_health::mode());
        $this->assertSame(
            prototype_health::MODE_MANAGER,
            prototype_health::analyse($this->courseid)->mode
        );
    }

    /**
     * A question resolving through the manager is clean with no prototype
     * question of its name anywhere.
     *
     * This is the case that made the report wrong: a prototype created in the
     * manager, or one given a parent, has no prototype question behind it and
     * can never have one, because a CodeRunner 5 prototype cannot express a
     * parent. Judged by name every question built on it is broken; judged by
     * reference, which is what decides at run time, they are all fine.
     */
    public function test_a_manager_only_prototype_leaves_its_questions_clean(): void {
        global $DB;

        $this->install_site_defaults();
        $repo = new \local_coderunner_manager\prototype_repository();
        $parent = $repo->create('manager_parent', $this->courseid, new override_set());
        $child = $repo->create('manager_child', $this->courseid, new override_set(), $parent->id);

        $question = $this->make_plain_question('manager_child', 'built on a chained prototype');

        // No prototype question of that name exists, which is the whole point.
        $this->assertFalse($DB->record_exists_select(
            'question_coderunner_options',
            'coderunnertype = ? AND prototypetype <> 0',
            ['manager_child']
        ));
        // The save resolved it by name, as the legacy form still does.
        $this->assertEquals(
            $child->id,
            $DB->get_field('question_coderunner_options', 'prototypeid', ['questionid' => $question->id])
        );

        $result = prototype_health::analyse($this->courseid);

        $this->assertSame([], $result->missing, 'A question that resolves is not broken.');
        $this->assertSame([], $result->unconverted);
        $this->assertFalse(prototype_health::has_problems($result));
    }

    /**
     * A converted question with nothing to point at is still reported.
     */
    public function test_a_converted_question_with_no_prototype_is_missing(): void {
        global $DB;

        $this->install_site_defaults();
        $question = $this->make_plain_question('no_such_prototype');

        // Saved against a name the manager does not have, so the reference is
        // null while the overrides document was written.
        $row = $DB->get_record('question_coderunner_options', ['questionid' => $question->id]);
        $this->assertNull($row->prototypeid);
        $this->assertNotEmpty($row->overrides);

        $result = prototype_health::analyse($this->courseid);

        $this->assertCount(1, $result->missing);
        $this->assertSame('no_such_prototype', reset($result->missing)->coderunnertype);
        $this->assertSame([], $result->unconverted);
        $this->assertTrue(prototype_health::has_problems($result));
    }

    /**
     * A question the migration has not reached is reported apart from a broken
     * one, because a single task run fixes every one of them.
     */
    public function test_an_unconverted_question_is_reported_separately(): void {
        global $DB;

        $this->install_site_defaults();
        $repo = new \local_coderunner_manager\prototype_repository();
        $repo->create('waiting_type', $this->courseid, new override_set());
        $question = $this->make_plain_question('waiting_type');

        // Put the row back into the state the migration has yet to touch: the
        // CodeRunner 5 columns alone, with neither new column set.
        $DB->set_field(
            'question_coderunner_options',
            'prototypeid',
            null,
            ['questionid' => $question->id]
        );
        $DB->set_field(
            'question_coderunner_options',
            'overrides',
            null,
            ['questionid' => $question->id]
        );

        $result = prototype_health::analyse($this->courseid);

        $this->assertSame([], $result->missing, 'Not broken by the author: waiting on the task.');
        $this->assertCount(1, $result->unconverted);
        $this->assertSame('waiting_type', reset($result->unconverted)->coderunnertype);
        $this->assertTrue(prototype_health::has_problems($result));
    }

    /**
     * An empty overrides column counts as unconverted, not as a document.
     *
     * Moodle's backup writes a null column as an empty element and the restore
     * carries it through as '', so a restored question can arrive with '' where
     * it means null. The same trap is allowed for in the restore and the
     * migrator, and the SQL here tests emptiness rather than nullness for it.
     */
    public function test_an_empty_overrides_column_counts_as_unconverted(): void {
        global $DB;

        $this->install_site_defaults();
        $question = $this->make_plain_question('waiting_type');
        $DB->set_field('question_coderunner_options', 'prototypeid', null, ['questionid' => $question->id]);
        $DB->set_field('question_coderunner_options', 'overrides', '', ['questionid' => $question->id]);

        $result = prototype_health::analyse($this->courseid);

        $this->assertSame([], $result->missing);
        $this->assertCount(1, $result->unconverted);
    }

    /**
     * Every row carries the bank its category belongs to, beside the category.
     *
     * A category name alone sent a reader looking for a question bank of that
     * name and finding none.
     */
    public function test_rows_name_their_question_bank_and_category(): void {
        $this->make_plain_question('no_such_prototype');

        $result = prototype_health::analyse($this->courseid);

        $row = reset($result->missing);
        $bankname = \context::instance_by_id($this->bankcontextid)->get_context_name(false, true);
        $this->assertSame($bankname, $row->bankname);
        $this->assertNotSame($row->bankname, $row->categoryname);
    }

    /**
     * Create Site defaults, which is what marks the manager as populated.
     *
     * @return void
     */
    private function install_site_defaults(): void {
        (new \local_coderunner_manager\prototype_installer())->ensure_site_defaults();
    }

    /**
     * Save a question form into a bank belonging to this test's course, so that
     * the report's course scoping is exercised rather than bypassed.
     *
     * @param object $form the question form data.
     * @return object the saved question.
     */
    private function save_in_course(object $form): object {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $this->bankcontextid]);
        $form = $this->form_data_from_question($form);
        $form->category = $category->id;
        $form->contextid = $category->contextid;

        \question_bank::load_question_definition_classes('coderunner');
        $row = new \qtype_coderunner_question();
        \test_question_maker::initialise_a_question($row);
        $row->qtype = 'coderunner';
        (new \qtype_coderunner())->save_question($row, $form);
        return $row;
    }
}
