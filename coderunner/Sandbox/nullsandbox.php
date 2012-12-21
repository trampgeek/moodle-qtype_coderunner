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

define('MAX_READ', 4096);  // Max bytes to read in popen

// ==============================================================
//
// Language definitions.
//
// ==============================================================
class Matlab_ns_Task extends LanguageTask {
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
        return array('/');  // Not meaningful in this sandbox
     }

     public function getRunCommand() {
         return array(
             dirname(__FILE__)  . "/localrunner.py",
             $this->workdir,
             10,         // Seconds of execution time allowed
             800000000,  // Max mem allowed (800MB!!)
             0,  // Max num processes set 0 (i.e. disabled) as matlab barfs at any reasonable number (TODO: why?)
             '/usr/local/Matlab2012a/bin/glnxa64/MATLAB',
             '-nojvm',
             '-nodesktop',
             '-singleCompThread',
             '-r',
             basename($this->sourceFileName)
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

class Python2_ns_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Python 2.7';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
    }

    public function readableDirs() {
        return array();  // Irrelevant for this sandbox
     }

     // Return the command to pass to VirtualBox as a list of arguments,
     // starting with the program to run followed by a list of its arguments.
     public function getRunCommand() {
        return array(
             dirname(__FILE__)  . "/localrunner.py",
             $this->workdir,
             3,   // Seconds of execution time allowed
             100000000,  // Max mem allowed (100MB)
             4,     // Max num processes
             '/usr/bin/python2',
             $this->sourceFileName
         );
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
            'languages' => array('matlab', 'python2')
        );
    }


    protected function createTask($language, $source) {
        $reqdClass = ucwords($language) . "_ns_Task";
        return new $reqdClass($source);
    }


    // Run the current $this->task in the (nonexistent) sandbox,
    // i.e. it runs it on the current machine, albeit with resource
    // limits like maxmemory, maxnumprocesses and maxtime set.
    // Results are all left in $this->task for later access by
    // getSubmissionDetails
    protected function runInSandbox($input) {
        $cmd = implode(' ', $this->task->getRunCommand()) . ' >prog.out 2>&1';
        $workdir = $this->task->workdir;
        chdir($workdir);
        try {
            $this->task->cmpinfo = ''; // Set defaults first
            $this->task->signal = 0;
            $this->task->time = 0;
            $this->task->memory = 0;

            if ($input != '') {
                $f = fopen('prog.in', 'w');
                fwrite($f, $input);
                fclose($f);
            }

            $handle = popen($cmd, 'r');
            $result = fread($handle, MAX_READ);
            pclose($handle);

            $this->task->stderr = file_get_contents("$workdir/prog.err");
            if ($this->task->stderr != '') {
                if ($this->task->stderr == "Killed by signal #9\n") {
                    $this->task->result = Sandbox::RESULT_TIME_LIMIT;
                    $this->task->signal = 9;
                    $this->task->stderr = '';
                } else {
                    $this->task->result = Sandbox::RESULT_ABNORMAL_TERMINATION;
                }
            }
            else {
                $this->task->result = Sandbox::RESULT_SUCCESS;
            }

            $this->task->output = $this->task->filterOutput(
                    file_get_contents("$workdir/prog.out"));
        }
        catch (Exception $e) {
            $this->task->result = Sandbox::RESULT_INTERNAL_ERR;
            $this->task->stderr = $this->task->cmpinfo = print_r($e, true);
            $this->task->output = $this->task->stderr;
            $this->task->signal = $this->task->time = $this->task->memory = 0;
        }
    }
}
?>