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
 * The type of a CodeRunner question field's value.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * The type of a field's value, which decides the control the forms render.
 */
enum field_type: string {
    /** A yes/no flag. */
    case BOOLEAN = 'bool';

    /** A whole number. */
    case INTEGER = 'int';

    /** A short string. */
    case CHAR = 'char';

    /** A long string. */
    case TEXT = 'text';

    /** A JSON document. */
    case JSON = 'json';

    /** One of a fixed set of values. */
    case CHOICE = 'choice';

    /** A set of files, held through the file API rather than in a column. */
    case FILES = 'files';
}
