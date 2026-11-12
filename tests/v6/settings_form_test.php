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
            ['template' => 'MY TEMPLATE', 'inherit_template' => 0],
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
            ['template' => 'OLD TEMPLATE', 'inherit_template' => 1],
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
            ['template' => '', 'inherit_template' => 0],
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
            ['penaltyregime' => '20, 40, ...', 'inherit_penaltyregime' => 0],
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
            ['allornothing' => '1', 'inherit_allornothing' => 0],
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
     * The Inherit menu's sense is the inverse of the stored state, in both
     * directions, and a field the form did not ask about keeps what it had.
     *
     * An inverted boolean is where this sort of change goes wrong, so each of
     * the three cases is asserted rather than reasoned about. The third is the
     * one with teeth: if a missing key were read as "inherit", a form showing
     * fewer fields than the question overrides would quietly drop the rest.
     */
    public function test_the_inherit_menu_is_the_inverse_of_an_override(): void {
        $existing = new override_set(['template' => 'OLD', 'grader' => 'EqualityGrader'], 'q');

        // Inherit: No keeps the author's value as an override.
        $kept = settings_form::overrides_from(
            ['template' => 'MINE', settings_form::INHERIT_PREFIX . 'template' => 0],
            [form_section::TEMPLATE],
            $existing,
            'q'
        );
        $this->assertSame('MINE', $kept->get('template'));

        // Inherit: Yes drops it.
        $dropped = settings_form::overrides_from(
            ['template' => 'MINE', settings_form::INHERIT_PREFIX . 'template' => 1],
            [form_section::TEMPLATE],
            $existing,
            'q'
        );
        $this->assertFalse($dropped->has('template'));

        // A field the form never asked about is left exactly as it was, so a
        // form showing one section cannot disturb another's overrides.
        $this->assertSame('EqualityGrader', $dropped->get('grader'));
        $untouched = settings_form::overrides_from([], [form_section::TEMPLATE], $existing, 'q');
        $this->assertSame('OLD', $untouched->get('template'));
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
            settings_form::INHERIT_PREFIX . 'showsource' => 0,
            'answer' => 'print(42)',
            settings_form::INHERIT_PREFIX . 'answer' => 0,
            'template' => 'MY TEMPLATE',
            settings_form::INHERIT_PREFIX . 'template' => 0,
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
        $this->assertFalse($mform->elementExists(settings_form::INHERIT_PREFIX . 'answer'));
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

            $components = settings_form::components_of($fields);
            foreach ($fields as $name => $field) {
                if (isset($components[$name])) {
                    // A component is rendered inside the group of the field it
                    // belongs to, and has neither a control nor a note of its
                    // own; the next test covers that it is there.
                    $this->assertFalse($mform->elementExists($name . '_group'));
                    $rendered++;
                    continue;
                }
                // A code field is added on its own so that Ace styles it
                // correctly; everything else is grouped with its override box.
                $this->assertTrue(
                    $mform->elementExists($field->iscode ? $name : $name . '_group'),
                    "Field $name rendered no control in section {$section->value}."
                );
                if (!$field->resolves_to_one_value()) {
                    // No override control on a collected field: a document
                    // merges with the ones above it and a qualifier applies to
                    // this layer alone, so neither offers a choice between
                    // inheriting and replacing.
                    $this->assertNull(
                        $this->group_member($mform, $name, settings_form::INHERIT_PREFIX . $name),
                        "Collected field $name was offered an override control."
                    );
                } else {
                    $this->assertNotNull(
                        $this->group_member($mform, $name, settings_form::INHERIT_PREFIX . $name),
                        "Field $name rendered no override control."
                    );
                }
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

        // Ace on each code field, and the script that keeps an inherited one
        // uneditable. The second is easy to lose: nothing else on the page
        // refers to it, and without it the markup's readonly attribute would
        // be the only thing holding a code field, which the author could then
        // never untick.
        $queued = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $amd = implode(' ', $queued->getValue($PAGE->requires));
        $this->assertStringContainsString('qtype_coderunner/userinterfacewrapper', $amd);
        $this->assertStringContainsString('qtype_coderunner/inheritedcode', $amd);
        $this->assertStringContainsString('"template"', $amd, 'The template was not among the fields named.');
    }

    /**
     * A form the author may only read gets Ace but is asked to unlock nothing.
     *
     * Formslib freezes such a form by rendering every control `readonly
     * disabled` — and the Inherit checkbox as a real input, disabled and
     * unchecked where the layer overrides the field. `inheritedcode.js` read
     * that as "not inherited, so unlock it" and handed back a fully editable
     * Ace editor on a page nobody could save. The script now refuses a
     * disabled checkbox, and is not asked for in the first place.
     */
    public function test_a_read_only_form_is_asked_to_unlock_nothing(): void {
        global $PAGE;

        $this->resetAfterTest();
        $PAGE->set_url('/');

        settings_form::require_ace([form_section::TEMPLATE], 'python3', false, true);

        $queued = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $amd = implode(' ', $queued->getValue($PAGE->requires));
        // Ace still arrives: a reader wants the highlighting.
        $this->assertStringContainsString('qtype_coderunner/userinterfacewrapper', $amd);
        $this->assertStringNotContainsString('qtype_coderunner/inheritedcode', $amd);
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
    public function test_the_note_states_the_parent_value_either_way(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['grader' => 'TemplateGrader'],
        ])->resolve();

        // Inherited: the control shows the value too, and the note says it
        // again. An earlier version suppressed the repetition, which made the
        // note mean one thing when a field was overridden and another when it
        // was not. It answers one question — what ticking Inherit would give
        // you — so it says the same thing in the same words either way.
        $plain = new \MoodleQuickForm('t', 'post', '');
        settings_form::add_section($plain, form_section::TEMPLATE, $inherited, new override_set([], 'q'));
        $this->assertStringContainsString(
            get_string('templategrader', 'qtype_coderunner'),
            (string) $this->group_member($plain, 'grader', 'grader_source')->_text
        );

        // Overridden: same note, and it names the layer, because a prototype
        // is somewhere the author could go and change it for every question.
        $overridden = new \MoodleQuickForm('t2', 'post', '');
        settings_form::add_section(
            $overridden,
            form_section::TEMPLATE,
            $inherited,
            new override_set(['grader' => 'EqualityGrader'], 'q')
        );
        $note = (string) $this->group_member($overridden, 'grader', 'grader_source')->_text;
        $this->assertStringContainsString(get_string('templategrader', 'qtype_coderunner'), $note);
        $this->assertStringContainsString('python3', $note);
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
            ['template' => "line one\r\nline two\r\n", 'inherit_template' => 0],
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

    /**
     * A merging field is offered with no Inherit control, and holds this
     * layer's own document rather than a copy of the nearest ancestor's.
     *
     * The control described a choice that does not exist. Both documents
     * merge, key by key, with every document above them, so "inherited" and
     * "overridden with nothing" are the same state — and the box, which
     * showed the nearest ancestor's document so that clearing the control
     * would show what the field fell back to, invited the author to adopt a
     * copy of it. A copy merges with the original to no effect, and stops
     * following it.
     */
    public function test_a_merging_field_has_no_inherit_control(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['uiparameters' => '{"ui_source": "prefixed"}'],
        ])->resolve();

        $mform = new \MoodleQuickForm('merging', 'post', '');
        settings_form::add_section($mform, form_section::UI, $inherited, new override_set([], 'q'));

        $this->assertTrue($mform->elementExists('uiparameters'));
        $this->assertFalse(
            $mform->elementExists(settings_form::INHERIT_PREFIX . 'uiparameters'),
            'A merging field was offered an Inherit control.'
        );
        $this->assertNull(
            $this->group_member($mform, 'uiparameters', settings_form::INHERIT_PREFIX . 'uiparameters'),
            'A merging field was offered an Inherit control inside its group.'
        );
        // Empty, because this layer contributes nothing — not a copy of what
        // the prototype contributes.
        $this->assertSame('', $mform->_defaultValues['uiparameters'] ?? '');
    }

    /**
     * An empty document is dropped on save rather than stored, because
     * contributing nothing and holding nothing are the same thing.
     *
     * This is the one place where dropping an override is safe. For every
     * other field an empty value is a real override — it blanks what would be
     * inherited — so dropping it would unfreeze a value the migration pinned.
     * Here it cannot: an empty document contributes nothing to the merge
     * whether it is stored or absent. On the development site 10,199 of the
     * 12,756 migrated questions hold exactly this.
     */
    public function test_an_empty_merging_document_is_dropped(): void {
        $existing = new override_set(['uiparameters' => '{"ui_source": "mine"}'], 'q');

        foreach (['', '   ', '{}', '[]'] as $saysnothing) {
            $after = settings_form::overrides_from(
                ['uiparameters' => $saysnothing],
                [form_section::UI],
                $existing,
                'q'
            );
            $this->assertFalse(
                $after->has('uiparameters'),
                "'$saysnothing' should contribute nothing and be dropped."
            );
        }

        // And a document that says something is kept, with no checkbox to ask.
        $kept = settings_form::overrides_from(
            ['uiparameters' => '{"ui_source": "prefixed"}'],
            [form_section::UI],
            $existing,
            'q'
        );
        $this->assertSame('{"ui_source": "prefixed"}', $kept->get('uiparameters'));

        // A section the form did not show still keeps what it had.
        $untouched = settings_form::overrides_from([], [form_section::UI], $existing, 'q');
        $this->assertSame('{"ui_source": "mine"}', $untouched->get('uiparameters'));
    }

    /**
     * A merging field is marked when it says something and unmarked when it
     * does not, whatever the layers above it hold.
     *
     * It cannot be compared against what it would inherit, the way a
     * replacing field is: it does not replace that. An empty document is the
     * identity of the merge; a document identical to the parent's changes
     * nothing either, but saying so would need the parameters evaluated,
     * which a form cannot do — so a non-empty document is marked.
     */
    public function test_a_merging_field_is_redundant_only_when_empty(): void {
        $inherited = prototype_chain::from_arrays([
            'python3' => ['uiparameters' => '{"ui_source": "prefixed"}'],
        ])->resolve();
        $field = field_registry::get('uiparameters');

        $empty = new override_set(['uiparameters' => ''], 'q');
        $this->assertTrue(settings_form::is_redundant($field, $empty, $inherited));

        $nothing = new override_set(['uiparameters' => '{}'], 'q');
        $this->assertTrue(settings_form::is_redundant($field, $nothing, $inherited));

        $something = new override_set(['uiparameters' => '{"font_size": 14}'], 'q');
        $this->assertFalse(settings_form::is_redundant($field, $something, $inherited));

        // An unreadable document is not nothing: it may be Twig, and claiming
        // it changes nothing would be a guess.
        $twig = new override_set(['templateparams' => '{"n": {{ 1 + 1 }} }'], 'q');
        $this->assertFalse(
            settings_form::is_redundant(field_registry::get('templateparams'), $twig, $inherited)
        );
    }

    /**
     * The parameters, the language they are written in and the per-try switch
     * are one composite: the components sit in the document's own group, and
     * the section counts the three of them as one change.
     *
     * They are not three settings. The language and the per-try switch say how
     * to read this layer's document and mean nothing without it, so presenting
     * them as peers of the thing they qualify is what made the section hard to
     * read — and counting them separately reported one authoring decision as
     * two or three changes.
     */
    public function test_the_parameters_and_their_qualifiers_are_one_composite(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $inherited = (new prototype_chain([]))->resolve();
        $own = new override_set([
            'templateparams' => '{"n": 3}',
            'templateparamslang' => 'twig',
            'templateparamsevalpertry' => 1,
        ], 'q');

        $mform = new \MoodleQuickForm('composite', 'post', '');
        settings_form::add_section($mform, form_section::PARAMETERS, $inherited, $own);

        // The components are in the document's group, not rows of their own.
        foreach (['templateparamslang', 'templateparamsevalpertry'] as $componentname) {
            $this->assertFalse(
                $mform->elementExists($componentname . '_group'),
                "$componentname should not have a row of its own."
            );
            $this->assertNotNull(
                $this->group_member($mform, 'templateparams', $componentname),
                "$componentname should sit beside the parameters it describes."
            );
            // Behind a label, so that two bare menus cannot be confused.
            $this->assertNotNull(
                $this->group_member($mform, 'templateparams', $componentname . '_label')
            );
        }

        // One change, not three.
        $this->assertSame(1, settings_form::changed_count(form_section::PARAMETERS, $own, $inherited));

        // And the components are still read back and stored in their own
        // right, since each is a column and a field of the overrides document.
        $saved = settings_form::overrides_from(
            [
                'templateparams' => '{"n": 3}',
                'templateparamslang' => 'twig',
                'templateparamsevalpertry' => '1',
            ],
            [form_section::PARAMETERS],
            new override_set([], 'q'),
            'q'
        );
        $this->assertSame('twig', $saved->get('templateparamslang'));
        $this->assertSame(1, $saved->get('templateparamsevalpertry'));
    }

    /**
     * A language pinned against no parameters is not a change, because the
     * composite's mark belongs to the document.
     *
     * On the development site 963 questions are in exactly that state, having
     * been pinned as `twig` by the migration while contributing nothing.
     */
    public function test_a_qualifier_without_parameters_is_not_a_change(): void {
        $inherited = (new prototype_chain([]))->resolve();
        $own = new override_set([
            'templateparams' => '',
            'templateparamslang' => 'twig',
        ], 'q');

        $this->assertSame(0, settings_form::changed_count(form_section::PARAMETERS, $own, $inherited));
    }

    /**
     * The two qualifiers of a contribution have no Inherit control either, and
     * they answer to the registry default rather than to the layer above.
     *
     * They are not inherited at all: `chain_layers()` gives a layer that sets
     * no language the registry default, not its parent's. So a control reading
     * *Inherit* beside a note reading *Parent: Twig* promised something the
     * run-time path does not do — a question contributing Twig parameters
     * while leaving the control alone would have been evaluated as *None*.
     */
    public function test_a_qualifier_answers_to_the_registry_default(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['templateparamslang' => 'twig'],
        ])->resolve();

        $mform = new \MoodleQuickForm('qualifier', 'post', '');
        settings_form::add_section($mform, form_section::PARAMETERS, $inherited, new override_set([], 'q'));

        $this->assertFalse(
            $mform->elementExists(settings_form::INHERIT_PREFIX . 'templateparamslang'),
            'A qualifier was offered an Inherit control.'
        );
        // The registry default, not the prototype's Twig: that is what this
        // layer's own parameters would be evaluated as.
        $this->assertSame(
            field_registry::get('templateparamslang')->default,
            $mform->_defaultValues['templateparamslang']
        );

        // Marked when it says something, measured against the default and not
        // against the layer above.
        $field = field_registry::get('templateparamslang');
        $this->assertTrue(
            settings_form::is_redundant($field, new override_set(['templateparamslang' => 'None'], 'q'), $inherited)
        );
        $this->assertFalse(
            settings_form::is_redundant($field, new override_set(['templateparamslang' => 'twig'], 'q'), $inherited)
        );
    }

    /**
     * A qualifier at the registry default is dropped on save, and one that
     * says something is kept.
     */
    public function test_a_qualifier_is_stored_only_when_it_says_something(): void {
        $existing = new override_set(['templateparamslang' => 'twig'], 'q');

        $dropped = settings_form::overrides_from(
            ['templateparamslang' => field_registry::get('templateparamslang')->default],
            [form_section::PARAMETERS],
            $existing,
            'q'
        );
        $this->assertFalse($dropped->has('templateparamslang'));

        $kept = settings_form::overrides_from(
            ['templateparamslang' => 'twig'],
            [form_section::PARAMETERS],
            $existing,
            'q'
        );
        $this->assertSame('twig', $kept->get('templateparamslang'));

        // And the boolean beside it, whose default is 0.
        $boolean = settings_form::overrides_from(
            ['templateparamsevalpertry' => '0'],
            [form_section::PARAMETERS],
            new override_set(['templateparamsevalpertry' => 1], 'q'),
            'q'
        );
        $this->assertFalse($boolean->has('templateparamsevalpertry'));
    }

    /**
     * The note under a merging field says what the document merges with: the
     * merged result where every contributing layer can be read as JSON, and
     * the layers by name where one cannot.
     */
    public function test_the_merge_note_says_what_it_merges_with(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        // Two layers of plain JSON, so the note can show the merge itself,
        // with the nearer layer's key winning as it does at run time.
        $readable = prototype_chain::from_arrays([
            'python3@1' => ['uiparameters' => '{"font_size": 12, "theme": "dark"}'],
            'python3_scratchpad@2' => ['uiparameters' => '{"font_size": 14}'],
        ])->resolve();
        $note = $this->merge_note($readable, form_section::UI, 'uiparameters');
        $this->assertStringContainsString('font_size', $note);
        $this->assertStringContainsString('14', $note);
        $this->assertStringContainsString('theme', $note);
        $this->assertStringNotContainsString('12', $note);

        // A layer that cannot be read as JSON is named instead of guessed at.
        $twig = prototype_chain::from_arrays([
            'python3@1' => ['templateparams' => '{"n": {{ 1 + 1 }} }'],
        ])->resolve();
        $this->assertStringContainsString(
            'python3',
            $this->merge_note($twig, form_section::PARAMETERS, 'templateparams')
        );

        // And nothing above contributing anything is said plainly.
        $none = (new prototype_chain([]))->resolve();
        $this->assertStringContainsString(
            get_string('parentnotset', 'qtype_coderunner'),
            $this->merge_note($none, form_section::UI, 'uiparameters')
        );
    }

    /**
     * The label of a merging field says that it merges, on a line of its own.
     */
    public function test_a_merging_fields_label_says_so(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $mform = new \MoodleQuickForm('labels', 'post', '');
        settings_form::add_section(
            $mform,
            form_section::UI,
            (new prototype_chain([]))->resolve(),
            new override_set([], 'q')
        );

        $label = (string) $mform->getElement('uiparameters')->getLabel();
        $this->assertStringContainsString(
            get_string('uiparameters', 'qtype_coderunner'),
            $label
        );
        $this->assertStringContainsString(get_string('mergeswithparent', 'qtype_coderunner'), $label);
        // The plugin menu beside it replaces rather than merges, and says
        // nothing of the kind.
        $this->assertStringNotContainsString(
            get_string('mergeswithparent', 'qtype_coderunner'),
            (string) $mform->getElement('uiplugin_group')->getLabel()
        );
    }

    /**
     * The text of a merging field's note, as rendered on a form.
     *
     * @param question_settings $inherited what the layers above contribute.
     * @param form_section $section the section holding the field.
     * @param string $name the field.
     * @return string the note's content.
     */
    private function merge_note(question_settings $inherited, form_section $section, string $name): string {
        $mform = new \MoodleQuickForm('note' . $name . mt_rand(), 'post', '');
        settings_form::add_section($mform, $section, $inherited, new override_set([], 'q'));
        $note = $this->group_member($mform, $name, $name . '_source');
        $this->assertNotNull($note, "Field $name rendered no note.");
        return (string) $note->_text;
    }

    /**
     * An inherited code field says so in its markup, because Ace reads the
     * textarea's readonly attribute as it starts up and nothing afterwards.
     *
     * Without this the attribute core's `disabledIf` sets was the only sign,
     * and by the time it is set the textarea is hidden behind an Ace editor
     * that neither carries it nor watches for it: an inherited template was
     * fully editable, and because a disabled field is not submitted, an edit
     * made to one was dropped without a word on save.
     */
    public function test_an_inherited_code_field_is_marked_readonly(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['template' => 'INHERITED'],
        ])->resolve();

        $inheritform = new \MoodleQuickForm('inherits', 'post', '');
        settings_form::add_section($inheritform, form_section::TEMPLATE, $inherited, new override_set([], 'q'));
        $this->assertSame(
            'readonly',
            $inheritform->getElement('template')->getAttribute('readonly'),
            'An inherited template was offered as editable.'
        );

        // And an overridden one is the author's to type in.
        $ownform = new \MoodleQuickForm('overrides', 'post', '');
        settings_form::add_section(
            $ownform,
            form_section::TEMPLATE,
            $inherited,
            new override_set(['template' => 'MINE'], 'q')
        );
        $this->assertNull(
            $ownform->getElement('template')->getAttribute('readonly'),
            'An overridden template was locked.'
        );
    }

    /**
     * Every code field an inheritable layer can override is marked, not just
     * the template: the JSON parameter fields are code fields too, and each of
     * them is edited in Ace.
     */
    public function test_every_inheritable_code_field_is_marked(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $inherited = (new prototype_chain([]))->resolve();
        $checked = 0;
        $merging = 0;
        foreach (form_section::cases() as $section) {
            $fields = array_filter(settings_form::fields_of($section), fn($field) => $field->iscode);
            if (!$fields) {
                continue;
            }

            $mform = new \MoodleQuickForm('t' . $section->value, 'post', '');
            settings_form::add_section($mform, $section, $inherited, new override_set([], 'q'));
            foreach ($fields as $name => $field) {
                if ($field->merges_with_inherited()) {
                    // A merging field is never locked: it holds this layer's
                    // own document, which is always the author's to write.
                    $this->assertNull(
                        $mform->getElement($name)->getAttribute('readonly'),
                        "Merging field $name was locked."
                    );
                    $merging++;
                    continue;
                }
                $this->assertSame(
                    'readonly',
                    $mform->getElement($name)->getAttribute('readonly'),
                    "Inherited code field $name was offered as editable."
                );
                $checked++;
            }
        }

        // The inherited code fields Ace is attached to: the template, the
        // result columns, the sandbox parameters and the two extras.
        $this->assertSame(5, $checked);
        // And the two that merge: the template parameters and the UI ones.
        $this->assertSame(2, $merging);
    }

    /**
     * A section with a mark in it explains what the mark means, and a section
     * with none says nothing.
     *
     * The legend comes from the component rather than from each form, because
     * the marks do: the prototype editor showed the marks with nothing to
     * explain them for exactly as long as the two were in different places.
     */
    public function test_a_section_with_a_change_explains_its_mark(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $this->resetAfterTest();

        $inherited = prototype_chain::from_arrays([
            'python3' => ['template' => 'INHERITED'],
        ])->resolve();
        $legend = settings_form::section_header(form_section::TEMPLATE) . '_legend';

        $changed = new \MoodleQuickForm('changed', 'post', '');
        settings_form::add_section(
            $changed,
            form_section::TEMPLATE,
            $inherited,
            new override_set(['template' => 'MINE'], 'q')
        );
        $this->assertTrue($changed->elementExists($legend), 'A marked section explained nothing.');

        $unchanged = new \MoodleQuickForm('unchanged', 'post', '');
        settings_form::add_section($unchanged, form_section::TEMPLATE, $inherited, new override_set([], 'q'));
        $this->assertFalse($unchanged->elementExists($legend), 'A section with no marks carried a legend.');

        // An override that restates what it would inherit is not a mark, so it
        // is not explained either.
        $redundant = new \MoodleQuickForm('redundant', 'post', '');
        settings_form::add_section(
            $redundant,
            form_section::TEMPLATE,
            $inherited,
            new override_set(['template' => 'INHERITED'], 'q')
        );
        $this->assertFalse($redundant->elementExists($legend));
    }

    /**
     * A section's heading counts the fields the layer changes, and counts only
     * the ones that make a difference.
     */
    public function test_a_section_label_counts_effective_changes(): void {
        $inherited = prototype_chain::from_arrays([
            'python3' => ['template' => 'INHERITED', 'grader' => 'TemplateGrader'],
        ])->resolve();

        $this->assertSame(
            form_section::TEMPLATE->label(),
            settings_form::section_label(form_section::TEMPLATE, new override_set([], 'q'), $inherited)
        );

        $own = new override_set([
            // A real change.
            'template' => 'MINE',
            // And one that restates what it would have inherited anyway, which
            // is what every migrated question is full of.
            'grader' => 'TemplateGrader',
        ], 'q');
        $this->assertSame(
            form_section::TEMPLATE->label() . ' · ' . get_string('sectionchanged', 'qtype_coderunner', 1),
            settings_form::section_label(form_section::TEMPLATE, $own, $inherited)
        );
        $this->assertSame(1, settings_form::changed_count(form_section::TEMPLATE, $own, $inherited));
    }
}
