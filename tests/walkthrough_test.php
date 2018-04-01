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
 * @copyright  2012, 2014 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');

/**
 * Unit tests for the coderunner question type.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**TODO** Much more thorough behaviour test required, e.g. of all the
 * testcase controls (useAsExample, display, hideRestIfFail).
 */

class qtype_coderunner_walkthrough_test extends qbehaviour_walkthrough_test_base {

    protected function setUp() {
        global $CFG;
        parent::setUp();
        qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    public function test_adaptive() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());
        $this->assertEquals('Started', $qa->summarise_action($qa->get_last_step()));

        // Submit blank.
        $this->process_submission(array('-submit' => 1, 'answer' => ''));

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_contains_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());
        $this->assertEquals('Submit: ', $qa->summarise_action($qa->get_last_step()));

        // Submit a wrong answer.
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                new question_pattern_expectation('/' .
                        preg_quote(get_string('noerrorsallowed', 'qtype_coderunner') . '/'))
              );
        $this->assertEquals('Submit: def sqr(n): return n', $qa->summarise_action($qa->get_last_step()));

        // Now get it right.
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n * n'));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.9);
        $this->check_current_output(
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_no_hint_visible_expectation());
        $this->assertEquals('Submit: def sqr(n): return n * n', $qa->summarise_action($qa->get_last_step()));

    }

    public function test_partial_marks() {
        $q = test_question_maker::make_question('coderunner', 'sqr_part_marks');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

         // Submit a totally wrong answer.
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return -19'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                new question_pattern_expectation('/' .
                        preg_quote(get_string('incorrect', 'question') . '/'))
              );

        // Submit a partially right answer.
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n * n if n < 0 else -19'));
        $this->check_current_mark(0.54);  // 4.5/7.5 * 90%.
        $this->check_current_output(
                new question_pattern_expectation('/' .
                        preg_quote(get_string('partiallycorrect', 'question') . '/'))
              );

        // Now get it right.
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n * n'));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.8); // Full marks but 20% penalty after 2 wrong submissions.
        $this->check_current_output(
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_no_hint_visible_expectation());

    }


    // Check that penalty regime is displayed with both the Moodle default
    // penalty (if no explicit regime - legacy test) or with CodeRunner's regime
    // if set.
    public function test_display_of_penalty_regime() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->penaltyregime = '';
        $q->penalty = 0.18555555;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_output_contains('penalty regime: 18.6, 37.1, ... %');
                $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->penaltyregime = '0,11,33, ...';
        $q->penalty = 0.18555555;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->check_output_contains('penalty regime: 0,11,33, ... %');
    }


    // Test that if an runtime error occurs with a combinator template,
    // the system falls back to a per test template and that the final results
    // table includes all tests up to and including the failing test.
    public function test_behaviour_with_run_error() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $answer = "def sqr(n): return n * n if n != 11 else x * x\n";
        $this->process_submission(array('-submit' => 1, 'answer' => $answer));
        $this->check_output_contains('print(sqr(0))');
        $this->check_output_contains('print(sqr(1))');
        $this->check_output_contains('print(sqr(11))');
        $this->check_output_does_not_contain('print(sqr(-7))');
        $this->check_output_does_not_contain('print(sqr(-6))');
        $this->check_output_contains('Testing was aborted due to error');
        $this->check_current_mark(0.0);
    }


    public function test_grading_template_output() {
        $q = test_question_maker::make_question('coderunner', 'sqrnoprint');
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
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return -19'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                new question_pattern_expectation('/' .
                        preg_quote(get_string('incorrect', 'question') . '/'))
              );

        // Submit a right answer - because of the broken grader it should only get 0.77
        // Have to restart as the behaviour of the test system with regard to
        // per-submission penalties doesn't seem to work.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_current_mark(23.0 / 31.0);
        $this->check_current_output( new question_pattern_expectation('/Tiddlypom/') );
        $this->check_current_output( new question_pattern_expectation('/Twiddlydee/') );
    }

    /* Test that if a template grader sets an abort attribute in the returned
     * JSON object to a True value, the test-runner stops running testcases
     * at that point.
     */
    public function test_grading_template_abort() {
        $q = test_question_maker::make_question('coderunner', 'sqrnoprint');
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
        $this->process_submission(array('-submit' => 1, 'answer' => "def sqr(n): return n * n\n"));
        $this->check_current_mark(3.0 / 31.0);
        $this->check_current_output( new question_pattern_expectation('/Twiddlydum/') );
    }

    /**
     *  Test that HTML in result table cells is appropriately sanitised
     */
    public function test_result_table_sanitising() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $qa = $this->get_question_attempt();

        // Submit an answer with a <b> tag in it and make sure it's suitably
        // escaped so it appears in the output.
        $this->process_submission(array('-submit' => 1,
            'answer' => "def sqr(n):\n    print('<b>')\n    return n * n"));
        $this->check_output_does_not_contain('<b>');
    }

    public function test_grading_template_html_output() {
        /* Test a grading template that supplies a JSON record with a got_html
           attribute. For a per-test template this is used in the results
           table as the raw html contents of the "got" column.
         */
        $q = test_question_maker::make_question('coderunner', 'sqrnoprint');
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
        $this->process_submission(array('-submit' => 1,
            'answer' => "def sqr(n): return -1 if n == 1 else n * n \n"));
        $this->check_current_mark(21.0 / 31.0);
        $this->check_current_output( new question_pattern_expectation("|<svg width=.100. height=.200.></svg>|") );
        $this->check_current_output( new question_pattern_expectation('/YeeHa/') );
        $this->check_current_output( new question_pattern_expectation('|<h2>Header</h2>|') );
    }

    public function test_combinator_template_grading() {
        // Use the question maker to provide a dummy question.
        // Mostly ignore it. This question wants an answer with exactly
        // two occurrences of each of the tokens 'hi' and 'ho' and awards
        // a mark according to how well this criterion is satisfied.
        $q = test_question_maker::make_question('coderunner', 'sqrnoprint');
        $q->template = <<<EOTEMPLATE
import json
answer = """{{ STUDENT_ANSWER | e('py') }}"""
tokens = answer.split()
num_hi = len([t for t in tokens if t.lower() == 'hi'])
num_ho = len([t for t in tokens if t.lower() == 'ho'])
hi_mark = 2 if num_hi == 2 else 1 if abs(num_hi - 2) == 1 else 0
ho_mark = 2 if num_ho == 2 else 1 if abs(num_ho - 2) == 1 else 0
fraction = (hi_mark + ho_mark) / 4
if fraction == 1.0:
    feedback = '<h2>Well done</h2><p>I got 2 of each of hi and ho.</p>'
else:
    template = '<h2>Wrong numbers of hi and/or ho</h2>'
    template += '<p>I wanted 2 of each but got {} and {} respectively.</p>'
    feedback = template.format(num_hi, num_ho)
print(json.dumps({'fraction': fraction, 'feedback_html': feedback}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->allornothing = false;
        $q->grader = 'TemplateGrader';

        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "hi di hi and HO DI HO"));
        $this->check_current_mark(1.0);
        $this->check_current_output( new question_pattern_expectation('|<h2>Well done</h2>|') );

        // Submit a partially right  answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "hi di nothi and HO DI NOTHO"));
        $this->check_current_mark(0.5);
        $this->check_current_output( new question_pattern_expectation('|<h2>Wrong numbers of hi and/or ho</h2>|') );
    }


    // Test the new template grader testresults, prologuehtml and columnformats fields.
    public function test_combinator_template_grading2() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

