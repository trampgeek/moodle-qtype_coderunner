<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
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
 * coderunner escape functions for use with the Twig template library
 *
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This class wraps the various escaper functions required by Twig.
class qtype_coderunner_escapers {
    /**
     * An escaper for use with Python. Escapes only
     * double quote characters plus backslashes.
     * @param type $environ   The Twig environment (currently ignored)
     * @param type $s         The string to convert
     * @param type $charset   The charset (currenly ignored)
     * @return typestudentanswervar
     */
    public static function python($environ, $s, $charset) {
        return str_replace('"', '\"', str_replace('\\', '\\\\', $s));
    }

    /**
     * An escaper for use with Matlab. Since Matlab has quirky string syntax,
     * this escaper should only be used to produce a string to pass as a parameter
     * to sprintf.
     * @param type $environ   The Twig environment (currently ignored)
     * @param type $s         The string to convert
     * @param type $charset   The charset (currenly ignored)
     * @return type
     */
    public static function matlab($environ, $s, $charset) {
        return str_replace(
            ["'", "\n", "\r", '%'],
            ["''", '\\n', '', '%%'],
            str_replace('\\n', '\\\\n', $s)
        );
    }


    /**
     * An escaper for use with Java or C. Implements all the standard single char
     * character escapes in Java (though not all the C ones).
     * @param type $environ   The Twig environment (currently ignored)
     * @param type $s         The string to convert
     * @param type $charset   The charset (currenly ignored)
     * @return type
     */
    public static function java($environ, $s, $charset) {
        return str_replace(
            ["'", '"', "\n", "\r", "\t", "\f", "\b"],
            ["\\'", '\\"', "\\n", "\\r", "\\t", "\\f", "\\b"],
            str_replace("\\", "\\\\", $s)
        );
    }
}
