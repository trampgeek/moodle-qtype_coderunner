<?php
/** A LanguageTask is an object that specifies how a particular language
 *  should be compiled and run within a particular LocalSandbox. For every
 *  subclass of LocalSandbox there will be a set of subclasses of
 *  LanguageTask, one for each language that can be run within that sandbox.
 *  To avoid naming conflicts each set of LanguageTask subclasses exists
 *  within a namespace specific to the particular LocalSandbox subclass.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');

abstract class LanguageTask {
    public $sandbox = null; // The sandbox on which this task is running
    public $cmpinfo = '';   // Output from compilation
    public $time = 0;       // Execution time (secs)
    public $memory = 0;     // Memory used (MB)
    public $signal = 0;
    public $output = '';    // Output from execution
    public $stderr = '';
    public $result = qtype_coderunner_sandbox::RESULT_NO_RUN;
    public $workdir = '';   // The temporary working directory created in constructor

    // For all languages it is necessary to store the source code in a
    // temporary file when constructing the task. A temporary directory
    // is made to hold the source code. The sandbox on which the task
    // is running is also stored, for access to sandbox parameters like
    // cputime etc.
    // Any files defined in the sandbox params associative array are created
    // in the working directory.
    public function __construct($sandbox, $sourceCode) {
        $this->sandbox = $sandbox;
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
    // task in the sandbox. Relevant only to LiuSandbox
    public static function readableDirs() {
        return array();
    }


    // Override the following function if the output from executing a program
    // in this language needs post-filtering to remove stuff like
    // header output.
    public function filterOutput($out) {
        return $out;
    }


    // Override the following function if the stderr from executing a program
    // in this language needs post-filtering to remove stuff like
    // backspaces and bells.
    public function filterStderr($stderr) {
        return $stderr;
    }


    public function close() {
        if (!isset($this->sourceFileName)) {
            throw new coding_exception('LanguageTask::close(): no source file');
        }
        $this->delTree($this->workdir);
    }
    
    // Check if PHP exec environment includes a PATH. If not, set up a
    // default, or gcc misbehaves. [Thanks to Binoj D for this bug fix,
    // needed on his CentOS system.]
    protected function setPath() {          
        $envVars = array();
        exec('printenv', $envVars);
        $hasPath = false;
        foreach ($envVars as $var) {
            if (strpos($var, 'PATH=') === 0) {
                $hasPath = true;
                break;
            }
        }
        if (!$hasPath) {
            putenv("PATH=/sbin:/bin:/usr/sbin:/usr/bin");
        }
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