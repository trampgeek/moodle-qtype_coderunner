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


class qtype_coderunner_ideonesandbox extends qtype_coderunner_sandbox {

    var $client = null;  // The soap client referencing ideone.com
    var $langMap = null;   // Languages supported by this sandbox: map from name to id

    public function __construct($user=null, $pass=null) {
        if ($user == null) {
            $user = get_config('qtype_coderunner', 'ideone_user');
        }

        if ($pass == null) {
            $pass = get_config('qtype_coderunner', 'ideone_password');
        }

        qtype_coderunner_sandbox::__construct($user, $pass);

        // A map from Ideone language names (regular expressions) to their
        // local short name, where appropriate

        $aliases = array('C99 strict.*'             =>'c',
                     '.*python *2\.[789]\.[0-9].*'  => 'python2',
                     'Python 3.*python-3\.*'        => 'python3',
                     'Java.*sun-jdk.*'              => 'java');

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


    public function get_languages() {
        return $this->langMap === null ? null : array_keys($this->langMap);
    }
    
    
    public function execute($sourcecode, $language, $input, $files=null, $params=null) {
          if ($error != qtype_coderunner_sandbox::OK) {
            return (object) array('error' => $error);
        } else {
            $count = 0;
            while ($state->error === qtype_coderunner_sandbox::OK &&
                   $state->status !== qtype_coderunner_sandbox::STATUS_DONE &&
                   $count < qtype_coderunner_sandbox::MAX_NUM_POLLS) {
                $count += 1;
                sleep(qtype_coderunner_sandbox::POLL_INTERVAL);
                $state = $this->get_submission_status($result->link);
            }

            if ($count >= qtype_coderunner_sandbox::MAX_NUM_POLLS) {
                throw new coderunner_exception("Timed out waiting for sandbox");
            }

            if ($state->error !== qtype_coderunner_sandbox::OK ||
                    $state->status !== qtype_coderunner_sandbox::STATUS_DONE) {
                throw new coding_exception("Error response or bad status from sandbox");
            }

            $details = $this->get_submission_details($result->link);

            return (object) array(
                'error'   => qtype_coderunner_sandbox::OK,
                'result'  => $state->result,
                'output'  => $details->output,
                'stderr'  => $details->stderr,
                'signal'  => $details->signal,
                'cmpinfo' => $details->cmpinfo);
        }
    }


    // Create a submission (a 'paste' in ideone terminology).
    // Return an object with an error and a link field, the latter being
    // the handle for the submission, for use in the following two calls.
    // TODO: come up with a better way of handling non-null $files and
    // $params.
    public function create_submission($sourceCode, $language, $input,
            $run=true, $private=true, $files=null, $params = null)
    {
        // Check language is valid and the user isn't attempting to set
        // files or execution parameters (since Ideone does not have such options).
        assert(in_array($language, $this->get_languages()));
        if ($files !== null && count($files) !== 0) {
            throw new moodle_exception("Ideone sandbox doesn't accept files");
        }
        if($params !== null) {
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
            return (object) array('error'=>qtype_coderunner_sandbox::OK, 'link'=> $response['link']);
        }
    }

    public function get_submission_status($link) {
        $response = $this->client->getSubmissionStatus($this->user, $this->pass, $link);
        $error = $response['error'];
        if ($error !== "OK") {
                throw new coding_exception("IdeoneSandbox::getSubmissionStatus: error ($error)");
        }
        else {
            return (object) array(
                'error' =>qtype_coderunner_sandbox::OK,
                'status'=>$response['status'],
                'result'=>$response['result']
            );
        }
    }


    // Should only be called if the status is STATUS_DONE. Returns an object
    // with fields error, result, time, memory, signal, cmpinfo, stderr, output.
    public function get_submission_details($link, $withSource=false,
            $withInput=false, $withOutput=true, $withStderr=true,
            $withCmpinfo=true)
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
                'error'  => qtype_coderunner_sandbox::OK,
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
