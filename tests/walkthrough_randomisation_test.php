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

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

/**
 * Further walkthrough tests for the CodeRunner plugin, testing the
 * randomisation mechanisem.
 * @group qtype_coderunner
 * @coversNothing
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2018 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class walkthrough_randomisation_test extends \qbehaviour_walkthrough_test_base {
    protected function setUp(): void {
        parent::setUp();
        \qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    // A randomised sqr that is named either sqr or mysqr with a single testcase
    // that prints the sqr of either 111 or 112. Checks Twig expansion of
    // questiontext test code, extra, and expected for all four possible
    // combinations.
    public function test_randomised_sqr() {

        $iters = 0;
        $tests = [
            ['searchfor' => 'print(mysqr(111))', 'answer' => "def mysqr(n): return n * n"],
            ['searchfor' => 'print(mysqr(112))', 'answer' => "def mysqr(n): return n * n"],
            ['searchfor' => 'print(sqr(111))', 'answer' => "def sqr(n): return n * n"],
            ['searchfor' => 'print(sqr(112))', 'answer' => "def sqr(n): return n * n"],
        ];

        foreach ($tests as $test) {
            while ($iters < 50) {
                // Keep trying the question until we get desired search string.
                $q = \test_question_maker::make_question('coderunner', 'sqr');
                $this->add_fields($q);
                $this->start_attempt_at_question($q, 'adaptive', 1, 1);
                $this->render();
                if (strpos($this->currentoutput, $test['searchfor']) !== false) {
                    break;
                }
                $iters += 1;
            }

            $this->assertTrue($iters < 50);
            $this->process_submission(['-submit' => 1, 'answer' => $test['answer']]);
            $this->check_current_mark(1.0);
        }
    }


    // A variant of the above that sets the random number seed at the start.
    // We check that iterating over a sequence of seeds eventually results in
    // each possible answer and that continuing with a fixed seed results in
    // no further randomisation.
    public function test_randomised_sqr_with_seed() {

        $tests = [
            ['searchfor' => 'print(mysqr(111))', 'answer' => "def mysqr(n): return n * n"],
            ['searchfor' => 'print(mysqr(112))', 'answer' => "def mysqr(n): return n * n"],
            ['searchfor' => 'print(sqr(111))', 'answer' => "def sqr(n): return n * n"],
            ['searchfor' => 'print(sqr(112))', 'answer' => "def sqr(n): return n * n"],
        ];

        foreach ($tests as $test) {
            // First, iterate the seed until the desired situation occurs.
            $found = false;
            $seed = 1000;
            while ($seed < 1100 && !$found) {
                $seed++;
                $q = \test_question_maker::make_question('coderunner', 'sqr');
                $this->add_fields($q, $seed);
                $this->start_attempt_at_question($q, 'adaptive', 1, 1);
                $this->render();
                if (strpos($this->currentoutput, $test['searchfor']) !== false) {
                    $found = true;
                }
            }

            $this->assertTrue($found);

            // Without changing seed, check that the same results occur every time.
            for ($i = 0; $i < 20; $i++) {
                $q = \test_question_maker::make_question('coderunner', 'sqr');
                $this->add_fields($q, $seed);
                $this->start_attempt_at_question($q, 'adaptive', 1, 1);
                $this->render();
                $this->assertTrue(strpos($this->currentoutput, $test['searchfor']) !== false);
            }
        }
    }


    private function add_fields($q, $seed = false) {
        if ($seed !== false) {
            $seeding = "{{- set_random_seed($seed) -}}\n";
        } else {
            $seeding = '';
        }

        $templateparams = '{"func": "{{ random(["sqr", "mysqr"]) }}", "n": {{ 111 + random(1) }} }';

        $q->templateparams = $seeding . $templateparams;
        $q->templateparamslang = 'twig';
        $q->templateparamsevalpertry = 0;
        $q->templateparamsevald = null;
        $q->uiparameters = null;
        $q->hoisttemplateparams = 1;
        $q->extractcodefromjson = 1;
        $q->twigall = 1;
        $q->questiontext = 'Write a function {{ func }}';
        $q->template = "{{ STUDENT_ANSWER }}\n{{ TEST.testcode }}\n{{ TEST.extra }}\n";
        $q->iscombinatortemplate = false;
        $q->testcases = [
            (object) ['type'     => 0,
                          'testcode'  => 'print({{ func }}({{ n }}))',
                          'extra'     => 'print({{ func }}({{ n }}))',
                          'expected'  => "{{ n * n }}\n{{ n * n }}",
                          'stdin'     => '',
                          'useasexample' => 1,
                          'display'   => 'SHOW',
                          'mark'      => 1.0,
                          'hiderestiffail'  => 0],
        ];
    }
}
