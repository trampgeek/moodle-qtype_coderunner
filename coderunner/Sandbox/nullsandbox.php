<?php

/*
 * Provides a NullSandbox class, which is a sandbox in name only.
 * Runs jobs without true sandboxing in a manner specified by each
 * individual language but currently all use DOMJudge's 'runguard' program.
 * This runs the job as user 'coderunner' with resource limits set by
 * the language, so is at least safe against major resource depletion issues
 * (memory, CPU). Does not protect against system calls like socket and
 * program can read any world-readable file on the server.
 *
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
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=15",             // Seconds of execution time allowed
             //"--memsize=8000000",   // Why won't MATLAB run with a memsize set?!!
             "--filesize=1000000",    // Max file sizes (10MB)
             "--nproc=50",            // At most 20 processes/threads (for this *user*)
             "--no-core",
             "--streamsize=1000000",   // Max stdout/stderr sizes (10MB)
             '/usr/local/Matlab2012a/bin/glnxa64/MATLAB',
             '-nojvm',
             '-nodesktop',
             '-r',
             basename($this->sourceFileName)
         );
     }


     public function filterOutput($out) {
         $lines = explode("\n", $out);
         $outlines = array();
         $headerEnded = FALSE;

         foreach ($lines as $line) {
             $line = rtrim($line);
             if ($headerEnded) {
                 $outlines[] = $line;
             }
             if (strpos($line, 'For product information, visit www.mathworks.com.') !== FALSE) {
                 $headerEnded = TRUE;
             }
         }

         // Remove blank lines at the start and end
         while (count($outlines) > 0 && strlen($outlines[0]) == 0) {
             array_shift($outlines);
         }
         while(count($outlines) > 0 && strlen(end($outlines)) == 0) {
             array_pop($outlines);
         }

         return implode("\n", $outlines) . "\n";
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

     // Return the command to pass to localrunner as a list of arguments,
     // starting with the program to run followed by a list of its arguments.
     public function getRunCommand() {
        return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=3",             // Seconds of execution time allowed
             "--memsize=100000",     // Max kb mem allowed (100MB)
             "--filesize=10000",     // Max file sizes (10MB)
             "--nproc=10",           // At most 10 processes/threads for this *user*
             "--no-core",
             "--streamsize=10000",   // Max stdout/stderr sizes (10MB)
             '/usr/bin/python2',
             '-BESs',
             $this->sourceFileName
         );
     }
};

class Python3_ns_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Python 3.2';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
    }

    public function readableDirs() {
        return array();  // Irrelevant for this sandbox
     }

     // Return the command to pass to localrunner as a list of arguments,
     // starting with the program to run followed by a list of its arguments.
     public function getRunCommand() {
        return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=10",             // Seconds of execution time allowed
             "--memsize=2000000",    // Max kb mem allowed (1GB)
             "--filesize=10000",     // Max file sizes (10MB)
             "--nproc=20",           // At most 20 processes/threads for this *user*
             "--no-core",
             "--streamsize=10000",   // Max stdout/stderr sizes (10MB)
             '/usr/bin/python3',
             '-BE',
             $this->sourceFileName
         );
     }
};

class Java_ns_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Java 1.6';
    }

    public function compile() {
        $prog = file_get_contents($this->sourceFileName);
        if (($this->mainClassName = $this->getMainClass($prog)) === FALSE) {
            $this->cmpinfo = "Error: no main class found, or multiple main classes. [Did you write a public class when asked for a non-public one?]";
        }
        else {
            exec("mv {$this->sourceFileName} {$this->mainClassName}.java", $output, $returnVar);
            if ($returnVar !== 0) {
                throw new coding_exception("Java compile: couldn't rename source file");
            }
            $this->sourceFileName = "{$this->mainClassName}.java";
            exec("/usr/bin/javac {$this->sourceFileName} 2>compile.out", $output, $returnVar);
            if ($returnVar == 0) {
                $this->cmpinfo = '';
                $this->executableFileName = $this->sourceFileName;
            }
            else {
                $this->cmpinfo = file_get_contents('compile.out');
            }
        }
    }


    public function readableDirs() {
        return array(
            '/'  // Irrelevant in the null sandbox
        );
     }

     public function getRunCommand() {
         return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=5",              // Seconds of execution time allowed
             "--memsize=2000000",     // Max kb mem allowed (2GB Why does it need so much?)
             "--filesize=10000",      // Max file sizes (10MB)
             "--nproc=50",            // At most 50 processes/threads for this *user*
             "--no-core",
             "--streamsize=10000",    // Max stdout/stderr sizes (10MB)
             '/usr/bin/java',
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m",
             $this->mainClassName
         );
     }


     // Return the name of the main class in the given prog, or FALSE if no
     // such class found. Uses a regular expression to find a public class with
     // a public static void main method.
     // Not totally safe as it doesn't parse the file, e.g. would be fooled
     // by a commented-out main class with a different name.
     private function getMainClass($prog) {
         $pattern = '/(^|\W)public\s+class\s+(\w+)\s*\{.*?public\s+static\s+void\s+main\s*\(\s*String/ms';
         if (preg_match_all($pattern, $prog, $matches) !== 1) {
             return FALSE;
         }
         else {
             return $matches[2][0];
         }
     }
};


class C_ns_Task extends LanguageTask {

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
        $cmd = "gcc -Wall -ansi -static -x c -o $execFileName $src -lm 2>$errorFileName";
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
         return array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=5",              // Seconds of execution time allowed
             "--memsize=100000",      // Max kb mem allowed (100MB)
             "--filesize=10000",      // Max file sizes (10MB)
             "--nproc=5",             // Only allow this *user* 5 tasks in parallel
             "--no-core",
             "--streamsize=10000",    // Max stdout/stderr sizes (10MB)
             "./" . $this->executableFileName
         );
    }

    public function readableDirs() {
        return array();
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
            'languages' => array('matlab', 'python2', 'python3', 'Java', 'C')
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
        $cmd = implode(' ', $this->task->getRunCommand()) . ">prog.out 2>prog.err";
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
                $cmd .= " <prog.in";
            }
            else {
                $cmd .= " </dev/null";
            }

            $handle = popen($cmd, 'r');
            $result = fread($handle, MAX_READ);
            pclose($handle);

            if (file_exists("$workdir/prog.err")) {
                $this->task->stderr = file_get_contents("$workdir/prog.err");
            }
            else {
                $this->task->stderr = '';
            }
            if ($this->task->stderr != '') {
                if (strpos($this->task->stderr, "warning: timelimit exceeded")) {
                    $this->task->result = Sandbox::RESULT_TIME_LIMIT;
                    $this->task->signal = 9;
                    $this->task->stderr = '';
                } else if(strpos($this->task->stderr, "warning: command terminated with signal 11")) {
                    $this->task->result = Sandbox::RESULT_RUNTIME_ERROR;
                    $this->task->signal = 11;
                    $this->task->stderr = '';
                }
                else {
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