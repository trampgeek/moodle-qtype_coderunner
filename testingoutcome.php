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

/** Defines classes involved in reporting on the result of testing a student's
 *  answer code with a given set of testCases and grading the result.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The outcome from testing a question against all test cases.
// All fields currently public as changing them to private breaks the
// deserialisation of all current question attempt records in the database
// and I don't feel strongly enough about it to try to fix that. Think Python!

// When a combinator-template grader is used, there is no concept of per-test
// case results, so there are no individual testResults and the feedback_html
// field is defined instead.

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
    public $runhost;                 // Host name of the front-end on which the run was done.
    public $actualmark;              // Actual mark (meaningful only if this is not an allornothing question).
    public $testresults;             // An array of TestResult objects.
    public $sourcecodelist;          // Array of all test runs.
    public $gradercodelist;          // Array of source code of all grader runs.
    public $feedbackhtml;            // Feedback defined by combinator-template-grader (subsumes testResults).

    public function __construct(
            $maxpossmark,
            $status = self::STATUS_VALID,
            $errormessage = '') {

        $this->status = $status;
        $this->errormessage = $errormessage;
        $this->errorcount = 0;
        $this->actualmark = 0;
        $this->maxpossmark = $maxpossmark;
        $this->runhost = php_uname('n');  // Useful for debugging with multiple front-ends.
        $this->testresults = array();
        $this->sourcecodelist = null;     // Array of all test runs on the sandbox.
        $this->gradercodelist = null;    // Array of all grader runs on the sandbox.
        $this->feedbackhtml = null;     // Used only by combinator template grader.
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


class qtype_coderunner_test_result {

    const MAX_LINE_LENGTH = 100;
    const MAX_NUM_LINES = 200;

    // NB: there may be other attributes added by the template grader.
    public $testcode;          // The test that was run (trimmed, snipped).
    public $iscorrect;         // True iff test passed fully (100%).
    public $expected;          // Expected output (trimmed, snipped).
    public $mark;              // The max mark awardable for this test.
    public $awarded;           // The mark actually awarded.
    public $got;               // What the student's code gave (trimmed, snipped).
    public $stdin;             // The standard input data (trimmed, snipped).
    public $extra;             // Extra data for use by some templates.

    public function __construct($test, $mark, $iscorrect, $awardedmark, $expected, $got, $stdin=null, $extra=null) {
        $this->testcode = $test;
        $this->mark = $mark;
        $this->iscorrect = $iscorrect;
        $this->awarded = $awardedmark;
        $this->expected = $expected;
        $this->got = $got;
        $this->stdin = $stdin;
        $this->extra = $extra;
    }


    // Return the value from this testresult as specified by the given
    // $fieldspecifier, which is either a fieldname within the test result
    // or an expression of the form diff(fieldspec1, fieldspec2). Both forms
    // now return the same result, namely the fieldspecifier or fieldspec1
    // in the diff case. The diff variant is obsolete - it was
    // used to provide a Show Differences button but that functionality is
    // now provided in JavaScript.
    public function gettrimmedvalue($fieldspecifier) {
        $matches = array();
        if (preg_match('|diff\((\w+), ?(\w+)\)|', $fieldspecifier, $matches)) {
            $fieldspecifier = $matches[1];
        }
        if (property_exists($this, $fieldspecifier)) {
            $value = $this->$fieldspecifier;
        } else {
            $value = "Unknown field '$fieldspecifier'";
        }
        return $value;
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
                } else if ($linelen == self::MAX_LINE_LENGTH) {
                    for ($j = 1; $j <= 3; $j++) {
                        $line[self::MAX_LINE_LENGTH - $j] = '.'; // Insert '...'.
                    }
                } // else { ...  ignore remainder of line ... }
                $linelen++;
            } else { // Newline.
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
}


