<?php
/** Defines classes involved in reporting on the result of testing a student's answer
 *  code with a given set of testCases.
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
class TestingOutcome {
    const STATUS_VALID = 1;         // A full set of test results is returned
    const STATUS_SYNTAX_ERROR = 2;  // The code (on any one test) didn't compile

    public $status;                    // One of the STATUS_ constants above
                                       // If this is not 1, subsequent fields may not be meaningful
    public $errorCount;                // The number of failing test cases
    public $maxPossMark;               // The maximum possible mark
    public $runHost;                   // Host name of the front-end on which the run was done
    public $actualMark;                // Actual mark (meaningful only if this is not an all_or_nothing question)
    public $testResults;               // An array of TestResult objects

    public function __construct($status=TestingOutcome::STATUS_VALID, $errorMessage = '') {
        if ($status != TestingOutcome::STATUS_VALID &&
            $status != TestingOutcome::STATUS_SYNTAX_ERROR) {
            throw new CodingException('Bad parameter to TestingOutcome constructor');
        }
        $this->status = $status;
        $this->errorMessage = $errorMessage;
        $this->errorCount = 0;
        $this->actualMark = 0;
        $this->maxPossMark = 0;
        $this->runHost = php_uname('n');  // Useful for debugging with multiple front-ends
        $this->testResults = array();
        $this->sourceCodeList = null;
    }

    public function hasSyntaxError()  {
        return $this->status === TestingOutcome::STATUS_SYNTAX_ERROR;
    }


    public function allCorrect() {
        return $this->status === TestingOutcome::STATUS_VALID && $this->errorCount == 0;
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

    public function addTestResult($tr) {
        $this->testResults[] = $tr;
        $this->maxPossMark += $tr->mark;
        if ($tr->isCorrect) {
            $this->actualMark += $tr->mark;
        } else {
            $this->errorCount++;
        }
    }
}


class TestResult {
    var $isCorrect;                 // True iff test passed
    var $expected;                  // Expected output (trimmed, snipped)
    var $mark;
    var $got;                       // What the student's code gave

    public function __construct($mark, $isCorrect, $expected, $got) {
        $this->mark = $mark;
        $this->isCorrect = $isCorrect;
        $this->expected = $expected;
        $this->got = $got;
    }
}

?>
