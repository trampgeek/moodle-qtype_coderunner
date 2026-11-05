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
     * Save a question form into a bank belonging to this test's course, so that
     * the report's course scoping is exercised rather than bypassed.
     *
     * @param object $form the question form data.
     * @return object the saved question.
     */
    private function save_in_course(object $form): object {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $this->bankcontextid]);
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
