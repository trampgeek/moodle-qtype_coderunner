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

/**
 * coderunner question definition classes.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot . '/question/behaviour/adaptive/behaviour.php');
require_once($CFG->dirroot . '/question/engine/questionattemptstep.php');
require_once($CFG->dirroot . '/question/behaviour/adaptive_adapted_for_coderunner/behaviour.php');
require_once($CFG->dirroot . '/question/type/coderunner/Twig/Autoloader.php');
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');
require_once($CFG->dirroot . '/question/type/coderunner/constants.php');
require_once($CFG->dirroot . '/question/type/coderunner/grader/graderbase.php');
require_once($CFG->dirroot . '/question/type/coderunner/sandbox/sandboxbase.php');
require_once($CFG->dirroot . '/question/type/coderunner/escapers.php');
require_once($CFG->dirroot . '/question/type/coderunner/testingoutcome.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/type/coderunner/jobrunner.php');

use qtype_coderunner\constants;

/**
 * Represents a 'CodeRunner' question.
 */
class qtype_coderunner_question extends question_graded_automatically {

    public $testcases; // Array of testcases.

    /**
     * Override default behaviour so that we can use a specialised behaviour
     * that caches test results returned by the call to grade_response().
     *
     * @param question_attempt $qa the attempt we are creating an behaviour for.
     * @param string $preferredbehaviour the requested type of behaviour.
     * @return question_behaviour the new behaviour object.
     */
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        // Regardless of the preferred behaviour, always use an adaptive
        // behaviour.
        return  new qbehaviour_adaptive_adapted_for_coderunner($qa, $preferredbehaviour);
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
                strlen($response['answer']) > constants::FUNC_MIN_LENGTH;
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
        // Return the sample answer, if supplied.
        return isset($this->answer) ? array('answer' => $this->answer) : array();
    }

    // Grade the given 'response'.
    // This implementation assumes a modified behaviour that will accept a
    // third array element in its response, containing data to be cached and
    // served up again in the response on subsequent calls.
    public function grade_response(array $response, $isprecheck=false) {
        if ($isprecheck && empty($this->precheck)) {
            throw new coding_exception("Unexpected precheck");
        }
        if (empty($response['_testoutcome'])) {
            $code = $response['answer'];
            $testcases = $this->filter_testcases($isprecheck, $this->precheck);
            $runner = new qtype_coderunner_jobrunner();
            $testoutcome = $runner->run_tests($this, $code, $testcases, $isprecheck);
            $testoutcomeserial = serialize($testoutcome);
        } else {
            $testoutcomeserial = $response['_testoutcome'];
            $testoutcome = unserialize($testoutcomeserial);
        }

        $datatocache = array('_testoutcome' => $testoutcomeserial);
        if ($testoutcome->all_correct()) {
             return array(1, question_state::$gradedright, $datatocache);
        } else if ($this->allornothing) {
            return array(0, question_state::$gradedwrong, $datatocache);
        } else {
            return array($testoutcome->mark_as_fraction(),
                    question_state::$gradedpartial, $datatocache);
        }
    }


    // Return an array of all the use_as_example testcases.
    public function example_testcases() {
        return array_filter($this->testcases, function($tc) {
                    return $tc->useasexample;
        });
    }


    // Extract and return the appropriate subset of the set of question testcases
    // given $isprecheckrun (true iff this was a run initiated by clicking
    // precheck) and the question's prechecksetting (0, 1, 2, 3 for Disable,
    // Empty, Examples and Selected respectively).
    protected function filter_testcases($isprecheckrun, $prechecksetting) {
        if (!$isprecheckrun) {
            if ($prechecksetting != constants::PRECHECK_SELECTED) {
                return $this->testcases;
            } else {
                return $this->selected_testcases(false);
            }
        } else { // This is a precheck run
            if ($prechecksetting == constants::PRECHECK_EMPTY) {
                return array($this->empty_testcase());
            } else if ($prechecksetting == constants::PRECHECK_EXAMPLES) {
                return $this->example_testcases();
            } else if ($prechecksetting == constants::PRECHECK_SELECTED) {
                return $this->selected_testcases(true);
            } else {
                throw new coding_exception('Precheck clicked but no precheck button?!');
            }
        }
    }


    // Return the appropriate subset of questions in the case that the question
    // precheck setting is "selected", given whether or not this is a precheckrun.
    protected function selected_testcases($isprecheckrun) {
        $testcases = array();
        foreach ($this->testcases as $testcase) {
            if (($isprecheckrun && $testcase->testtype != constants::TESTTYPE_NORMAL) ||
                (!$isprecheckrun && $testcase->testtype != constants::TESTTYPE_PRECHECK)) {
                $testcases[] = $testcase;
            }
        }
        return $testcases;
    }


    // Return an empty testcase - an artifical testcase with all fields
    // empty or zero except for a mark of 1.
    private function empty_testcase() {
        return (object) array(
            'testtype' => 0,
            'testcode' => '',
            'stdin'    => '',
            'expected' => '',
            'extra'    => '',
            'display'  => 0,
            'useasexample' => 0,
            'hiderestiffail' => 0,
            'mark'     => 1
        );
    }



    /******************************************************************
     * Interface methods for use by jobrunner
     ******************************************************************/
    // Return the per-test template
    public function get_per_test_template() {
        return $this->pertesttemplate;
    }


    // Return the programming language used to run the code
    public function get_language() {
        return $this->language;
    }

    // Get the showsource boolean
    public function get_show_source() {
        return $this->showsource;
    }


    // Return the regular expression used to split the combinator template
    // output into individual tests
    public function get_test_splitter_re() {
        return $this->testsplitterre;
    }


    // Return the combinator template, or NULL if it's empty or if
    // not enabled.
    public function get_combinator() {
        if (!$this->enablecombinator || empty($this->combinatortemplate)) {
            return null;
        } else {
            return $this->combinatortemplate;
        }
    }

    // Return an instance of the sandbox to be used to run code for this question.
    public function get_sandbox() {
        global $CFG;
        $sandbox = $this->sandbox; // Get the specified sandbox (if question has one).
        if ($sandbox === null) {   // No sandbox specified. Use best we can find.
            $sandbox = qtype_coderunner_sandbox::get_best_sandbox($this->language);
            if ($sandbox === null) {
                throw new coderunner_exception("Language {$this->language} is not available on this system");
            }
        } else {
            if (!get_config('qtype_coderunner', strtolower($sandbox) . '_enabled')) {
                throw new coderunner_exception("Question is configured to use a disabled sandbox ($sandbox)");
            }
        }

        return qtype_coderunner_sandbox::make_sandbox($sandbox);
    }


    // Get an instance of the grader to be used to grade this question.
    public function get_grader() {
        global $CFG;
        $grader = $this->grader == null ? constants::DEFAULT_GRADER : $this->grader;
        $filename = qtype_coderunner_grader::get_filename($grader);
        $graders = qtype_coderunner_grader::available_graders();
        $graderclass = $graders[$grader];
        require_once($CFG->dirroot . "/question/type/coderunner/grader/$filename");
        return new $graderclass();
    }


    // Return all the datafiles to use for a run
    public function get_files() {
        if ($this->prototypetype != 0) { // Is this a prototype question?
            $files = array(); // Don't load the files twice
        } else {
            // Load any files from the prototype.
            $context = qtype_coderunner::question_context($this);
            $prototype = qtype_coderunner::get_prototype($this->coderunnertype, $context);
            $files = $this->get_data_files($prototype, $prototype->questionid);
        }
        $files += $this->get_data_files($this, $this->id);  // Add in files for this question.
        return $files;
    }


    // Get the sandbox parameters for a run
    public function get_sandbox_params() {
        if (isset($this->sandboxparams)) {
            $sandboxparams = json_decode($this->sandboxparams, true);
        } else {
            $sandboxparams = array();
        }

        if (isset($this->cputimelimitsecs)) {
            $sandboxparams['cputime'] = intval($this->cputimelimitsecs);
        }
        if (isset($this->memlimitmb)) {
            $sandboxparams['memorylimit'] = intval($this->memlimitmb);
        }
        if (isset($this->templateparams) && $this->templateparams != '') {
            $this->parameters = json_decode($this->templateparams);
        }
        return $sandboxparams;
    }


    /**
     *  Return an associative array mapping filename to datafile contents
     *  for all the datafiles associated with a given question (which may
     *  be a real question or, in the case of a prototype, the question_options
     *  row) and the questionid from the mdl_questions table.
     */
    private static function get_data_files($question, $questionid) {
        global $DB;

        // If not given in the question object get the contextid from the database.
        if (isset($question->contextid)) {
            $contextid = $question->contextid;
        } else {
            $context = qtype_coderunner::question_context($question);
            $contextid = $context->id;
        }

        $fs = get_file_storage();
        $filemap = array();
        $files = $fs->get_area_files($contextid, 'qtype_coderunner', 'datafile', $questionid);

        foreach ($files as $f) {
            $name = $f->get_filename();
            if ($name !== '.') {
                $filemap[$f->get_filename()] = $f->get_content();
            }
        }
        return $filemap;
    }
}
