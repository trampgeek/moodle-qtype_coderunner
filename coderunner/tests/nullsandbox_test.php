<?php
/**
 * Unit tests for coderunner's NullSandbox sandbox class.
 * @group qtype_coderunner
 * This is just a copy of the LiuSandbox test class, with as few
 * changes as possible. [Yeah, Horrible, Horrible]
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/nullsandbox.php');

class qtype_coderunner_nullsandbox_test extends basic_testcase {
    public function setUp() {
    }

    public function tearDown() {
    }

    public function test_testfunction() {
        $sandbox = new nullsandbox();
        $tr = $sandbox->testFunction();
        $this->assertEquals($tr->error, Sandbox::OK);
        $this->assertEquals($tr->pi, 3.14);
        $this->assertEquals($tr->answerToLifeAndEverything, 42);
        $this->assertTrue($tr->oOok);
        $langs = $sandbox->getLanguages();
        $langs = $langs->languages;
        $this->assertTrue(in_array('python2', $langs, TRUE));
        $this->assertTrue(in_array('matlab', $langs, TRUE));
        $this->assertTrue(in_array('Java', $langs, TRUE));
    }


    // Test the nullsandbox class at the PHP level with a good Python2 program
    public function test_nullsandbox_ok_python2() {
        $sandbox = new nullsandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz'";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->output, "Hello Sandbox\nPython rulz\n");
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the nullsandbox class at the PHP level with a bad-syntax python2 prog
    // Syntax checking is not currently implemented by the nullsandbox, so the
    // program 'runs' but terminates abnormally with a syntax error.
    public function test_nullsandbox_syntax_error_python2() {
        $sandbox = new nullsandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz' + ";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_ABNORMAL_TERMINATION);
        $this->assertEquals($result->signal, 0);
        $this->assertTrue(strpos($result->stderr, 'SyntaxError') !== FALSE);
        $sandbox->close();
    }


    // Test the nullsandbox with a timeout error. On nullsandbox this gives
    // signal 9.
    public function test_nullsandbox_timeout() {
        $sandbox = new nullsandbox();
        $code = "while True: pass";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_TIME_LIMIT);
        $this->assertEquals($result->output, '');
        $this->assertTrue($result->signal == 9);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the nullsandbox with a memory limit error
    public function test_nullsandbox_memlimit() {
        $sandbox = new nullsandbox();
        $code = "data = list(range(1,100000000000))";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_ABNORMAL_TERMINATION);
        $this->assertEquals($result->output, '');
        $this->assertTrue(strpos($result->stderr, 'MemoryError') !== FALSE ||
                strpos($result->stderr, 'OverflowError') !== FALSE);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }


    // Test the nullsandbox with excessive output, using Python3
    // Actually generates a time limit error, because of the limitations
    // of runguard.
    public function test_nullsandbox_excessiveoutput() {
        $sandbox = new nullsandbox();
        $code = "while 1: print('blah blah blah blah blah blah blah')";
        $result = $sandbox->execute($code, 'python3', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_TIME_LIMIT);
        $this->assertTrue($result->signal == 9);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }
}

?>
