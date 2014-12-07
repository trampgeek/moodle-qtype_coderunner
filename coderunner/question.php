<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * coderunner question definition classes.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define('DEFAULT_GRADER', 'EqualityGrader');
defined('MOODLE_INTERNAL') || die();

/** Max size of output to be stored in question_attempt_step_data table
 *  (which is of type text so limited to 64k).
 */
define('MAX_OUTPUT_LENGTH', 60000);

define('FUNC_MIN_LENGTH', 1);  /* Minimum no. of bytes for a valid bit of code */

require_once($CFG->dirroot . '/question/behaviour/adaptive/behaviour.php');
require_once($CFG->dirroot . '/question/engine/questionattemptstep.php');
require_once($CFG->dirroot . '/question/behaviour/adaptive_adapted_for_coderunner/behaviour.php');
require_once($CFG->dirroot . '/local/Twig/Autoloader.php');
require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/sandbox_config.php');

require_once('Grader/graderbase.php');
require_once('escapers.php');
require_once('testingoutcome.php');

/**
 * Represents a Python 'coderunner' question.
 */
class qtype_coderunner_question extends question_graded_automatically {

    public  $testcases;    // Array of testcases
    private $graderInstance = NULL;      // The grader instance, if it's NOT a custom one
    private $twig = NULL;                // The template processor environment
    private $sandboxInstance = NULL;     // The sandbox we're using
    private $allRuns = NULL;             // Array of the source code for all runs

    /**
     * Override default behaviour so that we can use a specialised behaviour
     * that caches test results returned by the call to grade_response().
     *
     * @param question_attempt $qa the attempt we are creating an behaviour for.
     * @param string $preferredbehaviour the requested type of behaviour.
     * @return question_behaviour the new behaviour object.
     */
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        // TODO: see if there's some way or issuing a warning message when
        // coderunner questions aren't being used in an adaptive mode.

