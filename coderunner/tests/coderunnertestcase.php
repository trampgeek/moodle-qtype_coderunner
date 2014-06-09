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
        parent::setUp();
        set_config('runguardsandbox_enabled', 1, 'qtype_coderunner');
        set_config('liusandbox_enabled', 0, 'qtype_coderunner');
        set_config('ideonesandbox_enabled', 0, 'qtype_coderunner');
        set_config('jobesandbox_enabled', 1, 'qtype_coderunner');
        set_config('jobe_host', 'localhost', 'qtype_coderunner');
        set_config('ideone_user', 'coderunner', 'qtype_coderunner');
        set_config('ideone_password', 'moodlequizzes', 'qtype_coderunner');
        set_config('ideone_password', 'moodlequizzes', 'qtype_coderunner');
        $this->resetAfterTest();
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
    
    
    // Check if the particular help question can be loaded correctly,
    // which is essentially a test in the underlying question prototype exists.
    public function check_question_available($question) {
        try {
            test_question_maker::make_question('coderunner', $question);
        } catch (MissingCoderunnerQuestionType $ex) {
            $this->markTestSkipped("$question question unavailable: test skipped");
        }
    }
    
    
    // Check if a particular sandbox is enabled. Skip test if not.
    protected function check_sandbox_enabled($sandbox) {
        if (!get_config('qtype_coderunner', $sandbox . '_enabled')) {
            $this->markTestSkipped("Sandbox $sandbox unavailable: test skipped");
        }
    }

}
