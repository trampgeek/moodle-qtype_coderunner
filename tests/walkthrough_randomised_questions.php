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
    // questiontext test code, extra, and expected.
    public function test_randomised_sqr() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->templateparams = '{"func": {"@randompick": ["sqr", "mysqr"]}, "n": {"@randomint": [111, 112]}}';
        $q->questiontext = 'Write a function {{ QUESTION.parameters.func }}';
        $q->template = '{{ STUDENT_ANSWER }}\n{{ TEST.testcode }}\n {{ TEST.extra }}';
        $q->iscombinatortemplate = false;
        $q->testcases = array(
            (object) array('type'     => 0,
                          'testcode'  => 'print("{{ QUESTION.parameters.name }}")',
                          'extra'     => 'print({{ QUESTION.parameters.name }}( {{ QUESTION.parameters.n }} ))',
                          'expected'  => '{{ QUESTION.parameters.name }}\n{{ QUESTION.parameters.n * QUESTION.parameters.n }}',
                          'stdin'     => '',
                          'useasexample' => 1,
                          'display'   => 'SHOW',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0)
        );
        $foundsqr = $foundmysqr = false;
        $iters = 0;
        while ($iters < 30 && !$foundsqr) {
            $this->start_attempt_at_question($q, 'adaptive', 1, 1);
            $iters += 1;
        }
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

        // TO BE CONTINUED
    }
}