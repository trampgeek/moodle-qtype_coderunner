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
// The form's own base class, which edit_coderunner_form.php does not pull in
// for itself: without this the file loads only when some other test in the
// suite has required it first, and running this one alone fails with
// Class "question_edit_form" not found.
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
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

        $components = settings_form::components_of(field_registry::all());
        $missing = [];
        foreach (field_registry::all() as $name => $field) {
            if ($field->type === field_type::FILES) {
                // The support files have a file manager of their own.
                $this->assertTrue($mform->elementExists('datafiles'));
                continue;
            }
            if (isset($components[$name])) {
                // A component is offered inside the group of the field it
                // belongs to — the language and the per-try switch sit beside
                // the parameters they describe — so it is neither an element
                // of its own nor a group of its own.
                if (!$this->is_group_member($mform, $name)) {
                    $missing[] = $name;
                }
                continue;
            }
            if (!$mform->elementExists($name) && !$mform->elementExists($name . '_group')) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Registry fields the question form does not offer.');
    }

    /**
     * Whether a component field is offered inside the group of the field it
     * belongs to.
     *
     * @param \MoodleQuickForm $mform the built form.
     * @param string $name the component's name.
     * @return bool true if it is there.
     */
    private function is_group_member(\MoodleQuickForm $mform, string $name): bool {
        foreach (field_registry::all() as $principal) {
            if (!in_array($name, $principal->perlayerwith, true)) {
                continue;
            }
            if (!$mform->elementExists($principal->name . '_group')) {
                return false;
            }
            foreach ($mform->getElement($principal->name . '_group')->getElements() as $element) {
                if ($element->getName() === $name) {
                    return true;
                }
            }
        }
        return false;
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
        $question = $method->invoke($form, $data);

        $this->assertCount(2, $question->testcases);
        $this->assertSame("print(sqr(2))", $question->testcases[0]->testcode);
        // The other two jobs of the same call.
        $this->assertNull($question->sandbox);
        $this->assertSame('10, 20, ...', $question->penaltyregime);
    }

    /**
     * A memory limit that is not a number is refused.
     *
     * It reaches validation through a resolved question, and resolution casts
     * an integer field, so the text arrived as `(int)` of itself — 0, which
     * for this field is the legal value meaning *no limit imposed*. So the
     * typo passed every check and was saved as "unlimited" silently. A zero
     * the author really typed still has to be accepted, which is what makes
     * the two indistinguishable once cast and why the submitted text is read.
     *
     * @param string $submitted what the author typed in the box.
     * @param bool $refused whether the form should refuse it.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('memory_limit_provider')]
    public function test_a_bad_memory_limit_is_refused(string $submitted, bool $refused): void {
        $this->make_prototype('python3');
        $questiondata = $this->make_question('python3');

        $form = $this->build_form($questiondata);
        $errors = $form->validation([
            'category' => $this->category->id . ',' . $this->category->contextid,
            'name' => 'Memory limit',
            'questiontext' => ['text' => 'Write sqr.', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            prototype_picker::FIELD => $questiondata->options->prototypeid,
            'memlimitmb' => $submitted,
            settings_form::INHERIT_PREFIX . 'memlimitmb' => 0,
            'penaltyregime' => '10, 20, ...',
            settings_form::INHERIT_PREFIX . 'penaltyregime' => 0,
            'validateonsave' => 0,
            // The test case columns, which validation reads whatever else it
            // is checking.
            'testcode' => ['print(sqr(2))'],
            'stdin' => [''],
            'expected' => ["4\n"],
            'extra' => [''],
            'mark' => ['1.0'],
            'ordering' => ['10'],
            'useasexample' => ['0'],
            'display' => ['SHOW'],
            'hiderestiffail' => ['0'],
        ], []);

        if ($refused) {
            $this->assertArrayHasKey('memlimitmb_group', $errors);
            $this->assertSame(
                get_string('badmemlimit', 'qtype_coderunner'),
                $errors['memlimitmb_group']
            );
        } else {
            $this->assertArrayNotHasKey('memlimitmb_group', $errors);
        }
    }

    /**
     * Memory limits, and whether each should be refused.
     *
     * @return array the cases.
     */
    public static function memory_limit_provider(): array {
        return [
            'not a number' => ['notanumber', true],
            'negative' => ['-5', true],
            'trailing rubbish' => ['512mb', true],
            'zero means no limit' => ['0', false],
            'blank means unset' => ['', false],
            'a real limit' => ['512', false],
        ];
    }

    /**
     * The value the form holds for an element, as it would be rendered.
     *
     * @param \MoodleQuickForm $mform the form.
     * @param string $name the element's name.
     * @return string the value, with a select's single selection unwrapped.
     */
    private function element_value(\MoodleQuickForm $mform, string $name): string {
        // The form's own default, read from the bag rather than through
        // getElementValue(): that is another of the PEAR methods that throw
        // under PHP 8 -- `PEAR::getStaticProperty() cannot be called
        // statically` -- as toHtml() does.
        $property = new \ReflectionProperty(get_class($mform), '_defaultValues');
        $value = $property->getValue($mform)[$name] ?? null;
        if (is_array($value)) {
            $value = reset($value);
        }
        return (string) $value;
    }

    /**
     * A question's own test cases, reloaded after a change behind the form's back.
     *
     * @param int $questionid the question.
     * @return \stdClass the question data.
     */
    private function reload_question(int $questionid): \stdClass {
        \question_bank::notify_question_edited($questionid);
        return \question_bank::load_question_data($questionid);
    }

    /**
     * Drops one field from a question's overrides, so that it is inherited.
     *
     * The `sqr` fixture pins `uiplugin` to *None*, as a test question wants to
     * -- which makes it the overridden case rather than the inherited one.
     *
     * @param int $questionid the question.
     * @param string $field the field to stop overriding.
     * @return \stdClass the reloaded question data.
     */
    private function inherit_field(int $questionid, string $field): \stdClass {
        global $DB;

        $stored = $DB->get_field('question_coderunner_options', 'overrides', ['questionid' => $questionid]);
        $overrides = json_decode((string) $stored, true) ?: [];
        unset($overrides[$field]);
        $DB->set_field(
            'question_coderunner_options',
            'overrides',
            json_encode($overrides),
            ['questionid' => $questionid]
        );

        return $this->reload_question($questionid);
    }

    /**
     * Choosing a different prototype re-points the fields the question inherits.
     *
     * The picker rebuilds the form server-side, and core's
     * `question_edit_form::set_data()` flattens the question's *stored* options
     * onto it afterwards -- so without `repoint_inherited_fields()` every
     * inherited field answers for the prototype the question was saved with.
     * It showed as the answer box keeping the old UI, which is why `uiplugin`
     * is the field asserted here, but it was never confined to that one.
     */
    public function test_a_change_of_prototype_repoints_the_inherited_fields(): void {
        $ace = $this->make_prototype('python3', ['uiplugin' => 'ace', 'language' => 'python3']);
        $this->make_prototype('python3_scratchpad', ['uiplugin' => 'scratchpad', 'language' => 'python3']);
        $questiondata = $this->make_question('python3_scratchpad');
        $questiondata = $this->inherit_field((int) $questiondata->id, 'uiplugin');

        $mform = $this->quickform($this->build_form($questiondata));
        $this->assertSame(
            'scratchpad',
            $this->element_value($mform, 'uiplugin'),
            'As saved, the question inherits the scratchpad UI.'
        );

        // As the picker's reload submits it.
        $_POST['prototypeid'] = (string) $ace->id;
        try {
            $form = $this->build_form($questiondata);
            $form->set_data($questiondata);
            $this->assertSame(
                'ace',
                $this->element_value($this->quickform($form), 'uiplugin'),
                'After the change it answers for the prototype just chosen.'
            );
        } finally {
            unset($_POST['prototypeid']);
        }
    }

    /**
     * A field the question overrides survives a change of prototype.
     *
     * Its value is the question's own, so a new chain does not touch it. This
     * is the other half of the rule, and the half a careless fix would break.
     */
    public function test_a_change_of_prototype_leaves_an_overridden_field_alone(): void {
        global $DB;

        $ace = $this->make_prototype('python3', ['uiplugin' => 'ace']);
        $this->make_prototype('python3_scratchpad', ['uiplugin' => 'scratchpad']);
        $questiondata = $this->make_question('python3_scratchpad');

        $DB->set_field(
            'question_coderunner_options',
            'overrides',
            json_encode(['uiplugin' => 'graph']),
            ['questionid' => $questiondata->id]
        );
        $questiondata = $this->reload_question((int) $questiondata->id);

        $_POST['prototypeid'] = (string) $ace->id;
        try {
            $form = $this->build_form($questiondata);
            $form->set_data($questiondata);
            $this->assertSame(
                'graph',
                $this->element_value($this->quickform($form), 'uiplugin'),
                "The author's own choice is not replaced by the new chain's."
            );
        } finally {
            unset($_POST['prototypeid']);
        }
    }

    /**
     * A test case column with something in it is offered whatever its switch says.
     *
     * The switches govern the form and not the data, so a column turned off
     * after it was filled in went on reaching the template with no way for its
     * author to see it or clear it.
     */
    public function test_a_test_case_column_in_use_is_offered_despite_its_switch(): void {
        global $DB;

        $this->make_prototype('python3', ['showstdin' => 0, 'showtestextra' => 0]);
        $questiondata = $this->make_question('python3');

        $first = reset($questiondata->options->testcases);
        $DB->set_field(
            'question_coderunner_tests',
            'extra',
            '# this is read by the template',
            ['id' => $first->id]
        );
        $questiondata = $this->reload_question((int) $questiondata->id);

        $mform = $this->quickform($this->build_form($questiondata));

        $this->assertTrue(
            $mform->elementExists('extra[0]'),
            'The column holds something, so it is offered however its switch is set.'
        );
        $this->assertFalse(
            $mform->elementExists('stdin[0]'),
            'The one that is both switched off and empty stays hidden.'
        );
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

    /**
     * A question whose prototype cannot be found says so, and will not save.
     *
     * The picker falls back to the first prototype of the course when it is
     * handed nothing, so such a question presented as though the author had
     * chosen a type they had not. Saving took that choice:
     * `set_legacy_columns()` writes `coderunnertype` from the chosen
     * prototype, so the name of the type the question actually wants -- the
     * only record of it, and what the migration sweep resolves against --
     * was overwritten. Silent, and unrecoverable afterwards.
     *
     * The banner is checked by rendering; what cannot be seen that way, and
     * is the half that matters, is that the save is refused.
     */
    public function test_a_question_with_no_prototype_says_so_and_will_not_save(): void {
        $this->make_prototype('python3');
        $questiondata = $this->make_question('python3');
        // What an import, or a restore of a course whose prototypes have not
        // been snapshotted yet, leaves behind.
        $questiondata->options->prototypeid = null;

        $form = $this->build_form($questiondata);

        $this->assertTrue(
            $this->quickform($form)->elementExists(\qtype_coderunner_edit_form::MISSING_PROTOTYPE_ELEMENT),
            'A question with no prototype was shown no banner saying so.'
        );

        $errors = $form->validation([
            'category' => $this->category->id . ',' . $this->category->contextid,
            'name' => 'Broken question',
            'questiontext' => ['text' => 'Write sqr.', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            // The first prototype of the course, which is what the picker
            // offers and what an unwary save would adopt.
            prototype_picker::FIELD => $this->make_prototype('aaa_first')->id,
        ], []);

        $this->assertArrayHasKey(
            \qtype_coderunner_edit_form::MISSING_PROTOTYPE_ELEMENT,
            $errors,
            'A question with no prototype was allowed to save, adopting a type nobody chose.'
        );
    }
}
