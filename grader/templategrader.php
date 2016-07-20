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

/** The qtype_coderunner_per_test_template_grader class.
 *  This is a dummy grader that takes the output
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

defined('MOODLE_INTERNAL') || die();

require_once('graderbase.php');

class qtype_coderunner_template_grader extends qtype_coderunner_grader {

    /** Called to grade the output from a given testcase run when
     *  the template was used to generate a program that does both the test
     *  execution and the grading of the result.
     *  Returns a single TestResult object.
     *  Should not be called if the execution failed (syntax error, exception
     *  etc).
     */
    function grade_known_good(&$output, &$testcase) {
        $result = json_decode($output);
        if ($result === null || !isset($result->fraction) || !is_numeric($result->fraction)) {
            $errormessage = "Bad grading result from template:'" . $output . "'";
            $outcome = new qtype_coderunner_test_result(
                    qtype_coderunner_grader::tidy($testcase->testcode),
                    $testcase->mark,
                    false,
                    0.0,
                    qtype_coderunner_grader::tidy($testcase->expected),
                    $errormessage,
                    qtype_coderunner_grader::tidy($testcase->stdin),
                    qtype_coderunner_grader::tidy($testcase->extra)
            );
        } else {
            // First copy any missing fields from test case into result
            foreach (get_object_vars($testcase) as $key => $value) {
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
            $result->iscorrect =  abs($result->fraction - 1.0) < 0.000001;

            $outcome = new qtype_coderunner_test_result(
                qtype_coderunner_grader::tidy($result->testcode),
                $result->mark,
                $result->iscorrect,
                $result->awarded,
                qtype_coderunner_grader::tidy($result->expected),
                qtype_coderunner_grader::tidy($result->got),
                qtype_coderunner_grader::tidy($result->stdin),
                qtype_coderunner_grader::tidy($result->extra)
            );
            
            /* To accommodate generalised template graders that need to
             * have their own custom attributes, we also add any other result
             * attributes not already used into the TestResult object.
             */
            foreach ((array) $result as $key => $value) {
                if (!isset($outcome->$key)) {
                    $outcome->$key = $value;
                }
            }
            
        }
        return $outcome;
    }
}