        if ($preferredbehaviour == 'adaptive') {
            return  new qbehaviour_adaptive_adapted_for_coderunner($qa, $preferredbehaviour);
        }
        else {
            return parent::make_behaviour($qa, $preferredbehaviour);
        }
    }



    public function get_expected_data() {
        return array('answer' => PARAM_RAW, 'rating' => PARAM_INT);
    }


    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    public function is_gradable_response(array $response) {
        return array_key_exists('answer', $response) &&
                !empty($response['answer']) &&
                strlen($response['answer']) > FUNC_MIN_LENGTH;
    }

    public function is_complete_response(array $response) {
        return $this->is_gradable_response($response);
    }


    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        return get_string('answerrequired', 'qtype_coderunner');
    }


    /** This function is used by the question engine to prevent regrading of
     *  unchanged submissions.
     *
     * @param array $prevresponse
     * @param array $newresponse
     * @return boolean
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        if (!question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer')
            || !question_utils::arrays_same_at_key_integer(
                $prevresponse, $newresponse, 'rating')) {
            return false;
        }
        return true;
    }



    public function get_correct_response() {
        return $this->get_correct_answer();
    }


    public function get_correct_answer() {
        // Return the sample answer, if supplied
        return isset($this->answer) ? array('answer' => $this->answer) : array();
    }


    // Grade the given 'response'.
    // This implementation assumes a modified behaviour that will accept a
    // third array element in its response, containing data to be cached and
    // served up again in the response on subsequent calls.

    public function grade_response(array $response) {
        if (empty($response['_testoutcome'])) {
            $code = $response['answer'];
            $testOutcome = $this->run_tests($code, $this->testcases);
            $testOutcomeSerial = serialize($testOutcome);
        }
        else {
            $testOutcomeSerial = $response['_testoutcome'];
            $testOutcome = unserialize($testOutcomeSerial);
        }

        $dataToCache = array('_testoutcome' => $testOutcomeSerial);
        if ($testOutcome->allCorrect()) {
             return array(1, question_state::$gradedright, $dataToCache);
        }
        elseif ($this->all_or_nothing) {
            return array(0, question_state::$gradedwrong, $dataToCache);
        }
        else {
            return array($testOutcome->markAsFraction(),
                    question_state::$gradedpartial, $dataToCache);
        }
    }


    // Check the correctness of a student's code given the student's
    // response (i.e. "answer") and and a set of testCases.
    // Returns a TestingOutcome object.

    protected function run_tests($code, $testCases) {
        global $CFG;

        Twig_Autoloader::register();
        $loader = new Twig_Loader_String();
        $this->twig = new Twig_Environment($loader, array(
            'debug' => true,
            'autoescape' => false,
            'optimizations' => 0
        ));

        $twigCore = $this->twig->getExtension('core');
        $twigCore->setEscaper('py', 'pythonEscaper');
        $twigCore->setEscaper('python', 'pythonEscaper');
        $twigCore->setEscaper('c',  'javaEscaper');
        $twigCore->setEscaper('java', 'javaEscaper');
        $twigCore->setEscaper('ml', 'matlabEscaper');
        $twigCore->setEscaper('matlab', 'matlabEscaper');

        $this->setUpSandbox();
        $this->setUpGrader();

        $this->allRuns = array();

        // TODO: clean up the whole sandbox parameter business
        if (isset($this->sandbox_params)) {
            $sandboxParams = json_decode($this->sandbox_params, true);
        } else {
            $sandboxParams = array();
        }
        $files = $this->getDataFiles();
        if (isset($this->cputimelimitsecs)) {
            $sandboxParams['cputime'] = intval($this->cputimelimitsecs);
        }
        if (isset($this->memlimitmb)) {
            $sandboxParams['memorylimit'] = intval($this->memlimitmb);
        }
        
        if (isset($this->template_params) && $this->template_params != '') {
            $this->parameters = json_decode($this->template_params);
        }

        $outcome = $this->runWithCombinator($code, $testCases, $files, $sandboxParams);

        // If that failed for any reason (e.g. no combinator template or timeout
        // or signal) run the tests individually. Any compilation
        // errors or abnormal terminations (not including signals) in individual
        // tests bomb the whole test process, but otherwise we should finish
        // with a TestingOutcome object containing a test result for each test
        // case.

        if ($outcome == NULL) {
            $outcome = $this->runTestsSingly($code, $testCases, $files, $sandboxParams);
        }

        $this->sandboxInstance->close();
        if ($this->show_source) {
            $outcome->sourceCodeList = $this->allRuns;
        }
    	return $outcome;
    }


    /** Set the test cases for this question. Called by questiontype.
     *  Ummm. Well actually no. It isn't used as at the time the questiontype
     *  is trying to set testcases it doesn't have a real question object
     *  so it sets the testcases field by direct assignment :-(
     *  I'm leaving this code in place for documentation purposes, however.
     *
     * @param type $testcases The set of testcases, each consisting of
     * all the fields contained in the quest_coderunner_testcases database table
     * (q.v.) and including the testcode to run for the test, the stdin to
     * use, the expected output and the 'extra' field (used by some special
     * templates).
     */
    public function setTestcases($testcases) {
        $this->testcases = $testcases;
    }


    /** Find the 'best' sandbox for a given language, defined to be the
     *  first one in the ordered list of sandboxes in sandbox_config.php
     *  that has been enabled by the administrator (through the usual
     *  plug-in setting controls) and that supports the given language.
     *  It's public so the tester can call it (yuck, hacky).
     *  @param type $language to run. 
     *  @return the preferred sandbox for the given language or NULL if no
     *  enabled sandboxes support this language.
     */
    public static function getBestSandbox($language) {
        global $SANDBOXES;
        foreach($SANDBOXES as $sandbox) {
            if (get_config('qtype_coderunner', $sandbox . '_enabled')) {
                require_once("Sandbox/$sandbox.php");
                $sb = new $sandbox();
                $langsSupported = $sb->getLanguages()->languages;
                foreach ($langsSupported as $lang) {
                    if (strtolower($lang) == strtolower($language)) {
                        return $sandbox;
                    }
                }
            }
        }
        return NULL;
    }



    // Try running with the combinator template, which combines all tests into
    // a single sandbox run.
    // Only do this if the combinator is enabled, there are no stdins and the
    // question isn't set to let the template (i.e., the per-test-case template)
    // do the grading.
    // Special template parameters are STUDENT_ANSWER, the raw submitted code,
    // ESCAPED_STUDENT_ANSWER, the submitted code with all double quote chars escaped
    // (for use in a Python statement like s = """{{ESCAPED_STUDENT_ANSWER}}""" and
    // MATLAB_ESCAPED_STUDENT_ANSWER, a string for use in Matlab intended
    // to be used as s = sprintf('{{MATLAB_ESCAPED_STUDENT_ANSWER}}')
    // Return true if successful.
    // 26/5/14 - add entire QUESTION to template environment.
    private function runWithCombinator($code, $testCases, $files, $sandboxParams) {

        $isCombinatorGrader = strtolower($this->grader) === 'combinatortemplategrader';  
        $useCombinator = $isCombinatorGrader
            || ($this->enable_combinator && $this->noStdins($testCases) &&
                strtolower($this->grader) !== 'templategrader');
        if (!$useCombinator) {
            return NULL;  // Not our job
        }
        
        // We're OK to use a combinator. Let's go.
        
        $outcome = NULL;
        $maxMark = $this->maximumPossibleMark($testCases);
        if ($maxMark == 0) {
            $maxMark = 1; // Must be a combinator template grader
        }

        $templateParams = array(
            'STUDENT_ANSWER' => $code,
            'ESCAPED_STUDENT_ANSWER' => pythonEscaper(NULL, $code, NULL),
            'MATLAB_ESCAPED_STUDENT_ANSWER' => matlabEscaper(NULL, $code, NULL),
            'QUESTION' => $this,
            'TESTCASES' => $testCases);
        $testProg = $this->twig->render($this->combinator_template, $templateParams);

        $this->allRuns[] = $testProg;
        $run = $this->sandboxInstance->execute($testProg, $this->language,
                NULL, $files, $sandboxParams);

        // If it's a combinator grader, we pass the result to the
        // doCombinatorGrading method. Otherwise we deal with syntax errors or
        // a successful result without accompanying stderr.
        // In all other cases (runtime error etc) we give up
        // on the combinator.
        
        if ($run->error !== SANDBOX::OK) {
            $outcome = new TestingOutcome($maxMark,
                    TestingOutcome::STATUS_SANDBOX_ERROR,
                    Sandbox::errorString($run->error));
        } elseif ($isCombinatorGrader) {
            $outcome = $this->doCombinatorGrading($maxMark, $run);
        } else if ($run->result === SANDBOX::RESULT_COMPILATION_ERROR) {
            $outcome = new TestingOutcome($maxMark,
                    TestingOutcome::STATUS_SYNTAX_ERROR,
                    $run->cmpinfo);
        } else if ($run->result === Sandbox::RESULT_SUCCESS && !$run->stderr) {
            $outputs = preg_split($this->test_splitter_re, $run->output);
            if (count($outputs) == count($testCases)) {
                $outcome = new TestingOutcome($maxMark);
                $i = 0;
                foreach ($testCases as $testCase) {
                    $outcome->addTestResult($this->grade($outputs[$i], $testCase));
                    $i++;
                }
            }
        }

        return $outcome;
    }


    // Run all tests one-by-one on the sandbox
    private function runTestsSingly($code, $testCases, $files, $sandboxParams) {
        $maxMark = $this->maximumPossibleMark($testCases);
        $templateParams = array(
            'STUDENT_ANSWER' => $code,
            'ESCAPED_STUDENT_ANSWER' => pythonEscaper(NULL, $code, NULL),
            'MATLAB_ESCAPED_STUDENT_ANSWER' => matlabEscaper(NULL, $code, NULL),
            'QUESTION' => $this
         );

        $outcome = new TestingOutcome($maxMark);
        $template = $this->per_test_template;
        foreach ($testCases as $testCase) {
            $templateParams['TEST'] = $testCase;
            try {
                $testProg = $this->twig->render($template, $templateParams);
            } catch (Exception $e) {
                $outcome = new TestingOutcome(
                        $maxMark,
                        TestingOutcome::STATUS_SYNTAX_ERROR,
                        'TEMPLATE ERROR: ' . $e->getMessage());
                break;
            }

            $input = isset($testCase->stdin) ? $testCase->stdin : '';
            $this->allRuns[] = $testProg;
            $run = $this->sandboxInstance->execute($testProg, $this->language,
                    $input, $files, $sandboxParams);
            if ($run->error !== SANDBOX::OK) {
                $outcome = new TestingOutcome(
                    $maxMark, 
                    TestingOutcome::STATUS_SANDBOX_ERROR,
                    Sandbox::errorString($run->error));
                break;
            }
            else if ($run->result === SANDBOX::RESULT_COMPILATION_ERROR) {
                $outcome = new TestingOutcome(
                        $maxMark,
                        TestingOutcome::STATUS_SYNTAX_ERROR,
                        $run->cmpinfo);
                break;
            } else if ($run->result != Sandbox::RESULT_SUCCESS) {
                $errorMessage = $this->makeErrorMessage($run);
                $isError = TRUE;
                $outcome->addTestResult($this->grade($errorMessage, $testCase, $isError));
                break;
            } else {
                // Successful run. Merge stdout and stderr for grading.
                // [Rarely if ever do we get both stderr output and a
                // RESULT_SUCCESS result but it has been known to happen in the
                //  past, possibly with a now-defunct sandbox.]
                $output = $run->stderr ? $run->output + '\n' + $run->stderr : $run->output;
                $outcome->addTestResult($this->grade($output, $testCase));
            }
        }
        return $outcome;
    }


    // Set up a grader instance.
    private function setUpGrader() {
        global $CFG;
        $graderClass = $this->grader;
        if ($graderClass === NULL) {
            $this->grader = $graderClass = DEFAULT_GRADER;
        }
        $graderClassLC = strtolower($graderClass);
        require_once($CFG->dirroot . "/question/type/coderunner/Grader/$graderClassLC.php");
        $this->graderInstance = new $graderClass();
    }


    // Set $this->sandboxInstance
    private function setUpSandbox() {
        global $CFG;
        $sandboxClass = $this->sandbox;
        if ($sandboxClass === NULL)  {
            $this->sandbox = $sandboxClass = $this->getBestSandbox($this->language);
            if ($sandboxClass === NULL) {
                throw new coding_exception("Language {$this->language} is not available on this system");
            }
        } else {
            if (!get_config('qtype_coderunner', strtolower($sandboxClass) . '_enabled')) {
                throw new coding_exception("Question is configured to use a disabled sandbox ($sandboxClass)");
            }
        }

        $sandboxClassLC = strtolower($sandboxClass);
        require_once($CFG->dirroot . "/question/type/coderunner/Sandbox/$sandboxClassLC.php");
        $this->sandboxInstance = new $sandboxClass();
    }


    // Return the maximum possible mark from the given set of testcases.
    private function maximumPossibleMark($testcases) {
        $total = 0;
        foreach ($testcases as $testcase) {
            $total += $testcase->mark;
        }
        return $total;
    }



    /**
     *  Return an associative array mapping filename to datafile contents
     *  for all the datafiles associated with this question
     */
    private function getDataFiles() {
        global $DB;
        if (isset($this->contextid)) {  // Is this possible? No harm in trying
            $contextid = $this->contextid;
        } else if (isset($this->context)) {
            $contextid = $this->context->id;
        } else {
            $record = $DB->get_record('question_categories',
                array('id' => $this->category), 'contextid');
            $contextid = $record->contextid;
        }
        $fs = get_file_storage();
        $fileMap = array();
        $files = $fs->get_area_files($contextid, 'qtype_coderunner', 'datafile', $this->id);
        foreach ($files as $f) {
            $name = $f->get_filename();
            if ($name !== '.') {
                $fileMap[$f->get_filename()] = $f->get_content();
            }
        }
        return $fileMap;
    }



    // Grade a given test result by calling the grader.

    private function grade($output, $testcase, $isBad = FALSE) {
        return $this->graderInstance->grade($output, $testcase, $isBad);
    }
    
    
    private function doCombinatorGrading($maxMark, $run) {
        // Given the result of a sandbox run with the combinator template,
        // build and return a testingOutcome object with a status of
        // STATUS_COMBINATOR_TEMPLATE_GRADER and appropriate feedback_html.
        
        if ($run->result !== SANDBOX::RESULT_SUCCESS) {
            $fract = 0;
            $html = '<h2>BAD TEMPLATE RUN<h2><pre>' . $run->cmpinfo . 
                    $run->stderr . '</pre>';
        } 
        else  {
            $result = json_decode($run->output);
            if ($result === NULL || !isset($result->fraction) ||
                    !is_numeric($result->fraction) ||
                    !isset($result->feedback_html)) {
                $fract = 0;
                $html = "<h2>BAD TEMPLATE OUTPUT</h2><pre>{$run->output}</pre>";
            } else {
                $fract = $result->fraction;
                $html = $result->feedback_html;
            }
        } 
        $outcome = new TestingOutcome($maxMark, TestingOutcome::STATUS_COMBINATOR_TEMPLATE_GRADER);
        $outcome->setMarkAndFeedback($maxMark * $fract, $html);
        return $outcome;
    }


    // Return a $sep-separated string of the non-empty elements
    // of the array $strings. Similar to implode except empty strings
    // are ignored
    private function merge($sep, $strings) {
        $s = '';
        foreach($strings as $el) {
            if ($el) {
                if ($s !== '') {
                    $s .= $sep;
                }
                $s .= $el;
            }
        }
        return $s;
    }


    private function makeErrorMessage($run) {
        $err = "***" . Sandbox::resultString($run->result) . "***";
        if ($run->result === Sandbox::RESULT_RUNTIME_ERROR) {
            $sig = $run->signal;
            if ($sig) {
                $err .= " (signal $sig)";
            }
        }
        return $this->merge("\n", array($run->cmpinfo, $run->output, $err, $run->stderr));
    }


    /** True IFF no testcases have nonempty stdin. */
    private function noStdins($testCases) {
        foreach ($testCases as $testCase) {
            if ($testCase->stdin != '') {
                return FALSE;
            }
        }
        return TRUE;
    }


    // Count the number of errors in the given array of test results.
    // TODO -- figure out how to eliminate either this one or the identical
    // version in renderer.php.
    private function count_errors($testResults) {
        $errors = 0;
        foreach ($testResults as $tr) {
            if (!$tr->isCorrect) {
                $errors++;
            }
        }
        return $errors;
    }
}


