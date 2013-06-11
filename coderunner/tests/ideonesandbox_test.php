<?php
/**
 * Unit tests for coderunner's ideone sandbox class.
 * Need full internet connectivity to run this as it needs to
 * send jobs to ideone.com.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/ideonesandbox.php');

class qtype_coderunner_ideonesandbox_test extends basic_testcase {
    public function setUp() {
    }

    public function tearDown() {
    }

    public function test_testfunction() {
        $sandbox = new IdeoneSandbox();  // Lots happens here!

        $tr = $sandbox->testFunction();  // Make sure the generic test runs
        $this->assertEquals($tr->error, Sandbox::OK);
        $this->assertEquals($tr->pi, 3.14);
        $this->assertEquals($tr->answerToLifeAndEverything, 42);
        $this->assertTrue($tr->oOok);

        // Now check if we have at least a few of the expected languages.

        $langObj = $sandbox->getLanguages();
        $langs = $langObj->languages;
        $this->assertTrue(in_array('python2', $langs, TRUE));
        $this->assertTrue(in_array('python3', $langs, TRUE));
        $this->assertTrue(in_array('C', $langs, TRUE));
        $this->assertTrue(in_array('C99 strict (gcc-4.7.2)', $langs, TRUE));
    }

    public function test_ideonesandbox_python2_good() {
        // Test ideone using the execute method of the base class
        // with a valid python2 program.
        $source = 'print "Hello sandbox!"';
        $sandbox = new IdeoneSandbox();
        $result = $sandbox->execute($source, 'python2', '');
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->stderr, '');
        $this->assertEquals($result->cmpinfo, '');
        $this->assertEquals($result->output, "Hello sandbox!\n");
        $sandbox->close();
    }


    public function test_ideonesandbox_python3_good() {
        // Test the ideone sandbox using the execute method of the base class
        // with a valid python3 program.
        $source = 'print("Hello sandbox!")';
        $sandbox = new IdeoneSandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals($result->result, Sandbox::RESULT_SUCCESS);
        $this->assertEquals($result->stderr, '');
        $this->assertEquals($result->cmpinfo, '');
        $this->assertEquals($result->output, "Hello sandbox!\n");
        $sandbox->close();
    }


    // Test the ideone sandbox using the execute method of the base class
    // with a syntactically invalid python3 program.
    public function test_ideonesandbox_python3_bad() {
        $source = "print('Hello sandbox!'):\n";
        $sandbox = new IdeoneSandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals($result->result, Sandbox::RESULT_RUNTIME_ERROR);
        $this->assertEquals($result->signal, 0);
        $sandbox->close();
    }


    public function test_ideonesandbox_python3_timeout() {
        // Test the ideone sandbox using the execute method of the base class
        // with a python3 program that loops.
        $source = "while 1: pass\n";
        $sandbox = new IdeoneSandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals($result->result, Sandbox::RESULT_TIME_LIMIT);
        $this->assertEquals($result->output, '');
        $this->assertEquals($result->stderr, '');
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }

    // Test the liu sandbox with a memory limit error
    /*** Commented out as it actually just times out instead, so no point.
    public function test_liu_sandbox_memlimit() {
        $sandbox = new IdeoneSandbox();
        $code = "data = []\nwhile True: data.append(1)";
        $result = $sandbox->execute($code, 'python2', NULL);
        $this->assertEquals($result->result, Sandbox::RESULT_MEMORY_LIMIT);
        $this->assertEquals($result->output, '');
        $this->assertEquals($result->stderr, '');
        $this->assertEquals($result->cmpinfo, '');
        $sandbox->close();
    }
    */

/*
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


    private function delTree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
*/
}

?>
