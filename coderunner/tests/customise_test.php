<?php

/**
 * Unit tests for the coderunner question customistation capability
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');
require_once($CFG->dirroot . '/local/Twig/Autoloader.php');
class qtype_coderunner_customise_test extends basic_testcase {

    protected function setUp() {
        $this->qtype = new qtype_coderunner_question();
    }


    protected function tearDown() {
        $this->qtype = null;
    }

    public function test_grade_response_right() {
        $q = test_question_maker::make_question('coderunner', 'sqrCustomised');
        $response = array('answer' => 'def sqr(n): return times(n, n)');
        list($mark, $grade, $cache) = $q->grade_response($response);
        $this->assertEquals($mark, 1);
        $this->assertEquals($grade, question_state::$gradedright);
        $this->assertTrue(isset($cache['_testoutcome']));
        $testOutcome = unserialize($cache['_testoutcome']);
        $this->assertFalse($testOutcome->hasSyntaxError());
        foreach ($testOutcome->testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }
}
?>
