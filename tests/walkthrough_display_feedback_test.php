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

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');


/**
 * Use a walkthrough test to validate the new (2019) display-feedback options.
 * @group qtype_coderunner
 * @coversNothing
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2019 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class walkthrough_display_feedback_test extends \qbehaviour_walkthrough_test_base {
    protected function setUp(): void {
        parent::setUp();
        \qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }


    /** Test that if adaptive mode is selected and question has
     *  displayfeedback set to 0 or 1, feedback (result table) is shown but
     *  not if display feedback is set to 2.
     */
    public function test_display_feedback_adaptive() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $this->assertEquals($q->displayfeedback, 1);
        $this->start_attempt_at_question($q, 'adaptive', 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_contains('Passed all tests');
        $q->displayfeedback = 0; // Should be the same outcome (quiz default).
        $this->start_attempt_at_question($q, 'adaptive', 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_contains('Passed all tests');
        $q->displayfeedback = 2; // But with a setting of 2, should be no result table.
        $this->start_attempt_at_question($q, 'adaptive', 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_does_not_contain('Passed all tests');
    }

    /** Test that if deferred mode is selected and question has
     *  displayfeedback set to 0 or 2, feedback (result table) is not shown but
     *  it is shown if displayfeedback is set to 1.
     *  Actually it turns out that it isn't sufficient just to set the
     *  behaviour - you have to explicitly set the displayoptions that
     *  (usually) correspond to that behaviour.
     */
    public function test_display_feedback_deferred() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->displayfeedback = 0;  // Uses quiz feedback setting.
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);
        $this->displayoptions->feedback = 0; // Seems we have to set this explicitly.
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_does_not_contain('Passed all tests');
        $q->displayfeedback = 1;
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_contains('Passed all tests');
        $q->displayfeedback = 2;
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_does_not_contain('Passed all tests');
    }
}
