<?php


/* ==============================================================
 *
 * This file contains all the RunguardSandbox Language definitions.
 *
 * ==============================================================
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\local\languagetasks;

use qtype_coderunner\local\languagetasks\language_task;

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');
require_once($CFG->dirroot . '/question/type/coderunner/sandbox/languagetask.php');



class Matlab_Task extends language_task {
    private $sourcefilename = null; // The name of the source file
    public function compile($workdir, $sourcefilename) {
        chdir($workdir);
        if (!copy($sourcefilename, $sourcefilename . '.m')) {
            throw new coderunner_exception("Matlab_Task: couldn't copy source file");
        }
        $this->executablefilename = $sourcefilename;  // Matlab wants it without the .m extension
        return '';  // Compiler errors can't occur (I hope)
    }

    public function get_run_command() {
         return array(
             '/usr/local/bin/matlab_exec_cli',
             '-nojvm',
             '-r',
             basename($this->executablefilename)
         );
     }


     // Matlab throws in backspaces (grrr). There's also an extra BEL char
     // at the end of any abort error message (presumably introduced at some
     // point due to the EOF on stdin, which shuts down matlab).
     public static function filter_stderr($stderr) {
         $out = '';
         for ($i = 0; $i < strlen($stderr); $i++) {
             $c = $stderr[$i];
             if ($c === "\x07") {
                 // pass
             } else if ($c === "\x08" && strlen($out) > 0) {
                 $out = substr($out, 0, -1);
             } else {
                 $out .= $c;
             }
         }
         return $out;
     }

     
     public static function filter_output($out) {
         $lines = explode("\n", $out);
         $outlines = array();
         $headerended = false;

         foreach ($lines as $line) {
             $line = rtrim($line);
             if ($headerended) {
                 $outlines[] = $line;
             }
             if (strpos($line, 'For product information, visit www.mathworks.com.') !== false) {
                 $headerended = true;
             }
         }

         // Remove blank lines at the start and end
         while (count($outlines) > 0 && strlen($outlines[0]) == 0) {
             array_shift($outlines);
         }
         while(count($outlines) > 0 && strlen(end($outlines)) == 0) {
             array_pop($outlines);
         }

         return implode("\n", $outlines) . "\n";
     }
};


class Octave_Task extends language_task {
    private $sourcefilename = null; // The name of the original source file (without .m)
    public function compile($workdir, $sourcefilename) {
        $this->sourcefilename = $sourcefilename; // Save for use in get_run_command
        chdir($workdir);
        $this->executablefilename = $sourcefilename . '.m';
        if (!copy($sourcefilename, $this->executablefilename)) {
            throw new coderunner_exception("Octave_Task: couldn't copy source file");
        }
        return '';
    }

    public function get_run_command() {
         return array(
             '/usr/bin/octave',
             '--norc',
             '--no-window-system',
             '--silent',
             basename($this->sourcefilename)
         );
     }
     
     
     // Remove return chars and delete the extraneous error: lines
     public static function filter_stderr($stderr) {
         $out1 = str_replace("\r", '', $stderr);
         $out2 = preg_replace("/\nerror:.*\n/s", "\n", $out1);
         $out3 = preg_replace("|file /tmp/coderunner_.*|", 'source file', $out2);
         return $out3;
     }
}


class Python2_Task extends language_task {

    public function compile($workdir, $sourcefilename) {
        $this->executablefilename = $sourcefilename;
        return '';
    }


    // Return the command to pass to localrunner as a list of arguments,
    // starting with the program to run followed by a list of its arguments.
    public function get_run_command() {
        return array(
             '/usr/bin/python2',
             '-BESs',
             $this->executablefilename
         );
     }
};

class Python3_Task extends language_task {

    public function compile($workdir, $sourcefilename) {
        $returnvar = 0;
        exec("python3 -m py_compile $sourcefilename 2>compile.out", $output, $returnvar);
        if ($returnvar === 0) {
            $cmpinfo = '';
            $this->executablefilename = $sourcefilename;
        }
        else {
            $cmpinfo = file_get_contents('compile.out');
        }
        return $cmpinfo;
    }


    // Return the command to pass to localrunner as a list of arguments,
    // starting with the program to run followed by a list of its arguments.
    public function get_run_command() {
        return array(
             '/usr/bin/python3',
             '-BE',
             $this->executablefilename
         );
     }
};

class Java_Task extends language_task {
    private $mainclassname = null;  // The name of the main class

    public function compile($workdir, $sourcefilename) {
        $prog = file_get_contents($sourcefilename);
        if (($this->mainclassname = $this->get_main_class($prog)) === false) {
            return 'Error: no main class found, or multiple main classes. [Did you write a public class when asked for a non-public one?]';
        }
        else {
            $returnvar = 0;
            $newsourcefilename = $this->mainclassname . '.java';
            exec("mv $sourcefilename $newsourcefilename", $output, $returnvar);
            if ($returnvar !== 0) {
                throw new coderunner_exception("Java compile: couldn't rename source file");
            }

            exec("/usr/bin/javac $newsourcefilename 2>compile.out", $output, $returnvar);
            return file_get_contents('compile.out');
        }
    }


    public function get_run_command() {
        return array(
             '/usr/bin/java',
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m",
             $this->mainclassname
         );
    }


     // Return the name of the main class in the given prog, or false if no
     // such class found. Uses a regular expression to find a public class with
     // a public static void main method.
     // Not totally safe as it doesn't parse the file, e.g. would be fooled
     // by a commented-out main class with a different name.
     private function get_main_class($prog) {
         $pattern = '/(^|\W)public\s+class\s+(\w+)\s*\{.*?public\s+static\s+void\s+main\s*\(\s*String/ms';
         if (preg_match_all($pattern, $prog, $matches) !== 1) {
             return false;
         }
         else {
             return $matches[2][0];
         }
     }
};


class C_Task extends language_task {

    public function compile($workdir, $sourcefilename) {
        $src = basename($sourcefilename);
        $errorfilename = "$src.err";
        $this->executablefilename = "$src.exe";
        $cmd = "gcc -Wall -Werror -std=c99 -x c -o {$this->executablefilename} $src -lm 2>$errorfilename";
        // To support C++ instead use something like ...
        // $cmd = "g++ -Wall -Werror -x ++ -o $execFileName $src -lm 2>$errorFileName";
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            return '';
        } else {
            return file_get_contents($errorfilename);
        }
    }


    public function get_run_command() {
        return array(
             "./" . $this->executablefilename
         );
    }
};


