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

require_once($CFG->dirroot . '/question/type/coderunner/testresult.php');

defined('MOODLE_INTERNAL') || die();

class qtype_coderunner_testing_outcome {
    const STATUS_VALID = 1;         // A full set of test results is returned.
    const STATUS_SYNTAX_ERROR = 2;  // The code (on any one test) didn't compile.
    const STATUS_COMBINATOR_TEMPLATE_GRADER = 3;  // This is a combinator-template-grading result.
    const STATUS_SANDBOX_ERROR = 4;  // The run failed altogether.
    const TOLERANCE = 0.00001;       // Allowable difference between actual and max marks for a correct outcome.

    public $status;                  // One of the STATUS_ constants above.
                                     // If this is not 1, subsequent fields may not be meaningful.
    public $errorcount;              // The number of failing test cases.
    public $errormessage;            // The error message to display if there are errors.
    public $maxpossmark;             // The maximum possible mark.
    public $actualmark;              // Actual mark (meaningful only if this is not an all-or-nothing question).
    public $testresults;             // An array of TestResult objects.
    public $sourcecodelist;          // Array of all test runs.
    public $feedbackhtml;            // Feedback defined by combinator-template-grader (subsumes testResults).

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
        $this->feedbackhtml = null;       // Used only by combinator template grader.
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


    public function all_correct() {
        return $this->status !== self::STATUS_SYNTAX_ERROR &&
               $this->status !== self::STATUS_SANDBOX_ERROR &&
               $this->errorcount == 0;
    }

    public function mark_as_fraction() {
        // Need to ensure return exactly 1.0 for a right answer.
        if ($this->has_syntax_error()) {
            return 0.0;
        } else {
            return $this->errorcount == 0 ? 1.0 : $this->actualmark / $this->maxpossmark;
        }
    }

    public function add_test_result($tr) {
        $this->testresults[] = $tr;
        $this->actualmark += $tr->awarded;
        if (!$tr->iscorrect) {
            $this->errorcount++;
        }
    }

    // True if the number of tests does not equal the number originally
    // expected, meaning that testing was aborted.
    public function was_aborted() {
        return count($this->testresults) != $this->numtestsexpected;
    }

    // Method used only by combinator template grader to set the mark and
    // feedback  html.
    public function set_mark_and_feedback($mark, $html) {
        $this->actualmark = $mark;
        $this->feedbackhtml = $html;
        if (abs($mark - $this->maxpossmark) > self::TOLERANCE) {
            $this->errorcount += 1;
        }
    }
}
