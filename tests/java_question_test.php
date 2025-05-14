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

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');

/**
 * Unit tests for coderunner Java questions
 * @coversNothing
 */
class java_question_test extends \qtype_coderunner_testcase {
    protected function setUp(): void {
        parent::setUp();

        // Each test will be skipped if java not available on jobe server.
        $this->check_language_available('java');
    }

    public function test_good_sqr_function() {
        $q = $this->make_question('sqrjava');
        $response = ['answer' => "int sqr(int n) { return n * n; }\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }


    public function test_bad_sqr_function() {
        $q = $this->make_question('sqrjava');
        $response = ['answer' => "int sqr(int n) { return n; }\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testoutcome->testresults));
        $this->assertFalse($testoutcome->all_correct());
    }


    public function test_bad_syntax() {
        $q = $this->make_question('sqrjava');
        $response = ['answer' => "int sqr(n) { return n * n; }\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertTrue($testoutcome->has_syntax_error());
        $this->assertEquals(0, count($testoutcome->testresults));
    }


    public function test_class_type() {
        $q = $this->make_question('nameclass');
        $response = ['answer' => <<<EOCODE
class Name {
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
EOCODE
,
        ];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(2, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }

    public function test_program_type() {
        $q = $this->make_question('printsquares');
        $response = ['answer' => <<<EOCODE
import java.util.Scanner;
public class PrintNames {
    public static void main(String[] args) {
        Scanner in = new Scanner(System.in);
        int upTo = in.nextInt();
        String separator = "";
        for (int i = 1; i <= upTo; i++) {
           System.out.print(separator +  (Integer.valueOf(i * i).toString()));
           separator = " ";
        }
    }
}
EOCODE
,
        ];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(2, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }

    public function test_program_type_alternate_syntax() {
        $q = $this->make_question('printsquares');
        $response = ['answer' => <<<EOCODE
import java.util.Scanner;
public class PrintNames {
    static public void main(String[] args) {
        Scanner in = new Scanner(System.in);
        int upTo = in.nextInt();
        String separator = "";
        for (int i = 1; i <= upTo; i++) {
           System.out.print(separator +  (Integer.valueOf(i * i).toString()));
           separator = " ";
        }
    }
}
EOCODE
,
        ];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(2, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }


    // Checks if the Java Twig escape filter works.
    public function test_java_escape() {
        $q = $this->make_question('printstr');
        $response = ['answer' => ''];
        [$mark, , ] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
    }
}
