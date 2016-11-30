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
    const RESULT_COLUMNS = '[["Test", "testcode"], ["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]]';

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
        $answerprompt = get_string("answer", "quiz") . ': ';
        $qtext .= $answerprompt;
        $qtext .= html_writer::end_tag('div');

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $rows = isset($question->answerboxlines) ? $question->answerboxlines : 18;
        $cols = isset($question->answerboxcolumns) ? $question->answerboxcolumns : 100;
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
        $qtext .= html_writer::tag('textarea', s($currentanswer), $taattributes);

        if ($qa->get_state() == question_state::$invalid) {
            $qtext .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)),
                    array('class' => 'validationerror'));
        }

        $penalties = $question->penaltyregime ? $question->penaltyregime :
            number_format($question->penalty * 100, 1);
        $penaltypara =  html_writer::tag('p',
            get_string('penaltyregime', 'qtype_coderunner') . ': ' . s($penalties) . ' %',
            array('class' => 'penaltyregime'));
        $qtext .= $penaltypara;

        // Initialise any program-editing JavaScript.
        // Thanks to Ulrich Dangel for the original implementation of the Ace code editor.
        qtype_coderunner_util::load_ace_if_required($question, $responsefieldid, constants::USER_LANGUAGE);
        $PAGE->requires->js_call_amd('qtype_coderunner/textareas', 'initQuestionTA', array($responsefieldid));

        return $qtext;
    }



    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     * This code tries to allow for the possibility that the question is being
     * used with the wrong (i.e. non-adaptive) behaviour, which would mean that
     * test results aren't available. However, this can cause huge performance
     * loss, so a warning message accompanies the output in such cases.
     * As of 10 November 2016, a wrong behaviour type shouldn't be possible,
     * as CodeRunner questions now force adaptive mode. However, the code has
     * been left in place in case there is a need for a change to the policy
     * in future.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function specific_feedback(question_attempt $qa) {
        $q = $qa->get_question();
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        $fb = '';
        $toserialised = $qa->get_last_qt_var('_testoutcome');

        if (!$toserialised) {  // Bad bad bad. Not running in Adaptive mode.
            $response = $qa->get_last_qt_data();
            if (!$response) {
                return ''; // Not sure how this could happen copying renderbase.php.
            }
            if ($q->is_gradable_response($response)) {
                $text = get_string('qWrongBehaviour', 'qtype_coderunner');
                $fb .= html_writer::start_tag('div', array('class' => 'wrongBehaviour'));
                $fb .= html_writer::tag('p', $text);
                $fb .= html_writer::end_tag('div');
                list($markfraction, $state, $cachedata) = $q->grade_response($response);
                $toserialised = $cachedata['_testoutcome'];
            }
        }

        if ($toserialised) {  // Proceed only if we've managed to get some test data.
            $testoutcome = unserialize($toserialised);
            $testresults = $testoutcome->testresults;

            if ($testoutcome->all_correct()) {
                $resultsclass = "coderunner-test-results good";
            } else if (!$q->allornothing && $testoutcome->mark_as_fraction() > 0) {
                $resultsclass = 'coderunner-test-results partial';
            } else {
                $resultsclass = "coderunner-test-results bad";
            }

            if ($isprecheck) {
                $resultsclass .= ' precheck';
            }

            if ($q->showsource && count($testoutcome->sourcecodelist) > 0) {
                $fb .= $this->make_source_code_div(
                        'Debug: source code from all test runs',
                        $testoutcome->sourcecodelist
                );
            }

            $fb .= html_writer::start_tag('div', array('class' => $resultsclass));
            if ($testoutcome->run_failed()) {
                $fb .= html_writer::tag('h3', get_string('run_failed', 'qtype_coderunner'));;
                $fb .= html_writer::tag('p', s($testoutcome->errormessage),
                        array('class' => 'run_failed_error'));
            } else if ($testoutcome->has_syntax_error()) {
                $fb .= html_writer::tag('h3', get_string('syntax_errors', 'qtype_coderunner'));
                $fb .= html_writer::tag('pre', s($testoutcome->errormessage),
                        array('class' => 'pre_syntax_error'));
            } else if ($testoutcome->combinator_error()) {
                $fb .= html_writer::tag('h3', get_string('badquestion', 'qtype_coderunner'));
                $fb .= html_writer::tag('pre', s($testoutcome->errormessage),
                        array('class' => 'pre_question_error'));

            } else {
                if ($isprecheck) {
                    $fb .= html_writer::tag('h3', get_string('precheck_only', 'qtype_coderunner'));
                }
                if ($testoutcome->feedbackhtml) {
                    $fb .= $testoutcome->feedbackhtml;
                } else {
                    $results = $this->build_results_table($q, $testresults);
                    if ($results != null) {
                        $fb .= $results;
                    }
                }
            }

            // Summarise the status of the response in a paragraph at the end.
            // Suppress when previous errors have already said enough.
            if (!$testoutcome->has_syntax_error() &&
                 !$testoutcome->is_ungradable() &&
                 !$testoutcome->run_failed() &&
                 !$testoutcome->feedbackhtml) {
                $fb .= $this->build_feedback_summary($qa, $testoutcome);
            }
            $fb .= html_writer::end_tag('div');
        }

        return $fb;
    }


    // Build and return an HTML div section containing a list of template
    // outputs used as source code.
    private function make_source_code_div($heading, $runs) {
        $html = html_writer::start_tag('div', array('class' => 'debugging'));
        $html .= html_writer::tag('h3', $heading);
        $i = 1;
        foreach ($runs as $run) {
            $html .= html_writer::tag('h4', "Run $i");
            $i++;
            $html .= html_writer::tag('pre', s($run));
            $html .= html_writer::tag('hr', '');
        }
        $html .= html_writer::end_tag('div');
        return $html;
    }


    // Return a table of results or null if there are no results to show.
    private function build_results_table($question, $testresults) {
        // The set of columns to be displayed is specified by the
        // question's resultcolumns variable. This is a JSON-encoded list
        // of column specifiers. A column specifier is itself a list, usually
        // with 2 or 3 elements. The first element is the column header
        // the second is (usually) the test result object field name whose value
        // is to be displayed in the column and the third (optional) element is the
        // sprintf format used to display the field. It is also possible to
        // combine more than one field of the test result object into a single
        // field by adding extra field names into the column specifier before
        // the format, which is then mandatory. For example, to display the
        // mark awarded for a test case as, say '0.71 out of 1.00' the column
        // specifier would be ["Mark", "awarded", "mark", "%.2f out of %.2f"]
        // A special case format specifier is '%h' denoting that
        // te result object field value should be treated as ready-to-output html.
        // Empty columns are suppressed.

        global $COURSE;

        if (isset($question->resultcolumns) && $question->resultcolumns) {
            $resultcolumns = json_decode($question->resultcolumns);
        } else {
            $resultcolumns = json_decode(self::RESULT_COLUMNS);
        }
        if ($COURSE && $coursecontext = context_course::instance($COURSE->id)) {
            $canviewhidden = has_capability('moodle/grade:viewhidden', $coursecontext);
        } else {
            $canviewhidden = false;
        }

        $table = new html_table();
        $table->attributes['class'] = 'coderunner-test-results';
        $table->attributes['name'] = 'coderunner-test-results';

        // Build the table header, containing all the specified field headers,
        // unless all rows in that column would be blank.

        $table->head = array('');  // First column is tick or cross, like last column.
        foreach ($resultcolumns as &$colspec) {
            $len = count($colspec);
            if ($len < 3) {
                $colspec[] = '%s';  // Add missing default format.
            }
            $header = $colspec[0];
            $field = $colspec[1];  // Primary field - there may be more.
            $numnonblank = self::count_non_blanks($field, $testresults);
            if ($numnonblank == 0) {
                $colspec[count($colspec) - 1] = '';  // Zap format to hide column.
            } else {
                $table->head[] = $header;
            }
        }
        $table->head[] = '';  // Final tick/cross column.

        // Process each row of the results table.

        $tabledata = array();
        $i = 0;
        $rowclasses = array();
        $hidingrest = false;
        foreach ($testresults as $testresult) {
            $rowclasses[$i] = $i % 2 == 0 ? 'r0' : 'r1';
            $testisvisibile = $this->should_display_result($testresult) && !$hidingrest;
            if ($canviewhidden || $testisvisibile) {
                $fraction = $testresult->awarded / $testresult->mark;
                $tickorcross = $this->feedback_image($fraction);
                $tablerow = array($tickorcross); // Tick or cross.
                foreach ($resultcolumns as &$colspec) {
                    $len = count($colspec);
                    $format = $colspec[$len - 1];
                    if ($format === '%h') {  // If it's an html format, use value directly.
                        $value = $testresult->gettrimmedvalue($colspec[1]);
                        $tablerow[] = self::clean_html($value);
                    } else if ($format !== '') {  // Else if it's a non-null column.
                        $args = array($format);
                        for ($j = 1; $j < $len - 1; $j++) {
                            $value = $testresult->gettrimmedvalue($colspec[$j]);
                            $args[] = $value;
                        }
                        $content = call_user_func_array('sprintf', $args);
                        $tablerow[] = self::format_cell($content);
                    }
                }
                $tablerow[] = $tickorcross;
                $tabledata[] = $tablerow;
                if (!$testisvisibile) {
                    $rowclasses[$i] .= ' hidden-test';
                }
            }
            $i++;
            if ($testresult->hiderestiffail && !$testresult->iscorrect) {
                $hidingrest = true;
            }
        }

        $table->data = $tabledata;
        $table->rowclasses = $rowclasses;

        if (count($tabledata) > 0) {
            return html_writer::table($table);
        } else {
            return null;
        }
    }


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

    // Sanitise with 's()' and add line breaks to a given string.
    // TODO: expand tabs (which appear in Java traceback output).
    private static function format_cell($cell) {
        return str_replace("\n", "<br />", str_replace(' ', '&nbsp;', s($cell)));
    }

    // Compute the HTML feedback summary for a given test outcome.
    // Should not be called if there were any syntax or sandbox errors, or if a
    // combinator-template grader was used.
    private function build_feedback_summary($qa, $testoutcome) {
        $question = $qa->get_question();
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        $lines = array();  // List of lines of output.
        $testresults = $testoutcome->testresults;
        $onlyhiddenfailed = false;
        if ($testoutcome->was_aborted()) {
            $lines[] = get_string('aborted', 'qtype_coderunner');
        } else {
            $numerrors = $testoutcome->errorcount;
            $hiddenerrors = $this->count_hidden_errors($testresults);
            if ($numerrors > 0) {
                if ($numerrors == $hiddenerrors) {
                    $onlyhiddenfailed = true;
                    $lines[] = get_string('failedhidden', 'qtype_coderunner');
                } else if ($hiddenerrors > 0) {
                    $lines[] = get_string('morehidden', 'qtype_coderunner');
                }
            }
        }

        if ($testoutcome->all_correct()) {
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

        // Convert list of lines to HTML paragraph.

        if (count($lines) > 0) {
            $para = html_writer::start_tag('p');
            $para .= $lines[0];
            for ($i = 1; $i < count($lines); $i++) {
                $para .= html_writer::empty_tag('br') . $lines[$i];;
            }
            $para .= html_writer::end_tag('p');
        } else {
            $para = '';
        }
        return $para;
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


    // Clean the given html by wrapping it in <div> tags and parsing it with libxml
    // and outputing the (supposedly) cleaned up HTML.
    private function clean_html($html) {
        libxml_use_internal_errors(true);
        $html = "<div>". $html . "</div>"; // Wrap it in a div (seems to help libxml).
        $doc = new DOMDocument;
        if ($doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return $doc->saveHTML();
        } else {
            $message = "Errors in HTML\n<br />";
            foreach (libxml_get_errors() as $error) {
                $message .= "Line {$error->line} column {$error->line}: {$error->code}\n<br />";
            }
            libxml_clear_errors();
            $message .= "\n<br />" + $html;
            return $message;
        }
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
                $row[] = self::format_cell($example->testcode);
            }
            if ($numstd) {
                $row[] = self::format_cell($example->stdin);
            }
            $row[] = self::format_cell($example->expected);
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


    // Count the number of errors in hidden testcases, given the array of
    // testresults.
    private function count_hidden_errors($testresults) {
        $count = 0;
        $hidingrest = false;
        foreach ($testresults as $tr) {
            if ($hidingrest) {
                $isdisplayed = false;
            } else {
                $isdisplayed = $this->should_display_result($tr);
            }
            if (!$isdisplayed && !$tr->iscorrect) {
                $count++;
            }
            if ($tr->hiderestiffail && !$tr->iscorrect) {
                $hidingrest = true;
            }
        }
        return $count;
    }


    // True iff the given test result should be displayed.
    private function should_display_result($testresult) {
        return !isset($testresult->display) ||  // e.g. broken combinator template
             $testresult->display == 'SHOW' ||
            ($testresult->display == 'HIDE_IF_FAIL' && $testresult->iscorrect) ||
            ($testresult->display == 'HIDE_IF_SUCCEED' && !$testresult->iscorrect);
    }


    // Support function to count how many objects in the given list of objects
    // have the given 'field' attribute non-blank. Non-existent fields are also
    // included in order to generate a column showing the error, but null values.

    private static function count_non_blanks($field, $objects) {
        $n = 0;
        foreach ($objects as $obj) {
            if (!property_exists($obj, $field) ||
                (!is_null($obj->$field) && !is_string($obj->$field)) ||
                (is_string($obj->$field) && trim($obj->$field !== ''))) {
                $n++;
            }
        }
        return $n;
    }


    // Support method to generate the "Show differences" button.
    // Returns the HTML for the button, and sets up the JavaScript handler
    // for it.
    private static function diff_button($qa) {
        global $PAGE;
        $attributes = array(
            'type' => 'button',
            'id' => $qa->get_behaviour_field_name('button'),
            'name' => $qa->get_behaviour_field_name('button'),
            'value' => get_string('showdifferences', 'qtype_coderunner'),
            'class' => 'btn',
        );
        $output = html_writer::empty_tag('input', $attributes);

        $PAGE->requires->js_call_amd('qtype_coderunner/showdiff',
            'initDiffButton',
            array($attributes['id'],
                get_string('showdifferences', 'qtype_coderunner'),
                get_string('hidedifferences', 'qtype_coderunner')
            )
        );

        return $output;
    }
}
