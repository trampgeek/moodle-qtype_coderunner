<?php
/** The concrete implementation of LocalSandbox using the Liu sandbox.
 *  See http://openjudge.net/~liuyu/Project/LibSandbox and
 *  http://sourceforge.net/projects/libsandbox/
 *
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012, 2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('localsandbox.php');
require_once('liusandboxtasks.php');


// ==============================================================
//
// Now the actual Lius sandbox (or, really, the interface to the Python
// driver for the Liu sandbox).
//
// ==============================================================


class qtype_coderunner_liusandbox extends qtype_coderunner_localsandbox {
    private $LANGUAGES = array('c');

    private $RESULT_CODES = array(
            'PD' => qtype_coderunner_sandbox::RESULT_SANDBOX_PENDING,  // Shouldn't occur
            'OK' => qtype_coderunner_sandbox::RESULT_SUCCESS,
            'RF' => qtype_coderunner_sandbox::RESULT_ILLEGAL_SYSCALL,
            'RT' => qtype_coderunner_sandbox::RESULT_RUNTIME_ERROR,
            'TL' => qtype_coderunner_sandbox::RESULT_TIME_LIMIT,
            'ML' => qtype_coderunner_sandbox::RESULT_MEMORY_LIMIT,
            'OL' => qtype_coderunner_sandbox::RESULT_OUTPUT_LIMIT,
            'AT' => qtype_coderunner_sandbox::RESULT_ABNORMAL_TERMINATION,
            'IE' => qtype_coderunner_sandbox::RESULT_INTERNAL_ERR,
            'BP' => qtype_coderunner_sandbox::RESULT_SANDBOX_POLICY // Shouldn't occur
    );

    public function __construct($user=null, $pass=null) {
        qtype_coderunner_localsandbox::__construct($user, $pass);
    }

    public function get_languages() {
        return $this->LANGUAGES;
    }

    protected function createTask($language, $source) {
        $reqdClass = 'LiuSandbox\\' . ucwords($language) . '_Task';
        return new $reqdClass($this, $source);
    }
    
    
    public function execute($sourcecode, $language, $input, $files=null, $params=null) {
          if ($error != qtype_coderunner_sandbox::OK) {
            return (object) array('error' => $error);
        } else {
            $count = 0;
            while ($state->error === qtype_coderunner_sandbox::OK &&
                   $state->status !== qtype_coderunner_sandbox::STATUS_DONE &&
                   $count < qtype_coderunner_sandbox::MAX_NUM_POLLS) {
                $count += 1;
                sleep(qtype_coderunner_sandbox::POLL_INTERVAL);
                $state = $this->get_submission_status($result->link);
            }

            if ($count >= qtype_coderunner_sandbox::MAX_NUM_POLLS) {
                throw new coderunner_exception("Timed out waiting for sandbox");
            }

            if ($state->error !== qtype_coderunner_sandbox::OK ||
                    $state->status !== qtype_coderunner_sandbox::STATUS_DONE) {
                throw new coding_exception("Error response or bad status from sandbox");
            }

            $details = $this->get_submission_details($result->link);

            return (object) array(
                'error'   => qtype_coderunner_sandbox::OK,
                'result'  => $state->result,
                'output'  => $details->output,
                'stderr'  => $details->stderr,
                'signal'  => $details->signal,
                'cmpinfo' => $details->cmpinfo);
        }
    }


    // Run the current $this->task in the sandbox.
    // Results are all left in $this->task for later access by
    // getSubmissionDetails
    protected function runInSandbox($input, $files) {
       global $CFG;
       // Set up the control params for the sandbox

        $run = array(
            'cmd'       => $this->task->getRunCommand(),
            'input'     => $input,
            'quota'     => array(
                'wallclock' => 1000 * $this->get_param('walltime'),
                'cpu'       => 1000 * $this->get_param('cputime'),
                'memory'    => 1000000 * $this->get_param('memorylimit'),
                'disk'      => 1000000 * $this->get_param('disklimit')
            ),
            'readableDirs' => $this->task->readableDirs(),
            'workdir'      => $this->task->workdir
        );

        // Write the control params to a file, JSON encoded,
        // for use by the sandbox.

        chdir($this->task->workdir);
        $this->loadFiles($files);
        $taskname = "taskDetails.json";
        $handle = fopen($taskname, "w");
        $encodedRun = json_encode($run);
        fwrite($handle, $encodedRun);
        fclose($handle);

        // Run the command in the sandbox. Output is a JSON-encoded
        // sandbox-result structure.

        $cmd = $CFG->dirroot . "/question/type/coderunner/sandbox/liusandbox.py $taskname";

        exec($cmd, $output, $returnVar);
        $outputJson = $output[0];
        $result = json_decode($outputJson);

        // Copy result parameters into $this->task, clean-up and return

        $this->task->result = $this->RESULT_CODES[$result->returnCode];
        $this->task->output = $this->task->filterOutput($result->output);
        $this->task->stderr = $this->task->filterStderr($result->stderr);
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
