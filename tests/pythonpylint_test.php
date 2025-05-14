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
 * Unit tests for the python pylint questions (if installed).
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
 * Unit tests for the coderunner question definition class.
 * @coversNothing
 */
class pythonpylint_test extends \qtype_coderunner_testcase {
    protected function setUp(): void {
        parent::setUp();

        // Each test will be skipped if python3 not available on jobe server.
        $this->check_language_available('python3');
    }

    public function test_pylint_func_good() {
        // Test that a python3_pylint question with a good pylint-compatible.
        // submission passes.
        $q = $this->make_question('sqr_pylint');
        $q->templateparams = '{"isfunction":true}';
        $code = <<<EOCODE
import posix  # Checks if pylint bug patched
def sqr(n):
    '''This is a comment'''
    if posix.F_OK == 0:
        return n * n
    else:
        return 0

EOCODE;
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [, $grade, ] = $result;
        $this->assertEquals(\question_state::$gradedright, $grade);
    }

    public function test_pylint_func_bad() {
        // Test that a python3_pylint question with a bad (pylint-incompatible)
        // submission fails.
        $q = $this->make_question('sqr_pylint');
        // Code lacks a docstring.
        $code = <<<EOCODE
def sqr(n):
  return n * n

EOCODE;
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(\question_state::$gradedwrong, $grade);
    }
}
