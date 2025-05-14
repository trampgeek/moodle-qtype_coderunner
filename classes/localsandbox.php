<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/** A LocalSandbox is a subclass of the base qtype_coderunner_sandbox class,
 *  representing a sandbox that runs on the local server, performing compilation locally,
 *  caching compiled files, and processing the entire submission in a single
 *  call, rather than queueing the task for asynchronous procesing or
 *  sending it to a remove web service.
 *  It is assumed that an instance of the local sandbox will be created for
 *  each question run, though possibly not for each testcase, and that each
 *  call to createSubmission will run to completion before returning. Those
 *  conditions ensure that only one submission is running at a time on a particular
 *  sandbox, which allows caching of question-related information in the sandbox
 *  itself during submission.
 */

/**
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2012, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/*******************************************************************
 *
 * LocalSandbox Class
 *
 ******************************************************************/

abstract class qtype_coderunner_localsandbox extends qtype_coderunner_sandbox {
    const SOURCE_FILE_NAME = 'sourcefile';

    protected $source = null;       // Source code for the current task.
    protected $language = null;     // The language of the current task.
    protected $input = null;        // Standard input for the current task.
    protected $params = null;       // The parameters passed to the sandbox.
    protected $files = null;        // Map from filename to filecontents for any run-time files required.
    protected $sourcefilename = null; // Name of saved source in working directory.

    protected $result = null;       // One of the sandbox RESULT_* values defined in the parent class.
    protected $cmpinfo = null;      // Compiler output.
    protected $output = null;       // Stdout of the task.
    protected $stderr = null;       // Stderr from the task.
    protected $signal = null;       // Numeric signal value if run aborted (optional).

    protected $workdir = null;      // The current temporary working directory.

    public function __construct($user = null, $pass = null) {
        qtype_coderunner_sandbox::__construct($user, $pass);
    }


    /**   Execute the given source code in the given language with the given
     *  input and return an object with fields error, result, signal, cmpinfo, stderr, output.
     * @param string $sourcecode The source file to compile and run
     * @param string $language  One of the languages regognised by the sandbox
     * @param string $input A string to use as standard input during execution
     * @param associative array $files either NULL or a map from filename to
     *         file contents, defining a file context at execution time
     * @param associative array $params Sandbox parameters, depends on
     *         particular sandbox but most sandboxes should recognise
     *         at least cputime (secs), memorylimit (Megabytes) and
     *         files (an associative array mapping filenames to string
     *         filecontents.
     *         If the $params array is NULL, sandbox defaults are used.
     * @return an object with at least an attribute 'error'. This is one of the
     *         values 0 through 8 (OK to UNKNOWN_SERVER_ERROR) as defined above. If
     *         error is 0 (OK), the returned object has additional attributes
     *         result, output, signal, stderr, signal and cmpinfo as follows:
     *             result: one of the result_* constants defined above
     *             output: the stdout from the run
     *             stderr: the stderr output from the run (generally a non-empty
     *                     string is taken as a runtime error)
     *             signal: one of the standard Linux signal values (but often not
     *                     used)
     *             cmpinfo: the output from the compilation run (usually empty
     *                     unless the result code is for a compilation error).
     */
    public function execute($sourcecode, $language, $input, $files = null, $params = null) {
        $savedcurrentdir = getcwd();
        $language = strtolower($language);
        if (!in_array($language, $this->get_languages()->languages)) {
            return (object) ['error' => self::WRONG_LANG_ID];  // Should be impossible.
        }
        if ($input !== '' && substr($input, -1) != "\n") {
            $input .= "\n";  // Force newline on the end if necessary.
        }
        // Record input data in $this.
        $this->input = $input;
        $this->language = $language;
        $this->params = $params;
        $this->files = $files;

        // If this is the first call, make a working directory.
        if (empty($this->workdir)) {
            $this->set_path();
            $this->make_directory();
        }

        $this->load_files();  // Do this on every call in case a test run corrupts the files.

        $error = self::OK; // Start by being optimistic.

        if (empty($this->source) || $this->source !== $sourcecode) {
            // Copy sourcecode and recompile if new run or new sourcecode.
            $this->source = $sourcecode;
            $this->save_source();
            $error = $this->compile();
            if ($error === self::OK && !empty($this->cmpinfo)) {
                $this->result = self::RESULT_COMPILATION_ERROR;
            }
        }

        if ($error === self::OK && empty($this->cmpinfo)) {
            $error = $this->run_in_sandbox();
        }

        chdir($savedcurrentdir);
        if ($error === self::OK) {
            return (object) [
                'error'     => self::OK,
                'cmpinfo'   => $this->cmpinfo,
                'result'    => $this->result,
                'stderr'    => $this->stderr,
                'output'    => $this->output,
                'signal'    => $this->signal];
        } else {
            return (object) ['error' => $error];
        }
    }

