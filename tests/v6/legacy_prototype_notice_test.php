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
 * Unit tests for what an author sees on opening a CodeRunner 5 prototype question.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_candidates;
use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * A legacy prototype question is a dead artifact, and the form says so.
 *
 * What is asserted beyond the wording is that the page cannot write: the
 * settings sections are not built, because they have nothing true to show for
 * such a question, and a save would therefore write their absence into the
 * question's own columns and erase what the migration was careful to leave.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(legacy_prototype_notice::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class legacy_prototype_notice_test extends \advanced_testcase {
    /** @var prototype_repository prototype storage. */
    private prototype_repository $repo;

    /** @var \stdClass the course whose bank holds the question. */
    private \stdClass $course;

    /** @var \stdClass the question category. */
    private \stdClass $category;

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
        prototype_candidates::reset_caches();
    }

    /**
     * The banner names the prototype and links to it where the author may open
     * it.
     */
    public function test_the_banner_links_to_the_prototype_it_became(): void {
        $prototype = $this->repo->create(
            'cosc131_legacy',
            (int) $this->course->id,
            new override_set(['template' => '{{ STUDENT_ANSWER }}'], 'cosc131_legacy')
        );

        $body = legacy_prototype_notice::body('cosc131_legacy', (int) $this->course->id);

        $this->assertStringContainsString('cosc131_legacy', $body);
        $this->assertStringContainsString(
            '/local/coderunner_manager/edit.php?id=' . $prototype->id,
            $body
        );
    }

    /**
     * A prototype the author may not edit is named without a link, because an
     * author who cannot follow it is better told where to ask than shown a page
     * that refuses them.
     */
    public function test_a_prototype_the_author_may_not_edit_is_not_linked(): void {
        $this->repo->create(
            'site_legacy',
            (int) SITEID,
            new override_set(['template' => '{{ STUDENT_ANSWER }}'], 'site_legacy')
        );

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $body = legacy_prototype_notice::body('site_legacy', (int) $this->course->id);

        $this->assertStringContainsString('site_legacy', $body);
        $this->assertStringNotContainsString('edit.php', $body);
    }

    /**
     * A name with no prototype behind it says so, rather than linking nowhere.
     * The conversion runs on cron, so an author can reach this page first.
     */
    public function test_a_name_with_no_prototype_says_so(): void {
        $body = legacy_prototype_notice::body('never_converted', (int) $this->course->id);

        $this->assertStringContainsString('never_converted', $body);
        $this->assertStringNotContainsString('edit.php', $body);
    }

    /**
     * A prototype name is author-supplied text and reaches the page as markup,
     * so it is escaped.
     */
    public function test_the_name_is_escaped(): void {
        $body = legacy_prototype_notice::body('<script>alert(1)</script>', (int) $this->course->id);

        $this->assertStringNotContainsString('<script>', $body);
    }

    /**
     * The form for such a question is the banner and nothing else: no picker,
     * no settings sections, no test cases.
     */
    public function test_the_form_is_the_banner_and_nothing_else(): void {
        $mform = $this->build_form();

        $this->assertTrue($mform->elementExists(legacy_prototype_notice::ELEMENT));
        $this->assertFalse($mform->elementExists(prototype_picker::GROUP), 'The picker has nothing to offer.');
        $this->assertFalse($mform->elementExists('answer'));
        $this->assertFalse($mform->elementExists('datafiles'));
        foreach (\qtype_coderunner_edit_form::sections() as $section) {
            $this->assertFalse(
                $mform->elementExists('section_' . $section->value),
                "The {$section->value} section would show the registry's defaults, not the question's."
            );
        }
    }

    /**
     * And the controls that remain are frozen, so the page reads as a view
     * rather than an invitation.
     */
    public function test_the_remaining_controls_are_frozen(): void {
        $mform = $this->build_form();

        $this->assertTrue($mform->getElement('name')->isFrozen(), 'The question name is editable.');
    }

    /**
     * Saving is refused, because a frozen control is not submitted rather than
     * defended: the save would write nothing over the question's own columns.
     */
    public function test_saving_is_refused(): void {
        $form = $this->form();
        $errors = $form->validation([
            'name' => 'anything',
            'category' => "{$this->category->id},{$this->category->contextid}",
        ], []);

        $this->assertArrayHasKey(legacy_prototype_notice::ELEMENT, $errors);
    }

    /**
     * An ordinary question is untouched by any of this.
     */
    public function test_an_ordinary_question_gets_no_banner(): void {
        $this->repo->create(
            'python3',
            (int) SITEID,
            new override_set(['template' => '{{ STUDENT_ANSWER }}'], 'python3')
        );
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('coderunner', 'sqr', [
                'category' => $this->category->id,
                'coderunnertype' => 'python3',
            ]);
        prototype_candidates::reset_caches();

        $mform = $this->quickform($this->form(\question_bank::load_question_data($question->id)));

        $this->assertFalse($mform->elementExists(legacy_prototype_notice::ELEMENT));
        $this->assertTrue($mform->elementExists(prototype_picker::GROUP));
    }

    /**
     * The question form for a legacy prototype question.
     *
     * @return \MoodleQuickForm the inner form.
     */
    private function build_form(): \MoodleQuickForm {
        return $this->quickform($this->form());
    }

    /**
     * Build the question editing form.
     *
     * @param \stdClass|null $questiondata the question, or null for a legacy
     *      prototype question made for the purpose.
     * @return \qtype_coderunner_edit_form the form.
     */
    private function form(?\stdClass $questiondata = null): \qtype_coderunner_edit_form {
        $questiondata ??= $this->make_legacy_prototype_question();
        $catcontext = \context::instance_by_id($this->category->contextid);
        $contexts = new \core_question\local\bank\question_edit_contexts($catcontext);

        $questiondata->contextid = $catcontext->id;
        $questiondata->beingcopied = false;
        $questiondata->category = $this->category->id;
        $questiondata->formoptions = (object) [
            'canmove' => true,
            'cansaveasnew' => true,
            'canedit' => true,
            'repeatelements' => true,
        ];

        return new \qtype_coderunner_edit_form(
            'question.php',
            $questiondata,
            $this->category,
            $contexts,
            true
        );
    }

    /**
     * A question record in the state CodeRunner 5 left a prototype question in.
     *
     * Built by hand rather than saved through the question type, because
     * creating one is exactly what CodeRunner 6 no longer does.
     *
     * @return \stdClass the question data.
     */
    private function make_legacy_prototype_question(): \stdClass {
        return (object) [
            'createdby' => 2,
            'qtype' => 'coderunner',
            'name' => 'PROTOTYPE_cosc131_legacy',
            'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
            'options' => (object) [
                'coderunnertype' => 'cosc131_legacy',
                'prototypetype' => 2,
                'prototypeid' => null,
                'overrides' => null,
                'testcases' => [],
            ],
        ];
    }

    /**
     * The MoodleQuickForm inside a form.
     *
     * @param \moodleform $form the form.
     * @return \MoodleQuickForm the inner form.
     */
    private function quickform(\moodleform $form): \MoodleQuickForm {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }
}
