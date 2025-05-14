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
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_template_grader extends qtype_coderunner_grader {
    public function name() {
        return "TemplateGrader";
    }

    /** Called to grade the output from a given run when
     *  the template was used to generate a program that does both the test
     *  execution and the grading of the result.
     *  Returns a single TestResult object.
     *  Should not be called if the execution failed (syntax error, exception
     *  etc).
     *  Should also not be called if the template is a combinator template
     *  as in that case an entire TestingOutcome needs to be built from the
     *  output rather than a single TestResult as for all normal graders.
     *  Construction of the TestingOutcome in the case of a combinator template
     *  grading is done by jobrunner.php.
     */
    protected function grade_known_good(&$output, &$testcase) {
        $result = json_decode($output);
        if ($result === null || !isset($result->fraction) || !is_numeric($result->fraction)) {
            if ($result === null) {
                $errorcode = 'brokentemplategrader';
            } else {
                $errorcode = 'missingorbadfraction';
            }
            $errormessage = get_string(
                $errorcode,
                'qtype_coderunner',
                ['output' => $output]
            );
            $testresultobj = new qtype_coderunner_test_result($testcase, false, 0.0, $errormessage);
        } else {
            $iscorrect = abs($result->fraction - 1.0) < 0.000001;
            $awarded = isset($result->awarded) ? $result->awarded : $testcase->mark * $result->fraction;
            $got = isset($result->got) ? $result->got : '';

            $testresultobj = new qtype_coderunner_test_result($testcase, $iscorrect, $awarded, $got);
            // Now allow any fields defined in the grader's result object to
            // override any of the corresponding attributes of the testresult object.
            foreach (get_object_vars($result) as $key => $value) {
                $testresultobj->$key = $value;
            }
        }
        return $testresultobj;
    }
}
