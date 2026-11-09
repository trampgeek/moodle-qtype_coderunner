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
 * Unit tests for the registry-driven settings form component.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

defined('MOODLE_INTERNAL') || die();

/**
 * The component turns a layer's overrides into form controls and the author's
 * answer back into overrides. These tests cover the reading-back half, which is
 * where the rule that reverting drops an override actually lives, and the
 * registry-driven choices that decide what control each field gets.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(settings_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(field_registry::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class settings_form_test extends \advanced_testcase {
    /**
     * A field whose override box is ticked is recorded with the submitted
     * value.
     */
    public function test_a_ticked_field_becomes_an_override(): void {
        $overrides = settings_form::overrides_from(
            ['template' => 'MY TEMPLATE', 'override_template' => 1],
            [form_section::TEMPLATE],
            new override_set([], 'p')
        );

        $this->assertSame('MY TEMPLATE', $overrides->get('template'));
    }

    /**
     * Clearing the box drops the override, and drops the stored value with it.
     * This is the whole of the revert behaviour: nothing carries the old value
     * forward, so an overrides document never holds a value that is not in
     * effect.
     */
    public function test_clearing_the_box_drops_the_override_and_its_value(): void {
        $before = new override_set(['template' => 'OLD TEMPLATE'], 'p');

        $after = settings_form::overrides_from(
            ['template' => 'OLD TEMPLATE', 'override_template' => 0],
            [form_section::TEMPLATE],
            $before
        );

        $this->assertFalse($after->has('template'));
        $this->assertArrayNotHasKey('template', $after->to_array());
    }

    /**
     * An empty string is a real override, not an absence. Blanking a field is
     * how an author says "this prototype supplies nothing here", and it has to
     * survive a round trip through the form.
     */
    public function test_an_empty_value_is_still_an_override(): void {
        $overrides = settings_form::overrides_from(
            ['template' => '', 'override_template' => 1],
            [form_section::TEMPLATE],
            new override_set([], 'p')
        );

        $this->assertTrue($overrides->has('template'));
        $this->assertSame('', $overrides->get('template'));
    }

    /**
     * A form that shows only some sections must not drop the overrides of the
     * sections it did not show.
     */
    public function test_sections_that_were_not_shown_are_left_alone(): void {
        $before = new override_set(['template' => 'KEEP ME', 'penaltyregime' => '0, 10, ...'], 'p');

        $after = settings_form::overrides_from(
            ['penaltyregime' => '20, 40, ...', 'override_penaltyregime' => 1],
            [form_section::GRADING],
            $before
        );

        $this->assertSame('KEEP ME', $after->get('template'), 'An unshown section lost its override.');
        $this->assertSame('20, 40, ...', $after->get('penaltyregime'));
    }

    /**
     * Values come back cast to the registry's type, so a checkbox's '1' is
     * stored as an integer and compares equal to a resolved 1.
     */
    public function test_values_are_cast_to_the_registry_type(): void {
        $overrides = settings_form::overrides_from(
            ['allornothing' => '1', 'override_allornothing' => 1],
            [form_section::GRADING],
            new override_set([], 'p')
        );

        $this->assertSame(1, $overrides->get('allornothing'));
    }

    /**
     * Support files are not offered as an ordinary control, because they are a
     * set of files and need a file manager.
     *
     * The exclusion is by type and not by whether the field has a column: the
     * test case field switches have no column, living in the overrides
     * document instead, and must still be offered.
     */
    public function test_only_file_fields_are_left_out(): void {
        $offered = [];
        foreach (form_section::cases() as $section) {
            foreach (settings_form::fields_of($section) as $name => $field) {
                $this->assertNotSame(field_type::FILES, $field->type, "Field $name needs a file manager.");
                $offered[] = $name;
            }
        }

        $this->assertContains('showtestcode', $offered, 'A field with no column was wrongly left out.');
        $this->assertNotContains('supportfiles', $offered);
    }

    /**
     * Every field the component would render a select for can supply its
     * values, and every field it would not must not claim to.
     */
    public function test_every_choice_field_has_values(): void {
        foreach (field_registry::all() as $name => $field) {
            $choices = field_registry::choices($name);
            if ($field->type === field_type::CHOICE) {
                $this->assertIsArray($choices, "Choice field $name supplied no values.");
                $this->assertNotEmpty($choices, "Choice field $name supplied an empty list.");
            } else {
                $this->assertNull($choices, "Field $name is not a choice but supplied values.");
            }
        }
    }

    /**
     * One element of a field's group, by name.
     *
     * The input, the override checkbox and the provenance note share a row —
     * the note reads as the checkbox's caption rather than a remark of its own
     * — so none of the three is a top-level element of the form.
     *
     * @param \MoodleQuickForm $mform the built form.
     * @param string $field the field name.
     * @param string $name the element wanted.
     * @return object|null the element, or null if the group has no such member.
     */
    private function group_member(\MoodleQuickForm $mform, string $field, string $name) {
        if (!$mform->elementExists($field . '_group')) {
            return null;
        }
        foreach ($mform->getElement($field . '_group')->getElements() as $element) {
            if ($element->getName() === $name) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Between them the sections account for every field that has a column, so
     * no field can be added to the registry and silently never appear on a
     * form.
     *
     * Asked of the question form's view, which is the one that shows
     * everything: the prototype editor is offered fewer fields on purpose, as
     * the next test says.
     */
    public function test_every_column_field_appears_in_some_section(): void {
        $offered = [];
        foreach (form_section::cases() as $section) {
            $offered = array_merge($offered, array_keys(settings_form::fields_of($section, true)));
        }

        $expected = array_keys(array_filter(
            field_registry::all(),
            fn($f) => $f->type !== field_type::FILES
        ));

        $this->assertSame([], array_values(array_diff($expected, $offered)));
    }

    /**
     * A prototype is offered no instance field, and the difference between the
     * two views is exactly the instance fields.
     *
     * This is not tidiness. `prototype_chain` refuses a chain in which any
     * layer but the last supplies an instance field, and a question whose chain
     * cannot be built reports itself broken — so a prototype that stored one
     * would break every question beneath it. The field that made this real is
     * `showsource`, the control labelled *Template debugging*, which belongs to
     * the question alone and sits in the Template section that the prototype
     * editor shows.
     */
    public function test_a_prototype_is_offered_no_instance_field(): void {
        $forprototype = [];
        $forquestion = [];
        foreach (form_section::cases() as $section) {
            $forprototype = array_merge($forprototype, array_keys(settings_form::fields_of($section)));
            $forquestion = array_merge($forquestion, array_keys(settings_form::fields_of($section, true)));
        }

        foreach ($forprototype as $name) {
            $this->assertTrue(
                field_registry::get($name)->is_inheritable(),
                "The prototype editor is offered '$name', which only a question has."
            );
        }

        $instancefields = array_keys(array_filter(
            field_registry::all(),
            fn($f) => !$f->is_inheritable() && $f->type !== field_type::FILES
        ));
        $this->assertSame(
            $instancefields,
            array_values(array_diff($forquestion, $forprototype)),
            'The two views should differ by exactly the instance fields.'
        );
    }

    /**
     * An instance field is never read back as an override, whichever layer is
     * being edited and whatever the form submitted.
     *
     * The write path refuses them as well as the rendering, so a form that
     * offers one by mistake still cannot store it. A question keeps its
     * instance fields in its own columns.
     */
    public function test_an_instance_field_is_never_an_override(): void {
        $submitted = [
            'showsource' => 1,
            settings_form::OVERRIDE_PREFIX . 'showsource' => 1,
            'answer' => 'print(42)',
            settings_form::OVERRIDE_PREFIX . 'answer' => 1,
            'template' => 'MY TEMPLATE',
            settings_form::OVERRIDE_PREFIX . 'template' => 1,
        ];

        $overrides = settings_form::overrides_from(
            $submitted,
            form_section::cases(),
            new override_set([], 'x'),
            'the question'
        );

        $this->assertFalse($overrides->has('showsource'));
        $this->assertFalse($overrides->has('answer'));
        // And the inheritable field beside them is stored as usual.
        $this->assertSame('MY TEMPLATE', $overrides->get('template'));
    }

    /**
     * An instance field on the question form gets its control, its label and
     * its help, and no override checkbox: there is no layer above it to inherit
     * from, so the checkbox would be a choice between a value and nothing.
     */
    public function test_an_instance_field_has_no_override_control(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $mform = new \MoodleQuickForm('test', 'post', '');
        settings_form::add_section(
            $mform,
            form_section::QUESTION,
            (new prototype_chain([]))->resolve(),
            new override_set([], 'the question'),
            '',
            true
        );

        $this->assertTrue($mform->elementExists('answer'));
        $this->assertTrue($mform->elementExists('validateonsave'));
        $this->assertFalse($mform->elementExists('answer_group'));
        $this->assertFalse($mform->elementExists(settings_form::OVERRIDE_PREFIX . 'answer'));
        $this->assertFalse($mform->elementExists('answer_source'));
    }

    /**
     * Every section renders without error, and each field ends up with an
     * input, an override checkbox and a note of where its value comes from.
     *
     * This exercises the half the other tests do not: that each registry type
     * maps to an element MoodleQuickForm actually has, that the help buttons
     * reference strings that exist, and that disabledIf accepts the pairing.
     * Those fail at render time, not at read-back.
     */
    public function test_every_section_renders(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['template' => 'INHERITED', 'uiplugin' => 'ace', 'sandbox' => 'DEFAULT'],
        ])->resolve();
        $own = new override_set(['template' => 'MINE'], 'the question');

        $rendered = 0;
        foreach (form_section::cases() as $section) {
            $fields = settings_form::fields_of($section);
            if (!$fields) {
                continue;
            }

            $mform = new \MoodleQuickForm('test', 'post', '');
            settings_form::add_section($mform, $section, $inherited, $own);

            foreach ($fields as $name => $field) {
                // A code field is added on its own so that Ace styles it
                // correctly; everything else is grouped with its override box.
                $this->assertTrue(
                    $mform->elementExists($field->iscode ? $name : $name . '_group'),
                    "Field $name rendered no control in section {$section->value}."
                );
                $this->assertNotNull(
                    $this->group_member($mform, $name, settings_form::OVERRIDE_PREFIX . $name),
                    "Field $name rendered no override control."
                );
                $this->assertNotNull(
                    $this->group_member($mform, $name, $name . '_source'),
                    "Field $name rendered no provenance note."
                );
                $rendered++;
            }
        }

        $this->assertGreaterThan(30, $rendered, 'Far fewer fields rendered than the registry holds.');
    }

    /**
     * An inherited field offers the value it would inherit, not an empty box,
     * so that clearing the override shows what the field falls back to rather
     * than going blank.
     */
    public function test_an_inherited_field_offers_the_inherited_value(): void {
        $inherited = prototype_chain::from_arrays([
            'python3' => ['penaltyregime' => '10, 20, ...'],
        ])->resolve();

        $this->assertSame(
            '10, 20, ...',
            settings_form::inherited_value(field_registry::get('penaltyregime'), $inherited)
        );
    }

    /**
     * A field no layer sets falls back to the registry default, so the box
     * shows what CodeRunner would actually use.
     */
    public function test_an_unset_field_offers_the_registry_default(): void {
        $inherited = prototype_chain::from_arrays([['template' => 'T']])->resolve();

        $this->assertSame(
            'EqualityGrader',
            settings_form::inherited_value(field_registry::get('grader'), $inherited)
        );
    }

    /**
     * A collected field has no single inherited value, since every layer
     * contributes. The nearest contribution is what the box shows.
     */
    public function test_a_collected_field_offers_the_nearest_contribution(): void {
        $inherited = prototype_chain::from_arrays([
            'python3' => ['templateparams' => '{"a": 1}'],
            'python3_scratchpad' => ['templateparams' => '{"b": 2}'],
        ])->resolve();

        $this->assertSame(
            '{"b": 2}',
            settings_form::inherited_value(field_registry::get('templateparams'), $inherited)
        );
    }

    /**
     * Code and JSON fields are marked as such, and short text fields are not.
     *
     * The mark decides which fields get an Ace editor, so a filename pattern
     * acquiring one, or a template losing one, is a visible regression.
     */
    public function test_the_code_fields_are_the_ones_holding_code(): void {
        $code = array_keys(array_filter(field_registry::all(), fn($f) => $f->iscode));

        sort($code);
        $this->assertSame([
            'answer',
            'answerpreload',
            'globalextra',
            'prototypeextra',
            'resultcolumns',
            'sandboxparams',
            'template',
            'templateparams',
            'uiparameters',
        ], $code);

        $this->assertFalse(field_registry::get('filenamesregex')->iscode, 'A filename pattern is not code.');
        $this->assertFalse(field_registry::get('penaltyregime')->iscode);
    }

    /**
     * Asking for Ace registers the scripts and a wrapper for each code field
     * on show, and touches nothing else.
     *
     * This runs the real path, so it fails if the question type renames
     * load_ace() or the wrapper module — the coupling decision 22 records as
     * undeclarable, and therefore worth a test.
     */
    public function test_requiring_ace_registers_the_question_types_wrapper(): void {
        global $PAGE;

        $this->resetAfterTest();
        $PAGE->set_url('/');

        settings_form::require_ace([form_section::TEMPLATE], 'python3');

        $this->assertTrue(
            method_exists(\qtype_coderunner_util::class, 'load_ace'),
            'The question type no longer offers load_ace(), which the manager depends on.'
        );
    }

    /**
     * An overridden field says what it would fall back to.
     *
     * Without this an author who clears the override has no way to see what
     * saving would give them: the control greys but goes on showing the value
     * they are discarding, because nothing on the page redraws.
     */
    public function test_an_overridden_field_names_the_value_it_would_fall_back_to(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['grader' => 'TemplateGrader'],
        ])->resolve();

        $mform = new \MoodleQuickForm('t', 'post', '');
        settings_form::add_section(
            $mform,
            form_section::TEMPLATE,
            $inherited,
            new override_set(['grader' => 'RegexGrader'], 'q')
        );

        $note = (string) $this->group_member($mform, 'grader', 'grader_source')->_text;

        $this->assertStringContainsString('python3', $note);
        $this->assertStringContainsString(
            get_string('templategrader', 'qtype_coderunner'),
            $note,
            'The note did not say what clearing the override would give.'
        );
    }

    /**
     * A field that is not overridden does not repeat its value, because the
     * control already shows it.
     */
    public function test_an_inherited_field_does_not_repeat_its_value(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['grader' => 'TemplateGrader'],
        ])->resolve();

        $mform = new \MoodleQuickForm('t', 'post', '');
        settings_form::add_section($mform, form_section::TEMPLATE, $inherited, new override_set([], 'q'));

        $this->assertStringNotContainsString(
            get_string('templategrader', 'qtype_coderunner'),
            (string) $this->group_member($mform, 'grader', 'grader_source')->_text
        );
    }

    /**
     * A long inherited value is cut off, since the note says what a field
     * falls back to rather than showing the whole of a template.
     */
    public function test_a_long_inherited_value_is_truncated(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();

        $long = str_repeat('a long stock template line ', 40);
        $inherited = prototype_chain::from_arrays([['template' => $long]])->resolve();

        $mform = new \MoodleQuickForm('t', 'post', '');
        settings_form::add_section(
            $mform,
            form_section::TEMPLATE,
            $inherited,
            new override_set(['template' => 'MINE'], 'q')
        );

        $note = (string) $this->group_member($mform, 'template', 'template_source')->_text;

        $this->assertStringContainsString('…', $note, 'A long value was not truncated.');
        $this->assertLessThan(300, \core_text::strlen(strip_tags($note)));
    }

    /**
     * A form submits CRLF whatever the textarea was given, so the submitted
     * value is brought back to newlines before it is stored. Otherwise saving
     * a template rewrites every line of it without changing a character that
     * anyone can see.
     */
    public function test_submitted_line_endings_are_normalised(): void {
        $overrides = settings_form::overrides_from(
            ['template' => "line one\r\nline two\r\n", 'override_template' => 1],
            [form_section::TEMPLATE],
            new override_set([], 'p')
        );

        $this->assertSame("line one\nline two\n", $overrides->get('template'));
    }

    /**
     * Text that differs only in its line endings is not a change, so a history
     * does not report a field as edited when nothing about it moved.
     */
    public function test_line_endings_alone_are_not_a_difference(): void {
        $before = new override_set(['template' => "a\nb\nc"], 'v1');
        $after = new override_set(['template' => "a\r\nb\r\nc"], 'v2');

        $this->assertSame([], $after->differences_from($before));
    }

    /**
     * A real edit is still reported, so the leniency does not hide anything.
     */
    public function test_a_real_change_is_still_a_difference(): void {
        $before = new override_set(['template' => "a\nb"], 'v1');
        $after = new override_set(['template' => "a\r\nB"], 'v2');

        $this->assertArrayHasKey('template', $after->differences_from($before));
    }
}
