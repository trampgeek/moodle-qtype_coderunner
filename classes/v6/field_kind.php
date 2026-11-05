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
 * Who may supply a CodeRunner question field.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * Who may supply a field, and whether it resolves to a single value.
 */
enum field_kind: string {
    /** Belongs to the question alone; never inherited. */
    case INSTANCE = 'instance';

    /** Resolves to one value, from the nearest layer in the chain that sets it. */
    case INHERITED = 'inherited';

    /**
     * Does not resolve to a single value. Every layer's contribution is
     * collected and combined by the consumer, because combining requires work
     * the resolver cannot do: each layer's template parameters must be
     * evaluated in that layer's own language before they can be merged, and
     * each layer's UI parameters are merged only if that layer's UI plugin
     * matches the resolved one.
     */
    case COLLECTED = 'collected';

    /**
     * Whether a prototype may supply a field of this kind.
     *
     * @return bool true for anything but an instance field.
     */
    public function is_inheritable(): bool {
        return $this !== self::INSTANCE;
    }

    /**
     * Whether a field of this kind resolves to a single value.
     *
     * @return bool false only for collected fields.
     */
    public function resolves_to_one_value(): bool {
        return $this !== self::COLLECTED;
    }
}
