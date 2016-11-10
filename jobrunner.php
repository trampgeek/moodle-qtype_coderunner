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
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2016 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/coderunner/Twig/Autoloader.php');
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');
require_once($CFG->dirroot . '/question/type/coderunner/constants.php');
require_once($CFG->dirroot . '/question/type/coderunner/grader/graderbase.php');
require_once($CFG->dirroot . '/question/type/coderunner/sandbox/sandboxbase.php');
require_once($CFG->dirroot . '/question/type/coderunner/escapers.php');
require_once($CFG->dirroot . '/question/type/coderunner/testingoutcome.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');


// The qtype_coderunner_jobrunner class contains all code concerned with running a question
// in the sandbox and grading the result.
class qtype_coderunner_jobrunner {
    private $grader = null;              // The grader instance, if it's NOT a custom one.
    private $twig = null;                // The template processor environment.
    private $sandbox = null;             // The sandbox we're using.
    private $code = null;                // The code we're running
    private $question = null;            // The question that we're running code for
    private $testcases = null;           // The testcases (a subset of those in the question)
    private $allruns = null;             // Array of the source code for all runs.
    private $precheck = null;            // True if this is a precheck run

    // Check the correctness of a student's code as an answer to the given
    // question and and a given set of test cases (which may be empty or a
    // subset of the question's set of testcases. $isprecheck is true if
    // this is a run triggered by the student clicking the Precheck button.
    // Returns a TestingOutcome object.
    public function run_tests($question, $code, $testcases, $isprecheck) {
        global $CFG;

        $this->question = $question;
        $this->code = $code;
        $this->testcases = $testcases;
        $this->isprecheck = $isprecheck;

        Twig_Autoloader::register();
        $loader = new Twig_Loader_String();
        $this->twig = new Twig_Environment($loader, array(
            'debug' => true,
            'autoescape' => false,
            'optimizations' => 0
        ));

        $twigcore = $this->twig->getExtension('core');
        $twigcore->setEscaper('py', 'python_escaper');
        $twigcore->setEscaper('python', 'python_escaper');
        $twigcore->setEscaper('c',  'java_escaper');
        $twigcore->setEscaper('java', 'java_escaper');
        $twigcore->setEscaper('ml', 'matlab_escaper');
        $twigcore->setEscaper('matlab', 'matlab_escaper');

        $this->sandbox = $question->get_sandbox();
        $this->grader = $question->get_grader();
        $this->files = $question->get_files();
        $this->sandboxparams = $question->get_sandbox_params();
        $this->allruns = array();
        $this->templateparams = array(
            'STUDENT_ANSWER' => $code,
            'ESCAPED_STUDENT_ANSWER' => python_escaper(null, $code, null),
            'MATLAB_ESCAPED_STUDENT_ANSWER' => matlab_escaper(null, $code, null),
            'IS_PRECHECK' => $isprecheck,
            'QUESTION' => $question
         );

        $outcome = $this->run_with_combinator();

        // If that failed for any reason (e.g. no combinator template or timeout
        // or signal) run the tests individually. Any compilation
        // errors or abnormal terminations (not including signals) in individual
        // tests bomb the whole test process, but otherwise we should finish
        // with a TestingOutcome object containing a test result for each test
        // case.

        if ($outcome == null) {
            $outcome = $this->run_tests_singly();
        }

        $this->sandbox->close();
        if ($question->get_show_source()) {
            $outcome->sourcecodelist = $this->allruns;
        }
        return $outcome;
    }


    // Try running with the combinator template, which combines all tests into
    // a single sandbox run.
    // Only do this if the combinator
    // is enabled, there are no stdins and the grader is not a per-test-template
    // grader.
    // Special template parameters are STUDENT_ANSWER, the raw submitted code,
    // ESCAPED_STUDENT_ANSWER, the submitted code with all double quote chars escaped
    // (for use in a Python statement like s = """{{ESCAPED_STUDENT_ANSWER}}""" and
    // MATLAB_ESCAPED_STUDENT_ANSWER, a string for use in Matlab intended
    // to be used as s = sprintf('{{MATLAB_ESCAPED_STUDENT_ANSWER}}')
    // The escaped versions are deprecated: the use of a twig escaper is
    // preferred.
    // Return true if successful.
    // 26/5/14 - add entire QUESTION to template environment.
    private function run_with_combinator() {
        $combinator = $this->question->get_combinator();
        $iscombinatorgrader = $this->grader->name() === 'CombinatorTemplateGrader';
        $usecombinator = $iscombinatorgrader || (!empty($combinator) &&
                $this->has_no_stdins() &&
                $this->grader->name() !== 'TemplateGrader');
        if (!$usecombinator) {
            return null;  // Not our job.
        }

        // We're OK to use a combinator. Let's go.
        $this->templateparams['TESTCASES'] = $this->testcases;
        $outcome = null;
        $maxmark = $this->maximum_possible_mark();
        if ($maxmark == 0) {
            $maxmark = 1; // Must be a combinator template grader.
        }

        $testprog = $this->twig->render($combinator, $this->templateparams);

        $this->allruns[] = $testprog;
        $run = $this->sandbox->execute($testprog, $this->question->get_language(),
                null, $this->files, $this->sandboxparams);

        // If it's a combinator grader, we pass the result to the
        // do_combinator_grading method. Otherwise we deal with syntax errors or
        // a successful result without accompanying stderr.
        // In all other cases (runtime error etc) we give up
        // on the combinator.
        $numtests = count($this->testcases);
        if ($run->error !== qtype_coderunner_sandbox::OK) {
            $outcome = new qtype_coderunner_testing_outcome($maxmark,
                    $numtests,
                    qtype_coderunner_testing_outcome::STATUS_SANDBOX_ERROR,
                    qtype_coderunner_sandbox::error_string($run->error));
        } else if ($iscombinatorgrader) {
            $outcome = $this->do_combinator_grading($maxmark, $run);
        } else if ($run->result === qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR) {
            $outcome = new qtype_coderunner_testing_outcome($maxmark,
                    $numtests,
                    qtype_coderunner_testing_outcome::STATUS_SYNTAX_ERROR,
                    $run->cmpinfo);
        } else if ($run->result === qtype_coderunner_sandbox::RESULT_SUCCESS && !$run->stderr) {
            $outputs = preg_split($this->question->get_test_splitter_re(), $run->output);
            if (count($outputs) == count($this->testcases)) {
                $outcome = new qtype_coderunner_testing_outcome($maxmark, $numtests);
                $i = 0;
                foreach ($this->testcases as $testcase) {
                    $outcome->add_test_result($this->grade($outputs[$i], $testcase));
                    $i++;
                }
            }
        }

        return $outcome;
    }


