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
/** The RegexGrader class. With this grader the *expected* field of the test
 *  case being graded is a PHP-style PERL regular expression, as defined
 *  at http://www.php.net/manual/en/pcre.pattern.php, but excluding the
 *  delimiters and modifiers. The modifiers PCRE_MULTILINE and PCRE_DOTALL
 *  are always added. Internally the delimiter '/' is used but any existing
 *  slashes in the pattern are first escaped with a backslash so the user
 *  doesn't need to worry about the choice of delimiter.
 *  The grader awards full marks if and only if the output matches the
 *  expected pattern (using preg_match) with the addition of the
 *  PCRE_MULTILINE and PCRE_DOTALL modifiers. Otherwise, zero marks are awarded.
 *  Note that preg_match is actually a search function, so any substring of
 *  the output can match the pattern. If the entire output is to be matched
 *  in the normal sense of the term, the pattern should start with '^' and end
 *  with '$'.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class qtype_coderunner_regex_grader extends qtype_coderunner_grader {

    public function name() {
        return 'RegexGrader';
    }

    /** Called to grade the output generated by a student's code for
     *  a given testcase. Returns a single TestResult object.
     *  Should not be called if the execution failed (syntax error, exception
     *  etc).
     */
    public function grade_known_good(&$output, &$testcase) {
        $regex = '/' . str_replace('/', '\/', rtrim($testcase->expected ?? '')) . '/ms';
        $iscorrect = preg_match($regex, $output);
        $awardedmark = $iscorrect ? $testcase->mark : 0.0;
        return new qtype_coderunner_test_result($testcase, $iscorrect, $awardedmark, $output);
    }
}
