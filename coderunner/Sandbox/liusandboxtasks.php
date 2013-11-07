<?php
namespace LiuSandbox;


/* ==============================================================
 *
 * This file contains all the LiuSandbox Language definitions.
 *
 * ==============================================================

 * Python3 has been removed from the sandbox for now because of the security
 * hole introduced by requiring openat to be an acceptable system call.
 * I'm leaving the code in place for now, in case I change my mind.
 * **TODO** remove this is due course if not needed.

 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class Python3_Task extends \LanguageTask {
    public function __construct($sandbox, $source) {
        \LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'Python 3.3';
    }

    public function compile() {
        exec("python3 -m py_compile {$this->sourceFileName} 2>compile.out", $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $this->sourceFileName;
        }
        else {
            $this->cmpinfo = file_get_contents('compile.out');
        }
    }

    public static function readableDirs() {
        return array(
            '/lib/',
            '/lib64/',
            '/etc/',
            '/usr/local/lib',
            '/usr/lib',
            '/usr/bin',
            '/proc/meminfo',
            '/usr/include',
            '/dev/urandom',
            '/usr/local',
            '/usr/pyvenv.cfg'
        );
     }

     public function getRunCommand() {
         return array(
             '/usr/bin/python3', '-BESs', basename($this->executableFileName)
         );
     }

};

// =============================================================

class Python2_Task extends \LanguageTask {
    public function __construct($sandbox, $source) {
        \LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'Python 2.6';
    }

    public function compile() {
        exec("python2 -m py_compile {$this->sourceFileName} 2>compile.out", $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $this->sourceFileName;
        }
        else {
            $this->cmpinfo = file_get_contents('compile.out');
        }
    }

    public static function readableDirs() {
        return array(
            '/lib',
            '/etc/',
            '/usr/local/lib',
            '/usr/lib',
            '/usr/bin',
            '/proc/meminfo',
            '/usr/include'
        );
     }

     public function getRunCommand() {
         return array(
             '/usr/bin/python2', '-BESs', basename($this->executableFileName)
         );
     }
};


// =============================================================

class C_Task extends \LanguageTask {

    public function __construct($sandbox, $source) {
        \LanguageTask::__construct($sandbox, $source);
    }

    public function getVersion() {
        return 'gcc-4.6.3';
    }

    public function compile() {
        $src = basename($this->sourceFileName);
        $errorFileName = "$src.err";
        $execFileName = "$src.exe";
        $cmd = "gcc -Wall -Werror -std=c99 -static -x c -o $execFileName $src -lm 2>$errorFileName";
        // To support C++ instead use something like ...
        // $cmd = "g++ -Wall -Werror -static -x c++ -o $execFileName $src -lm 2>$errorFileName";
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            $this->cmpinfo = '';
            $this->executableFileName = $execFileName;
        }
        else {
            $this->cmpinfo = file_get_contents($errorFileName);
        }
    }


    public function getRunCommand() {
        return array($this->executableFileName);
    }

};

?>
