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
 * Unit tests for coderunner C++ questions.
 * @group qtype_coderunner
 * Assumed to be run after python and C questions have been tested, so focuses
 * only on C++-specific aspects.
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
 * Unit tests for coderunner C++ questions
 * @coversNothing
 */
class cpp_questions_test extends \qtype_coderunner_testcase {
    protected function setUp(): void {
        parent::setUp();

        // Each test will be skipped if cpp not installed on jobe server.
        $this->check_language_available('cpp');
    }

    public function test_good_sqr_function() {
        $q = $this->make_question('sqr_cpp');
        $response = ['answer' => "int sqr(int n) { return n * n;}\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $testoutcome = unserialize($cache['_testoutcome']);

        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $this->assertEquals(4, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }


    public function test_good_hello_world() {
        $q = $this->make_question('hello_prog_cpp');
        $response = ['answer' => "#include <iostream>\nusing namespace std;\n" .
            "int main() { cout << \"Hello ENCE260\\n\";\nreturn 0;}\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testoutcome->has_syntax_error());
        $this->assertEquals(1, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }


    public function test_copy_stdin_cpp() {
        $q = $this->make_question('copy_stdin_cpp');
        $response = ['answer' => "#include <iostream>
using namespace std;
int main() {
   cout << cin.rdbuf();
   return 0;
}
"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testoutcome->has_syntax_error());
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue($testoutcome->all_correct());
    }


    public function test_runtime_error() {
        $q = $this->make_question('hello_prog_cpp');
        $response = ['answer' => "#include <iostream>\n" .
            "using namespace std;\n" .
            "int main() { char* p = NULL; *p = 10; return 0; }\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testoutcome->has_syntax_error());
        $this->assertEquals(1, count($testoutcome->testresults));
        $this->assertFalse($testoutcome->all_correct());
        $this->assertTrue(strpos($testoutcome->testresults[0]->got, '***Run error***') === 0);
    }

    public function test_cpp_strings() {
        // Trivial test that the C++ string class is being included.
        $q = $this->make_question('str_to_upper_cpp');
        $response = ['answer' => "string str_to_upper(string s) {
    string result;
    for (size_t i = 0; i != s.length(); i++) {
        result += toupper(s[i]);
    }
    return result;
}"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testoutcome->has_syntax_error());
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertEquals(2, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }
}
