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
 * One field of a CodeRunner question.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * A description of one field of a CodeRunner question: who may supply it, how a
 * layer's value combines with an inherited one, and where it appears in the
 * authoring forms.
 *
 * Instances are built only by {@see field_registry} and cannot be modified
 * afterwards.
 */
final readonly class field {
    /**
     * Describe one field.
     *
     * @param string $name the field's name, which is also its column name when it has one.
     * @param field_kind $kind who may supply the field.
     * @param field_type $type the type of its value.
     * @param form_section $section where it appears in the authoring forms.
     * @param mixed $default the value a question gets when no layer in its chain sets the field.
     * @param merge_rule $merge how a layer's value combines with the inherited one.
     * @param bool $twigable true if the field is Twig expanded when twigall is set.
     * @param bool $incolumn true if the field is stored as a column of question_coderunner_options.
     * @param string[] $perlayerwith other fields that travel with this one, per layer, and cannot be separated from it.
     * @param int $rows how many lines the field's editor gets, when neither
     *      the code default of twelve nor the plain-text default of two suits
     *      it. A result-columns specification is usually four short lines, and
     *      a twelve-line box for it is mostly empty; an author who needs more
     *      drags the editor's handle.
     * @param bool $iscode true if the field holds code or a JSON document, and
     *      is therefore edited in Ace rather than in a plain textarea. Short
     *      strings that merely happen to be typed as text, such as a filename
     *      pattern, are not code.
     * @param string $labelstring the language string identifying this field's
     *      label, when it is not the field's own name. CodeRunner 5's form
     *      labels several fields under a group heading, so the control itself
     *      carries either no label or one named differently; the prototype
     *      editor shows each field on its own with its provenance, so every
     *      field needs a label of its own.
     */
    public function __construct(
        /** @var string The field's name, which is also its column name when it has one. */
        public string $name,
        /** @var field_kind Who may supply the field. */
        public field_kind $kind,
        /** @var field_type The type of its value. */
        public field_type $type,
        /** @var form_section Where it appears in the authoring forms. */
        public form_section $section,
        /** @var mixed The value a question gets when no layer in its chain sets the field. */
        public mixed $default = null,
        /** @var merge_rule How a layer's value combines with the inherited one. */
        public merge_rule $merge = merge_rule::REPLACE,
        /** @var bool True if the field is Twig expanded when twigall is set. */
        public bool $twigable = false,
        /** @var bool True if the field is stored as a column of question_coderunner_options. */
        public bool $incolumn = true,
        /** @var string[] Other fields that travel with this one, per layer. */
        public array $perlayerwith = [],
        /** @var string The label's language string id, when not the field name. */
        public string $labelstring = '',
        /** @var bool True if the field holds code or JSON and is edited in Ace. */
        public bool $iscode = false,
        /** @var string The help's language string id, when not the label's. */
        public string $helpstring = '',
        /** @var int How many lines its editor gets, when the default is wrong. */
        public int $rows = 0,
    ) {
    }

    /**
     * Whether a prototype may supply this field.
     *
     * @return bool true unless the field belongs to the question alone.
     */
    public function is_inheritable(): bool {
        return $this->kind->is_inheritable();
    }

    /**
     * Whether this field resolves to a single value, as opposed to being
     * collected from every layer for the consumer to combine.
     *
     * @return bool true if the field resolves to one value.
     */
    public function resolves_to_one_value(): bool {
        return $this->kind->resolves_to_one_value();
    }

    /**
     * This field's value reduced to a canonical form, for comparing a
     * CodeRunner 5 resolution against a CodeRunner 6 one.
     *
     * The three value types differ in whether null means the same as empty, and
     * getting it wrong in either direction is a real error:
     *
     * - For a boolean, null and 0 are one value. `allowmultiplestdins` is only
     *   read in a boolean test (`classes/jobrunner.php`), so a prototype
     *   storing null and a default of 0 agree.
     * - For a string, including JSON, null and the empty string are one value.
     *   `acelang` is read through `empty()` (`renderer.php`) and
     *   `resultcolumns` through a truthiness test (`question.php`), and
     *   CodeRunner 5's own `set_inherited_fields()` likewise treated an empty
     *   string as "not set".
     * - For an integer, they are **not** one value. `cputimelimitsecs` is read
     *   through `isset()` (`question.php`), so null means no limit and 0 means
     *   a limit of zero.
     *
     * @param mixed $value the value to canonicalise.
     * @return mixed the canonical form.
     */
    public function normalise_for_comparison(mixed $value): mixed {
        return match ($this->type) {
            field_type::BOOLEAN => (int) (bool) $value,
            field_type::INTEGER => $value === null ? null : (int) $value,
            field_type::FILES => $value,
            // Line endings are not content. A form submits CRLF whatever it
            // was given, so without this every multi-line field looks changed
            // the first time a prototype is saved through the editor.
            default => $value === null ? '' : str_replace(["\r\n", "\r"], "\n", (string) $value),
        };
    }

    /**
     * The identifier of this field's label string in the language file.
     *
     * @return string the string identifier.
     */
    public function label_string_id(): string {
        return $this->labelstring !== '' ? $this->labelstring : $this->name;
    }

    /**
     * This field's label, for a form or a resolved-settings display.
     *
     * @return string the label.
     */
    public function label(): string {
        return get_string($this->label_string_id(), 'qtype_coderunner');
    }

    /**
     * This field's help text, if there is any.
     *
     * Help is optional by design. A field without it gets no help button rather
     * than a broken one, so that help can be written field by field without
     * every form being blocked on the last of it.
     *
     * @return string|null the help text, or null if none is defined.
     */
    public function help(): ?string {
        $id = $this->help_string_id();
        return get_string_manager()->string_exists($id, 'qtype_coderunner')
            ? get_string($id, 'qtype_coderunner')
            : null;
    }

    /**
     * The identifier of this field's help string in the language file.
     *
     * @return string the string identifier.
     */
    public function help_string_id(): string {
        return $this->help_identifier() . '_help';
    }

    /**
     * The identifier a help button is registered under.
     *
     * Moodle's help icon takes an identifier and looks up both it and that
     * identifier with '_help' appended, so the pair has to share a base. Where
     * a field borrows the help of the CodeRunner 5 group it used to sit in, the
     * base is that group's, not the field's.
     *
     * @return string the identifier.
     */
    public function help_identifier(): string {
        if ($this->helpstring === '') {
            return $this->label_string_id();
        }
        return preg_replace('/_help$/', '', $this->helpstring);
    }
}
