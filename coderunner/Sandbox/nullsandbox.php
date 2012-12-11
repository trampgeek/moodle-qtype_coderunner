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

require_once('sandboxbase.php');


class NullSandbox extends Sandbox {
    private $LANGUAGES = array(
        'matlab'    => array(
            'version'      => 'Matlab R2012a',
            'executable'   => '',
            'compile'      => '',
            'run'          => "/usr/local/Matlab2012a/bin/glnxa64/MATLAB -nodisplay -nojvm -r '{{SOURCE_FILE_BASENAME}}()'",
        )
    );

    private static $currentRunId = '99';  // The only one we ever use


    public function __construct($user=NULL, $pass=NULL) {
        Sandbox::__construct($user, $pass);
    }

    public function getLanguages() {
        return (object) array('error' => Sandbox::OK,
            'languages' => array_keys($this->LANGUAGES));
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
     */
    public function createSubmission($sourceCode, $language, $input,
                                        $run=TRUE, $private=TRUE) {
        if (!isset($this->LANGUAGES[$language])) {
            throw new coding_exception('NullSandbox::createSubmission: Bad language');
        }

        $languageDetails = $this->LANGUAGES[$language];

        // Record input data in $this in case requested in call to getSubmissionDetails
        $this->source = $sourceCode;
        $this->input = $input;
        $this->language = $language;

        // Create a temporary directory and within that a temporary file
        // containing the source code.

        $tempdir=tempnam(sys_get_temp_dir(),"coderunner_");
        unlink($tempdir);
        if (!mkdir($tempdir)) {
            die("NullSandbox: race error making directory");
        }

        $srcfname = tempnam($tempdir, "");
        $handle = fopen($srcfname, "w");
        fwrite($handle, $sourceCode);
        fclose($handle);

        if (!$run || !$private) {
            throw new coding_exception('NullSandbox::createSubmission: unexpected param value');
        }
        $this->date = date("Y-m-d H-i-s");

        $errors = '';
        $errorFileName = $srcfname . '.err';
        $executableFileName = $this->expand($languageDetails['executable'], $srcfname);

        $this->result = Sandbox::RESULT_SUCCESS;
        $this->cmpinfo = '';
        $this->time = 0;
        $this->memory = 0;
        $this->signal = 0;
        $this->output = '';
        $this->stderr = '';

        chdir($tempdir);

        if ($languageDetails['compile'] !== '') {
            // Compile if this language requires compilation

            $compileCommand = $this->expand($languageDetails['compile'], $srcfname);
            $errors = $this->compile($compileCommand, $errorFileName);

            if ($errors !== '') {
                $this->result = Sandbox::RESULT_COMPILATION_ERROR;
                $this->cmpinfo = $errors;
            }
        }

        if ($errors === '') {
            $executeCommand = $this->expand($languageDetails['run'], $srcfname);
            $this->runProgram($executeCommand, $executableFileName, $input);
        }

        //deltree($srcfname);
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
                            'langVersion' => $this->LANGUAGES[$this->language]['version'],
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
    private function compile($compileCommand, $errorFileName) {
        exec($compileCommand, $output, $returnVar);
        if ($returnVar == 0) {
            $compileErrors = '';
        }
        else {
            $compileErrors = file_get_contents($errorFileName);
        }
        unlink($errorFileName);

        return $compileErrors;
    }


    // Run the given command (not in a sandbox) with the given filename
    // appended.
    // Results are all left in $this for later access by getSubmissionDetails
    private function runProgram($executeCommand, $filename, $input) {
        global $CFG;

        exec($executeCommand . ' ' . $filename, $output, $returnVar);

        // Copy result parameters into $this, clean-up and return

        $this->output = implode("\n", $output);
        debugging("$executeCommand\n$filename\n{$this->output}");
        $this->stderr = '';  // How to capture this??
        $this->cmpinfo = '';
        if ($returnVar == 0) {
            $this->result = Sandbox::RESULT_SUCCESS;
        } else {
            $this->result = Sandbox::RESULT_RUNTIME_ERROR;
        }
    }


    // Expand a given template, replacing all occurrences of '{{SOURCE_FILE}}'
    // with the given actual file name and '{{SOURCE_FILE_BASENAME}} with
    // the base name of the given source file.
    private function expand($template, $srcfilename) {
        $basename = basename($srcfilename);
        $s = str_replace('{{SOURCE_FILE}}', $srcfilename, $template);
        return str_replace('{{SOURCE_FILE_BASENAME}}', $basename, $s);
    }
}


// Delete a given directory tree
function delTree($dir) {
   $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}
?>
