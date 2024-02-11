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
 * Testing the templating mechanism.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/vendor/autoload.php');
require_once($CFG->dirroot . '/question/type/coderunner/classes/twigmacros.php');

/**
 * Unit tests for the behaviour of coderunner question templates.
 * @coversNothing
 */
class template_test extends \qtype_coderunner_testcase {
    public function test_template_engine() {
        // Check if the template engine is installed and working OK.
        $macros = \qtype_coderunner_twigmacros::macros();
        $twigloader = new \Twig\Loader\ArrayLoader($macros);
        $twigoptions = [
                'cache' => false,
                'optimizations' => 0,
                'autoescape' => false,
                'strict_variables' => true,
                'debug' => true];
        $twig = new \Twig\Environment($twigloader, $twigoptions);

        $template = $twig->createTemplate('Hello {{ name }}!');
        $renderedstring = $template->render(['name' => 'Fabien']);
        $this->assertEquals('Hello Fabien!', $renderedstring);
    }

    public function test_question_template() {
        // Check that a Python question gets suitably expanded with parameters
        // from the question itself. Also tests the JSON handling of sandbox
        // params.
        $q = $this->make_question('sqr');
        $q->sandboxparams = "twiddle-twaddle";
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
{{ TEST.testcode }}
print( '{{QUESTION.sandboxparams}}')
EOTEMPLATE;
        $q->iscombinatortemplate = false;
        $q->allornothing = false;
        $q->testcases = [
           (object) ['testtype'      => 0,
                         'testcode'       => 'print(sqr(-3))',
                         'expected'       => "9\ntwiddle-twaddle",
                         'stdin'          => '',
                         'extra'          => '',
                         'useasexample'   => 0,
                         'display'        => 'SHOW',
                         'mark'           => 1.0,
                         'hiderestiffail' => 0],
        ];
        $code = "def sqr(n): return n * n\n";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(\question_state::$gradedright, $grade);
    }

    public function test_grading_template() {
        // Test a template that is also custom grader, plus python-escaping
        // in Twig templates.
        // This grader gives full marks if the input value is negative and
        // the output value is correct or zero marks otherwise.
        // The testcases are for n = {0, 1, 11, -7, -6} with marks of
        // 1, 2, 4, 8, 16 respectively. So the expected mark is 24 / 31
        // i.e 0.7742.
        $q = $this->make_question('sqrnoprint');
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
got = str({{TEST.testcode}})
expected = """{{TEST.expected|e('py')}}""".strip()
if expected == '36' and expected == got:
    print('{"fraction":1.0}')
elif expected == '49' and expected == got:
    print('{"fraction":1}')
else:
    print('{"fraction":0}')
EOTEMPLATE;
        $q->grader = 'TemplateGrader';
        $q->iscombinatortemplate = false;
        $q->allornothing = false;
        $code = "def sqr(n): return n * n\n";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertTrue(abs($mark - 24.0 / 31.0) < 0.000001);
        $q->allornothing = true;
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertTrue($mark == 0.0);
    }

    public function test_template_params() {
        // Test that a templateparams field in the question is expanded
        // from a JSON string and available to the template engine.

        $q = $this->make_question('sqr');
        $q->templateparams = '{"age":23, "string":"blah"}';
        $q->parameters = json_decode($q->templateparams);
        $q->template = <<<EOTEMPLATE
{{ STUDENT_ANSWER }}
{{ TEST.testcode }}
print( {{QUESTION.parameters.age}}, '{{QUESTION.parameters.string}}')
EOTEMPLATE;
        $q->allornothing = false;
        $q->iscombinatortemplate = false;
        $q->testcases = [
                       (object) ['type' => 0,
                         'testcode'       => '',
                         'expected'       => "23 blah",
                         'stdin'          => '',
                         'extra'          => '',
                         'useasexample'   => 0,
                         'display'        => 'SHOW',
                         'mark'           => 1.0,
                         'hiderestiffail' => 0],
        ];
        $q->allornothing = false;
        $q->iscombinatortemplate = false;
        $code = "";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $this->assertEquals(\question_state::$gradedright, $grade);
    }
}
