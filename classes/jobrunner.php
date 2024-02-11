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
/*
 * @package    qtype_coderunner
 * @copyright  2016 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

use qtype_coderunner\constants;

// The qtype_coderunner_jobrunner class contains all code concerned with running a question
// in the sandbox and grading the result.
class qtype_coderunner_jobrunner {
    private $grader = null;          // The grader instance, if it's NOT a custom one.
    private $sandbox = null;         // The sandbox we're using.
    private $code = null;            // The code we're running.
    private $files = null;           // The files to be loaded into the working dir.
    private $question = null;        // The question that we're running code for.
    private $testcases = null;       // The testcases (a subset of those in the question).
    private $allruns = null;         // Array of the source code for all runs.

    /** @var ?array Array of sandbox params. */
    private $sandboxparams = null;

    /** @var ?string Language used to run the code. */
    private $language = null;

    /** @var ?array An associative array of template params.*/
    private $templateparams = null;

    /** @var bool True if this grading is occurring because the student clicked the precheck button. */
    private $isprecheck = false;

    // Check the correctness of a student's code and possible extra attachments
    // as an answer to the given
    // question and and a given set of test cases (which may be empty or a
    // subset of the question's set of testcases. $isprecheck is true if
    // this is a run triggered by the student clicking the Precheck button.
    // $answerlanguage will be the empty string except for multilanguage questions,
    // when it is the language selected in the language drop-down menu.
    // Returns a TestingOutcome object.
    public function run_tests($question, $code, $attachments, $testcases, $isprecheck, $answerlanguage) {
        if (empty($question->prototype)) {
            // Missing prototype. We can't run this question.
            $outcome = new qtype_coderunner_testing_outcome(0, 0, false);
            if ($question->prototypetype != 0) {
                $message = get_string('cannotrunprototype', 'qtype_coderunner');
            } else {
                $message = get_string(
                    'missingprototypewhenrunning',
                    'qtype_coderunner',
                    ['crtype' => $question->coderunnertype]
                );
            }
            $outcome->set_status(qtype_coderunner_testing_outcome::STATUS_MISSING_PROTOTYPE, $message);
            return $outcome;
        }

        // Extract the code from JSON if this is a Scratchpad UI or similar.
        // Note that this breaks the old python3_scratchpad question type
        // where the template expects the full JSON string to be presented as
        // the value of STUDENT_ANSWER. The fix is simply to change the template
        // to use the value of STUDENT_ANSWER as given, rather than trying
        // to extract the student answer itself.
        if ($question->extractcodefromjson) {
            $json = json_decode($code, true);
            if ($json !== null && isset($json[constants::ANSWER_CODE_KEY])) {
                $code = $json[constants::ANSWER_CODE_KEY][0];
            }
        }

        $this->question = $question;
        $this->code = $code;
        $this->testcases = array_values($testcases);
        $this->isprecheck = $isprecheck;
        $this->grader = $question->get_grader();
        $this->sandbox = $question->get_sandbox();
        $this->files = array_merge($attachments, $question->get_files());
        $attachedfilenames = implode(',', array_keys($attachments));
        $this->sandboxparams = $question->get_sandbox_params();
        $this->language = $question->get_language();

        $this->allruns = [];
        $this->templateparams = [
            'STUDENT_ANSWER' => $code,
            'ESCAPED_STUDENT_ANSWER' => qtype_coderunner_escapers::python(null, $code, null), // LEGACY SUPPORT.
            'MATLAB_ESCAPED_STUDENT_ANSWER' => qtype_coderunner_escapers::matlab(null, $code, null), // LEGACY SUPPORT.
            'IS_PRECHECK' => $isprecheck ? "1" : "0",
            'ANSWER_LANGUAGE' => $answerlanguage,
            'ATTACHMENTS' => $attachedfilenames,
         ];

        if (
            $question->get_is_combinator() &&
                ($this->has_no_stdins() || $question->allow_multiple_stdins() ||
                $this->grader->name() === 'TemplateGrader')
        ) {
            $outcome = $this->run_combinator($isprecheck);
        } else {
            $outcome = null;
        }

        // If that failed for any reason (e.g. timeout or signal), or if the
        // template isn't a combinator, run the tests individually. Any compilation
        // errors or stderr output in individual tests bomb the whole test process,
        // but otherwise we should finish with a TestingOutcome object containing
        // a test result for each test case.

        if ($outcome == null) {
            $outcome = $this->run_tests_singly($isprecheck);
        }

        $this->sandbox->close();
        if ($question->get_show_source()) {
            $outcome->sourcecodelist = $this->allruns;
        }
        return $outcome;
    }


    // If the template is a combinator, try running all the tests in a single
    // go.
    //
    // Special template parameters are STUDENT_ANSWER, the raw submitted code,
    // IS_PRECHECK, which is true if this is a precheck run, TESTCASES,
    // a list of all the test cases and QUESTION, the original question object.
    // Return the testing outcome object if successful else null.
    private function run_combinator($isprecheck) {
        $numtests = count($this->testcases);
        $this->templateparams['TESTCASES'] = $this->testcases;
        $maxmark = $this->maximum_possible_mark();
        $outcome = new qtype_coderunner_testing_outcome($maxmark, $numtests, $isprecheck);
        $question = $this->question;
        try {
            $testprog = $question->twig_expand($question->template, $this->templateparams);
        } catch (Exception $e) {
            $outcome->set_status(
                qtype_coderunner_testing_outcome::STATUS_SYNTAX_ERROR,
                get_string('templateerror', 'qtype_coderunner') . ': ' . $e->getMessage()
            );
            return $outcome;
        }

        $this->allruns[] = $testprog;
        $run = $this->sandbox->execute(
            $testprog,
            $this->language,
            null,
            $this->files,
            $this->sandboxparams
        );

        // If it's a template grader, we pass the result to the
        // do_combinator_grading method. Otherwise we deal with syntax errors or
        // a successful result without accompanying stderr.
        // In all other cases (runtime error etc) we give up
        // on the combinator.

        if ($run->error !== qtype_coderunner_sandbox::OK) {
            $outcome->set_status(
                qtype_coderunner_testing_outcome::STATUS_SANDBOX_ERROR,
                qtype_coderunner_sandbox::error_string($run)
            );
        } else if ($this->grader->name() === 'TemplateGrader') {
            $outcome = $this->do_combinator_grading($run, $isprecheck);
        } else if ($run->result === qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR) {
            $outcome->set_status(
                qtype_coderunner_testing_outcome::STATUS_SYNTAX_ERROR,
                $run->cmpinfo
            );
        } else if ($run->result === qtype_coderunner_sandbox::RESULT_SUCCESS) {
            $outputs = preg_split($this->question->get_test_splitter_re(), $run->output);
            if (count($outputs) === $numtests) {
                $i = 0;
                foreach ($this->testcases as $testcase) {
                    $outcome->add_test_result($this->grade($outputs[$i], $testcase));
                    $i++;
                }
            } else {  // Error: wrong number of tests after splitting.
                $error = get_string(
                    'brokencombinator',
                    'qtype_coderunner',
                    ['numtests' => $numtests, 'numresults' => count($outputs)]
                );
                $outcome->set_status(qtype_coderunner_testing_outcome::STATUS_BAD_COMBINATOR, $error);
            }
        } else {
            $outcome = null; // Something broke badly.
        }
        if ($outcome && isset($run->sandboxinfo)) {
            $outcome->add_sandbox_info($run->sandboxinfo);
        }
        return $outcome;
    }


    // Run all tests one-by-one on the sandbox.
    private function run_tests_singly($isprecheck) {
        $maxmark = $this->maximum_possible_mark($this->testcases);
        if ($maxmark == 0) {
            $maxmark = 1; // Something silly is happening. Probably running a prototype with no tests.
        }
        $numtests = count($this->testcases);
        $outcome = new qtype_coderunner_testing_outcome($maxmark, $numtests, $isprecheck);
        $question = $this->question;
        foreach ($this->testcases as $testcase) {
            if ($this->question->iscombinatortemplate) {
                $this->templateparams['TESTCASES'] = [$testcase];
            } else {
                $this->templateparams['TEST'] = $testcase;
            }
            try {
                $testprog = $question->twig_expand($question->template, $this->templateparams);
            } catch (Exception $e) {
                $outcome->set_status(
                    qtype_coderunner_testing_outcome::STATUS_SYNTAX_ERROR,
                    'TEMPLATE ERROR: ' . $e->getMessage()
                );
                break;
            }

            $input = isset($testcase->stdin) ? $testcase->stdin : '';
            $this->allruns[] = $testprog;
            $run = $this->sandbox->execute(
                $testprog,
                $this->language,
                $input,
                $this->files,
                $this->sandboxparams
            );
            if (isset($run->sandboxinfo)) {
                $outcome->add_sandbox_info($run->sandboxinfo);
            }
            if ($run->error !== qtype_coderunner_sandbox::OK) {
                $outcome->set_status(
                    qtype_coderunner_testing_outcome::STATUS_SANDBOX_ERROR,
                    qtype_coderunner_sandbox::error_string($run)
                );
                break;
            } else if ($run->result === qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR) {
                $outcome->set_status(
                    qtype_coderunner_testing_outcome::STATUS_SYNTAX_ERROR,
                    $run->cmpinfo
                );
                break;
            } else if ($run->result != qtype_coderunner_sandbox::RESULT_SUCCESS) {
                $errormessage = $this->make_error_message($run);
                $iserror = true;
                $outcome->add_test_result($this->grade($errormessage, $testcase, $iserror));
                break;
            } else {
                $testresult = $this->grade($run->output, $testcase);
                $aborting = false;
                if (isset($testresult->abort) && $testresult->abort) { // Templategrader abort request?
                    $testresult->awarded = 0;  // Mark it wrong regardless.
                    $testresult->iscorrect = false;
                    $aborting = true;
                }
                $outcome->add_test_result($testresult);
                if ($aborting) {
                    break;
                }
            }
        }
        return $outcome;
    }

    // Grade a given test result by calling the grader.
    private function grade($output, $testcase, $isbad = false) {
        return $this->grader->grade($output, $testcase, $isbad);
    }

    /**
     * Given the result of a sandbox run with the combinator template,
     * build and return a testingOutcome object with a status of
     * STATUS_COMBINATOR_TEMPLATE_GRADER and attributes of prelude and/or
     * and/or testresults and/or epiloguehtml.
     *
     * @param int $maxmark The maximum mark for this question
     * @param JSON $run The JSON-encoded output from the run.
     * @return \qtype_coderunner_testing_outcome the outcome object ready
     * for display by the renderer. This will have an actualmark and zero or more of
     * prologuehtml, testresults and epiloguehtml. The last three are: some
     * html for display before the result table, the test results table (an
     * array of pseudo-test_result objects) and some html for display after
     * the result table.
     */
    private function do_combinator_grading($run, $isprecheck) {
        $outcome = new qtype_coderunner_combinator_grader_outcome($isprecheck);
        try {
            if ($run->result !== qtype_coderunner_sandbox::RESULT_SUCCESS) {
                $resulterror = qtype_coderunner_sandbox::result_string($run->result);
                $error = get_string(
                    'brokentemplategrader',
                    'qtype_coderunner',
                    ['output' => "\nRun result: $resulterror" . "\nOutput: " .
                    $run->cmpinfo . "\n" . $run->output . "\n" . $run->stderr]
                );
                throw new Exception($error);
            }

            $result = json_decode($run->output);
            if ($result === null) {
                $error = get_string(
                    'badjson',
                    'qtype_coderunner',
                    ['output' => $run->output]
                );
                throw new Exception($error);
            }

            if (isset($result->showoutputonly) && $result->showoutputonly) {
                $outcome->set_output_only();
            } else if ($this->missing_or_bad_fraction($result)) {
                $error = get_string(
                    'missingorbadfraction',
                    'qtype_coderunner',
                    ['output' => $run->output]
                );
                throw new Exception($error);
            }

            // A successful combinator run (so far).
            $fract = $outcome->is_output_only() ? 1.0 : $result->fraction;
            $feedback = [];
            if (isset($result->feedback_html)) {  // Legacy combinator grader?
                $result->feedbackhtml = $result->feedback_html; // Change to modern version.
                unset($result->feedback_html);
            }
            foreach ($result as $key => $value) {
                if (!in_array($key, $outcome->allowedfields)) {
                    $error = get_string(
                        'unknowncombinatorgraderfield',
                        'qtype_coderunner',
                        ['fieldname' => $key]
                    );
                    throw new Exception($error);
                }
                if ($key === 'feedbackhtml' || $key === 'feedback_html') {
                    // For compatibility with older combinator graders.
                    $feedback['epiloguehtml'] = $result->$key;
                } else {
                    $feedback[$key] = $value;
                }
            }
            $outcome->set_mark_and_feedback($fract, $feedback);  // Further valididty checks done in here.
        } catch (Exception $except) {
            $outcome->set_status(qtype_coderunner_testing_outcome::STATUS_BAD_COMBINATOR, $except->getMessage());
        }
        return $outcome;
    }


    /* Check for missing or bad fraction.
     * @return bool true iff the fraction is missing or bad.
     */
    private function missing_or_bad_fraction($result) {
        return !isset($result->fraction) ||
               !is_numeric($result->fraction) ||
               floatval($result->fraction) < 0.0 ||
               floatval($result->fraction) > 1.0;
    }


    /* Return a $sep-separated string of the non-empty elements
       of the array $strings. Similar to implode except empty strings
       are ignored. */
    private function merge($sep, $strings) {
        $s = '';
        foreach ($strings as $el) {
            if (trim($el ?? '')) {
                if ($s !== '') {
                    $s .= $sep;
                }
                $s .= $el;
            }
        }
        return $s;
    }


    // Return the maximum possible mark from the set of testcases we're running.
    private function maximum_possible_mark() {
        $total = 0;
        foreach ($this->testcases as $testcase) {
            $total += $testcase->mark;
        }
        if ($total == 0) {
            $total = 1; // Something silly is happening. Probably running a prototype with no tests.
        }
        return $total;
    }


    private function make_error_message($run) {
        $err = "***" . qtype_coderunner_sandbox::result_string($run->result) . "***";
        if ($run->result === qtype_coderunner_sandbox::RESULT_RUNTIME_ERROR) {
            $sig = $run->signal;
            if ($sig) {
                $err .= " (signal $sig)";
            }
        }
        return $this->merge("\n", [$run->cmpinfo, $run->output, $err, $run->stderr]);
    }


    /** True IFF no testcases have nonempty stdin. */
    private function has_no_stdins() {
        foreach ($this->testcases as $testcase) {
            if ($testcase->stdin != '') {
                return false;
            }
        }
        return true;
    }
}
