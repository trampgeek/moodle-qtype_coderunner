<?php
/**
 * Unit tests for coderunner's liusandbox class.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/liusandbox.php');

class qtype_coderunner_liusandbox_test extends basic_testcase {
    public function setUp() {
    }

    public function tearDown() {
    }

    public function test_testfunction() {
        $sandbox = new LiuSandbox();
        $tr = $sandbox->testFunction();
        $this->assertEquals(Sandbox::OK, $tr->error);
        $this->assertEquals(3.14, $tr->pi);
        $this->assertEquals(42, $tr->answerToLifeAndEverything);
        $this->assertTrue($tr->oOok);
        $langs = $sandbox->getLanguages();
        $langs = $langs->languages;
        $this->assertTrue(in_array('python2', $langs, TRUE));
        $this->assertTrue(in_array('C', $langs, TRUE));
    }

    public function test_liu_sandbox_raw() {
        // Test the Python2 interface to the Liu sandbox directly.
        global $CFG;
        $dirname = tempnam("/tmp", "coderunnertest_");
        unlink($dirname);
        mkdir($dirname);
        chdir($dirname);
        $handle = fopen('sourceFile', "w");
        fwrite($handle, "print 'Hello Sandbox'\nprint 'Python rulz'");
        fclose($handle);

        $run = array(
            'cmd' => array('/usr/bin/python2', '-BESsu', 'sourceFile'),
            'input'  => '',
            'quota'  => array(
                'wallclock' => 30000,    // 30 secs
                'cpu'       => 5000,     // 5 secs
                'memory'    => 64000000, // 64MB
                'disk'      => 1048576   // 1 MB
            ),
            'workdir'      => $dirname,
            'readableDirs' => Python3_Task::readableDirs()
        );

        $handle = fopen('runspec.json', "w");
        fwrite($handle, json_encode($run));
        fclose($handle);
        $output = array();
        $cmd = $CFG->dirroot . "/question/type/coderunner/Sandbox/liusandbox.py runspec.json";
        exec($cmd, $output, $returnVar);
        $outputJson = $output[0];
        $result = json_decode($outputJson);
        $this->assertEquals('OK', $result->returnCode);
        $this->assertEquals("Hello Sandbox\nPython rulz\n", $result->output);
        $this->assertEquals('', $result->stderr);
        chdir('..');
        $this->delTree($dirname);
    }

/* Python3 tests removed as it's no longer in this sandbox.
 * **TODO** Delete if not reinstated.

    // Test the liu sandbox class at the PHP level with a good Python3 program
    public function test_liu_sandbox_ok_python3() {
        $sandbox = new LiuSandbox();
        $code = "print('Hello Sandbox')\nprint('Python rulz')";
        $result = $sandbox->execute($code, 'python3', NULL);
        $this->assertEquals(Sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("Hello Sandbox\nPython rulz\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the liu sandbox class at the PHP level with a bad-syntax python3 prog
    public function test_liu_sandbox_syntax_error_python3() {
        $sandbox = new LiuSandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz' ";
        $result = $sandbox->execute($code, 'python3', NULL);
        $this->assertEquals(Sandbox::RESULT_COMPILATION_ERROR, $result->result);
        $this->assertEquals(0, $result->signal);
        $sandbox->close();
    }
*/
    // Test the liu sandbox class at the PHP level with a good Python2 program
    public function test_liu_sandbox_ok_python2() {
        $sandbox = new LiuSandbox();
        $code = "print 'Hello Sandbox'\nprint 'Python rulz'";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals(Sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("Hello Sandbox\nPython rulz\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the liu sandbox class at the PHP level with a bad-syntax python2 prog
    public function test_liu_sandbox_syntax_error_python2() {
        $sandbox = new LiuSandbox();
        $code = "print 'Hello Sandbox'\nprint: 'Python rulz' ";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals(Sandbox::RESULT_COMPILATION_ERROR, $result->result);
        $this->assertTrue(strpos($result->cmpinfo, 'SyntaxError') !== FALSE);
        $this->assertEquals(0, $result->signal);
        $sandbox->close();
    }

    // Test the liu sandbox with a timeout error
    public function test_liu_sandbox_timeout() {
        $sandbox = new LiuSandbox();
        $code = "while True: pass";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals(Sandbox::RESULT_TIME_LIMIT, $result->result);
        $this->assertEquals('', $result->output);
        $this->assertEquals('', $result->stderr);
        $this->assertTrue($result->signal == 18 || $result->signal == 10);  // Varies?
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the liu sandbox with a memory limit error
    public function test_liu_sandbox_memlimit() {
        $sandbox = new LiuSandbox();
        $code = "data = []\nwhile True: data.append(1)";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals(Sandbox::RESULT_MEMORY_LIMIT, $result->result);
        $this->assertEquals('', $result->output);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the liu sandbox with a syntactically bad C program
    public function test_liu_sandbox_bad_C() {
        $sandbox = new LiuSandbox();
        $code = "#include <stdio.h>\nint main(): {\n    printf(\"Hello sandbox\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'C', NULL);
        $this->assertEquals(Sandbox::RESULT_COMPILATION_ERROR, $result->result);
        $this->assertTrue(strpos($result->cmpinfo, 'error:') !== FALSE);
        $sandbox->close();
    }

    // Test the liu sandbox with a valid C program
    public function test_liu_sandbox_ok_C() {
        $sandbox = new LiuSandbox();
        $code = "#include <stdio.h>\nint main() {\n    printf(\"Hello sandbox\\n\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'C', NULL);
        $this->assertEquals(Sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("Hello sandbox\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
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
print f.read()
f.close()
";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals(Sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("stuff\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
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
print f.read()
f.close()
";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals(Sandbox::RESULT_ILLEGAL_SYSCALL, $result->result);
        $sandbox->close();
    }


    private function delTree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

}

?>
