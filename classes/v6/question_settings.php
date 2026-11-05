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
 * The effective settings of a CodeRunner question.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * What CodeRunner actually runs: every field's effective value, and the layer
 * each value came from.
 *
 * Produced by {@see prototype_chain::resolve()} and never built directly, so
 * that the chain is walked exactly once and there is a single answer to which
 * layers were in play.
 *
 * Inherited fields resolve to one value, read with get(). Collected fields do
 * not: template parameters must be evaluated in each layer's own language
 * before they can be merged, and UI parameters are merged only for layers whose
 * UI plugin matches the resolved one. Those are read with layers_of(), which
 * returns each contributing layer in root-first order for the consumer to
 * combine.
 */
final class question_settings {
    /**
     * @param array $values field name => resolved value, for fields that
     *      resolve to one value.
     * @param array $sources field name => the source label of the layer the
     *      value came from, or '' for a registry default.
     * @param array $collected field name => list of ['value' => mixed,
     *      'source' => string, 'layer' => override_set], root first.
     * @param string[] $chain the source labels of every layer, root first.
     */
    public function __construct(
        private readonly array $values,
        private readonly array $sources,
        private readonly array $collected,
        public readonly array $chain = [],
    ) {
    }

    /**
     * The effective value of a field that resolves to one value.
     *
     * @param field|string $field the field or its name.
     * @return mixed the resolved value.
     * @throws \coding_exception if the field is a collected one, which has no
     *      single value, or if the name is not a registry field.
     */
    public function get(field|string $field): mixed {
        $f = self::field_of($field);
        if (!$f->resolves_to_one_value()) {
            throw new \coding_exception(
                "Field '{$f->name}' is collected from every layer and has no single value; use layers_of() instead."
            );
        }
        return $this->values[$f->name] ?? null;
    }

    /**
     * Which layer a resolved value came from.
     *
     * @param field|string $field the field or its name.
     * @return string the source label of the layer that supplied it, or '' if
     *      no layer did and the registry default was used.
     */
    public function source_of(field|string $field): string {
        return $this->sources[self::field_of($field)->name] ?? '';
    }

    /**
     * Whether a field's value came from a layer rather than from the registry
     * default.
     *
     * @param field|string $field the field or its name.
     * @return bool true if some layer in the chain supplied it.
     */
    public function was_set(field|string $field): bool {
        return array_key_exists(self::field_of($field)->name, $this->sources);
    }

    /**
     * Every contributing layer's value for a collected field, root first.
     *
     * @param field|string $field the field or its name.
     * @return array list of ['value' => mixed, 'source' => string, 'layer' => override_set].
     * @throws \coding_exception if the field resolves to a single value instead.
     */
    public function layers_of(field|string $field): array {
        $f = self::field_of($field);
        if ($f->resolves_to_one_value()) {
            throw new \coding_exception(
                "Field '{$f->name}' resolves to a single value; use get() instead of layers_of()."
            );
        }
        return $this->collected[$f->name] ?? [];
    }

    /**
     * Every resolved value, for fields that resolve to one value.
     *
     * @return array field name => resolved value.
     */
    public function to_array(): array {
        return $this->values;
    }

    /**
     * Every resolved value's source layer.
     *
     * @return array field name => source label.
     */
    public function sources(): array {
        return $this->sources;
    }

    /**
     * The registry entry for a field given either the field or its name.
     *
     * @param field|string $field the field or its name.
     * @return field the registry entry.
     * @throws \coding_exception if the name is not a registry field.
     */
    private static function field_of(field|string $field): field {
        return $field instanceof field ? $field : field_registry::get($field);
    }
}