n_vals = [3, -5, 11, 21, 200];
results = [['n', 'Expected', 'Got', 'Mark', 'Correct', 'Comment', 'ishidden', 'iscorrect']]
total = 0
for n in n_vals:
    got = sqr(n)
    mark = 1 if got == n * n else 0
    total += mark
    results.append([n, n * n, got, mark, got == n * n, '<p class="blah">Test value ' + str(n) + '</p>', 0, got == n * n])
print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'testresults': results,
                  'epiloguehtml': "Wasn't that FUN!",
                  'fraction': total / len(n_vals),
                  'columnformats': ['%s', '%s', '%s', '%s', '%s', '%h']
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->allornothing = true;  // This should be ignored.
        $q->grader = 'TemplateGrader';

        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "def sqr(n): return n * n"));
        $this->check_current_mark(1.0);
        $this->check_output_contains('Prologue');
        $this->check_output_contains('Expected');
        $this->check_output_contains('Got');
        $this->check_output_contains("Wasn't that FUN!");
        $this->check_output_contains("Passed all tests!");
        $this->check_output_does_not_contain("Blah"); // HTML field should be rendered directly so class not visible.

        // Submit a partially right  answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "def sqr(n): return n * n if n != -5 else 'Bin' + 'Go!'"));
        $this->check_output_contains('Prologue');
        $this->check_output_contains('Expected');
        $this->check_output_contains('Got');
        $this->check_output_contains("Wasn't that FUN!");
        $this->check_output_contains("Marks for this submission: 0.80/1.00");
        $this->check_output_contains("BinGo!");
    }


    // Test that if the combinator output fails to yield the expected number
    // of test case outputs, we get an appropriate error message.
    public function test_bad_combinator_error() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
