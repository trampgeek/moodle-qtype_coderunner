<?php
/**
 * Unit tests for coderunner's sandbox class.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/liusandbox.php');

class qtype_coderunner_sandbox_test extends basic_testcase {
    public function setUp() {
    }

    public function tearDown() {
    }

    public function test_testfunction() {
        $sandbox = new LiuSandbox();
        $tr = $sandbox->testFunction();
        $this->assertEquals($tr->error, Sandbox::OK);
        $this->assertEquals($tr->pi, 3.14);
        $this->assertEquals($tr->answerToLifeAndEverything, 42);
        $this->assertTrue($tr->oOok);
        $langs = $sandbox->getLanguages();
        $langs = $langs->languages;
        $this->assertTrue(in_array('python2', $langs, TRUE));
        $this->assertTrue(in_array('python3', $langs, TRUE));
        $this->assertTrue(in_array('C', $langs, TRUE));
    }

    public function test_liu_sandbox_raw() {
        // Test the Python3 interface to the Liu sandbox directly.
        global $CFG;
        $srcfname = tempnam("/tmp", "coderunnersrc_");
        $handle = fopen($srcfname, "w");
        fwrite($handle, "print('Hello Sandbox')\nprint('Python rulz')");
        fclose($handle);

        $run = array(
            'args' => array('/usr/bin/python3', '-BESsu'),
            'filename' => $srcfname,
            'input'  => '',
            'quota'  => array(
                'wallclock' => 30000,    // 30 secs
                'cpu'       => 5000,     // 5 secs
                'memory'    => 64000000, // 64MB
                'disk'      => 1048576   // 1 MB
            )
        );

        $tmpfname = tempnam("/tmp", "coderunner_");
        $handle = fopen($tmpfname, "w");
        fwrite($handle, json_encode($run));
        fclose($handle);
        $output = array();
        $cmd = $CFG->dirroot . "/question/type/coderunner/Sandbox/liusandbox.py $tmpfname";
        exec($cmd, $output, $returnVar);
        $outputJson = $output[0];
        $result = json_decode($outputJson);
        $this->assertEquals($result->returnCode, 'OK');
        $this->assertEquals($result->output, "Hello Sandbox\nPython rulz\n");
        $this->assertEquals($result->stderr, '');
        unlink($srcfname);
        unlink($tmpfname);
    }


    // Test the liu sandbox class at the PHP level with a good Python3 program
    public function test_liu_sandbox_ok_python3() {
        $sandbox = new LiuSandbox();
        $code = "print('Hello Sandbox')\nprint('Python rulz')";
        $result = $sandbox->execute($code, 'python3', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->output, "Hello Sandbox\nPython rulz\n");
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox class at the PHP level with a bad-syntax python3 prog
    public function test_liu_sandbox_syntax_error_python3() {
        $sandbox = new LiuSandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz' ";
        $result = $sandbox->execute($code, 'python3', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_ABNORMAL_TERMINATION);
        $this->assertTrue(strpos($result->stderr, 'SyntaxError') !== FALSE);
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox class at the PHP level with a good Python2 program
    public function test_liu_sandbox_ok_python2() {
        $sandbox = new LiuSandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz'";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->output, "Hello Sandbox\nPython rulz\n");
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox class at the PHP level with a bad-syntax python2 prog
    public function test_liu_sandbox_syntax_error_python2() {
        $sandbox = new LiuSandbox();
        $code = "print 'Hello Sandbox'\nprint: 'Python rulz' ";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_ABNORMAL_TERMINATION);
        $this->assertTrue(strpos($result->stderr, 'SyntaxError') !== FALSE);
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox with a timeout error
    public function test_liu_sandbox_timeout() {
        $sandbox = new LiuSandbox();
        $code = "while True: pass";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_TIME_LIMIT);
        $this->assertEquals($result->output, '');
        $this->assertEquals($result->stderr, '');
        $this->assertTrue($result->signal == 18 || $result->signal == 10);  // Varies?
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox with a memory limit error
    public function test_liu_sandbox_memlimit() {
        $sandbox = new LiuSandbox();
        $code = "data = []\nwhile True: data.append(1)";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_MEMORY_LIMIT);
        $this->assertEquals($result->output, '');
        $this->assertEquals($result->stderr, '');
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox with a syntactically bad C program
    public function test_liu_sandbox_bad_C() {
        $sandbox = new LiuSandbox();
        $code = "#include <stdio.h>\nint main(): {\n    printf(\"Hello sandbox\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'C', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_COMPILATION_ERROR);
        $this->assertTrue(strpos($result->cmpinfo, 'error:') !== FALSE);
        $sandbox->close();
    }

    // Test the liu sandbox with a valid C program
    public function test_liu_sandbox_ok_C() {
        $sandbox = new LiuSandbox();
        $code = "#include <stdio.h>\nint main() {\n    printf(\"Hello sandbox\\n\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'C', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->output, "Hello sandbox\n");
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox will allow opening, writing and reading in the current dir
    public function test_liu_sandbox_fileio_in_cwd() {
        $sandbox = new LiuSandbox();
        $code =
"import os
f = open('junk', 'w')
f.write('stuff')
f.close()
f = open('junk')
print(f.read())
f.close()
";
        $result = $sandbox->execute($code, 'python3', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->output, "stuff\n");
        $this->assertEquals($result->signal, 0);
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }


    // Test the liu sandbox will not allow opening, writing and reading in /tmp
    public function test_liu_sandbox_fileio_bad() {
        $sandbox = new LiuSandbox();
        $code =
"import os
f = open('/tmp/junk', 'w')
f.write('stuff')
f.close()
f = open('/tmp/junk')
print(f.read())
f.close()
";
        $result = $sandbox->execute($code, 'python3', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_ILLEGAL_SYSCALL);
        $sandbox->close();
    }

}

?>
