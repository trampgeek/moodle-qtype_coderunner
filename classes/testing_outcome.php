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

/** Defines a testing_outcome class which contains the complete set of
 *  results from running all the tests on a particular submission.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
use qtype_coderunner\constants;

class qtype_coderunner_testing_outcome {
    const STATUS_VALID = 1;         // A full set of test results is returned.
    const STATUS_SYNTAX_ERROR = 2;  // The code (on any one test) didn't compile.
    const STATUS_BAD_COMBINATOR = 3; // A combinator template yielded an invalid result
    const STATUS_SANDBOX_ERROR = 4;  // The run failed altogether.

    const TOLERANCE = 0.00001;       // Allowable difference between actual and max marks for a correct outcome.

    const RESULT_COLUMNS = '[["Test", "testcode"], ["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]]';

    public $status;                  // One of the STATUS_ constants above.
                                     // If this is not 1, subsequent fields may not be meaningful.
    public $errorcount;              // The number of failing test cases.
    public $errormessage;            // The error message to display if there are errors.
    public $maxpossmark;             // The maximum possible mark.
    public $actualmark;              // Actual mark (meaningful only if this is not an all-or-nothing question).
    public $testresults;             // An array of TestResult objects.
    public $sourcecodelist;          // Array of all test runs.

    public function __construct(
            $maxpossmark,
            $numtestsexpected,
            $status = self::STATUS_VALID,
            $errormessage = '') {

        $this->status = $status;
        $this->errormessage = $errormessage;
        $this->errorcount = 0;
        $this->actualmark = 0;
        $this->maxpossmark = $maxpossmark;
        $this->numtestsexpected = $numtestsexpected;
        $this->testresults = array();
        $this->sourcecodelist = null;     // Array of all test runs on the sandbox.
    }

    public function set_status($status, $errormessage='') {
        $this->status = $status;
        $this->errormessage = $errormessage;
    }

    public function run_failed() {
        return $this->status === self::STATUS_SANDBOX_ERROR;
    }

    public function has_syntax_error() {
        return $this->status === self::STATUS_SYNTAX_ERROR;
    }

    public function combinator_error() {
        return $this->status === self::STATUS_BAD_COMBINATOR;
    }

    public function is_ungradable() {
        return $this->run_failed() || $this->combinator_error();
    }

    public function mark_as_fraction() {
        // Need to return exactly 1.0 for a right answer.
        $fraction = $this->actualmark / $this->maxpossmark;
        return abs($fraction - 1.0) < self::TOLERANCE ? 1.0 : $fraction;
    }

    public function all_correct() {
        return $this->mark_as_fraction() === 1.0;
    }

    // True if the number of tests does not equal the number originally
    // expected, meaning that testing was aborted.
    public function was_aborted() {
        return count($this->testresults) != $this->numtestsexpected;
    }


    public function add_test_result($tr) {
        $this->testresults[] = $tr;
        $this->actualmark += $tr->awarded;
        if (!$tr->iscorrect) {
            $this->errorcount++;
        }
    }


    /**
     *
     * @param qtype_renderer $renderer The renderer being used to display this
     * testing outcome.
     */
    public function set_renderer(qtype_renderer $renderer) {
        $this->renderer = $renderer;
    }


    /*********************************************************************
     *
     * Methods to format various aspects of the outcome as HTML for display.
     *
     *********************************************************************/

    public function html_feedback(question_attempt $qa) {
        $resultsclass = $this->results_class();
        $question = $qa->get_question();
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        if ($isprecheck) {
            $resultsclass .= ' precheck';
        }

        $fb = '';
        $q = $qa->get_question();

        if ($q->showsource) {
            $fb .= $this->make_source_code_div();
        }

        $fb .= html_writer::start_tag('div', array('class' => $resultsclass));
        if ($this->run_failed()) {
            $fb .= html_writer::tag('h3', get_string('run_failed', 'qtype_coderunner'));;
            $fb .= html_writer::tag('p', s($this->errormessage),
                    array('class' => 'run_failed_error'));
        } else if ($this->has_syntax_error()) {
            $fb .= html_writer::tag('h3', get_string('syntax_errors', 'qtype_coderunner'));
            $fb .= html_writer::tag('pre', s($this->errormessage),
                    array('class' => 'pre_syntax_error'));
        } else if ($this->combinator_error()) {
            $fb .= html_writer::tag('h3', get_string('badquestion', 'qtype_coderunner'));
            $fb .= html_writer::tag('pre', s($this->errormessage),
                    array('class' => 'pre_question_error'));

        } else {

            // The run was successful. Display results.
            if ($isprecheck) {
                $fb .= html_writer::tag('h3', get_string('precheck_only', 'qtype_coderunner'));
            }

            $results = $this->build_results_table($q);
            if ($results != null) {
                $fb .= $results;
            }

        }

        // Summarise the status of the response in a paragraph at the end.
        // Suppress when previous errors have already said enough.
        if (!$this->has_syntax_error() &&
             !$this->is_ungradable() &&
             !$this->run_failed()) {
            $fb .= $this->build_feedback_summary($qa);
        }
        $fb .= html_writer::end_tag('div');

        return $fb;
    }


    // Return a table of results or null if there are no results to show.
    protected function build_results_table($question) {
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
            $numnonblank = self::count_non_blanks($field, $this->testresults);
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
        foreach ($this->testresults as $testresult) {
            $rowclasses[$i] = $i % 2 == 0 ? 'r0' : 'r1';
            $testisvisibile = $this->should_display_result($testresult) && !$hidingrest;
            if ($canviewhidden || $testisvisibile) {
                $fraction = $testresult->awarded / $testresult->mark;
                $tickorcross = $this->renderer->get_feedback_image($fraction);
                $tablerow = array($tickorcross); // Tick or cross.
                foreach ($resultcolumns as &$colspec) {
                    $len = count($colspec);
                    $format = $colspec[$len - 1];
                    if ($format === '%h') {  // If it's an html format, use value directly.
                        $value = $testresult->gettrimmedvalue($colspec[1]);
                        $tablerow[] = qtype_coderunner_util::clean_html($value);
                    } else if ($format !== '') {  // Else if it's a non-null column.
                        $args = array($format);
                        for ($j = 1; $j < $len - 1; $j++) {
                            $value = $testresult->gettrimmedvalue($colspec[$j]);
                            $args[] = $value;
                        }
                        $content = call_user_func_array('sprintf', $args);
                        $tablerow[] = qtype_coderunner_util::format_cell($content);
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


    // Compute the HTML feedback summary for this test outcome.
    // Should not be called if there were any syntax or sandbox errors.
    protected function build_feedback_summary($qa) {
        $question = $qa->get_question();
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        $lines = array();  // List of lines of output.

        $onlyhiddenfailed = false;
        if ($this->was_aborted()) {
            $lines[] = get_string('aborted', 'qtype_coderunner');
        } else {

            $hiddenerrors = $this->count_hidden_errors();
            if ($this->errorcount > 0) {
                if ($this->errorcount == $hiddenerrors) {
                    $onlyhiddenfailed = true;
                    $lines[] = get_string('failedhidden', 'qtype_coderunner');
                } else if ($hiddenerrors > 0) {
                    $lines[] = get_string('morehidden', 'qtype_coderunner');
                }
            }
        }

        if ($this->all_correct()) {
            if (!$isprecheck) {
                $lines[] = get_string('allok', 'qtype_coderunner') .
                        "&nbsp;" . $this->renderer->get_feedback_image(1.0);
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


    // Build and return an HTML div section containing a list of template
    // outputs used as source code.
    protected function make_source_code_div() {
        $html = '';
        if (count($this->sourcecodelist) > 0) {
            $heading = get_string('sourcecodeallruns', 'qtype_coderunner');
            $html = html_writer::start_tag('div', array('class' => 'debugging'));
            $html .= html_writer::tag('h3', $heading);
            $i = 1;
            foreach ($this->sourcecodelist as $run) {
                $html .= html_writer::tag('h4', "Run $i");
                $i++;
                $html .= html_writer::tag('pre', s($run));
                $html .= html_writer::tag('hr', '');
            }
            $html .= html_writer::end_tag('div');
        }
        return $html;
    }

    // The results class for this outcome
    protected function results_class() {
        if ($this->all_correct()) {
            $resultsclass = "coderunner-test-results good";
        } else if ($this->mark_as_fraction() > 0) {
            $resultsclass = 'coderunner-test-results partial';
        } else {
            $resultsclass = "coderunner-test-results bad";
        }
        return $resultsclass;
    }


    // Count the number of errors in hidden testcases, given the array of
    // testresults.
    protected function count_hidden_errors() {
        $count = 0;
        $hidingrest = false;
        foreach ($this->testresults as $tr) {
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
    protected function should_display_result($testresult) {
        return !isset($testresult->display) ||  // e.g. broken combinator template
             $testresult->display == 'SHOW' ||
            ($testresult->display == 'HIDE_IF_FAIL' && $testresult->iscorrect) ||
            ($testresult->display == 'HIDE_IF_SUCCEED' && !$testresult->iscorrect);
    }


    // Support function to count how many objects in the given list of objects
    // have the given 'field' attribute non-blank. Non-existent fields are also
    // included in order to generate a column showing the error, but null values.

    protected static function count_non_blanks($field, $objects) {
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
