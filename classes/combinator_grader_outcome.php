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

/** Defines a subclass of the normal coderunner testing_outcome for use when
 * a combinator template grader is used.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
use qtype_coderunner\constants;

class qtype_coderunner_combinator_grader_outcome extends qtype_coderunner_testing_outcome {

    public function __construct() {
        parent::__construct(1, 0);
        $this->feedbackhtml = null;
        $this->preludehtml = null;
        $this->testresults = null;
    }


    /**
     * Method to set the mark and the various feedback values (preludehtml,
     * testresults, feedbackhtml).
     * @param float $markfraction the mark in the range 0 - 1
     * @param array $feedback Associative array of attributes to add to the
     * outcome object, usually zero or more of preludehtml, results, feedbackhtml.
     */
    public function set_mark_and_feedback($markfraction, $feedback) {
        $this->actualmark = $markfraction;  // Combinators work in the range 0 - 1
        foreach ($feedback as $key => $value) {
            $this->$key = $value;
        }
    }


    // Generate the main feedback, consisting of (in order) any prelude HTML,
    // a table of results and any postlude html (called 'feedbackhtml' for
    // historic reasons).
    protected function build_results_table($question) {
        $fb = empty($this->preludehtml) ? '' : $this->preludehtml;
        if (!empty($this->testresults) && count($this->testresults) > 0) {
            $table = new html_table();
            $table->attributes['class'] = 'coderunner-test-results';
            $headers = $this->testresults[0];
            foreach ($headers as $header) {
                $table->head[] = strtolower($header) === 'correct' ? '' : $header;
            }

            $rowclasses = array();
            $tablerows = array();

            for ($i = 1; $i < count($this->testresults); $i++) {
                $cells = $this->testresults[$i];
                $rowclasses[] = $i % 2 == 0 ? 'r0' : 'r1';
                $tablerow = array();
                $j = 0;
                foreach ($cells as $cell) {
                    if (strtolower($headers[$j]) === 'correct') {
                        $markfrac = $cell ? 1.0 : 0.0;
                        $tablerow[] = $this->renderer->get_feedback_image($markfrac);
                    } else {
                        $tablerow[] = qtype_coderunner_util::format_cell($cell);
                    }
                    $j++;
                }
                $tablerows[] = $tablerow;
            }
            $table->data = $tablerows;
            $table->rowclasses = $rowclasses;
            $fb .= html_writer::table($table);

        }
        if (!empty($this->feedbackhtml)) {
            $fb .= $this->feedbackhtml;
        }
        return $fb;
    }


    // Compute the HTML feedback summary for this test outcome.
    // Should not be called if there were any syntax or sandbox errors.
    protected function build_feedback_summary($qa) {
        $isprecheck = $qa->get_last_behaviour_var('_precheck', 0);
        $lines = array();  // List of lines of output.

        if ($this->all_correct() && !$isprecheck) {
            $lines[] = get_string('allok', 'qtype_coderunner') .
                    "&nbsp;" . $this->renderer->get_feedback_image(1.0);
        }

        return qtype_coderunner_util::make_html_para($lines);
    }

}
