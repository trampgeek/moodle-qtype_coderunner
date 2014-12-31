<?php
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
class TestingOutcome {
    const STATUS_VALID = 1;         // A full set of test results is returned
    const STATUS_SYNTAX_ERROR = 2;  // The code (on any one test) didn't compile
    const STATUS_COMBINATOR_TEMPLATE_GRADER = 3;  // This is a combinator-template-grading result
    const STATUS_SANDBOX_ERROR = 4;  // The run failed altogether
    const TOLERANCE = 0.00001;    // Allowable difference between actual and max marks for a correct outcome

    public $status;                  // One of the STATUS_ constants above
                                     // If this is not 1, subsequent fields may not be meaningful
    public $errorCount;              // The number of failing test cases
    public $maxPossMark;             // The maximum possible mark
    public $runHost;                 // Host name of the front-end on which the run was done
    public $actualMark;              // Actual mark (meaningful only if this is not an all_or_nothing question)
    public $testResults;             // An array of TestResult objects
    public $sourcecodelist;          // Array of all test runs
    public $graderCodeList;          // Array of source code of all grader runs
    public $feedback_html;           // Feedback defined by combinator-template-grader (subsumes testResults)

    public function __construct(
            $maxPossMark,
            $status=TestingOutcome::STATUS_VALID,
            $errorMessage = '') {

        $this->status = $status;
        $this->errorMessage = $errorMessage;
        $this->errorCount = 0;
        $this->actualMark = 0;
        $this->maxPossMark = $maxPossMark;
        $this->runHost = php_uname('n');  // Useful for debugging with multiple front-ends
        $this->testResults = array();
        $this->sourcecodelist = null;     // Array of all test runs on the sandbox
        $this->graderCodeList = null;    // Array of all grader runs on the sandbox
        $this->feedback_html = null;     // Used only by combinator template grader
    }
    
    
    public function runFailed() {
        return $this->status === TestingOutcome::STATUS_SANDBOX_ERROR;
    }

    
    public function hasSyntaxError()  {
        return $this->status === TestingOutcome::STATUS_SYNTAX_ERROR;
    }


    public function allCorrect() {
        return $this->status !== TestingOutcome::STATUS_SYNTAX_ERROR && 
               $this->status !== TestingOutcome::STATUS_SANDBOX_ERROR &&
               $this->errorCount == 0;
    }

    public function markAsFraction() {
        // Need to ensure return exactly 1.0 for a right answer
        if ($this->hasSyntaxError()) {
            return 0.0;
        }
        else {
            return $this->errorCount == 0 ? 1.0 : $this->actualMark / $this->maxPossMark;
        }
    }

    public function add_test_result($tr) {
        $this->testResults[] = $tr;
        $this->actualMark += $tr->awarded;
        if (!$tr->iscorrect) {
            $this->errorCount++;
        }
    }
    
    // Method used only by combinator template grader to set the mark and 
    // feedback  html.
    public function set_mark_and_feedback($mark, $html) {
        $this->actualMark = $mark;
        $this->feedback_html = $html;
        if (abs($mark - $this->maxPossMark) > TestingOutcome::TOLERANCE) {
            $this->errorCount += 1;
        }
    }
}


class TestResult {
    // NB: there may be other attributes added by the template grader
    var $testcode;          // The test that was run (trimmed, snipped)
    var $iscorrect;         // True iff test passed fully (100%)
    var $expected;          // Expected output (trimmed, snipped)
    var $mark;              // The max mark awardable for this test
    var $awarded;           // The mark actually awarded.
    var $got;               // What the student's code gave (trimmed, snipped)
    var $stdin;             // The standard input data (trimmed, snipped)
    var $extra;             // Extra data for use by some templates

    public function __construct($test, $mark, $isCorrect, $awardedMark, $expected, $got, $stdin=NULL, $extra=NULL) {
        $this->testcode = $test;
        $this->mark = $mark;
        $this->iscorrect = $isCorrect;
        $this->awarded = $awardedMark;
        $this->expected = $expected;
        $this->got = $got;
        $this->stdin = $stdin;
        $this->extra = $extra;
    }
}

?>
