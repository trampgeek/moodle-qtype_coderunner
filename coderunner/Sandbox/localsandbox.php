<?php
/** A LocalSandbox is a subclass of the base Sandbox class, representing
 *  a sandbox that runs on the local server, performing compilation locally,
 *  caching compiled files, and processing the entire submission in a single
 *  call, rather than queueing the task for asynchronous procesing of
 *  sending it to a remove web service.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2012, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('sandboxbase.php');


// A LanguageTask encapsulates the language specific behaviour associated
// with compiling and running a particular bit of source code. It is subclassed
// for each provided language.
abstract class LanguageTask {
    public $cmpinfo = '';  // Output from compilation
    public $time = 0;      // Execution time (secs)
    public $memory = 0;    // Memory used (MB)
    public $signal = 0;
    public $output = '';   // Output from execution
    public $stderr = '';
    public $result = Sandbox::RESULT_NO_RUN;
    public $workdir = '';  // The temporary working directory created in constructor

    // For all languages it is necessary to store the source code in a
    // temporary file when constructing the task. A temporary directory
    // is made to hold the source code.
    public function __construct($sourceCode) {
        $this->workdir = tempnam("/tmp", "coderunner_");
        if (!unlink($this->workdir) || !mkdir($this->workdir)) {
            throw new coding_exception("LanguageTask: error making temp directory (race error?)");
        }
        $this->sourceFileName = "sourceFile";
        chdir($this->workdir);
        $handle = fopen($this->sourceFileName, "w");
        fwrite($handle, $sourceCode);
        fclose($handle);
    }


    // Compile the current source file in the current directory, saving
    // the compiled output in a file $this->executableFileName.
    // Sets $this->cmpinfo accordingly.
    protected abstract function compile();


    // Return the Linux command to use to run the current job with the given
    // standard input. It's an array of string arguments, suitable
    // for passing to the LiuSandbox.
    public abstract function getRunCommand();


    // Return the version of language supported by this particular Language/Task
    public abstract function getVersion();


    // Return the list of readable directories allowed when running this
    // task in the sandbox.
    public abstract function readableDirs();


    // Override the following function if the output from executing a program
    // in this language needs post-filtering to remove stuff like
    // header output.
    public function filterOutput($out) {
        return $out;
    }


    public function close() {
        if (!isset($this->sourceFileName)) {
            throw new coding_exception('LanguageTask::close(): no source file');
        }
        $this->delTree($this->workdir);
    }


    // Delete a given directory tree
    private function delTree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}

//******************************************************************
//
// LocalSandbox Class
//
//******************************************************************

abstract class LocalSandbox extends Sandbox {

    private static $currentRunId = '99';  // The only one we ever use

    public function __construct($user=NULL, $pass=NULL) {
        Sandbox::__construct($user, $pass);
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
     * @return object with 'error' field (always '') and 'link' field (always $currentRunId above)
     * @throws coding_exception if I've goofed
     *
     */
    public function createSubmission($sourceCode, $language, $input,
                                        $run=TRUE, $private=TRUE) {
        if (!in_array($language, $this->getLanguages()->languages)) {
            throw new coding_exception('LocalSandbox::createSubmission: Bad language');
        }

        if (!$run || !$private) {
            throw new coding_exception('LocalSandbox::createSubmission: unexpected param value');
        }

        // Record input data in $this in case requested in call to getSubmissionDetails
        $this->date = date("Y-m-d H-i-s");
        $this->input = $input;
        $this->language = $language;
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
            $this->runInSandbox($input);
        }
        else {
            $this->task->result = Sandbox::RESULT_COMPILATION_ERROR;
        }

        return (object) array('error' => Sandbox::OK, 'link' => self::$currentRunId);
    }


    public function getSubmissionStatus($link) {
        if (!isset($this->task) || $link !== self::$currentRunId) {
            return (object) array('error' => Sandbox::PASTE_NOT_FOUND);
        } else {
            return (object) array('error' => Sandbox::OK,
                         'status' => Sandbox::STATUS_DONE,
                         'result' => $this->task->result);
        }
    }


    public function getSubmissionDetails($link, $withSource=FALSE,
            $withInput=FALSE, $withOutput=TRUE, $withStderr=TRUE,
            $withCmpinfo=TRUE) {

        if (!isset($this->task) || $link !== self::$currentRunId) {
            return (object) array('error' => Sandbox::PASTE_NOT_FOUND);
        } else {
            $retVal = (object) array(
                            'error'     => Sandbox::OK,
                            'status'    => Sandbox::STATUS_DONE,
                            'result'    => $this->task->result,
                            'langId'    => array_search($this->language, $this->getLanguages()->languages),
                            'langName'  => $this->language,
                            'langVersion' => $this->task->getVersion(),
                            'time'      => $this->task->time,
                            'date'      => $this->date,
                            'memory'    => $this->task->memory,
                            'signal'    => $this->task->signal,
                            'public'    => FALSE);

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


    // Create and return a LanguageTask object for the given language and
    // the given source code.
    protected abstract function createTask($language, $source);


    // Run the given command in the sandbox.
    protected abstract function runInSandbox($input);

}
?>
