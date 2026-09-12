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
 * Unit tests for the coderunner question type class.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/coderunner/edit_coderunner_form.php');



/**
 * Unit tests for the coderunner question type class.
 * @coversNothing
 * @copyright  2021 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questiontype_test extends \advanced_testcase {
    protected $qtype;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->qtype = new \qtype_coderunner();
    }

    protected function tearDown(): void {
        $this->qtype = null;
    }

    protected function get_test_question_data() {
        $q = new \stdClass();
        $q->id = 1;

        return $q;
    }

    public function test_name(): void {
        $this->assertEquals('coderunner', $this->qtype->name());
    }


    public function test_get_random_guess_score(): void {
        $q = $this->get_test_question_data();
        $this->assertEquals(0, $this->qtype->get_random_guess_score($q));
    }

    public function test_get_possible_responses(): void {
        $q = $this->get_test_question_data();
        $this->assertEquals([], $this->qtype->get_possible_responses($q));
    }

    /**
     * Build a form-data object equivalent to what the editing form submits
     * for three test cases after the middle one has been deleted with its
     * "Delete this test case" button. The surviving rows keep their original
     * repeat_elements keys (0 and 2), so every parallel field array is
     * non-contiguous.
     * @return \stdClass the fake submitted question data.
     */
    private function make_form_data_with_deleted_middle_case(): \stdClass {
        $question = new \stdClass();
        $question->id = 42;
        // Note the missing key 1 throughout: that row was deleted.
        $question->testcode = [0 => 'print(1)', 2 => 'print(3)'];
        $question->stdin = [0 => '', 2 => ''];
        $question->expected = [0 => '1', 2 => '3'];
        $question->extra = [0 => '', 2 => ''];
        $question->testtype = [0 => 0, 2 => 0];
        $question->useasexample = [0 => 0, 2 => 0];
        $question->display = [0 => 'SHOW', 2 => 'SHOW'];
        $question->hiderestiffail = [0 => 0, 2 => 0];
        $question->mark = [0 => '1.0', 2 => '2.0'];
        $question->ordering = [0 => 10, 2 => 20];
        return $question;
    }

    /**
     * Invoke the private copy_testcases_from_form on $this->qtype.
     * @param \stdClass $question the fake submitted question data.
     * @param bool $validation whether to run in validation mode.
     */
    private function invoke_copy_testcases_from_form(\stdClass $question, bool $validation): void {
        $method = new \ReflectionMethod(\qtype_coderunner::class, 'copy_testcases_from_form');
        $method->setAccessible(true);
        // copy_testcases_from_form takes $question by reference, so the args
        // array must hold it by reference or invokeArgs rejects it.
        $args = [&$question, $validation];
        $method->invokeArgs($this->qtype, $args);
    }

    public function test_copy_testcases_from_form_keeps_noncontiguous_survivors(): void {
        // Deleting a middle test case leaves non-contiguous keys. Iterating by
        // key (rather than by a contiguous 0..count-1 index) must keep both
        // survivors intact and correctly paired: the old contiguous loop would
        // have read the deleted index 1 and skipped the real index 2, losing
        // the last test case.
        $question = $this->make_form_data_with_deleted_middle_case();
        $this->invoke_copy_testcases_from_form($question, false);

        $this->assertCount(2, $question->testcases);
        $this->assertSame('print(1)', $question->testcases[0]->testcode);
        $this->assertSame('1', $question->testcases[0]->expected);
        $this->assertSame('print(3)', $question->testcases[1]->testcode);
        $this->assertSame('3', $question->testcases[1]->expected);
        // Marks must follow their own row, not shift onto a neighbour.
        $this->assertEqualsWithDelta(1.0, $question->testcases[0]->mark, 0.0001);
        $this->assertEqualsWithDelta(2.0, $question->testcases[1]->mark, 0.0001);
    }

    public function test_copy_testcases_from_form_validation_maps_original_row_numbers(): void {
        // In validation mode each testcase records the form row it came from so
        // that a failed test can be linked back to the right field. After a
        // deletion that row number must be the surviving row's original key (2),
        // not its new position in the compacted list (1).
        $question = $this->make_form_data_with_deleted_middle_case();
        $this->invoke_copy_testcases_from_form($question, true);

        $this->assertCount(2, $question->testcases);
        $this->assertSame(0, $question->testcases[0]->rownum);
        $this->assertSame(2, $question->testcases[1]->rownum);
    }
}
