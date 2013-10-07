<?php
/* A sandbox that uses the remote ideone.com computer server to run
 * student submissions. This is completely safe but gives a poor turn-around,
 * which can be up to a minute.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('sandboxbase.php');

define('USER', 'coderunner');
define('PASS', 'moodlequizzes');

class IdeoneSandbox extends Sandbox {

    var $client = NULL;  // The soap client referencing ideone.com
    var $langMap = NULL;   // Languages supported by this sandbox: map from name to id

    public function __construct($user=NULL, $pass=NULL) {
        Sandbox::__construct($user, $pass);

        // A map from Ideone language names to their local short name, where
        // appropriate
        $aliases = array('C99 strict (gcc-4.7.2)'=>'C',
                     'Python (python 2.7.3)'=>'python2',
                     'Python 3 (python-3.2.3)'=>'python3',
                     'Java (sun-jdk-1.7.0_10)'=>'Java');

        $this->client = $client = new SoapClient("http://ideone.com/api/1/service.wsdl");
        $response = $this->client->getLanguages(USER, PASS);
        $error = $response['error'];
        if ($error !== 'OK') {
            throw new coding_exception("IdeoneSandbox::getSubmissionStatus: error ($error)");
        }
        $this->langMap = array();  // Construct a map from language name to id
        $response = $this->client->getLanguages(USER, PASS);
        $error = $response['error'];
        if ($error !== 'OK') {
            throw new coding_exception("IdeoneSandbox::getLanguages: error ($error)");
        }

        foreach ($response['languages'] as $id=>$lang) {
            $this->langMap[$lang] = $id;
            if (array_key_exists($lang, $aliases)) {
                $this->langMap[$aliases[$lang]] = $id;
            }
        }

    }



    // Returns an object containing an error field and a languages field,
    // where the latter is a list of strings of languages handled by this sandbox.
    // This latter consists of all the languages returned by a query to Ideone plus
    // the local simplified aliases, like python2, python3, C.
    public function getLanguages() {
        $resultObj = (object) array('error'=>Sandbox::OK,
            'languages'=>array_keys($this->langMap));
        return $resultObj;
    }


    // Create a submission (a 'paste' in ideone terminology).
    // Return an object with an error and a link field, the latter being
    // the handle for the submission, for use in the following two calls.
    public function createSubmission($sourceCode, $language, $input,
            $run=TRUE, $private=TRUE)
    {
        assert(in_array($language, $this->getLanguages()->languages));
        $langId = $this->langMap[$language];
        $response = $this->client->createSubmission(USER, PASS,
                $sourceCode, $langId, $input, $run, $private);
        $error = $response['error'];
        if ($error !== 'OK') {
            throw new coding_exception("IdeoneSandbox::getSubmissionStatus: error ($error)");
        }
        else {
            return (object) array('error'=>Sandbox::OK, 'link'=> $response['link']);
        }
    }

    public function getSubmissionStatus($link) {
        $response = $this->client->getSubmissionStatus(USER, PASS, $link);
        $error = $response['error'];
        if ($error !== "OK") {
                throw new coding_exception("IdeoneSandbox::getSubmissionStatus: error ($error)");
        }
        else {
            return (object) array(
                'error' =>Sandbox::OK,
                'status'=>$response['status'],
                'result'=>$response['result']
            );
        }
    }


    // Should only be called if the status is STATUS_DONE. Returns an object
    // with fields error, result, time, memory, signal, cmpinfo, stderr, output.
    public function getSubmissionDetails($link, $withSource=FALSE,
            $withInput=FALSE, $withOutput=TRUE, $withStderr=TRUE,
            $withCmpinfo=TRUE)
    {
        $response = $this->client->getSubmissionDetails(USER, PASS,
                $link, $withSource, $withInput, $withOutput,
                $withStderr, $withCmpinfo);

        $error = $response['error'];
        if ($error !== 'OK') {
            throw new coding_exception("IdeoneSandbox::getSubmissionStatus: error ($error)");
        }
        else {
            return (object) array(
                'error'  => Sandbox::OK,
                'time'   => $response['time'],
                'memory' => $response['memory'],
                'signal' => $response['signal'],
                'cmpinfo'=> $response['cmpinfo'],
                'output' => $response['output'],
                'stderr' => $response['stderr']

            );
        }
    }
}
?>
