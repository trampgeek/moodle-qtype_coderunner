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

/** The base class for the CodeRunner Sandbox classes.
 *  Sandboxes have an external name, which appears in the exported .xml question
 *  files for example, and a classname and a filename in which the class is
 *  defined.
 *  Error and result codes are based on those of the ideone
 *  API, which should be consulted for details:
 *  see ideone.com/files/ideone-api.pdf
 */

/**
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2012, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// TODO: provide a mechanism to check that a sandbox recognises all the
// non-null parameters it has been given (in particular the 'files' param).

defined('MOODLE_INTERNAL') || die();

use qtype_coderunner\constants;

global $CFG;

abstract class qtype_coderunner_sandbox {
    protected $user;     // Username supplied when constructing.
    protected $password; // Password supplied when constructing.
    protected $authenticationerror;

    // Symbolic constants as per ideone API (mostly).

    // First the error codes from the initial create_submission call. Any
    // value other than OK is fatal.
    const OK                = 0;
    const AUTH_ERROR        = 1;
    const PASTE_NOT_FOUND   = 2;  // Link to a non-existent submission.
    const WRONG_LANG_ID     = 3;  // No such language.
    const ACCESS_DENIED     = 4;  // Only if using ideone or jobe.
    const SUBMISSION_LIMIT_EXCEEDED = 5; // Ideone or Jobe only.
    const CREATE_SUBMISSION_FAILED = 6; // Failed on call to CREATE_SUBMISSION.
    const UNKNOWN_SERVER_ERROR = 7;
    const JOBE_400_ERROR    = 8;  // Jobe returned an HTTP code of 400.
    const SERVER_OVERLOAD   = 9;


    // Values of the result 'attribute' of the object returned by a call to
    // get submissionStatus.
    const RESULT_NO_RUN             = 0;
    const RESULT_SUCCESS2           = 0; // Used by Jobe.
    const RESULT_COMPILATION_ERROR  = 11;
    const RESULT_RUNTIME_ERROR      = 12;
    const RESULT_TIME_LIMIT         = 13;
    const RESULT_SUCCESS            = 15;
    const RESULT_MEMORY_LIMIT       = 17;
    const RESULT_ILLEGAL_SYSCALL    = 19;
    const RESULT_INTERNAL_ERR       = 20;

    const RESULT_SERVER_OVERLOAD    = 21;
    const RESULT_OUTPUT_LIMIT       = 30;
    const RESULT_ABNORMAL_TERMINATION = 31;

    const POLL_INTERVAL = 3;     // Secs to wait for sandbox done.
    const MAX_NUM_POLLS = 40;    // No more than 120 seconds waiting.


    // The following run constants can be overridden in subclasses.
    // See function getParam for their usage.
    public static $defaultcputime = 3;   // Max seconds CPU time per run.
    public static $defaultwalltime = 30; // Max seconds wall clock time per run.
    public static $defaultmemorylimit = 64; // Max MB memory per run.
    public static $defaultdisklimit = 10;   // Max MB disk usage.
    public static $defaultnumprocs = 20;    // Number of processes/threads.
    public static $defaultfiles = null;     // Associative array of data files.

    protected $params = null;       // Associative array of run params.



    public function __construct($user = null, $password = null) {
        $this->user = $user;
        $this->password = $password;
        $this->authenticationerror = false;
    }


    /**
     *
     * @param string $sandboxextname the external name of the sandbox required
     * (e.g. 'jobesandbox')
     * @return an instance of the specified sandbox or null if the specified
     * sandbox doesn't exist or is not enabled.
     */
    public static function get_instance($sandboxextname) {
        $boxes = self::enabled_sandboxes();
        if (array_key_exists($sandboxextname, $boxes)) {
            $classname = $boxes[$sandboxextname];
            $filename = self::get_filename($sandboxextname);
            require_once("$filename");
            $sb = new $classname();
            return $sb;
        } else {
            return null;
        }
    }


    /** Find the 'best' sandbox for a given language, defined to be the
     * first one in the ordered list of sandboxes in sandbox_config.php
     * that has been enabled by the administrator (through the usual
     * plug-in setting controls) and that supports the given language.
     * It's public so the tester can call it (yuck, hacky).
     * If there's only one sandbox available, just return it without querying
     * it. This could result in downstream "unsupported language" errors but
     * saves an extra call to the jobe server in the most common case where
     * there is a only a single Jobe server available anyway.
     * @param string $language to run.
     * @param bool $forcelanguagecheck true to ensure language is actually
     * available even when there's only one sandbox available.
     * @return an instance of the preferred sandbox for the given language
     * or null if no enabled sandboxes support this language.
     */
    public static function get_best_sandbox($language, $forcelanguagecheck = false) {

        $hidec = false;  // Set true when testing if language is skipped in php tests.
        if ($hidec && $language == 'c') {
            return null;
        }

        $sandboxes = self::enabled_sandboxes();
        if (count($sandboxes) == 0) {
            throw new qtype_coderunner_exception('No sandboxes available for running code!');
        }
        foreach (array_keys($sandboxes) as $extname) {
            $sb = self::get_instance($extname);
            if ($sb) {
                if (count($sandboxes) === 1 && !$forcelanguagecheck) {
                    return $sb;  // There's only one sandbox, let's just try it!
                }
                $langs = $sb->get_languages();
                if ($langs->error == $sb::OK) {
                    foreach ($langs->languages as $lang) {
                        if (strtolower($lang) == strtolower($language)) {
                            return $sb;
                        }
                    }
                } else {
                    $pseudorunobj = new stdClass();
                    $pseudorunobj->error = $langs->error;
                    $errorstring = $sb->error_string($pseudorunobj);
                    throw new qtype_coderunner_exception(
                        'sandboxerror',
                        ['sandbox' => $extname, 'message' => $errorstring]
                    );
                }
            }
        }
        return null;
    }


    /**
     * A list of available sandboxes. Keys are the externally known sandbox names
     * as they appear in the exported questions, values are the associated
     * class names. File names are the same as the class names with the
     * leading qtype_coderunner and all underscores removed.
     * @return array
     */
    public static function available_sandboxes() {
        return ['jobesandbox'      => 'qtype_coderunner_jobesandbox',
                     'ideonesandbox'    => 'qtype_coderunner_ideonesandbox',
        ];
    }


    /**
     * A list of enabled sandboxes.
     * Keys are the externally known sandbox names
     * as they appear in the exported questions, values are the associated
     * class names. File names are the same as the class names with the
     * leading qtype_coderunner and all underscores removed.
     * @return array
     */
    public static function enabled_sandboxes() {
        $available = self::available_sandboxes();
        $enabled = [];
        foreach ($available as $extname => $classname) {
            if (get_config('qtype_coderunner', $extname . '_enabled')) {
                $enabled[$extname] = $classname;
            }
        }
        return $enabled;
    }

    /**
     * Returns true if sandbox is being used for tests.
     * @return bool
     */
    public static function is_using_test_sandbox(): bool {
        global $CFG;
        return !empty($CFG->behat_prefix) && $CFG->prefix === $CFG->behat_prefix;
    }

    /**
     * Returns true if canterbury jobe server is being used.
     * @param string jobeserver being used.
     * @return bool
     */
    public static function is_canterbury_server(string $jobeserver): bool {
        return $jobeserver === constants::JOBE_HOST_DEFAULT;
    }

    /**
     * Get the filename containing the given external sandbox name.
     * @param string $externalsandboxname
     * @return string $filename
     */
    public static function get_filename($extsandboxname) {
        $boxes = self::available_sandboxes();
        $classname = $boxes[$extsandboxname];
        return str_replace('_', '', str_replace('qtype_coderunner_', '', $classname)) . '.php';
    }

    /**
     *
     * @param type $runresult  (an object returned by a call to sandbox::execute).
     * @return string a description of the particular runresult
     * @throws coding_exception
     */
    public static function error_string($runresult) {
        $errorstrings = [
            self::OK              => 'errorstring-ok',
            self::AUTH_ERROR      => 'errorstring-autherror',
            self::PASTE_NOT_FOUND => 'errorstring-pastenotfound',
            self::WRONG_LANG_ID   => 'errorstring-wronglangid',
            self::ACCESS_DENIED   => 'errorstring-accessdenied',
            self::SUBMISSION_LIMIT_EXCEEDED  => 'errorstring-submissionlimitexceeded',
            self::CREATE_SUBMISSION_FAILED  => 'errorstring-submissionfailed',
            self::UNKNOWN_SERVER_ERROR  => 'errorstring-unknown',
            self::JOBE_400_ERROR  => 'errorstring-jobe400',
            self::SERVER_OVERLOAD => 'errorstring-overload',
        ];
        $errorcode = $runresult->error;
        if (!isset($errorstrings[$errorcode])) {
            throw new coding_exception("Bad call to sandbox.errorString");
        }
        if ($errorcode == self::JOBE_400_ERROR) {
            // Special case for JOBE_400 error. Include HTTP error message in
            // the returned error (if given).
            $message = get_string('errorstring-jobe400', 'qtype_coderunner');
            if (isset($runresult->stderr)) {
                $message .= $runresult->stderr;
            }
        } else if ($errorcode == self::UNKNOWN_SERVER_ERROR && isset($runresult->stderr)) {
            // Errors such as a blocked URL land up here.
            $message = get_string('errorstring-jobe-failed', 'qtype_coderunner');
            $extra = $runresult->stderr;
            if (strcmp($extra, "The URL is blocked.") == 0) {
                $extra = get_string('errorstring-blocked-url', 'qtype_coderunner');
            }
            $message .= $extra;
        } else {
            $message = get_string($errorstrings[$errorcode], 'qtype_coderunner');
        }
        return $message;
    }


    // Strings corresponding to the RESULT_* defines above.
    public static function result_string($resultcode) {
        $resultstrings = [
            self::RESULT_NO_RUN               => 'resultstring-norun',
            self::RESULT_COMPILATION_ERROR    => 'resultstring-compilationerror',
            self::RESULT_RUNTIME_ERROR        => 'resultstring-runtimeerror',
            self::RESULT_TIME_LIMIT           => 'resultstring-timelimit',
            self::RESULT_SUCCESS              => 'resultstring-success',
            self::RESULT_MEMORY_LIMIT         => 'resultstring-memorylimit',
            self::RESULT_ILLEGAL_SYSCALL      => 'resultstring-illegalsyscall',
            self::RESULT_INTERNAL_ERR         => 'resultstring-internalerror',
            self::RESULT_OUTPUT_LIMIT         => 'resultstring-outputlimit',
            self::RESULT_ABNORMAL_TERMINATION => 'resultstring-abnormaltermination',
            self::RESULT_SERVER_OVERLOAD      => 'resultstring-sandboxoverload',
        ];
        if (!isset($resultstrings[$resultcode])) {
            throw new coding_exception("Bad call to sandbox.resultString");
        }
        return get_string($resultstrings[$resultcode], 'qtype_coderunner');
    }



    /**
     * Return the value of the given parameter from the $params parameter
     * of the currently executing submission (see createSubmission) if defined
     * or the static variable of name "default$param" otherwise.
     * @param string $param The name of the required parameter
     * @return string The value of the specified parameter
     */
    protected function get_param($param) {
        if ($this->params !== null && isset($this->params[$param])) {
            return $this->params[$param];
        } else {
            $staticname = "default$param";
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
     *  input and returns an object with fields error, result, signal, cmpinfo,
     *  stderr, output.
     * @param string $sourcecode The source file to compile and run
     * @param string $language  One of the languages regognised by the sandbox
     * @param string $input A string to use as standard input during execution
     * @param associative array $files either null or a map from filename to
     *         file contents, defining a file context at execution time
     * @param associative array $params Sandbox parameters, depends on
     *         particular sandbox but most sandboxes should recognise
     *         at least cputime (secs) and memorylimit (Megabytes).
     *         If the $params array is null, sandbox defaults are used.
     * @return an object with at least an attribute 'error'. This is one of the
     *         values 0 through 9 (OK to SERVER_OVERLOAD) as defined above.
     *         If error is 0 (OK), the returned object has additional attributes
     *         result, output, stderr, signal and cmpinfo as follows:
     *             result: one of the result_* constants defined above
     *             output: the stdout from the run
     *             stderr: the stderr output from the run (generally a non-empty
     *                     string is taken as a runtime error)
     *             signal: one of the standard Linux signal values (but often not
     *                     used)
     *             cmpinfo: the output from the compilation run (usually empty
     *                     unless the result code is for a compilation error).
     *          If error is anything other than OK, the returned object may
     *          optionally include an error message in the stderr field.
     */
    abstract public function execute($sourcecode, $language, $input, $files = null, $params = null);

    /** Function called by the tester as a simple sanity check on the
     *  existence of a particular sandbox subclass.
     * @return object A result object with an 'error' attribute. If that
     * attribute is OK, attributes of moreHelp, pi, answerToLifeAndEverything
     * and oOok are also defined.
     */
    public function test_function() {
        if ($this->authenticationerror) {
            return (object) ['error' => self::AUTH_ERROR];
        } else {
            return (object) [
                'error' => self::OK,
                'moreHelp' => 'No more help available',
                'pi' => 3.14,
                'answerToLifeAndEverything' => 42,
                'oOok' => true,
            ];
        }
    }


    // Should be called when the sandbox is no longer needed.
    // Can be used by the sandbox for garbage collection, e.g. deleting a
    // cached object file to avoid re-compilation.
    public function close() {
    }
}
