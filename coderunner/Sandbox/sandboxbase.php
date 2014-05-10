<?php
/** The base class for the CodeRunner Sandbox classes.
 *  Essentially just defines the API, which is heavily based on the ideone
 *  API, which should be consulted for details:
 *  see ideone.com/files/ideone-api.pdf
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2012, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// TODO: provide a mechanism to check that a sandbox recognises all the
// non-null parameters it has been given (in particular the 'files' param).

abstract class Sandbox {
    protected $user;     // Username supplied when constructing
    protected $password; // Password supplied when constructing
    protected $authenticationError;

    // Symbolic constants as per ideone API
    const OK           = 0;
    const AUTH_ERROR   = 1;
    const PASTE_NOT_FOUND = 2;  // Link to a non-existent submission
    const WRONG_LANG_ID   = 3;  // No such language
    const ACCESS_DENIED   = 4;  // Only if using ideone or jobe
    const CANNOT_SUBMIT_THIS_MONTH_ANYMORE = 5; // Ideone only
    const CREATE_SUBMISSION_FAILED = 6; // Failed on call to CREATE_SUBMISSION
    const UNKNOWN_SERVER_ERROR = 7;
    const HTTP_ERROR = 8; 

    const STATUS_WAITING     = -1;
    const STATUS_DONE        = 0;
    const STATUS_COMPILING   = 1;
    const STATUS_RUNNING     = 3;

    const RESULT_NO_RUN      = 0;
    const RESULT_SUCCESS2    = 0; // Used by Jobe
    const RESULT_COMPILATION_ERROR = 11;
    const RESULT_RUNTIME_ERROR = 12;
    const RESULT_TIME_LIMIT   = 13;
    const RESULT_SUCCESS      = 15;
    const RESULT_MEMORY_LIMIT    = 17;
    const RESULT_ILLEGAL_SYSCALL = 19;
    const RESULT_INTERNAL_ERR = 20;

    // Additions to ideone API for Liu Sandbox compatibility
    const RESULT_SANDBOX_PENDING = 21; // Sandbox PD error
    const RESULT_SANDBOX_POLICY = 22; // Sandbox BP error
    const RESULT_OUTPUT_LIMIT = 30;
    const RESULT_ABNORMAL_TERMINATION = 31;


    const POLL_INTERVAL = 3;     // secs to wait for sandbox done
    const MAX_NUM_POLLS = 40;    // No more than 120 seconds waiting


    public function __construct($user=NULL, $pass=NULL) {
        $this->user = $user;
        $this->pass = $pass;
        $authenticationError = FALSE;
    }

    public static function resultString($resultCode) {
        $RESULT_STRINGS = array(
            Sandbox::RESULT_NO_RUN               => "No run",
            Sandbox::RESULT_COMPILATION_ERROR    => "Compilation error",
            Sandbox::RESULT_RUNTIME_ERROR        => "Runtime error",
            Sandbox::RESULT_TIME_LIMIT           => "Time limit exceeded",
            Sandbox::RESULT_SUCCESS              => "OK",
            Sandbox::RESULT_MEMORY_LIMIT         => "Memory limit exceeded",
            Sandbox::RESULT_ILLEGAL_SYSCALL      => "Illegal function call",
            Sandbox::RESULT_INTERNAL_ERR         => "CodeRunner error (IE): please tell a tutor",
            Sandbox::RESULT_SANDBOX_PENDING      => "CodeRunner error (PD): please tell a tutor",
            Sandbox::RESULT_SANDBOX_POLICY       => "CodeRunner error (BP): please tell a tutor",
            Sandbox::RESULT_OUTPUT_LIMIT         => "Excessive output",
            Sandbox::RESULT_ABNORMAL_TERMINATION => "Abnormal termination"
        );
        if (!isset($RESULT_STRINGS[$resultCode])) {
            throw new coding_exception("Bad call to sandbox.resultString");
        }
        return $RESULT_STRINGS[$resultCode];
    }

    // Returns an object containing an error field and a languages field,
    // where the latter is a list of strings of languages handled by this sandbox.
    // This latter consists of all the languages returned by a query to Ideone plus
    // the local simplified aliases, like python2, python3, C.
    abstract public function getLanguages();

    // Create a submission object, which has an error and a link field, the
    // latter being the 'handle' by which the submission is subsequently
    // referred to. Error codes are as defined by the first block of symbolic
    // constants above (the values 0 through 6). These are
    // exactly the values defined by the ideone api, with a couple of additions.
    // The $files parameter is an addition to the Ideone-based interface to
    // allow for providing a set of files for use at runtime. It is an
    // associative array mapping filename to filecontents (or NULL for no files).
    // The $params parameter is also an addition to the Ideone-based interface to
    // allow for setting sandbox parameters. It's an associative array, with
    // a sandbox-dependent set of keys, although all except the Ideone sandbox
    // should recognise at least the keys 'cputime' (CPU time limit, in seconds)
    // 'memorylimit' (in megabytes) and 'files' (an associative array mapping
    // filenames to string filecontents).
    abstract public function createSubmission($sourceCode, $language, $input,
            $run=TRUE, $private=TRUE, $files=NULL, $params=NULL);

    // Enquire about the status of the submission with the given 'link' (aka
    // handle. The return value is an object containing an error and a result
    // field, the values of which are given by the symbolic constants above.
    abstract public function getSubmissionStatus($link);

    // Should only be called if the status is STATUS_DONE. Returns an ideone
    // style object with fields error, langId, langName, langVersion, time,
    // date, status, result, memory, signal, cmpinfo, output.
    abstract public function getSubmissionDetails($link, $withSource=FALSE,
            $withInput=FALSE, $withOutput=TRUE, $withStderr=TRUE,
            $withCmpinfo=TRUE);

    /** Main interface function for use by coderunner but not part of ideone API.
     *  Executes the given source code in the given language with the given
     *  input and returns an object with fields error, result, time,
     *  memory, signal, cmpinfo, stderr, output.
     * @param string $sourceCode The source file to compile and run
     * @param string $language  One of the languages regognised by the sandbox
     * @param string $input A string to use as standard input during execution
     * @param associative array $files either NULL or a map from filename to
     *         file contents, defining a file context at execution time
     * @param associative array $params Sandbox parameters, depends on
     *         particular sandbox but most sandboxes should recognise
     *         at least cputime (secs), memorylimit (Megabytes) and
     *         files (an associative array mapping filenames to string
     *         filecontents.
     *         If the $params array is NULL, sandbox defaults are used.
     */
    public function execute($sourceCode, $language, $input, $files=NULL, $params = NULL) {
        if (!in_array($language, $this->getLanguages()->languages)) {
            throw new coding_exception('Executing an unsupported language in sandbox');
        }
        $result = $this->createSubmission($sourceCode, $language, $input,
                TRUE, TRUE, $files, $params);
        $error = $result->error;
        if ($error === Sandbox::OK) {
            $state = $this->getSubmissionStatus($result->link);
            $error = $state->error;
        }

        if ($error != Sandbox::OK) {
            return (object) array('error' => $error);
        } else {
            $count = 0;
            while ($state->error === Sandbox::OK &&
                   $state->status !== Sandbox::STATUS_DONE &&
                   $count < Sandbox::MAX_NUM_POLLS) {
                $count += 1;
                sleep(Sandbox::POLL_INTERVAL);
                $state = $this->getSubmissionStatus($result->link);
            }

            if ($count >= Sandbox::MAX_NUM_POLLS) {
                throw new coding_exception("Timed out waiting for sandbox");
            }

            if ($state->error !== Sandbox::OK ||
                    $state->status !== Sandbox::STATUS_DONE) {
                throw new coding_exception("Error response or bad status from sandbox");
            }

            $details = $this->getSubmissionDetails($result->link);

            return (object) array(
                'error'   => Sandbox::OK,
                'result'  => $state->result,
                'output'  => $details->output,
                'stderr'  => $details->stderr,
                'signal'  => $details->signal,
                'cmpinfo' => $details->cmpinfo);
        }
    }

    public function testFunction() {
        if ($this->authenticationError) {
            return (object) array('error'=>Sandbox::AUTH_ERROR);
        } else {
            return (object) array(
                'error' => Sandbox::OK,
                'moreHelp' => 'No more help available',
                'pi' => 3.14,
                'answerToLifeAndEverything' => 42,
                'oOok' => TRUE
            );
        }
    }



    // SHould be called when the sandbox is no longer needed.
    // Can be used by the sandbox for garbage collection, e.g. deleting a
    // cached object file to avoid re-compilation.
    // Not part of the ideone API.
    public function close() {
    }
}
?>
