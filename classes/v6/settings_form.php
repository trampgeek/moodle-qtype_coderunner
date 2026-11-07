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
 * The shared, registry-driven settings form component.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * Renders a layer's settings as form controls, each either inherited from the
 * layers above it or overridden here, and reads the author's answer back as an
 * override set.
 *
 * Used by both the prototype editor and the question form, which differ only
 * in which layer they are editing and which sections they show.
 *
 * **Every control is a standard MoodleQuickForm element** (decision 21). The
 * one thing core has no element for is "inherited, or overridden with a control
 * to revert", and that is built here as a thin wrapper rather than by
 * reimplementing the inputs: each field keeps whatever element its registry
 * type calls for, and an `advcheckbox` beside it decides whether the value is
 * the author's or the one above. `disabledIf` greys the input while the box is
 * clear, so the whole affordance needs no JavaScript at all.
 *
 * Clearing the box drops the override when the form is saved; the value is not
 * kept against the author changing their mind, because an overrides document
 * holding values that are not in effect is a second, invisible state.
 */
class settings_form {
    /** @var string Prefix for the checkbox that says a field is overridden here. */
    const OVERRIDE_PREFIX = 'override_';

    /**
     * Add every field of one form section.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param form_section $section the section to add.
     * @param question_settings $inherited what the layers above this one resolve to.
     * @param override_set $own what this layer currently overrides.
     */
    public static function add_section(
        \MoodleQuickForm $mform,
        form_section $section,
        question_settings $inherited,
        override_set $own,
        string $language = ''
    ): void {
        foreach (self::fields_of($section) as $name => $field) {
            self::add_field($mform, $field, $inherited, $own, $language);
        }
    }

    /**
     * The fields belonging to one section that this component can render, in
     * registry order.
     *
     * Everything except support files, which are a set of files rather than a
     * value and need a file manager and a draft area of their own. Note that
     * the test for exclusion is the field's *type*, not whether it has a
     * column: the test case field switches have no column, because they live
     * in the overrides document rather than in
     * question_coderunner_options, and they are settings an author edits like
     * any other.
     *
     * @param form_section $section the section.
     * @return field[] name => field.
     */
    public static function fields_of(form_section $section): array {
        return array_filter(
            field_registry::all(),
            fn($field) => $field->section === $section && $field->type !== field_type::FILES
        );
    }

    /**
     * Add one field: its input, its override checkbox, and where its inherited
     * value comes from.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param field $field the field to add.
     * @param question_settings $inherited what the layers above resolve to.
     * @param override_set $own what this layer currently overrides.
     */
    protected static function add_field(
        \MoodleQuickForm $mform,
        field $field,
        question_settings $inherited,
        override_set $own,
        string $language = ''
    ): void {
        $name = $field->name;
        $overridename = self::OVERRIDE_PREFIX . $name;
        $isoverridden = $own->has($field);

        $override = $mform->createElement(
            'advcheckbox',
            $overridename,
            '',
            get_string('overridethisfield', 'qtype_coderunner')
        );

        if ($field->iscode) {
            // A code field is added on its own rather than in a group.
            // CodeRunner positions Ace's full-screen control against the
            // textarea's own container, and a group wrapper leaves it half
            // visible in the corner. The author form adds its code textareas
            // the same way, as plain elements, which is why they look right.
            $mform->addElement(self::input_element($mform, $field, $language));
            $mform->addElement($override);
            $helpanchor = $name;
        } else {
            $mform->addGroup(
                [self::input_element($mform, $field, $language), $override],
                $name . '_group',
                $field->label(),
                '&nbsp;&nbsp;&nbsp;',
                false
            );
            $helpanchor = $name . '_group';
        }

        if ($field->help() !== null) {
            // Moodle's help icon appends '_help' itself and needs the plain
            // identifier to exist as well, so the two strings must share a
            // base. Passing help_string_id() here would send it looking for
            // '<field>_help_help'.
            $mform->addHelpButton($helpanchor, $field->help_identifier(), 'qtype_coderunner');
        }

        // The input shows the author's value when they have one, and otherwise
        // what they would inherit, so that clearing the checkbox shows what the
        // field falls back to rather than going blank.
        $mform->setDefault(
            $name,
            $isoverridden ? $own->get($field) : self::inherited_value($field, $inherited)
        );
        $mform->setDefault($overridename, $isoverridden ? 1 : 0);
        $mform->setType($name, self::param_type($field));

        // No JavaScript: core greys the input while the box is clear.
        $mform->disabledIf($name, $overridename, 'notchecked');

        self::add_provenance($mform, $field, $inherited, $isoverridden);
    }

