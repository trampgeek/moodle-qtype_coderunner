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


class LiuSandbox extends LocalSandbox {
    private $LANGUAGES = array('C');

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
        $reqdClass = 'LiuSandbox\\' . ucwords($language) . '_Task';
        return new $reqdClass($this, $source);
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
                'wallclock' => 1000 * $this->getParam('walltime'),
                'cpu'       => 1000 * $this->getParam('cputime'),
                'memory'    => 1000000 * $this->getParam('memorylimit'),
                'disk'      => 1000000 * $this->getParam('disklimit')
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
