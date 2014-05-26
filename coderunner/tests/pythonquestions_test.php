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
 * Unit tests for the coderunner question definition class.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');
require_once($CFG->dirroot . '/local/Twig/Autoloader.php');


/**
 * Unit tests for the coderunner question definition class.
 */
class qtype_coderunner_pythonquestions_test extends qtype_coderunner_testcase {
    protected function setUp() {
        parent::setUp();
        $this->goodcode = "def sqr(n): return n * n";
    }


    public function test_get_question_summary() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $this->assertEquals('Write a function sqr(n) that returns n squared',
                $q->get_question_summary());
    }


    public function test_summarise_response() {
        $s = $this->goodcode;
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $this->assertEquals($s, $q->summarise_response(array('answer' => $s)));
    }


    public function test_grade_response_right() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $response = array('answer' => $this->goodcode);
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        foreach ($testOutcome->testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }


    public function test_grade_response_wrong_ans() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $code = "def sqr(x): return x * x * x / abs(x)";
        $response = array('answer' => $code);
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
    }


    public function test_grade_syntax_error() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $code = "def sqr(x): return x  x";
        $response = array('answer' => $code);
        list($mark, $grade, $cache) =  $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertTrue($testOutcome->hasSyntaxError());
        $this->assertEquals(0, count($testOutcome->testResults));
    }


    public function test_grade_runtime_error() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $code = "def sqr(x): return x * y";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testOutcome->testResults));
        $this->assertFalse($testOutcome->testResults[0]->isCorrect);
    }


    public function test_student_answer_variable() {
        $q = test_question_maker::make_question('coderunner', 'studentanswervar');
        $code = "\"\"\"Line1\n\"Line2\"\n'Line3'\nLine4\n\"\"\"";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
    }


    public function test_illegal_open_error() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $code = "def sqr(x):\n    f = open('/tmp/xxx');\n    return x * x";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testOutcome->testResults));
        $this->assertFalse($testOutcome->testResults[0]->isCorrect);
    }


    public function test_grade_delayed_runtime_error() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $code = "def sqr(x):\n  if x != 11:\n    return x * x\n  else:\n    return y";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(3, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->testResults[0]->isCorrect);
        $this->assertFalse($testOutcome->testResults[2]->isCorrect);
    }


    public function test_triple_quotes() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $code = <<<EOCODE
def sqr(x):
    """This is a function
       that squares its parameter"""
    return x * x
EOCODE;
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(5, count($testOutcome->testResults));
        foreach ($testOutcome->testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }


    public function test_helloFunc() {
        // Check a question type with a function that prints output
        $q = test_question_maker::make_question('coderunner', 'helloFunc');
        $code = "def sayHello(name):\n  print('Hello ' + name)";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testOutcome->testResults));
        foreach ($testOutcome->testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }


    public function test_copyStdin() {
        // Check a question that reads stdin and writes to stdout
        $q = test_question_maker::make_question('coderunner', 'copyStdin');
        $code = <<<EOCODE
def copyStdin(n):
  for i in range(n):
    line = input()
    print(line)
EOCODE;
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(5, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->testResults[0]->isCorrect);
        $this->assertTrue($testOutcome->testResults[1]->isCorrect);
        $this->assertTrue($testOutcome->testResults[2]->isCorrect);
        $this->assertTrue($testOutcome->testResults[3]->isCorrect);
        $this->assertFalse($testOutcome->testResults[4]->isCorrect);
        $this->assertTrue(strpos($testOutcome->testResults[4]->got, 'EOFError') !== FALSE);
     }


     public function test_timeout() {
         // Check a question that loops forever. Should cause sandbox timeout
        $q = test_question_maker::make_question('coderunner', 'timeout');
        $code = "def timeout():\n  while (1):\n    pass";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testOutcome->testResults));
        $this->assertFalse($testOutcome->testResults[0]->isCorrect);
        $this->assertTrue(strpos($testOutcome->testResults[0]->got, 'Time limit exceeded') !== FALSE);
     }


     public function test_exceptions() {
         // Check a function that conditionally throws exceptions
        $q = test_question_maker::make_question('coderunner', 'exceptions');
        $code = "def checkOdd(n):\n  if n & 1:\n    raise ValueError()";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(2, count($testOutcome->testResults));
        $this->assertEquals("Exception\n", $testOutcome->testResults[0]->got);
        $this->assertEquals("Yes\nYes\nNo\nNo\nYes\nNo\n",
                $testOutcome->testResults[1]->got);
     }

     public function test_partial_mark_question() {
         // Test a question that isn't of the usual all_or_nothing variety
        $q = test_question_maker::make_question('coderunner', 'sqrPartMarks');
        $code = "def sqr(n):\n  return -17.995";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedpartial, $grade);
        $this->assertEquals(0, $mark);

        $code = "def sqr(n):\n  return 0";  // Passes first test only
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedpartial, $grade);
        $this->assertTrue(abs($mark - 0.5/7.5) < 0.00001);

        $code = "def sqr(n):\n  return n * n if n <= 0 else -17.995";  // Passes first test and last two only
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedpartial, $grade);
        $this->assertTrue(abs($mark - 5.0/7.5) < 0.00001);

        $code = "def sqr(n):\n    return n * n if n <= 0 else 1 / 0";  // Passes first test then aborts
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedpartial, $grade);
        $this->assertTrue(abs($mark - 0.5/7.5) < 0.00001);
     }


     public function test_pylint_func_good() {
        // Test that a pylint-func question with a good pylint-compatible
        // submission passes.
        $q = test_question_maker::make_question('coderunner', 'sqr_pylint');
        $q->options['coderunner_type'] = 'python3_pylint_func';
        $code = <<<EOCODE
import posix  # Checks if pylint bug patched
def sqr(n):
    '''This is a comment'''
    if posix.F_OK == 0:
        return n * n
    else:
        return 0
                
EOCODE;
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedright, $grade);
     }


     public function test_pylint_func_bad() {
        // Test that a pylint-func question with a bad (pylint-incompatible)
        // submission fails.
        $q = test_question_maker::make_question('coderunner', 'sqr_pylint');
        $q->options['coderunner_type'] = 'python3_pylint_func';
        // Code lacks a docstring
        $code = <<<EOCODE
def sqr(n):
  return n * n
                
EOCODE;
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedwrong, $grade);
     }




     public function test_customised_timeout() {
        $q = test_question_maker::make_question('coderunner', 'helloPython');
        $slowSquare = <<<EOT
from time import clock
t = clock()
while clock() < t + 10: pass  # Wait 10 seconds
print("Hello Python")
EOT;
        $response = array('answer' => $slowSquare);  // Should time out
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $q->cputimelimitsecs = 20;  // This should fix it
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
    }

}

