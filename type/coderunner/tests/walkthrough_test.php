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
        require($CFG->dirroot . '/question/type/coderunner/tests/config.php');
    }

    public function test_adaptive() {
        $q = test_question_maker::make_question('coderunner', 'sqr');
        $q->hints = array(
            new question_hint(1, 'This is the first hint.', FORMAT_HTML),
            new question_hint(2, 'This is the second hint.', FORMAT_HTML),
        );
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

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

        // Submit a wrong answer
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                new question_pattern_expectation('/' .
                        preg_quote(get_string('noerrorsallowed', 'qtype_coderunner') . '/'))
              );

        // Now get it right.
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n * n'));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.6666666667);
        $this->check_current_output(
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_no_hint_visible_expectation());

    }

    public function test_partial_marks() {
        $q = test_question_maker::make_question('coderunner', 'sqrPartMarks');
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

         // Submit a totally wrong answer
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return -19'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                new question_pattern_expectation('/' .
                        preg_quote(get_string('incorrect', 'question') . '/'))
              );

        // Submit a partially right answer
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n * n if n < 0 else -19'));
        $this->check_current_mark(0.2666666667);
        $this->check_current_output(
                new question_pattern_expectation('/' .
                        preg_quote(get_string('partiallycorrect', 'question') . '/'))
              );

        // Now get it right.
        $this->process_submission(array('-submit' => 1, 'answer' => 'def sqr(n): return n * n'));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.3333333333333);
        $this->check_current_output(
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_no_hint_visible_expectation());

    }

    public function test_grading_template_output() {
        $q = test_question_maker::make_question('coderunner', 'sqrnoprint');
        $q->per_test_template = <<<EOTEMPLATE
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
        $q->all_or_nothing = false;
        $q->grader = 'TemplateGrader';
        $q->customise = true;
        $q->enable_combinator = false;
        $q->unitpenalty = 0;
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

         // Submit a totally wrong answer
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
        $this->check_current_mark(23.0/31.0);
        $this->check_current_output( new question_pattern_expectation('/Tiddlypom/') );
        $this->check_current_output( new question_pattern_expectation('/Twiddlydee/') );
    }
    
    public function test_grading_template_html_output() {
        /* Test a grading template that supplies a JSON record with a got_html
           attribute. For a per-test template this is used in the results
           table as the raw html contents of the "got" column.
         */
        $q = test_question_maker::make_question('coderunner', 'sqrnoprint');
        $q->per_test_template = <<<EOTEMPLATE
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
        $q->all_or_nothing = false;
        $q->grader = 'TemplateGrader';
        $q->customise = true;
        $q->enable_combinator = false;
        $q->unitpenalty = 0;
        $q->result_columns = '[["Test", "testcode"], ["Expected", "expected_html", "%h"], ["Got", "got_html", "%h"]]';

        // Submit an answer that's right for all except one test case
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "def sqr(n): return -1 if n == 1 else n * n \n"));
        //echo $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $this->check_current_mark(21.0/31.0);
        $this->check_current_output( new question_pattern_expectation('|<svg width=\'100\' height=\'200\'></svg>|') );
        $this->check_current_output( new question_pattern_expectation('/YeeHa/') );
        $this->check_current_output( new question_pattern_expectation('|<h2>Header</h2>|') );
    }
    
    
    public function test_combinator_template_grading() {
        // Use the question maker to provide a dummy question.
        // Mostly ignore it. This question wants an answer with exactly
        // two occurrences of each of the tokens 'hi' and 'ho' and awards
        // a mark according to how well this criterion is satisfied.
        $q = test_question_maker::make_question('coderunner', 'sqrnoprint');
        $q->combinator_template = <<<EOTEMPLATE
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
    feedback = '<h2>Wrong numbers of hi and/or ho</h2><p>I wanted 2 of each but got {} and {} respectively.</p>'.format(num_hi, num_ho)
print(json.dumps({'fraction': fraction, 'feedback_html': feedback}))
EOTEMPLATE;
        $q->all_or_nothing = false;
        $q->grader = 'qtype_coderunner_combinator_template_grader';
        $q->customise = true;
        $q->enable_combinator = true;
        $q->unitpenalty = 0;

        // Submit a right answer
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "hi di hi and HO DI HO"));
        //echo $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $this->check_current_mark(1.0);
        $this->check_current_output( new question_pattern_expectation('|<h2>Well done</h2>|') );
        
        // Submit a partially right  answer
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(array('-submit' => 1,
            'answer' => "hi di nothi and HO DI NOTHO"));
        //echo $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $this->check_current_mark(0.5);
        $this->check_current_output( new question_pattern_expectation('|<h2>Wrong numbers of hi and/or ho</h2>|') );
    }
}

