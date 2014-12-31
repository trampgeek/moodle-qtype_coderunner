<?php

/**
 * Base testcase for coderunner tests.
 * A standard advanced_testcase but sets up config state in set-up.
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

class qtype_coderunner_testcase extends advanced_testcase {
    protected function setUp() {
        global $CFG;
        parent::setUp();
        require($CFG->dirroot . '/question/type/coderunner/tests/config.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $generator->create_question_category(array());
    }

    function test_dummy() {
        /* Present to avoid a warning about no testcases */
    }
    
    
    // Check if language installed. If not, mark test skipped and don't
    // return (exception raised internally).
    protected function check_language_available($language) {
        if (qtype_coderunner_question::getBestSandbox($language) === NULL) {
            $this->markTestSkipped(
                    "$language is not installed on your server. Test skipped.");
        }
    }
    
    
    // Make and return a question, skipping the test if it can't be made.
    public function make_question($question) {
        try {
            $q = test_question_maker::make_question('coderunner', $question);
        } catch (MissingCoderunnerQuestionType $ex) {
            $this->markTestSkipped("$question question unavailable: test skipped");
        }
        $q->contextid = $this->category->contextid;
        return $q;
    }
    
    
    // Check if a particular sandbox is enabled. Skip test if not.
    protected function check_sandbox_enabled($sandbox) {
        if (!get_config('qtype_coderunner', $sandbox . '_enabled')) {
            $this->markTestSkipped("Sandbox $sandbox unavailable: test skipped");
        }
    }

}
