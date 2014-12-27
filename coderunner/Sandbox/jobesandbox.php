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

require_once('sandboxbase.php');
require_once 'HTTP/Request2.php';

define('DEBUGGING', '0');

class JobeSandbox extends Sandbox {

    var $languages = NULL;   // Languages supported by this sandbox
    var $status = NULL;      // Status as set by constructor

    public function __construct() {
        // Constructor gets languages from Jobe and stores them.
        Sandbox::__construct();
        list($returnCode, $language_pairs) = $this->httpRequest(
                'languages', HTTP_Request2::METHOD_GET, NULL);

        if ($returnCode == 200 && is_array($language_pairs)) {
            $this->languages = array();
            foreach ($language_pairs as $lang) {
                $this->languages[] = $lang[0];
            };
            $this->status = Sandbox::OK; 
        } else {
            $this->status = Sandbox::UNKNOWN_SERVER_ERROR;
        }        
    }


    // Returns an object containing an error field and a languages field,
    // where the latter is a list of strings of languages handled by this sandbox.
    // This latter consists of all the languages returned by a query to Jobe.
    public function getLanguages() {
        
        $resultObj = (object) array('error'    => $this->status,
                                    'languages'=> $this->languages);
        return $resultObj;
    }


    // Create a submission.
    // Return an object with an error and a link field, the latter being
    // the handle for the submission, for use in the following two calls.

    public function createSubmission($sourceCode, $language, $input,
            $run=TRUE, $private=TRUE, $files=NULL, $params = NULL)
    {
        // Check language is valid
        if (!in_array($language, $this->getLanguages()->languages)) {
            return (object) array('error' => Sandbox::WRONG_LANG_ID,
                                  'link' => 0);
        }
        $fileList = array();
        if ($files !== NULL) {
            foreach($files as $filename=>$contents) {
                $id = md5($contents);
                $fileList[] = array($id, $filename);
            }
        }

        $progname = "prog.$language";
        
        $run_spec = array(
                'language_id'       => $language,
                'sourcecode'        => $sourceCode,
                'sourcefilename'    => $progname,
                'input'             => $input,
                'file_list'         => $fileList
            );
             
        if (DEBUGGING) {
            $run_spec['debug'] = 1;
        }
        
        if($params !== NULL) {
            $run_spec['parameters'] = $params;
            if (isset($params['debug']) && $params['debug']) {
                $run_spec['debug'] = 1;
            }
            if (isset($params['sourcefilename'])) {
                $run_spec['sourcefilename'] = $params['sourcefilename'];
            }
        }
        
        $postBody = array('run_spec' => $run_spec);
        
        // Try submitting the job. If we get a 404, try again after
        // putting all the files on the server. Anything else is an error.
        $httpCode = $this->submit($postBody);
        if ($httpCode == 404) { // Missing file(s)?
            foreach($files as $filename=>$contents) {
                if (($httpCode = $this->putFile($contents)) != 204) {
                    break;
                }
            }
            if ($httpCode == 204) {
                // Try again if put_files all worked
                $httpCode = $this->submit($postBody);
            }
        }

        if ($httpCode == 200) {  // We don't deal with Jobe servers that return 202!
            $status = Sandbox::OK;  // (And resultObject has been saved in this)
        } else {
            $status = Sandbox::UNKNOWN_SERVER_ERROR;
        }
        $this->link = rand();  // A constant should do but this protects against inadvertent re-enty
        $answer = (object) array('error'=>$status, 'link'=>$this->link);
        return $answer;
    }

    public function getSubmissionStatus($link) {
        if ($link != $this->link) {
            throw new coding_exception("link mismatch in jobesandbox");
        }
        
        if (is_object($this->resultObj) && isset($this->resultObj->outcome)) {
            return (object) array(
                    'error' => Sandbox::OK,
                    'status'=> Sandbox::STATUS_DONE,
                    'result'=> $this->resultObj->outcome
            );
        } else {
            return (object) array(
                    'error' => Sandbox::OK,
                    'status'=> Sandbox::UNKNOWN_SERVER_ERROR
            );
        }
    }


    // Should only be called if the status is STATUS_DONE. Returns an object
    // with fields error, time, memory, signal, cmpinfo, stderr, output.
    public function getSubmissionDetails($link, $withSource=FALSE,
            $withInput=FALSE, $withOutput=TRUE, $withStderr=TRUE,
            $withCmpinfo=TRUE)
    {
        if ($link != $this->link) {
            throw new coding_exception("link mismatch in jobesandbox");
        }

        return (object) array(
                'error'  => Sandbox::OK,
                'time'   => 0,  // TODO - consider if this needs fixing
                'memory' => 0,  // TODO - consider if this needs fixing
                'signal' => 0,  // TODO - consider if this needs fixing
                'cmpinfo'=> $this->resultObj->cmpinfo,
                'output' => $this->filter_file_path($this->resultObj->stdout),
                'stderr' => $this->filter_file_path($this->resultObj->stderr)
        );
    }
    
    
    // Put the given file to the server, using its MD5 checksum as the id.
    // Returns the HTTP response code, or -1 if the HTTP request fails
    // altogether
    private function putFile($contents) {
        $id = md5($contents);
        $contentsb64 = base64_encode($contents);
        list($httpCode, $body) = $this->httpRequest("files/$id", 
                HTTP_Request2::METHOD_PUT,
                array('file_contents' => $contentsb64));
        return $httpCode;  
    }
    
    // Submit the given job, which must be an associative array with at
    // least a key 'run_spec'. Return value is the HTTP response code. If
    // the return value is 200, the response is copied into $this->resultObj.
    // We don't at this stage deal with Jobe servers that may defer requests,
    // returning 202 Accepted rather than 200 OK.
    private function submit($job) {
        list($returnCode, $response) = $this->httpRequest('runs',
                HTTP_Request2::METHOD_POST, $job);
        if ($returnCode == 200) {
            $this->resultObj = $response;
        }
        return $returnCode;
        
    }
    
    // Send an http request to the Jobe server at the given resource using
    // the given method (HTTP_Request2::METHOD_PUT etc). The body, if given,
    // is json encoded and added to the request. 
    // Return value is a 2-element
    // array containing the http response code and the response body.
    // The code is -1 if the request fails utterly.
    private function httpRequest($resource, $method, $body=NULL) {
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
            $returnCode = $response->getStatus();
            $body = $response->getBody();
            if ($body) {
                $body = json_decode($body);
            }
   
        } catch (HTTP_Request2_Exception $e) {
            $returnCode = -1;
        }   
        return array($returnCode, $body);
    }
    
    
    // Replace jobe filepaths of the form /home/jobe/runs/<directory>/filename
    // with filename.
    private function filter_file_path($s) {
        return preg_replace('|(/home/jobe/runs/jobe_[a-zA-Z0-9_]+/)([a-zA-Z0-9_]+)|', '$2', $s);
    }
}
?>
