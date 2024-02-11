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
 * Unit tests for the coderunner question definition class.
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
require_once($CFG->dirroot . '/lib/accesslib.php');


/**
 * Unit tests for the coderunner question definition class.
 * @coversNothing
 */
class pythonquestions_test extends \qtype_coderunner_testcase {

    /** @var string  */
    private $goodcode;

    protected function setUp(): void {
        parent::setUp();

        // Each test will be skipped if python3 not available on jobe server.
        $this->check_language_available('python3');

        $this->goodcode = "def sqr(n): return n * n";
    }

    public function test_summarise_response() {
        $s = $this->goodcode;
        $q = $this->make_question('sqr');
        $this->assertEquals($s, $q->summarise_response(['answer' => $s]));
    }

    public function test_grade_response_right() {
        $q = $this->make_question('sqr');
        $response = ['answer' => $this->goodcode];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testoutcome->has_syntax_error());
        foreach ($testoutcome->testresults as $tr) {
            $this->assertTrue($tr->iscorrect);
        }
    }

    public function test_grade_response_wrong_ans() {
        $q = $this->make_question('sqr');
        $code = "def sqr(x): return x * x * x / abs(x)";
        $response = ['answer' => $code];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
    }

    public function test_grade_syntax_error() {
        $q = $this->make_question('sqr');
        $code = "def sqr(x): return x  x";
        $response = ['answer' => $code];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertTrue($testoutcome->has_syntax_error());
        $this->assertEquals(0, count($testoutcome->testresults));
    }

    public function test_grade_runtime_error() {
        $q = $this->make_question('sqr');
        $code = "def sqr(x): return x * y";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testoutcome->testresults));
        $this->assertFalse($testoutcome->testresults[0]->iscorrect);
    }

    public function test_student_answer_variable() {
        $q = $this->make_question('studentanswervar');
        $code = "\"\"\"Line1\n\"Line2\"\n'Line3'\nLine4\n\"\"\"";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, ] = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
    }

    public function test_illegal_open_error() {
        $q = $this->make_question('sqr');
        $code = "def sqr(x):\n    f = open('/twaddle/blah/xxx');\n    return x * x";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testoutcome->testresults));
        $this->assertFalse($testoutcome->testresults[0]->iscorrect);
    }

    public function test_grade_delayed_runtime_error() {
        $q = $this->make_question('sqr');
        $code = "def sqr(x):\n  if x != 11:\n    return x * x\n  else:\n    return y";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(3, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->testresults[0]->iscorrect);
        $this->assertFalse($testoutcome->testresults[2]->iscorrect);
    }

    public function test_triple_quotes() {
        $q = $this->make_question('sqr');
        $code = <<<EOCODE
def sqr(x):
    """This is a function
       that squares its parameter"""
    return x * x
EOCODE;
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(5, count($testoutcome->testresults));
        foreach ($testoutcome->testresults as $tr) {
            $this->assertTrue($tr->iscorrect);
        }
    }

    public function test_hellofunc() {
        // Check a question type with a function that prints output.
        $q = $this->make_question('hello_func');
        $code = "def sayHello(name):\n  print('Hello ' + name)";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testoutcome->testresults));
        foreach ($testoutcome->testresults as $tr) {
            $this->assertTrue($tr->iscorrect);
        }
    }

    public function test_copystdin() {
        // Check a question that reads stdin and writes to stdout.
        $q = $this->make_question('copy_stdin');
        $code = <<<EOCODE
def copy_stdin(n):
  for i in range(n):
    line = input()
    print(line)
EOCODE;
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(5, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->testresults[0]->iscorrect);
        $this->assertTrue($testoutcome->testresults[1]->iscorrect);
        $this->assertTrue($testoutcome->testresults[2]->iscorrect);
        $this->assertTrue($testoutcome->testresults[3]->iscorrect);
        $this->assertFalse($testoutcome->testresults[4]->iscorrect);
        $this->assertTrue(strpos($testoutcome->testresults[4]->got, 'EOFError') !== false);
    }

    public function test_timeout() {
         // Check a question that loops forever. Should cause sandbox timeout.
        $q = $this->make_question('timeout');
        $code = "def timeout():\n  while (1):\n    pass";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testoutcome->testresults));
        $this->assertFalse($testoutcome->testresults[0]->iscorrect);
        $this->assertTrue(strpos($testoutcome->testresults[0]->got, 'Time limit exceeded') !== false);
    }

    public function test_exceptions() {
         // Check a function that conditionally throws exceptions.
        $q = $this->make_question('exceptions');
        $code = "def checkOdd(n):\n  if n & 1:\n    raise ValueError()";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(2, count($testoutcome->testresults));
        $this->assertEquals("Exception\n", $testoutcome->testresults[0]->got);
        $this->assertEquals(
            "Yes\nYes\nNo\nNo\nYes\nNo\n",
            $testoutcome->testresults[1]->got
        );
    }

    public function test_partial_mark_question() {
        // Test a question that isn't of the usual allornothing variety.
        $q = $this->make_question('sqr_part_marks');
        $code = "def sqr(n):\n  return -17.995";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(\question_state::$gradedpartial, $grade);
        $this->assertEquals(0, $mark);

        $code = "def sqr(n):\n  return 0";  // Passes first test only.
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(\question_state::$gradedpartial, $grade);
        $this->assertTrue(abs($mark - 0.5 / 7.5) < 0.00001);

        $code = "def sqr(n):\n  return n * n if n <= 0 else -17.995";  // Passes first test and last two only.
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(\question_state::$gradedpartial, $grade);
        $this->assertTrue(abs($mark - 5.0 / 7.5) < 0.00001);

        $code = "def sqr(n):\n    return n * n if n <= 0 else 1 / 0";  // Passes first test then aborts.
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(\question_state::$gradedpartial, $grade);
        $this->assertTrue(abs($mark - 0.5 / 7.5) < 0.00001);
    }

    public function test_customised_timeout() {
        $q = $this->make_question('hello_python');
        $slowsquare = <<<EOT
from time import sleep
sleep(10)  # Wait 10 seconds
print("Hello Python")
EOT;
        $response = ['answer' => $slowsquare];  // Should time out.
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $q->cputimelimitsecs = 20;  // This should fix it.
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
    }
}
