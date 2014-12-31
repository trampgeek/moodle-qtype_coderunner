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


define('DEFAULT_GRADER', 'EqualityGrader');  // Name of file containing default grader
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
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');
require_once($CFG->dirroot . '/question/type/coderunner/Grader/graderbase.php');
require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/sandboxbase.php');
require_once($CFG->dirroot . '/question/type/coderunner/escapers.php');
require_once($CFG->dirroot . '/question/type/coderunner/testingoutcome.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Represents a 'CodeRunner' question.
 */
class qtype_coderunner_question extends question_graded_automatically {

    public  $testcases;    // Array of testcases
    private $graderinstance = NULL;      // The grader instance, if it's NOT a custom one
    private $twig = NULL;                // The template processor environment
    private $sandboxinstance = NULL;     // The sandbox we're using
    private $allruns = NULL;             // Array of the source code for all runs

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
            $testoutcome = $this->run_tests($code, $this->testcases);
            $testoutcomeserial = serialize($testoutcome);
        }
        else {
            $testoutcomeserial = $response['_testoutcome'];
            $testoutcome = unserialize($testoutcomeserial);
        }

        $datatocache = array('_testoutcome' => $testoutcomeserial);
        if ($testoutcome->allCorrect()) {
             return array(1, question_state::$gradedright, $datatocache);
        }
        elseif ($this->all_or_nothing) {
            return array(0, question_state::$gradedwrong, $datatocache);
        }
        else {
            return array($testoutcome->markAsFraction(),
                    question_state::$gradedpartial, $datatocache);
        }
    }


    // Check the correctness of a student's code given the student's
    // response (i.e. "answer") and and a set of testCases.
    // Returns a TestingOutcome object.

    protected function run_tests($code, $testcases) {
        global $CFG;

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

        $this->setup_sandbox();
        $this->setup_grader();

        $this->allruns = array();

        if (isset($this->sandbox_params)) {
            $sandboxparams = json_decode($this->sandbox_params, true);
        } else {
            $sandboxparams = array();
        }
        
        if ($this->prototype_type != 0) {
            $files = array(); // We're running a prototype question ?!
        } else {
            // Load any files from the prototype
            $context = qtype_coderunner::question_context($this);
            $prototype = qtype_coderunner::getPrototype($this->coderunner_type, $context);
            $files = $this->get_data_files($prototype);
        }
        $files += $this->get_data_files($this, $this->contextid);  // Add in files for this question
        if (isset($this->cputimelimitsecs)) {
            $sandboxparams['cputime'] = intval($this->cputimelimitsecs);
        }
        if (isset($this->memlimitmb)) {
            $sandboxparams['memorylimit'] = intval($this->memlimitmb);
        }
        
        if (isset($this->template_params) && $this->template_params != '') {
            $this->parameters = json_decode($this->template_params);
        }

        $outcome = $this->run_with_combinator($code, $testcases, $files, $sandboxparams);

        // If that failed for any reason (e.g. no combinator template or timeout
        // or signal) run the tests individually. Any compilation
        // errors or abnormal terminations (not including signals) in individual
        // tests bomb the whole test process, but otherwise we should finish
        // with a TestingOutcome object containing a test result for each test
        // case.

        if ($outcome == NULL) {
            $outcome = $this->run_tests_singly($code, $testcases, $files, $sandboxparams);
        }

        $this->sandboxinstance->close();
        if ($this->show_source) {
            $outcome->sourcecodelist = $this->allruns;
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
     *  @return the external name of the preferred sandbox for the given language
     *  or NULL if no enabled sandboxes support this language.
     */
    public static function get_best_sandbox($language) {
        $sandboxes = Sandbox::available_sandboxes();
        foreach($sandboxes as $extname=>$classname) {
            if (get_config('qtype_coderunner', $extname . '_enabled')) {
                $filename = Sandbox::get_filename($extname);
                require_once("Sandbox/$filename");
                $sb = new $classname();
                $queryresult = $sb->get_languages();
                if ($queryresult->error == Sandbox::OK) {
                    $supportedlangs = $queryresult->languages;
                    foreach ($supportedlangs as $lang) {
                        if (strtolower($lang) == strtolower($language)) {
                            return $extname;
                        }
                    }
                } else {
                    throw new coderunner_exception("Sandbox $extname is down or misconfigured.");
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
    private function run_with_combinator($code, $testcases, $files, $sandboxparams) {

        $iscombinatorgrader = strtolower($this->grader) === 'combinatortemplategrader';  
        $usecombinator = $iscombinatorgrader
            || ($this->enable_combinator && $this->has_no_stdins($testcases) &&
                strtolower($this->grader) !== 'templategrader');
        if (!$usecombinator) {
            return NULL;  // Not our job
        }
        
        // We're OK to use a combinator. Let's go.
        
        $outcome = NULL;
        $maxmark = $this->maximum_possible_mark($testcases);
        if ($maxmark == 0) {
            $maxmark = 1; // Must be a combinator template grader
        }

        $templateparams = array(
            'STUDENT_ANSWER' => $code,
            'ESCAPED_STUDENT_ANSWER' => python_escaper(NULL, $code, NULL),
            'MATLAB_ESCAPED_STUDENT_ANSWER' => matlab_escaper(NULL, $code, NULL),
            'QUESTION' => $this,
            'TESTCASES' => $testcases);
        $testprog = $this->twig->render($this->combinator_template, $templateparams);

        $this->allruns[] = $testprog;
        $run = $this->sandboxinstance->execute($testprog, $this->language,
                NULL, $files, $sandboxparams);

        // If it's a combinator grader, we pass the result to the
        // doCombinatorGrading method. Otherwise we deal with syntax errors or
        // a successful result without accompanying stderr.
        // In all other cases (runtime error etc) we give up
        // on the combinator.
        
        if ($run->error !== Sandbox::OK) {
            $outcome = new TestingOutcome($maxmark,
                    TestingOutcome::STATUS_SANDBOX_ERROR,
                    Sandbox::error_string($run->error));
        } elseif ($iscombinatorgrader) {
            $outcome = $this->do_combinator_grading($maxmark, $run);
        } else if ($run->result === Sandbox::RESULT_COMPILATION_ERROR) {
            $outcome = new TestingOutcome($maxmark,
                    TestingOutcome::STATUS_SYNTAX_ERROR,
                    $run->cmpinfo);
        } else if ($run->result === Sandbox::RESULT_SUCCESS && !$run->stderr) {
            $outputs = preg_split($this->test_splitter_re, $run->output);
            if (count($outputs) == count($testcases)) {
                $outcome = new TestingOutcome($maxmark);
                $i = 0;
                foreach ($testcases as $testcase) {
                    $outcome->add_test_result($this->grade($outputs[$i], $testcase));
                    $i++;
                }
            }
        }

        return $outcome;
    }


    // Run all tests one-by-one on the sandbox
    private function run_tests_singly($code, $testcases, $files, $sandboxparams) {
        $maxMark = $this->maximum_possible_mark($testcases);
        $templateparams = array(
            'STUDENT_ANSWER' => $code,
            'ESCAPED_STUDENT_ANSWER' => python_escaper(NULL, $code, NULL),
            'MATLAB_ESCAPED_STUDENT_ANSWER' => matlab_escaper(NULL, $code, NULL),
            'QUESTION' => $this
         );

        $outcome = new TestingOutcome($maxMark);
        $template = $this->per_test_template;
        foreach ($testcases as $testcase) {
            $templateparams['TEST'] = $testcase;
            try {
                $testprog = $this->twig->render($template, $templateparams);
            } catch (Exception $e) {
                $outcome = new TestingOutcome(
                        $maxMark,
                        TestingOutcome::STATUS_SYNTAX_ERROR,
                        'TEMPLATE ERROR: ' . $e->getMessage());
                break;
            }

            $input = isset($testcase->stdin) ? $testcase->stdin : '';
            $this->allruns[] = $testprog;
            $run = $this->sandboxinstance->execute($testprog, $this->language,
                    $input, $files, $sandboxparams);
            if ($run->error !== Sandbox::OK) {
                $outcome = new TestingOutcome(
                    $maxMark, 
                    TestingOutcome::STATUS_SANDBOX_ERROR,
                    Sandbox::error_string($run->error));
                break;
            }
            else if ($run->result === Sandbox::RESULT_COMPILATION_ERROR) {
                $outcome = new TestingOutcome(
                        $maxMark,
                        TestingOutcome::STATUS_SYNTAX_ERROR,
                        $run->cmpinfo);
                break;
            } else if ($run->result != Sandbox::RESULT_SUCCESS) {
                $errormessage = $this->make_error_message($run);
                $iserror = true;
                $outcome->add_test_result($this->grade($errormessage, $testcase, $iserror));
                break;
            } else {
                // Successful run. Merge stdout and stderr for grading.
                // [Rarely if ever do we get both stderr output and a
                // RESULT_SUCCESS result but it has been known to happen in the
                //  past, possibly with a now-defunct sandbox.]
                $output = $run->stderr ? $run->output + '\n' + $run->stderr : $run->output;
                $outcome->add_test_result($this->grade($output, $testcase));
            }
        }
        return $outcome;
    }


    // Set up a grader instance.
    private function setup_grader() {
        global $CFG;
        $grader = $this->grader;
        if ($grader === NULL) {
            $this->grader = $grader = DEFAULT_GRADER;
        }

        $filename = qtype_coderunner_grader::get_filename($grader);
        $graderclass = qtype_coderunner_grader::available_graders()[$grader];
        require_once($CFG->dirroot . "/question/type/coderunner/Grader/$filename");
        $this->graderinstance = new $graderclass();
    }


    // Set $this->sandboxInstance
    private function setup_sandbox() {
        global $CFG;
        $sandbox = $this->sandbox;
        if ($sandbox === NULL)  {
            $this->sandbox = $sandbox = $this->get_best_sandbox($this->language);
            if ($sandbox === NULL) {
                throw new coderunner_exception("Language {$this->language} is not available on this system");
            }
        } else {
            if (!get_config('qtype_coderunner', strtolower($sandbox) . '_enabled')) {
                throw new coderunner_exception("Question is configured to use a disabled sandbox ($sandbox)");
            }

        }

        $sandboxclass = Sandbox::available_sandboxes()[$sandbox];
        $filename = Sandbox::get_filename($sandbox);
        require_once($CFG->dirroot . "/question/type/coderunner/Sandbox/$filename");
        $this->sandboxinstance = new $sandboxclass();
    }


    // Return the maximum possible mark from the given set of testcases.
    private function maximum_possible_mark($testcases) {
        $total = 0;
        foreach ($testcases as $testcase) {
            $total += $testcase->mark;
        }
        return $total;
    }



    /**
     *  Return an associative array mapping filename to datafile contents
     *  for all the datafiles associated with a given question (which may
     *  be a real question or, in the case of a prototype, the question_options
     *  row).
     */
    private static function get_data_files($question) {
        global $DB;

        // Deal with problem that $question might not be an actual question
        // but (in case of prototype) a row from the question_options table.
        $questionid = isset($question->questionid) ? $question->questionid : $question->id;
        
        // If not given in the question object get the contextid from the database

        if (isset($question->contextid)) {
            $contextid = $question->contextid;
        } else {
            $context = qtype_coderunner::question_context($question);
            $contextid = $context->id;
        }

        $fs = get_file_storage();
        $fileMap = array();
        $files = $fs->get_area_files($contextid, 'qtype_coderunner', 'datafile', $questionid);
        foreach ($files as $f) {
            $name = $f->get_filename();
            if ($name !== '.') {
                $fileMap[$f->get_filename()] = $f->get_content();
            }
        }
        return $fileMap;
    }



    // Grade a given test result by calling the grader.

    private function grade($output, $testcase, $isbad = false) {
        return $this->graderinstance->grade($output, $testcase, $isbad);
    }
    
    
    private function do_combinator_grading($maxmark, $run) {
        // Given the result of a sandbox run with the combinator template,
        // build and return a testingOutcome object with a status of
        // STATUS_COMBINATOR_TEMPLATE_GRADER and appropriate feedback_html.
        
        if ($run->result !== Sandbox::RESULT_SUCCESS) {
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
        $outcome = new TestingOutcome($maxmark, TestingOutcome::STATUS_COMBINATOR_TEMPLATE_GRADER);
        $outcome->set_mark_and_feedback($maxmark * $fract, $html);
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


    private function make_error_message($run) {
        $err = "***" . Sandbox::result_string($run->result) . "***";
        if ($run->result === Sandbox::RESULT_RUNTIME_ERROR) {
            $sig = $run->signal;
            if ($sig) {
                $err .= " (signal $sig)";
            }
        }
        return $this->merge("\n", array($run->cmpinfo, $run->output, $err, $run->stderr));
    }


    /** True IFF no testcases have nonempty stdin. */
    private function has_no_stdins($testcases) {
        foreach ($testcases as $testcase) {
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


