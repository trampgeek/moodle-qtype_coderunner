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
        set_config('runguardsandbox_enabled', 0, 'qtype_coderunner');
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

}
