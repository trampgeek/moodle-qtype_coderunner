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
 * Unit tests for coderunner octave questions.
 * @group qtype_coderunner
 * Assumed to be run after python questions have been tested, so focuses
 * only on octave-specific aspects.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2014-2022 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

/**
 * Unit tests for coderunner octave questions.
 * @coversNothing
 */
class octave_question_test extends \qtype_coderunner_testcase {
    protected function setUp(): void {
        parent::setUp();

        // Each test will be skipped if octave not available on jobe server.
        $this->check_language_available('octave');
    }


    public function test_good_sqr_function() {
        $q = $this->make_question('sqroctave');
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
        $q = $this->make_question('sqroctave');
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
        $q = $this->make_question('sqroctave');
        $response = ['answer' => "function sq = sqr(n)\n  sq = n:\nend\n"];
        [$mark, $grade, $cache] = $q->grade_response($response);
        $this->assertEquals(0, $mark);
        $this->assertEquals(\question_state::$gradedwrong, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertEquals(1, count($testoutcome->testresults));
        $this->assertTrue(strpos($testoutcome->testresults[0]->got, "Abnormal termination") !== false
                || strpos($testoutcome->testresults[0]->got, "syntax error") !== false);
    }

    public function test_student_answer_macro() {
        $q = $this->make_question('teststudentanswermacrooctave');
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

        [$mark, $grade, ] = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(\question_state::$gradedright, $grade);
    }
}
