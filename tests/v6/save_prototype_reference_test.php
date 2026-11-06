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
 * Unit tests for recording a question's prototype reference when it is saved.
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

/**
 * Tests that a question saved through the CodeRunner 5 form acquires the
 * CodeRunner 6 prototype id and overrides document, so that a question edited
 * after the migration resolves the way a migrated one does.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(prototype_repository::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class save_prototype_reference_test extends \advanced_testcase {
    /** @var \qtype_coderunner the question type under test. */
    private \qtype_coderunner $qtype;

    /** @var prototype_repository prototype storage. */
    private prototype_repository $repo;

    /** @var \stdClass a course whose questions do the asking. */
    private \stdClass $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->qtype = new \qtype_coderunner();
        $this->repo = new prototype_repository();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A form-like question object of the shape save_question_options() sees
     * once clean_question_form() has run: a prototype name in coderunnertype,
     * and null in every field the question inherits.
     *
     * @param array $extra fields to set or override.
     * @param int|null $courseid the course whose context the question is in.
     * @return \stdClass the question.
     */
    private function form_question(array $extra = [], ?int $courseid = null): \stdClass {
        $context = \context_course::instance($courseid ?? $this->course->id);
        $question = (object) array_merge([
            'coderunnertype' => 'python3',
            'prototypetype' => 0,
            'context' => $context,
            'contextid' => $context->id,
            // Pinned per question by the converter, whether null or not.
            'allornothing' => 1,
            'penaltyregime' => '10, 20, ...',
            'precheck' => 0,
            // Inherited in CodeRunner 5, so null means inherited.
            'template' => null,
            'grader' => null,
            'language' => null,
        ], $extra);
        return $question;
    }

    /**
     * Create a prototype the way the snapshot does, with a template of its own.
     *
     * @param string $name the prototype name.
     * @param int $courseid the scope.
     * @return int the prototype id.
     */
    private function make_prototype(string $name, int $courseid): int {
        return $this->repo->create(
            $name,
            $courseid,
            new override_set(['template' => 'PROTOTYPE TEMPLATE'], $name)
        )->id;
    }

    /**
     * A saved question points at the prototype its name resolves to, and
     * carries an overrides document.
     */
    public function test_reference_is_taken_from_the_prototype_name(): void {
        $prototypeid = $this->make_prototype('python3', SITEID);

        $question = $this->form_question();
        $this->qtype->set_prototype_reference($question);

        $this->assertEquals($prototypeid, $question->prototypeid);

        $overrides = json_decode($question->overrides, true);
        $this->assertIsArray($overrides);
        // Pinned because CodeRunner 5 did not inherit it.
        $this->assertArrayHasKey('allornothing', $overrides);
        // Null in the column and inherited in CodeRunner 5, so not an override.
        $this->assertArrayNotHasKey('template', $overrides);
    }

    /**
     * A question whose own value for a CodeRunner 5 inherited field is set
     * overrides the prototype, and the overrides document says so.
     */
    public function test_a_customised_field_becomes_an_override(): void {
        $this->make_prototype('python3', SITEID);

        $question = $this->form_question(['template' => 'MY OWN TEMPLATE']);
        $this->qtype->set_prototype_reference($question);

        $overrides = json_decode($question->overrides, true);
        $this->assertSame('MY OWN TEMPLATE', $overrides['template']);
    }

    /**
     * An unresolvable name leaves a null prototype id, so that the question is
     * recorded as broken, but still gets its overrides document.
     */
    public function test_unresolvable_name_gives_a_null_id_and_keeps_overrides(): void {
        $question = $this->form_question(['coderunnertype' => 'no_such_prototype']);
        $this->qtype->set_prototype_reference($question);

        $this->assertNull($question->prototypeid);
        $this->assertIsArray(json_decode($question->overrides, true));
    }

    /**
     * A prototype belonging to another course is not visible, which is the
     * same scope rule the picker and the migration use.
     */
    public function test_another_courses_prototype_is_not_visible(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->make_prototype('python3', (int) $other->id);

        $question = $this->form_question();
        $this->qtype->set_prototype_reference($question);

        $this->assertNull($question->prototypeid);
    }

    /**
     * A prototype of the question's own course is visible.
     */
    public function test_the_questions_own_course_prototype_is_visible(): void {
        $prototypeid = $this->make_prototype('python3_local', (int) $this->course->id);

        $question = $this->form_question(['coderunnertype' => 'python3_local']);
        $this->qtype->set_prototype_reference($question);

        $this->assertEquals($prototypeid, $question->prototypeid);
    }

    /**
     * A legacy prototype question is not a question with a prototype, so both
     * columns stay null — which is what the migration does with it too.
     */
    public function test_a_legacy_prototype_question_keeps_both_columns_null(): void {
        $this->make_prototype('python3', SITEID);

        $question = $this->form_question(['prototypetype' => 2]);
        $this->qtype->set_prototype_reference($question);

        $this->assertNull($question->prototypeid);
        $this->assertNull($question->overrides);
    }

    /**
     * Both columns are extra question fields, so that get_question_options()
     * loads them and save_question_options() writes them.
     */
    public function test_both_columns_are_extra_question_fields(): void {
        $fields = \qtype_coderunner::extra_question_fields_static();
        $this->assertContains('prototypeid', $fields);
        $this->assertContains('overrides', $fields);
    }

    /**
     * Both columns are non-inherited. Without this, clean_question_form()
     * nulls them on every save and set_inherited_fields() copies a prototype
     * question's values over them.
     */
    public function test_both_columns_are_non_inherited(): void {
        $fields = \qtype_coderunner::noninherited_fields();
        $this->assertContains('prototypeid', $fields);
        $this->assertContains('overrides', $fields);
    }

    /**
     * clean_question_form() must leave the two columns alone. It nulls any
     * inherited field that arrives blank, and both arrive blank from a form
     * that has no control for them.
     */
    public function test_clean_question_form_does_not_null_the_new_columns(): void {
        $prototypeid = $this->make_prototype('python3', SITEID);

        $question = $this->form_question([
            'prototypeid' => $prototypeid,
            'overrides' => '{"allornothing":1}',
            'customise' => false,
            'isnew' => false,
            'testcases' => [],
        ]);
        $this->qtype->clean_question_form($question);

        $this->assertEquals($prototypeid, $question->prototypeid);
        $this->assertSame('{"allornothing":1}', $question->overrides);
    }

    /**
     * The prototype id is a local id, so it is excluded from the identity hash
     * that restore uses to match a backed-up question against an existing one.
     */
    public function test_prototypeid_is_excluded_from_the_identity_hash(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/moodle2/restore_qtype_plugin.class.php');
        require_once($CFG->dirroot .
            '/question/type/coderunner/backup/moodle2/restore_qtype_coderunner_plugin.class.php');

        $method = new \ReflectionMethod(
            \restore_qtype_coderunner_plugin::class,
            'define_excluded_identity_hash_fields'
        );
        $excluded = $method->invoke(
            (new \ReflectionClass(\restore_qtype_coderunner_plugin::class))->newInstanceWithoutConstructor()
        );

        $this->assertContains('/options/prototypeid', $excluded);
    }
}
