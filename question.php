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
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/behaviour/adaptive/behaviour.php');
require_once($CFG->dirroot . '/question/engine/questionattemptstep.php');
require_once($CFG->dirroot . '/question/behaviour/adaptive_adapted_for_coderunner/behaviour.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

use qtype_coderunner\constants;
use qtype_coderunner\coderunner_files;

/**
 * Represents a 'CodeRunner' question.
 */
#[AllowDynamicProperties]
class qtype_coderunner_question extends question_graded_automatically {
    public $testcases = null; // Array of testcases.

    /** @var string containing the language for coderunner type. */
    public $coderunnertype;

    /** @var int 0, 1 or 2 for not-a-prototype, built-in prototype and user-defined prototype */
    public $prototypetype;

    /** @var bool True for All-or-nothing grading */
    public $allornothing;

    /** @var string The penalty regime of the question. */
    public $penaltyregime;

    /** @var int Precheck for the question.
     *  0 = 'disable': no pretest button available,
     *  1 = 'empty' for no actual tests,
     *  2 = 'examples' for all use-as-example tests,
     *  3 = 'selected' for specific selected tests,
     *  4 = 'all' for all tests.
     */
    public $precheck;

    /** @var int Hide check. Non-zero to hide the Check button. */
    public $hidecheck;

    /** @var bool Show source. If true, the Twigged template output is displayed for each run. */
    public $showsource;

    /** @var int|string The number of lines for the answer box. */
    public $answerboxlines = '';

    /** @var string The string that is preloaded into the answer box. */
    public $answerpreload;

    /** @var string Extra data for use by template authors, global to all tests. */
    public $globalextra;

    /** @var bool True if template uses ace. */
    public $useace;

    /** @var string JSON-encoded list of column specifiers. */
    public $resultcolumns;

    /** @var string The question template. */
    public $template;

    /** @var ?bool True if a combinator template is being used. */
    public $iscombinatortemplate;

    /** @var bool True if multiple tests are allowed. */
    public $allowmultiplestdins;

    /** @var string The answer of the question. */
    public $answer;

    /** @var int True to validate the question on save. */
    public $validateonsave = 1;

    /** @var string The regular expression to split output from the combinator run into the basic tests again. */
    public $testsplitterre;

    /** @var string The language of the question. */
    public $language;

    /** @var string The language for the Ace editor */
    public $acelang;

    /** @var mixed The question sandbox. */
    public $sandbox;

    /** @var string The grader instance. */
    public $grader;

    /** @var ?double The allowed CPU time (null unless explicitly set). */
    public $cputimelimitsecs;

    /** @var ?int The allowed memory in MB (null unless explicitly set). */
    public $memlimitmb;

    /** @var string The JSON string used to specify the sandbox parameters. */
    public $sandboxparams;

    /** @var string The template parameters. */
    public $templateparams;

    /** @var bool The hoisted template parameters. */
    public $hoisttemplateparams;

    /** @var bool True if the response is json from which the actual code attribute should be extracted */
    public $extractcodefromjson;

    /** @var ?string The template parameters language. */
    public $templateparamslang;

    /** @var bool The template parameters eval per try. */
    public $templateparamsevalpertry;

    /** @var string The evaluated template parameters (JSON). */
    public $templateparamsevald;

    /** @var ?int True if all question fields need Twig expansion. */
    public $twigall;

    /** @var ?string The UI plugin in use. */
    public $uiplugin;

    /** @var ?string The parameters to pass to the UI plugin*/
    public $uiparameters;

    /** @var ?string The attachments of the question. */
    public $attachments;

    /** @var ?int The number of attachments required. */
    public $attachmentsrequired;

    /** @var ?int Max allowed file size (bytes) */
    public $maxfilesize;

    /** @var ?string Allowed file names (regular expression) */
    public $filenamesregex;

    /** @var ?string Description of file name. */
    public $filenamesexplain;

    /**
     * @var ?int Set to 0 or 1, feedback (result table) is shown.
     * Not if display feedback is set to 2.
     */
    public $displayfeedback;

    /** @var int True if Stop button is to be displayed. */
    public $giveupallowed;

    /** @var ?string Extra data for use by prototype or customised code. */
    public $prototypeextra;

    /** @var ?array The answers of the question (unused - for superclass compatibility only) */
    public $answers;

    /** @var bool Whether the question is customised or not. */
    public $customise;

    /** @var \qtype_coderunner_student Holds student details. */
    public $student;

    /** @var ?\qtype_coderunner_question The question prototype. */
    public $prototype;

    /** @var ?string The initialisation error message. */
    public $initialisationerrormessage;

    /** @var ?array Cache in this to avoid multiple evaluations during question editing and validation.*/
    public $cachedfuncparams;

    /** @var ?string Cache for evaluated template parameters field */
    public $cachedevaldtemplateparams;

    /** @var ?string merged UI parameters */
    public $mergeduiparameters;

    /** @var string The json string of template params. */
    public $templateparamsjson;

    /** @var ?array PHP associative array containing Twig environment variables plus UI plugin parameters*/
    public $parameters;

    /** @var stdClass Object containing step information of the response. */
    public $stepinfo;

    /** @var question_display_options the question options that control display of the question.*/
    public $options;

    /** @var bool */
    public $isnew;

    /** @var int question context id. */
    public $context;

    /** @var int questionid. */
    public $questionid;

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
    public function start_attempt(question_attempt_step $step = null, $variant = null) {
        global $DB, $USER;
        if ($step !== null) {
            parent::start_attempt($step, $variant);
            $userid = $step->get_user_id();
            $this->student = new qtype_coderunner_student($DB->get_record('user', ['id' => $userid]));
            $step->set_qt_var('_STUDENT', serialize($this->student));
        } else {  // Validation, so just use the global $USER as student.
            $this->student = new qtype_coderunner_student($USER);
        }

        $seed = mt_rand();
        if ($step !== null) {
            $step->set_qt_var('_mtrandseed', $seed);
        }
        $this->evaluate_question_for_display($seed, $step);
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
            // was introduced into the code.
            $seed = mt_rand();
        }
        $this->evaluate_question_for_display($seed, $step);
    }


    // Evaluate all templated fields of the question that are required for
    // displaying it to either the student or the author. At very least this will
    // involve evaluating the template parameters using whatever language
    // processor is set by the templateparamslang field. The evaluation
    // defines $this->parameters, which is a PHP associative array containing
    // Twig environment variables plus UI plugin parameters.
    //
    // If Twigall is set, other fields of the question, such as the question
    // text and the various test cases are then twig-expanded using
    // $this->parameters as an environment.
    // We can't really deal with exceptions here - they shouldn't occur
    // normally as questions shouldn't be saved with bad template or UI
    // parameters, but they can occur with legacy questions or due to Jobe server
    // overload when using non-twig template parameter preprocessors.
    // If this happens, we display a message at the start of the question.
    //
    public function evaluate_question_for_display($seed, $step) {
        $this->get_prototype();
        $this->initialisationerrormessage = '';
        try {
            $this->templateparamsjson = $this->evaluate_merged_parameters($seed, $step);
            $this->parameters = json_decode($this->templateparamsjson);
            // TODO ** Consider implications of adding the parameter true to
            // the following, so that the parameters are PHP arrays for Twig.
            if ($this->twigall) {
                $this->twig_all();
            }
            $this->mergeduiparameters = $this->evaluate_merged_ui_parameters();
        } catch (Exception $e) {
            $error = $e->getMessage();
            $this->parameters = ["initerror" => "' . $error . '"];
            $this->templateparamsjson = json_encode($this->parameters);
            $erroroninit = get_string('erroroninit', 'qtype_coderunner', ['error' => $error]);
            $this->initialisationerrormessage = $erroroninit;
        }
    }

    /**
     * Define the question's parameters, which define the environment that
     * is used by Twig for expanding the question's template and (if TwigAll is
     * set) the various other question fields to be displayed to the author
     * or the student.
     * The parameters are defined by running the template parameters field
     * through the appropriate language processor as specified by the
     * templateparamslang field (default: Twig). The result needs to be merged with
     * the prototype's parameters, which are subject to the same process.
     * After running this function, $this->parameters is a stdClass object
     * with all the parameters as attributes.
     *
     * In the simplest case, the template parameters are evaluated when the
     * question is saved and are stored in the question in the database.
     * However, if the "Evaluate on each attempt" checkbox is set (implying
     * randomisation or customisation per student) the template parameters
     * are evaluated once when the question attempt begins. A further
     * complication is that the question author might change the template
     * parameters after students have started a quiz (hopefully in a way that
     * doesn't disrupt the randomisation) and the template parameters will
     * then need to be re-evaluated. This is handled by recording the
     * md5 hash of the template parameters within the question attempt step
     * record in the database, re-evaluating only if the hash changes.
     *
     * If the prototype is missing, process just the template parameters from
     * this question; an error message will be given later.
     * @param int $seed The random number seed to set for Twig randomisation
     * @param question_attempt_step $step The current question attempt step
     * @return string The json string of the merged template parameters.
     */

    public function evaluate_merged_parameters($seed, $step = null) {
        assert(isset($this->templateparams));
        $paramsjson = $this->template_params_json($seed, $step, '_template_params');
        $prototype = $this->prototype;
        if ($prototype !== null && !is_array($prototype) &&  $this->prototypetype == 0) {
            // Merge with prototype parameters (unless this is a prototype or prototype is missing/multiple).
            $prototype->student = $this->student; // Supply this missing attribute.
            $prototypeparamsjson = $prototype->template_params_json($seed, $step, '_prototype__template_params');
            $paramsjson = qtype_coderunner_util::merge_json($prototypeparamsjson, $paramsjson);
        }

        if (empty($paramsjson)) {
            $paramsjson = '{}';
        }

        return $paramsjson;
    }

    /**
     * Evaluate the template parameter field for this question alone (i.e.
     * not including its prototype).
     *
     * @param int $seed the random number seed for this instance of the question
     * @param question_attempt_step $step the current attempt step
     * @param string $qtvar the base name of a qt_variable in which to record
     * the md5 hash of the current template parameters (with suffix '_md5') and the evaluated
     * json (with suffix '_json').
     * @return string The Json template parameters.
     */
    public function template_params_json($seed = 0, $step = null, $qtvar = '') {
        $params = $this->templateparams;
        $lang = $this->templateparamslang;
        if ($step === null) {
            $jsontemplateparams = $this->evaluate_template_params($params, $lang, $seed);
        } else {
            $previousparamsmd5 = $step->get_qt_var($qtvar . '_md5');
            $currentparamsmd5 = md5($params);
            if ($previousparamsmd5 === $currentparamsmd5) {
                $jsontemplateparams = $step->get_qt_var($qtvar . '_json');
            } else {
                $jsontemplateparams = $this->evaluate_template_params($params, $lang, $seed);
                if (!is_a($step, 'question_attempt_step_read_only')) {
                    $step->set_qt_var($qtvar . '_md5', $currentparamsmd5);
                    $step->set_qt_var($qtvar . '_json', $jsontemplateparams);
                }
            }
        }
        return $jsontemplateparams;
    }


    // Evaluate the given template parameters in the context of the given random
    // number seed. Return value is a json string.

    /**
     * Evaluate a template parameter string.
     * @param string $templateparams The template parameter string to evaluate.
     * @param string $lang The language of the template params
     * @param int $seed The random number seed for this question attempt
     * @return string The evaluated JSON string or an error message (which won't be
     * valid json).
     */
    public function evaluate_template_params($templateparams, $lang, $seed) {
        $lang = strtolower($lang); // Just in case some old legacy DB entries escaped.
        if (empty($templateparams)) {
            $jsontemplateparams = '{}';
        } else if (
            isset($this->cachedfuncparams) &&
                $this->cachedfuncparams === ['lang' => $lang, 'seed' => $seed]
        ) {
            // Use previously cached result if possible.
            $jsontemplateparams = $this->cachedevaldtemplateparams;
        } else if ($lang == 'none') {
            $jsontemplateparams = $templateparams;
        } else if ($lang == 'twig') {
            try {
                $jsontemplateparams = $this->twig_render_with_seed($templateparams, $seed);
            } catch (\Twig\Error\Error $e) {
                throw new qtype_coderunner_bad_json_exception($e->getMessage());
            }
        } else if (!$this->templateparamsevalpertry && !empty($this->templateparamsevald)) {
            $jsontemplateparams = $this->templateparamsevald;
        } else {
            $jsontemplateparams = $this->evaluate_template_params_on_jobe($templateparams, $lang, $seed);
        }
        // Cache in this to avoid multiple evaluations during question editing and validation.
        $this->cachedfuncparams = ['lang' => $lang, 'seed' => $seed];
        $this->cachedevaldtemplateparams = $jsontemplateparams;
        return $jsontemplateparams;
    }


    /**
     * Evaluate a template parameter string using a given language on the Jobe
     * server. Return value should be the JSON template parameter string.
     *
     * @param string $templateparams The template parameters to evaluate.
     * @param int $seed The random number seed to use when evaluating.
     * @return string The output from the run.
     */
    private function evaluate_template_params_on_jobe($templateparams, $lang, $seed) {
        $files = $this->get_files();
        $input = '';
        $runargs = ["seed=$seed"];
        foreach (['id', 'username', 'firstname', 'lastname', 'email'] as $key) {
            $value = preg_replace("/[^A-Za-z0-9]/", '', $this->student->$key);
            $runargs[] = "$key=" . $value;
        }
        $sandboxparams = ["runargs" => $runargs, "cputime" => 10];
        $sandbox = $this->get_sandbox();
        $run = $sandbox->execute($templateparams, $lang, $input, $files, $sandboxparams);
        if ($run->error === qtype_coderunner_sandbox::SERVER_OVERLOAD) {
            // Ugly. Probably a major test is running and we overloaded the server.
            $message = get_string('overloadoninit', 'qtype_coderunner');
            throw new qtype_coderunner_overload_exception($message);
        } else if ($run->error !== qtype_coderunner_sandbox::OK) {
            return qtype_coderunner_sandbox::error_string($run);
        } else if ($run->result != qtype_coderunner_sandbox::RESULT_SUCCESS) {
            return qtype_coderunner_sandbox::result_string($run->result) . "\n" . $run->cmpinfo . $run->output . $run->stderr;
        } else {
            return $run->output;
        }
    }


    // Render the given twig text using the given random number seed and
    // student variable. This version should be called only during question
    // initialisation when evaluating the template parameters.
    private function twig_render_with_seed($text, $seed) {
        mt_srand($seed);
        return qtype_coderunner_twig::render($text, $this->student);
    }


    // Get the default ui parameters for the ui plugin and merge in
    // both the prototypes and this questions parameters.
    // In order to support the legacy method of including ui parameters
    // within the template parameters, we need to filter out only the
    // valid ui parameters, so need to load the uiplugin json file to find
    // which ones are supported.
    // The order of evaluation (later values overriding earlier ones) is:
    // built-in defaults; plugin's json defaults; modern prototype ui params;
    // legacy prototype template params; legacy question template params;
    // modern question ui params.
    // Return the the merged parameters as an associative array.
    private function evaluate_merged_ui_parameters() {
        $uiplugin = $this->uiplugin === null ? 'ace' : strtolower($this->uiplugin);
        $uiparams = new qtype_coderunner_ui_parameters($uiplugin);
        // Merge prototype's UI parameters unless prototype is missing or UI plugin has changed.
        if (isset($this->prototype->uiparameters) && strtolower($this->prototype->uiplugin) === $uiplugin) {
            $uiparams->merge_json($this->prototype->uiparameters);
        }
        $uiparams->merge_json($this->templateparamsjson, true); // Legacy support.
        $uiparams->merge_json($this->uiparameters);
        return $uiparams->updated_params();
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

    /**
     * What data may be included in the form submission when a student submits
     * this question in its current state?
     *
     * @return array|string variable name => PARAM_... constant
     */
    public function get_expected_data() {
        $expecteddata = ['answer' => PARAM_RAW,
                     'language' => PARAM_NOTAGS]; // NOTAGS => any HTML is stripped.
        if ($this->attachments != 0) {
            $expecteddata['attachments'] = question_attempt::PARAM_FILES;
        }
        return $expecteddata;
    }


    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            $ans = $response['answer'];
            if ($this->extractcodefromjson) {
                $json = json_decode($ans, true);
                if ($json !== null && isset($json[constants::ANSWER_CODE_KEY])) {
                    $ans = $json[constants::ANSWER_CODE_KEY][0];
                }
            }
            return $ans;
        } else {
            return null;
        }
    }


    public function validate_response(array $response) {
        // Check the response and return a validation error message if it's
        // faulty or an empty string otherwise.

        // First check the attachments.
        $hasattachments = array_key_exists('attachments', $response)
            && $response['attachments'] instanceof question_response_files;
        if ($hasattachments) {
            $attachmentfiles = $response['attachments']->get_files();
            $attachcount = count($attachmentfiles);
            // Check the filetypes.
            $invalidfiles = [];
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

        if ($attachcount == 0) { // If no attachments, require an answer.
            $hasanswer = array_key_exists('answer', $response);
            if (!$hasanswer || strlen($response['answer']) == 0) {
                return get_string('answerrequired', 'qtype_coderunner');
            } else if (strlen($response['answer']) < constants::FUNC_MIN_LENGTH) {
                return get_string('answertooshort', 'qtype_coderunner', constants::FUNC_MIN_LENGTH);
            } else if (trim($response['answer']) == trim($this->answerpreload)) {
                return get_string('answerunchanged', 'qtype_coderunner');
            }
        }
        return '';  // All good.
    }

    // Return true iff the given filename is valid, meaning it matches the
    // regex (if given), contains only alphanumerics plus '-', '_' and '.',
    // doesn't clash with any of the support files and doesn't
    // start with double underscore..
    private function is_valid_filename($filename, $regex, $supportfiles) {
        if (strpos($filename, '__') === 0) {
            return false;  // Dunder names are reserved for runtime task.
        }
        if (!ctype_alnum(str_replace(['-', '_', '.'], '', $filename))) {
            return false;  // Filenames must be alphanumeric plus '.', '-', or '_'.
        }
        if (!empty($regex) && preg_match('=^' . $this->filenamesregex . '$=', $filename) !== 1) {
            return false;  // Filename doesn't match given regex.
        }
        foreach (array_keys($supportfiles) as $supportfilename) {
            if ($supportfilename == $filename) {
                return false;  // Filename collides with a support file name.
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
        $error = $this->validate_response($response);
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
            $prevresponse,
            $newresponse,
            'answer'
        ) &&
                question_utils::arrays_same_at_key_missing_is_blank(
                    $prevresponse,
                    $newresponse,
                    'language'
                );
        $attachments1 = $this->get_attached_files($prevresponse);
        $attachments2 = $this->get_attached_files($newresponse);
        $sameattachments = $attachments1 === $attachments2;
        return $sameanswer && $sameattachments;
    }


    public function get_correct_response() {
        $response = $this->get_correct_answer();
        if ($this->attachments) {
            $response['attachments'] = $this->make_attachments_saver();
        }
        return $response;
    }


    public function get_correct_answer() {
        // Return the sample answer, if supplied.
        if (!isset($this->answer)) {
            return null;
        } else {
            $answer = ['answer' => $this->answer];
            // Get any sample question files first.
            $context = qtype_coderunner::question_context($this);
            $contextid = $context->id;
            // For multilanguage questions we also need to specify the language.
            // Use the answer_language template parameter value if given, otherwise
            // run with the default.
            $params = $this->parameters;
            if (!empty($params->answer_language)) {
                $answer['language'] = $params->answer_language;
            } else if (!empty($this->acelang) && strpos($this->acelang, ',') !== false) {
                [$langs, $defaultlang] = qtype_coderunner_util::extract_languages($this->acelang);
                $default = empty($defaultlang) ? $langs[0] : $defaultlang;
                $answer['language'] = $default;
            }
            return $answer;
        }
    }


    /**
     * Creates an empty draft area for attachments.
     * @return int The draft area's itemid.
     */
    protected function make_attachment_draft_area() {
        $draftid = 0;
        $contextid = 0;

        $component = 'question';
        $filearea = 'response_attachments';

        // Create an empty file area.
        file_prepare_draft_area($draftid, $contextid, $component, $filearea, null);
        return $draftid;
    }


    /**
     * Adds the given file to the given draft area.
     * @param int $draftid The itemid for the draft area in which the file should be created.
     * @param string $file The file to be added.
     */
    protected function make_attachment($draftid, $file) {
        global $USER;

        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);

        // Create the file in the provided draft area.
        $fileinfo = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftid,
            'filepath'  => '/',
            'filename'  => $file->get_filename(),
        ];
        $fs->create_file_from_string($fileinfo, $file->get_content());
    }


    /**
     * Generates a draft file area that contains the sample answer attachments.
     * @return int The itemid of the generated draft file area or null if there are
     * no sample answer attachments.
     */
    public function make_attachments() {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->contextid, 'qtype_coderunner', 'samplefile', $this->id);
        $usefulfiles = [];
        foreach ($files as $file) {  // Filter out useless '.' files.
            if ($file->get_filename() !== '.') {
                $usefulfiles[] = $file;
            }
        }

        if (count($usefulfiles) > 0) {
            $draftid = $this->make_attachment_draft_area();
            foreach ($usefulfiles as $file) {
                $this->make_attachment($draftid, $file);
            }
            return $draftid;
        } else {
            return null;
        }
    }


    /**
     * Generates a question_file_saver that contains all the sample answer attachments.
     *
     * @return question_file_saver a question_file_saver that contains the
     * sample answer attachments.
     */
    public function make_attachments_saver() {
        $attachments = $this->make_attachments();
        if ($attachments) {
            return new question_file_saver($attachments, 'question', 'response_attachments');
        } else {
            return null;
        }
    }


    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'response_attachments') {
            // Response attachments visible if the question has them.
            return $this->attachments != 0;
        } else {
            return parent::check_file_access(
                $qa,
                $options,
                $component,
                $filearea,
                $args,
                $forcedownload
            );
        }
    }


    /** Return a setting that determines whether or not the specific
     *  feedback display is controlled by the quiz settings or this particular
     *  question.
     * @return int FEEDBACK_USE_QUIZ, FEEDBACK_SHOW or FEEDBACK_HIDE from constants class.
     */
    public function display_feedback() {
        return isset($this->displayfeedback) ? intval($this->displayfeedback) : constants::FEEDBACK_USE_QUIZ;
    }


    /**
     * Grade the given student's response.
     * This implementation assumes a modified behaviour that will accept a
     * third array element in its response, containing data to be cached and
     * served up again in the response on subsequent calls.
     * @param array $response the qt_data for the current pending step. The
     * main relevant keys are '_testoutcome', which is a cached copy of the
     * grading outcome if this response has already been graded and 'answer'
     * (the student's answer) otherwise. Also present are 'numchecks',
     * 'numprechecks' and 'fraction' which relate to the current (pending) step and
     * the history of prior submissions.
     * @param bool $isprecheck true iff this grading is occurring because the
     * student clicked the precheck button
     * @param bool $isvalidationrun true iff this is a validation run when saving 
     * a question.
     * @return 3-element array of the mark (0 - 1), the question_state (
     * gradedright, gradedwrong, gradedpartial, invalid) and the full
     * qtype_coderunner_testing_outcome object to be cached. The invalid
     * state is used when a sandbox error occurs.
     * @throws coding_exception
     */
    public function grade_response(array $response, bool $isprecheck = false, $isvalidationrun = false) {
        if ($isprecheck && empty($this->precheck)) {
            throw new coding_exception("Unexpected precheck");
        }
        $language = empty($response['language']) ? '' : $response['language'];
        $gradingreqd = true;
        if (!empty($response['_testoutcome'])) {
            $testoutcomeserial = $response['_testoutcome'];
            $testoutcome = unserialize($testoutcomeserial);
            if (
                $testoutcome instanceof qtype_coderunner_testing_outcome  // Ignore legacy-format outcomes.
                    && $testoutcome->isprecheck == $isprecheck
            ) {
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
            if ($isvalidationrun) {
                $testcases = $this->testcases;
            } else {
                $testcases = $this->filter_testcases($isprecheck, $this->precheck);
            }
            $runner = new qtype_coderunner_jobrunner();
            $this->stepinfo = self::step_info($response);
            if (isset($response['graderstate'])) {
                $this->stepinfo->graderstate = $response['graderstate'];
            } else {
                $this->stepinfo->graderstate = '';
            }
            $testoutcome = $runner->run_tests($this, $code, $attachments, $testcases, $isprecheck, $language);
            $testoutcomeserial = serialize($testoutcome);
        }

        $datatocache = ['_testoutcome' => $testoutcomeserial];
        if ($testoutcome->run_failed()) {
            return [0, question_state::$invalid, $datatocache];
        } else if ($testoutcome->all_correct()) {
             return [1, question_state::$gradedright, $datatocache];
        } else if (
            $this->allornothing &&
                !($this->grader === 'TemplateGrader' && $this->iscombinatortemplate)
        ) {
            return [0, question_state::$gradedwrong, $datatocache];
        } else {
            // Allow partial marks if not allornothing or if it's a combinator template grader.
            return [$testoutcome->mark_as_fraction(),
                    question_state::$gradedpartial, $datatocache];
        }
    }


    // Return a map from filename to file contents for all the attached files
    // in the given response.
    private function get_attached_files($response) {
        $attachments = [];
        if (array_key_exists('attachments', $response) && $response['attachments']) {
            $files = $response['attachments']->get_files();
            foreach ($files as $file) {
                if ($file->get_filename() !== ".") {
                    $attachments[$file->get_filename()] = $file->get_content();
                }
            }
        }
        return $attachments;
    }


    /** Pulls out the step information in the response, added by the CodeRunner
    /*  custom behaviour, for use by the question author in issuing feedback.
     *
     * @param type $response The usual response array enhanced by the addition of
     * numchecks, numprechecks and fraction values relating to the current step.
     * @return stdClass object with the numchecks, numprechecks and fraction
     * attributes.
     */

    private static function step_info($response) {
        $stepinfo = new stdClass();
        foreach (['numchecks', 'numprechecks', 'fraction', 'preferredbehaviour'] as $key) {
            $value = isset($response[$key]) ? $response[$key] : 0;
            $stepinfo->$key = $value;
        }
        $stepinfo->coderunnerversion = get_config('qtype_coderunner')->version;
        return $stepinfo;
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
            $resultcolumns = [
                [get_string('testcolhdr', 'qtype_coderunner'), 'testcode'],
                [get_string('inputcolhdr', 'qtype_coderunner'), 'stdin'],
                [get_string('expectedcolhdr', 'qtype_coderunner'), 'expected'],
                [get_string('gotcolhdr', 'qtype_coderunner'), 'got'],
            ];
        }
        return $resultcolumns;
    }


    // Return an array of all the use_as_example testcases.
    public function example_testcases() {
        return array_filter($this->testcases, function ($tc) {
                    return $tc->useasexample;
        });
    }


    // Twig expand all text fields of the question except the templateparam field
    // (which should have been expanded when the question was started) and
    // the template itself.
    // Done only if the Twig All checkbox is checked.
    private function twig_all() {
        // Twig expand everything in a context that includes the template
        // parameters and the STUDENT and QUESTION objects.
        $this->questiontext = $this->twig_expand($this->questiontext);
        $this->generalfeedback = $this->twig_expand($this->generalfeedback);
        $this->answer = $this->twig_expand($this->answer);
        $this->answerpreload = $this->twig_expand($this->answerpreload);
        $this->globalextra = $this->twig_expand($this->globalextra);
        $this->prototypeextra = $this->twig_expand($this->prototypeextra);
        if (!empty($this->uiparameters)) {
            $this->uiparameters = $this->twig_expand($this->uiparameters);
        }
        foreach (array_keys($this->testcases) as $key) {
            foreach (['testcode', 'stdin', 'expected', 'extra'] as $field) {
                $text = $this->testcases[$key]->$field;
                $this->testcases[$key]->$field = $this->twig_expand($text);
            }
        }
    }

    // Return a stdObject pseudo-clone of this question with only the fields
    // documented in the README.md, for use in Twig expansion.
    // HACK ALERT - the field uiparameters exported to the Twig context is
    // actually the mergeduiparameters field, just as the parameters field
    // is the merged template parameters. [Where merging refers to the combining
    // of the prototype and the question].
    protected function sanitised_clone_of_this() {
        $clone = new stdClass();
        $fieldsrequired = ['id', 'name', 'questiontext', 'generalfeedback',
            'generalfeedbackformat', 'testcases',
            'answer', 'answerpreload', 'language', 'globalextra', 'prototypeextra', 'useace', 'sandbox',
            'grader', 'cputimelimitsecs', 'memlimitmb', 'sandboxparams',
            'parameters', 'resultcolumns', 'allornothing', 'precheck',
            'hidecheck', 'penaltyregime', 'iscombinatortemplate',
            'allowmultiplestdins', 'acelang', 'uiplugin', 'attachments',
            'attachmentsrequired', 'displayfeedback', 'stepinfo'];
        foreach ($fieldsrequired as $field) {
            if (isset($this->$field)) {
                $clone->$field = $this->$field;
            } else {
                $clone->$field = null;
            }
        }
        if (isset($this->mergeduiparameters)) { // Only available at execution time.
            $clone->uiparameters = $this->mergeduiparameters;
        }
        $clone->questionid = $this->id; // Legacy support.
        return $clone;
    }

    /**
     * Return Twig-expanded version of the given text.
     * Twig environment includes the question itself (this) and, if template
     * parameters are to be hoisted, the (key, value) pairs in $this->parameters.
     * @param string $text Text to be twig expanded.
     */
    public function twig_expand($text, $context = []) {
        if (empty(trim($text ?? ''))) {
            return $text;
        } else {
            $context['QUESTION'] = $this->sanitised_clone_of_this();
            if ($this->hoisttemplateparams) {
                foreach ($this->parameters as $key => $value) {
                    $context[$key] = $value;
                }
            }
            return qtype_coderunner_twig::render($text, $this->student, $context);
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
                return [$this->empty_testcase()];
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
        $testcases = [];
        foreach ($this->testcases as $testcase) {
            if (
                ($isprecheckrun && $testcase->testtype != constants::TESTTYPE_NORMAL) ||
                (!$isprecheckrun && $testcase->testtype != constants::TESTTYPE_PRECHECK)
            ) {
                $testcases[] = $testcase;
            }
        }
        return $testcases;
    }


    // Return an empty testcase - an artifical testcase with all fields
    // empty or zero except for a mark of 1.
    private function empty_testcase() {
        return (object) [
            'testtype' => 0,
            'testcode' => '',
            'stdin'    => '',
            'expected' => '',
            'extra'    => '',
            'display'  => 'HIDE',
            'useasexample' => 0,
            'hiderestiffail' => 0,
            'mark'     => 1,
        ];
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
            $files = []; // Don't load the files twice.
        } else {
            // Load any files from the prototype.
            $this->get_prototype();
            $files = self::get_support_files($this->prototype);
        }
        $files = array_merge($files, self::get_support_files($this));  // Add in files for this question.
        return $files;
    }


    // Get the sandbox parameters for a run.
    public function get_sandbox_params() {
        if (isset($this->sandboxparams)) {
            $sandboxparams = json_decode($this->sandboxparams, true);
        } else {
            $sandboxparams = [];
        }

        if (isset($this->cputimelimitsecs)) {
            $sandboxparams['cputime'] = intval($this->cputimelimitsecs);
        }
        if (isset($this->memlimitmb)) {
            $sandboxparams['memorylimit'] = intval($this->memlimitmb);
        }
        return $sandboxparams;
    }


    /**
     * Load the prototype for this question and store in $this->prototype
     */
    public function get_prototype() {
        if (isset($this->prototype)) {
            return;  // Nothing to do.
        }
        if ($this->prototypetype == 0) {
            $context = qtype_coderunner::question_context($this);
            $this->prototype = qtype_coderunner::get_prototype($this->coderunnertype, $context);
        } else {
            $this->prototype = null;
        }
    }

    /**
     *  Return an associative array mapping filename to file contents
     *  for all the support files for the given question.
     *  The sample answer files are not included in the return value.
     */
    private static function get_support_files($question) {
        global $USER;

        // If not given in the question object get the contextid from the database.
        if (isset($question->contextid)) {
            $contextid = $question->contextid;
        } else {
            $context = qtype_coderunner::question_context($question);
            $contextid = $context->id;
        }

        $fs = get_file_storage();
        $filemap = [];

        if (isset($question->supportfilemanagerdraftid)) {
            // If we're just validating a question, get files from user draft area.
            $draftid = $question->supportfilemanagerdraftid;
            $context = context_user::instance($USER->id);
            $files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, '', false);
        } else {
            // Otherwise, get the stored support files for this question (not
            // the sample answer files).
            $files = $fs->get_area_files($contextid, 'qtype_coderunner', 'datafile', $question->id);
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
