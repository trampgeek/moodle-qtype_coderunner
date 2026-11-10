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
 * Unit tests for the question form's prototype picker.
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

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Tests that the picker renders what the manager's candidate list produces:
 * the groups as headings, the tree as indentation, the chain as a breadcrumb,
 * and the capability as a refusal.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prototype_picker::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class prototype_picker_test extends \advanced_testcase {
    /** @var prototype_repository prototype storage. */
    private prototype_repository $repo;

    /** @var \stdClass the course the question belongs to. */
    private \stdClass $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->repo = new prototype_repository();
        $this->course = $this->getDataGenerator()->create_course();
        prototype_candidates::reset_caches();
    }

    /**
     * Create a prototype.
     *
     * @param string $name its name.
     * @param int|null $courseid its scope, defaulting to the site.
     * @param int|null $parentid its parent, if any.
     * @param string $summary its one-line summary.
     * @return prototype the prototype.
     */
    private function make_prototype(
        string $name,
        ?int $courseid = null,
        ?int $parentid = null,
        string $summary = ''
    ): prototype {
        return $this->repo->create(
            $name,
            $courseid ?? (int) SITEID,
            new override_set(['template' => 'T'], $name),
            $parentid,
            $summary
        );
    }

    /**
     * The picker's options for a question in the test course.
     *
     * @param int|null $selectedid the prototype currently chosen.
     * @return array the option list.
     */
    private function optgroups(?int $selectedid = null): array {
        return prototype_picker::optgroups((int) $this->course->id, $selectedid);
    }

    /**
     * Every option offered, flattened across the groups.
     *
     * @param int|null $selectedid the prototype currently chosen.
     * @return array prototype id => label.
     */
    private function all_options(?int $selectedid = null): array {
        $flat = [];
        foreach ($this->optgroups($selectedid) as $options) {
            $flat += $options;
        }
        return $flat;
    }

    /**
     * The groups are real option groups, keyed by their heading.
     *
     * An `autocomplete` could not have them — it rebuilds its suggestions from
     * the select's direct children, so anything inside an `optgroup` vanishes
     * once its JavaScript loads — which is part of why the picker is a
     * `selectgroups`, as Moodle's own question category selector is.
     */
    public function test_the_groups_are_real_option_groups(): void {
        $this->make_prototype('python3');
        $this->make_prototype('cosc131_python3', (int) $this->course->id);

        $groups = $this->optgroups();

        $this->assertSame(
            [
                get_string('prototypegroup_thiscourse', 'qtype_coderunner'),
                get_string('prototypegroup_site', 'qtype_coderunner'),
            ],
            array_keys($groups),
            "The course's own prototypes come first, then the site's."
        );
    }

    /**
     * A course with nothing of its own gets a plain menu rather than a grouped
     * one, because a single heading is a row that partitions nothing.
     *
     * Most sites are expected to look like this for a long while: the
     * built-ins and no course prototypes at all.
     */
    public function test_one_group_means_a_plain_menu(): void {
        $this->make_prototype('python3');

        $mform = $this->build_picker((int) $this->course->id);
        $this->assertSame('select', $this->menu($mform)->getType());

        // And with a prototype of its own, the menu is grouped again.
        $this->make_prototype('cosc131_python3', (int) $this->course->id);
        prototype_candidates::reset_caches();
        $grouped = $this->build_picker((int) $this->course->id);
        $this->assertSame('selectgroups', $this->menu($grouped)->getType());
    }

    /**
     * A prototype is indented by its depth, so the shape of the library shows
     * in a plain select.
     */
    public function test_a_prototype_is_indented_by_its_depth(): void {
        $parent = $this->make_prototype('python3');
        $child = $this->make_prototype('python3_scratchpad', null, $parent->id);

        $options = $this->all_options();

        $this->assertSame(0, substr_count($options[$parent->id], '&nbsp;'));
        $this->assertSame(4, substr_count($options[$child->id], '&nbsp;'));
    }

    /**
     * A prototype's name is escaped.
     *
     * It has to be: HTML_QuickForm writes an option's text into the select
     * unescaped, and core's autocomplete takes that option's inner HTML and
     * renders it as HTML in the suggestion list. A name is author-supplied
     * text, stored as given.
     */
    public function test_a_name_is_escaped(): void {
        $prototype = $this->make_prototype('py<script>alert(1)</script>');

        $all = implode('', $this->all_options());

        $this->assertStringNotContainsString('<script>', $all);
        $this->assertStringContainsString('&lt;script&gt;', $all);
        $this->assertNotEmpty($prototype->id);
    }

    /**
     * The breadcrumb names every layer of the chosen prototype's chain, and
     * shows its summary.
     */
    public function test_the_breadcrumb_names_the_chain(): void {
        $root = $this->make_prototype('python3');
        $middle = $this->make_prototype('python3_scratchpad', null, $root->id);
        $leaf = $this->make_prototype(
            'cosc131_scratchpad',
            (int) $this->course->id,
            $middle->id,
            'Scratchpad with the COSC131 wrapper.'
        );

        $breadcrumb = prototype_picker::breadcrumb($leaf->id);

        $this->assertStringContainsString('python3 › python3_scratchpad › cosc131_scratchpad', $breadcrumb);
        // The summary is its own line beside the menu's row, not part of the
        // trail: it is a sentence, and the trail is a path.
        $this->assertStringNotContainsString('Scratchpad with the COSC131 wrapper.', $breadcrumb);
        $this->assertStringContainsString(
            'Scratchpad with the COSC131 wrapper.',
            prototype_picker::summary($leaf->id)
        );
    }

    /**
     * Site defaults is left out of the trail, and a trail that would say only
     * what the menu already says is left out altogether.
     *
     * Every prototype descends from Site defaults, so naming it tells an author
     * nothing they could act on; and once it is gone, a prototype whose parent
     * is Site defaults has nothing left to show.
     */
    public function test_site_defaults_is_left_out_of_the_trail(): void {
        $sitedefaults = $this->repo->site_defaults()
            ?? $this->repo->create('Site defaults', (int) SITEID, new override_set([], 'Site defaults'),
                null, '', '', false, '', true);
        $child = $this->make_prototype('python3', null, $sitedefaults->id);
        $grandchild = $this->make_prototype('python3_scratchpad', null, $child->id);

        // Directly under Site defaults: nothing worth saying.
        $this->assertSame('', prototype_picker::breadcrumb($child->id));

        // One step further down, the trail names the prototype between them and
        // never Site defaults itself.
        $trail = prototype_picker::breadcrumb($grandchild->id);
        $this->assertStringContainsString('python3 › python3_scratchpad', $trail);
        $this->assertStringNotContainsString('Site defaults', $trail);
    }

    /**
     * With nothing chosen there is nothing to say, and a prototype that has
     * gone says nothing either: the question reports itself broken elsewhere,
     * and the picker does not need to say it twice.
     */
    public function test_the_breadcrumb_is_empty_when_there_is_nothing_to_show(): void {
        $this->assertSame('', prototype_picker::breadcrumb(null));
        $this->assertSame('', prototype_picker::breadcrumb(999999));
    }

    /**
     * A question must be built on something.
     */
    public function test_validation_requires_a_prototype(): void {
        $this->assertNotNull(prototype_picker::validation_error(null));
        $this->assertNotNull(prototype_picker::validation_error(0));
    }

    /**
     * Pointing a question at another course's prototype needs the management
     * capability in that course, and the check is in the code rather than only
     * in the list the picker offers.
     */
    public function test_validation_refuses_another_courses_prototype(): void {
        $other = $this->getDataGenerator()->create_course();
        $theirs = $this->make_prototype('theirs', (int) $other->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertNotNull(prototype_picker::validation_error($theirs->id));
        // But the question may keep a reference it already has.
        $this->assertNull(prototype_picker::validation_error($theirs->id, $theirs->id));
    }

    /**
     * A prototype of the author's own course, and a site one, are allowed.
     */
    public function test_validation_allows_what_is_in_scope(): void {
        $mine = $this->make_prototype('mine', (int) $this->course->id);
        $sitewide = $this->make_prototype('python3');

        $this->assertNull(prototype_picker::validation_error($mine->id));
        $this->assertNull(prototype_picker::validation_error($sitewide->id));
    }

    /**
     * The picker builds on a real form.
     *
     * Building it is where a missing language string, a help button pointing at
     * nothing, or an element type MoodleQuickForm does not have would show up,
     * and all of those happen at page-view time rather than at save time.
     */
    public function test_the_picker_builds_on_a_form(): void {
        $prototype = $this->make_prototype('python3');
        $courseid = (int) $this->course->id;

        $mform = $this->build_picker($courseid, $prototype->id);

        // The menu and the trail share one row, so the group is what the form
        // holds and the menu is inside it.
        $this->assertTrue($mform->elementExists(prototype_picker::GROUP));
        // The first step: a button, and no course menu until it is pressed.
        $this->assertTrue($mform->elementExists(prototype_picker::OTHER_COURSE_BUTTON));
        $this->assertFalse($mform->elementExists(prototype_picker::COURSE_FIELD));
        $this->assertTrue($mform->isNoSubmitButton(prototype_picker::OTHER_COURSE_BUTTON));

        // And the prototype is among the options offered.
        $this->assertContains((string) $prototype->id, $this->menu_values($mform));
    }

    /**
     * The values the menu offers, whichever element it is.
     *
     * A course with prototypes of its own gets a grouped menu and one without
     * gets a plain one, so a test that reads the options has to cope with
     * both.
     *
     * @param \MoodleQuickForm $mform the built form.
     * @return string[] the option values.
     */
    private function menu_values(\MoodleQuickForm $mform): array {
        $menu = $this->menu($mform);
        $values = [];
        if ($menu->getType() === 'selectgroups') {
            foreach ($menu->_optGroups as $group) {
                foreach ($group['options'] as $option) {
                    $values[] = (string) $option['attr']['value'];
                }
            }
            return $values;
        }

        foreach ($menu->_options as $option) {
            $values[] = (string) $option['attr']['value'];
        }
        return $values;
    }

    /**
     * The menu element itself, which lives inside the group that carries the
     * label and the chain rather than at the top level of the form.
     *
     * @param \MoodleQuickForm $mform the built form.
     * @return object the menu element.
     */
    private function menu(\MoodleQuickForm $mform) {
        [$menu] = $mform->getElement(prototype_picker::GROUP)->getElements();
        return $menu;
    }

    /**
     * Build a form carrying just the picker.
     *
     * @param int $courseid the question's course.
     * @param int|null $selectedid the prototype currently chosen.
     * @return \MoodleQuickForm the built form.
     */
    private function build_picker(int $courseid, ?int $selectedid = null): \MoodleQuickForm {
        $form = new class (null, ['courseid' => $courseid, 'selectedid' => $selectedid]) extends \moodleform {
            /**
             * Just the picker, which is what is under test.
             */
            protected function definition() {
                prototype_picker::add_to_form(
                    $this->_form,
                    $this->_customdata['courseid'],
                    $this->_customdata['selectedid']
                );
            }
        };

        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * Pressing *From another course…* brings up the course menu, and the one
     * query that has to resolve a capability over every course runs only then.
     */
    public function test_the_course_menu_appears_once_it_is_asked_for(): void {
        $other = $this->getDataGenerator()->create_course(['shortname' => 'COSC122-26S1']);
        $this->make_prototype('theirs', (int) $other->id);

        $_POST[prototype_picker::OTHER_COURSE_BUTTON] = 'Use a prototype from another course';

        $mform = $this->build_picker((int) $this->course->id);

        $this->assertTrue($mform->elementExists(prototype_picker::COURSE_FIELD));
        // And it is remembered, so the menu does not vanish on the next
        // submission of the form.
        $this->assertTrue($mform->elementExists(prototype_picker::OTHER_COURSE_WANTED));

        $courses = [];
        foreach ($mform->getElement(prototype_picker::COURSE_FIELD)->_options as $option) {
            $courses[] = (string) $option['attr']['value'];
        }
        $this->assertContains((string) $other->id, $courses);
    }

    /**
     * Once a course has been chosen, its prototypes are offered as a further
     * group, labelled with the course so that two of the same name stay
     * distinguishable.
     */
    public function test_the_chosen_courses_prototypes_become_a_group(): void {
        $other = $this->getDataGenerator()->create_course(['shortname' => 'COSC122-26S1']);
        $theirs = $this->make_prototype('scratchpad', (int) $other->id);

        $_POST[prototype_picker::COURSE_FIELD] = (string) $other->id;

        $groups = prototype_picker::optgroups((int) $this->course->id, null, (int) $other->id);
        $heading = get_string('prototypegroup_othercourse', 'qtype_coderunner');

        $this->assertArrayHasKey($heading, $groups);
        $this->assertArrayHasKey($theirs->id, $groups[$heading]);
        $this->assertStringContainsString('scratchpad (COSC122-26S1)', $groups[$heading][$theirs->id]);
    }

    /**
     * An author with no other course to borrow from is told so, rather than
     * being given an empty menu — and the press that got them there is still a
     * no-submit press.
     *
     * The second assertion is the one with teeth, and this test was here
     * without it while the bug it describes was live. Pressing the button
     * saved the question: the press arrives on a request that takes the
     * second branch of the step, that branch returned early for an author
     * with nowhere to borrow from, and the early return skipped the
     * registration — so formslib saw an ordinary submission, validated the
     * question, ran the sample answer against the sandbox and saved a new
     * version. The registration is unconditional now.
     */
    public function test_an_author_with_nowhere_to_borrow_from_is_told(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $_POST[prototype_picker::OTHER_COURSE_BUTTON] = 'x';

        $mform = $this->build_picker((int) $this->course->id);

        $this->assertFalse($mform->elementExists(prototype_picker::COURSE_FIELD));
        $this->assertTrue($mform->elementExists(prototype_picker::COURSE_FIELD . '_none'));
        $this->assertTrue(
            $mform->isNoSubmitButton(prototype_picker::OTHER_COURSE_BUTTON),
            'The press that asked for another course was not recorded as a no-submit press, '
                . 'so formslib would save the question instead of rebuilding the form.'
        );
    }

    /**
     * Every state of the step keeps the button registered, whether or not it
     * renders one, because the press always arrives on a later request than
     * the render.
     *
     * Asserted through `isNoSubmitButton()` rather than
     * `no_submit_button_pressed()`: that method caches its answer in a static,
     * which lasts for the whole PHPUnit process rather than for one request,
     * so the first test to call it would decide the answer for every test
     * after it.
     */
    public function test_the_button_is_registered_in_every_state(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->make_prototype('theirs', (int) $other->id);

        // Before it has been asked for, which is when the button is rendered.
        $fresh = $this->build_picker((int) $this->course->id);
        $this->assertTrue($fresh->isNoSubmitButton(prototype_picker::OTHER_COURSE_BUTTON));

        // On the press, where the course menu replaces the button.
        $_POST[prototype_picker::OTHER_COURSE_BUTTON] = 'x';
        $pressed = $this->build_picker((int) $this->course->id);
        $this->assertTrue($pressed->elementExists(prototype_picker::COURSE_FIELD));
        $this->assertTrue($pressed->isNoSubmitButton(prototype_picker::OTHER_COURSE_BUTTON));

        // And on the second press, which asks for a course's prototypes.
        $_POST[prototype_picker::COURSE_FIELD] = (int) $other->id;
        $chosen = $this->build_picker((int) $this->course->id);
        $this->assertTrue($chosen->isNoSubmitButton(prototype_picker::OTHER_COURSE_BUTTON));
    }

    /**
     * Each no-submit button marks itself as skipping the form's client-side
     * validation, which it can only do if it was registered before it was
     * created.
     *
     * `MoodleQuickForm_submit` asks `isNoSubmitButton()` as it is built
     * (`lib/form/submit.php`) and adds `data-skip-validation`,
     * `data-no-submit` and an `onclick` that sets `skipClientValidation`.
     * Registered the other way round the button still works on the server,
     * but pressing it runs the validation of every rule on the form — and the
     * picker itself carries a required rule.
     */
    public function test_a_no_submit_button_skips_client_validation(): void {
        $mform = $this->build_picker((int) $this->course->id);

        foreach ([prototype_picker::RELOAD_BUTTON, prototype_picker::OTHER_COURSE_BUTTON] as $name) {
            $button = $mform->getElement($name);
            $this->assertSame(
                1,
                $button->getAttribute('data-no-submit'),
                "$name was registered after it was created, so it does not skip client validation."
            );
            $this->assertStringContainsString(
                'skipClientValidation',
                (string) $button->getAttribute('onclick'),
                "$name does not set skipClientValidation."
            );
        }
    }
}
