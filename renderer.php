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
 * CodeRunner renderer class.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

use qtype_coderunner\constants;

/**
 * Subclass for generating the bits of output specific to coderunner questions.
 *
 * @copyright  Richard Lobb, University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qtype_coderunner_renderer extends qtype_renderer {

    const FORCE_TABULAR_EXAMPLES = true;

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the question text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        global $CFG, $PAGE;

        $question = $qa->get_question();
        $qtext = $question->format_questiontext($qa);
        $examples = $question->example_testcases();
        if (count($examples) > 0) {
            $qtext .= html_writer::tag('p', 'For example:', array('class' => 'for-example-para'));
            $qtext .= html_writer::start_tag('div', array('class' => 'coderunner-examples'));
            $qtext .= $this->format_examples($examples);
            $qtext .= html_writer::end_tag('div');
        }

        $qtext .= html_writer::start_tag('div', array('class' => 'prompt'));

        if (empty($question->penaltyregime)) {
            if (intval(100 * $question->penalty) == 100 * $question->penalty) {
                $decdigits = 0;
            } else {
                $decdigits = 1;
            }
            $penaltypercent = number_format($question->penalty * 100, $decdigits);
            $penaltypercent2 = number_format($question->penalty * 200, $decdigits);
            $penalties = $penaltypercent . ', ' . $penaltypercent2 . ', ...';
        } else {
            $penalties = $question->penaltyregime;
        }

        $answerprompt = html_writer::tag('span',
                get_string('answerprompt', 'qtype_coderunner'), array('class' => 'answerprompt'));
        $penaltystring = html_writer::tag('span',
            get_string('penaltyregime', 'qtype_coderunner', $penalties),
            array('class' => 'penaltyregime'));
        $qtext .= $answerprompt . $penaltystring;
        $qtext .= html_writer::end_tag('div');

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $rows = isset($question->answerboxlines) ? $question->answerboxlines : 18;
        $cols = isset($question->answerboxcolumns) ? $question->answerboxcolumns : 100;
        $preload = isset($question->answerpreload) ? $question->answerpreload : '';
        $taattributes = array(
            'class' => 'coderunner-answer edit_code',
            'name'  => $responsefieldname,
            'id'    => $responsefieldid,
            'cols'      => $cols,
            'spellcheck' => 'false',
            'rows'      => $rows
        );

        if ($options->readonly) {
            $taattributes['readonly'] = 'readonly';
        }

        $currentanswer = $qa->get_last_qt_var('answer');
        if (empty($currentanswer)) {
            $currentanswer = $preload;
        }
        $qtext .= html_writer::tag('textarea', s($currentanswer), $taattributes);

        if ($qa->get_state() == question_state::$invalid) {
            $qtext .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)),
                    array('class' => 'validationerror'));
        }

        // Initialise any program-editing JavaScript.
        // Thanks to Ulrich Dangel for the original implementation of the Ace code editor.
        qtype_coderunner_util::load_ace_if_required($question, $responsefieldid, constants::USER_LANGUAGE);
        $PAGE->requires->js_call_amd('qtype_coderunner/textareas', 'initQuestionTA', array($responsefieldid));

        return $qtext;
    }


    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function specific_feedback(question_attempt $qa) {
        $toserialised = $qa->get_last_qt_var('_testoutcome');
        if (!$toserialised) { // Something broke?
            return '';
        }

        $q = $qa->get_question();
        $outcome = unserialize($toserialised);
        $resultsclass = $this->results_class($outcome, $q->allornothing);
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        if ($isprecheck) {
            $resultsclass .= ' precheck';
        }

        $fb = '';

        if ($q->showsource) {
            $fb .= $this->make_source_code_div($outcome);
        }

        $fb .= html_writer::start_tag('div', array('class' => $resultsclass));
        if ($outcome->run_failed()) {
            $fb .= html_writer::tag('h3', get_string('run_failed', 'qtype_coderunner'));;
            $fb .= html_writer::tag('p', s($outcome->errormessage),
                    array('class' => 'run_failed_error'));
        } else if ($outcome->has_syntax_error()) {
            $fb .= html_writer::tag('h3', get_string('syntax_errors', 'qtype_coderunner'));
            $fb .= html_writer::tag('pre', s($outcome->errormessage),
                    array('class' => 'pre_syntax_error'));
        } else if ($outcome->combinator_error()) {
            $fb .= html_writer::tag('h3', get_string('badquestion', 'qtype_coderunner'));
            $fb .= html_writer::tag('pre', s($outcome->errormessage),
                    array('class' => 'pre_question_error'));

        } else {

            // The run was successful (i.e didn't crash, but may be wrong answer). Display results.
            if ($isprecheck) {
                $fb .= html_writer::tag('h3', get_string('precheck_only', 'qtype_coderunner'));
            }

            if ($isprecheck && $q->precheck == constants::PRECHECK_EMPTY && !$outcome->iscombinatorgrader()) {
                $fb .= $this->empty_precheck_status($outcome);
            } else {
                $fb .= $this->build_results_table($outcome, $q);
            }
        }

        // Summarise the status of the response in a paragraph at the end.
        // Suppress when previous errors have already said enough.
        if (!$outcome->has_syntax_error() &&
             !$outcome->is_ungradable() &&
             !$outcome->run_failed()) {

            $fb .= $this->build_feedback_summary($qa, $outcome);
        }
        $fb .= html_writer::end_tag('div');

        return $fb;
    }

    /**
     * Return html to display the status of an empty precheck run.
     * @param qtype_coderunner_testing_outcome $outcome the results from the test
     * Must be a standard testing outcome, not a combinator grader outcome.
     * @return html string describing the outcome
     */
    protected function empty_precheck_status($outcome) {
        $output = $outcome->get_raw_output();
        if (!empty($output)) {
            $fb = html_writer::tag('p', get_string('bademptyprecheck', 'qtype_coderunner'));
            $fb .= html_writer::tag('pre', qtype_coderunner_util::format_cell($output),
                    array('class' => 'bad_empty_precheck'));
        } else {
            $fb = html_writer::tag('p', get_string('goodemptyprecheck', 'qtype_coderunner'),
                    array('class' => 'good_empty_precheck'));
        }
        return $fb;

    }

    // Generate the main feedback, consisting of (in order) any prologuehtml,
    // a table of results and any epiloguehtml.
    protected function build_results_table($outcome, qtype_coderunner_question $question) {
        $fb = $outcome->get_prologue();
        $testresults = $outcome->get_test_results($question);
        if (count($testresults) > 0) {
            $table = new html_table();
            $table->attributes['class'] = 'coderunner-test-results';
            $headers = $testresults[0];
            foreach ($headers as $header) {
                if (strtolower($header) != 'ishidden') {
                    $table->head[] = strtolower($header) === 'iscorrect' ? '' : $header;
                }
            }

            $rowclasses = array();
            $tablerows = array();

            for ($i = 1; $i < count($testresults); $i++) {
                $cells = $testresults[$i];
                $rowclass = $i % 2 == 0 ? 'r0' : 'r1';
                $tablerow = array();
                $j = 0;
                foreach ($cells as $cell) {
                    if (strtolower($headers[$j]) === 'iscorrect') {
                        $markfrac = (float) $cell;
                        $tablerow[] = $this->feedback_image($markfrac);
                    } else if (strtolower($headers[$j]) === 'ishidden') { // Control column.
                        if ($cell) { // Anything other than zero or false means hidden.
                            $rowclass .= ' hidden-test';
                        }
                    } else if ($cell instanceof qtype_coderunner_html_wrapper) {
                        $tablerow[] = $cell->value();  // It's already HTML.
                    } else {
                        $tablerow[] = qtype_coderunner_util::format_cell($cell);
                    }
                    $j++;
                }
                $tablerows[] = $tablerow;
                $rowclasses[] = $rowclass;
            }
            $table->data = $tablerows;
            $table->rowclasses = $rowclasses;
            $fb .= html_writer::table($table);

        }
        $fb .= empty($outcome->epiloguehtml) ? '' : $outcome->epiloguehtml;

        return $fb;
    }


    // Compute the HTML feedback summary for this test outcome.
    // Should not be called if there were any syntax or sandbox errors.
    protected function build_feedback_summary(question_attempt $qa, qtype_coderunner_testing_outcome $outcome) {
        if ($outcome->iscombinatorgrader()) {
            // Simplified special case.
            return $this->build_combinator_grader_feedback_summary($qa, $outcome);
        }
        $question = $qa->get_question();
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        $lines = array();  // List of lines of output.

        $onlyhiddenfailed = false;
        if ($outcome->was_aborted()) {
            $lines[] = get_string('aborted', 'qtype_coderunner');
        } else {
            $hiddenerrors = $outcome->count_hidden_errors();
            if ($outcome->get_error_count() > 0) {
                if ($outcome->get_error_count() == $hiddenerrors) {
                    $onlyhiddenfailed = true;
                    $lines[] = get_string('failedhidden', 'qtype_coderunner');
                } else if ($hiddenerrors > 0) {
                    $lines[] = get_string('morehidden', 'qtype_coderunner');
                }
            }
        }

        if ($outcome->all_correct()) {
            if (!$isprecheck) {
                $lines[] = get_string('allok', 'qtype_coderunner') .
                        "&nbsp;" . $this->feedback_image(1.0);
            }
        } else {
            if ($question->allornothing && !$isprecheck) {
                $lines[] = get_string('noerrorsallowed', 'qtype_coderunner');
            }

            // Provide a show differences button if answer wrong and equality grader used.
            if ((empty($question->grader) ||
                 $question->grader == 'EqualityGrader' ||
                 $question->grader == 'NearEqualityGrader') &&
                    !$onlyhiddenfailed) {
                $lines[] = $this->diff_button($qa);
            }
        }

        return qtype_coderunner_util::make_html_para($lines);
    }


    // A special case of the above method for use with combinator template graders
    // only.
    protected function build_combinator_grader_feedback_summary($qa, qtype_coderunner_combinator_grader_outcome $outcome) {
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        $lines = array();  // List of lines of output.

        if ($outcome->all_correct() && !$isprecheck) {
            $lines[] = get_string('allok', 'qtype_coderunner') .
                    "&nbsp;" . $this->feedback_image(1.0);
        }

        return qtype_coderunner_util::make_html_para($lines);
    }


    // Build and return an HTML div section containing a list of template
    // outputs used as source code (which are recorded in the given $outcome).
    protected function make_source_code_div($outcome) {
        $html = '';
        $sourcecodelist = $outcome->get_sourcecode_list();
        if (count($sourcecodelist) > 0) {
            $heading = get_string('sourcecodeallruns', 'qtype_coderunner');
            $html = html_writer::start_tag('div', array('class' => 'debugging'));
            $html .= html_writer::tag('h3', $heading);
            $i = 1;
            foreach ($sourcecodelist as $run) {
                $html .= html_writer::tag('h4', "Run $i");
                $i++;
                $html .= html_writer::tag('pre', s($run));
                $html .= html_writer::tag('hr', '');
            }
            $html .= html_writer::end_tag('div');
        }
        return $html;
    }


    /**
     * Return the HTML to display the sample answer, if given.
     * @param question_attempt $qa
     * @return string The html for displaying the sample answer.
     */
    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        $answer = $question->answer;
        if (!$answer) {
            return '';
        }

        $heading = get_string('asolutionis', 'qtype_coderunner');
        $html = html_writer::start_tag('div', array('class' => 'sample code'));
        $html .= html_writer::tag('h4', $heading);
        $html .= html_writer::tag('pre', s($answer));
        $html .= html_writer::end_tag('div');
        return $html;
    }


    // Format one or more examples.
    protected function format_examples($examples) {
        if ($this->all_single_line($examples) && ! self::FORCE_TABULAR_EXAMPLES) {
            return $this->format_examples_one_per_line($examples);
        } else {
            return $this->format_examples_as_table($examples);
        }
    }


    // Return true iff there is no standard input and all expectedoutput and shell
    // input cases are single line only.
    private function all_single_line($examples) {
        foreach ($examples as $example) {
            if (!empty($example->stdin) ||
                strpos($example->testcode, "\n") !== false ||
                strpos($example->expected, "\n") !== false) {
                return false;
            }
        }
        return true;
    }


    // Return a '<br>' separated list of expression -> result examples.
    // For use only where there is no stdin and shell input is one line only.
    private function format_examples_one_per_line($examples) {
        $text = '';
        foreach ($examples as $example) {
            $text .= $example->testcode . ' &rarr; ' . $example->expected;
            $text .= html_writer::empty_tag('br');
        }
        return $text;
    }

    /**
     *
     * @param array $examples The array of testcases tagged "use as example"
     * @return string An HTML table element displaying all the testcases.
     */
    private function format_examples_as_table($examples) {
        $table = new html_table();
        $table->attributes['class'] = 'coderunnerexamples';
        list($numstd, $numshell) = $this->count_bits($examples);
        $table->head = array();
        if ($numshell) {
            $table->head[] = 'Test';
        }
        if ($numstd) {
            $table->head[] = 'Input';
        }
        $table->head[] = 'Result';

        $tablerows = array();
        $rowclasses = array();
        $i = 0;
        foreach ($examples as $example) {
            $row = array();
            $rowclasses[$i] = $i % 2 == 0 ? 'r0' : 'r1';
            if ($numshell) {
                $row[] = qtype_coderunner_util::format_cell($example->testcode);
            }
            if ($numstd) {
                $row[] = qtype_coderunner_util::format_cell($example->stdin);
            }
            $row[] = qtype_coderunner_util::format_cell($example->expected);
            $tablerows[] = $row;
            $i++;
        }
        $table->data = $tablerows;
        $table->rowclasses = $rowclasses;
        return html_writer::table($table);
    }


    // Return a count of the number of non-empty stdins and non-empty shell
    // inputs in the given list of test result objects.
    private function count_bits($tests) {
        $numstds = 0;
        $numshell = 0;
        foreach ($tests as $test) {
            if (trim($test->stdin) !== '') {
                $numstds++;
            }
            if (trim($test->testcode) !== '') {
                $numshell++;
            }
        }
        return array($numstds, $numshell);
    }


    /**
     *
     * @param qtype_coderunner_testing_outcome $outcome
     * @return string the CSS class for the given testing outcome
     */
    protected function results_class($outcome, $isallornothing) {
        if ($outcome->all_correct()) {
            $resultsclass = "coderunner-test-results good";
        } else if ($isallornothing || $outcome->mark_as_fraction() == 0) {
            $resultsclass = "coderunner-test-results bad";
        } else {
            $resultsclass = 'coderunner-test-results partial';
        }
        return $resultsclass;
    }


    // Support method to generate the "Show differences" button.
    // Returns the HTML for the button, and sets up the JavaScript handler
    // for it.
    protected static function diff_button($qa) {
        global $PAGE;
        $attributes = array(
            'type' => 'button',
            'id' => $qa->get_behaviour_field_name('button'),
            'name' => $qa->get_behaviour_field_name('button'),
            'value' => get_string('showdifferences', 'qtype_coderunner'),
            'class' => 'btn',
        );
        $html = html_writer::empty_tag('input', $attributes);

        $PAGE->requires->js_call_amd('qtype_coderunner/showdiff',
            'initDiffButton',
            array($attributes['id'],
                get_string('showdifferences', 'qtype_coderunner'),
                get_string('hidedifferences', 'qtype_coderunner')
            )
        );

        return $html;
    }
}
