<?php

/*
 * Provides a NullSandbox class, which is a sandbox in name only -- it
 * doesn't provide any security features, but just implements the generic
 * Sandbox interface by running the code unsecured in a temporary subdirectory.
 * Intended for testing or for providing (unsafe) support for languages that
 * won't run in any of the standard sandboxes.
 *
 * VERY LITTLE TESTING -- for emergency use only.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('localsandbox.php');

// ==============================================================
//
// Language definitions.
//
// ==============================================================
class Matlab_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Matlab R2012';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
    }

    public function readableDirs() {
        return array('/');  // FIX ME!!!
     }

     public function getRunCommand() {
         return array(
             "/usr/local/Matlab2012a/bin/glnxa64/MATLAB",
             "-nodisplay",
             "-nojvm",
             "-r",
             basename($this->executableFileName),
             '> prog.out',
             '2> prog.err'
         );
     }
};


// ==============================================================
//
// Now the actual null sandbox.
//
// ==============================================================

class NullSandbox extends LocalSandbox {
    private $LANGUAGES = array('matlab');

    public function __construct($user=NULL, $pass=NULL) {
        LocalSandbox::__construct($user, $pass);
    }

    public function getLanguages() {
        return (object) array('error' => Sandbox::OK,
            'languages' => 'matlab');
    }

    protected function createTask($language, $source) {
        $reqdClass = ucwords($language) . "_Task";
        return new $reqdClass($source);
    }


    // Run the current $this->task in the (nonexistent) sandbox.
    // Results are all left in $this->task for later access by
    // getSubmissionDetails
    protected function runInSandbox($input) {
        $cmd = implode(' ', $this->task->getRunCommand());
        $workdir = $this->task->workdir;
        chdir($this->task->workdir);
        exec($cmd, $output, $returnVar);

        if ($returnVar != 0) {
            $this->task->result == Sandbox::RESULT_RUNTIME_ERROR;
        }
        else {
            $this->task->result == Sandbox::RESULT_SUCCESS;
        }
        
        $this->task->output = file_get_contents('prog.out');
        $this->task->stderr = file_get_contents('prog.err');
        $this->task->cmpinfo = '';
        $this->task->signal = 0;
        $this->task->time = 0;
        $this->task->memory = 0;
    }
}
?>