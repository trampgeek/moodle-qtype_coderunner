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
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');

/**
 * Unit tests for coderunner C questions
 */
class qtype_coderunner_c_question_test extends qtype_coderunner_testcase {

    public function test_good_sqr_function() {
        $q = test_question_maker::make_question('coderunner', 'sqrC');
        $response = array('answer' => "int sqr(int n) { return n * n;}\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->allCorrect());
    }


    public function test_compile_error() {
        $q = test_question_maker::make_question('coderunner', 'sqrC');
        $response = array('answer' => "int sqr(int n) { return n * n; /* No closing brace */");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertTrue($testOutcome->hasSyntaxError());
        $this->assertEquals(0, count($testOutcome->testResults));
    }



    public function test_good_hello_world() {
        $q = test_question_maker::make_question('coderunner', 'helloProgC');
        $response = array('answer' => "#include <stdio.h>\nint main() { printf(\"Hello ENCE260\\n\");return 0;}\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        $this->assertEquals(1, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->allCorrect());
    }


    public function test_bad_hello_world() {
        $q = test_question_maker::make_question('coderunner', 'helloProgC');
        $response = array('answer' => "#include <stdio.h>\nint main() { printf(\"Hello ENCE260!\\n\");return 0;}\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        $this->assertEquals(1, count($testOutcome->testResults));
        $this->assertFalse($testOutcome->allCorrect());
    }


    public function test_copy_stdinC() {
        $q = test_question_maker::make_question('coderunner', 'copyStdinC');
        $response = array('answer' => "#include <stdio.h>\nint main() { char c;\nwhile((c = getchar()) != EOF) {\n putchar(c);\n}\nreturn 0;}\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertFalse($testOutcome->hasSyntaxError());
        $this->assertEquals(3, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->allCorrect());
    }


    public function test_C_func_with_side_effects() {
        // This used to test the c_function_side_effects question type, but
        // that's now defunct. The test is still vaguely useful, though, as
        // it tests that what looks like a global array in each test case
        // is actually local to each test within the same combined function.
        $q = test_question_maker::make_question('coderunner', 'strToUpper');
        $response = array('answer' =>
"void strToUpper(char s[]) {
    int i = 0;
    while (s[i]) {
       s[i] = toupper(s[i]);
       i++;
    }
}
");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        $this->assertEquals(2, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->allCorrect());
    }



    public function test_str_to_upper_full_main() {
        // This version has a full main function in the test
        $q = test_question_maker::make_question('coderunner', 'strToUpperFullMain');
        $response = array('answer' =>
"void strToUpper(char s[]) {
    int i = 0;
    while (s[i]) {
       s[i] = toupper(s[i]);
       i++;
    }
}
");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        $this->assertEquals(2, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->allCorrect());
    }



    public function test_runtime_error() {
        $q = test_question_maker::make_question('coderunner', 'helloProgC');
        $response = array('answer' => "#include <stdio.h>\n#include <stdlib.h>\nint main() { char* p = NULL; *p = 10; return 0; }\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        $this->assertEquals(1, count($testOutcome->testResults));
        $this->assertFalse($testOutcome->allCorrect());
        $this->assertEquals("***Runtime error*** (signal 11)\n", $testOutcome->testResults[0]->got);
    }


    public function test_timelimit_exceeded() {
        $q = test_question_maker::make_question('coderunner', 'helloProgC');
        $response = array('answer' => "#include <stdio.h>\nint main() { while(1) {};return 0;}\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        $this->assertEquals(1, count($testOutcome->testResults));
        $this->assertFalse($testOutcome->allCorrect());
        $this->assertEquals("***Time limit exceeded***\n", $testOutcome->testResults[0]->got);
    }




    public function test_missing_semicolon() {
        // Check that a missing semicolon in a simple printf test is reinsterted
        // Check grading of a "write-a-function" question with multiple
        // test cases and a correct solution
        $q = test_question_maker::make_question('coderunner', 'sqrNoSemicolons');
        $response = array('answer' => "int sqr(int n) { return n * n;}\n");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testOutcome->testResults));
        $this->assertTrue($testOutcome->allCorrect());
    }



    public function test_illegal_function_call() {
        // NOTE: The null sandbox doesn't pass this test.
        // I'm not sure why the nproc=1 setting in runguard doesn't prevent
        // the forking. Even if it did it wouldn't detect the illegal
        // function call, but it should at least abort the program, shouldn't it?
        // TODO: find out what's happening here.
        $q = test_question_maker::make_question('coderunner', 'sqrC');
        $response = array('answer' =>
"#include <linux/unistd.h>
#include <unistd.h>
int sqr(int n) {
    if (n == 0) return 0;
    else {
        int i = 0;
        for (i = 0; i < 2000; i++)
            fork();
        return 0;
    }
}");
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(2, count($testOutcome->testResults));
        $this->assertTrue(strpos($testOutcome->testResults[1]->got, "***Illegal function call***") !== FALSE);
    }
}

