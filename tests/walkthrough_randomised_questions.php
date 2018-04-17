<?php
/**
 * Further walkthrough tests for the CodeRunner plugin, testing the
 * randomisation mechanisem.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2018 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

class qtype_coderunner_walkthrough_randomisation_test extends qbehaviour_walkthrough_test_base {

    protected function setUp() {
        global $CFG;
        parent::setUp();
        qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    // A randomised sqr that is named either sqr or mysqr with a single testcase
    // that prints the sqr of either 111 or 112. Checks Twig expansion of
    // questiontext test code, extra, and expected for all four possible
    // combinations.
    public function test_randomised_sqr() {

        $iters = 0;
        $tests = array(
            array('searchfor' => 'print(mysqr(111))', 'answer' => "def mysqr(n): return n * n"),
            array('searchfor' => 'print(mysqr(112))', 'answer' => "def mysqr(n): return n * n"),
            array('searchfor' => 'print(sqr(111))', 'answer' => "def sqr(n): return n * n"),
            array('searchfor' => 'print(sqr(112))', 'answer' => "def sqr(n): return n * n"),
        );

        foreach ($tests as $test) {
            while ($iters < 50) {
                // Keep trying the question until we get desired search string
                $q = test_question_maker::make_question('coderunner', 'sqr');
                $this->add_fields($q);
                $this->start_attempt_at_question($q, 'adaptive', 1, 1);
                $this->render();
                if (strpos($this->currentoutput, $test['searchfor']) !== false) {
                    break;
                }
                $iters += 1;
            }

            $this->assertTrue($iters < 50);
            $this->process_submission(array('-submit' => 1, 'answer' => $test['answer']));
            $this->check_current_mark(1.0);
        }
    }


    private function add_fields($q) {
        $q->templateparams = '{"func": "{{ random(["sqr", "mysqr"]) }}", "n": {{ 111 + random(1) }} }';
        $q->hoisttemplateparams = 1;
        $q->twigall = 1;
        $q->questiontext = 'Write a function {{ func }}';
        $q->template = "{{ STUDENT_ANSWER }}\n{{ TEST.testcode }}\n{{ TEST.extra }}\n";
        $q->iscombinatortemplate = false;
        $q->testcases = array(
            (object) array('type'     => 0,
                          'testcode'  => 'print({{ func }}({{ n }}))',
                          'extra'     => 'print({{ func }}({{ n }}))',
                          'expected'  => "{{ n * n }}\n{{ n * n }}",
                          'stdin'     => '',
                          'useasexample' => 1,
                          'display'   => 'SHOW',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0)
        );

    }
}