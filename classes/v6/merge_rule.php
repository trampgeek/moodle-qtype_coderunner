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
 * How a layer's value for a field combines with an inherited one.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * How a layer's value for a field combines with the value it inherits.
 */
enum merge_rule: string {
    /** The nearest layer that sets the field wins outright. */
    case REPLACE = 'replace';

    /** JSON objects merged key by key, the later layer winning. */
    case BY_KEY = 'bykey';

    /** Files merged by filename, the later layer winning. */
    case BY_FILENAME = 'byfilename';

    /** Collected per layer with its language, then merged once each is evaluated. */
    case AFTER_EVALUATION = 'afterevaluation';

    /** Collected per layer, merged only where that layer's UI plugin matches the resolved one. */
    case IF_SAME_UI = 'ifsameui';
}
