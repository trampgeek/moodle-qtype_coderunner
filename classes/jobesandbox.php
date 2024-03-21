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

/* A sandbox that uses the Jobe server (http://github.com/trampgeek/jobe) to run
 * student submissions.
 *
 * This version doesn't do any authentication; it's assumed the server is
 * firewalled to accept connections only from Moodle.
 *
 * @package    qtype_coderunner
 * @copyright  2014, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php'); // Needed when run as web service.

class qtype_coderunner_jobesandbox extends qtype_coderunner_sandbox {
    const DEBUGGING = 0;
    const HTTP_GET = 1;
    const HTTP_POST = 2;
    const HTTP_PUT = 3;

    /**
     * @var string when this variable is set, it is added as a HTTP
     * header X-CodeRunner-Job-Id: to every API call we make.
     *
     * This is intended for use when load-balancing over multiple instances
     * of JOBE, so that a sequence of related API calls can all be
     * routed to the same JOBE instance. Typically a particular value
     * of the job id will not be used for more than a few seconds,
     * so quite a short time-out can be used.
     *
     * Typical load-balancer config might be:
     *  - with haproxy try "balance hdr(X-CodeRunner-Job-Id)" (not tested)
     *  - with a Netscaler use rule-based persistence with expression
     *    HTTP.REQ.HEADER(“X-CodeRunner-Job-Id”)
     *  - with cookies support
     */
    private $currentjobid = null;

    private $languages = null;   // Languages supported by this sandbox.
    private $httpcode = null;    // HTTP response code.
    private $response = null;    // Response from HTTP request to server.

    /** @var ?string Jobe server host name. */
    private $jobeserver;

    /** @var ?string Jobe server API-key. */
    private $apikey;

    // Constructor gets languages from Jobe and stores them.
    // If $this->languages is left null, the Jobe server is down or
    // refusing requests or misconfigured. The actual HTTP returncode and response
    // are left in $httpcode and $response resp.
    public function __construct() {
        global $CFG;
        qtype_coderunner_sandbox::__construct();
        $this->jobeserver = get_config('qtype_coderunner', 'jobe_host');
        if (qtype_coderunner_sandbox::is_canterbury_server($this->jobeserver)
                && qtype_coderunner_sandbox::is_using_test_sandbox()) {
            throw new Exception("Please don't use the Canterbury jobe server for test runs");
        }
        $this->apikey = get_config('qtype_coderunner', 'jobe_apikey');
        $this->languages = null;
    }


    // List of supported languages.
    public function get_languages() {
        if ($this->languages === null) {
            [$this->httpcode, $this->response] = $this->http_request(
                'languages',
                self::HTTP_GET
            );
            if ($this->httpcode == 200 && is_array($this->response)) {
                $this->languages = [];
                foreach ($this->response as $lang) {
                    $this->languages[] = $lang[0];
                }
            } else {
                $this->languages = [];
            }
        }
        return (object) [
            'error'     => $this->get_error_code($this->httpcode),
            'languages' => $this->languages];
    }

    /** Execute the given source code in the given language with the given
     *  input and returns an object with fields error, result, signal, cmpinfo,
     *  stderr, output.
     * @param string $sourcecode The source file to compile and run
     * @param string $language  One of the languages recognised by the sandbox
     * @param string $input A string to use as standard input during execution
     * @param associative array $files either null or a map from filename to
     *         file contents, defining a file context at execution time
     * @param associative array $params Sandbox parameters, depends on
     *         particular sandbox but most sandboxes should recognise
     *         at least cputime (secs) and memorylimit (Megabytes).
     *         If the $params array is null, sandbox defaults are used.
     * @return an object with at least the attribute 'error'.
     *         The error attribute is one of the
     *         values 0 through 9 (OK to UNKNOWN_SERVER_ERROR, OVERLOAD)
     *         as defined in the base class. If
     *         error is 0 (OK), the returned object has additional attributes
     *         result, output, stderr, signal and cmpinfo as follows:
     *             result: one of the result_* constants defined in the base class
     *             output: the stdout from the run
     *             stderr: the stderr output from the run (generally a non-empty
     *                     string is taken as a runtime error)
     *             signal: one of the standard Linux signal values (but often not
     *                     used)
     *             cmpinfo: the output from the compilation run (usually empty
     *                     unless the result code is for a compilation error).
     *         If error is anything other than OK, the attribute stderr will
     *         contain the text of the actual HTTP response header, e.g
     *         Bad Parameter if the response was 400 Bad Parameter.
     *         If the run was actually submitted to a jobe server, the returned
     *         object also has an attribute 'sandboxinfo', which
     *         is an associative array with the keys 'jobeserver' and 'jobeapikey'
     *         showing which jobeserver was used and what key was used (if any).
     */

    public function execute($sourcecode, $language, $input, $files = null, $params = null) {
        global $CFG;

        $language = strtolower($language);
        if (is_null($input)) {
            $input = '';
        }
        if ($input !== '' && substr($input, -1) != "\n") {
            $input .= "\n";  // Force newline on the end if necessary.
        }

        if ($language === 'java') {
            $mainclass = $this->get_main_class($sourcecode);
            if ($mainclass) {
                $progname = "$mainclass.$language";
            } else {
                $progname = 'NO_PUBLIC_CLASS_FOUND.java';  // I give up. Over to the sandbox. Will probably fail.
            }
        } else {
            $progname = "__tester__.$language";
        }

        $filelist = [];
        if ($files !== null) {
            foreach ($files as $filename => $contents) {
                if ($filename == $progname) {
                    // If Jobe has named the progname the same as filename, throw an error.
                    $badname['error'] = self::JOBE_400_ERROR;
                    $badname['stderr'] = get_string('errorstring-duplicate-name', 'qtype_coderunner');
                    return (object) $badname;
                }
                $id = md5($contents);
                $filelist[] = [$id, $filename];
            }
        }

        $runspec = [
                'language_id'       => $language,
                'sourcecode'        => $sourcecode,
                'sourcefilename'    => $progname,
                'input'             => $input,
                'file_list'         => $filelist,
            ];

        if (self::DEBUGGING) {
            $runspec['debug'] = 1;
        }

        if ($params !== null) {
            // Process any given sandbox parameters.
            $runspec['parameters'] = $params;
            if (isset($params['debug']) && $params['debug']) {
                $runspec['debug'] = 1;
            }
            if (isset($params['sourcefilename'])) {
                $runspec['sourcefilename'] = $params['sourcefilename'];
            }
            if (isset($params['jobeserver'])) {
                $this->jobeserver = $params['jobeserver'];
            }
            if (isset($params['jobeapikey'])) {
                $this->apikey = $params['jobeapikey'];
            }
        }

        $postbody = ['run_spec' => $runspec];
        $this->currentjobid = sprintf('%08x', mt_rand());

        // Create a single curl object here, with support for cookies, and use it for all requests.
        // This supports Jobe back-ends that use cookies for sticky load-balancing.
        // Make a place to store the cookies.
        make_temp_directory('qtype_coderunner');
        $cookiefile = $CFG->tempdir . '/qtype_coderunner/session_cookies_' . $this->currentjobid . '.txt';

        $curl = new curl();
        $curl->setopt([
                'CURLOPT_COOKIEJAR' => $cookiefile,
                'CURLOPT_COOKIEFILE' => $cookiefile,
        ]);

        // Try submitting the job. If we get a 404, try again after
        // putting all the files on the server. Anything else is an error.
        $httpcode = $this->submit($postbody, $curl);
        if ($httpcode == 404) { // If it's a file not found error ...
            foreach ($files as $filename => $contents) {
                if (($httpcode = $this->put_file($contents, $curl)) != 204) {
                    break;
                }
            }
            if ($httpcode == 204) {
                // Try again if put_files all worked.
                $httpcode = $this->submit($postbody, $curl);
            }
        }

        // Delete the cookie file.
        @unlink($cookiefile);

        $runresult = [];
        $runresult['sandboxinfo'] = [
            'jobeserver' => $this->jobeserver,
            'jobeapikey' => $this->apikey,
        ];

        $okresponse = in_array($httpcode, [200, 203]);  // Allow 203, which can result from an intevening proxy server.
        if (
            !$okresponse                        // If it's not an OK response...
                || !is_object($this->response)  // ... or there's any sort of broken ...
                || !isset($this->response->outcome)
        ) {     // ... communication with server.
            // Return with errorcode set and as much extra info as possible in stderr.
            $errorcode = $okresponse ? self::UNKNOWN_SERVER_ERROR : $this->get_error_code($httpcode);
            $this->currentjobid = null;
            $runresult['error'] = $errorcode;
            $runresult['stderr'] = "HTTP response from Jobe was $httpcode: " . json_encode($this->response);
        } else if ($this->response->outcome == self::RESULT_SERVER_OVERLOAD) {
            $runresult['error'] = self::SERVER_OVERLOAD;
        } else {
            $stderr = $this->filter_file_path($this->response->stderr);
            // Any stderr output is treated as a runtime error.
            if (trim($stderr ?? '') !== '') {
                $this->response->outcome = self::RESULT_RUNTIME_ERROR;
            }
            $this->currentjobid = null;
            $runresult['error'] = self::OK;
            $runresult['stderr'] = $stderr;
            $runresult['result'] = $this->response->outcome;
            $runresult['signal'] = 0; // Jobe doesn't return signals.
            $runresult['cmpinfo'] = $this->response->cmpinfo;
            $runresult['output'] = $this->filter_file_path($this->response->stdout);
        }
        return (object) $runresult;
    }

    // Return the name of the main class in the given Java prog, or FALSE if no
    // such class found. Removes comments, strings and nested code and then
    // uses a regexp to find a public class.
    private function get_main_class($prog) {
        // filter out comments and strings
        $prog = $prog . ' ';
        $filteredProg = array();
        $skipTo = -1;

        for ($i = 0; $i < strlen($prog) - 1; $i++) {
            if ($skipTo == false) break;  // an unclosed comment/string - bail out
            if ($i < $skipTo) continue;

            // skip "//" comments
            if ($prog[$i].$prog[$i+1] == '//') {
                $skipTo = strpos($prog, "\n", $i + 2);
            }

            // skip "/**/" comments
            else if ($prog[$i].$prog[$i+1] == '/*') {
                $skipTo = strpos($prog, '*/', $i + 2) + 2;
                $filteredProg[] = ' ';  // '/**/' is a token delimiter
            }

            // skip strings
            else if ($prog[$i] == '"') {
                // matches the whole string
                if (preg_match('/"((\\.)|[^\\"])*"/', $prog, $matches, 0, $i)) {
                    $skipTo = $i + strlen($matches[0]);
                }
                else $skipTo = false;
            }

            // copy everything else
            else $filteredProg[] = $prog[$i];
        }

        // remove nested code
        $depth = 0;
        for ($i = 0; $i < count($filteredProg); $i++) {
            if ($filteredProg[$i] == '{') $depth++;
            if ($filteredProg[$i] == '}') $depth--;
            if ($filteredProg[$i] != "\n" && $depth > 0 && !($depth == 1 && $filteredProg[$i] == '{')) {
                $filteredProg[$i] = ' ';
            }
        }

        // search for a public class
        if (preg_match('/public\s(\w*\s)*class\s*(\w+)[^\w]/', implode('', $filteredProg), $matches) !== 1) {
            return false;
        } else {
            return $matches[2];
        }
    }

	

    // Return the sandbox error code corresponding to the given httpcode.
    private function get_error_code($httpcode) {
        $codemap = [
            '200' => self::OK,
            '202' => self::OK,
            '204' => self::OK,
            '400' => self::JOBE_400_ERROR,
            '401' => self::SUBMISSION_LIMIT_EXCEEDED,
            '403' => self::AUTH_ERROR,
        ];
        if (isset($codemap[$httpcode])) {
            return $codemap[$httpcode];
        } else {
            return self::UNKNOWN_SERVER_ERROR;
        }
    }

    // Put the given file to the server, using its MD5 checksum as the id.
    // If you pass a curl object, this will be used to make the request.
    // Returns the HTTP response code, or -1 if the HTTP request fails
    // altogether.
    private function put_file($contents, $curl) {
        $id = md5($contents);
        $contentsb64 = base64_encode($contents);
        $resource = "files/$id";

        [$url, $headers] = $this->get_jobe_connection_info($resource);

        $body = ['file_contents' => $contentsb64];
        $result = $curl->put($url, json_encode($body));
        $returncode = $curl->info['http_code'];
        return $result === false ? -1 : $returncode;
    }

    /**
     * Helper method  used by put_file and http_request.
     *
     * Determine the URL for a particular API call, and
     * also get the HTTP headers that should be sent.
     *
     * @param string $resource specific REST API call to add to the URL.
     * @return array with two elements, the URL for the given resource,
     * and the HTTP headers that should be used in the request.
     */
    private function get_jobe_connection_info($resource) {
        $jobe = $this->jobeserver;
        if (strpos($jobe, ';') !== false) {
            // Support multiple servers - thanks Khang Pham Nguyen KHANG: 2021/10/18.
            $servers = array_values(array_filter(array_map('trim', explode(';', $jobe)), 'strlen'));
            if ($this->currentjobid) {
                // Make sure to use the same jobe server when files are involved.
                $rand = intval($this->currentjobid, 16);
            } else {
                $rand = mt_rand();
            }
            $jobe = $servers[$rand % count($servers)];
        }
        $jobe = trim($jobe); // Remove leading or trailing extra whitespace from the settings.
        $protocol = 'http://';
        $url = (strpos($jobe, 'http') === 0 ? $jobe : $protocol . $jobe) . "/jobe/index.php/restapi/$resource";

        $headers = [
                'User-Agent: CodeRunner',
                'Content-Type: application/json; charset=utf-8',
                'Accept-Charset: utf-8',
                'Accept: application/json',
        ];
        if (!empty($this->apikey)) {
            $headers[] = "X-API-KEY: $this->apikey";
        }
        if (!empty($this->currentjobid)) {
            $headers[] = "X-CodeRunner-Job-Id: $this->currentjobid";
        }

        return [$url, $headers];
    }

    // Submit the given job, which must be an associative array with at
    // least a key 'run_spec'. Return value is the HTTP response code.
    // The actual response is copied into $this->response.
    // In cases where the response code is not 200, the response is typically
    // the message associated with the response, e.g. Bad Parameter is the
    // response was 400 Bad Parameter.
    // We don't at this stage deal with Jobe servers that may defer requests
    // i.e. that return 202 Accepted rather than 200 OK.
    // If you pass a curl object, this will be used to make the request.
    private function submit($job, $curl) {
        [$returncode, $response] = $this->http_request('runs', self::HTTP_POST, $job, $curl);
        $this->response = $response;
        return $returncode;
    }

    // Send an http request to the Jobe server at the given
    // resource using the given method (self::HTTP_GET or self::HTTP_POST).
    // The body, if given, is json encoded and added to the request.
    // Return value is a 2-element
    // array containing the http response code and the response body (decoded
    // from json).
    // The code is -1 if the request fails utterly.
    // Note that the Moodle curl class documentation lies when it says the
    // return value from get and post is a bool. It's either the value false
    // if the request failed or the actual string response, otherwise.
    // If you pass a curl object, this will be used to make the request.
    private function http_request($resource, $method, $body = null, $curl = null) {
        [$url, $headers] = $this->get_jobe_connection_info($resource);

        if ($curl == null) {
            $curl = new curl();
        }
        $curl->setHeader($headers);

        if ($method === self::HTTP_GET) {
            if (!empty($body)) {
                throw new coding_exception("Illegal HTTP GET: non-empty body");
            }
            $response = $curl->get($url);
        } else if ($method === self::HTTP_POST) {
            if (empty($body)) {
                throw new coding_exception("Illegal HTTP POST: empty body");
            }
            $bodyjson = json_encode($body);
            $response = $curl->post($url, $bodyjson);
        } else {
            throw new coding_exception('Invalid method passed to http_request');
        }

        if ($response !== false) {
            // We got a response rather than a completely failed request.
            if (isset($curl->info['http_code'])) {
                $returncode = $curl->info['http_code'];
                $responsebody = $response === '' ? '' : json_decode($response);
            } else {
                // Various weird stuff lands here, such as URL blocked.
                // Hopefully the value of $response is useful.
                $returncode = -1;
                $responsebody = json_encode($response);
            }
        } else {
            // Request failed.
            $returncode = -1;
            $responsebody = '';
        }

        return [$returncode, $responsebody];
    }


    // Replace jobe filepaths of the form /home/jobe/runs/<directory>/filename
    // with filename.
    private function filter_file_path($s) {
        return preg_replace('|(/home/jobe/runs/jobe_[a-zA-Z0-9_]+/)([a-zA-Z0-9_]+)|', '$2', $s);
    }
}
