<?php
/** A language_task is an object that specifies how a particular language
 *  should be compiled and run within the runguard_sandbox. There is one
 *  for each language supported by the runguard_sandbox.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\languagetasks;

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');

abstract class language_task {
    protected $executablefilename = null;  // Name of the file to be executed

    /** Compile the given source file in the given working directory.
     * @return string Compiler error messages (empty or null if no errors occur)
     */
    protected abstract function compile($workdir, $sourcefilename);


    /** @return string array the Linux command to use to run the current job.
     *  It's an array of string arguments.
     */
    public abstract function get_run_command();


    // Override the following function if the output from executing a program
    // in this language needs post-filtering to remove stuff like
    // header output.
    public static function filter_output($out) {
        return $out;
    }


    // Override the following function if the stderr from executing a program
    // in this language needs post-filtering to remove stuff like
    // backspaces and bells.
    public static function filter_stderr($stderr) {
        return $stderr;
    }

}