    // Run all tests one-by-one on the sandbox.
    private function run_tests_singly() {
        $maxmark = $this->maximum_possible_mark($this->testcases);
        $numtests = count($this->testcases);
        $outcome = new qtype_coderunner_testing_outcome($maxmark, $numtests);
        $template = $this->question->get_per_test_template();
        foreach ($this->testcases as $testcase) {
            $this->templateparams['TEST'] = $testcase;
            try {
                $testprog = $this->twig->render($template, $this->templateparams);
            } catch (Exception $e) {
                $outcome->set_status(
                        qtype_coderunner_testing_outcome::STATUS_SYNTAX_ERROR,
                        'TEMPLATE ERROR: ' . $e->getMessage());
                break;
            }

            $input = isset($testcase->stdin) ? $testcase->stdin : '';
            $this->allruns[] = $testprog;
            $run = $this->sandbox->execute($testprog, $this->question->language,
                    $input, $this->files, $this->sandboxparams);
            if ($run->error !== qtype_coderunner_sandbox::OK) {
                $outcome->set_status(
                    qtype_coderunner_testing_outcome::STATUS_SANDBOX_ERROR,
                    qtype_coderunner_sandbox::error_string($run->error));
                break;
            } else if ($run->result === qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR) {
                $outcome->set_status(
                        qtype_coderunner_testing_outcome::STATUS_SYNTAX_ERROR,
                        $run->cmpinfo);
                break;
            } else if ($run->result != qtype_coderunner_sandbox::RESULT_SUCCESS) {
                $errormessage = $this->make_error_message($run);
                $iserror = true;
                $outcome->add_test_result($this->grade($errormessage, $testcase, $iserror));
                break;
            } else {
                // Successful run. Merge stdout and stderr for grading.
                // Rarely if ever do we get both stderr output and a
                // RESULT_SUCCESS result but it has been known to happen in the
                // past, possibly with a now-defunct sandbox.
                $output = $run->stderr ? $run->output + '\n' + $run->stderr : $run->output;
                $testresult = $this->grade($output, $testcase);
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


    private function do_combinator_grading($maxmark, $run) {
        // Given the result of a sandbox run with the combinator template,
        // build and return a testingOutcome object with a status of
        // STATUS_COMBINATOR_TEMPLATE_GRADER and appropriate feedback_html.
        if ($run->result !== qtype_coderunner_sandbox::RESULT_SUCCESS) {
            $fract = 0;
            $html = '<h2>BAD TEMPLATE RUN<h2><pre>' . $run->cmpinfo .
                    $run->stderr . '</pre>';
        } else {
            $result = json_decode($run->output);
            if (isset($result->feedback_html)) {  // Legacy combinator grader?
                $result->feedbackhtml = $result->feedback_html; // Change to modern version.
            }
            if ($result === null || !isset($result->fraction) ||
                    !is_numeric($result->fraction) ||
                    !isset($result->feedbackhtml)) {
                $fract = 0;
                $html = "<h2>BAD TEMPLATE OUTPUT</h2><pre>{$run->output}</pre>";
            } else {
                $fract = $result->fraction;
                $html = $result->feedbackhtml;
            }
        }
        $outcome = new qtype_coderunner_testing_outcome($maxmark,
                0, // No individual tests
                qtype_coderunner_testing_outcome::STATUS_COMBINATOR_TEMPLATE_GRADER);
        $outcome->set_mark_and_feedback($maxmark * $fract, $html);
        return $outcome;
    }

    // Return a $sep-separated string of the non-empty elements
    // of the array $strings. Similar to implode except empty strings
    // are ignored.
    private function merge($sep, $strings) {
        $s = '';
        foreach ($strings as $el) {
            if (trim($el)) {
                if ($s !== '') {
                    $s .= $sep;
                }
                $s .= $el;
            }
        }
        return $s;
    }


    // Return the maximum possible mark from the set of testcases we're running
    private function maximum_possible_mark() {
        $total = 0;
        foreach ($this->testcases as $testcase) {
            $total += $testcase->mark;
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
        return $this->merge("\n", array($run->cmpinfo, $run->output, $err, $run->stderr));
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

    // Count the number of errors in the given array of test results.
    // TODO -- figure out how to eliminate either this one or the identical
    // version in renderer.php.
    private function count_errors($testresults) {
        $errors = 0;
        foreach ($testresults as $tr) {
            if (!$tr->iscorrect) {
                $errors++;
            }
        }
        return $errors;
    }
}