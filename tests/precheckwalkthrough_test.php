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
 * Walkthrough testing of the precheck capability of the CodeRunner plugin.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2016 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

use qtype_coderunner\constants;

/**
 * @coversNothing
 */
class precheckwalkthrough_test extends \qbehaviour_walkthrough_test_base {
    protected function setUp(): void {
        parent::setUp();
        \qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    protected function make_precheck_question() {
                $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->testcases = [
            (object) ['testtype'     => 2, // Both.
                          'testcode'  => 'print(sqr(-11))',
                          'expected'  => '121',
                          'stdin'     => '',
                          'useasexample' => 1,
                          'display'   => 'SHOW',
                          'extra'     => '',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0],
            (object) ['testtype'     => 2, // Both.
                          'testcode'  => 'print(sqr(12))',
                          'expected'  => '144',
                          'stdin'     => '',
                          'useasexample' => 1,
                          'display'   => 'SHOW',
                          'extra'     => '',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0],
            (object) ['testtype'     => 0, // Normal (i.e. not precheck).
                          'testcode'  => 'print(sqr(-7))',
                          'expected'  => '49',
                          'stdin'     => '',
                          'useasexample' => 0,
                          'display'   => 'SHOW',
                          'extra'     => '',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0],
            (object) ['testtype'     => 1, // Precheck only.
                          'testcode'  => 'print(sqr(-5))',
                          'expected'  => '25',
                          'stdin'     => '',
                          'useasexample' => 0,
                          'display'   => 'SHOW',
                          'extra'     => '',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0],
            ];
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
{{ TEST.testcode }}
EOTEMPLATE;
        $q->iscombinatortemplate = false;
        $q->precheck = constants::PRECHECK_EXAMPLES;
        $q->penaltyregime = "20, 40, ...";
        return $q;
    }

    // Test that a precheck run with precheck set to 2 (PRECHECK_EXAMPLES)
    // runs just the use-as-example tests, and that it's marked wrong when
    // given wrong answer, right when given right answers.
    public function test_precheck_examples() {
        $q = $this->make_precheck_question();
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();
        $this->assertEquals('Not complete', $qa->get_state_string(true));

        // Precheck with a wrong answer.
        $this->process_submission(['-precheck' => 1, 'answer' => "def sqr(n): return n\n"]);
        $this->check_output_contains("Precheck only");
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_does_not_contain('print(sqr(-7))');
        $this->check_output_does_not_contain('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results bad precheck">');
        $this->check_output_does_not_contain('Marks for this submission');
        $this->save_quba();
        $this->check_current_mark(null);
        $this->assertEquals('Precheck results', $qa->get_state_string(true));
        $this->assertEquals("Prechecked: def sqr(n): return n\n", $qa->summarise_action($qa->get_last_step()));

        // Now re-precheck with a right answer.
        $this->process_submission(['-precheck' => 1, 'answer' => "def sqr(n): return n * n\n"]);
        $this->check_output_contains("Precheck only");
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_does_not_contain('print(sqr(-7))');
        $this->check_output_does_not_contain('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results good precheck">');
        $this->check_output_does_not_contain('Marks for this submission');
        $this->check_current_mark(null);
        $this->assertEquals('Precheck results', $qa->get_state_string(true));
        $this->save_quba();

        // Now click check with a wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => "def sqr(n): return n\n"]);
        $this->check_output_does_not_contain("Precheck only");
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_contains('print(sqr(-7))');
        $this->check_output_contains('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results bad">');
        $this->check_output_does_not_contain('Testing was aborted due to error');
        $this->assertEquals('Incorrect', $qa->get_state_string(true));
        $this->check_current_mark(0.0);

        // Lastly check with a right answer, verify that a single 20% penalty was incurred.
        $this->process_submission(['-submit' => 1, 'answer' => "def sqr(n): return n * n\n"]);
        $this->check_output_does_not_contain("Precheck only");
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_contains('print(sqr(-7))');
        $this->check_output_contains('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results good">');
        $this->check_output_does_not_contain('Testing was aborted due to error');
        $this->assertEquals('Correct', $qa->get_state_string(true));
        $this->check_current_mark(0.8);
    }


    // Test that a precheck run with precheck set to 3 (PRECHECK_SELECTED)
    // runs just the selected tests and that the final check also runs just
    // the appropriate set of tests.
    public function test_precheck_selected() {
        $q = $this->make_precheck_question();
        $q->precheck = constants::PRECHECK_SELECTED;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

        // Precheck with a wrong answer.
        $this->process_submission(['-precheck' => 1, 'answer' => "def sqr(n): return n\n"]);
        $this->check_output_contains("Precheck only");
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_does_not_contain('print(sqr(-7))');
        $this->check_output_contains('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results bad precheck">');
        $this->check_output_does_not_contain('Marks for this submission');
        $this->save_quba();
        $this->check_current_mark(null);

        // Now re-precheck with a right answer.
        $this->process_submission(['-precheck' => 1, 'answer' => "def sqr(n): return n * n\n"]);
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_does_not_contain('print(sqr(-7))');
        $this->check_output_contains('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results good precheck">');
        $this->check_output_does_not_contain('Marks for this submission');
        $this->check_current_mark(null);
        $this->save_quba();

        // Now click check with a wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => "def sqr(n): return n\n"]);
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_contains('print(sqr(-7))');
        $this->check_output_does_not_contain('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results bad">');
        $this->check_output_does_not_contain('Testing was aborted due to error.');
        $this->check_current_mark(0.0);

        // Lastly check with a right answer, verify that a single 20% penalty was incurred.
        $this->process_submission(['-submit' => 1, 'answer' => "def sqr(n): return n * n\n"]);
        $this->check_output_contains('print(sqr(-11))');
        $this->check_output_contains('print(sqr(12))');
        $this->check_output_contains('print(sqr(-7))');
        $this->check_output_does_not_contain('print(sqr(-5))');
        $this->check_output_contains('<div class="coderunner-test-results good">');
        $this->check_output_does_not_contain('Testing was aborted due to error.');
        $this->check_current_mark(0.8);
    }

    // Test that a precheck run with precheck set to 4 (PRECHECK_ALL)
    // runs all tests with the template parameter IS_PRECHECK set to true.
    // Then do a normal 'Check' run and verify that IS_PRECHECK is false.
    public function test_precheck_all() {
        $q = $this->make_precheck_question();
        $q->precheck = constants::PRECHECK_ALL;
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }} # Defines sqr2 not sqr
{% if IS_PRECHECK %}
def sqr(n): return 2 * sqr2(n)
{% else %}
def sqr(n): return sqr2(n)
{% endif %}
{{ TEST.testcode }}
EOTEMPLATE;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

        // Precheck with a right answer, but because IS_PRECHECK is true
        // all answers should have been doubled.
        $this->process_submission(['-precheck' => 1, 'answer' => "def sqr2(n): return n * n\n"]);
        $this->check_output_contains("Precheck only");
        $this->check_output_contains('242');
        $this->check_output_contains('288');
        $this->check_output_contains('98');
        $this->check_output_contains('50');

        // Now check with a right answer.
        $this->process_submission(['-submit' => 1, 'answer' => "def sqr2(n): return n * n\n"]);
        $this->check_output_contains('121');
        $this->check_output_contains('144');
        $this->check_output_contains('49');
        $this->check_output_contains('25');
        $this->check_current_mark(1.0);
        $this->save_quba();
    }
}
