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
 * The field values supplied by one layer of a prototype chain.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * The field values one layer of a prototype chain supplies.
 *
 * A layer is a prototype, or a question's own overrides. Both are recorded the
 * same way, which is what lets one piece of code handle inheritance within the
 * chain and inheritance by a question from its prototype.
 *
 * The one rule this class exists to enforce is that **absent is not the same as
 * empty**. A field this layer does not mention is inherited; a field it sets to
 * the empty string is an override to the empty string. Today's code conflates
 * the two, so a prototype's value cannot be deliberately blanked. Relying on
 * callers to remember array_key_exists() rather than isset() or empty() would
 * reintroduce that bug, so key presence is tested here and nowhere else.
 *
 * Values are not validated against the field's type: that is the authoring
 * form's job, and a layer loaded from the database may predate a type change.
 */
final class override_set {
    /** @var array field name => value, holding only the fields this layer sets. */
    private array $values;

    /**
     * @param array $values field name => value. A name absent from the array is
     *      inherited; a name present with any value, including null or the empty
     *      string, is an override.
     * @param string $source a label for where these values came from, shown in
     *      the authoring forms and the prototype editor's resolved view.
     * @throws \coding_exception if a name is not a registry field.
     */
    public function __construct(array $values = [], public readonly string $source = '') {
        foreach (array_keys($values) as $name) {
            if (!field_registry::exists($name)) {
                throw new \coding_exception("Unknown CodeRunner question field '$name' in override set '$source'.");
            }
        }
        $this->values = $values;
    }

    /**
     * Whether this layer supplies the given field.
     *
     * @param field|string $field the field or its name.
     * @return bool true if this layer sets it, even to null or the empty string.
     */
    public function has(field|string $field): bool {
        return array_key_exists(self::name_of($field), $this->values);
    }

    /**
     * This layer's value for the given field.
     *
     * @param field|string $field the field or its name.
     * @return mixed the value, or null if this layer does not supply the field.
     *      Use has() to tell a stored null from an absent field.
     */
    public function get(field|string $field): mixed {
        return $this->values[self::name_of($field)] ?? null;
    }

    /**
     * The names of the fields this layer supplies.
     *
     * @return string[] the field names.
     */
    public function names(): array {
        return array_keys($this->values);
    }

    /**
     * The fields this layer supplies, as registry entries.
     *
     * @return field[] name => field.
     */
    public function fields(): array {
        $fields = [];
        foreach ($this->names() as $name) {
            $fields[$name] = field_registry::get($name);
        }
        return $fields;
    }

    /**
     * How many fields this layer supplies.
     *
     * @return int the number of overridden fields.
     */
    public function count(): int {
        return count($this->values);
    }

    /**
     * What changed between another override set and this one.
     *
     * Fields, not text: comparing two overrides documents as JSON would report
     * a reordering or a whitespace change as a difference, which is noise. The
     * question form needs this too, to count what a question overrides.
     *
     * Values are compared through the registry's normalisation, so a stored
     * '1' and a resolved 1 are the same value, and null and the empty string
     * are the same for a string field but not for an integer one.
     *
     * @param override_set $previous the set this one is compared against.
     * @return array field name => ['change' => 'added'|'removed'|'changed',
     *      'from' => mixed, 'to' => mixed], in registry order.
     */
    public function differences_from(override_set $previous): array {
        $differences = [];

        foreach (field_registry::all() as $name => $field) {
            $wasset = $previous->has($field);
            $isset = $this->has($field);

            if (!$wasset && !$isset) {
                continue;
            }

            $from = $wasset ? $previous->get($field) : null;
            $to = $isset ? $this->get($field) : null;

            if (!$wasset) {
                $differences[$name] = ['change' => 'added', 'from' => null, 'to' => $to];
            } else if (!$isset) {
                $differences[$name] = ['change' => 'removed', 'from' => $from, 'to' => null];
            } else if ($field->normalise_for_comparison($from) !== $field->normalise_for_comparison($to)) {
                $differences[$name] = ['change' => 'changed', 'from' => $from, 'to' => $to];
            }
        }

        return $differences;
    }

    /**
     * All of this layer's values.
     *
     * @return array field name => value.
     */
    public function to_array(): array {
        return $this->values;
    }

    /**
     * A copy of this layer with the given field set.
     *
     * @param field|string $field the field or its name.
     * @param mixed $value the value to set, which may be null or the empty string.
     * @return self a new override set; this one is unchanged.
     */
    public function with(field|string $field, mixed $value): self {
        $values = $this->values;
        $values[self::name_of($field)] = $value;
        return new self($values, $this->source);
    }

    /**
     * A copy of this layer with the given field no longer set, so that it
     * inherits again.
     *
     * @param field|string $field the field or its name.
     * @return self a new override set; this one is unchanged.
     */
    public function without(field|string $field): self {
        $values = $this->values;
        unset($values[self::name_of($field)]);
        return new self($values, $this->source);
    }

    /**
     * The name of a field given either the field or its name, checking that a
     * name refers to a real field so that a typo fails here rather than
     * silently reading as an unset field.
     *
     * @param field|string $field the field or its name.
     * @return string the field name.
     * @throws \coding_exception if the name is not a registry field.
     */
    private static function name_of(field|string $field): string {
        if ($field instanceof field) {
            return $field->name;
        }
        if (!field_registry::exists($field)) {
            throw new \coding_exception("Unknown CodeRunner question field '$field'.");
        }
        return $field;
    }
}
