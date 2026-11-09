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
 * Unit tests for the CodeRunner 6 question authoring form.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype;
use local_coderunner_manager\prototype_candidates;
use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/type/coderunner/edit_coderunner_form.php');

/**
 * Tests the question form: that it builds, that it offers every field the
 * registry holds, and that it reads back a prototype reference and an overrides
 * document rather than the CodeRunner 5 columns.
 *
 * Building the form is where most of what can go wrong does: a missing language
 * string, a help button pointing at nothing, an element type MoodleQuickForm
 * does not have, or a `disabledIf` naming an element that is not there. All of
 * those fail when an author opens the page, not when a question is saved.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner_edit_form::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class question_form_test extends \advanced_testcase {
    /** @var prototype_repository prototype storage. */
    private prototype_repository $repo;

    /** @var \stdClass the course whose bank holds the question. */
    private \stdClass $course;

    /** @var \stdClass the question category. */
    private \stdClass $category;

    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->repo = new prototype_repository();
        $this->course = $this->getDataGenerator()->create_course();
        // The question bank's own form callbacks read the page's URL when the
        // form is for a question that already exists, which the real page has
        // set long before it builds a form.
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $generator->create_question_category([
            'contextid' => \context_course::instance($this->course->id)->id,
        ]);
        prototype_candidates::reset_caches();
    }

    /**
     * Create a prototype in the site scope.
     *
     * @param string $name its name.
     * @param array $overrides what it supplies.
     * @param int|null $parentid its parent, if any.
     * @return prototype the prototype.
     */
    private function make_prototype(string $name, array $overrides = [], ?int $parentid = null): prototype {
        return $this->repo->create(
            $name,
            (int) SITEID,
            new override_set($overrides + ['template' => '{{ STUDENT_ANSWER }}'], $name),
            $parentid,
            'A prototype for testing.'
        );
    }

    /**
     * Build the question form for a new question, or for one already saved.
     *
     * @param \stdClass|null $questiondata an existing question, or null for a new one.
     * @return \qtype_coderunner_edit_form the form.
     */
    private function build_form(?\stdClass $questiondata = null): \qtype_coderunner_edit_form {
        $catcontext = \context::instance_by_id($this->category->contextid);
        $contexts = new \core_question\local\bank\question_edit_contexts($catcontext);

        if ($questiondata === null) {
            $questiondata = (object) [
                'createdby' => 2,
                'qtype' => 'coderunner',
                'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
            ];
        }
        $questiondata->contextid = $catcontext->id;
        $questiondata->beingcopied = false;
        $questiondata->category = $this->category->id;
        $questiondata->formoptions = (object) [
            'canmove' => true,
            'cansaveasnew' => true,
            'canedit' => true,
            'repeatelements' => true,
        ];

        // Built directly rather than through question_test_helper, which
        // copies a handful of fields onto a fresh object and so always builds
        // the form for a *new* question — with no options, and therefore no
        // prototype, no overrides and no test cases to read.
        return new \qtype_coderunner_edit_form('question.php', $questiondata, $this->category, $contexts, true);
    }

    /**
     * The MoodleQuickForm inside a form.
     *
     * @param \moodleform $form the form.
     * @return \MoodleQuickForm the inner form.
     */
    private function quickform(\moodleform $form): \MoodleQuickForm {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * Create a CodeRunner question in the category, built on the given
     * prototype.
     *
     * @param string $prototypename the prototype's name.
     * @return \stdClass the question data, options and all.
     */
    private function make_question(string $prototypename): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $question = $generator->create_question('coderunner', 'sqr', [
            'category' => $this->category->id,
            'coderunnertype' => $prototypename,
        ]);
        prototype_candidates::reset_caches();

        return \question_bank::load_question_data($question->id);
    }

    /**
     * The form for a new question builds, and offers the picker, the sample
     * answer and every settings section.
     */
    public function test_the_form_for_a_new_question_builds(): void {
        $this->make_prototype('python3');

        $mform = $this->quickform($this->build_form());

        $this->assertTrue($mform->elementExists(prototype_picker::GROUP));
        $this->assertTrue($mform->elementExists('answer'));
        $this->assertTrue($mform->elementExists('answerpreload'));
        $this->assertTrue($mform->elementExists('datafiles'));

        foreach (\qtype_coderunner_edit_form::sections() as $section) {
            if ($section === form_section::QUESTION) {
                // Rendered at the top of the form beside the test cases rather
                // than as a collapsible section of its own.
                continue;
            }
            $this->assertTrue(
                $mform->elementExists('section_' . $section->value),
                "No header for the {$section->value} section."
            );
        }
    }

    /**
     * Every field of the registry is offered, each either as a plain control
     * or as a control with an override checkbox beside it.
     *
     * This is the question-form half of the rule that the field set comes from
     * the current code: the control inventory records what CodeRunner 5
     * offered, and this asserts the new form offers all of it.
     */
    public function test_every_field_is_offered(): void {
        $this->make_prototype('python3');

        $mform = $this->quickform($this->build_form());

        $missing = [];
        foreach (field_registry::all() as $name => $field) {
            if ($field->type === field_type::FILES) {
                // The support files have a file manager of their own.
                $this->assertTrue($mform->elementExists('datafiles'));
                continue;
            }
            if (!$mform->elementExists($name) && !$mform->elementExists($name . '_group')) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Registry fields the question form does not offer.');
    }

    /**
     * An inheritable field gets an override checkbox; a field of the question
     * alone does not, because there is nothing above it to inherit from.
     */
    public function test_only_inheritable_fields_get_an_override_control(): void {
        $this->make_prototype('python3');

        $mform = $this->quickform($this->build_form());

        // An inheritable field's control, override checkbox and provenance note
        // share a row, so the group is what the form holds.
        $this->assertTrue($mform->elementExists('template_group'));
        // A field of the question alone gets no group and no checkbox: there is
        // nothing above it to inherit from.
        $this->assertFalse($mform->elementExists('answer_group'));
        $this->assertFalse($mform->elementExists('showsource_group'));
        $this->assertFalse($mform->elementExists(settings_form::INHERIT_PREFIX . 'answer'));
    }

    /**
     * The form for a saved question builds, and the picker shows the prototype
     * the question is built on.
     */
    public function test_the_form_for_a_saved_question_builds(): void {
        $prototype = $this->make_prototype('python3');
        $questiondata = $this->make_question('python3');

        $this->assertEquals($prototype->id, $questiondata->options->prototypeid);

        $form = $this->build_form($questiondata);
        $mform = $this->quickform($form);

        $this->assertTrue($mform->elementExists(prototype_picker::GROUP));
    }

    /**
     * A question whose prototype has gone still gives a form, so that an
     * author can point it at one that works rather than meeting a stack trace.
     */
    public function test_a_question_with_no_prototype_still_builds(): void {
        $this->make_prototype('python3');
        $questiondata = $this->make_question('python3');
        $questiondata->options->prototypeid = null;

        $mform = $this->quickform($this->build_form($questiondata));

        $this->assertTrue($mform->elementExists(prototype_picker::GROUP));
    }

    /**
     * A section header counts the fields the question overrides in it, so a
     * closed section cannot hide a change.
     */
    public function test_a_section_header_counts_its_overrides(): void {
        $this->make_prototype('python3');
        $questiondata = $this->make_question('python3');
        $questiondata->options->overrides = json_encode([
            'template' => 'MY TEMPLATE',
            'grader' => 'TemplateGrader',
        ]);

        $mform = $this->quickform($this->build_form($questiondata));
        $header = $mform->getElement('section_' . form_section::TEMPLATE->value);

        $this->assertStringContainsString(
            get_string('sectionchanged', 'qtype_coderunner', 2),
            (string) $header->_text
        );
    }

    /**
     * An override that restates what the field would inherit is not counted as
     * a change.
     *
     * This is what makes the counts mean anything on a migrated question. The
     * migration pins twenty fields whether or not the value differs from what
     * the chain supplies, so counting overrides reported five of the eight
     * sections as changed on every pre-6.0 question — including fields holding
     * exactly the value they would have had anyway. Measured on the
     * development site's own data, the typical question has twenty overrides
     * of which six or seven are real.
     */
    public function test_a_redundant_override_is_not_counted(): void {
        $prototype = $this->make_prototype('python3', ['grader' => 'TemplateGrader']);
        $questiondata = $this->make_question('python3');
        $questiondata->options->overrides = json_encode([
            // Exactly what the prototype supplies, so it changes nothing.
            'grader' => 'TemplateGrader',
            // And one that really does differ.
            'template' => 'MY TEMPLATE',
        ]);

        $mform = $this->quickform($this->build_form($questiondata));
        $header = $mform->getElement('section_' . form_section::TEMPLATE->value);

        $this->assertStringContainsString(
            get_string('sectionchanged', 'qtype_coderunner', 1),
            (string) $header->_text
        );
        $this->assertNotEmpty($prototype->id);
    }

    /**
     * The question the form builds for validation carries its test cases.
     *
     * Without them the sample answer run dies in `jobrunner.php` on a null
     * test case list, which is what happens the moment an author ticks
     * *Validate on save* — so this is the invariant that keeps the form able to
     * save at all. It is `clean_question_form()` that turns the submitted
     * arrays into `$question->testcases`, and the same call nulls a sandbox of
     * `DEFAULT` and tidies the penalty regime.
     */
    public function test_the_validation_question_carries_its_test_cases(): void {
        $this->make_prototype('python3');
        $questiondata = $this->make_question('python3');

        $form = $this->build_form($questiondata);
        $data = [
            'category' => $this->category->id . ',' . $this->category->contextid,
            'questiontext' => ['text' => 'Write sqr.', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            prototype_picker::FIELD => $questiondata->options->prototypeid,
            'testcode' => ["print(sqr(2))", "print(sqr(-3))"],
            'stdin' => ['', ''],
            'expected' => ["4\n", "9\n"],
            'extra' => ['', ''],
            'mark' => ['1.0', '1.0'],
            'ordering' => ['10', '20'],
            'useasexample' => ['1', '0'],
            'display' => ['SHOW', 'SHOW'],
            'hiderestiffail' => ['0', '0'],
            'sandbox' => 'DEFAULT',
            'penaltyregime' => '10%,20%, ...',
            settings_form::INHERIT_PREFIX . 'penaltyregime' => 0,
        ];

        $method = new \ReflectionMethod($form, 'make_question_from_form_data');
        $method->setAccessible(true);
        $question = $method->invoke($form, $data);

        $this->assertCount(2, $question->testcases);
        $this->assertSame("print(sqr(2))", $question->testcases[0]->testcode);
        // The other two jobs of the same call.
        $this->assertNull($question->sandbox);
        $this->assertSame('10, 20, ...', $question->penaltyregime);
    }

    /**
     * The test case columns a question's chain switches off are not offered,
     * and the ones it leaves on are.
     */
    public function test_the_test_case_field_switches_decide_the_columns(): void {
        $this->make_prototype('python3', ['showstdin' => 0, 'showtestextra' => 0]);
        $questiondata = $this->make_question('python3');

        $mform = $this->quickform($this->build_form($questiondata));

        $this->assertTrue($mform->elementExists('testcode[0]'));
        $this->assertTrue($mform->elementExists('expected[0]'));
        $this->assertFalse($mform->elementExists('stdin[0]'));
        $this->assertFalse($mform->elementExists('extra[0]'));
    }
}
