<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for coderunner's jobe sandbox class.
 *
 * This test requires that the jobe sandbox (configured in
 * tests/fixtures/test-sandbox-config-dist.php)
 * be set to require an API-key and that the key "test-api-key" be set in
 * its database as a valid key that *does* enforce limits. Because the
 * Jobe sandbox maintains its own database of accesses, this test can only
 * be run correctly once an hour, unless the limits table in the Jobe DB
 * is cleared between tests.
 *
 * @group qtype_coderunner
 * @package    qtype_coderunner
 * @copyright  2013, 2014, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');

/**
 * @coversNothing
 */
class jobesandbox_test extends \qtype_coderunner_testcase {
    public function test_fail_with_bad_key() {
        $this->check_sandbox_enabled('jobesandbox');
        if (!get_config('qtype_coderunner', 'jobe_apikey_enabled')) {
            $this->markTestSkipped("Jobe API key security disabled: test skipped");
        }
        set_config('jobe_apikey', 'no-such-key-we-hope', 'qtype_coderunner');
        $sandbox = new \qtype_coderunner_jobesandbox();
        $langs = $sandbox->get_languages();
        $this->assertEquals($sandbox::AUTH_ERROR, $langs->error);
    }

    public function test_succeed_with_good_key() {
        $this->check_sandbox_enabled('jobesandbox');
        if (!get_config('qtype_coderunner', 'jobe_apikey_enabled')) {
            $this->markTestSkipped("Jobe API key security disabled: test skipped");
        }
        $sandbox = new \qtype_coderunner_jobesandbox();
        // NB: config.php should have the correct api-key in it.
        $langs = $sandbox->get_languages();
        $this->assertEquals($sandbox::OK, $langs->error);
    }

    public function test_languages() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new \qtype_coderunner_jobesandbox();
        $langs = $sandbox->get_languages()->languages;
        $this->assertTrue(in_array('python3', $langs, true));
        $this->assertTrue(in_array('c', $langs, true));
    }

    public function test_jobesandbox_python3_good() {
        // Test the jobe sandbox using the execute method of the base class.
        // with a valid python3 program.
        $this->check_sandbox_enabled('jobesandbox');
        $source = 'print("Hello sandbox!")';
        $sandbox = new \qtype_coderunner_jobesandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals(\qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(\qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
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
        $sandbox = new \qtype_coderunner_jobesandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals(\qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(\qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR, $result->result);
        $sandbox->close();
    }

    public function test_jobesandbox_python3_with_files() {
        $this->check_sandbox_enabled('jobesandbox');
        $source = "print(open('first.a').read())
print(open('second.bb').read())
";
        $sandbox = new \qtype_coderunner_jobesandbox();
        $result = $sandbox->execute(
            $source,
            'python3',
            '',
            ['first.a' => "Line1\nLine2",
            'second.bb' => 'Otherfile']
        );
        $this->assertEquals(\qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(\qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('', $result->cmpinfo);
        $this->assertEquals("Line1\nLine2\nOtherfile\n", $result->output);
    }

    public function test_jobesandbox_python3_timeout() {
        // Test the jobe sandbox using the execute method of the base class
        // with a python3 program that loops.
        $this->check_sandbox_enabled('jobesandbox');
        $source = "while 1: pass\n";
        $sandbox = new \qtype_coderunner_jobesandbox();
        $result = $sandbox->execute($source, 'python3', '');
        $this->assertEquals(\qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(\qtype_coderunner_sandbox::RESULT_TIME_LIMIT, $result->result);
        $this->assertEquals('', $result->output);
        $this->assertEquals('', $result->stderr);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the jobe sandbox with a syntactically bad C program.
    public function test_jobe_sandbox_bad_c() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new \qtype_coderunner_jobesandbox();
        $code = "#include <stdio.h>\nint main(): {\n    printf(\"Hello sandbox\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'c', null);
        $this->assertEquals(\qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(\qtype_coderunner_sandbox::RESULT_COMPILATION_ERROR, $result->result);
        $this->assertTrue(strpos($result->cmpinfo, 'error:') !== false);
        $sandbox->close();
    }

    // Test the jobe sandbox with a valid C program.
    public function test_jobe_sandbox_ok_c() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new \qtype_coderunner_jobesandbox();
        $code = "#include <stdio.h>\nint main() {\n    printf(\"Hello sandbox\\n\");\n    return 0;\n}\n";
        $result = $sandbox->execute($code, 'c', null);
        $this->assertEquals(\qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(\qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("Hello sandbox\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test the jobe sandbox with a valid java program.
    public function test_jobe_sandbox_ok_java() {
        $this->check_sandbox_enabled('jobesandbox');
        $sandbox = new \qtype_coderunner_jobesandbox();
        $langs = $sandbox->get_languages()->languages;
        if (!in_array('java', $langs)) {
            $this->markTestSkipped('Java not available on the Jobe server. ' .
                    'Test skipped');
        }
        $code = <<< EOCODE
public class HelloWorld {
   public static void main(String[] args) {
      System.out.println("Hello sandbox");
   }
}
EOCODE;
        $result = $sandbox->execute($code, 'java', null);
        $this->assertEquals(\qtype_coderunner_sandbox::OK, $result->error);
        $this->assertEquals(\qtype_coderunner_sandbox::RESULT_SUCCESS, $result->result);
        $this->assertEquals("Hello sandbox\n", $result->output);
        $this->assertEquals(0, $result->signal);
        $this->assertEquals('', $result->cmpinfo);
        $sandbox->close();
    }

    // Test if limits are enforced, but only if all previous tests passed
    // (otherwise we're done with testing for an hour) and if config parameter
    // 'jobe_limits_enforced is true.
    public function test_limits_enforced() {
        $this->check_sandbox_enabled('jobesandbox');
        if (!get_config('qtype_coderunner', 'jobe_limits_enforced')) {
            $this->markTestSkipped("Jobe limits not being enforced: test skipped");
        }
        if ($this->hasfailed) {
            $this->markTestSkipped("Skipping limit testing with JobeSandbox as there are other errors");
        } else {
            $maxnumtries = 100;  // Assume that jobe sets the max num gets per hour at 100.
            $sandbox = new \qtype_coderunner_jobesandbox();
            $source = 'print("Hello sandbox!")';
            for ($i = 0; $i < $maxnumtries; $i++) {
                $result = $sandbox->execute($source, 'python3', '', null, ['debug' => 1]);
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
