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
 * This is a walkthrough test for the CodeRunner plugin
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012, 2014, 2020 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');

/**
 * Unit tests for the coderunner question type.
 * @coversNothing
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class walkthrough_test extends \qbehaviour_walkthrough_test_base {
    protected function setUp(): void {
        parent::setUp();
        \qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    public function test_adaptive() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();

        // Check the initial state.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Started', $qa->summarise_action($qa->get_last_step()));

        // Submit blank.
        $this->process_submission(['-submit' => 1, 'answer' => '']);

        // Verify.
        $this->check_current_state(\question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
            $this->get_contains_validation_error_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Submit: ', $qa->summarise_action($qa->get_last_step()));

        // Submit a wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n']);

        // Verify.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            new \question_pattern_expectation('/' .
                        preg_quote(get_string('noerrorsallowed', 'qtype_coderunner') . '/'))
        );
        $this->assertEquals('Submit: def sqr(n): return n', $qa->summarise_action($qa->get_last_step()));

        // Now get it right.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n * n']);

        // Verify.
        $this->check_current_state(\question_state::$complete);
        $this->check_current_mark(0.9);
        $this->check_current_output(
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Submit: def sqr(n): return n * n', $qa->summarise_action($qa->get_last_step()));
    }

    public function test_view_hidden_testcases_capability() {
        global $DB, $PAGE;
        $this->resetAfterTest();

        // Create a course and a teacher.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $coursecontext = \context_course::instance($course->id);
        $generator->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $PAGE->set_course($course);

        // Create the question we will test.
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

        // Submit a wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n']);

        // This is not what we are really testing, but just to make what the test is doing clear.
        $this->assertTrue(has_capability('qtype/coderunner:viewhiddentestcases', $coursecontext));

        // Verify hidden cases are visible.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->render();
        $this->assertStringContainsString('print(sqr(-6))', $this->currentoutput);

        // Change users permission and check.
        role_change_permission(
            $DB->get_field('role', 'id', ['shortname' => 'editingteacher']),
            $coursecontext,
            'qtype/coderunner:viewhiddentestcases',
            CAP_PREVENT
        );
        $this->assertFalse(has_capability('qtype/coderunner:viewhiddentestcases', $coursecontext));

        // Verify hidden cases hidden.
        $this->render();
        $this->assertStringNotContainsString('print(sqr(-6))', $this->currentoutput);
    }

    public function test_partial_marks() {
        $q = \test_question_maker::make_question('coderunner', 'sqr_part_marks');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

         // Submit a totally wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return -19']);

        // Verify.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            new \question_pattern_expectation('/' .
                        preg_quote(get_string('incorrect', 'question') . '/'))
        );

        // Submit a partially right answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n * n if n < 0 else -19']);
        $this->check_current_mark(0.54);  // 4.5/7.5 * 90%.
        $this->check_current_output(
            new \question_pattern_expectation('/' .
                        preg_quote(get_string('partiallycorrect', 'question') . '/'))
        );

        // Now get it right.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n * n']);

        // Verify.
        $this->check_current_state(\question_state::$complete);
        $this->check_current_mark(0.8); // Full marks but 20% penalty after 2 wrong submissions.
        $this->check_current_output(
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_no_hint_visible_expectation()
        );
    }


    // Check that penalty regime is displayed with both the Moodle default
    // penalty (if no explicit regime - legacy test) or with CodeRunner's regime
    // if set.
    public function test_display_of_penalty_regime() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->penaltyregime = '';
        $q->penalty = 0.18555555;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_output_contains('penalty regime: 18.6, 37.1, ... %');
                $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->penaltyregime = '0,11,33, ...';
        $q->penalty = 0.18555555;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_output_contains('penalty regime: 0,11,33, ... %');
    }


    // Test that if an runtime error occurs with a combinator template,
    // the system falls back to a per test template and that the final results
    // table includes all tests up to and including the failing test.
    public function test_behaviour_with_run_error() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $answer = "def sqr(n): return n * n if n != 11 else x * x\n";
        $this->process_submission(['-submit' => 1, 'answer' => $answer]);
        $this->check_output_contains('print(sqr(0))');
        $this->check_output_contains('print(sqr(1))');
        $this->check_output_contains('print(sqr(11))');
        $this->check_output_does_not_contain('print(sqr(-7))');
        $this->check_output_does_not_contain('print(sqr(-6))');
        $this->check_output_contains('Testing was aborted due to error');
        $this->check_current_mark(0.0);
    }


    public function test_grading_template_output() {
        $q = \test_question_maker::make_question('coderunner', 'sqrnoprint');
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
got = str({{TEST.testcode}})
expected = """{{TEST.expected|e('py')}}""".strip()
if expected == '49' and expected == got:
    print('{"fraction":"1.0","got":"Tiddlypom"}')
elif expected == '36' and expected == got:
    print('{"fraction":"0.5"}')  # Broken grader here
elif expected == got:
    print('{"fraction":"1","expected":"Twiddlydee"}')
else:
    print('{"fraction":"0","expected":"Twiddlydee"}')
EOTEMPLATE;
        $q->iscombinatortemplate = false;
        $q->allornothing = false;
        $q->grader = 'TemplateGrader';
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

         // Submit a totally wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return -19']);

        // Verify.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            new \question_pattern_expectation('/' .
                        preg_quote(get_string('incorrect', 'question') . '/'))
        );

        // Submit a right answer - because of the broken grader it should only get 0.77
        // Have to restart as the behaviour of the test system with regard to
        // per-submission penalties doesn't seem to work.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1, 'answer' => "def sqr(n): return n * n\n"]);
        $this->check_current_mark(23.0 / 31.0);
        $this->check_current_output(new \question_pattern_expectation('/Tiddlypom/'));
        $this->check_current_output(new \question_pattern_expectation('/Twiddlydee/'));
    }

    /* Test that if a template grader sets an abort attribute in the returned
     * JSON object to a True value, the test-runner stops running testcases
     * at that point.
     */
    public function test_grading_template_abort() {
        $q = \test_question_maker::make_question('coderunner', 'sqrnoprint');
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
got = {{TEST.testcode}}  # e.g. sqr(-11)
expected = got
if expected != 121:
    print('{"fraction":1.0,"got":"' + str(got) + '"}')  # Abort after this testcase
else:
    print('{"fraction":1.0,"got":"121","expected":"Twiddlydum","abort":true}')
EOTEMPLATE;
        $q->iscombinatortemplate = false;
        $q->allornothing = false;
        $q->grader = 'TemplateGrader';

        // Submit a right answer. Because the template sets abort when it
        // gets to the sqr(11) case, marks should be awarded only for the three
        // test cases (0, 1, 11). The sqr(11) case will be awarded zero marks
        // despite being given a fraction of 1.0.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1, 'answer' => "def sqr(n): return n * n\n"]);
        $this->check_current_mark(3.0 / 31.0);
        $this->check_current_output(new \question_pattern_expectation('/Twiddlydum/'));
    }

    /**
     *  Test that HTML in result table cells is appropriately sanitised
     */
    public function test_result_table_sanitising() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

        // Submit an answer with a <b> tag in it and make sure it's suitably
        // escaped so it appears in the output.
        $this->process_submission(['-submit' => 1,
            'answer' => "def sqr(n):\n    print('<b>')\n    return n * n"]);
        $this->check_output_does_not_contain('<b>');
    }

    public function test_grading_template_html_output() {
        /* Test a grading template that supplies a JSON record with a got_html
           attribute. For a per-test template this is used in the results
           table as the raw html contents of the "got" column.
         */
        $q = \test_question_maker::make_question('coderunner', 'sqrnoprint');
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
got = str({{TEST.testcode}})
expected = """{{TEST.expected|e('py')}}""".strip()
if expected == '49' and expected == got:
    print('{"fraction":"1.0","got_html":"<svg width=\'100\' height=\'200\'></svg>"}')
elif expected == '36' and expected == got:
    print('{"fraction":"0.5"}')  # Broken grader here
elif expected == got:
    print('{"fraction":"1","got_html":"<em>Tweedledum</em>"}')
else:
    print('{"fraction":"0","expected_html": "<h2>Header</h2>", "got_html":"<script>document.write(\'YeeHa\')</script>"}')
EOTEMPLATE;
        $q->iscombinatortemplate = false;
        $q->allornothing = false;
        $q->grader = 'TemplateGrader';
        $q->resultcolumns = '[["Test", "testcode"], ["Expected", "expected_html", "%h"], ["Got", "got_html", "%h"]]';

        // Submit an answer that's right for all except one test case.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => "def sqr(n): return -1 if n == 1 else n * n \n"]);
        $this->check_current_mark(21.0 / 31.0);
        $this->check_current_output(new \question_pattern_expectation("|<svg width=.100. height=.200.></svg>|"));
        $this->check_current_output(new \question_pattern_expectation('/YeeHa/'));
        $this->check_current_output(new \question_pattern_expectation('|<h2>Header</h2>|'));
    }


    // Check that if Template Debugging is enabled, the source code appears.
    public function test_template_debugging() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->showsource = 1;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_contains('Debug: source code from all test runs');
        $this->check_output_contains('Run 1');
        $this->check_output_contains('SEPARATOR = &quot;#&lt;ab@17943918#@&gt;#&quot;');
    }

    // Check hidecheck option works.
    public function test_hide_check() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_output_contains('Check');
        $q->hidecheck = 1;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_output_does_not_contain('Check');
    }

    // Check that a question with an answer preload is not gradable if answer not changed.
    public function test_preload_not_graded() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->answerpreload = 'def sqr(n):';
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_output_contains('def sqr(n):');
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n):']);
        $this->check_current_state(\question_state::$invalid);
        $this->check_current_mark(null);
    }

    public function test_stop_button_always() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->giveupallowed = constants::GIVEUP_ALWAYS;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();

        // Check the initial state.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_contains_stop_button_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Started', $qa->summarise_action($qa->get_last_step()));

        // Submit blank.
        $this->process_submission(['-submit' => 1, 'answer' => '']);

        // Verify.
        $this->check_current_state(\question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
            $this->get_contains_validation_error_expectation(),
            $this->get_contains_stop_button_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Submit: ', $qa->summarise_action($qa->get_last_step()));

        // Submit a wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n']);

        // Verify.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            new \question_pattern_expectation('/' .
                        preg_quote(get_string('noerrorsallowed', 'qtype_coderunner') . '/')),
            $this->get_contains_stop_button_expectation()
        );
        $this->assertEquals('Submit: def sqr(n): return n', $qa->summarise_action($qa->get_last_step()));

        // Now get it right.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n * n']);

        // Verify.
        $this->check_current_state(\question_state::$complete);
        $this->check_current_mark(0.9);
        $this->check_current_output(
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_contains_stop_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Submit: def sqr(n): return n * n', $qa->summarise_action($qa->get_last_step()));

        // Now click the Stop button.
        $this->process_submission(['-finish' => 1, 'answer' => 'def sqr(n): return n * n']);
        $this->check_current_state(\question_state::$gradedright);
        $this->check_current_mark(0.9);
        $this->check_current_output(
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_no_hint_visible_expectation(),
            $this->get_contains_general_feedback_expectation($q)
        );
        $this->assertEquals(
            'Attempt finished submitting: def sqr(n): return n * n',
            $qa->summarise_action($qa->get_last_step())
        );
    }

    public function test_stop_button_always_never_answered() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->giveupallowed = constants::GIVEUP_ALWAYS;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();

        // Check the initial state.
        $this->check_current_state(\question_state::$todo);

        // Click the Stop button.
        $this->process_submission(['-finish' => 1, 'answer' => '']);
        $this->check_current_state(\question_state::$gaveup);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_no_hint_visible_expectation(),
            $this->get_contains_general_feedback_expectation($q)
        );
        $this->assertEquals('Attempt finished submitting: ', $qa->summarise_action($qa->get_last_step()));

        // Also check what happens in Quiz deferred feedback mode, when all the quiz display
        // options are false, but the question is set to override that.
        $q->displayfeedback = constants::FEEDBACK_SHOW;
        $this->displayoptions->feedback = false;
        $this->displayoptions->generalfeedback = false;
        $this->check_current_output(
            $this->get_contains_general_feedback_expectation($q)
        );
    }

    public function test_stop_button_after_max() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->giveupallowed = constants::GIVEUP_AFTER_MAX_MARKS;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();

        // Check the initial state.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Started', $qa->summarise_action($qa->get_last_step()));

        // Submit blank.
        $this->process_submission(['-submit' => 1, 'answer' => '']);

        // Verify.
        $this->check_current_state(\question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
            $this->get_contains_marked_out_of_summary(),
            $this->get_contains_submit_button_expectation(true),
            $this->get_does_not_contain_feedback_expectation(),
            $this->get_contains_validation_error_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Submit: ', $qa->summarise_action($qa->get_last_step()));

        // Submit a wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n']);

        // Verify.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            new \question_pattern_expectation('/' .
                        preg_quote(get_string('noerrorsallowed', 'qtype_coderunner') . '/')),
            $this->get_does_not_contain_stop_button_expectation()
        );
        $this->assertEquals('Submit: def sqr(n): return n', $qa->summarise_action($qa->get_last_step()));

        // Now get it right.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n * n']);

        // Verify.
        $this->check_current_state(\question_state::$complete);
        $this->check_current_mark(0.9);
        $this->check_current_output(
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_contains_stop_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Submit: def sqr(n): return n * n', $qa->summarise_action($qa->get_last_step()));

        // Submit something invalid again..
        $this->process_submission(['-submit' => 1, 'answer' => 'wrong']);

        // Verify.
        $this->check_current_state(\question_state::$complete);
        $this->check_current_mark(0.9);
        $this->check_current_output(
            $this->get_contains_mark_summary(0.9),
            $this->get_contains_submit_button_expectation(true),
            $this->get_contains_stop_button_expectation(),
            $this->get_does_not_contain_try_again_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );
        $this->assertEquals('Submit: wrong', $qa->summarise_action($qa->get_last_step()));

        // Now click the Stop button.
        $this->process_submission(['-finish' => 1, 'answer' => 'wrong']);
        $this->check_current_state(\question_state::$gradedwrong);
        $this->check_current_mark(0.9);
        $this->check_current_output(
            $this->get_contains_incorrect_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_no_hint_visible_expectation(),
            $this->get_contains_general_feedback_expectation($q)
        );
        $this->assertEquals(
            'Attempt finished submitting: wrong',
            $qa->summarise_action($qa->get_last_step())
        );

        // Also check what happens in Quiz deferred feedback mode, when all the quiz display
        // options are false, but the question is set to override that.
        $q->displayfeedback = constants::FEEDBACK_SHOW;
        $this->displayoptions->feedback = false;
        $this->displayoptions->generalfeedback = false;
        $this->check_current_output(
            $this->get_contains_general_feedback_expectation($q)
        );
    }

    public function test_stop_button_after_max_repeatedly_wrong() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->penaltyregime = '50, 100';
        $q->giveupallowed = constants::GIVEUP_AFTER_MAX_MARKS;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();

        // Check the initial state.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(null);

        // Submit a wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n']);

        // Verify.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_does_not_contain_stop_button_expectation()
        );

        // Submit a different wrong answer.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return 2 * n']);

        // Verify - now not possible to improve.
        $this->check_current_state(\question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_correct_expectation(),
            $this->get_does_not_contain_validation_error_expectation(),
            $this->get_contains_stop_button_expectation(),
            $this->get_no_hint_visible_expectation()
        );

        // Now click the Stop button.
        $this->process_submission(['-finish' => 1, 'answer' => 'def sqr(n): return 2 * n']);
        $this->check_current_state(\question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->check_current_output(
            $this->get_contains_incorrect_expectation(),
            $this->get_does_not_contain_stop_button_expectation(),
            $this->get_no_hint_visible_expectation(),
            $this->get_contains_general_feedback_expectation($q)
        );
        $this->assertEquals(
            'Attempt finished submitting: def sqr(n): return 2 * n',
            $qa->summarise_action($qa->get_last_step())
        );
    }

    protected function get_contains_stop_button_expectation($enabled = null): \question_contains_tag_with_attributes {
        $expectedattributes = [
            'type' => 'submit',
            'name' => $this->quba->get_field_prefix($this->slot) . '-finish',
        ];
        $forbiddenattributes = [];
        if ($enabled === true) {
            $forbiddenattributes['disabled'] = 'disabled';
        } else if ($enabled === false) {
            $expectedattributes['disabled'] = 'disabled';
        }
        return new \question_contains_tag_with_attributes('input', $expectedattributes, $forbiddenattributes);
    }

    protected function get_does_not_contain_stop_button_expectation(): \question_no_pattern_expectation {
        return new \question_no_pattern_expectation('/name="' .
            $this->quba->get_field_prefix($this->slot) . '-finish"/');
    }
}
