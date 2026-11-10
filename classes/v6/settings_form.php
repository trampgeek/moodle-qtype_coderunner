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
 * clear, so the affordance needs no JavaScript — except on the code fields,
 * where Ace replaces the textarea with an editor of its own that `disabledIf`
 * cannot reach. Those get `inheritedcode.js`, which is a few lines and does
 * nothing else.
 *
 * Clearing the box drops the override when the form is saved; the value is not
 * kept against the author changing their mind, because an overrides document
 * holding values that are not in effect is a second, invisible state.
 */
class settings_form {
    /**
     * Prefix for the menu that says whether a field is inherited here.
     *
     * An *Inherit* menu rather than an *Override* checkbox, and the difference
     * is not cosmetic. The migration pins twenty fields on every pre-6.0
     * question whether or not the value differs from what the chain supplies
     * (M3), so most overrides on a migrated question restate what it would
     * have inherited anyway. *Override ✓* against a value identical to the
     * parent's reads as a mistake; *Inherit: No* against the same value reads
     * as a true statement of where the value comes from, and makes turning
     * inheritance on the obvious next move rather than a puzzle.
     *
     * The sense is therefore inverted from the stored state: `1` means the
     * field is inherited, and so *not* in the overrides document.
     */
    const INHERIT_PREFIX = 'inherit_';

