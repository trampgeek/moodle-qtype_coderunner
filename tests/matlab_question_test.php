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
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

/**
 * Unit tests for coderunner matlab questions
 * @coversNothing
 */
class matlab_question_test extends \qtype_coderunner_testcase {
    protected function setUp(): void {
        parent::setUp();

        // Each test will be skipped if matlab not available on jobe server.
        $this->check_language_available('matlab');
    }

    public function test_good_sqr_function() {
        $this->check_language_available('matlab');
        $q = $this->make_question('sqrmatlab');
        $response = ['answer' => "function sq = sqr(n)\n  sq = n * n;\nend\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testoutcome->testresults));
        $this->assertTrue($testoutcome->all_correct());
    }


    public function test_bad_sqr_function() {
        $this->check_language_available('matlab');
        $q = $this->make_question('sqrmatlab');
        $response = ['answer' => "function sq = sqr(n)\n  sq = n;\nend\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(4, count($testoutcome->testresults));
        $this->assertFalse($testoutcome->all_correct());
    }


    public function test_bad_syntax() {
        $this->check_language_available('matlab');
        $q = $this->make_question('sqrmatlab');
        $response = ['answer' => "function sq = sqr(n)\n  sq = n;\nendd\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testoutcome->testresults));
        $this->assertTrue(strpos($testoutcome->testresults[0]->got, "Abnormal termination") !== false);
    }

    public function test_student_answer_macro() {
        $this->check_language_available('matlab');
        $q = $this->make_question('teststudentanswermacro');
        $response = ['answer' => <<<EOT
function mytest()
    s1 = '"Hi!" he said'; % a comment
    s2 = '''Hi!'' he said';
    disp(s1);
    disp(s2);
end
EOT
,
        ];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
    }
}
