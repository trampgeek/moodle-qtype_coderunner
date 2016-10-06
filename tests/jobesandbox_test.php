<?php
/**
 * Unit tests for coderunner's jobe sandbox class.
 * This test requires that the jobe sandbox (configured in tests/config.php)
 * be set to require an API-key and that the key "test-api-key" be set in
 * its database as a valid key that *does* enforce limits. Because the
 * Jobe sandbox maintains its own database of accesses, this test can only
 * be run correctly once an hour, unless the limits table in the Jobe DB
 * is cleared between tests.
 *
 * @group qtype_coderunner
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2013, 2014, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');
require_once($CFG->dirroot . '/question/type/coderunner/sandbox/jobesandbox.php');


class qtype_coderunner_jobesandbox_test extends qtype_coderunner_testcase {

    private $hasfailed = false;

    protected function onNotSuccessfulTest(Exception $e) {
        $this->hasfailed = true;
        throw $e;
    }

    public function test_fail_with_bad_key() {
        $this->check_sandbox_enabled('jobesandbox');
        set_config('jobe_apikey', 'no-such-key-we-hope', 'qtype_coderunner');
        $sandbox = new qtype_coderunner_jobesandbox();
        $langs = $sandbox->get_languages();
        $this->assertEquals($sandbox::AUTH_ERROR, $langs->error);
    }

    public function test_succeed_with_good_key() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new qtype_coderunner_jobesandbox();
        // config.php should have the correct api-key in it
        $langs = $sandbox->get_languages();
        $this->assertEquals($sandbox::OK, $langs->error);
    }

    public function test_languages() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new qtype_coderunner_jobesandbox();
        $langs = $sandbox->get_languages()->languages;
        $this->assertTrue(in_array('python3', $langs, true));
        $this->assertTrue(in_array('c', $langs, true));
    }

    public function test_jobesandbox_python3_good() {
        // Test the jobe sandbox using the execute method of the base class.
        // with a valid python3 program.
        $this->check_sandbox_enabled('jobesandbox');
        $source = 'print("Hello sandbox!")';
        $sandbox = new qtype_coderunner_jobesandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals(qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('', $result->cmpinfo);
        $this->assertEquals("Hello sandbox!\n", $result->output);
        $sandbox->close();
    }

    // Test the jobe sandbox using the execute method of the base class.
    // with a syntactically invalid python3 program.
    public function test_jobesandbox_python3_bad() {
        $this->check_sandbox_enabled('jobesandbox');
        $source = "print('Hello sandbox!'):\n";
        $sandbox = new qtype_coderunner_jobesandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals(qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR, $result->result);
        $sandbox->close();
    }

    public function test_jobesandbox_python3_with_files() {
        $this->check_sandbox_enabled('jobesandbox');
        $source = "print(open('first.a').read())
print(open('second.bb').read())
";
        $sandbox = new qtype_coderunner_jobesandbox();
        $result = $sandbox->execute($source, 'python3', '',
                array('first.a' => "Line1\nLine2",
                      'second.bb' => 'Otherfile'));
        $this->assertEquals(qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('', $result->cmpinfo);
        $this->assertEquals("Line1\nLine2\nOtherfile\n", $result->output);
    }

    public function test_jobesandbox_python3_timeout() {
        // Test the jobe sandbox using the execute method of the base class
        // with a python3 program that loops.
        $this->check_sandbox_enabled('jobesandbox');
        $source = "while 1: pass\n";
        $sandbox = new qtype_coderunner_jobesandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals(qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(qtype_coderunner_sandbox::RESULT_TIME_LIMIT, $result->result);
        $this->assertEquals('', $result->output);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the jobe sandbox with a syntactically bad C program.
    public function test_jobe_sandbox_bad_C() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new qtype_coderunner_jobesandbox();
        $code = "#include <stdio.h>\nint main(): {\n    printf(\"Hello sandbox\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'c', null);
        $this->assertEquals(qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR, $result->result);
        $this->assertTrue(strpos($result->cmpinfo, 'error:') !== false);
        $sandbox->close();
    }

    // Test the jobe sandbox with a valid C program.
    public function test_jobe_sandbox_ok_C() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new qtype_coderunner_jobesandbox();
        $code = "#include <stdio.h>\nint main() {\n    printf(\"Hello sandbox\\n\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'c', null);
        $this->assertEquals(qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("Hello sandbox\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the jobe sandbox with a valid java program.
    public function test_jobe_sandbox_ok_java() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new qtype_coderunner_jobesandbox();
        $langs = $sandbox->get_languages()->languages;
        if (!in_array('java', $langs)) {
            $this->markTestSkipped('Java not available on the Jobe server. ' .
                    'Test skipped');
        }
        $code = 'public class HelloWorld { 
   public static void main(String[] args) { 
      System.out.println("Hello sandbox");
   }
}';
        $result = $sandbox->execute($code, 'java', null);
        $this->assertEquals(qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("Hello sandbox\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test if limits are enforced, but only if all previous tests passed
    // (otherwise we're done with testing for an hour).
    public function test_limits_enforced() {
        $this->check_sandbox_enabled('jobesandbox');
        if ($this->hasfailed) {
            $this->markTestSkipped("Skipping limit testing with JobeSandbox as there are other errors");
        } else {
            $maxnumtries = 100;  // Assume that jobe sets the max num gets per hour at 100.
            $sandbox = new qtype_coderunner_jobesandbox();
            $source = 'print("Hello sandbox!")';
            for ($i = 0; $i < $maxnumtries; $i++) {
                $result = $sandbox->execute($source, 'python3', '', null, array('debug' => 1));
                if ($result->error === $sandbox::SUBMISSION_LIMIT_EXCEEDED) {
                    return;
                } else {
                    $this->assertEquals($result->error, $sandbox::OK);
                }
            }

            $this->assertTrue(false);  // Never got a submission limit exceeded.
        }
    }
}
