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
 *
 * And that the two menus agree with each other — which course's library is
 * showing, and which prototype that scope can show as the selection — since
 * that is the part formslib does not do for itself.
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
                get_string('scopegroup_thiscourse', 'local_coderunner_manager'),
                get_string('scopegroup_site', 'local_coderunner_manager'),
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

        // The menus and the trail share one row, so the group is what the form
        // holds and the menus are inside it.
        $this->assertTrue($mform->elementExists(prototype_picker::GROUP));
        $this->assertNotNull($this->menu($mform));
        // Nothing to borrow from, so no course menu: one control, which is
        // what nearly every author sees.
        $this->assertNull($this->grouped($mform, prototype_picker::COURSE_FIELD));

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
        return $this->grouped($mform, prototype_picker::FIELD);
    }

    /**
     * One element of the picker's group, by name.
     *
     * By name and not by position: the course menu comes first where it is
     * drawn at all, so the prototype menu is the first element for one author
     * and the second for another.
     *
     * @param \MoodleQuickForm $mform the built form.
     * @param string $name the element wanted.
     * @return object|null the element, or null if the group has no such one.
     */
    private function grouped(\MoodleQuickForm $mform, string $name) {
        foreach ($mform->getElement(prototype_picker::GROUP)->getElements() as $element) {
            if ($element->getName() === $name) {
                return $element;
            }
        }
        return null;
    }

    /**
     * What a menu of the picker's group is showing as its selection.
     *
     * @param object $element the menu.
     * @return string the selected value, or '' for none.
     */
    private function selection($element): string {
        $values = method_exists($element, 'getSelected') ? $element->getSelected() : $element->getValue();
        $values = array_values((array) $values);
        return $values === [] ? '' : (string) $values[0];
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
     * The course menu is drawn when there is a course to choose, and not when
     * the only entry would be the question's own course.
     *
     * No step of its own to reach it: the entries are there from the first
     * build, which is what makes a borrowed prototype findable rather than
     * hidden behind a button whose effect is invisible.
     */
    public function test_the_course_menu_is_drawn_only_when_it_offers_a_choice(): void {
        $alone = $this->build_picker((int) $this->course->id);
        $this->assertNull(
            $this->grouped($alone, prototype_picker::COURSE_FIELD),
            'One scope is no choice of scope, so the menu would say nothing.'
        );

        $other = $this->getDataGenerator()->create_course(['shortname' => 'COSC122-26S1']);
        $this->make_prototype('theirs', (int) $other->id);

        $menu = $this->grouped($this->build_picker((int) $this->course->id), prototype_picker::COURSE_FIELD);
        $this->assertNotNull($menu);

        $courses = [];
        foreach ($menu->_options as $option) {
            $courses[(string) $option['attr']['value']] = $option['text'];
        }
        $this->assertSame(
            [
                (string) $this->course->id => get_string('scopethiscourse', 'local_coderunner_manager'),
                (string) $other->id => 'COSC122-26S1',
            ],
            $courses,
            "The question's own course first, named for its relation to the question; then the rest by name."
        );
    }

    /**
     * *Site* is not one of the course menu's entries: the built-ins are
     * site-scope, and a course that writes most of its questions directly on
     * python3 would otherwise be changing scope on the ordinary path.
     */
    public function test_the_site_is_not_a_scope_of_its_own(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->make_prototype('theirs', (int) $other->id);
        $sitewide = $this->make_prototype('python3');

        $menu = $this->grouped($this->build_picker((int) $this->course->id), prototype_picker::COURSE_FIELD);
        $courses = [];
        foreach ($menu->_options as $option) {
            $courses[] = (string) $option['attr']['value'];
        }

        $this->assertNotContains((string) SITEID, $courses);
        // And the site library is offered in the question's own scope instead.
        $this->assertContains((string) $sitewide->id, $this->menu_values($this->build_picker((int) $this->course->id)));
    }

    /**
     * Choosing a course **replaces** what the prototype menu holds.
     *
     * The author has just said which library they want; offering their own
     * course's prototypes alongside it would make them read past the list they
     * asked not to see, and appending a group to a closed menu is a change
     * with nothing visible about it.
     */
    public function test_a_chosen_course_replaces_the_list(): void {
        $other = $this->getDataGenerator()->create_course(['shortname' => 'COSC122-26S1']);
        $theirs = $this->make_prototype('scratchpad', (int) $other->id);
        $mine = $this->make_prototype('mine', (int) $this->course->id);
        $sitewide = $this->make_prototype('python3');

        $_POST[prototype_picker::COURSE_FIELD] = (string) $other->id;
        $values = $this->menu_values($this->build_picker((int) $this->course->id));

        $this->assertContains((string) $theirs->id, $values);
        $this->assertNotContains((string) $mine->id, $values);
        $this->assertNotContains((string) $sitewide->id, $values);
    }

    /**
     * One course's library is one group, so it renders as a plain menu with no
     * heading, and the names in it carry no course: the course menu beside it
     * is already saying which course this is.
     */
    public function test_a_chosen_courses_list_is_plain(): void {
        $other = $this->getDataGenerator()->create_course(['shortname' => 'COSC122-26S1']);
        $theirs = $this->make_prototype('scratchpad', (int) $other->id);

        $groups = prototype_picker::optgroups((int) $this->course->id, null, (int) $other->id);

        $this->assertCount(1, $groups);
        $options = reset($groups);
        $this->assertSame('scratchpad', $options[$theirs->id]);
    }

    /**
     * An existing question opens on the scope that holds its own prototype,
     * with the prototype selected, however the author got there.
     */
    public function test_the_scope_opens_on_the_questions_own_prototype(): void {
        $other = $this->getDataGenerator()->create_course(['shortname' => 'COSC122-26S1']);
        $theirs = $this->make_prototype('scratchpad', (int) $other->id);
        $this->make_prototype('mine', (int) $this->course->id);

        $mform = $this->build_picker((int) $this->course->id, $theirs->id);
        prototype_picker::settle_selection($mform, (int) $this->course->id, $theirs->id);

        $this->assertSame(
            (string) $other->id,
            $this->selection($this->grouped($mform, prototype_picker::COURSE_FIELD))
        );
        $this->assertSame((string) $theirs->id, $this->selection($this->menu($mform)));
    }

    /**
     * A question built on a prototype of a course the author may not browse
     * keeps it: the scope is reachable, and offers that one prototype.
     *
     * Without this an author with rights only in their own course could not
     * edit such a question at all — the menu would not hold the question's own
     * value, so saving the form would either re-point the question or fail the
     * picker's own required rule.
     */
    public function test_a_prototype_in_use_stays_reachable_without_the_capability(): void {
        $other = $this->getDataGenerator()->create_course(['shortname' => 'COSC122-26S1']);
        $theirs = $this->make_prototype('scratchpad', (int) $other->id);
        $this->make_prototype('also_theirs', (int) $other->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $mform = $this->build_picker((int) $this->course->id, $theirs->id);
        prototype_picker::settle_selection($mform, (int) $this->course->id, $theirs->id);

        $courses = [];
        foreach ($this->grouped($mform, prototype_picker::COURSE_FIELD)->_options as $option) {
            $courses[] = (string) $option['attr']['value'];
        }
        $this->assertContains((string) $other->id, $courses, 'The scope holding the value must be reachable.');
        $this->assertSame((string) $theirs->id, $this->selection($this->menu($mform)));
        $this->assertSame(
            ['', (string) $theirs->id],
            $this->menu_values($mform),
            'The placeholder and the prototype in use, and nothing else of that course.'
        );
    }

    /**
     * Nothing is preselected on a new question: not the first prototype, not
     * the most recently used, not anything. The author chooses.
     */
    public function test_a_new_question_starts_at_the_placeholder(): void {
        $this->make_prototype('python3');
        $this->make_prototype('mine', (int) $this->course->id);

        $mform = $this->build_picker((int) $this->course->id);
        prototype_picker::settle_selection($mform, (int) $this->course->id, null);

        $this->assertSame('', $this->selection($this->menu($mform)));
        $this->assertSame('', $this->menu_values($mform)[0], 'The placeholder is the first option.');
    }

    /**
     * While another course's library is showing, the prototype menu shows the
     * placeholder rather than a value the author cannot see in it; and coming
     * back to the scope that holds it shows it again.
     *
     * The second half is the one formslib would get wrong on its own: on a
     * no-submit reload it fills every element from a submission that is one
     * render out of date, which is the placeholder the author was shown while
     * they were away.
     */
    public function test_the_selection_follows_the_scope(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->make_prototype('theirs', (int) $other->id);
        $mine = $this->make_prototype('mine', (int) $this->course->id);

        // Away: the value is out of scope, so the menu offers the placeholder.
        $_POST[prototype_picker::COURSE_FIELD] = (string) $other->id;
        $away = $this->build_picker((int) $this->course->id, $mine->id);
        prototype_picker::settle_selection($away, (int) $this->course->id, $mine->id);
        $this->assertSame('', $this->selection($this->menu($away)));

        // Back, with the stale placeholder submitted as the prototype.
        $_POST[prototype_picker::COURSE_FIELD] = (string) $this->course->id;
        $_POST[prototype_picker::FIELD] = '';
        $back = $this->build_picker((int) $this->course->id, $mine->id);
        prototype_picker::settle_selection($back, (int) $this->course->id, $mine->id);
        $this->assertSame((string) $mine->id, $this->selection($this->menu($back)));
    }

    /**
     * The reload button is registered as a no-submit button in every state of
     * the picker, whether or not the form draws a course menu.
     *
     * Unconditionally, and that is the lesson of a bug with teeth: the press
     * arrives on a *different* request from the one that drew the button, and
     * a name missing from the form's no-submit list exactly when the button
     * has been pressed makes formslib take the press for an ordinary
     * submission — it validated the question, ran the sample answer against
     * the sandbox, saved a new version and redirected as a successful save
     * does.
     *
     * Asserted through `isNoSubmitButton()` rather than
     * `no_submit_button_pressed()`: that method caches its answer in a static,
     * which lasts for the whole PHPUnit process rather than for one request,
     * so the first test to call it would decide the answer for every test
     * after it.
     */
    public function test_the_reload_button_is_registered_in_every_state(): void {
        // With one scope, where no course menu is drawn.
        $alone = $this->build_picker((int) $this->course->id);
        $this->assertTrue($alone->isNoSubmitButton(prototype_picker::RELOAD_BUTTON));

        // With a course to borrow from, where one is.
        $other = $this->getDataGenerator()->create_course();
        $this->make_prototype('theirs', (int) $other->id);
        $withscopes = $this->build_picker((int) $this->course->id);
        $this->assertNotNull($this->grouped($withscopes, prototype_picker::COURSE_FIELD));
        $this->assertTrue($withscopes->isNoSubmitButton(prototype_picker::RELOAD_BUTTON));

        // And while another course's library is showing.
        $_POST[prototype_picker::COURSE_FIELD] = (string) $other->id;
        $chosen = $this->build_picker((int) $this->course->id);
        $this->assertTrue($chosen->isNoSubmitButton(prototype_picker::RELOAD_BUTTON));
    }

    /**
     * The reload button marks itself as skipping the form's client-side
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
        $button = $mform->getElement(prototype_picker::RELOAD_BUTTON);

        $this->assertSame(
            1,
            $button->getAttribute('data-no-submit'),
            'The reload button was registered after it was created, so it does not skip client validation.'
        );
        $this->assertStringContainsString(
            'skipClientValidation',
            (string) $button->getAttribute('onclick'),
            'The reload button does not set skipClientValidation.'
        );
    }
}
