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
 * Multiple choice question renderer classes.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

define('FORCE_TABULAR_EXAMPLES', TRUE);
define('MAX_LINE_LENGTH', 120);
define('MAX_NUM_LINES', 200);
define('DEFAULT_RESULT_COLUMNS', '[["Test", "testcode"], ["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]]');

require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');

/**
 * Subclass for generating the bits of output specific to coderunner questions.
 *
 * @copyright  Richard Lobb, University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_coderunner_renderer extends qtype_renderer {

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
        $testcases = $question->testcases;
        $examples = array_filter($testcases, function($tc) {
                    return $tc->useasexample;
                });
        if (count($examples) > 0) {
            $qtext .= html_writer::tag('p', 'For example:', array('class' => 'for-example-para'));
            $qtext .= html_writer::start_tag('div', array('class' => 'coderunner-examples'));
            $qtext .= $this->formatExamples($examples);
            $qtext .= html_writer::end_tag('div');
        }


        $qtext .= html_writer::start_tag('div', array('class' => 'prompt'));
        $answerprompt = get_string("answer", "quiz") . ': ';
        $qtext .= $answerprompt;
        $qtext .= html_writer::end_tag('div');

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $rows =  isset($question->answerbox_lines) ? $question->answerbox_lines : 18;
        $cols = isset($question->answerbox_columns) ? $question->answerbox_columns : 100;
        $ta_attributes = array(
            'class' => 'coderunner-answer edit_code',
            'name'  => $responsefieldname,
            'id'    => $responsefieldid,
            'cols'      => $cols,
            'spellcheck' => 'false',
            'rows'      => $rows
        );

        if ($options->readonly) {
            $ta_attributes['readonly'] = 'readonly';
        }

        $currentanswer = $qa->get_last_qt_var('answer');
        $currentrating = $qa->get_last_qt_var('rating', 0);
        $qtext .= html_writer::tag('textarea', s($currentanswer), $ta_attributes);

        if ($qa->get_state() == question_state::$invalid) {
            $qtext .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)),
                    array('class' => 'validationerror'));
        }

        // Initialise any program-editing JavaScript.
        // Thanks to Ulrich Dangel for incorporating the Ace code editor.

        $PAGE->requires->js_init_call('M.qtype_coderunner.initQuestionTA', array($responsefieldid));
        load_ace_if_required($question, $responsefieldid, USER_LANGUAGE);

        return $qtext;

    }


    /**
     * Gereate the specific feedback. This is feedback that varies according to
     * the reponse the student gave.
     * This code tries to allow for the possiblity that the question is being
     * used with the wrong (i.e. non-adaptive) behaviour, which would mean that
     * test results aren't available.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function specific_feedback(question_attempt $qa) {
        $toSerialised = $qa->get_last_qt_var('_testoutcome');
        if ($toSerialised) {
            $q = $qa->get_question();
            $testCases = $q->testcases;
            $testOutcome = unserialize($toSerialised);
            $testResults = $testOutcome->testResults;
            if ($testOutcome->allCorrect()) {
                $resultsclass = "coderunner-test-results good";
            } elseif (!$q->all_or_nothing && $testOutcome->markAsFraction() > 0) {
                $resultsclass = 'coderunner-test-results partial';
            } else {
                $resultsclass = "coderunner-test-results bad";
            }

            $fb = '';

            if ($q->show_source && count($testOutcome->sourceCodeList) > 0) {
                $fb .= $this->makeSourceCodeDiv(
                        'Debug: source code from all test runs',
                        $testOutcome->sourceCodeList
                );
            }


            $fb .= html_writer::start_tag('div', array('class' => $resultsclass));
            // Hack to insert run host as hidden comment in html
            $fb .= "\n<!-- Run on {$testOutcome->runHost} -->\n";

            if ($testOutcome->hasSyntaxError()) {
                $fb .= html_writer::tag('h3', 'Syntax Error(s)');
                $fb .= html_writer::tag('pre', s($testOutcome->errorMessage), 
                        array('class' => 'pre_syntax_error'));
            }
            else if ($testOutcome->feedback_html) {
                $fb .= $testOutcome->feedback_html;
            } else {
                $fb .= html_writer::tag('p', '&nbsp;', array('class' => 'coderunner-spacer'));
                $results = $this->buildResultsTable($q, $testCases, $testResults);
                if ($results != NULL) {
                    $fb .= $results;
                }
            }

            // Summarise the status of the response in a paragraph at the end.

            if (!$testOutcome->hasSyntaxError() && !$testOutcome->feedback_html) {
                $fb .= $this->buildFeedbackSummary($q, $testCases, $testOutcome);
            }
            $fb .= html_writer::end_tag('div');
        } else { // No testresults?! Probably due to a wrong behaviour selected
            $text = get_string('qWrongBehaviour', 'qtype_coderunner');
            $fb = html_writer::start_tag('div', array('class' => 'missingResults'));
            $fb .= html_writer::tag('p', $text);
            $fb .= html_writer::end_tag('div');
        }
        return $fb;
    }


    // Build and return an HTML div section containing a list of template
    // outputs used as source code.
    private function makeSourceCodeDiv($heading, $runs) {
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


    // Return a table of results or NULL if there are no results to show.
    private function buildResultsTable($question, $testCases, $testResults) {
        // The set of columns to be displayed is specified by the 
        // question's result_columns variable. This is a JSON-encoded list
        // of column specifiers. A column specifier is itself a list, usually
        // with 2 or 3 elements. The first element is the column header
        // the second is the test result object field name whose value is to be
        // displayed in the column and the third (optional) element is the
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
                
        if (isset($question->result_columns) && $question->result_columns) {
            $result_columns = json_decode($question->result_columns);
        } else {
            $result_columns = json_decode(DEFAULT_RESULT_COLUMNS);
        }
        if ($COURSE && $coursecontext = context_course::instance($COURSE->id)) {
            $canViewHidden = has_capability('moodle/grade:viewhidden', $coursecontext);
        } else {
            $canViewHidden = FALSE;
        }

        $table = new html_table();
        $table->attributes['class'] = 'coderunner-test-results';

        
        // Build the table header, containing all the specified field headers,
        // unless all rows in that column would be blank.

        $table->head = array('');  // First column is tick or cross, like last column
        foreach ($result_columns as &$col_spec) {
            $len = count($col_spec);
            if ($len < 3) {
                $col_spec[] = '%s';  // Add missing default format
            }
            $header = $col_spec[0];
            $field = $col_spec[1];  // Primary field - there may be more
            $num_non_blank = count_non_blanks($field, $testResults);
            if ($num_non_blank == 0) {
                $col_spec[count($col_spec) - 1] = '';  // Zap format to hide column
            } else {
                $table->head[] = $header;
            }
        }
        $table->head[] = '';  // Final tick/cross column
        
        // Process each row of the results table
        
        $tableData = array();
        $testCaseKeys = array_keys($testCases);  // Arbitrary numeric indices. Aarghhh.
        $i = 0;
        foreach ($testResults as $testResult) {
            $testCase = $testCases[$testCaseKeys[$i]];
            $testIsVisible = $this->shouldDisplayResult($testCase, $testResult);
            if ($canViewHidden || $testIsVisible) {
                $fraction = $testResult->awarded / $testResult->mark;
                $tick_or_cross = $this->feedback_image($fraction);
                $tableRow = array($tick_or_cross); // Tick or cross
                foreach ($result_columns as &$col_spec) {
                    $len = count($col_spec);
                    $format = $col_spec[$len - 1];
                    if ($format === '%h') {  // If it's an html format
                        $field = $col_spec[1]; // ... use the template supplied value directly
                        if (property_exists($testResult, $field)) {
                            $value = $testResult->$field;
                        } else {
                            $value = "Field '$field' does not exist";
                        }
                        $tableRow[] = $value;  
                    } else if ($format !== '') {  // Else if it's a non-null column
                        $args = array($format);
                        for ($j = 1; $j < $len - 1; $j++) {
                            $field = $col_spec[$j];
                            if (property_exists($testResult, $field)) {
                                $args[] = restrict_qty($testResult->$field);
                            } else {
                                $args[] = "Field '$field' does not exist";
                            }
                        }
                        $content = call_user_func_array('sprintf', $args);
                        $tableRow[] = $this->formatCell($content);
                    }
                }
                $tableRow[] = $tick_or_cross;
                $tableData[] = $tableRow;
                if (!$testIsVisible) {
                    $rowclasses[$i] = 'hidden-test';
                }
            }
            $i++;
            if ($testCase->hiderestiffail && !$testResult->isCorrect) {
                break;
            }
        }
        
        $table->data = $tableData;
        if (isset($rowclasses)) {
            $table->rowclasses = $rowclasses;
        }
        if (count($tableData) > 0) {
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
    
    // Sanitise with 's()' and add line breaks to a given string
    private function formatCell($cell) {
        return str_replace("\n", "<br />", str_replace(' ', '&nbsp;', s($cell)));
    }

    // Compute the HTML feedback summary for a given test outcome.
    // Should not be called if there were any syntax errors, or if a
    // combinator-template grader was used. 
    private function buildFeedbackSummary($question, $testCases, $testOutcome) {
        assert(!$testOutcome->hasSyntaxError());
        $lines = array();  // List of lines of output
        $testResults = $testOutcome->testResults;
        if (count($testResults) != count($testCases)) {
            $lines[] = get_string('aborted', 'qtype_coderunner');
        } else {
            $numErrors = $testOutcome->errorCount;
            $hiddenErrors = $this->count_hidden_errors($testResults, $testCases);
            if ($numErrors > 0) {
                if ($numErrors == $hiddenErrors) {
                    // Only hidden tests were failed
                    $lines[] = get_string('failedhidden', 'qtype_coderunner');
                }
                else if ($hiddenErrors > 0) {
                    $lines[] = get_string('morehidden', 'qtype_coderunner');
                }
            }
        }

        if ($testOutcome->allCorrect()) {
            $lines[] = get_string('allok', 'qtype_coderunner') .
                        "&nbsp;" . $this->feedback_image(1.0);
        } else if ($question->all_or_nothing) {
            $lines[] = get_string('noerrorsallowed', 'qtype_coderunner');
        }


        // Convert list of lines to HTML paragraph

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


    // Format one or more examples
    protected function formatExamples($examples) {
        if ($this->allSingleLine($examples) && ! FORCE_TABULAR_EXAMPLES) {
            return $this->formatExamplesOnePerLine($examples);
        }
        else {
            return $this->formatExamplesAsTable($examples);
        }
    }


    // Return true iff there is no standard input and all expectedoutput and shell
    // input cases are single line only
    private function allSingleLine($examples) {
        foreach ($examples as $example) {
            if (!empty($example->stdin) ||
                strpos($example->testcode, "\n") !== FALSE ||
                strpos($example->expected, "\n") !== FALSE) {
               return FALSE;
            }
         }
         return TRUE;
    }



    // Return a '<br>' separated list of expression -> result examples.
    // For use only where there is no stdin and shell input is one line only.
    private function formatExamplesOnePerLine($examples) {
       $text = '';
       foreach ($examples as $example) {
            $text .=  $example->testcode . ' &rarr; ' . $example->expected;
            $text .= html_writer::empty_tag('br');
       }
       return $text;
    }


    private function formatExamplesAsTable($examples) {
        $table = new html_table();
        $table->attributes['class'] = 'coderunnerexamples';
        list($numStd, $numShell) = $this->countBits($examples);
        $table->head = array();
        if ($numShell) {
            $table->head[] = 'Test';
        }
        if ($numStd) {
            $table->head[] = 'Input';
        }
        $table->head[] = 'Result';

        $tableRows = array();
        foreach ($examples as $example) {
            $row = array();
            if ($numShell) {
                $row[] = $this->formatCell($example->testcode);
            }
            if ($numStd) {
                $row[] = $this->formatCell($example->stdin);
            }
            $row[] = $this->formatCell($example->expected);
            $tableRows[] = $row;
        }
        $table->data = $tableRows;
        return html_writer::table($table);
    }


    // Return a count of the number of non-empty stdins and non-empty shell
    // inputs in the given list of test result objects.
    private function countBits($tests) {
        $numStds = 0;
        $numShell = 0;
        foreach ($tests as $test) {
            if (trim($test->stdin) !== '') {
                $numStds++;
            }
            if (trim($test->testcode) !== '') {
                $numShell++;
            }
        }
        return array($numStds, $numShell);
    }


    // Count the number of errors in hidden testcases, given the arrays of
    // testcases and testresults. A slight complication here is that the testcase keys
    // are arbitrary integers.
    private function count_hidden_errors($testResults, $testCases) {
        $testCaseKeys = array_keys($testCases);  // Arbitrary numeric indices. Aarghhh.
        $i = 0;
        $count = 0;
        $hidingRest = FALSE;
        foreach ($testResults as $tr) {
            $testCase = $testCases[$testCaseKeys[$i]];
            if ($hidingRest) {
                $isDisplayed = FALSE;
            }
            else {
                $isDisplayed = $this->shouldDisplayResult($testCase, $tr);
            }
            if (!$isDisplayed && !$tr->isCorrect) {
                $count++;
            }
            if ($testCase->hiderestiffail && !$tr->isCorrect) {
                $hidingRest = TRUE;
            }
            $i++;
        }
        return $count;
    }


    // True iff the given test result should be displayed
    private function shouldDisplayResult($testCase, $testResult) {
        return $testCase->display == 'SHOW' ||
            ($testCase->display == 'HIDE_IF_FAIL' && $testResult->isCorrect) ||
            ($testCase->display == 'HIDE_IF_SUCCEED' && !$testResult->isCorrect);
    }

}


/* Support function to limit the size of a string for browser display.
 * Restricts line length to MAX_LINE_LENGTH and number of lines to
 * MAX_NUM_LINES.
 */
function restrict_qty($s) {
    $result = '';
    $n = strlen($s);
    $line = '';
    $lineLen = 0;
    $numLines = 0;
    for ($i = 0; $i < $n && $numLines < MAX_NUM_LINES; $i++) {
        if ($s[$i] != "\n") {
            if ($lineLen < MAX_LINE_LENGTH) {
                $line .= $s[$i];
            }
            elseif ($lineLen == MAX_LINE_LENGTH) {
                $line[MAX_LINE_LENGTH - 1] = $line[MAX_LINE_LENGTH - 2] =
                $line[MAX_LINE_LENGTH -3] = '.';
            }
            else {
                /* ignore remainder of line */
            }
            $lineLen++;
        }
        else {
            $result .= $line . "\n";
            $line = '';
            $lineLen = 0;
            $numLines += 1;
            if ($numLines == MAX_NUM_LINES) {
                $result .= "[... snip ...]\n";
            }
        }
    }
    return $result . $line;
}


// support function to count how many objects in the given list of objects
// have the given 'field' attribute non-blank. Non-existent fields are also
// included in order to generate a column showing the error, but null values

function count_non_blanks($field, $objects) {
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

