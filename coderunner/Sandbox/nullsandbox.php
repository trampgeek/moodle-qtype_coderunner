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
        $this->executableFileName = $this->sourceFileName . '.m';
        if (!copy($this->sourceFileName, $this->executableFileName)) {
            throw new coding_exception("Matlab_Task: couldn't copy source file");
        }
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
             "'" . basename($this->sourceFileName) . "()'",
             '> prog.out',
             '2> prog.err',
             '</dev/null'
         );
     }


     public function filterOutput($out) {
         $lines = explode("\n", $out);
         $outlines = array();
         $headerEnded = FALSE;
         foreach ($lines as $line) {
             $line = trim($line);
             if ($headerEnded && $line != '') {
                 $outlines[] = $line;
             }
             if (strpos($line, 'For product information, visit www.mathworks.com.') !== FALSE) {
                 $headerEnded = TRUE;
             }
         }
         return implode("\n", $outlines);
     }
};


// ==============================================================
//
// Now the actual null sandbox.
//
// ==============================================================

class NullSandbox extends LocalSandbox {

    public function __construct($user=NULL, $pass=NULL) {
        LocalSandbox::__construct($user, $pass);
    }

    public function getLanguages() {
        return (object) array(
            'error' => Sandbox::OK,
            'languages' => array('matlab')
        );
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
        $fout = fopen('cmd', 'w');
        fwrite($fout, $cmd);
        fclose($fout);
        // debugging("Executing cmd: $cmd");
        exec($cmd, $output, $returnVar);
        $this->task->stderr = file_get_contents('prog.err');
        if ($this->task->stderr != '') {
            $this->task->result = Sandbox::RESULT_ABNORMAL_TERMINATION;

        }
        else {
            $this->task->result = Sandbox::RESULT_SUCCESS;
        }

        $this->task->output = $this->task->filterOutput(file_get_contents('prog.out'));
        $this->task->cmpinfo = '';
        $this->task->signal = 0;
        $this->task->time = 0;
        $this->task->memory = 0;
    }
}
?>