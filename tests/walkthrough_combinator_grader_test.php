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
 * This is a walkthrough test for the CodeRunner plugin, focusing on tests
 * of the combinator template grader.
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
 *
 * @coversNothing
 * @copyright  2011, 2020 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class walkthrough_combinator_grader_test extends \qbehaviour_walkthrough_test_base {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        \qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    public function test_combinator_template_grading() {
        // Use the question maker to provide a dummy question.
        // Mostly ignore it. This question wants an answer with exactly
        // two occurrences of each of the tokens 'hi' and 'ho' and awards
        // a mark according to how well this criterion is satisfied.
        $q = \test_question_maker::make_question('coderunner', 'sqrnoprint');
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
        $this->process_submission(['-submit' => 1,
            'answer' => "hi di hi and HO DI HO"]);
        $this->check_current_mark(1.0);
        $this->check_current_output(new \question_pattern_expectation('|<h2>Well done</h2>|'));

        // Submit a partially right  answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => "hi di nothi and HO DI NOTHO"]);
        $this->check_current_mark(0.5);
        $this->check_current_output(new \question_pattern_expectation('|<h2>Wrong numbers of hi and/or ho</h2>|'));
    }


    // Test the new template grader testresults, prologuehtml and columnformats fields.
    public function test_combinator_template_grading2() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
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
        $this->process_submission(['-submit' => 1,
            'answer' => "def sqr(n): return n * n"]);
        $this->check_current_mark(1.0);
        $this->check_output_contains('Prologue');
        $this->check_output_contains('Expected');
        $this->check_output_contains('Got');
        $this->check_output_contains("Wasn't that FUN!");
        $this->check_output_contains("Passed all tests!");
        $this->check_output_does_not_contain("Blah"); // HTML field should be rendered directly so class not visible.

        // Submit a partially right  answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => "def sqr(n): return n * n if n != -5 else 'Bin' + 'Go!'"]);
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
        $q = \test_question_maker::make_question('coderunner', 'sqr');
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
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(0.0);
        $this->check_output_contains('Perhaps excessive output or error in question?');
    }

    // Test that if the combinator grader outputs bad JSON, we get an
    // appropriate error message.
    public function test_bad_json() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

print('twaddle')
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(0.0);
        $this->check_output_contains('Bad JSON output from combinator grader. Output was: twaddle');
    }

    // Test that if the combinator grader output has a missing fraction attribute
    // an error is generated.
    public function test_missing_fraction() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'epiloguehtml': "Wasn't that FUN!",
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(0.0);
        $this->check_output_contains('Bad or missing fraction in output from template grader');
    }

    // Test that if the combinator grader output has a fraction attribute > 1.0
    // an error is generated.
    public function test_bad_fraction() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'fraction': 1.1,
                  'epiloguehtml': "Wasn't that FUN!",
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(0.0);
        $this->check_output_contains('Bad or missing fraction in output from template grader');
    }

    // Test that if the combinator grader output has a showoutputonly attribute
    // and no fraction, no error is generated, the mark is 1.0 and there is
    // no Passed all tests message.
    public function test_show_output_only() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}

print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'showoutputonly': True,
                  'epiloguehtml': "Wasn't that FUN!",
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(1.0);
        $this->check_output_contains("Prologue");
        $this->check_output_contains("Wasn't that FUN!");
        $this->check_output_does_not_contain('Passed all tests');
    }

    // Test that if the combinator grader output has a columnformats with
    // the wrong number of values, an appropriate error is issued.
    public function test_bad_combinator_grader_error() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
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
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(0.0);
        $this->check_output_contains('Wrong number of test results column formats. Expected 6, got 7');
    }


    // Test that if the combinator output has a misspelled columnformats
    // field, an appropriate error is issued.
    public function test_bad_combinator_grader_error2() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
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
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(0.0);
        $this->check_output_contains('Unknown field name (columnformatt) in combinator grader output');
    }


    // Test that if the combinator output has a bad value in the columnformats
    // field, an appropriate error is issued.
    public function test_bad_combinator_grader_error3() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
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
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark(0.0);
        $this->check_output_contains('Illegal format (%x) in columnformats');
    }

    // Test that if there is a 'graderstate' in the JSON printed by
    // a combinator template grader, that value is available to the
    // question author on the next submission of an answer.
    public function test_graderstate_in_stepinfo() {
        $q = \test_question_maker::make_question('coderunner', 'sqr');
        $q->template = <<<EOTEMPLATE
import json
{{ STUDENT_ANSWER }}
stepinfo = json.loads("""{{ QUESTION.stepinfo | json_encode }}""")
gradermessage = stepinfo['graderstate']
if gradermessage == '':
    gradermessage = 'Empty'
epiloguehtml = f"graderstate: {gradermessage}"
print(json.dumps({'prologuehtml': "<h2>Prologue</h2>",
                  'showoutputonly': True,
                  'epiloguehtml': epiloguehtml,
                  'graderstate': "boomerang"
}))
EOTEMPLATE;
        $q->iscombinatortemplate = true;
        $q->grader = 'TemplateGrader';
        // Submit a right answer.
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n']);
        $this->check_output_contains("Prologue");
        $this->check_output_contains("graderstate: Empty");
        $this->check_output_does_not_contain('Passed all tests');
        $this->process_submission(['-submit' => 1,
            'answer' => 'def sqr(n): return n * n # resubmit']);
        $this->check_output_contains("graderstate: boomerang");
    }
}
