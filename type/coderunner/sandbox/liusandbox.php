<?php
/** The concrete implementation of qtype_coderunner_sandbox using the Liu sandbox.
 *  See http://openjudge.net/~liuyu/Project/LibSandbox and
 *  http://sourceforge.net/projects/libsandbox/
 * 
 * This current implementation handles just C, though it could easily
 * be adapted to C++. Running other languages like Java or Python in this
 * sandbox has proven difficult because of the wide range of system calls
 * and accesses to surprising parts of the file system made by such languages.
 *
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012, 2013, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once('localsandbox.php');


// ==============================================================
//
// Now the actual Lius sandbox (or, really, the interface to the Python
// driver for the Liu sandbox).
//
// ==============================================================


class qtype_coderunner_liusandbox extends qtype_coderunner_localsandbox {
    
    private $COMPILE_OPTIONS = '-Wall -Werror -std=c99 -static -x c';
    
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
    
    private $executablefilename = null;

    public function __construct($user=null, $pass=null) {
        qtype_coderunner_localsandbox::__construct($user, $pass);
    }

    public function get_languages() {
        return (object) array('error' => self::OK, 'languages' => $this->LANGUAGES);
    }
    
    
    public function compile() {
        assert($this->language === 'c');  // All we support
        $src = basename($this->sourcefilename);
        $errorfilename = "$src.err";
        $execfilename = "$src.exe";
        
        $cmd = "gcc {$this->COMPILE_OPTIONS} -o $execfilename $src -lm 2>$errorfilename";
        // To support C++ instead use something like ...
        // $cmd = "g++ -Wall -Werror -static -x c++ -o $execFileName $src -lm 2>$errorFileName";
        
        $returnvar = 0;
        exec($cmd, $output, $returnvar);
        if ($returnvar == 0) {
            $this->cmpinfo = '';
            $this->executablefilename = $execfilename;
        }
        else {
            $this->cmpinfo = file_get_contents($errorfilename);
        } 
        return qtype_coderunner_sandbox::OK;
    }


    /** Run the task defined by the source, language, input and params attributes
     *  of this is the sandbox, defining the result, stderr, output and
     * signal attributes of $this. If a compilation step is required, this
     * must already have been performed with the compiler output left in 
     * $this->cmpinfo (non-empty is taken as a compiler error) and the object
     * code in a location defined by the subclass.
     * @return qtype_coderunner_sandbox::ok if run succeeds in the sense
     * of nothing going terribly wrong or qtype_coderunner_sandbox::UNKNOWN_SERVER_ERROR
     * otherwise.
     */
    protected function run_in_sandbox() {
       global $CFG;

       // Set up the control params for the sandbox

        $run = array(
            'cmd'       => $this->executablefilename,
            'input'     => $this->input,
            'quota'     => array(
                'wallclock' => 1000 * $this->get_param('walltime'),
                'cpu'       => 1000 * $this->get_param('cputime'),
                'memory'    => 1000000 * $this->get_param('memorylimit'),
                'disk'      => 1000000 * $this->get_param('disklimit')
            ),
            'readableDirs' => array(),
            'workdir'      => $this->workdir
        );

        // Write the control params to a file, JSON encoded,
        // for use by the sandbox.

        chdir($this->workdir);
        $taskname = "taskdetails.json";
        $handle = fopen($taskname, "w");
        $encodedrun = json_encode($run);
        fwrite($handle, $encodedrun);
        fclose($handle);

        // Run the command in the sandbox. Output is a JSON-encoded
        // sandbox-result structure.

        $cmd = $CFG->dirroot . "/question/type/coderunner/sandbox/liusandbox.py $taskname";

        $returnvar = 0;
        exec($cmd, $output, $returnvar);
        $outputjson = $output[0];
        $response = json_decode($outputjson);

        // Copy result parameters into $this->task, clean-up and return

        $this->result = $this->RESULT_CODES[$response->returnCode];
        $this->output = $response->output;
        $this->stderr = $response->stderr;
        $this->cmpinfo = '';
        if (isset($response->details->signal_info)) {
            $this->signal = $response->details->signal_info[0];
        } else {  // An internal error doesn't set all fields
            $this->signal = 0;
        }
        return qtype_coderunner_sandbox::OK;
    }
}

