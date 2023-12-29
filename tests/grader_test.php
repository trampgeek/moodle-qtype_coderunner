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

// Tests of various graders other than the default 'equality grader', which
// is extensively tested by the other tests.

/**
 * Unit tests for various CodeRunner graders.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');

/**
 * Unit tests for various CodeRunner graders.
 * @coversNothing
 */
class grader_test extends \qtype_coderunner_testcase {
    public function test_regex_grader() {
        // Check using a question that reads stdin and writes to stdout.
        $q = $this->make_question('copy_stdin');
        $q->grader = 'RegexGrader';
        $q->testcases = [
            (object) ['testcode' => 'copy_stdin()',
                          'stdin'       => "Line1\n  Line2  \n /123Line 3456/ \n",
                          'extra'       => '',
                          'expected'    => "^ *Line1 *\n +Line2 +. /[1-3]{3}Line *3[4-6]{3}/ *\n$",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' => 0]];
        $code = <<<EOCODE
def copy_stdin():
  try:
    while True:
        line = input()
        print(line)
  except EOFError:
    pass
EOCODE;
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $testoutcome = unserialize($cache['_testoutcome']); // For debugging test.
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
    }

    public function test_nearequality_grader_right_answer() {
        // Check using a question that reads stdin and writes to stdout.
        $q = $this->make_question('copy_stdin');
        $q->grader = 'NearEqualityGrader';
        $q->testcases = [
            (object) ['testcode' => 'copy_stdin()',
                          'stdin'       => "line 1 \nline  2  \nline   3   \n\n\n",
                          'extra'       => '',
                          'expected'    => "Line 1\nLine 2\nLine 3\n",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' => 0]];
        $code = <<<EOCODE
def copy_stdin():
  try:
    while True:
        line = input()
        print(line)
  except EOFError:
    pass
EOCODE;
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $testoutcome = unserialize($cache['_testoutcome']); // For debugging test.
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
    }

    public function test_nearequality_grader_wrong_answer() {
        // Check using a question that reads stdin and writes to stdout.
        $q = $this->make_question('copy_stdin');
        $q->grader = 'NearEqualityGrader';
        $q->testcases = [
            (object) ['testcode' => 'copy_stdin()',
                          'stdin'       => " line 1 \n line  2  \n line   3   \n\n\n",
                          'extra'       => '',
                          'expected'    => "Line 1\nLine 2\nLine 3\n",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' => 0]];
        $code = <<<EOCODE
def copy_stdin():
  try:
    while True:
        line = input()
        print(line)
  except EOFError:
    pass
EOCODE;
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $testoutcome = unserialize($cache['_testoutcome']); // For debugging test.
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
    }
}