    /**
     * The input control for a field, chosen from its registry type.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param field $field the field.
     * @return object the form element.
     */
    protected static function input_element(\MoodleQuickForm $mform, field $field, string $language = '') {
        $name = $field->name;

        if ($field->type === field_type::CHOICE) {
            return $mform->createElement('select', $name, '', field_registry::choices($name));
        }
        if ($field->type === field_type::BOOLEAN) {
            // Not a checkbox: it would sit beside the override checkbox as two
            // identical boxes with a single label between them, and no way to
            // tell which was which.
            return $mform->createElement('select', $name, '', [
                0 => get_string('no'),
                1 => get_string('yes'),
            ]);
        }
        if ($field->type === field_type::TEXT || $field->type === field_type::JSON) {
            // A code field carries its own label, since it is not in a group.
            $label = $field->iscode ? $field->label() : '';
            $attributes = ['rows' => $field->rows ?: ($field->iscode ? 12 : 2)];
            if ($field->iscode) {
                // InterfaceWrapper reads the language from this attribute and
                // overwrites whatever data-params holds, so this is the only
                // place that can set it. Template parameters and UI parameters
                // are deliberately left without one, because they may contain
                // Twig and a JSON mode reports errors that are not there.
                $attributes['data-lang'] = self::ace_language($field, $language);
                $attributes['class'] = 'edit_code';
            } else {
                $attributes['cols'] = 80;
            }
            return $mform->createElement('textarea', $name, $label, $attributes);
        }
        if ($field->type === field_type::INTEGER) {
            return $mform->createElement('text', $name, '', ['size' => 6]);
        }
        return $mform->createElement('text', $name, '', ['size' => 40]);
    }

    /**
     * Say where an inherited value comes from, so an author can see which
     * prototype to go and edit.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param field $field the field.
     * @param question_settings $inherited what the layers above resolve to.
     * @param bool $isoverridden whether this layer overrides the field.
     */
    protected static function add_provenance(
        \MoodleQuickForm $mform,
        field $field,
        question_settings $inherited,
        bool $isoverridden
    ): void {
        $source = $inherited->was_set($field) ? $inherited->source_of($field) : null;
        $text = $source === null
            ? get_string('inheritedfromdefault', 'qtype_coderunner')
            : get_string('inheritedfrom', 'qtype_coderunner', self::layer_name($source));

        $value = self::inherited_value($field, $inherited);

        if (in_array($value, [null, ''], true)) {
            $text .= ' ' . get_string('inheritednotset', 'qtype_coderunner');
        } else if ($isoverridden) {
            // Only when the field is overridden. Otherwise the control itself
            // already shows this value and repeating it is noise. Overridden,
            // the control shows the author's value instead, and this is the
            // only place that says what clearing the override would give them
            // — including straight after they clear it, when the control is
            // greyed but still displaying what they are about to discard.
            $text .= ': ' . self::summarise($field, $value);
        }

        $mform->addElement(
            'static',
            $field->name . '_source',
            '',
            \html_writer::tag('small', $text, ['class' => 'text-muted'])
        );
    }

    /**
     * An inherited value, short enough to sit on one line.
     *
     * A choice shows the label an author picked, not the number stored behind
     * it, and a long value is cut off: a template runs to thousands of
     * characters and the point here is to say what the field would fall back
     * to, not to show the whole of it.
     *
     * @param field $field the field.
     * @param mixed $value the inherited value.
     * @return string the rendered summary.
     */
    protected static function summarise(field $field, $value): string {
        if ($field->type === field_type::BOOLEAN) {
            return $value ? get_string('yes') : get_string('no');
        }

        $choices = field_registry::choices($field->name);
        if ($choices !== null && array_key_exists($value, $choices)) {
            return (string) $choices[$value];
        }

        $text = preg_replace('/\s+/', ' ', trim((string) $value));
        return \core_text::strlen($text) > 80
            ? \core_text::substr($text, 0, 80) . '…'
            : $text;
    }

