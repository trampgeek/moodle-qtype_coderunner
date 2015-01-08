<?php

/**
 * Unit tests for the coderunner question customistation capability
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');
require_once($CFG->dirroot . '/question/type/coderunner/Twig/Autoloader.php');

class qtype_coderunner_customise_test extends qtype_coderunner_testcase {

    public function test_grade_response_right() {
        $q = $this->make_question('sqrCustomised');
        $response = array('answer' => 'def sqr(n): return times(n, n)');
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testoutcome->has_syntax_error());
        foreach ($testoutcome->testresults as $tr) {
            $this->assertTrue($tr->iscorrect);
        }
    }
}
?>
