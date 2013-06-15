<?php
/**
 * Unit tests for coderunner's VirtualBox sandbox class.
 * This is just a copy of the LiuSandbox test class, with as few
 * changes as possible. [Yeah, Horrible, Horrible]
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/vbsandbox.php');

class qtype_coderunner_vbsandbox_test extends basic_testcase {
    public function setUp() {
        $handle = popen('whoami', 'r');
        $result = fread($handle, 200);
        pclose($handle);
        if (strpos($result, 'www-data') === FALSE &&
            strpos($result, 'apache') === FALSE) {
            throw new Exception('vbsandbox tests must be run by the web-server user.');
        }
    }

    public function tearDown() {
    }

    public function test_testfunction() {
        $sandbox = new VbSandbox();
        $tr = $sandbox->testFunction();
        $this->assertEquals($tr->error, Sandbox::OK);
        $this->assertEquals($tr->pi, 3.14);
        $this->assertEquals($tr->answerToLifeAndEverything, 42);
        $this->assertTrue($tr->oOok);
        $langs = $sandbox->getLanguages();
        $langs = $langs->languages;
        $this->assertTrue(in_array('python2', $langs, TRUE));
        $this->assertTrue(in_array('matlab', $langs, TRUE));
    }


    // Test the vbsandbox class at the PHP level with a good Python2 program
    public function test_vbsandbox_ok_python2() {
        $sandbox = new VbSandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz'";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->output, "Hello Sandbox\nPython rulz\n");
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the vbsandbox class at the PHP level with a bad-syntax python2 prog
    // Syntax checking is not currently implemented by the VbSandbox, so the
    // program 'runs' but terminates abnormally with a syntax error.
    public function test_vbsandbox_syntax_error_python2() {
        $sandbox = new VbSandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz' + ";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_ABNORMAL_TERMINATION);
        $this->assertEquals($result->signal, 0);
        $this->assertTrue(strpos($result->stderr, 'SyntaxError') !== FALSE);
        $sandbox->close();
    }


    // Test the vbsandbox with a timeout error. On VbSandbox this gives
    // signal 9.
    public function test_vbsandbox_timeout() {
        $sandbox = new VbSandbox();
        $code = "while True: pass";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_TIME_LIMIT);
        $this->assertEquals($result->output, '');
        $this->assertTrue($result->signal == 9);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the vbsandbox with a memory limit error
    public function test_vbsandbox_memlimit() {
        $sandbox = new VbSandbox();
        $code = "data = list(range(1,10000000000))";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_ABNORMAL_TERMINATION);
        $this->assertEquals($result->output, '');
        $this->assertTrue(strpos($result->stderr, 'MemoryError') !== FALSE);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }
}

?>
