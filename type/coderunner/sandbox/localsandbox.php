<?php
/** A LocalSandbox is a subclass of the base qtype_coderunner_sandbox class, 
 *  representing a sandbox that runs on the local server, performing compilation locally,
 *  caching compiled files, and processing the entire submission in a single
 *  call, rather than queueing the task for asynchronous procesing or
 *  sending it to a remove web service.
 *  It is assumed that an instance of the local sandbox will be created for
 *  each question run, though possibly not for each testcase, and that each
 *  call to createSubmission will run to completion before returning. Those
 *  conditions ensure that only one submission is running at a time on a particular
 *  sandbox, which allows caching of question-related information in the sandbox
 *  itself during submission.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2012, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');
require_once($CFG->dirroot . '/question/type/coderunner/sandbox/sandboxbase.php');


//******************************************************************
//
// LocalSandbox Class
//
//******************************************************************

abstract class qtype_coderunner_localsandbox extends qtype_coderunner_sandbox {

    private static $currentRunId = '99';  // The only one we ever use
    protected $date = null;         // Current date/time
    protected $input = null;        // Standard input for the current task
    protected $language = null;     // The language of the current task

    public function __construct($user=null, $pass=null) {
        qtype_coderunner_sandbox::__construct($user, $pass);
    }




    /** Implement the abstract createSubmission method, which mimics the
     *  ideone function of the same name. Following the ideone API, a call
     *  to this method would generally be followed by calls to
     *  getSubmissionStatus and getSubmissionDetails, using the return 'link'.
     *  This implementation does a compile (if necessary) and run,
     *  rather than queuing the task. Results from this are stored in the
     *  object's instance fields for use by getSubmissionDetails.
     *
     *  Since a new object of this class will be created for each student
     *  submission of a question, multiple calls to createSubmission should
     *  always be with the same language, but not necessarily the same
     *  sourceCode as this may vary per testcase.
     *
     * @param string $sourceCode
     * @param string $language -- must be one of the entries in $LANGUAGES above
     * @param string $input -- stdin for use when running the program
     * @param boolean $run -- hook for ideone com
     * @param boolean $private -- hook for ideone compatibility (not used)
     * @param associative array $params -- sandbox parameters. See base class.
     * @return object with 'error' field (always '') and 'link' field (always $currentRunId above)
     * @throws coding_exception if I've goofed
     *
     */
    public function create_submission($sourceCode, $language, $input,
                            $run=true, $private=true, $files=null, $params = null) {
        if (!in_array($language, $this->get_languages())) {
            throw new coderunner_exception('LocalSandbox::createSubmission: Bad language');
        }

        if (!$run || !$private) {
            throw new coderunner_exception('LocalSandbox::createSubmission: unexpected param value');
        }

        // Record input data in $this in case requested in call to getSubmissionDetails,
        // and also for use by LanguageTask if desired, via its reference
        // back to $this.
        $this->date = date("Y-m-d H-i-s");
        $this->input = $input;
        $this->language = $language;
        $this->params = $params;
        if (!isset($this->currentSource) || $this->currentSource !== $sourceCode) {
            // Only need to save source code and consider recompiling etc
            // if sourcecode changes between tests
            if (isset($this->task)) {
                $this->task->close();  // Clean up if any existing task
            }
            $this->currentSource = $sourceCode;
            $this->task = $this->createTask($language, $sourceCode);
            $this->task->compile();
        }

        if ($this->task->cmpinfo === '') {
            $this->runInSandbox($input, $files);
        }
        else {
            $this->task->result = qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR;
        }

        return (object) array('error' => qtype_coderunner_sandbox::OK, 'link' => self::$currentRunId);
    }


    public function get_submission_status($link) {
        if (!isset($this->task) || $link !== self::$currentRunId) {
            return (object) array('error' => qtype_coderunner_sandbox::PASTE_NOT_FOUND);
        } else {
            return (object) array('error' => qtype_coderunner_sandbox::OK,
                         'status' => qtype_coderunner_sandbox::STATUS_DONE,
                         'result' => $this->task->result);
        }
    }


    public function get_submission_details($link, $withSource=false,
            $withInput=false, $withOutput=true, $withStderr=true,
            $withCmpinfo=true) {

        if (!isset($this->task) || $link !== self::$currentRunId) {
            return (object) array('error' => qtype_coderunner_sandbox::PASTE_NOT_FOUND);
        } else {
            $retVal = (object) array(
                'error'     => qtype_coderunner_sandbox::OK,
                'status'    => qtype_coderunner_sandbox::STATUS_DONE,
                'result'    => $this->task->result,
                'langId'    => array_search($this->language, $this->get_languages()),
                'langName'  => $this->language,
                'langVersion' => $this->task->getVersion(),
                'time'      => $this->task->time,
                'date'      => $this->date,
                'memory'    => $this->task->memory,
                'signal'    => $this->task->signal,
                'public'    => false);

            if ($withSource) {
                $retVal->source = $this->currentSource;
            }
            if ($withInput) {
                $retVal->input = $this->input;
            }
            if ($withOutput) {
                $retVal->output = $this->task->output;
            }
            if ($withStderr) {
                $retVal->stderr = $this->task->stderr;
            }
            if ($withCmpinfo) {
                $retVal->cmpinfo = $this->task->cmpinfo;
            }
            return $retVal;
        }
    }


    // On close, delete the last compiler output file (if we have one).
    public function close() {
        if (isset($this->task)) {
            $this->task->close();
        }
        unset($this->task);
    }


    /**
     * Generate a set of files in the current directory as defined by the
     * $files parameter.
     * @param type $files an associative map from filename to file contents.
     */
    protected function loadFiles($files) {
        if ($files !== null) {
            foreach ($files as $filename=>$contents) {
                file_put_contents($filename, $contents);
            }
        }
    }


    // Create and return a LanguageTask object for the given language and
    // the given source code. If the $files parameter is non-null it must be
    // an associative array mapping filename to filecontents; a set of such
    // files is built in the local execution environment.
    protected abstract function createTask($language, $source);


    // Run the given command in the sandbox with the given set of files
    // in the local working directory.
    protected abstract function runInSandbox($input, $files);


}
?>
