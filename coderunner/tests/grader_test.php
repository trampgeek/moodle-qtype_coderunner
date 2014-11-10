<?php
// Tests of various graders other than the default 'EqualityGrader', which
// is extensively tested by the other tests.

/**
 * Unit tests for the coderunner question definition class.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');
require_once($CFG->dirroot . '/local/Twig/Autoloader.php');

/**
 * Unit tests for the RegexGrader class.
 */
class qtype_coderunner_grader_test extends qtype_coderunner_testcase {

    public function test_copyStdin() {
        // Check a question that reads stdin and writes to stdout
        $q = $this->make_question('copyStdin');
        $q->grader = 'RegexGrader';
        $q->testcases = array(
            (object) array('testcode' => 'copyStdin()',
                          'stdin'       => "Line1\n  Line2  \n /123Line 3456/ \n",
                          'extra'       => '',
                          'expected'    => "^ *Line1 *\n +Line2 +. /[1-3]{3}Line *3[4-6]{3}/ *\n$",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0));
        $code = <<<EOCODE
def copyStdin():
  try:
    while True:
        line = input()
        print(line)
  except EOFError:
    pass
EOCODE;
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $testOutcome = unserialize($cache['_testoutcome']); // For debugging test
        //var_dump($testOutcome);
        $this->assertEquals(1, $mark);
        $this->assertEquals(question_state::$gradedright, $grade);
        $this->assertTrue(isset($cache['_testoutcome']));

     }
}

?>
