<?php

/*
 * Provides a Sandbox that runs code in a VirtualBox. Primarily intended
 * for use with Matlab (which won't run in the Liu sandbox) but supports
 * Python 2 and 3 as well, for testing purposes. The assumption is that the
 * server has VirtualBox installed with a virtual machine named LinuxSandbox
 * running a version of Linux that supports the language required. There
 * must be a user 'sandbox' on that virtual machine, with password
 * 'LinuxSandbox'.
 *
 * It is assumed that there is an executable file (likely a bash script)
 * in /home/sandbox for each language, e.g. /home/sandbox/vbpython2. This
 * will usually take as its first parameter the working directory; the script
 * should leave stdout in prog.out within that directory and stderr in prog.err.
 *
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('localsandbox.php');

define('MAX_VB_READ', 4096);  // Maximum bytes to read from the VM
define('MAX_RETRIES', 5);     // Max num times to try a VM command
define('AUTHENTICATE', ' --username sandbox --password LinuxSandbox ');

// ==============================================================
//
// Language definitions.
//
// ==============================================================
class Matlab_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Matlab R2012';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName . '.m';
        if (!copy($this->sourceFileName, $this->executableFileName)) {
            throw new coding_exception("Matlab_Task: couldn't copy source file");
        }
    }

    public function readableDirs() {
        return array();  // Irrelevant for this sandbox
     }

     public function getRunCommand() {
         // For Matlab, VB is configured with a special script 'matlab' that
         // cd's into the temp directory given as $1 then
         // executes the command:
         //    matlab -nodisplay -nojvm -r $2 >prog.out 2>prog.err </dev/null
         // Resource constraints should be applied as well before executing.
         // TODO: add resource constraints
         return array(
             "/home/sandbox/vbmatlab",
             $this->workdir,
             basename($this->sourceFileName),
             '> prog.out',
             '2> prog.err',
             '</dev/null'
         );
     }


     public function filterOutput($out) {
         $lines = explode("\n", $out);
         $outlines = array();
         $headerEnded = FALSE;
         foreach ($lines as $line) {
             $line = trim($line);
             if ($headerEnded && $line != '') {
                 $outlines[] = $line;
             }
             if (strpos($line, 'For product information, visit www.mathworks.com.') !== FALSE) {
                 $headerEnded = TRUE;
             }
         }
         return implode("\n", $outlines);
     }
};

class Python2_Task extends LanguageTask {
    public function __construct($source) {
        LanguageTask::__construct($source);
    }

    public function getVersion() {
        return 'Python 2.7';
    }

    public function compile() {
        $this->executableFileName = $this->sourceFileName;
    }

    public function readableDirs() {
        return array();  // Irrelevant for this sandbox
     }

     public function getRunCommand() {
        return array(
             "/home/sandbox/vbpython2",
             $this->workdir,
             $this->sourceFileName,
             '> prog.out',
             '2> prog.err',
             '</dev/null'
         );
     }
};


// ==============================================================
//
// Now the actual sandbox.
//
// ==============================================================

class VbSandbox extends LocalSandbox {

    public function __construct($user=NULL, $pass=NULL) {
        LocalSandbox::__construct($user, $pass);
    }

    public function getLanguages() {
        return (object) array(
            'error' => Sandbox::OK,
            'languages' => array('matlab', 'python2')
        );
    }


    protected function createTask($language, $source) {
        $reqdClass = ucwords($language) . "_Task";
        return new $reqdClass($source);
    }


    // Run the current $this->task in the sandbox with the given stdin.
    // Results are all left in $this->task for later access by
    // getSubmissionDetails
    protected function runInSandbox($input) {
        $args = $this->task->getRunCommand();
        $exec = array_shift($args);
        $workdir = $this->task->workdir;
        try {
            if (!$this->sandboxRunning()) {
                $this->startSandbox();
            }

            $dirOnVb = $this->copyTempDir($workdir);
            if ($input == '') {
                array_push($args, "</dev/null");
            } else {
                $this->putVbData($input, $dirOnVb, "prog.in");
                array_push($args, "<prog.in");
            }
            $this->runInVb($exec, $args);
            $this->task->stderr = $this->getVbFileContents("/tmp/$dirOnVb/prog.err");
            if ($this->task->stderr != '') {
                $this->task->result = Sandbox::RESULT_ABNORMAL_TERMINATION;
            }
            else {
                $this->task->result = Sandbox::RESULT_SUCCESS;
            }

            $this->task->output = $this->task->filterOutput(
                    $this->getVbFileContents("/tmp/$dirOnVb/prog.out"));
            $this->task->cmpinfo = '';
            $this->task->signal = 0;
            $this->task->time = 0;
            $this->task->memory = 0;
        }
        catch (Exception $e) {
            $this->task->result = Sandbox::RESULT_INTERNAL_ERR;
            $this->task->stderr = $this->task->cmpinfo = print_r($e, true);
            $this->task->output = $this->task->stderr;
            $this->task->signal = $this->task->time = $this->task->memory = 0;
        }

        if (isset($dirOnVb)) {
            $deleteTemp = "guestcontrol LinuxSandbox execute /bin/rm " .AUTHENTICATE .
                    " --wait-stdout -- -Rf /tmp/$dirOnVb";
            $this->doVbmCommand($deleteTemp);
        }
    }


    private function runInVb($executable, $args) {
        $cmd = "guestcontrol LinuxSandbox execute $executable " .
                AUTHENTICATE . " --wait-stdout -- 2>&1 " .
                implode(' ', $args);
        return $this->doVbmCommand($cmd);
    }


    private function getVbFileContents($filepath) {
        $cmd = "guestcontrol LinuxSandbox execute /bin/cat " . AUTHENTICATE .
                " --wait-stdout --timeout 5000 -- $filepath";
        $data = $this->doVbmCommand($cmd);
        return $data;
    }


    // Create a file of the given name in the current working dir containing
    // the given data, then copy it to the nominated directory on the virtual box.
    private function putVbData($data, $dir, $filename) {
        $localPath = "{$this->workdir}/$filename";
        $f = fopen($localPath, 'w');
        fwrite($f, $data);
        fclose($f);
        $remotePath = "$dir/$filename";
        $output = $this->doVbmCommand("guestcontrol copyto $localPath $remotePath " .
                AUTHENTICATE);
        if (trim($output) != '') {
            throw new Exception('Failed to put data to VM');
        }
    }


    // True IFF sandbox is running
    private function sandboxRunning() {
        $result = $this->doVbmCommand('list runningvms');
        return strpos($result, 'LinuxSandbox') !== FALSE;
    }


    // Start the sandbox
    private function startSandbox() {
        $start = "startvm LinuxSandbox --type headless";
        $result = $this->doVbmCommand($start);
        if (strpos($result, 'successfully started') == False) {
            throw new Exception('Failed to start VirtualBox');
        }
        $poll = "guestcontrol LinuxSandbox execute /bin/echo --timeout 1000" .
                AUTHENTICATE .
                "--wait-stdout -- Sandbox is alive";
        $time = 0;
        do {
            $out = $this->doVbmCommand($poll);
            $running = strpos($out, 'Sandbox is alive') !== FALSE;
            if (!$running) {
                $time += 1;
                sleep(1);
            }
        } while (!$running && $time < 30);
        if (!$running) {
            throw new Exception('VirtualBox running but no response');
        }
    }


    // Copy the contents of the working temporary directory across to
    // VirtualBox. Return the name of the working temp dir on VirtualBox (which
    // will usually be the same as the parameter, unless there's a conflict
    // of names)
    //
    private function copyTempDir($tmpdir) {
        $tries = 0;
        $destName = basename($tmpdir);
        while ($tries < MAX_RETRIES && !$this->createVbDir($destName)) {
            $tries += 1;
            $destName = basename($tmpdir) . $tries;
        }
        if ($tries >= MAX_RETRIES) {
            throw new Exception("Failed to create tempdir on VirtualBox");
        }

        $this->copyFilesToVB($tmpdir, $destName);
        return $destName;
    }

    // Create a directory on VB. Return false if fail.
    private function createVbDir($dir) {
        $cmd = "guestcontrol LinuxSandbox createdirectory /tmp/$dir " . AUTHENTICATE;
        return trim($this->doVbmCommand($cmd)) == '';
    }


    // Create a directory on VB. Throws an exception if fails.
    private function copyFilesToVB($src, $dest) {
        $cmd = "guestcontrol LinuxSandbox copyto $src/* " .
                "/tmp/$dest/ --recursive " . AUTHENTICATE;
        $result = $this->doVbmCommand($cmd);
        if (trim($result) != '') {
            throw new Exception("CopyFilesToVB: failed to copy");
        }
    }


    // Execute the given VBoxManager command, returning stdout.
    private function doVbmCommand($cmd) {
        //debugging($cmd);
        $handle = popen('VBoxManage 2>&1 ' . $cmd, 'r');
        $result = fread($handle, MAX_VB_READ);
        pclose($handle);
        return $result;
    }

}
?>