    /**
     * The name of the layer at the root of every chain.
     *
     * Named here so that the provenance note can decline to point an author at
     * it. It is the one layer nobody navigates to from a question form, and
     * every chain ends there.
     */
    const SITE_DEFAULTS_LAYER = 'Site defaults';

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
        string $language = '',
        bool $withinstancefields = false
    ): void {
        global $OUTPUT;

        $fields = self::fields_of($section, $withinstancefields);
        $components = self::components_of($fields);
        foreach ($fields as $name => $field) {
            if (isset($components[$name])) {
                // Rendered beside the field it belongs to, by add_field().
                continue;
            }
            self::add_field($mform, $field, $inherited, $own, $language);
        }

        if (self::changed_count($section, $own, $inherited, $withinstancefields) === 0) {
            return;
        }

        // What the marks beside the fields above mean, said once per section
        // that has any and nowhere else. A legend outside the sections would
        // be in a collapsed region or far from the thing it explains.
        //
        // Emitted here rather than by the caller so that the two forms cannot
        // disagree: the marks are put on the fields by this component, and a
        // form that showed them with nothing to explain them is what the
        // prototype editor did.
        $mform->addElement(
            'static',
            self::section_header($section) . '_legend',
            '',
            \html_writer::tag(
                'small',
                $OUTPUT->pix_icon('i/asterisk', '', 'core', ['class' => 'text-primary']) . ' '
                    . get_string('fieldchangedlegend', 'qtype_coderunner'),
                ['class' => 'text-muted']
            )
        );
    }

    /**
     * The form element name of a section's header.
     *
     * Shared, because the legend above hangs off it and both forms name their
     * headers this way.
     *
     * @param form_section $section the section.
     * @return string the element name.
     */
    public static function section_header(form_section $section): string {
        return 'section_' . $section->value;
    }

    /**
     * The fields of a section that belong to another field rather than
     * standing on their own.
     *
     * The registry says which through `perlayerwith`: the language a layer's
     * template parameters are written in, and whether they are re-evaluated
     * for every student, describe how to read that layer's document and mean
     * nothing without it. Presenting them as peers of the thing they qualify
     * is what made the whole of this section hard to read — three controls
     * that look like three independent settings, two of which are inert
     * unless the third says something.
     *
     * They are therefore rendered beside their own field and counted with it,
     * so that one authoring decision reads as one change rather than three.
     *
     * @param field[] $fields the section's fields.
     * @return array component name => true.
     */
    public static function components_of(array $fields): array {
        $components = [];
        foreach ($fields as $field) {
            foreach ($field->perlayerwith as $componentname) {
                $components[$componentname] = true;
            }
        }
        return $components;
    }

    /**
     * A section's heading, with the number of fields this layer changes in it
     * when there are any.
     *
     * @param form_section $section the section.
     * @param override_set $own what this layer overrides.
     * @param question_settings $inherited what the layers above resolve to.
     * @param bool $withinstancefields true when the layer is a question.
     * @return string the heading.
     */
    public static function section_label(
        form_section $section,
        override_set $own,
        question_settings $inherited,
        bool $withinstancefields = false
    ): string {
        $name = $section->label();
        $changed = self::changed_count($section, $own, $inherited, $withinstancefields);
        if ($changed === 0) {
            return $name;
        }
        return $name . ' · ' . get_string('sectionchanged', 'qtype_coderunner', $changed);
    }

    /**
     * How many of a section's fields this layer overrides **to a different
     * value** from the one it would otherwise inherit.
     *
     * Not simply how many it overrides. The migration pins twenty fields on
     * every pre-6.0 question whether or not the value differs from what the
     * chain would supply (M3), so counting overrides reports five of the eight
     * sections as changed on every migrated question — including fields
     * holding exactly the registry default, which is what an author reads as
     * *not* changed. Counting effective differences makes the number mean what
     * the header claims: this layer does something the one above it does not.
     *
     * @param form_section $section the section.
     * @param override_set $own what this layer overrides.
     * @param question_settings $inherited what the layers above resolve to.
     * @param bool $withinstancefields true when the layer is a question.
     * @return int the count.
     */
    public static function changed_count(
        form_section $section,
        override_set $own,
        question_settings $inherited,
        bool $withinstancefields = false
    ): int {
        $fields = self::fields_of($section, $withinstancefields);
        $components = self::components_of($fields);
        $changed = 0;
        foreach ($fields as $name => $field) {
            if (isset($components[$name])) {
                // Counted with the field it belongs to: the language the
                // parameters are written in is part of one decision about
                // those parameters, not a second decision.
                continue;
            }
            if ($own->has($field) && !self::is_redundant($field, $own, $inherited)) {
                $changed++;
            }
        }
        return $changed;
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
     * **Instance fields are left out unless asked for**, because only a
     * question has them. The prototype editor must not offer one at all: a
     * prototype that stored an instance field would break every question
     * beneath it, since `prototype_chain` refuses a chain in which any layer
     * but the last supplies one, and a question whose chain cannot be built
     * reports itself broken. `showsource` is the field that makes this more
     * than theory — it is the control labelled *Template debugging*, it is a
     * field of the question alone, and it sits in the Template section, which
     * the prototype editor shows.
     *
     * The default is therefore the safe one: a caller has to say that it is
     * editing a question before an instance field appears. The write path
     * refuses them in any case; see {@see overrides_from()}.
     *
     * @param form_section $section the section.
     * @param bool $withinstancefields true when the layer being edited is a
     *      question, which is the only layer that has instance fields.
     * @return field[] name => field.
     */
    public static function fields_of(form_section $section, bool $withinstancefields = false): array {
        return array_filter(
            field_registry::all(),
            fn($field) => $field->section === $section
                && $field->type !== field_type::FILES
                && ($withinstancefields || $field->is_inheritable())
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

        if (!$field->is_inheritable()) {
            // A field of the question alone. There is no layer above it to
            // inherit from, so an override checkbox would be a choice between
            // a value and nothing, and a provenance note would have nothing to
            // name. It gets its control and its help, and that is all.
            $mform->addElement(self::input_element($mform, $field, $language, true));
            if ($field->help() !== null) {
                $mform->addHelpButton($name, $field->help_identifier(), 'qtype_coderunner');
            }
            $mform->setDefault($name, $own->has($field) ? $own->get($field) : $field->default);
            $mform->setType($name, self::param_type($field));
            return;
        }

        if (!$field->resolves_to_one_value()) {
            self::add_collected_field($mform, $field, $inherited, $own, $language);
            return;
        }

        $overridename = self::INHERIT_PREFIX . $name;
        $isoverridden = $own->has($field);

        // A checkbox, labelled, rather than a second unlabelled menu beside the
        // field's own: two bare Yes/No menus side by side give an author no way
        // to tell which is which. The box reads "Inherit", and the field's
        // control is greyed while it is ticked.
        $override = $mform->createElement(
            'advcheckbox',
            $overridename,
            '',
            get_string('inheritthisfield', 'qtype_coderunner')
        );

        // Where the value would come from if this layer did not override it,
        // beside the checkbox that decides whether it does rather than on a
        // line of its own: it is the reading of the checkbox, not a remark
        // about the field.
        $provenance = self::provenance_element($mform, $field, $inherited, $isoverridden, $own);

        if ($field->iscode) {
            // A code field is added on its own rather than in a group.
            // CodeRunner positions Ace's full-screen control against the
            // textarea's own container, and a group wrapper leaves it half
            // visible in the corner. The author form adds its code textareas
            // the same way, as plain elements, which is why they look right.
            $mform->addElement(self::input_element($mform, $field, $language, false, !$isoverridden));
            $mform->addGroup([$override, $provenance], $name . '_group', '', '&nbsp;&nbsp;', false);
            $helpanchor = $name;
        } else {
            $mform->addGroup(
                [self::input_element($mform, $field, $language), $override, $provenance],
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
        $mform->setDefault($overridename, $isoverridden ? 0 : 1);
        $mform->setType($name, self::param_type($field));
        $mform->setType($overridename, PARAM_BOOL);

        // No JavaScript: core greys the field while Inherit is ticked.
        $mform->disabledIf($name, $overridename, 'checked');
    }

    /**
     * Add a collected field: one that every layer contributes to rather than
     * one the nearest layer wins outright.
     *
     * **No *Inherit* control on any of them**, because there is nothing for it
     * to decide. Two of the four are JSON documents that merge key by key, so
     * a layer holding an empty document and a layer holding none are the same
     * state: the control offered a choice between two identical things and
     * then reported one of them as a change — on the development site, 10,199
     * of 12,756 migrated questions were marked as changing their UI parameters
     * by holding an empty override of them. The other two qualify a layer's
     * *own* contribution, and are not inherited at all: `chain_layers()` gives
     * a layer that sets no language the registry default, not its parent's, so
     * a control reading *Inherit* beside a note reading *Parent: Twig* was
     * promising something the run-time path does not do.
     *
     * What the author sees instead is what their own layer says — empty, or
     * the registry default — with a note saying what that means and a mark
     * when it says anything.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param field $field the field to add.
     * @param question_settings $inherited what the layers above contribute.
     * @param override_set $own what this layer contributes.
     * @param string $language the language a template is highlighted in.
     */
    protected static function add_collected_field(
        \MoodleQuickForm $mform,
        field $field,
        question_settings $inherited,
        override_set $own,
        string $language = ''
    ): void {
        $name = $field->name;

        // The fields that travel with this one per layer, each behind its own
        // label so that two bare menus side by side cannot be confused, and
        // then the note saying what the document merges with. add_section()
        // leaves the components out of its own loop, so this is the only place
        // they are rendered.
        $members = [];
        $defaults = [];
        foreach ($field->perlayerwith as $componentname) {
            $component = field_registry::get($componentname);
            $members[] = $mform->createElement(
                'static',
                $componentname . '_label',
                '',
                \html_writer::tag('span', $component->label(), ['class' => 'text-muted small'])
            );
            $members[] = self::input_element($mform, $component, $language);
            $defaults[$componentname] = $own->has($component)
                ? $own->get($component)
                : $component->default;
        }
        $members[] = self::merge_note($mform, $field, $inherited, $own);

        // Where the field's own help goes, and whether that leaves the group
        // free to carry the components'. A help button cannot anchor on an
        // element inside a group, so a component's can only go on the group.
        $componenthelp = $name . '_group';
        if ($field->iscode) {
            // A code field is added on its own, as it is for every other kind
            // of field: CodeRunner positions Ace's full-screen control against
            // the textarea's own container, and a group wrapper leaves it half
            // visible in the corner. Its components and its note follow in the
            // group where the override checkbox used to be.
            $mform->addElement(self::input_element($mform, $field, $language));
            $mform->addGroup($members, $name . '_group', '', '&nbsp;&nbsp;', false);
            if ($field->help() !== null) {
                $mform->addHelpButton($name, $field->help_identifier(), 'qtype_coderunner');
            }
        } else {
            // Everything else keeps the shape every other field has — control
            // and note on one row, under the field's label — with nothing
            // where the override checkbox was.
            $mform->addGroup(
                array_merge([self::input_element($mform, $field, $language)], $members),
                $name . '_group',
                self::field_label($field),
                '&nbsp;&nbsp;&nbsp;',
                false
            );
            if ($field->help() !== null) {
                $mform->addHelpButton($name . '_group', $field->help_identifier(), 'qtype_coderunner');
                $componenthelp = null;
            }
        }

        // The components' help, where the group has none of its own. Both of
        // this field's components share `twigcontrols_help`, so the first one
        // that has help speaks for them.
        foreach ($componenthelp === null ? [] : $field->perlayerwith as $componentname) {
            $component = field_registry::get($componentname);
            if ($component->help() !== null) {
                $mform->addHelpButton($componenthelp, $component->help_identifier(), 'qtype_coderunner');
                break;
            }
        }

        foreach ($defaults as $componentname => $value) {
            $mform->setDefault($componentname, $value);
            $mform->setType($componentname, self::param_type(field_registry::get($componentname)));
        }

        // What this layer itself says, and nothing else: its own document, or
        // its own qualifier, or the registry default where it has none.
        // Showing the nearest ancestor's instead — which is what a field with
        // an Inherit control does, so that clearing the control shows what the
        // field falls back to — invited the author to adopt a copy of it, and
        // a copy of a document merges to no effect while ceasing to follow the
        // original.
        $mform->setDefault(
            $name,
            $own->has($field) ? $own->get($field) : ($field->merges_with_inherited() ? '' : $field->default)
        );
        $mform->setType($name, self::param_type($field));
    }

    /**
     * What a merging field's document merges with: the documents of the layers
     * above, merged where they can be, and named where they cannot.
     *
     * A document is only merged for display when every contributing layer's is
     * readable as JSON. A layer whose parameters are written in Twig or
     * evaluated by a language cannot be merged without running it, which a
     * form cannot do, so those layers are named instead of being guessed at.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param field $field the field.
     * @param question_settings $inherited what the layers above contribute.
     * @param override_set $own what this layer contributes.
     * @return object the form element.
     */
    protected static function merge_note(
        \MoodleQuickForm $mform,
        field $field,
        question_settings $inherited,
        override_set $own
    ) {
        $contributions = [];
        foreach ($inherited->layers_of($field) as $layer) {
            if (!self::document_is_empty($layer['value'])) {
                $contributions[] = $layer;
            }
        }

        if (!$contributions) {
            $text = get_string('mergesnothing', 'qtype_coderunner');
        } else {
            $merged = [];
            $names = [];
            foreach ($contributions as $layer) {
                $names[] = self::layer_name($layer['source']);
                $decoded = json_decode((string) $layer['value'], true);
                if (!is_array($decoded)) {
                    $merged = null;
                    break;
                }
                // Root first, so a nearer layer's key wins, as it does when
                // the question runs.
                $merged = array_merge($merged, $decoded);
            }

            $text = $merged === null
                ? get_string('mergeswithlayers', 'qtype_coderunner', implode(', ', $names))
                : get_string(
                    'mergeswith',
                    'qtype_coderunner',
                    self::summarise($field, json_encode($merged))
                );
        }

        $marker = $own->has($field) && !self::document_is_empty($own->get($field))
            ? self::changed_marker()
            : '';

        return $mform->createElement(
            'static',
            $field->name . '_source',
            '',
            $marker . \html_writer::tag('small', $text, ['class' => 'text-muted'])
        );
    }

    /**
     * Whether a JSON document says nothing, and so contributes nothing to a
     * merge.
     *
     * An unreadable document is not empty: it may be Twig, which this cannot
     * evaluate, and treating it as nothing would claim a question changes
     * nothing when it may change everything.
     *
     * @param mixed $value the document.
     * @return bool true if it contributes nothing.
     */
    public static function document_is_empty($value): bool {
        $text = trim((string) $value);
        if ($text === '') {
            return true;
        }
        // Both `{}` and `[]` decode to the empty array.
        return json_decode($text, true) === [];
    }

    /**
     * The mark put beside a field this layer changes.
     *
     * An asterisk, in the accent colour, and not `i/warning`: that one maps to
     * `fa-triangle-exclamation text-warning` in core's icon map, so it is a
     * hazard triangle and reads as something being wrong rather than as a
     * mark. An asterisk is also what a footnote reference is, which is what
     * this is: the legend at the foot of the section explains it.
     *
     * @return string the marker's HTML.
     */
    protected static function changed_marker(): string {
        global $OUTPUT;

        return \html_writer::tag(
            'span',
            $OUTPUT->pix_icon('i/asterisk', get_string('fieldchanged', 'qtype_coderunner')),
            ['class' => 'text-primary me-1 small']
        );
    }

    /**
     * The input control for a field, chosen from its registry type.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param field $field the field.
     * @param string $language the language a template is highlighted in.
     * @param bool $ownlabel true when the control carries its own label rather
     *      than taking it from the group it sits in.
     * @param bool $locked true when the field is inherited, which a code field
     *      has to carry in its markup. See the comment where it is used.
     * @return object the form element.
     */
    protected static function input_element(
        \MoodleQuickForm $mform,
        field $field,
        string $language = '',
        bool $ownlabel = false,
        bool $locked = false
    ) {
        $name = $field->name;
        $label = $ownlabel ? self::field_label($field) : '';

        if ($field->type === field_type::CHOICE) {
            return $mform->createElement('select', $name, $label, field_registry::choices($name));
        }
        if ($field->type === field_type::BOOLEAN) {
            // Not a checkbox: it would sit beside the override checkbox as two
            // identical boxes with a single label between them, and no way to
            // tell which was which.
            return $mform->createElement('select', $name, $label, [
                0 => get_string('no'),
                1 => get_string('yes'),
            ]);
        }
        if ($field->type === field_type::TEXT || $field->type === field_type::JSON) {
            // A code field carries its own label, since it is not in a group.
            $label = ($field->iscode || $ownlabel) ? self::field_label($field) : '';
            $attributes = ['rows' => $field->rows ?: ($field->iscode ? 12 : 2)];
            if ($field->iscode) {
                // InterfaceWrapper reads the language from this attribute and
                // overwrites whatever data-params holds, so this is the only
                // place that can set it. Template parameters and UI parameters
                // are deliberately left without one, because they may contain
                // Twig and a JSON mode reports errors that are not there.
                $attributes['data-lang'] = self::ace_language($field, $language);
                $attributes['class'] = 'edit_code';
                if ($locked) {
                    // Ace reads the textarea's readonly attribute when it is
                    // constructed and never looks at it again, so an inherited
                    // code field has to say so in the markup. `disabledIf`
                    // alone greys a textarea the author can no longer see:
                    // Ace has replaced it with an editor of its own, which
                    // stays editable. See {@see require_ace()} for the other
                    // half, which follows the checkbox afterwards.
                    $attributes['readonly'] = 'readonly';
                }
            } else {
                $attributes['cols'] = 80;
            }
            return $mform->createElement('textarea', $name, $label, $attributes);
        }
        if ($field->type === field_type::INTEGER) {
            return $mform->createElement('text', $name, $label, ['size' => 6]);
        }
        return $mform->createElement('text', $name, $label, ['size' => 40]);
    }

    /**
     * A field's label, with a second line on the ones that merge saying so.
     *
     * The distinction is the hardest thing about these two fields and it
     * cannot be read off the control: a box of JSON looks the same whether it
     * replaces what is above it or adds to it. The form labels say which it
     * is; the help text says what that means.
     *
     * @param field $field the field.
     * @return string the label, which a form renders as HTML.
     */
    protected static function field_label(field $field): string {
        if (!$field->merges_with_inherited()) {
            return $field->label();
        }

        return $field->label() . \html_writer::tag(
            'span',
            get_string('mergeswithparent', 'qtype_coderunner'),
            ['class' => 'd-block fw-normal text-muted small']
        );
    }

    /**
     * Whether a layer's override of a field says nothing: it holds the value
     * the field would have had anyway.
     *
     * The migration pins twenty fields on every pre-6.0 question whether or
     * not the value differs from what the chain supplies, so most overrides on
     * a migrated question are of this kind. They are real overrides and are
     * left alone in the data — dropping them would unfreeze behaviour the
     * migration deliberately froze — but presenting them as changes makes a
     * form full of nothing look full of something.
     *
     * Compared through the registry's own normalisation, so that a null and an
     * empty string are one value for a string field and two for an integer
     * (decision 2).
     *
     * @param field $field the field.
     * @param override_set $own what this layer overrides.
     * @param question_settings $inherited what the layers above resolve to.
     * @return bool true if the override makes no difference.
     */
    public static function is_redundant(field $field, override_set $own, question_settings $inherited): bool {
        if (!$own->has($field)) {
            return false;
        }

        if ($field->merges_with_inherited()) {
            // Such a field changes nothing when it says nothing. It cannot be
            // compared against what it would inherit, because it does not
            // replace that: it is merged with it, so an identical document
            // and an empty one have the same effect and a different one adds
            // to rather than supplants what is above.
            return self::document_is_empty($own->get($field));
        }

        if (!$field->resolves_to_one_value()) {
            // A qualifier of this layer's own contribution, measured against
            // the registry default rather than against the layer above:
            // `chain_layers()` gives a layer that does not set it the default,
            // so that — and not the parent's value — is what this layer would
            // have had if it said nothing.
            return $field->normalise_for_comparison($own->get($field))
                === $field->normalise_for_comparison($field->default);
        }

        return $field->normalise_for_comparison($own->get($field))
            === $field->normalise_for_comparison(self::inherited_value($field, $inherited));
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
    protected static function provenance_element(
        \MoodleQuickForm $mform,
        field $field,
        question_settings $inherited,
        bool $isoverridden,
        ?override_set $own = null
    ) {
        global $OUTPUT;

        $own ??= new override_set([], '');
        $value = self::inherited_value($field, $inherited);
        $source = $inherited->was_set($field) ? self::layer_name($inherited->source_of($field)) : null;

        // What the field would take if Inherit were ticked, which is the one
        // thing an author cannot read off the form otherwise. Always said, and
        // always in the same words: "Inherited" beside a box labelled "Inherit"
        // read as a second opinion on the same question.
        //
        // The layer is named only when it is one the author could go and edit.
        // Nearly every field of nearly every question resolves to Site
        // defaults or to the registry default, and saying so on forty rows
        // tells an author nothing.
        if (in_array($value, [null, ''], true)) {
            $text = get_string('parentnotset', 'qtype_coderunner');
        } else if ($source === null || $source === self::SITE_DEFAULTS_LAYER) {
            $text = get_string('parentvalue', 'qtype_coderunner', self::summarise($field, $value));
        } else {
            $text = get_string('parentvaluefrom', 'qtype_coderunner', [
                'value' => self::summarise($field, $value),
                'source' => $source,
            ]);
        }

        // A field whose override really changes something is marked, so that
        // the count on the section header can be found without opening every
        // field. The ones that merely restate what they would inherit — most
        // of them, on a migrated question — are left unmarked.
        $marker = $isoverridden && !self::is_redundant($field, $own, $inherited)
            ? self::changed_marker()
            : '';

        return $mform->createElement(
            'static',
            $field->name . '_source',
            '',
            $marker . \html_writer::tag('small', $text, ['class' => 'text-muted'])
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
    public static function require_ace(
        array $sections,
        string $language = '',
        bool $withinstancefields = false
    ): void {
        global $PAGE;

        \qtype_coderunner_util::load_ace();

        $inheritable = [];
        foreach ($sections as $section) {
            foreach (self::fields_of($section, $withinstancefields) as $name => $field) {
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
                if ($field->is_inheritable() && !$field->merges_with_inherited()) {
                    $inheritable[] = $name;
                }
            }
        }

        if ($inheritable) {
            // The one place this component needs JavaScript, and only for the
            // code fields. Every other control is greyed by `disabledIf`,
            // which sets the `disabled` attribute; by then Ace has replaced
            // the textarea with an editor that neither carries that attribute
            // nor watches for it, so an inherited template stayed fully
            // editable and an author's edit to it was dropped on save. The
            // markup carries the initial state (see {@see input_element()});
            // this follows the checkbox from there on.
            $PAGE->requires->js_call_amd('qtype_coderunner/inheritedcode', 'init', [$inheritable]);
        }
    }

    /**
     * Submitted text with its line endings in the form the rest of CodeRunner
     * uses.
     *
     * An HTML form submits CRLF whatever the textarea was given, so saving a
     * template that was stored with newlines rewrites every line of it. The
     * text is unchanged to look at, which makes the consequence worse rather
     * than better: a change history reports the field as edited, and the
     * before and after columns show the same thing.
     *
     * CodeRunner 5 has the same behaviour and patches one symptom of it in
     * renderer.php, where a submitted answer is cleaned on the way back out.
     * This cleans it on the way in, which is where it starts.
     *
     * @param mixed $value the submitted value.
     * @return mixed the value, with CRLF and bare CR turned into newlines.
     */
    protected static function normalise_newlines($value) {
        return is_string($value) ? str_replace(["\r\n", "\r"], "\n", $value) : $value;
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
            // Instance fields are not asked for here at all, whichever layer is
            // being edited: a question keeps them in its own columns, and a
            // prototype that stored one would break every question beneath it.
            // Refusing them on the write path as well as in the rendering means
            // a form that offers one by mistake still cannot store it.
            foreach (self::fields_of($section) as $name => $field) {
                if (!$field->resolves_to_one_value()) {
                    // No Inherit control to consult: what the control holds is
                    // the whole of what this layer says, and it is stored only
                    // when it says something — an empty document, or a
                    // qualifier at the registry default, is dropped rather
                    // than stored. That is safe here and nowhere else: for a
                    // collected field, holding nothing and saying nothing are
                    // the same thing, so nothing the migration pinned comes
                    // unfrozen. For a field that replaces, an empty value is a
                    // real override that blanks what would be inherited.
                    if (!array_key_exists($name, $data)) {
                        continue;
                    }
                    unset($values[$name]);
                    $value = legacy_converter::cast($name, self::normalise_newlines($data[$name]));
                    $saysnothing = $field->merges_with_inherited()
                        ? self::document_is_empty($value)
                        : $field->normalise_for_comparison($value)
                            === $field->normalise_for_comparison($field->default);
                    if (!$saysnothing) {
                        $values[$name] = $value;
                    }
                    continue;
                }

                $key = self::INHERIT_PREFIX . $name;
                if (!array_key_exists($key, $data)) {
                    // The form did not ask about this field, so it says nothing
                    // about it: whatever the layer overrode before stands. Only
                    // a field the author was actually shown can have its
                    // override dropped.
                    continue;
                }

                unset($values[$name]);
                if (empty($data[$key]) && array_key_exists($name, $data)) {
                    // Not inherited, so the author's value is an override.
                    $values[$name] = legacy_converter::cast($name, self::normalise_newlines($data[$name]));
                }
            }
        }

        return new override_set($values, $source !== '' ? $source : $existing->source);
    }
}
