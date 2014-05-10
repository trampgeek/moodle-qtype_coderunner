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


class JobeSandbox extends Sandbox {

    var $langMap = NULL;   // Languages supported by this sandbox: map from name to id

    public function __construct() {

        Sandbox::__construct();
    }


    // Returns an object containing an error field and a languages field,
    // where the latter is a list of strings of languages handled by this sandbox.
    // This latter consists of all the languages returned by a query to Jobe.
    public function getLanguages() {
        $jobe = get_config('qtype_coderunner', 'jobe_host');
        $url = "http://$jobe/jobe/index.php/restapi/languages";
        $request = new HTTP_Request2($url, HTTP_Request2::METHOD_GET);
        $request->setHeader('Accept', 'application/json');
        $status = Sandbox::UNKNOWN_SERVER_ERROR;
        $langKeys = array();
        try {
            $response = $request->send();
            if ($response->getStatus() == 200) {
                $languages = json_decode($response->getBody());
                if (is_array($languages)) {
                    $langKeys = array();
                    foreach ($languages as $lang) {
                        $langKeys[] = $lang[0];
                    };
                    $status = Sandbox::OK;
                }
            }
        } catch (HTTP_Request2_Exception $e) {
            debugging("Http error" . $e);
            $status = Sandbox::HTTP_ERROR;
        }
        $resultObj = (object) array('error'=>$status, 'languages'=>$langKeys);
        return $resultObj;
    }


    // Create a submission.
    // Return an object with an error and a link field, the latter being
    // the handle for the submission, for use in the following two calls.

    public function createSubmission($sourceCode, $language, $input,
            $run=TRUE, $private=TRUE, $files=NULL, $params = NULL)
    {
        $extensions = array('c' => '.c', 'python3' => '.py');
        // Check language is valid
        if (!in_array($language, $this->getLanguages()->languages)) {
            return (object) array('error' => Sandbox::WRONG_LANG_ID,
                                  'link' => 0);
        }
        $fileList = array();
        if ($files !== NULL) {
            foreach($files as $filename=>$contents) {
                $id = $this->putFile($contents);
                $fileList[] = array($id, $filename);
            }
        }
        if($params !== NULL) {
            throw new moodle_exception('params not yet implemented');
        }

        $progname = "prog{$extensions[$language]}";
        $postBody = array('run_spec' =>
            array(
                'language_id'       => $language,
                'sourcecode'        => $sourceCode,
                'sourcefilename'    => $progname,
                'input'             => $input,
                'files'             => $fileList
            )
        );
        
        $jobe = get_config('qtype_coderunner', 'jobe_host');
        $url = "http://$jobe/jobe/index.php/restapi/runs";
        $request = new HTTP_Request2($url, HTTP_Request2::METHOD_POST);
        $request->setHeader('Content-type', 'application/json; charset=utf-8');
        $request->setHeader('Accept', 'application/json');
        $request->setBody(json_encode($postBody));
        //debugging(json_encode($request));
        $status = Sandbox::UNKNOWN_SERVER_ERROR;

        try {
            $response = $request->send();
            if ($response->getStatus() == 200) {
                $this->resultObj = json_decode($response->getBody());
                $status = Sandbox::OK;
            } else {
                debugging('Bad status from server: ' . $response->getStatus() . "\n" . $response->getBody());
            }
        } catch (HTTP_Request2_Exception $e) {
            debugging("Http error" . $e);
            $status = Sandbox::HTTP_ERROR;
        }
        $this->link = rand();  // A constant should do but this protects against inadvertent re-enty
        $answer = (object) array('error'=>$status, 'link'=>$this->link);
        return $answer;
    }

    public function getSubmissionStatus($link) {
        if ($link != $this->link) {
            throw new coding_exception("link mismatch in jobesandbox");
        }
        return (object) array(
                'error' => Sandbox::OK,
                'status'=> Sandbox::STATUS_DONE,
                'result'=> $this->resultObj->outcome
            );
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
                'output' => $this->resultObj->stdout,
                'stderr' => $this->resultObj->stderr
        );
    }
    
    
    private function putFile($file) {
        throw new coding_exception('Unimplemented');  // TODO
    }
}
?>
