<?php
/** The base class for the CodeRunner Sandbox classes.
 *  Sandboxes have an external name, which appears in the exported .xml question
 *  files for example, and a classname and a filename in which the class is
 *  defined.
 *  Error and result codes are based on those of the ideone
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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');

abstract class qtype_coderunner_sandbox {
    protected $user;     // Username supplied when constructing
    protected $password; // Password supplied when constructing
    protected $authenticationerror;

    // Symbolic constants as per ideone API (mostly)
    
    // First the error codes from the initial create_submission call. Any
    // value other than OK is fatal.
    const OK                = 0;
    const AUTH_ERROR        = 1;
    const PASTE_NOT_FOUND   = 2;  // Link to a non-existent submission
    const WRONG_LANG_ID     = 3;  // No such language
    const ACCESS_DENIED     = 4;  // Only if using ideone or jobe
    const SUBMISSION_LIMIT_EXCEEDED = 5; // Ideone or Jobe only
    const CREATE_SUBMISSION_FAILED = 6; // Failed on call to CREATE_SUBMISSION
    const UNKNOWN_SERVER_ERROR = 7;
    

    // Values of the result 'attribute' of the object returned by a call to
    // get submissionStatus.
    const RESULT_NO_RUN             = 0;
    const RESULT_SUCCESS2           = 0; // Used by Jobe
    const RESULT_COMPILATION_ERROR  = 11;
    const RESULT_RUNTIME_ERROR      = 12;
    const RESULT_TIME_LIMIT         = 13;
    const RESULT_SUCCESS            = 15;
    const RESULT_MEMORY_LIMIT       = 17;
    const RESULT_ILLEGAL_SYSCALL    = 19;
    const RESULT_INTERNAL_ERR       = 20;

    const RESULT_SANDBOX_PENDING    = 21; // Liu sandbox PD error
    const RESULT_SANDBOX_POLICY     = 22; // Liu sandbox BP error
    const RESULT_OUTPUT_LIMIT       = 30;
    const RESULT_ABNORMAL_TERMINATION = 31;

    const POLL_INTERVAL = 3;     // secs to wait for sandbox done
    const MAX_NUM_POLLS = 40;    // No more than 120 seconds waiting


    // The following run constants can be overridden in subclasses.
    // See function getParam for their usage.
    public static $default_cputime = 3;   // Max seconds CPU time per run
    public static $default_walltime = 30; // Max seconds wall clock time per run
    public static $default_memorylimit = 64; // Max MB memory per run
    public static $default_disklimit = 10;   // Max MB disk usage
    public static $default_numprocs = 20;    // Number of processes/threads
    public static $default_files = null;     // Associative array of data files
    
    protected $params = null;       // Associative array of run params
    
    
    /**
     * A list of available standboxes. Keys are the externally known sandbox names
     * as they appear in the exported questions, values are the associated
     * class names. File names are the same as the class names with the
     * leading qtype_coderunner and all underscores removed.
     * @return array 
     */
    public static function available_sandboxes() {
        return array('jobesandbox'      => 'qtype_coderunner_jobesandbox',
                     'liusandbox'       => 'qtype_coderunner_liusandbox',
                     'runguardsandbox'  => 'qtype_coderunner_runguardsandbox',
                     'ideonesandbox'    => 'qtype_coderunner_ideonesandbox'
                );

    }
    
    /**
     * Get the filename containing the given external sandbox name
     * @param string $externalsandboxname
     * @return string $filename
     */
    public static function get_filename($extsandboxname) {
        $boxes = self::available_sandboxes();
        $classname = $boxes[$extsandboxname];
        return str_replace('_', '', str_replace('qtype_coderunner_', '', $classname)) . '.php';
    }
    
        
    public function __construct($user=null, $pass=null) {
        $this->user = $user;
        $this->pass = $pass;
        $authenticationError = false;
    }

    // Strings corresponding to the execute error codes defined above
    public static function error_string($errorcode) {
        $ERROR_STRINGS = array(
            qtype_coderunner_sandbox::OK              => "OK",
            qtype_coderunner_sandbox::AUTH_ERROR      => "Unauthorised to use sandbox",
            qtype_coderunner_sandbox::PASTE_NOT_FOUND => "Requesting status of non-existent job",
            qtype_coderunner_sandbox::WRONG_LANG_ID   => "Non-existent language requested",
            qtype_coderunner_sandbox::ACCESS_DENIED   => "Access to sandbox defined",
            qtype_coderunner_sandbox::SUBMISSION_LIMIT_EXCEEDED  => "Sandbox submission limit reached",
            qtype_coderunner_sandbox::CREATE_SUBMISSION_FAILED  => "Submission to sandbox failed",
            qtype_coderunner_sandbox::UNKNOWN_SERVER_ERROR  => "Unexpected error from sandbox (Jobe server down or excessive timeout, perhaps?)"  
        );
        if (!isset($ERROR_STRINGS[$errorcode])) {
            throw new coding_exception("Bad call to sandbox.errorString");
        }
        return $ERROR_STRINGS[$errorcode];
    }
    
    
    // Strings corresponding to the RESULT_* defines above
    public static function result_string($resultCode) {
        $RESULT_STRINGS = array(
            qtype_coderunner_sandbox::RESULT_NO_RUN               => "No run",
            qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR    => "Compilation error",
            qtype_coderunner_sandbox::RESULT_RUNTIME_ERROR        => "Runtime error",
            qtype_coderunner_sandbox::RESULT_TIME_LIMIT           => "Time limit exceeded",
            qtype_coderunner_sandbox::RESULT_SUCCESS              => "OK",
            qtype_coderunner_sandbox::RESULT_MEMORY_LIMIT         => "Memory limit exceeded",
            qtype_coderunner_sandbox::RESULT_ILLEGAL_SYSCALL      => "Illegal function call",
            qtype_coderunner_sandbox::RESULT_INTERNAL_ERR         => "CodeRunner error (IE): please tell a tutor",
            qtype_coderunner_sandbox::RESULT_SANDBOX_PENDING      => "CodeRunner error (PD): please tell a tutor",
            qtype_coderunner_sandbox::RESULT_SANDBOX_POLICY       => "CodeRunner error (BP): please tell a tutor",
            qtype_coderunner_sandbox::RESULT_OUTPUT_LIMIT         => "Excessive output",
            qtype_coderunner_sandbox::RESULT_ABNORMAL_TERMINATION => "Abnormal termination"
        );
        if (!isset($RESULT_STRINGS[$resultCode])) {
            throw new coding_exception("Bad call to sandbox.resultString");
        }
        return $RESULT_STRINGS[$resultCode];
    }
    
    

    /**
     * Return the value of the given parameter from the $params parameter
     * of the currently executing submission (see createSubmission) if defined
     * or the static variable of name "default_$param" otherwise.
     * @param string $param The name of the required parameter
     * @return string The value of the specified parameter
     */
    protected function get_param($param) {
        if ($this->params !== null && isset($this->params[$param])) {
            return $this->params[$param];
        } else {
            $staticname = "default_$param";
            assert(isset(static::$$staticname));
            return static::$$staticname;
        }
    }

    /**
     * @return object result An object with an 'error' attribute taking one of
     *  the values OK through UNKNOWN_SERVER_ERROR above. If the value is
     *  OK the object also includes a 'languages' attribute that is a
     *  list of languages handled by the sandbox. The languages list
     *  may include different varieties of a given language as well as aliases,
     *  e.g. C89, C99, C.
     */
    abstract public function get_languages();


    /** Execute the given source code in the given language with the given
     *  input and returns an object with fields error, result, signal, cmpinfo, stderr, output.
     * @param string $sourcecode The source file to compile and run
     * @param string $language  One of the languages regognised by the sandbox
     * @param string $input A string to use as standard input during execution
     * @param associative array $files either null or a map from filename to
     *         file contents, defining a file context at execution time
     * @param associative array $params Sandbox parameters, depends on
     *         particular sandbox but most sandboxes should recognise
     *         at least cputime (secs), memorylimit (Megabytes) and
     *         files (an associative array mapping filenames to string
     *         filecontents.
     *         If the $params array is null, sandbox defaults are used.
     * @return an object with at least an attribute 'error'. This is one of the
     *         values 0 through 8 (OK to UNKNOWN_SERVER_ERROR) as defined above. If
     *         error is 0 (OK), the returned object has additional attributes
     *         result, output, stderr, signal and cmpinfo as follows:
     *             result: one of the result_* constants defined above
     *             output: the stdout from the run
     *             stderr: the stderr output from the run (generally a non-empty
     *                     string is taken as a runtime error)
     *             signal: one of the standard Linux signal values (but often not
     *                     used)
     *             cmpinfo: the output from the compilation run (usually empty
     *                     unless the result code is for a compilation error).
     */
    abstract public function execute($sourcecode, $language, $input, $files=null, $params=null);

    /** Function called by the tester as a simple sanity check on the
     *  existence of a particular sandbox subclass.
     * @return object A result object with an 'error' attribute. If that 
     * attribute is OK, attributes of moreHelp, pi, answerToLifeAndEverything
     * and oOok are also defined.
     */
    public function test_function() {
        if ($this->authenticationerror) {
            return (object) array('error'=>qtype_coderunner_sandbox::AUTH_ERROR);
        } else {
            return (object) array(
                'error' => qtype_coderunner_sandbox::OK,
                'moreHelp' => 'No more help available',
                'pi' => 3.14,
                'answerToLifeAndEverything' => 42,
                'oOok' => true
            );
        }
    }



    // Should be called when the sandbox is no longer needed.
    // Can be used by the sandbox for garbage collection, e.g. deleting a
    // cached object file to avoid re-compilation.
    public function close() {
    }
}

