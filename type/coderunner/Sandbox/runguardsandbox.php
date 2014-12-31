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
// This sandbox has a very high default number of processes because the resource
// limit mechanism used is per user ('coderunner') not per
// process tree.
//
// ==============================================================

class RunguardSandbox extends LocalSandbox {

    public static $default_numprocs = 200;    // Number of processes/threads

    public function __construct($user=NULL, $pass=NULL) {
        LocalSandbox::__construct($user, $pass);
    }

    public function get_languages() {
        return (object) array(
            'error' => Sandbox::OK,
            'languages' => array('matlab', 'octave', 'python2', 'python3', 'java', 'c')
        );
    }


    protected function createTask($language, $source) {
        $reqdClass = 'RunguardSandbox\\' . ucwords($language) . '_Task';
        return new $reqdClass($this, $source);
    }


    // Run the current $this->task on the current machine with resource
    // limits like maxmemory, maxnumprocesses and maxtime set.
    // If $files is non-null it defines a map from filename to filecontents;
    // these files are created in the current directory before the run begins.
    // [And they're recreated for each run in case the program corrupts them.]
    // Results are all left in $this->task for later access by
    // getSubmissionDetails
    protected function runInSandbox($input, $files) {
        $filesize = 1000 * $this->getParam('disklimit'); // MB -> kB
        $memsize = 1000 * $this->getParam('memorylimit');
        $cputime = $this->getParam('cputime');
        $numProcs = $this->getParam('numprocs');
        $sandboxCmdBits = array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=$cputime",         // Seconds of execution time allowed
             "--filesize=$filesize",    // Max file sizes
             "--nproc=$numProcs",       // Max num processes/threads for this *user*
             "--no-core",
             "--streamsize=$filesize");  // Max stdout/stderr sizes
        if ($memsize != 0) {  // Special case: Matlab won't run with a memsize set. TODO: WHY NOT!
            $sandboxCmdBits[] = "--memsize=$memsize";
        }
        $allCmdBits = array_merge($sandboxCmdBits, $this->task->getRunCommand());
        $cmd = implode(' ', $allCmdBits) . " >prog.out 2>prog.err";

        $workdir = $this->task->workdir;
        chdir($workdir);
        $this->loadFiles($files);
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
                $stderr = file_get_contents("$workdir/prog.err");
                $this->task->stderr = $this->task->filterStderr($stderr);
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
