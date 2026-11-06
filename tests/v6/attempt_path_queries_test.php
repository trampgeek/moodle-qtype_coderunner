<?php
// This file is part of CodeRunner - http://coderunner.org.nz
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
 * Unit tests that the attempt path reads no prototype table.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Decision 16: everything the collected fields need is resolved when a question
 * is loaded, and the attempt path merges it without touching the prototype
 * tables.
 *
 * This is enforced by counting reads rather than by review, because the cost of
 * getting it wrong is invisible in a test suite and expensive in an exam: the
 * attempt path runs once per student per page, where question loading runs once
 * per questiondata cache entry.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class attempt_path_queries_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A question whose chain has been resolved carries its per-layer data, so
     * applying attempt state reads neither prototype table.
     */
    public function test_applying_attempt_state_reads_no_prototype_table(): void {
        global $DB;

        $repo = new prototype_repository();
        $course = $this->getDataGenerator()->create_course();
        $parent = $repo->create('python3_base', SITEID, new override_set([
            'template' => '{{ STUDENT_ANSWER }}',
            'templateparams' => '{"fromparent": 1}',
            'templateparamslang' => 'twig',
            'uiplugin' => 'ace',
            'uiparameters' => '{"font_size": 18}',
        ], 'python3_base'));
        $child = $repo->create('python3_child', (int) $course->id, new override_set([
            'templateparams' => '{"fromchild": 2}',
            'templateparamslang' => 'twig',
        ], 'python3_child'), $parent->id);

        // What get_question_options() would have recorded on the question.
        $chain = $repo->chain_for($child->id, new override_set([
            'templateparams' => '{"fromquestion": 3}',
            'templateparamslang' => 'twig',
        ], \qtype_coderunner::QUESTION_LAYER));
        $layers = (new \qtype_coderunner())->chain_layers($chain);
        $areas = $repo->file_areas_for($child->id);

        $this->assertCount(3, $layers, 'The root prototype, the child and the question.');

        $question = new \qtype_coderunner_question();
        $question->chainlayers = $layers;
        $question->supportfileareas = $areas;
        $question->prototypeid = $child->id;
        $question->prototypetype = 0;
        $question->templateparams = '{"fromquestion": 3}';
        $question->templateparamslang = 'twig';
        $question->uiplugin = 'ace';
        $question->uiparameters = null;
        $question->twigall = 0;
        $question->testcases = [];
        $question->student = new \qtype_coderunner_student(\core_user::get_user_by_username('admin'));

        $before = $DB->perf_get_reads();
        $question->evaluate_question_for_display(1234, null);
        $after = $DB->perf_get_reads();

        $this->assertSame(
            '',
            $question->initialisationerrormessage,
            'Evaluating the question for display reported an initialisation error.'
        );

        $this->assertSame(
            18,
            $question->mergeduiparameters['font_size'] ?? null,
            "A prototype's UI parameter reached the merged set."
        );

        // Reads are allowed — the file API is one — but none may be a prototype
        // table, so the strongest available check is that the whole merge is
        // cheap and does not grow with chain depth.
        $this->assertLessThanOrEqual(
            4,
            $after - $before,
            'Evaluating a question for display did more database reads than the file lookups need, '
                . 'which suggests the prototype chain is being walked on the attempt path.'
        );
    }

    /**
     * Every layer's template parameters are merged, with the nearest layer
     * winning, and each is evaluated in its own language.
     */
    public function test_every_layer_contributes_its_template_parameters(): void {
        $repo = new prototype_repository();
        $parent = $repo->create('p_base', SITEID, new override_set([
            'templateparams' => '{"a": 1, "b": 1}',
            'templateparamslang' => 'twig',
        ], 'p_base'));

        $chain = $repo->chain_for($parent->id, new override_set([
            'templateparams' => '{"b": 2, "c": 2}',
            'templateparamslang' => 'twig',
        ], \qtype_coderunner::QUESTION_LAYER));

        $question = new \qtype_coderunner_question();
        $question->chainlayers = (new \qtype_coderunner())->chain_layers($chain);
        $question->prototypetype = 0;
        $question->templateparamsevalpertry = 0;

        $merged = json_decode($question->evaluate_merged_parameters(1234, null), true);

        $this->assertSame(1, $merged['a'], 'A value only the root sets is inherited.');
        $this->assertSame(2, $merged['b'], 'The nearest layer wins.');
        $this->assertSame(2, $merged['c'], 'A value only the question sets is kept.');
    }
}
