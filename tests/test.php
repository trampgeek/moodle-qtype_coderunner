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

/**
 * @coversNothing
 */
class qtype_coderunner_testcase extends advanced_testcase {
    protected $hasfailed = false; // Set to true when a test fails.

    /** @var stdClass Holds question category.*/
    protected $category;

    protected function setUp(): void {
        parent::setUp();
        self::setup_test_sandbox_configuration();
        $this->resetAfterTest(false);
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $generator->create_question_category([]);
    }

    /**
     * Set up the the test sandbox configuration defined in the files
     * tests/fixtures/test-sandbox-config-dist.php and
     * tests/fixtures/test-sandbox-config.php.
     */
    public static function setup_test_sandbox_configuration(): void {
        global $CFG, $USER;

        $localconfig = $CFG->dirroot . '/question/type/coderunner/tests/fixtures/test-sandbox-config.php';
        if (is_readable($localconfig)) {
            require($localconfig);
        } else {
            throw new coding_exception('tests/fixtures/test-sandbox-config.php must exist to define test configuration');
        }
        $USER->username  = 'tester';
        $USER->email     = 'tester@nowhere.com';
        $USER->firstname = 'Test';
        $USER->lastname  = 'User';
    }

    // Override base class method to set a flag, which can be tested
    // to conditionally skip later tests. See jobesendbox_test.
    // Name can't be made moodle-standards compliant as it's defined by phpunit.
    // $e is the exception to be thrown.
    protected function onnotsuccessfultest(Throwable $e): void {
        $this->hasfailed = true;
        throw $e;
    }

    public function test_dummy(): void {
        /* Present to avoid a warning about no testcases. */
    }

    // Check if language installed. If not, mark test skipped and don't
    // return (exception raised internally).
    protected function check_language_available($language): void {
        if (qtype_coderunner_sandbox::get_best_sandbox($language, true) === null) {
            $this->markTestSkipped(
                "$language is not installed on your server. Test skipped."
            );
        }
    }

    // Make and return a question, skipping the test if it can't be made.
    public function make_question($question) {
        try {
            $q = test_question_maker::make_question('coderunner', $question);
        } catch (qtype_coderunner_missing_question_type $ex) {
            $this->markTestSkipped("$question question unavailable: test skipped");
        }
        $q->contextid = $this->category->contextid;
        return $q;
    }

    // Check if a particular sandbox is enabled. Skip test if not.
    protected function check_sandbox_enabled($sandbox): void {
        if (!get_config('qtype_coderunner', $sandbox . '_enabled')) {
            $this->markTestSkipped("Sandbox $sandbox unavailable: test skipped");
        }
    }
}