    /**
     * A layer's name as an author knows it.
     *
     * The stored label carries the prototype's id, so that two prototypes of
     * the same name in different courses stay distinct when a chain is checked
     * for cycles. The id means nothing to an author and is dropped here.
     *
     * @param string $source the stored layer label.
     * @return string the name.
     */
    protected static function layer_name(string $source): string {
        return preg_replace('/@\\d+$/', '', $source);
    }

    /**
     * The value a field would take if this layer did not override it.
     *
     * @param field $field the field.
     * @param question_settings $inherited what the layers above resolve to.
     * @return mixed the inherited value.
     */
    public static function inherited_value(field $field, question_settings $inherited) {
        if ($field->resolves_to_one_value()) {
            return $inherited->get($field);
        }
        // A collected field has no single inherited value: every layer
        // contributes. The nearest contribution is the useful thing to show.
        $layers = $inherited->layers_of($field);
        return $layers ? end($layers)['value'] : $field->default;
    }

    /**
     * The Ace syntax mode a code field is edited in.
     *
     * Empty for the parameter fields, which may hold Twig and would be
     * reported as broken JSON, and for the free-form extras. The template is
     * the prototype's own language, which is set when Ace is attached rather
     * than here, since the registry does not know which prototype is being
     * edited.
     *
     * @param field $field the field.
     * @return string the Ace language, or the empty string for none.
     */
    protected static function ace_language(field $field, string $language = ''): string {
        if ($field->name === 'template') {
            return $language;
        }
        // Everything else a code field holds is either JSON that may contain
        // Twig, or free-form text. Neither has a mode worth setting.
        return '';
    }

    /**
     * Ask the page for Ace, and attach it to every code field on show.
     *
     * Called by the page rather than from the form definition, so that
     * building a form has no side effects on $PAGE and can be done in a test.
     *
     * The question type owns Ace and the wrapper that attaches it; see
     * decision 22 for why that coupling exists and why it cannot be declared.
     *
     * @param form_section[] $sections the sections the form is showing.
     * @param string $language the language to highlight the template in. This
     *      must come from the settings the layer being edited *resolves to*,
     *      not from what it inherits: a prototype that sets its own language
     *      would otherwise get no syntax colouring at all.
     */
    public static function require_ace(array $sections, string $language = ''): void {
        global $PAGE;

        \qtype_coderunner_util::load_ace();

        foreach ($sections as $section) {
            foreach (self::fields_of($section) as $name => $field) {
                if (!$field->iscode) {
                    continue;
                }
                // Two arguments only: newUiWrapper(uiname, textareaId). The
                // language travels on the textarea's data-lang attribute,
                // which InterfaceWrapper reads for itself.
                $PAGE->requires->js_call_amd(
                    'qtype_coderunner/userinterfacewrapper',
                    'newUiWrapper',
                    ['ace', 'id_' . $name]
                );
            }
        }
    }

    /**
     * The PARAM_ type a field's submitted value is cleaned with.
     *
     * @param field $field the field.
     * @return string the PARAM_ constant.
     */
    protected static function param_type(field $field): string {
        return match ($field->type) {
            field_type::BOOLEAN => PARAM_BOOL,
            field_type::INTEGER => PARAM_RAW_TRIMMED,
            default => PARAM_RAW,
        };
    }

    /**
     * Read the author's answer back as the set of fields this layer overrides.
     *
     * A field whose checkbox is clear is simply absent from the result, which
     * is what makes reverting drop the override: nothing carries the old value
     * forward.
     *
     * @param object|array $data the submitted form data.
     * @param form_section[] $sections the sections that were shown, so that a
     *      form showing only some of them cannot drop the rest.
     * @param override_set $existing what the layer overrode before, used for
     *      the sections that were not shown.
     * @param string $source the label to record against the new set.
     * @return override_set the fields this layer now overrides.
     */
    public static function overrides_from(
        $data,
        array $sections,
        override_set $existing,
        string $source = ''
    ): override_set {
        $data = (array) $data;
        $values = $existing->to_array();

        foreach ($sections as $section) {
            foreach (self::fields_of($section) as $name => $field) {
                unset($values[$name]);
                $overridden = !empty($data[self::OVERRIDE_PREFIX . $name]);
                if ($overridden && array_key_exists($name, $data)) {
                    $values[$name] = legacy_converter::cast($name, $data[$name]);
                }
            }
        }

        return new override_set($values, $source !== '' ? $source : $existing->source);
    }
}
