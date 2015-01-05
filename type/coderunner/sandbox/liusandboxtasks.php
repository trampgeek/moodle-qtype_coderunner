<?php
namespace LiuSandbox;


/* ==============================================================
 *
 * This file contains all the LiuSandbox Language definitions.
 *
 * ==============================================================

 * Only C programs currently use this sandbox.

 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/coderunner/sandbox/languagetask.php');

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
        $this->setPath();
        
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
