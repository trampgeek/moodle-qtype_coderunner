<?php

/*
 * Provides the RunguardSandbox class, which is a lightweight sandbox that
 * runs jobs without using the 'runguard' program that provides the controlled
 * execution environment in the DomJudge programming contest system
 * (see http://www.domjudge.org/).
 * Jobs are run as user 'coderunner' with resource limits set by
 * the language, so is at least safe against major resource depletion issues
 * (memory, CPU). However, it does not protect against system calls like socket,
 * and the program can read any world-readable file on the server.
 *
 * One major concern with the use of RunguardSandbox is concurrency. runguard
 * limits processes using the standard Linux set_resource_limits mechanism,
 * but this limits the processes/threads on a per-user basis not on a per
 * process-tree basis like other resources. In a web-server environment,
 * each user request results in a new server thread and each thread can
 * proceed to do coderunner tests. These all run as user 'coderunner'.
 * Languages like Matlab and Java make extensive use of threads (a typical
 * JVM execution uses at least 10 threads and Matlab uses even more).
 * If too many coderunner instances are active at once, a new submission might
 * fail for no fault of its own. This is not currently handled correctly.
 *
 * In practice, we've seen only one situation that triggered the problem: a Python
 * task that ran the Java VM required close to the 20 threads allocated a
 * Python task at the time, and a second concurrent test would fail. For now
 * this problem has been resolved by increasing the allocation of processes
 * to 200, but a better solution is needed in the longer term.
 *
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('localsandbox.php');
require_once('runguardsandboxtasks.php');

define('MAX_READ', 4096);  // Max bytes to read in popen

// ==============================================================
//
// Now the actual sandbox.
// This has a very high default number of processes because the resource
// limit mechanism used in this sandbox is per user ('coderunner') not be
// process tree.
//
// ==============================================================

class RunguardSandbox extends LocalSandbox {

    public static $default_numprocs = 200;    // Number of processes/threads

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
        $reqdClass = 'RunguardSandbox\\' . ucwords($language) . '_Task';
        return new $reqdClass($this, $source);
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