    // Set up a temporary working directory and copy the current set of
    // files into it.
    private function make_directory() {
        $this->workdir = tempnam("/tmp", "coderunner_");
        if (!unlink($this->workdir) || !mkdir($this->workdir)) {
            throw new coding_exception("localsandbox: error making temp directory (race error?)");
        }
    }

    /**
     * Copy the text in $this->source into the current working directory,
     * naming it self::SOURCE_FILE_NAME. That name is recorded in
     * $this->sourcefilename.
     */
    private function save_source() {
        assert(!empty($this->workdir));
        chdir($this->workdir);
        $handle = fopen(self::SOURCE_FILE_NAME, "w");
        fwrite($handle, $this->source);
        fclose($handle);
        $this->sourcefilename = self::SOURCE_FILE_NAME;
    }


    /**
     * Generate in the current working directory a set of files as defined by the
     * $this->files.
     */
    private function load_files() {
        if ($this->files !== null) {
            chdir($this->workdir);
            foreach ($this->files as $filename => $contents) {
                file_put_contents($filename, $contents);
            }
        }
    }


    /** Delete the working directory and its contents when the set of runs
     *  finishes.
     */
    public function close() {
        self::del_tree($this->workdir);
    }


    // Delete a given directory tree.
    private static function del_tree($dir) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::del_tree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }


    // Check if PHP exec environment includes a PATH. If not, set up a
    // default, or gcc misbehaves. Thanks to Binoj D for this bug fix,
    // needed on his CentOS system.
    private static function set_path() {
        $envvars = [];
        exec('printenv', $envvars);
        $haspath = false;
        foreach ($envvars as $var) {
            if (strpos($var, 'PATH=') === 0) {
                $haspath = true;
                break;
            }
        }
        if (!$haspath) {
            putenv("PATH=/sbin:/bin:/usr/sbin:/usr/bin");
        }
    }

    /****** ABSTRACT METHODS TO BE IMPLEMENTED BY SUBCLASSES ******/

    /**
     * compile $this->source in language $this->language. Set $this->cmpinfo to
     * a non-empty value in the event of a compiler error. Otherwise leave
     * the output object file in the current working directory for use by
     * run_in_sandbox.
     * @return qtype_coderunner_sandbox::ok if run succeeds in the sense
     * of nothing going terribly wrong or qtype_coderunner_sandbox::UNKNOWN_SERVER_ERROR
     * otherwise.
     */
    abstract protected function compile();


    /** Run the task defined by the source, language, input and params attributes
     *  of this in the sandbox, defining the result, stderr, output and
     * signal attributes of $this. If a compilation step is required, this
     * must already have been performed with the compiler output left in
     * $this->cmpinfo (non-empty is taken as a compiler error) and the object
     * code in a location defined by the subclass.
     * @return qtype_coderunner_sandbox::ok if run succeeds in the sense
     * of nothing going terribly wrong or qtype_coderunner_sandbox::UNKNOWN_SERVER_ERROR
     * otherwise.
     */
    abstract protected function run_in_sandbox();
}
