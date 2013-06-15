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
 * Unit tests for coderunner C questions.
 * @group qtype_coderunner
 * Assumed to be run after python questions have been tested, so focuses
 * only on C-specific aspects.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

/**
 * Unit tests for coderunner Java questions
 */
class qtype_coderunner_java_question_test extends basic_testcase {
    protected function setUp() {
        $this->qtype = new qtype_coderunner_question();
    }


    protected function tearDown() {
        $this->qtype = null;
    }


    public function test_good_sqr_function() {
        $q = test_question_maker::make_question('coderunner', 'sqrjava');
        $response = array('answer' => "int sqr(int n) { return n * n; }\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals($mark, 1);
        $this->assertEquals($grade, question_state::$gradedright);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(count($testOutcome->testResults), 4);
        $this->assertTrue($testOutcome->allCorrect());
    }


    public function test_bad_sqr_function() {
        $q = test_question_maker::make_question('coderunner', 'sqrjava');
        $response = array('answer' => "int sqr(int n) { return n; }\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals($mark, 0);
        $this->assertEquals($grade, question_state::$gradedwrong);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(count($testOutcome->testResults), 4);
        $this->assertFalse($testOutcome->allCorrect());
    }


    public function test_bad_syntax() {
        $q = test_question_maker::make_question('coderunner', 'sqrjava');
        $response = array('answer' => "int sqr(n) { return n * n; }\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals($mark, 0);
        $this->assertEquals($grade, question_state::$gradedwrong);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertTrue($testOutcome->hasSyntaxError());
        $this->assertEquals(count($testOutcome->testResults), 0);
    }


    public function test_class_type() {
        $q = test_question_maker::make_question('coderunner', 'nameclass');
        $response = array('answer' =>
"class Name {
  String first;
  String last;
  public Name(String f, String l) {
    first = f;
    last = l;
  }
  public String toString() {
    return first + ' ' + last;
  }
}
");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals($mark, 1);
        $this->assertEquals($grade, question_state::$gradedright);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(count($testOutcome->testResults), 2);
        $this->assertTrue($testOutcome->allCorrect());
    }

    public function test_program_type() {
        $q = test_question_maker::make_question('coderunner', 'printsquares');
        $response = array('answer' =>
"import java.util.Scanner;
 public class PrintNames {
     public static void main(String[] args) {
        Scanner in = new Scanner(System.in);
        int upTo = in.nextInt();
        String separator = \"\";
        for (int i = 1; i <= upTo; i++) {
           System.out.print(separator +  (new Integer(i * i).toString()));
           separator = \" \";
        }
   }
}
");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals($mark, 1);
        $this->assertEquals($grade, question_state::$gradedright);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(count($testOutcome->testResults), 2);
        $this->assertTrue($testOutcome->allCorrect());
    }
}

