<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * coderunner escape functions for use with the Twig template library
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * An escaper for user with Python triple-doublequote delimiters. Escapes only
 * double quote characters plus backslashes.
 * @param type $environ   The Twig environment (currently ignored)
 * @param type $s         The string to convert
 * @param type $charset   The charset (currenly ignored)
 * @return typestudentanswervar
 */
function pythonEscaper($environ, $s, $charset) {
    return str_replace('"', '\"', str_replace('\\', '\\\\', $s));
}

/**
 * An escaper for user with Matlab. Since Matlab has quirky string syntax,
 * this escaper should only be used to produce a string to pass as a parameter
 * to sprintf.
 * @param type $environ   The Twig environment (currently ignored)
 * @param type $s         The string to convert
 * @param type $charset   The charset (currenly ignored)
 * @return type
 */
function matlabEscaper($environ, $s, $charset) {
    return str_replace(
                array("'",  "\n", "\r", '%'),
                array("''", '\\n',  '',  '%%'),
                str_replace('\\n', '\\\\n', $s));
}


/**
 * An escaper for user with Java or C. Implements all the standard single char
 * character escapes in Java (though not all the C ones).
 * @param type $environ   The Twig environment (currently ignored)
 * @param type $s         The string to convert
 * @param type $charset   The charset (currenly ignored)
 * @return type
 */
function javaEscaper($environ, $s, $charset) {
    return str_replace(
                array("'",    '"',  "\n",  "\r",   "\t", "\f",  "\b"),
                array("\\'", '\\"', "\\n", "\\r", "\\t","\\f", "\\b"),
                str_replace("\\", "\\\\", $s));
}

