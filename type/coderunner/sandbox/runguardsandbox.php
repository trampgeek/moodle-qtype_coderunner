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

defined('MOODLE_INTERNAL') || die();

require_once('localsandbox.php');
require_once('runguardsandboxtasks.php');

// ==============================================================
//
// This sandbox has a very high default number of processes because the resource
// limit mechanism used is per user ('coderunner') not per
// process tree.
//
// ==============================================================

class qtype_coderunner_runguardsandbox extends qtype_coderunner_localsandbox {
    
    const MAX_READ = 4096;  // Max bytes to read in popen

    public static $default_numprocs = 200;    // Number of processes/threads

    public function __construct($user=null, $pass=null) {
        parent::__construct($user, $pass);
    }

    public function get_languages() {
        return array('matlab', 'octave', 'python2', 'python3', 'java', 'c');
    }
     
    
    /**
     * Compile the source code in $this->source in the current working directory.
     * Set $this->cmpinfo to any compiler error messages, empty if no errors.
     * The output of the compilation (if there is any compilation) is left in
     * the working directory for subsequent use by run_in_sandbox.
     * @return int qtype_coderunner_sandbox::OK 
     */
    public function compile() {
        $taskclass = 'qtype_coderunner\\local\\languagetasks\\' . ucwords($this->language) . '_Task';
        $this->task = new $taskclass();
        chdir($this->workdir);
        $this->cmpinfo = $this->task->compile($this->workdir, $this->sourcefilename);
        return qtype_coderunner_sandbox::OK;
    }



    /** Run the task defined by the source, language, input and params attributes
     *  of this in the sandbox, defining the result, stderr, output and
     * signal attributes of $this. If a compilation step is required, this
     * must already have been performed with the compiler output left in 
     * $this->cmpinfo (non-empty is taken as a compiler error) and the object
     * code in a location defined by the subclass.
     * @return qtype_coderunner_sandbox::OK if run succeeds in the sense
     * of nothing going terribly wrong or qtype_coderunner_sandbox::UNKNOWN_SERVER_ERROR
     * otherwise.
     */
    protected function run_in_sandbox() {
        $filesize = 1000 * $this->get_param('disklimit'); // MB -> kB
        $memsize = 1000 * $this->get_param('memorylimit');
        $cputime = $this->get_param('cputime');
        $numprocs = $this->get_param('numprocs');
        $sandboxcmdbits = array(
             dirname(__FILE__)  . "/runguard",
             "--user=coderunner",
             "--time=$cputime",         // Seconds of execution time allowed
             "--filesize=$filesize",    // Max file sizes
             "--nproc=$numprocs",       // Max num processes/threads for this *user*
             "--no-core",
             "--streamsize=$filesize");  // Max stdout/stderr sizes
        if ($memsize != 0) {  // Special case: Matlab won't run with a memsize set. TODO: WHY NOT!
            $sandboxcmdbits[] = "--memsize=$memsize";
        }
        $allCmdBits = array_merge($sandboxcmdbits, $this->task->get_run_command());
        $cmd = implode(' ', $allCmdBits) . " >prog.out 2>prog.err";

        $workdir = $this->workdir;
        chdir($workdir);

        try {
            $this->cmpinfo = ''; // Set defaults first
            $this->signal = 0;

            if ($this->input != '') {
                $f = fopen('prog.in', 'w');
                fwrite($f, $this->input);
                fclose($f);
                $cmd .= " <prog.in";
            }
            else {
                $cmd .= " </dev/null";
            }

            $handle = popen($cmd, 'r');
            $result = fread($handle, self::MAX_READ);
            pclose($handle);

            if (file_exists("$workdir/prog.err")) {
                $stderr = file_get_contents("$workdir/prog.err");
                $this->stderr = $this->task->filter_stderr($stderr);
            }
            else {
                $this->stderr = '';
            }
            if ($this->stderr !== '') {
                if (strpos($this->stderr, "warning: timelimit exceeded")) {
                    $this->result = self::RESULT_TIME_LIMIT;
                    $this->signal = 9;
                    $this->stderr = '';
                } else if(strpos($this->stderr, "warning: command terminated with signal 11")) {
                    $this->result = self::RESULT_RUNTIME_ERROR;
                    $this->signal = 11;
                    $this->stderr = '';
                }
                else {
                    $this->result = self::RESULT_ABNORMAL_TERMINATION;
                }
            }
            else {
                $this->result = self::RESULT_SUCCESS;
            }

            $this->output = $this->task->filter_output(file_get_contents("$workdir/prog.out"));
        }
        catch (Exception $e) {
            $this->result = self::RESULT_INTERNAL_ERR;
            $this->stderr = print_r($e, true);
            $this->output = $this->stderr;
            $this->signal = 0;
            return self::UNKNOWN_SERVER_ERROR;
        }
        return self::OK;
    }
}


