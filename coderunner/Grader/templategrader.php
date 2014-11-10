<?php
/** The TemplateGrader class. This is a dummy grader that takes the output
 *  from the test to be an actual grading result encoded as a JSON object.
 *  This is used when the per-test-template is set up to do the grading in
 *  addition to the actual test run (if such a thing is needed).
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('graderbase.php');
class TemplateGrader extends Grader {

    /** Called to grade the output from a given testcase run when
     *  the template was used to generate a program that does both the test
     *  execution and the grading of the result.
     *  Returns a single TestResult object.
     *  Should not be called if the execution failed (syntax error, exception
     *  etc).
     */
    function gradeKnownGood(&$output, &$testcase) {
        $result = json_decode($output);
        if ($result === NULL || !isset($result->fraction) || !is_numeric($result->fraction)) {
            $errorMessage = "Bad grading result from template:'" . $output . "'";
            $outcome = new TestResult(
                    Grader::tidy($testcase->testcode),
                    $testcase->mark,
                    FALSE,
                    0.0,
                    Grader::tidy($testcase->expected),
                    $errorMessage,
                    Grader::tidy($testcase->stdin),
                    Grader::tidy($testCase->extra)
            );
        } else {
            // First copy any missing fields from test case into result
            foreach (get_object_vars($testcase) as $key=>$value) {
                if (!isset($result->$key)) {
                    $result->$key = $value;
                }
            }
            if (!isset($result->awarded)) {
                $result->awarded = $result->mark * $result->fraction;
            }
            if (!isset($result->got)) {
                $result->got = '';
            }
            $result->isCorrect =  abs($result->fraction - 1.0) < 0.000001;

            $outcome = new TestResult(
                Grader::tidy($result->testcode),
                $result->mark,
                $result->isCorrect,
                $result->awarded,
                Grader::tidy($result->expected),
                Grader::tidy($result->got),
                Grader::tidy($result->stdin),
                Grader::tidy($result->extra)
            );
            
            /* To accommodate generalised template graders that need to
             * output their own HTML results, we also add any other result
             * attributes not already used into the TestResult object.
             */
            foreach ((array) $result as $key=>$value) {
                if ($key !== 'fraction' && !isset($outcome->$key)) {
                    $outcome->$key = $value;
                }
            }
            
        }
        return $outcome;
    }
}
?>
