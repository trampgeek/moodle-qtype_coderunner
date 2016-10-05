<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Further walkthrough tests for the CodeRunner plugin, testing recently
 * added features like the 'extra' field for use by the template and the
 * relabelling of output columns.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012, 2014 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');

class qtype_coderunner_walkthrough_extras_test extends qbehaviour_walkthrough_test_base {

    protected function setUp() {
        global $CFG;
        parent::setUp();
        require($CFG->dirroot . '/question/type/coderunner/tests/config.php');
    }

    public function test_extra_testcase_field() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->testcases = array(
            (object) array('testcode' => 'print("Oops")',
                          'extra'     => 'print(sqr(-11))',
                          'expected'  => '121',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark'    => 1.0,
                          'hiderestiffail'  => 0)
            );
        $q->pertesttemplate = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
{{ TEST.extra }}  # Use this instead of the normal testcode field
EOTEMPLATE;
        $q->allornothing = false;
        $q->customise = true;
        $q->enablecombinator = false;
        $q->unitpenalty = 0;

        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_current_mark(1.0);
    }

    public function test_result_column_selection() {
        // Make sure can relabel result table columns.
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->resultcolumns = '[["Blah", "testcode"], ["Thing", "expected"], ["Gottim", "got"]]';

        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_current_mark(1.0);
        $this->check_current_output( new question_pattern_expectation('/Blah/') );
        $this->check_current_output( new question_pattern_expectation('/Thing/') );
        $this->check_current_output( new question_pattern_expectation('/Gottim/') );
    }

    public function test_diff_filter() {
        // Test that we can do a diff filter call in the result-column selector.
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->resultcolumns = '[["Blah", "testcode"], ["Expected", "diff(expected, got)", "%h"],'
                . '["Got", "diff(got, expected)", "%h"]]';

        // Submit a wrong answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "def sqr(n): return n * n if n != -7 else 99\n "));
        $this->check_current_mark(0.0);
        $this->check_current_output( new question_pattern_expectation('|<del>4</del>|') );
        $this->check_current_output( new question_pattern_expectation('|<ins>9</ins>|') );
        $this->check_current_output( new question_pattern_expectation('|<del>9</del>|') );
        $this->check_current_output( new question_pattern_expectation('|<ins>4</ins>|') );
    }

    /** Make sure that if the Jobe URL is wrong we get "jobesandbox is down
     *  or misconfigured" exception.
     *
     * @expectedException coderunner_exception
     * @expectedExceptionMessageRegExp |.*jobesandbox is down or misconfigured.*|
     * @retrun void
     */
    public function test_misconfigured_jobe() {
        if (!get_config('qtype_coderunner', 'jobesandbox_enabled')) {
            $this->markTestSkipped("Sandbox $sandbox unavailable: test skipped");
        }
        set_config('jobe_host', 'localhostxxx', 'qtype_coderunner');  // Broken jobe_host url
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
    }
}

