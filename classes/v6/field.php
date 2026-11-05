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
            default => $value === null ? '' : (string) $value,
        };
    }

    /**
     * The identifier of this field's label string in the language file.
     *
     * @return string the string identifier.
     */
    public function label_string_id(): string {
        return $this->name;
    }

    /**
     * The identifier of this field's help string in the language file.
     *
     * @return string the string identifier.
     */
    public function help_string_id(): string {
        return $this->name . '_help';
    }
}
