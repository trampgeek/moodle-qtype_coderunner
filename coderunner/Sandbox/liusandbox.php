<?php
/** The concrete implementation of sandboxbase using the Liu sandbox.
 *  See http://openjudge.net/~liuyu/Project/LibSandbox and
 *  http://sourceforge.net/projects/libsandbox/
 *
 * This is a local sandbox, supporting only a single execution run
 * per webservice request. It provides just a wrapper for the real interface
 * which uses Python.
 */

require_once('sandboxbase.php');

class LiuSandbox extends Sandbox {
    private $LANGUAGES = array('C', 'python2', 'python3');
    private $LANG_VERSIONS = array('C' => 'gcc-4.6.3',
        'python2' => 'Python 2.7.3', 'python3' => 'Python 3.2.3');

    // For each language, either define its compiler or its executable
    // TODO: generalise the 'compilers' option for non-C -style languages
    // (currently the -o filename flag is added by the 'compile' method).
    private $COMPILERS = array('C' => 'gcc -Wall -ansi -lm -x c ');
    private $EXECUTABLES = array('python2' => '/usr/bin/python2',
        'python3' => '/usr/bin/python3');

    private $RESULT_CODES = array(
            'PD' => Sandbox::RESULT_INTERNAL_ERR,  // Pending???
            'OK' => Sandbox::RESULT_SUCCESS,
            'RF' => Sandbox::RESULT_ILLEGAL_SYSCALL,
            'RT' => Sandbox::RESULT_RUNTIME_ERROR,
            'TL' => Sandbox::RESULT_TIME_LIMIT,
            'ML' => Sandbox::RESULT_MEMORY_LIMIT,
            'OL' => Sandbox::RESULT_OUTPUT_LIMIT,
            'AT' => Sandbox::RESULT_ABNORMAL_TERMINATION,
            'IE' => Sandbox::RESULT_INTERNAL_ERR,
            'BP' => Sandbox::RESULT_INTERNAL_ERR // Bad policy
    );  // ** TODO: find out what the IE error means

    private static $currentRunId = '99';  // The only one we ever use

    private $lastCompiledCode = '';
    private $lastCompileOutputFile = '';
    private $lastCompileErrors = '';

    public function __construct($user=NULL, $pass=NULL) {
        Sandbox::__construct($user, $pass);
    }

    public function getLanguages() {
        return (object) array('error' => Sandbox::OK, 'languages' => $this->LANGUAGES);
    }


    /** Implement the abstract createSubmission method, which mimics the
     *  ideone function of the same name. Following the ideone API, a call
     *  to this method would generally be followed by calls to
     *  getSubmissionStatus and getSubmissionDetails, using the return 'link'.
     *  This implementation does a compile (if necessary) and run,
     *  rather than queuing the task. Results from this are stored in the
     *  object's instance fields for use by getSubmissionDetails.
     *
     * @param string $sourceCode
     * @param string $language -- must be one of the entries in $LANGUAGES above
     * @param string $input -- stdin for use when running the program
     * @param boolean $run -- hook for ideone compatibility (never used)
     * @param boolean $private -- hook for ideone compatibility (never used)
     * @return object with 'error' field (always '') and 'link' field (always $currentRunId above)
     * @throws coding_exception if I've goofed
     *
     * TODO: add caching of most-recently-compiled C program (or a few such).
     */
    public function createSubmission($sourceCode, $language, $input,
                                        $run=TRUE, $private=TRUE) {
        if (!in_array($language, $this->LANGUAGES, TRUE)) {
            throw new coding_exception('LiuSandbox::createSubmission: Bad language');
        }

        // Copy the sourceCode into a temporary file
        $srcfname = tempnam("/tmp", "coderunner_src_");
        $handle = fopen($srcfname, "w");
        fwrite($handle, $sourceCode);
        fclose($handle);

        // Record input data in $this in case requested in call to getSubmissionDetails
        $this->source = $sourceCode;
        $this->input = $input;
        $this->language = $language;
        if (!$run || !$private) {
            throw new conding_exception('LiuSandbox::createSubmission: unexpected param value');
        }
        $this->date = date("Y-m-d H-i-s");


        if (isset($this->COMPILERS[$language])) {
            // Compile if this language requires compilation
            // Optimise (e.g. when running the same program with multiple different
            // stdin data) to avoid recompiling.

            if ($this->lastCompiledCode === $sourceCode) {
                $executable = $this->lastCompileOutputFile;
                $errors = $this->lastCompileErrors;
            }
            else {
                list($executable, $errors) = $this->compile($srcfname, $language);
            }

            if ($errors === '') {
                $args = array();
                $this->runInSandbox($args, $executable, $input);
            } else {
                $this->result = Sandbox::RESULT_COMPILATION_ERROR;
                $this->cmpinfo = $errors;
                $this->time = 0;
                $this->memory = 0;
                $this->signal = 0;
                $this->output = '';
                $this->stderr = '';
            }

        } else {
            // Interpreted language
            $errors = '';
            $args = array($this->EXECUTABLES[$language], '-BESsu');
            $this->runInSandbox($args, $srcfname, $input);
        }


        unlink($srcfname);
        return (object) array('error' => Sandbox::OK, 'link' => self::$currentRunId);
    }

