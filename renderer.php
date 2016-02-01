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

require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');
require_once($CFG->dirroot . '/question/type/coderunner/constants.php');
require_once($CFG->dirroot . '/question/type/coderunner/testingoutcome.php');
require_once($CFG->dirroot . '/question/type/coderunner/legacytestingoutcome.php');

use qtype_coderunner\constants;

/**
 * Subclass for generating the bits of output specific to coderunner questions.
 *
 * @copyright  Richard Lobb, University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qtype_coderunner_renderer extends qtype_renderer {
    
    const FORCE_TABULAR_EXAMPLES = true;
    const MAX_LINE_LENGTH = 120;
    const MAX_NUM_LINES = 200;
    const SIMPLE_RESULT_COLUMNS = '[["Test", "testcode"], ["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]]';
    const DIFF_RESULT_COLUMNS = '[["Test", "testcode"], ["Input", "stdin"], ["Expected", "diff(expected, got)", "%h"], ["Got", "diff(got, expected)", "%h"]]';

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
            $qtext .= $this->format_examples($examples);
            $qtext .= html_writer::end_tag('div');
        }


        $qtext .= html_writer::start_tag('div', array('class' => 'prompt'));
        $answerprompt = get_string("answer", "quiz") . ': ';
        $qtext .= $answerprompt;
        $qtext .= html_writer::end_tag('div');

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $rows =  isset($question->answerboxlines) ? $question->answerboxlines : 18;
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
        $currentrating = $qa->get_last_qt_var('rating', 0);
        $qtext .= html_writer::tag('textarea', s($currentanswer), $taattributes);

        if ($qa->get_state() == question_state::$invalid) {
            $qtext .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)),
                    array('class' => 'validationerror'));
        }

        // Initialise any program-editing JavaScript.
        // Thanks to Ulrich Dangel for incorporating the Ace code editor.

        $PAGE->requires->js_init_call('M.qtype_coderunner.initQuestionTA', array($responsefieldid));
        load_ace_if_required($question, $responsefieldid, constants::USER_LANGUAGE);

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
        $toserialised = $qa->get_last_qt_var('_testoutcome');
        if ($toserialised) {
            $q = $qa->get_question();
            $testcases = $q->testcases;
            $testoutcome = unserialize($toserialised);
            $testresults = $testoutcome->testresults;
            if ($testoutcome->all_correct()) {
                $resultsclass = "coderunner-test-results good";
            } else if (!$q->allornothing && $testoutcome->mark_as_fraction() > 0) {
                $resultsclass = 'coderunner-test-results partial';
            } else {
                $resultsclass = "coderunner-test-results bad";
            }

            $fb = '';

            if ($q->showsource && count($testoutcome->sourcecodelist) > 0) {
                $fb .= $this->make_source_code_div(
                        'Debug: source code from all test runs',
                        $testoutcome->sourcecodelist
                );
            }


            $fb .= html_writer::start_tag('div', array('class' => $resultsclass));
            // Hack to insert run host as hidden comment in html
            $fb .= "\n<!-- Run on {$testoutcome->runhost} -->\n";

            if ($testoutcome->run_failed()) {
                $fb .= html_writer::tag('h3', get_string('run_failed', 'qtype_coderunner'));;
                $fb .= html_writer::tag('p', s($testoutcome->errormessage), 
                        array('class' => 'run_failed_error'));
            } else if ($testoutcome->has_syntax_error()) {
                $fb .= html_writer::tag('h3', get_string('syntax_errors', 'qtype_coderunner'));
                $fb .= html_writer::tag('pre', s($testoutcome->errormessage), 
                        array('class' => 'pre_syntax_error'));
            } else if ($testoutcome->feedbackhtml) {
                $fb .= $testoutcome->feedbackhtml;
            } else {
                $fb .= html_writer::tag('p', '&nbsp;', array('class' => 'coderunner-spacer'));
                $results = $this->build_results_table($q, $testcases, $testresults);
                if ($results != null) {
                    $fb .= $results;
                }
            }

            // Summarise the status of the response in a paragraph at the end.

            if (!$testoutcome->has_syntax_error() && !$testoutcome->run_failed() &&
                !$testoutcome->feedbackhtml) {
                $fb .= $this->build_feedback_summary($qa, $testcases, $testoutcome);
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
    private function build_results_table($question, $testcases, $testresults) {
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
        // As an extension an expression of the form
        // diff(resultObjectField1, resultObjectField2) can be used in place
        // of a field name to generate an HTML-encoded difference display
        // using finediff: https://github.com/gorhill/PHP-FineDiff. The output
        // should then be displayed using a '%h' format.
        

        global $COURSE;

        $question->usesdiff = FALSE;  // HACK ALERT -- adding a new field to question
        if (isset($question->resultcolumns) && $question->resultcolumns) {
            $resultcolumns = json_decode($question->resultcolumns);
        } elseif (get_config('qtype_coderunner', 'diff_check_enabled') &&
                (empty($question->grader) || $question->grader === 'EqualityGrader')) {
            $resultcolumns = json_decode(self::DIFF_RESULT_COLUMNS);
        } else {
            $resultcolumns = json_decode(self::SIMPLE_RESULT_COLUMNS);
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

        $table->head = array('');  // First column is tick or cross, like last column
        foreach ($resultcolumns as &$colspec) {
            $len = count($colspec);
            if ($len < 3) {
                $colspec[] = '%s';  // Add missing default format
            }
            $header = $colspec[0];
            $field = $colspec[1];  // Primary field - there may be more
            $num_non_blank = self::count_non_blanks($field, $testresults);
            if ($num_non_blank == 0) {
                $colspec[count($colspec) - 1] = '';  // Zap format to hide column
            } else {
                $table->head[] = $header;
            }
        }
        $table->head[] = '';  // Final tick/cross column
        
        // Process each row of the results table
        
        $tabledata = array();
        $testcasekeys = array_keys($testcases);  // Arbitrary numeric indices. Aarghhh.
        $i = 0;
        $rowclasses = array();
        foreach ($testresults as $testresult) {
            $rowclasses[$i] = $i % 2 == 0 ? 'r0' : 'r1';
            $testcase = $testcases[$testcasekeys[$i]];
            $testIsVisible = $this->should_display_result($testcase, $testresult);
            if ($canviewhidden || $testIsVisible) {
                $fraction = $testresult->awarded / $testresult->mark;
                $tickorcross = $this->feedback_image($fraction);
                $tablerow = array($tickorcross); // Tick or cross
                foreach ($resultcolumns as &$colspec) {
                    $len = count($colspec);
                    if (strpos($colspec[1], 'diff') !== FALSE) {
                        $question->usesdiff = TRUE;
                    }
                    $format = $colspec[$len - 1];
                    if ($format === '%h') {  // If it's an html format, use value directly
                        $value = self::restrict_qty($testresult->getvalue($colspec[1]));
                        $tablerow[] = self::clean_html($value);  
                    } else if ($format !== '') {  // Else if it's a non-null column
                        $args = array($format);
                        for ($j = 1; $j < $len - 1; $j++) {
                            $value = self::restrict_qty($testresult->getvalue($colspec[$j]));
                            $args[] = $value;
                        }
                        $content = call_user_func_array('sprintf', $args);
                        $tablerow[] = self::format_cell($content);
                    }
                }
                $tablerow[] = $tickorcross;
                $tabledata[] = $tablerow;
                if (!$testIsVisible) {
                    $rowclasses[$i] .= ' hidden-test';
                }
            }
            $i++;
            if ($testcase->hiderestiffail && !$testresult->iscorrect) {
                break;
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
    
    // Sanitise with 's()' and add line breaks to a given string
    private static function format_cell($cell) {
        return str_replace("\n", "<br />", str_replace(' ', '&nbsp;', s($cell)));
    }

    // Compute the HTML feedback summary for a given test outcome.
    // Should not be called if there were any syntax or sandbox errors, or if a
    // combinator-template grader was used. 
    private function build_feedback_summary($qa, $testcases, $testoutcome) {
        $question = $qa->get_question();
        $lines = array();  // List of lines of output
        $testresults = $testoutcome->testresults;
        $onlyhiddenfailed = FALSE;
        if (count($testresults) != count($testcases)) {
            $lines[] = get_string('aborted', 'qtype_coderunner');
        } else {
            $numerrors = $testoutcome->errorcount;
            $hiddenerrors = $this->count_hidden_errors($testresults, $testcases);
            if ($numerrors > 0) {
                if ($numerrors == $hiddenerrors) {
                    $onlyhiddenfailed = TRUE;
                    $lines[] = get_string('failedhidden', 'qtype_coderunner');
                }
                else if ($hiddenerrors > 0) {
                    $lines[] = get_string('morehidden', 'qtype_coderunner');
                }
            }
        }

        if ($testoutcome->all_correct()) {
            $lines[] = get_string('allok', 'qtype_coderunner') .
                        "&nbsp;" . $this->feedback_image(1.0);
        } else {
            if ($question->allornothing) {
                $lines[] = get_string('noerrorsallowed', 'qtype_coderunner');
            }
            if ($question->usesdiff && !$onlyhiddenfailed) {
                $lines[] = $this->diff_button($qa);
            }
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
    protected function format_examples($examples) {
        if ($this->all_single_line($examples) && ! self::FORCE_TABULAR_EXAMPLES) {
            return $this->format_examples_one_per_line($examples);
        }
        else {
            return $this->format_examples_as_table($examples);
        }
    }


    // Return true iff there is no standard input and all expectedoutput and shell
    // input cases are single line only
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
        $html = "<div>". $html . "</div>"; // Wrap it in a div (seems to help libxml)
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
            $text .=  $example->testcode . ' &rarr; ' . $example->expected;
            $text .= html_writer::empty_tag('br');
       }
       return $text;
    }


    private function format_examples_as_table($examples) {
        $table = new html_table();
        $table->attributes['class'] = 'coderunnerexamples';
        list($numStd, $numShell) = $this->count_bits($examples);
        $table->head = array();
        if ($numShell) {
            $table->head[] = 'Test';
        }
        if ($numStd) {
            $table->head[] = 'Input';
        }
        $table->head[] = 'Result';

        $tablerows = array();
        foreach ($examples as $example) {
            $row = array();
            if ($numShell) {
                $row[] = self::format_cell($example->testcode);
            }
            if ($numStd) {
                $row[] = self::format_cell($example->stdin);
            }
            $row[] = self::format_cell($example->expected);
            $tablerows[] = $row;
        }
        $table->data = $tablerows;
        return html_writer::table($table);
    }


    // Return a count of the number of non-empty stdins and non-empty shell
    // inputs in the given list of test result objects.
    private function count_bits($tests) {
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
    private function count_hidden_errors($testresults, $testcases) {
        $testcasekeys = array_keys($testcases);  // Arbitrary numeric indices. Aarghhh.
        $i = 0;
        $count = 0;
        $hidingrest = false;
        foreach ($testresults as $tr) {
            $testcase = $testcases[$testcasekeys[$i]];
            if ($hidingrest) {
                $isdisplayed = false;
            }
            else {
                $isdisplayed = $this->should_display_result($testcase, $tr);
            }
            if (!$isdisplayed && !$tr->iscorrect) {
                $count++;
            }
            if ($testcase->hiderestiffail && !$tr->iscorrect) {
                $hidingrest = true;
            }
            $i++;
        }
        return $count;
    }


    // True iff the given test result should be displayed
    private function should_display_result($testcase, $testresult) {
        return $testcase->display == 'SHOW' ||
            ($testcase->display == 'HIDE_IF_FAIL' && $testresult->iscorrect) ||
            ($testcase->display == 'HIDE_IF_SUCCEED' && !$testresult->iscorrect);
    }
    

    /* Support function to limit the size of a string for browser display.
     * Restricts line length to MAX_LINE_LENGTH and number of lines to
     * MAX_NUM_LINES.
     */
    private static function restrict_qty($s) {
        if (!is_string($s)) {  // It's a no-op for non-strings.
            return $s;
        }
        $result = '';
        $n = strlen($s);
        $line = '';
        $linelen = 0;
        $numlines = 0;
        for ($i = 0; $i < $n && $numlines < self::MAX_NUM_LINES; $i++) {
            if ($s[$i] != "\n") {
                if ($linelen < self::MAX_LINE_LENGTH) {
                    $line .= $s[$i];
                }
                else if ($linelen == self::MAX_LINE_LENGTH) {
                    $line[self::MAX_LINE_LENGTH - 1] = $line[self::MAX_LINE_LENGTH - 2] =
                    $line[self::MAX_LINE_LENGTH -3] = '.';
                }
                else {
                    /* ignore remainder of line */
                }
                $linelen++;
            }
            else {
                $result .= $line . "\n";
                $line = '';
                $linelen = 0;
                $numlines += 1;
                if ($numlines == self::MAX_NUM_LINES) {
                    $result .= "[... snip ...]\n";
                }
            }
        }
        return $result . $line;
    }


    // support function to count how many objects in the given list of objects
    // have the given 'field' attribute non-blank. Non-existent fields are also
    // included in order to generate a column showing the error, but null values

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

        $PAGE->requires->js_init_call('M.qtype_coderunner.initDiffButton', 
                array($attributes['id'], $attributes['value'],
                    get_string('hidedifferences', 'qtype_coderunner')));
        return $output;

    }

}
