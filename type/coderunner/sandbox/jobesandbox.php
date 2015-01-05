<?php
/* A sandbox that uses the Jobe server (http://github.com/trampgeek/jobe) to run
 * student submissions.
 * 
 * This version doesn't do any authentication; it's assumed the server is
 * firewalled to accept connections only from Moodle.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->dirroot . '/question/type/coderunner/sandbox/sandboxbase.php';
require_once 'HTTP/Request2.php';


class qtype_coderunner_jobesandbox extends qtype_coderunner_sandbox {

    const DEBUGGING = 0;
    
    private $languages = null;   // Languages supported by this sandbox
    private $response = null;    // Response from HTTP request to server

    // Constructor gets languages from Jobe and stores them.
    // If $this->languages is left null, the Jobe server is down or
    // misconfigured.
    public function __construct() {
        qtype_coderunner_sandbox::__construct();
        list($returncode, $languagepairs) = $this->http_request(
                'languages', HTTP_Request2::METHOD_GET, null);

        if ($returncode == 200 && is_array($languagepairs)) {
            $this->languages = array();
            foreach ($languagepairs as $lang) {
                $this->languages[] = $lang[0];
            }
        } else {
            $this->languages = null;
        }        
    }


    // List of supported languages
    public function get_languages() {
        return $this->languages;
    }
    
    /** Execute the given source code in the given language with the given
     *  input and returns an object with fields error, result, signal, cmpinfo, stderr, output.
     * @param string $sourcecode The source file to compile and run
     * @param string $language  One of the languages regognised by the sandbox
     * @param string $input A string to use as standard input during execution
     * @param associative array $files either null or a map from filename to
     *         file contents, defining a file context at execution time
     * @param associative array $params Sandbox parameters, depends on
     *         particular sandbox but most sandboxes should recognise
     *         at least cputime (secs), memorylimit (Megabytes) and
     *         files (an associative array mapping filenames to string
     *         filecontents.
     *         If the $params array is null, sandbox defaults are used.
     * @return an object with at least an attribute 'error'. This is one of the
     *         values 0 through 8 (OK to UNKNOWN_SERVER_ERROR) as defined above. If
     *         error is 0 (OK), the returned object has additional attributes
     *         result, output, stderr, signal and cmpinfo as follows:
     *             result: one of the result_* constants defined above
     *             output: the stdout from the run
     *             stderr: the stderr output from the run (generally a non-empty
     *                     string is taken as a runtime error)
     *             signal: one of the standard Linux signal values (but often not
     *                     used)
     *             cmpinfo: the output from the compilation run (usually empty
     *                     unless the result code is for a compilation error).
     */
    
    public function execute($sourcecode, $language, $input, $files=null, $params=null)  {
        if ($this->get_languages() === null) {
            return (object) array('error' => qtype_coderunner_sandbox::UNKNOWN_SERVER_ERROR);
        }
        
        $language = strtolower($language);
        if (!in_array($language, $this->get_languages())) {
            // Shouldn't be possible
            throw new coderunner_exception('Executing an unsupported language in sandbox');
        }
        
        if ($input !== '' && substr($input, -1) != "\n") {
            $input .= "\n";  // Force newline on the end if necessary
        }

        $filelist = array();
        if ($files !== null) {
            foreach($files as $filename=>$contents) {
                $id = md5($contents);
                $filelist[] = array($id, $filename);
            }
        }

        $progname = "prog.$language";
        
        $run_spec = array(
                'language_id'       => $language,
                'sourcecode'        => $sourcecode,
                'sourcefilename'    => $progname,
                'input'             => $input,
                'file_list'         => $filelist
            );
             
        if (self::DEBUGGING) {
            $run_spec['debug'] = 1;
        }
        
        if($params !== null) {
            // Process any given sandbox parameters
            $run_spec['parameters'] = $params;
            if (isset($params['debug']) && $params['debug']) {
                $run_spec['debug'] = 1;
            }
            if (isset($params['sourcefilename'])) {
                $run_spec['sourcefilename'] = $params['sourcefilename'];
            }
        }
        
        $postbody = array('run_spec' => $run_spec);
        
        // Try submitting the job. If we get a 404, try again after
        // putting all the files on the server. Anything else is an error.
        $httpcode = $this->submit($postbody);
        if ($httpcode == 404) { // Missing file(s)?
            foreach($files as $filename=>$contents) {
                if (($httpcode = $this->put_file($contents)) != 204) {
                    break;
                }
            }
            if ($httpcode == 204) {
                // Try again if put_files all worked
                $httpcode = $this->submit($postbody);
            }
        }

        if ($httpcode != 200                // We don't deal with Jobe servers that return 202!
            || !is_object($this->response)  // Or any sort of broken ...
            || !isset($this->response->outcome)) {     // ... communication with server.
            return (object) array('error' => qtype_coderunner_sandbox::UNKNOWN_SERVER_ERROR);
        } else {
              return (object) array(
                'error'  => qtype_coderunner_sandbox::OK,
                'result' => $this->response->outcome,
                'signal' => 0,              // Jobe doesn't return this
                'cmpinfo'=> $this->response->cmpinfo,
                'output' => $this->filter_file_path($this->response->stdout),
                'stderr' => $this->filter_file_path($this->response->stderr)
              );
        }
    }

   
   
    // Put the given file to the server, using its MD5 checksum as the id.
    // Returns the HTTP response code, or -1 if the HTTP request fails
    // altogether
    private function put_file($contents) {
        $id = md5($contents);
        $contentsb64 = base64_encode($contents);
        list($httpCode, $body) = $this->http_request("files/$id", 
                HTTP_Request2::METHOD_PUT,
                array('file_contents' => $contentsb64));
        return $httpCode;  
    }
    
    // Submit the given job, which must be an associative array with at
    // least a key 'run_spec'. Return value is the HTTP response code. If
    // the return value is 200, the response is copied into $this->response.
    // We don't at this stage deal with Jobe servers that may defer requests
    // i.e. that return 202 Accepted rather than 200 OK.
    private function submit($job) {
        list($returncode, $response) = $this->http_request('runs', HTTP_Request2::METHOD_POST, $job);
        if ($returncode == 200) {
            $this->response = $response;
        }
        return $returncode;
        
    }
    
    // Send an http request to the Jobe server at the given resource using
    // the given method (HTTP_Request2::METHOD_PUT etc). The body, if given,
    // is json encoded and added to the request. 
    // Return value is a 2-element
    // array containing the http response code and the response body.
    // The code is -1 if the request fails utterly.
    private function http_request($resource, $method, $body=null) {
        $jobe = get_config('qtype_coderunner', 'jobe_host');
        $url = "http://$jobe/jobe/index.php/restapi/$resource";
        $request = new HTTP_Request2($url, $method);
        $request->setHeader('Content-type', 'application/json; charset=utf-8');
        $request->setHeader('Accept', 'application/json');
        if ($body) {
            $request->setBody(json_encode($body));
        }
        
        try {
            $response = $request->send();
            $returncode = $response->getStatus();
            $body = $response->getBody();
            if ($body) {
                $body = json_decode($body);
            }
   
        } catch (HTTP_Request2_Exception $e) {
            $returncode = -1;
        }   
        return array($returncode, $body);
    }
    
    
    // Replace jobe filepaths of the form /home/jobe/runs/<directory>/filename
    // with filename.
    private function filter_file_path($s) {
        return preg_replace('|(/home/jobe/runs/jobe_[a-zA-Z0-9_]+/)([a-zA-Z0-9_]+)|', '$2', $s);
    }
}
?>
