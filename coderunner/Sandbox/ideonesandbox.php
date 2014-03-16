<?php
/* A sandbox that uses the remote ideone.com compute server to run
 * student submissions. This is completely safe but gives a poor turn-around,
 * which can be up to a minute.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('sandboxbase.php');


class IdeoneSandbox extends Sandbox {

    var $client = NULL;  // The soap client referencing ideone.com
    var $langMap = NULL;   // Languages supported by this sandbox: map from name to id

    public function __construct($user=NULL, $pass=NULL) {
        if ($user == NULL) {
            $user = get_config('qtype_coderunner', 'ideone_user');
        }

        if ($pass == NULL) {
            $pass = get_config('qtype_coderunner', 'ideone_password');
        }

        Sandbox::__construct($user, $pass);

        // A map from Ideone language names (regular expressions) to their
        // local short name, where appropriate

        $aliases = array('C99 strict.*'             =>'C',
                     '.*python *2\.[789]\.[0-9].*'  => 'python2',
                     'Python 3.*python-3\.*'        => 'python3',
                     'Java.*sun-jdk.*'              => 'Java');

        $this->client = $client = new SoapClient("http://ideone.com/api/1/service.wsdl");
        $this->langMap = array();  // Construct a map from language name to id

        $response = $this->client->getLanguages($user, $pass);
        $error = $response['error'];
        if ($error !== 'OK') {
            throw new coding_exception("IdeoneSandbox::getLanguages: error ($error)");
        }

        foreach ($response['languages'] as $id=>$lang) {
            $this->langMap[$lang] = $id;
            foreach ($aliases as $pattern=>$alias) {
                if (preg_match('/' . $pattern . '/', $lang)) {
                    $this->langMap[$alias] = $id;
                }
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
    // TODO: come up with a better way of handling non-null $files and
    // $params.
    public function createSubmission($sourceCode, $language, $input,
            $run=TRUE, $private=TRUE, $files=NULL, $params = NULL)
    {
        // Check language is valid and the user isn't attempting to set
        // files or execution parameters (since Ideone does not have such options).
        assert(in_array($language, $this->getLanguages()->languages));
        if ($files !== NULL && count($files) !== 0) {
            throw new moodle_exception("Ideone sandbox doesn't accept files");
        }
        if($params !== NULL) {
            throw new moodle_exception(
   "ideone sandbox doesn't accept parameters like cpu time or memory limit");
        }
        $langId = $this->langMap[$language];
        $response = $this->client->createSubmission($this->user, $this->pass,
                $sourceCode, $langId, $input, $run, $private);
        $error = $response['error'];
        if ($error !== 'OK') {
            throw new moodle_exception("IdeoneSandbox::getSubmissionStatus: error ($error)");
        }
        else {
            return (object) array('error'=>Sandbox::OK, 'link'=> $response['link']);
        }
    }

    public function getSubmissionStatus($link) {
        $response = $this->client->getSubmissionStatus($this->user, $this->pass, $link);
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
        $response = $this->client->getSubmissionDetails($this->user, $this->pass,
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
