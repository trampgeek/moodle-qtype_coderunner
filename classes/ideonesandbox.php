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

/* A sandbox that uses the remote ideone.com compute server to run
 * student submissions. This is completely safe but gives a poor turn-around,
 * which can be up to a minute. It was developed as a proof of concept of
 * the idea of a remote sandbox and is not recommended for general purpose use.
 *
 * @package    qtype_coderunner
 * @copyright  2012, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_ideonesandbox extends qtype_coderunner_sandbox {
    private $langserror = null;   // The error attribute from the last call to getLanguages.
    private $langmap = null;      // Languages supported by this sandbox: map from name to id.
    //
    // Values of the 'status' attribute of the object returned by
    // a call to the Sphere getSubmissionStatus method.
    const STATUS_WAITING     = -1;
    const STATUS_DONE        = 0;
    const STATUS_COMPILING   = 1;
    const STATUS_RUNNING     = 3;


    public function __construct($user = null, $pass = null) {
        if ($user == null) {
            $user = get_config('qtype_coderunner', 'ideone_user');
        }

        if ($pass == null) {
            $pass = get_config('qtype_coderunner', 'ideone_password');
        }

        qtype_coderunner_sandbox::__construct($user, $pass);

        // A map from Ideone language names (regular expressions) to their
        // local short name, where appropriate.

        $aliases = ['C99 .*'                   => 'c',
                     '.*python *2\.[789]\.[0-9].*'  => 'python2',
                     'Python *3 *\(python.*'        => 'python3',
                     'Java.*sun-jdk.*'              => 'java'];

        $this->client = new SoapClient("http://ideone.com/api/1/service.wsdl");
        $this->langmap = [];  // Construct a map from language name to id.

        // Build a table mapping from language name to Ideone language ID.
        // Names are the Ideone names up to but not including the ' (',
        // converted to lower case. Only the first occurrence of a name is
        // recorded. Also, the aliases c, python2, python3 and java are as
        // above.
        $response = $this->client->getLanguages($user, $pass);
        $this->langserror = $response['error'];

        if ($this->langserror == self::OK) {
            foreach ($response['languages'] as $id => $lang) {
                $endofname = strpos($lang, ' (');
                $shortlangname = strtolower(trim(substr($lang ?? '', 0, $endofname)));
                if (empty($this->langmap[$shortlangname])) {
                    $this->langmap[$shortlangname] = $id;
                }
                foreach ($aliases as $pattern => $alias) {
                    if (preg_match('/' . $pattern . '/', $lang)) {
                        $this->langmap[$alias] = $id;
                    }
                }
            }
        } else {
            $this->langmap = [];
        }
    }


    public function get_languages() {
        return (object) [
            'error'     => $this->langserror,
            'languages' => array_keys($this->langmap)];
    }


    /** Main interface function for use by coderunner but not part of ideone API.
     *  Executes the given source code in the given language with the given
     *  input and returns an object with fields error, result, signal, cmpinfo, stderr, output.
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
        $language = strtolower($language);
        if (!in_array($language, $this->get_languages()->languages)) {
            throw new qtype_coderunner_exception('Executing an unsupported language in sandbox');
        }
        if ($input !== '' && substr($input, -1) != "\n") {
            $input .= "\n";  // Force newline on the end if necessary.
        }
        $result = $this->create_submission(
            $sourcecode,
            $language,
            $input,
            true,
            true,
            $files,
            $params
        );
        $error = $result->error;
        if ($error === self::OK) {
            $state = $this->get_submission_status($result->link);
            $error = $state->error;
        }

        if ($error != self::OK) {
            return (object) ['error' => $error];
        } else {
            $count = 0;
            while (
                $state->error === self::OK &&
                   $state->status !== self::STATUS_DONE &&
                   $count < self::MAX_NUM_POLLS
            ) {
                $count += 1;
                sleep(self::POLL_INTERVAL);
                $state = $this->get_submission_status($result->link);
            }

            if ($count >= self::MAX_NUM_POLLS) {
                throw new qtype_coderunner_exception("Timed out waiting for sandbox");
            }

            if (
                $state->error !== self::OK ||
                    $state->status !== self::STATUS_DONE
            ) {
                throw new coding_exception("Error response or bad status from sandbox");
            }

            $details = $this->get_submission_details($result->link);

            return (object) [
                'error'   => self::OK,
                'result'  => $details->result,
                'output'  => $details->output,
                'stderr'  => $details->stderr,
                'signal'  => $details->signal,
                'cmpinfo' => $details->cmpinfo];
        }
    }


    // Create a submission (a 'paste' in ideone terminology).
    // Return an object with an error and a link field, the latter being
    // the handle for the submission, for use in the following two calls.
    // TODO: come up with a better way of handling non-null $files and
    // $params.
    public function create_submission(
        $sourcecode,
        $language,
        $input,
        $run = true,
        $private = true,
        $files = null,
        $params = null
    ) {
        // Check language is valid.
        assert(in_array($language, $this->get_languages()->languages));
        if ($files !== null && count($files) !== 0) {
            throw new moodle_exception("Ideone sandbox doesn't accept files");
        }

        // Ideally we'd check to see if any $params were provided and raise an
        // exception to say Ideone sandbox doesn't accept them. But $params
        // are provided by default regardless, so for now we'll just ignore them.
        // TODO: if ever ideonesandbox becomes more than just a proof of
        // concept we should try to find a way to warn question authors of
        // the fact that parameters like cpu_time, memory_limit etc are being ignored.

        $langid = $this->langmap[$language];
        $response = $this->client->createSubmission(
            $this->user,
            $this->pass,
            $sourcecode,
            $langid,
            $input,
            $run,
            $private
        );
        $error = $response['error'];
        if ($error !== 'OK') {
            throw new moodle_exception("IdeoneSandbox::get_submission_status: error ($error)");
        } else {
            return (object) ['error' => self::OK, 'link' => $response['link']];
        }
    }

    public function get_submission_status($link) {
        $response = $this->client->getSubmissionStatus($this->user, $this->pass, $link);
        $error = $response['error'];
        if ($error !== "OK") {
                throw new coding_exception("IdeoneSandbox::get_submission_status: error ($error)");
        } else {
            return (object) [
                'error'  => self::OK,
                'status' => $response['status'],
                'result' => $response['result'],
            ];
        }
    }


    // Should only be called if the status is STATUS_DONE. Returns an object
    // with fields error, result, time, memory, signal, cmpinfo, stderr, output.
    public function get_submission_details(
        $link,
        $withsource = false,
        $withinput = false,
        $withoutput = true,
        $withstderr = true,
        $withcmpinfo = true
    ) {

        $response = $this->client->getSubmissionDetails(
            $this->user,
            $this->pass,
            $link,
            $withsource,
            $withinput,
            $withoutput,
            $withstderr,
            $withcmpinfo
        );

        $error = $response['error'];
        if ($error !== 'OK') {
            throw new coding_exception("IdeoneSandbox::getSubmissionStatus: error ($error)");
        } else {
            return (object) [
                'error'   => self::OK,
                'result'  => $response['result'],
                'signal'  => $response['signal'],
                'cmpinfo' => $response['cmpinfo'],
                'output'  => $response['output'],
                'stderr'  => $response['stderr'],

            ];
        }
    }
}
