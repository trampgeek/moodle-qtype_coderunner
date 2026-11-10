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
 * Unit tests for *Make prototype from this question*.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype;
use local_coderunner_manager\prototype_candidates;
use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Promoting a question's overrides to a prototype of their own.
 *
 * The property that matters, and the one asserted hardest, is that a promoted
 * question resolves to exactly what it resolved to before: the author has
 * moved their settings up a layer, not changed them.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prototype_promotion::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class prototype_promotion_test extends \advanced_testcase {
    /** @var prototype_repository prototype storage. */
    private prototype_repository $repo;

    /** @var \stdClass the course whose bank holds the question. */
    private \stdClass $course;

    /** @var \stdClass the question category, in the course's context. */
    private \stdClass $category;

    /** @var prototype the prototype the question is built on. */
    private prototype $parent;

    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');

        $this->repo = new prototype_repository();
        $this->course = $this->getDataGenerator()->create_course();
        $this->category = $this->getDataGenerator()
            ->get_plugin_generator('core_question')
            ->create_question_category([
                'contextid' => \context_course::instance($this->course->id)->id,
            ]);
        $this->parent = $this->repo->create(
            'python3',
            (int) SITEID,
            new override_set([
                'template' => '{{ STUDENT_ANSWER }}',
                'cputimelimitsecs' => 5,
                'penaltyregime' => '10, 20, ...',
            ], 'python3')
        );
        prototype_candidates::reset_caches();
    }

    /**
     * Submit the question form, with whatever promotion fields are given, and
     * return what it would save.
     *
     * @param array $promotion the promotion fields to add to the submission.
     * @param array $overrides question field values to pin as its own.
     * @return object|null what get_data() returns.
     */
    private function submit(array $promotion, array $overrides = []): ?object {
        $formdata = \test_question_maker::get_question_form_data('coderunner');
        foreach ($overrides as $name => $value) {
            $formdata->$name = $value;
        }
        // Keeps the sandbox out of it: validation runs the sample answer when
        // this is on, and these tests are about what is stored.
        $formdata->validateonsave = 0;
        \qtype_coderunner_test_helper::pin_every_field($formdata, $this->parent->id);
        $formdata->category = "{$this->category->id},{$this->category->contextid}";
        foreach ($promotion as $name => $value) {
            $formdata->$name = $value;
        }

        \qtype_coderunner_edit_form::mock_submit((array) $formdata);

        $questiondata = \test_question_maker::get_question_data('coderunner');
        $form = \qtype_coderunner_test_helper::get_question_editing_form(
            $this->category,
            $questiondata
        );
        $this->assertTrue($form->is_validated(), 'The mock submission did not validate.');

        return $form->get_data();
    }

    /**
     * Build the question form over a submission, and return the inner
     * MoodleQuickForm so that its elements can be read.
     *
     * @param array $promotion the promotion fields in the submission.
     * @param bool $withprototype whether the submission names a prototype.
     * @return \MoodleQuickForm the inner form.
     */
    private function build_form(array $promotion, bool $withprototype = true): \MoodleQuickForm {
        $formdata = \test_question_maker::get_question_form_data('coderunner');
        $formdata->validateonsave = 0;
        \qtype_coderunner_test_helper::pin_every_field($formdata, $this->parent->id);
        if (!$withprototype) {
            $formdata->prototypeid = 0;
        }
        $formdata->category = "{$this->category->id},{$this->category->contextid}";
        foreach ($promotion as $name => $value) {
            $formdata->$name = $value;
        }
        \qtype_coderunner_edit_form::mock_submit((array) $formdata);

        $form = \qtype_coderunner_test_helper::get_question_editing_form(
            $this->category,
            \test_question_maker::get_question_data('coderunner')
        );
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * The button is offered on a question that has a prototype to be a child
     * of, and pressing it reveals the name box.
     */
    public function test_the_button_is_offered_and_reveals_the_name_box(): void {
        $mform = $this->build_form([]);
        $this->assertTrue($mform->elementExists(prototype_promotion::BUTTON));
        $this->assertFalse(
            $mform->elementExists(prototype_promotion::NAME_FIELD),
            'The name box is not on the form until it is asked for.'
        );

        $mform = $this->build_form([prototype_promotion::WANTED => 1]);
        $this->assertTrue($mform->elementExists(prototype_promotion::NAME_FIELD));
        $this->assertTrue($mform->elementExists(prototype_promotion::REPOINT_FIELD));
    }

    /**
     * A question with no prototype chosen is not offered the button: a new
     * prototype has to be a child of something.
     */
    public function test_the_button_is_not_offered_without_a_prototype(): void {
        $mform = $this->build_form([], false);

        $this->assertFalse($mform->elementExists(prototype_promotion::BUTTON));
    }

    /**
     * A prototype is created, in the question's own course, as a child of the
     * prototype the question was built on, holding exactly its overrides.
     */
    public function test_promotion_creates_a_child_holding_exactly_those_overrides(): void {
        $saved = $this->submit([
            prototype_promotion::WANTED => 1,
            prototype_promotion::NAME_FIELD => 'cosc131_python3',
            prototype_promotion::REPOINT_FIELD => 1,
        ]);

        $created = $this->repo->get((int) $saved->prototypeid);
        $this->assertNotNull($created);
        $this->assertSame('cosc131_python3', $created->name);
        $this->assertSame((int) $this->course->id, (int) $created->courseid);
        $this->assertSame((int) $this->parent->id, (int) $created->currentversion->parentid);

        // Exactly what the question overrode, redundant overrides included:
        // an override equal to the parent's value still freezes it, and
        // dropping it would change what the question follows.
        $expected = $this->overrides_of_a_pinned_form();
        $this->assertSame($expected, $created->currentversion->overrides->to_array());
    }

    /**
     * Re-pointing moves the settings up a layer: the question refers to the new
     * prototype and overrides nothing of its own.
     */
    public function test_repointing_clears_the_questions_overrides(): void {
        $saved = $this->submit([
            prototype_promotion::WANTED => 1,
            prototype_promotion::NAME_FIELD => 'cosc131_python3',
            prototype_promotion::REPOINT_FIELD => 1,
        ]);

        $this->assertNotEquals((int) $this->parent->id, (int) $saved->prototypeid);
        $this->assertSame([], json_decode($saved->overrides, true));
    }

    /**
     * And the question resolves to exactly what it resolved to before, which is
     * the whole point: the author moved their settings, not changed them.
     */
    public function test_a_repointed_question_resolves_identically(): void {
        $before = $this->submit([]);
        $beforesettings = $this->resolve(
            (int) $before->prototypeid,
            json_decode($before->overrides, true)
        );

        $after = $this->submit([
            prototype_promotion::WANTED => 1,
            prototype_promotion::NAME_FIELD => 'cosc131_python3',
            prototype_promotion::REPOINT_FIELD => 1,
        ]);
        $aftersettings = $this->resolve(
            (int) $after->prototypeid,
            json_decode($after->overrides, true)
        );

        foreach (field_registry::all() as $name => $field) {
            if (!$field->is_inheritable()) {
                continue;
            }
            if (!$field->resolves_to_one_value()) {
                // A collected field is merged from every contributing layer,
                // nearest key winning, so what has to be unchanged is the
                // sequence of contributions rather than one value. The layer
                // each comes from does change, and is meant to: that is the
                // promotion.
                $this->assertSame(
                    array_column($beforesettings->layers_of($name), 'value'),
                    array_column($aftersettings->layers_of($name), 'value'),
                    "The field {$name} is contributed differently after promotion."
                );
                continue;
            }
            $this->assertEquals(
                $beforesettings->get($name),
                $aftersettings->get($name),
                "The field {$name} resolves differently after promotion."
            );
        }
    }

    /**
     * Declining to re-point creates the prototype and leaves the question as it
     * was, keeping its own overrides and its own parent.
     */
    public function test_declining_to_repoint_leaves_the_question_alone(): void {
        $saved = $this->submit([
            prototype_promotion::WANTED => 1,
            prototype_promotion::NAME_FIELD => 'cosc131_python3',
            prototype_promotion::REPOINT_FIELD => 0,
        ]);

        $this->assertSame((int) $this->parent->id, (int) $saved->prototypeid);
        $this->assertNotSame([], json_decode($saved->overrides, true));

        $created = $this->repo->find_by_name_for_course('cosc131_python3', (int) $this->course->id);
        $this->assertNotNull($created, 'The prototype is created whether or not the question uses it.');
    }

    /**
     * An empty name makes no prototype. An author who reveals the box and
     * thinks better of it cannot hide it again, so an empty box has to mean
     * *never mind*.
     */
    public function test_an_empty_name_promotes_nothing(): void {
        $saved = $this->submit([
            prototype_promotion::WANTED => 1,
            prototype_promotion::NAME_FIELD => '   ',
            prototype_promotion::REPOINT_FIELD => 1,
        ]);

        $this->assertSame((int) $this->parent->id, (int) $saved->prototypeid);
        $this->assertSame(1, $this->count_prototypes(), 'Only the parent should exist.');
    }

    /**
     * A name already taken is a validation error against the name box, not an
     * exception from the repository.
     */
    public function test_a_clashing_name_is_a_validation_error(): void {
        $this->repo->create(
            'taken',
            (int) $this->course->id,
            new override_set([], 'taken'),
            $this->parent->id
        );

        $errors = prototype_promotion::validate(
            [prototype_promotion::NAME_FIELD => 'taken'],
            (int) $this->course->id,
            (int) $this->parent->id
        );

        $this->assertArrayHasKey(prototype_promotion::NAME_FIELD, $errors);
    }

    /**
     * A question with no prototype has nothing to be a child of, and is told so
     * rather than being offered the box.
     */
    public function test_a_question_with_no_prototype_cannot_promote(): void {
        $errors = prototype_promotion::validate(
            [prototype_promotion::NAME_FIELD => 'orphan'],
            (int) $this->course->id,
            null
        );

        $this->assertArrayHasKey(prototype_promotion::NAME_FIELD, $errors);
    }

    /**
     * The capability is asked on save as well as when the box is offered,
     * because a form's offer is not an authorisation.
     */
    public function test_an_author_without_the_capability_is_refused_on_save(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        $this->setUser($user);

        $this->assertFalse(
            (new prototype_candidates())->may_create_in_course((int) $this->course->id)
        );

        $errors = prototype_promotion::validate(
            [prototype_promotion::NAME_FIELD => 'sneaky'],
            (int) $this->course->id,
            (int) $this->parent->id
        );

        $this->assertArrayHasKey(prototype_promotion::NAME_FIELD, $errors);
    }

    /**
     * Only an administrator may put a prototype at site level, exactly as only
     * an administrator may edit one there.
     */
    public function test_only_an_administrator_may_create_a_site_prototype(): void {
        $this->assertTrue((new prototype_candidates())->may_create_in_course((int) SITEID));

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertFalse((new prototype_candidates())->may_create_in_course((int) SITEID));
    }

    /**
     * Pressing the button is a no-submit press, and is recognised as one even
     * in the states where the button is not drawn.
     *
     * This is the fault the picker's *From another course...* step produced:
     * the press arrives on a request that may take a different branch, and a
     * name missing from the form's no-submit list exactly when the button has
     * been pressed makes formslib take the press for an ordinary submission —
     * validating the question, running its sample answer and saving a version.
     * So the registration cannot depend on anything that differs between the
     * render and the press.
     */
    public function test_pressing_the_button_is_never_taken_for_a_save(): void {
        foreach ([true, false] as $withprototype) {
            $formdata = \test_question_maker::get_question_form_data('coderunner');
            $formdata->validateonsave = 0;
            \qtype_coderunner_test_helper::pin_every_field($formdata, $this->parent->id);
            if (!$withprototype) {
                $formdata->prototypeid = 0;
            }
            $formdata->category = "{$this->category->id},{$this->category->contextid}";
            $formdata->{prototype_promotion::BUTTON} = 'Make prototype from this question...';
            \qtype_coderunner_edit_form::mock_submit((array) $formdata);

            $form = \qtype_coderunner_test_helper::get_question_editing_form(
                $this->category,
                \test_question_maker::get_question_data('coderunner')
            );

            // Asserted as the registration rather than through
            // `no_submit_button_pressed()`, which memoises its answer in a
            // function static for the whole request — so in a test process it
            // returns whatever the first form to ask it found. The
            // registration is the invariant anyway: formslib recognises a
            // press by finding a registered name in the submission.
            $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
            $this->assertTrue(
                $mform->isNoSubmitButton(prototype_promotion::BUTTON),
                'The button is not registered with prototypeid '
                    . ($withprototype ? 'set' : 'absent')
                    . ', so formslib would take the press for a save.'
            );
        }
    }

    /**
     * Resolve a chain, as the run-time path does.
     *
     * @param int $prototypeid the prototype.
     * @param array $overrides the question's own overrides.
     * @return question_settings the resolved settings.
     */
    private function resolve(int $prototypeid, array $overrides): question_settings {
        return $this->repo->chain_for(
            $prototypeid,
            new override_set($overrides, \qtype_coderunner::QUESTION_LAYER)
        )->resolve();
    }

    /**
     * What a form with every field pinned overrides, as the question form would
     * build it.
     *
     * @return array field name => value.
     */
    private function overrides_of_a_pinned_form(): array {
        $saved = $this->submit([]);
        return json_decode($saved->overrides, true);
    }

    /**
     * How many prototypes exist.
     *
     * @return int the count.
     */
    private function count_prototypes(): int {
        global $DB;
        return $DB->count_records('local_coderunner_manager_prototypes');
    }
}