__SEPARATOR__ = "#<ab@17943918#@>#"
{% for TEST in TESTCASES %}
{{ TEST.testcode }}
print(__SEPARATOR__)
{% endfor %}
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'EqualityGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => 'def sqr(n): return n * n'));
        $this->check_current_mark(0.0);
        $this->check_output_contains('Perhaps excessive output or error in question?');
    }

    // Check that if Template Debugging is enabled, the source code appears.
    public function test_template_debugging() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->showsource = 1;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => 'def sqr(n): return n * n'));
        $this->check_output_contains('Debug: source code from all test runs');
        $this->check_output_contains('Run 1');
        $this->check_output_contains('SEPARATOR = &quot;#&lt;ab@17943918#@&gt;#&quot;');
    }


    // Test that if the combinator grader output has a columnformats with
    // the wrong number of values, an appropriate error is issued.
    public function test_bad_combinator_grader_error() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

n_vals = [3, -5, 11, 21, 200];
results = [['n', 'Expected', 'Got', 'Mark', 'Correct', 'Comment', 'ishidden', 'iscorrect']]
total = 0
for n in n_vals:
    got = sqr(n)
    mark = 1 if got == n * n else 0
    total += mark
    results.append([n, n * n, got, mark, got == n * n, '<p class="blah">Test value ' + str(n) + '</p>', 0, got == n * n])
print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'testresults': results,
                  'epiloguehtml': "Wasn't that FUN!",
                  'fraction': total / len(n_vals),
                  'columnformats': ['%s', '%s', '%s', '%s', '%s', '%h', '%s']
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => 'def sqr(n): return n * n'));
        $this->check_current_mark(0.0);
        $this->check_output_contains('Wrong number of test results column formats. Expected 6, got 7');
    }


    // Test that if the combinator output has a misspelled columnformats
    // field, an appropriate error is issued.
    public function test_bad_combinator_grader_error2() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

n_vals = [3, -5, 11, 21, 200];
results = [['n', 'Expected', 'Got', 'Mark', 'Correct', 'Comment', 'ishidden', 'iscorrect']]
total = 0
for n in n_vals:
    got = sqr(n)
    mark = 1 if got == n * n else 0
    total += mark
    results.append([n, n * n, got, mark, got == n * n, '<p class="blah">Test value ' + str(n) + '</p>', 0, got == n * n])
print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'testresults': results,
                  'epiloguehtml': "Wasn't that FUN!",
                  'fraction': total / len(n_vals),
                  'columnformatt': ['%s', '%s', '%s', '%s', '%s', '%h', '%s']
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => 'def sqr(n): return n * n'));
        $this->check_current_mark(0.0);
        $this->check_output_contains('Unknown field name (columnformatt) in combinator grader output');
    }


    // Test that if the combinator output has a bad value in the columnformats
    // field, an appropriate error is issued.
    public function test_bad_combinator_grader_error3() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

n_vals = [3, -5, 11, 21, 200];
results = [['n', 'Expected', 'Got', 'Mark', 'Correct', 'Comment', 'ishidden', 'iscorrect']]
total = 0
for n in n_vals:
    got = sqr(n)
    mark = 1 if got == n * n else 0
    total += mark
    results.append([n, n * n, got, mark, got == n * n, '<p class="blah">Test value ' + str(n) + '</p>', 0, got == n * n])
print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'testresults': results,
                  'epiloguehtml': "Wasn't that FUN!",
                  'fraction': total / len(n_vals),
                  'columnformats': ['%s', '%s', '%s', '%s', '%x', '%h']
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => 'def sqr(n): return n * n'));
        $this->check_current_mark(0.0);
        $this->check_output_contains('Illegal format (%x) in columnformats');
    }

}