    public function getSubmissionStatus($link) {
        if ($link !== self::$currentRunId) {
            return (object) array('error' => Sandbox::PASTE_NOT_FOUND);
        } else {
            return (object) array('error' => Sandbox::OK,
                         'status' => Sandbox::STATUS_DONE,
                         'result' => $this->result);
        }
    }

    public function getSubmissionDetails($link, $withSource=FALSE,
            $withInput=FALSE, $withOutput=TRUE, $withStderr=TRUE,
            $withCmpinfo=TRUE) {

        if ($link !== self::$currentRunId) {
            return (object) array('error' => Sandbox::PASTE_NOT_FOUND);
        } else {
            $retVal = (object) array('error' => Sandbox::OK,
                            'status'    => Sandbox::STATUS_DONE,
                            'result'    => $this->result,
                            'langId'    => array_search($this->language, $this->LANGUAGES),
                            'langName'  => $this->language,
                            'langVersion' => $this->LANG_VERSIONS[$this->language],
                            'time'      => $this->time,
                            'date'      => $this->date,
                            'memory'    => $this->memory,
                            'signal'    => $this->signal,
                            'public'    => FALSE);

            if ($withSource) {
                $retVal->source = $this->source;
            }
            if ($withInput) {
                $retVal->input = $this->input;
            }
            if ($withOutput) {
                $retVal->output = $this->output;
            }
            if ($withStderr) {
                $retVal->stderr = $this->stderr;
            }
            if ($withCmpinfo) {
                $retVal->cmpinfo = $this->cmpinfo;
            }
            return $retVal;
        }

    }


    /* This function is called to compile a new src file.
     * The caller should already have checked that the source code
     * being compiled isn't exactly the same as last time.
     */
    private function compile($srcfile, $lang) {
        if ($this->lastCompileOutputFile != '') {
            unlink($this->lastCompileOutputFile);
        }
        $exec = $srcfile . '.exe';
        $errs = $srcfile . '.err';
        $cmd = $this->COMPILERS[$lang] . " -o $exec $srcfile 2>$errs";
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            $compileErrors = '';
        }
        else {
            $compileErrors = file_get_contents($errs);
            $exec = NULL;
        }
        unlink($errs);

        // Cache results to avoid recompiling the same code again
        $this->lastCompiledCode = $this->source;
        $this->lastCompileErrors = $compileErrors;
        $this->lastCompileOutputFile = $exec;

        return array($exec, $compileErrors);
    }

    // On close, delete the last compiler output file (if we have one).
    public function close() {
        if ($this->lastCompileOutputFile != '') {
            unlink($this->lastCompileOutputFile);
        }
    }


    // Run the given command in the sandbox, with the given file and stdin.
    // Results are all left in $this for later access by getSubmissionDetails
    private function runInSandbox($args, $filename, $input) {
       global $CFG;
       // Set up the control params for the sandbox

        $run = array(
            'args'      => $args,
            'filename'  => $filename,
            'input'     => $input,
            'quota'     => array(
                'wallclock' => 30000,    // 30 secs
                'cpu'       => 5000,     // 5 secs
                'memory'    => 64000000, // 64MB
                'disk'      => 1048576   // 1 MB
            )
        );

        // Write the control params to a file, JSON encoded,
        // for use by the sandbox.

        $tmpfname = tempnam("/tmp", "coderunner_json_");
        $handle = fopen($tmpfname, "w");
        $encodedRun = json_encode($run);
        fwrite($handle, $encodedRun);
        fclose($handle);

        // Run the command in the sandbox. Output is a JSON-encoded
        // sandbox-result structure.

        $cmd = $CFG->dirroot . "/question/type/coderunner/Sandbox/liusandbox.py $tmpfname";
        exec($cmd, $output, $returnVar);
        $outputJson = $output[0];
        $result = json_decode($outputJson);

        // Copy result parameters into $this, clean-up and return

        $this->result = $this->RESULT_CODES[$result->returnCode];
        $this->output = $result->output;
        $this->stderr = $result->stderr;
        $this->cmpinfo = '';
        if (isset($result->details->signal_info)) {
            $this->signal = $result->details->signal_info[0];
            $this->time = $result->details->elapsed;
            $this->memory = $result->details->mem_info[0];
        } else {  // Python2 probe in Liu sandbox doesn't return some of the info
            $this->signal = print_r($result->details, TRUE);
            $this->time = 0;
            $this->memory = 0;
        }

        unlink($tmpfname);
    }

}
?>
