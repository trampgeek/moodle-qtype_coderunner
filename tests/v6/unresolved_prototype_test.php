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
 * Unit tests for a question whose prototype cannot be found.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/type/coderunner/classes/jobrunner.php');

/**
 * Tests that a question whose chain cannot be built says so.
 *
 * This state now arises in normal use: deleting a course deletes the
 * prototypes it owned, so a question elsewhere that used one is left with a
 * prototype id pointing at nothing. It has to report itself broken rather than
 * try to run, which it cannot do, having no template.
 *
 * What made that fail is that neither of the things the run-time path could see
 * distinguishes the two cases. A question that resolved perfectly well also has
 * a null `prototype` -- resolution nulls it -- and a set `prototypeid`. So
 * resolution records that it could not build the chain, and the job runner
 * reads that.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner_jobrunner::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class unresolved_prototype_test extends \advanced_testcase {
    /** @var int the question category. */
    private int $categoryid;

    /** @var prototype_repository the manager's repository. */
    private prototype_repository $repo;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $courseid = (int) $this->getDataGenerator()->create_course()->id;
        $this->categoryid = (int) $this->getDataGenerator()
            ->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => \context_course::instance($courseid)->id])
            ->id;
        $this->repo = new prototype_repository();
    }

    /**
     * Resolution records that it could not build the chain.
     */
    public function test_an_unfindable_prototype_is_recorded_as_unresolved(): void {
        $questiondata = $this->load($this->make_question(self::gone()));

        $this->assertNotEmpty(
            $questiondata->options->prototypeunresolved ?? null,
            'Nothing recorded that the chain could not be built, so the run-time path cannot know.'
        );
    }

    /**
     * A question that resolves is not marked unresolved.
     *
     * The other half of the rule: if the flag were set for every question, the
     * job runner would refuse to run any of them.
     */
    public function test_a_question_that_resolves_is_not_marked(): void {
        $prototype = $this->repo->create(
            'present_type',
            SITEID,
            new override_set(['template' => 'PRESENT'], 'present_type')
        );

        $questiondata = $this->load($this->make_question((int) $prototype->id));

        $this->assertEmpty(
            $questiondata->options->prototypeunresolved ?? null,
            'A question whose prototype is right here was marked as unresolvable.'
        );
    }

    /**
     * The job runner refuses such a question, naming the type it wanted.
     *
     * Before this, the guard tested whether a prototype *id* was present,
     * which a question with an unfindable prototype has, so the question ran
     * with no template and failed obscurely instead.
     */
    public function test_the_job_runner_reports_a_broken_question(): void {
        $question = \question_bank::make_question($this->load($this->make_question(self::gone())));

        $outcome = (new \qtype_coderunner_jobrunner())->run_tests($question, '', [], [], false, '');

        $this->assertSame(
            \qtype_coderunner_testing_outcome::STATUS_MISSING_PROTOTYPE,
            $outcome->status,
            'A question whose prototype cannot be found was not reported as broken.'
        );
        $this->assertStringContainsString(
            'vanished_type',
            $outcome->errormessage,
            'The message has to name the question type that is missing.'
        );
    }

    /**
     * A prototype id that belongs to nothing.
     *
     * @return int the id.
     */
    private static function gone(): int {
        return 999999;
    }

    /**
     * A question's data, loaded as a page load loads it.
     *
     * @param int $questionid the question.
     * @return object the question data, with its chain resolved or not.
     */
    private function load(int $questionid): object {
        return \question_bank::load_question_data($questionid);
    }

    /**
     * A CodeRunner question using the given prototype id.
     *
     * @param int $prototypeid the prototype it names, found or not.
     * @return int the question id.
     */
    private function make_question(int $prototypeid): int {
        global $DB;

        $question = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question(
            'coderunner',
            null,
            ['category' => $this->categoryid, 'name' => 'Question with a lost type']
        );
        $DB->update_record('question_coderunner_options', (object) [
            'id' => $DB->get_field('question_coderunner_options', 'id', ['questionid' => $question->id]),
            'prototypeid' => $prototypeid,
            'coderunnertype' => 'vanished_type',
            'overrides' => '{}',
            'template' => null,
        ]);
        \question_bank::notify_question_edited($question->id);

        return (int) $question->id;
    }
}
