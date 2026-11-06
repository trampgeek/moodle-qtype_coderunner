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
        override_set $own
    ): void {
        foreach (self::fields_of($section) as $name => $field) {
            self::add_field($mform, $field, $inherited, $own);
        }
    }

    /**
     * The fields belonging to one section, in registry order.
     *
     * @param form_section $section the section.
     * @return field[] name => field.
     */
    public static function fields_of(form_section $section): array {
        return array_filter(
            field_registry::all(),
            fn($field) => $field->section === $section && $field->incolumn
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
        override_set $own
    ): void {
        $name = $field->name;
        $overridename = self::OVERRIDE_PREFIX . $name;
        $isoverridden = $own->has($field);

        $group = [
            self::input_element($mform, $field),
            $mform->createElement(
                'advcheckbox',
                $overridename,
                '',
                get_string('overridethisfield', 'qtype_coderunner')
            ),
        ];
        $mform->addGroup($group, $name . '_group', $field->label(), ' ', false);

        if ($field->help() !== null) {
            // Moodle's help icon appends '_help' itself and needs the plain
            // identifier to exist as well, so the two strings must share a
            // base. Passing help_string_id() here would send it looking for
            // '<field>_help_help'.
            $mform->addHelpButton($name . '_group', $field->label_string_id(), 'qtype_coderunner');
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
    protected static function input_element(\MoodleQuickForm $mform, field $field) {
        $name = $field->name;

        if ($field->type === field_type::CHOICE) {
            return $mform->createElement('select', $name, '', field_registry::choices($name));
        }
        if ($field->type === field_type::BOOLEAN) {
            return $mform->createElement('advcheckbox', $name, '', '');
        }
        if ($field->type === field_type::TEXT || $field->type === field_type::JSON) {
            return $mform->createElement('textarea', $name, '', ['rows' => 6, 'cols' => 80]);
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
            : get_string('inheritedfrom', 'qtype_coderunner', $source);

        $mform->addElement(
            'static',
            $field->name . '_source',
            '',
            \html_writer::tag('small', $text, ['class' => 'text-muted'])
        );
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
