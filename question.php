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
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

use qtype_coderunner\constants;

/**
 * Represents a 'CodeRunner' question.
 */
class qtype_coderunner_question extends question_graded_automatically {

    public $testcases = null; // Array of testcases.

    /**
     * Start a new attempt at this question, storing any information that will
     * be needed later in the step. It is retrieved and applied by
     * apply_attempt_state.
     *
     * For CodeRunner questions we pre-process the template parameters for any
     * randomisation required, storing the processed template parameters in
     * the question_attempt_step.
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being started. Can be used to store state. Is set to null during
     *      question validation, and must then be ignored.
     * @param int $varant which variant of this question to start. Will be between
     *      1 and {@link get_num_variants()} inclusive.
     */
    public function start_attempt(question_attempt_step $step=null, $variant=null) {
        global $USER;

        $user = $USER;
        $this->student = $user;
        if ($step !== null) {
            parent::start_attempt($step, $variant);
            $step->set_qt_var('_STUDENT', serialize($user));
        }

        $seed = mt_rand();
        if ($step !== null) {
            $step->set_qt_var('_mtrandseed', $seed);
        }
        $this->setup_template_params($seed);
        if ($this->twigall) {
            $this->twig_all();
        }
    }

    // Retrieve the saved random number seed and reconstruct the template
    // parameters to the state they were left after start_attempt was called.
    // Also twig expand the rest of the question fields if $this->twigall is true.
    public function apply_attempt_state(question_attempt_step $step) {
        parent::apply_attempt_state($step);
        $this->student = unserialize($step->get_qt_var('_STUDENT'));
        $seed = $step->get_qt_var('_mtrandseed');
        if ($seed === null) {
            // Rendering a question that was begun before randomisation
            // was introduced into the code
           $seed = mt_rand();
        }
        $this->setup_template_params($seed);

        if ($this->twigall) {
            $this->twig_all();
        }
    }

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
        $expecteddata = array('answer' => PARAM_RAW,
                     'language' => PARAM_NOTAGS);
        if ($this->attachments != 0) {
            $expecteddata['attachments'] = question_attempt::PARAM_FILES;
        }
        return $expecteddata;
    }



    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }


    public function validate_response(array $response) {
        // Check the response and return a validation error message if it's
        // faulty or an empty string otherwise.

        // First check the attachments
        $hasattachments = array_key_exists('attachments', $response)
            && $response['attachments'] instanceof question_response_files;
        if ($hasattachments) {
            $attachmentfiles = $response['attachments']->get_files();
            $attachcount = count($attachmentfiles);
            // Check the filetypes.
            $invalidfiles = array();
            $regex = $this->filenamesregex;
            $supportfiles = $this->get_files();
            foreach ($attachmentfiles as $file) {
                $filename = $file->get_filename();
                if (!$this->is_valid_filename($filename, $regex, $supportfiles)) {
                    $invalidfiles[] = $filename;
                }
            }

            if (count($invalidfiles) > 0) {
                $badfilelist = implode(', ', $invalidfiles);
                return get_string('badfiles', 'qtype_coderunner', $badfilelist);
            }
        } else {
            $attachcount = 0;
        }

        if ($attachcount < $this->attachmentsrequired) {
            return get_string('insufficientattachments', 'qtype_coderunner', $this->attachmentsrequired);
        }

        if ($attachcount == 0) { // If no attachments, require an answer
            $hasanswer = array_key_exists('answer', $response);
            if (!$hasanswer || strlen($response['answer']) == 0) {
                return get_string('answerrequired', 'qtype_coderunner');
            } else if (strlen($response['answer']) < constants::FUNC_MIN_LENGTH) {
                return get_string('answertooshort', 'qtype_coderunner', constants::FUNC_MIN_LENGTH);
            }
        }
        return '';  // All good
    }

    // Return true iff the given filename is valid, meaning it matches the
    // regex (if given), contains only alphanumerics plus '-', '_' and '.',
    // doesn't clash with any of the support files and doesn't
    // start with double underscore..
    private function is_valid_filename($filename, $regex, $supportfiles) {
        if (strpos($filename, '__') === 0) {
            return false;  // Dunder names are reserved for runtime task
        }
        if (!ctype_alnum(str_replace(array('-', '_', '.'), '', $filename))) {
            return false;  // Filenames must be alphanumeric plus '.', '-', or '_'
        }
        if (!empty($regex) && preg_match('`^' . $this->filenamesregex . '$`', $filename) !== 1) {
            return false;  // Filename doesn't match given regex
        }
        foreach (array_keys($supportfiles) as $supportfilename) {
            if ($supportfilename == $filename) {
                return false;  // Filename collides with a support file name
            }
        }
        return true;
    }

    public function is_gradable_response(array $response) {
        // Determine if the given response has a non-empty answer and/or
        // a suitable number of attachments of accepted types.
        return $this->validate_response($response) == '';
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
        $error =  $this->validate_response($response);
        if ($error) {
            return $error;
        } else {
            return get_string('unknownerror', 'qtype_coderunner');
        }
    }


    /** This function is used by the question engine to prevent regrading of
     *  unchanged submissions.
     *
     * @param array $prevresponse
     * @param array $newresponse
     * @return boolean
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        $sameanswer = question_utils::arrays_same_at_key_missing_is_blank(
                        $prevresponse, $newresponse, 'answer') &&
                question_utils::arrays_same_at_key_missing_is_blank(
                        $prevresponse, $newresponse, 'language');
        $attachments1 = $this->get_attached_files($prevresponse);
        $attachments2 = $this->get_attached_files($newresponse);
        $sameattachments = $attachments1 === $attachments2;
        return $sameanswer && $sameattachments;
    }


    public function get_correct_response() {
        return $this->get_correct_answer();
    }


    public function get_correct_answer() {
        // Return the sample answer, if supplied.
        if (!isset($this->answer)) {
            return null;
        } else {
            $answer = array('answer' => $this->answer);
            // For multilanguage questions we also need to specify the language.
            if (!empty($this->acelang) && strpos($this->acelang, ',') !== false) {
                list($langs, $defaultlang) = qtype_coderunner_util::extract_languages($this->acelang);
                $default = empty($defaultlang) ? $langs[0] : $defaultlang;
                $answer['language'] = $default;
            }
            return $answer;
        }
    }


    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'response_attachments') {
            // Response attachments visible if the question has them.
            return $this->attachments != 0;
        } else {
            return parent::check_file_access($qa, $options, $component,
                    $filearea, $args, $forcedownload);
        }
    }


    /** Return a setting that determines whether or not the specific
     *  feedback display is controlled by the quiz settings or this particular
     *  question.
     * @return bool FEEDBACK_USE_QUIZ, FEEDBACK_SHOW or FEEDBACK_HIDE from constants class.
     */
    public function display_feedback() {
        return isset($this->displayfeedback) ? intval($this->displayfeedback): constants::FEEDBACK_USE_QUIZ;
    }


    /**
     * Grade the given student's response.
     * This implementation assumes a modified behaviour that will accept a
     * third array element in its response, containing data to be cached and
     * served up again in the response on subsequent calls.
     * @param array $response the qt_data for the current pending step. The
     * two relevant keys are '_testoutcome', which is a cached copy of the
     * grading outcome if this response has already been graded and 'answer'
     * (the student's answer) otherwise.
     * @param bool $isprecheck true iff this grading is occurring because the
     * student clicked the precheck button
     * @return 3-element array of the mark (0 - 1), the question_state (
     * gradedright, gradedwrong, gradedpartial, invalid) and the full
     * qtype_coderunner_testing_outcome object to be cached. The invalid
     * state is used when a sandbox error occurs.
     * @throws coding_exception
     */
    public function grade_response(array $response, $isprecheck=false) {
        if ($isprecheck && empty($this->precheck)) {
            throw new coding_exception("Unexpected precheck");
        }
        $language = empty($response['language']) ? '' : $response['language'];
        $gradingreqd = true;
        if (!empty($response['_testoutcome'])) {
            $testoutcomeserial = $response['_testoutcome'];
            $testoutcome = unserialize($testoutcomeserial);
            if ($testoutcome instanceof qtype_coderunner_testing_outcome  // Ignore legacy-format outcomes.
                    && $testoutcome->isprecheck == $isprecheck) {
                $gradingreqd = false;  // Already graded and with same precheck state.
            }
        }
        if ($gradingreqd) {
            // We haven't already graded this submission or we graded it with
            // a different precheck setting. Get the code and the attachments
            // from the response. The attachments is an array with keys being
            // filenames and values being file contents.
            $code = $response['answer'];
            $attachments = $this->get_attached_files($response);
            $testcases = $this->filter_testcases($isprecheck, $this->precheck);
            $runner = new qtype_coderunner_jobrunner();
            $testoutcome = $runner->run_tests($this, $code, $attachments, $testcases, $isprecheck, $language);
            $testoutcomeserial = serialize($testoutcome);
        }

        $datatocache = array('_testoutcome' => $testoutcomeserial);
        if ($testoutcome->run_failed()) {
            return array(0, question_state::$invalid, $datatocache);
        } else if ($testoutcome->all_correct()) {
             return array(1, question_state::$gradedright, $datatocache);
        } else if ($this->allornothing &&
                !($this->grader === 'TemplateGrader' && $this->iscombinatortemplate)) {
            return array(0, question_state::$gradedwrong, $datatocache);
        } else {
            // Allow partial marks if not allornothing or if it's a combinator template grader.
            return array($testoutcome->mark_as_fraction(),
                    question_state::$gradedpartial, $datatocache);
        }
    }


    // Return a map from filename to file contents for all the attached files
    //in the given response.
    private function get_attached_files($response) {
        $attachments = array();
        if (array_key_exists('attachments', $response) && $response['attachments']) {
            $files = $response['attachments']->get_files();
            foreach ($files as $file) {
                $attachments[$file->get_filename()] = $file->get_content();
            }
        }
        return $attachments;
    }


    /**
     * @return an array of result column specifiers, each being a 2-element
     *  array of a column header and the testcase field to be displayed
     */
    public function result_columns() {
        if (isset($this->resultcolumns) && $this->resultcolumns) {
            $resultcolumns = json_decode($this->resultcolumns);
        } else {
            // Use default column headers, equivalent to json_decode of (in English):
            // '[["Test", "testcode"], ["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]]'.
            $resultcolumns = array(
                array(get_string('testcolhdr', 'qtype_coderunner'), 'testcode'),
                array(get_string('inputcolhdr', 'qtype_coderunner'), 'stdin'),
                array(get_string('expectedcolhdr', 'qtype_coderunner'), 'expected'),
                array(get_string('gotcolhdr', 'qtype_coderunner'), 'got'),
            );
        }
        return $resultcolumns;
    }


    // Return an array of all the use_as_example testcases.
    public function example_testcases() {
        return array_filter($this->testcases, function($tc) {
                    return $tc->useasexample;
        });
    }


    // Twig expand all text fields of the question except the templateparam field
    // (which should have been expanded when the question was started) and
    // the template itself.
    // Done only if randomisation is specified within the template params.
    private function twig_all() {
        // Before twig expanding all fields, copy the template parameters
        // into $this->parameters.
        if (!empty($this->templateparams)) {
            $this->parameters = json_decode($this->templateparams);
        } else {
            $this->parameters = array();
        }

        // Twig expand everything in a context that includes the template
        // parameters and the STUDENT and QUESTION objects. The only thing
        // guaranteed about the QUESTION object is the parameters field - use
        // other fields at your peril (since the order in which they are
        // expanded might vary in the future).
        $this->questiontext = $this->twig_expand($this->questiontext);
        $this->generalfeedback = $this->twig_expand($this->generalfeedback);
        $this->answer = $this->twig_expand($this->answer);
        $this->answerpreload = $this->twig_expand($this->answerpreload);
        foreach ($this->testcases as $key => $test) {
            foreach (['testcode', 'stdin', 'expected', 'extra'] as $field) {
                $text = $this->testcases[$key]->$field;
                $this->testcases[$key]->$field = $this->twig_expand($text);
            }
        }
    }

    /**
     * Return Twig-expanded version of the given text. The
     * Twig environment includes the question itself (this) and the template
     * parameters. Additional twig environment parameters are passed in via
     * $twigparams. Template parameters are hoisted if required.
     * @param string $text Text to be twig expanded.
     * @param associative array $twigparams Extra twig environment parameters
     */
    public function twig_expand($text, $twigparams=array()) {
        $twig = qtype_coderunner_twig::get_twig_environment();
        $twigparams['QUESTION'] = $this;
        if ($this->hoisttemplateparams) {
            foreach ($this->parameters as $key => $value) {
                $twigparams[$key] = $value;
            }
        }
        $twigparams['STUDENT'] = new qtype_coderunner_student($this->student);
        return $twig->render($text, $twigparams);
    }

    /**
     * Define the template parameters for this question by Twig-expanding
     * both our own template params and our prototype template params and
     * merging the two.
     * @param type $seed The random number seed to set for Twig randomisation
     */
    private function setup_template_params($seed) {
        $twig = qtype_coderunner_twig::get_twig_environment();
        $twigparams = array('STUDENT' => new qtype_coderunner_student($this->student));
        mt_srand($seed);
        $ournewtemplateparams = $twig->render($this->templateparams, $twigparams);
        if (isset($this->prototypetemplateparams)) {
            $prototypenewtemplateparams = $twig->render($this->prototypetemplateparams, $twigparams);
            $this->templateparams = qtype_coderunner_util::merge_json($prototypenewtemplateparams, $ournewtemplateparams);
        } else {
            // Missing prototype?
            $this->templateparams = $ournewtemplateparams;
        }
    }


    // Extract and return the appropriate subset of the set of question testcases
    // given $isprecheckrun (true iff this was a run initiated by clicking
    // precheck) and the question's prechecksetting (0, 1, 2, 3, 4 for Disable,
    // Empty, Examples, Selected and All respectively).
    protected function filter_testcases($isprecheckrun, $prechecksetting) {
        if (!$isprecheckrun) {
            if ($prechecksetting != constants::PRECHECK_SELECTED) {
                return $this->testcases;
            } else {
                return $this->selected_testcases(false);
            }
        } else { // This is a precheck run.
            if ($prechecksetting == constants::PRECHECK_EMPTY) {
                return array($this->empty_testcase());
            } else if ($prechecksetting == constants::PRECHECK_EXAMPLES) {
                return $this->example_testcases();
            } else if ($prechecksetting == constants::PRECHECK_SELECTED) {
                return $this->selected_testcases(true);
            } else if ($prechecksetting == constants::PRECHECK_ALL) {
                return $this->testcases;
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
            'display'  => 'HIDE',
            'useasexample' => 0,
            'hiderestiffail' => 0,
            'mark'     => 1
        );
    }



    /* ================================================================
     * Interface methods for use by jobrunner.
       ================================================================*/

    // Return the template.
    public function get_template() {
        return $this->template;
    }


    // Return the programming language used to run the code.
    public function get_language() {
        return $this->language;
    }

    // Get the showsource boolean.
    public function get_show_source() {
        return $this->showsource;
    }


    // Return the regular expression used to split the combinator template
    // output into individual tests.
    public function get_test_splitter_re() {
        return $this->testsplitterre;
    }


    // Return whether or not the template is a combinator.
    public function get_is_combinator() {
        return $this->iscombinatortemplate;
    }


    // Return whether or not multiple stdins are allowed when using combinator.
    public function allow_multiple_stdins() {
        return $this->allowmultiplestdins;
    }

    // Return an instance of the sandbox to be used to run code for this question.
    public function get_sandbox() {
        global $CFG;
        $sandbox = $this->sandbox; // Get the specified sandbox (if question has one).
        if ($sandbox === null) {   // No sandbox specified. Use best we can find.
            $sandboxinstance = qtype_coderunner_sandbox::get_best_sandbox($this->language);
            if ($sandboxinstance === null) {
                throw new qtype_coderunner_exception("Language {$this->language} is not available on this system");
            }
        } else {
            $sandboxinstance = qtype_coderunner_sandbox::get_instance($sandbox);
            if ($sandboxinstance === null) {
                throw new qtype_coderunner_exception("Question is configured to use a non-existent or disabled sandbox ($sandbox)");
            }
        }

        return $sandboxinstance;
    }


    // Get an instance of the grader to be used to grade this question.
    public function get_grader() {
        global $CFG;
        $grader = $this->grader == null ? constants::DEFAULT_GRADER : $this->grader;
        if ($grader === 'CombinatorTemplateGrader') { // Legacy grader type.
            $grader = 'TemplateGrader';
            assert($this->iscombinatortemplate);
        }
        $graders = qtype_coderunner_grader::available_graders();
        $graderclass = $graders[$grader];

        $graderinstance = new $graderclass();
        return $graderinstance;
    }


    // Return the support files for this question, namely all the files
    // uploaded with this question itself plus all the files uploaded with the
    // prototype. This does not include files attached to the answer.
    // Returns an associative array mapping filenames to filecontents.
    public function get_files() {
        if ($this->prototypetype != 0) { // Is this a prototype question?
            $files = array(); // Don't load the files twice.
        } else {
            // Load any files from the prototype.
            $this->get_prototype();
            $files = self::get_support_files($this->prototype, $this->prototype->questionid);
        }
        $files = array_merge($files, self::get_support_files($this, $this->id));  // Add in files for this question.
        return $files;
    }


    // Get the sandbox parameters for a run.
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
     * Load the prototype for this question and store in $this->prototype
     */
    public function get_prototype() {
        if (!isset($this->prototype)) {
            $context = qtype_coderunner::question_context($this);
            $this->prototype = qtype_coderunner::get_prototype($this->coderunnertype, $context);
        }
    }


    /**
     *  Return an associative array mapping filename to file contents
     *  for all the support files the given question (which may be a real
     *  question or, in the case of a prototype, the question_options row).
     *  $questionid is the id of the question.
     *  The sample answer files are not included in the return value.
     */
    private static function get_support_files($question, $questionid) {
        global $DB, $USER;

        // If not given in the question object get the contextid from the database.
        if (isset($question->contextid)) {
            $contextid = $question->contextid;
        } else {
            $context = qtype_coderunner::question_context($question);
            $contextid = $context->id;
        }

        $fs = get_file_storage();
        $filemap = array();

        if (isset($question->supportfilemanagerdraftid)) {
            // If we're just validating a question, get files from user draft area.
            $draftid = $question->supportfilemanagerdraftid;
            $context = context_user::instance($USER->id);
            $files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, '', false);
        } else {
            // Otherwise, get the stored support files for this question (not
            // the sample answer files).
            $files = $fs->get_area_files($contextid, 'qtype_coderunner', 'datafile', $questionid);
        }

        foreach ($files as $f) {
            $name = $f->get_filename();
            if ($name !== '.') {
                $filemap[$f->get_filename()] = $f->get_content();
            }
        }
        return $filemap;
    }
}
