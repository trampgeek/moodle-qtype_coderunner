<?php
/** The concrete implementation of LocalSandbox using the Liu sandbox.
 *  See http://openjudge.net/~liuyu/Project/LibSandbox and
 *  http://sourceforge.net/projects/libsandbox/
 *
 * 21 June: Removed Python3 from this sandbox because it uses the
 * openat system call, which is too hard to validity-check.
 */

require_once('localsandbox.php');

// ==============================================================
//
// Language definitions.
//
// ==============================================================

// Python3 has been removed from the sandbox for now because of the security
// hole introduced by requiring openat to be an acceptable system call.
// I'm leaving the code in place for now, in case I change my mind.
// **TODO** remove this is due course if not needed.
class Python3_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Python 3.3';
    }

    public function compile() {
        exec("python3 -m py_compile {$this->sourceFileName} 2>compile.out", $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $this->sourceFileName;
        }
        else {
            $this->cmpinfo = file_get_contents('compile.out');
        }
    }

    public static function readableDirs() {
        return array(
            '/lib/',
            '/lib64/',
            '/etc/',
            '/usr/local/lib',
            '/usr/lib',
            '/usr/bin',
            '/proc/meminfo',
            '/usr/include',
            '/dev/urandom',
            '/usr/local',
            '/usr/pyvenv.cfg'
        );
     }

     public function getRunCommand() {
         return array(
             '/usr/bin/python3', '-BESs', basename($this->executableFileName)
         );
     }

};

// =============================================================

class Python2_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Python 2.6';
    }

    public function compile() {
        exec("python2 -m py_compile {$this->sourceFileName} 2>compile.out", $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $this->sourceFileName;
        }
        else {
            $this->cmpinfo = file_get_contents('compile.out');
        }
    }

    public static function readableDirs() {
        return array(
            '/lib',
            '/etc/',
            '/usr/local/lib',
            '/usr/lib',
            '/usr/bin',
            '/proc/meminfo',
            '/usr/include'
        );
     }

     public function getRunCommand() {
         return array(
             '/usr/bin/python2', '-BESs', basename($this->executableFileName)
         );
     }
};


// =============================================================

class C_Task extends LanguageTask {

    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'gcc-4.6.3';
    }

    public function compile() {
        $src = basename($this->sourceFileName);
        $errorFileName = "$src.err";
        $execFileName = "$src.exe";
        $cmd = "gcc -Wall -Werror -std=c99 -static -x c -o $execFileName $src -lm 2>$errorFileName";
        // To support C++ instead use something like ...
        // $cmd = "g++ -Wall -Werror -static -x c++ -o $execFileName $src -lm 2>$errorFileName";
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $execFileName;
        }
        else {
            $this->cmpinfo = file_get_contents($errorFileName);
        }
    }


    public function getRunCommand() {
        return array($this->executableFileName);
    }

};


// ==============================================================
//
// Now the actual Lius sandbox (or, really, the interface to the Python
// driver for the Liu sandbox).
//
// ==============================================================

class LiuSandbox extends LocalSandbox {
    private $LANGUAGES = array('C', 'python2');  // Remove Python3 from this box

    private $RESULT_CODES = array(
            'PD' => Sandbox::RESULT_SANDBOX_PENDING,  // Shouldn't occur
            'OK' => Sandbox::RESULT_SUCCESS,
            'RF' => Sandbox::RESULT_ILLEGAL_SYSCALL,
            'RT' => Sandbox::RESULT_RUNTIME_ERROR,
            'TL' => Sandbox::RESULT_TIME_LIMIT,
            'ML' => Sandbox::RESULT_MEMORY_LIMIT,
            'OL' => Sandbox::RESULT_OUTPUT_LIMIT,
            'AT' => Sandbox::RESULT_ABNORMAL_TERMINATION,
            'IE' => Sandbox::RESULT_INTERNAL_ERR,
            'BP' => Sandbox::RESULT_SANDBOX_POLICY // Shouldn't occur
    );

    public function __construct($user=NULL, $pass=NULL) {
        LocalSandbox::__construct($user, $pass);
    }

    public function getLanguages() {
        return (object) array('error' => Sandbox::OK,
            'languages' => $this->LANGUAGES);
    }

    protected function createTask($language, $source) {
        $reqdClass = ucwords($language) . "_Task";
        return new $reqdClass($source);
    }


    // Run the current $this->task in the sandbox.
    // Results are all left in $this->task for later access by
    // getSubmissionDetails
    protected function runInSandbox($input) {
       global $CFG;
       // Set up the control params for the sandbox
        //debugging($executeCommand);
        $run = array(
            'cmd'       => $this->task->getRunCommand(),
            'input'     => $input,
            'quota'     => array(
                'wallclock' => 30000,    // 30 secs
                'cpu'       => 5000,     // 5 secs
                'memory'    => 64000000, // 64MB
                'disk'      => 1048576   // 1 MB
            ),
            'readableDirs' => $this->task->readableDirs(),
            'workdir'      => $this->task->workdir
        );

        // Write the control params to a file, JSON encoded,
        // for use by the sandbox.

        chdir($this->task->workdir);
        $taskname = "taskDetails.json";
        $handle = fopen($taskname, "w");
        $encodedRun = json_encode($run);
        fwrite($handle, $encodedRun);
        fclose($handle);

        // Run the command in the sandbox. Output is a JSON-encoded
        // sandbox-result structure.

        $cmd = $CFG->dirroot . "/question/type/coderunner/Sandbox/liusandbox.py $taskname";

        exec($cmd, $output, $returnVar);
        $outputJson = $output[0];
        $result = json_decode($outputJson);

        // Copy result parameters into $this->task, clean-up and return

        $this->task->result = $this->RESULT_CODES[$result->returnCode];
        $this->task->output = $this->task->filterOutput($result->output);
        $this->task->stderr = $result->stderr;
        $this->task->cmpinfo = '';
        if (isset($result->details->signal_info)) {
            $this->task->signal = $result->details->signal_info[0];
            $this->task->time = $result->details->elapsed;
            $this->task->memory = $result->details->mem_info[0];
        } else {  // An internal error doesn't set all fields
            $this->task->signal = 0;
            $this->task->time = 0;
            $this->task->memory = 0;
        }
    }
}
?>
