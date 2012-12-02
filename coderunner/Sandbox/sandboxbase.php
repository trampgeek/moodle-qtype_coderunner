<?php
/** The base class for the pycode Sandbox classes.
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

abstract class Sandbox {
    protected $user;     // Username supplied when constructing
    protected $password; // Password supplied when constructing
    protected $authenticationError;

    // Symbolic constants as per ideone API
    const OK           = 0;
    const AUTH_ERROR   = 1;
    const PASTE_NOT_FOUND = 2;  // Link to a non-existent submission
    const WRONG_LANG_ID   = 3;  // No such language
    const ACCESS_DENIED   = 4;  // Only if using ideone
    const CANNOT_SUBMIT_THIS_MONTH_ANYMORE = 5; // Ideone only

    const STATUS_WAITING     = -1;
    const STATUS_DONE        = 0;
    const STATUS_COMPILING   = 1;
    const STATUS_RUNNING     = 3;

    const RESULT_NO_RUN      = 0;
    const RESULT_COMPILATION_ERROR = 11;
    const RESULT_RUNTIME_ERROR = 12;
    const RESULT_TIME_LIMIT   = 13;
    const RESULT_SUCCESS      = 15;
    const RESULT_MEMORY_LIMIT    = 17;
    const RESULT_ILLEGAL_SYSCALL = 19;
    const RESULT_INTERNAL_ERR = 20;

    // Additions to ideone API for Liu Sandbox compatibility
    const RESULT_OUTPUT_LIMIT = 30;
    const RESULT_ABNORMAL_TERMINATION = 31;


    const POLL_INTERVAL = 3;     // secs to wait for sandbox done
    const MAX_NUM_POLLS = 20;    // No more than 60 seconds waiting


    public function __construct($user=NULL, $pass=NULL) {
        $this->user = $user;
        $this->pass = $pass;
        $authenticationError = FALSE;
    }

    public static function resultString($resultCode) {
        $RESULT_STRINGS = array(
            0 => "No run",
            11 => "Compilation error",
            12 => "Runtime error",
            13 => "Time limit exceeded",
            15 => "OK",
            17 => "Memory limit exceeded",
            19 => "Illegal function call",
            20 => "Pycode error: please tell a tutor",
            30 => "Excessive output",
            31 => "Abnormal termination"
        );
        if (!isset($RESULT_STRINGS[$resultCode])) {
            throw new coding_exception("Bad call to sandbox.resultString");
        }
        return $RESULT_STRINGS[$resultCode];
    }

    abstract public function getLanguages();

    abstract public function createSubmission($sourceCode, $language, $input,
            $run=TRUE, $private=TRUE);

    abstract public function getSubmissionStatus($link);

    abstract public function getSubmissionDetails($link, $withSource=FALSE,
            $withInput=FALSE, $withOutput=TRUE, $withStderr=TRUE,
            $withCmpinfo=TRUE);

    /** Main interface function for use by coderunner but not part of ideone API.
     *  Executes the given source code in the given language with the given
     *  input and returns an associative array with fields result,
     *  output, stderr, cmpinfo.
     * @param type $sourceCode
     * @param type $language
     * @param type $input
     */
    public function execute($sourceCode, $language, $input) {
        if (!in_array($language, $this->getLanguages()->languages)) {
            throw new coding_exception('Executing an unsupported language in sandbox');
        }
        $result = $this->createSubmission($sourceCode, $language, $input);
        $state = $this->getSubmissionStatus($result->link);
        if ($state->error != Sandbox::OK) {
            return (object) array('error' => $this->error);
        } else {
            $count = 0;
            while ($state->error === Sandbox::OK &&
                   $state->status !== Sandbox::STATUS_DONE &&
                   $count < Sandbox::MAX_NUM_POLLS) {
                $count += 1;
                sleep(Sandbox::POLL_INTERVAL);
                $state = $this->getSubmissionStatus($link);
            }

            if ($state->error !== Sandbox::OK ||
                    $state->status !== Sandbox::STATUS_DONE) {
                throw new coding_exception('Error response from sandbox');
            }

            $details = $this->getSubmissionDetails($result->link);

            return (object) array(
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
