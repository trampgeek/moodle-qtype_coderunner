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
class qtype_coderunner_testing_outcome {
    const STATUS_VALID = 1;         // A full set of test results is returned
    const STATUS_SYNTAX_ERROR = 2;  // The code (on any one test) didn't compile
    const STATUS_COMBINATOR_TEMPLATE_GRADER = 3;  // This is a combinator-template-grading result
    const STATUS_SANDBOX_ERROR = 4;  // The run failed altogether
    const TOLERANCE = 0.00001;    // Allowable difference between actual and max marks for a correct outcome

    public $status;                  // One of the STATUS_ constants above
                                     // If this is not 1, subsequent fields may not be meaningful
    public $errorcount;              // The number of failing test cases
    public $maxpossmark;             // The maximum possible mark
    public $runhost;                 // Host name of the front-end on which the run was done
    public $actualmark;              // Actual mark (meaningful only if this is not an allornothing question)
    public $testresults;             // An array of TestResult objects
    public $sourcecodelist;          // Array of all test runs
    public $gradercodelist;          // Array of source code of all grader runs
    public $feedbackhtml;           // Feedback defined by combinator-template-grader (subsumes testResults)

    public function __construct(
            $maxpossmark,
            $status = self::STATUS_VALID,
            $errormessage = '') {

        $this->status = $status;
        $this->errorMessage = $errormessage;
        $this->errorcount = 0;
        $this->actualmark = 0;
        $this->maxpossmark = $maxpossmark;
        $this->runhost = php_uname('n');  // Useful for debugging with multiple front-ends
        $this->testresults = array();
        $this->sourcecodelist = null;     // Array of all test runs on the sandbox
        $this->gradercodelist = null;    // Array of all grader runs on the sandbox
        $this->feedbackhtml = null;     // Used only by combinator template grader
    }
    
    
    public function run_failed() {
        return $this->status === self::STATUS_SANDBOX_ERROR;
    }

    
    public function has_syntax_error()  {
        return $this->status === self::STATUS_SYNTAX_ERROR;
    }


    public function all_correct() {
        return $this->status !== self::STATUS_SYNTAX_ERROR && 
               $this->status !== self::STATUS_SANDBOX_ERROR &&
               $this->errorcount == 0;
    }

    public function mark_as_fraction() {
        // Need to ensure return exactly 1.0 for a right answer
        if ($this->has_syntax_error()) {
            return 0.0;
        }
        else {
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
    // NB: there may be other attributes added by the template grader
    var $testcode;          // The test that was run (trimmed, snipped)
    var $iscorrect;         // True iff test passed fully (100%)
    var $expected;          // Expected output (trimmed, snipped)
    var $mark;              // The max mark awardable for this test
    var $awarded;           // The mark actually awarded.
    var $got;               // What the student's code gave (trimmed, snipped)
    var $stdin;             // The standard input data (trimmed, snipped)
    var $extra;             // Extra data for use by some templates

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
}

?>
