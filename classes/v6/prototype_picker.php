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
 * The question form's prototype picker.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_candidates;
use local_coderunner_manager\prototype_repository;

/**
 * The control by which a question author chooses the prototype their question
 * is built on, replacing CodeRunner 5's *Question type* menu.
 *
 * The scope and ordering rules belong to the manager
 * ({@see prototype_candidates}); what is here is the rendering, which is the
 * question type's (design section 5).
 *
 * **A `selectgroups`, which is what Moodle's own question category selector
 * is.** The design asked for an `autocomplete`, for its filter box. Built that
 * way it read badly: `autocomplete` puts the current selection in a panel of
 * its own *above* a search box, where every other selector in Moodle puts the
 * menu beside its label with the current value showing in it. It also has no
 * option groups — `form-autocomplete.js` rebuilds its suggestions from the
 * select's direct children, so anything inside an `optgroup` vanishes once the
 * JavaScript loads — which forced the groups to be faked with disabled
 * options. `MoodleQuickForm_questioncategory` extends `selectgroups`, so
 * following it gives real option groups, the conventional layout, and a native
 * select's keyboard type-ahead in place of the filter box. If the library
 * grows past what type-ahead can cope with, that is the point to revisit it.
 *
 * Option text is written into the select unescaped, by `selectgroups` exactly
 * as by `select`, so the indentation that shows the tree works and every label
 * has to be escaped here: a prototype's name is author-supplied text.
 *
 * **Another course takes two steps, and neither needs JavaScript.** A
 * no-submit button reveals a course menu, and choosing a course adds that
 * course's prototypes as a further group. The two steps are what the design
 * asks for, and they also keep the one expensive query — which courses the
 * author may manage — from running on every load of the question form.
 */
class prototype_picker {
    /** @var string The field holding the chosen prototype, which is the column it is saved to. */
    const FIELD = 'prototypeid';

    /**
     * The form group holding the menu and the chain beside it.
     *
     * Errors and the help button anchor here rather than on the menu, because
     * formslib shows both against the group when an element is inside one.
     */
    const GROUP = 'prototypegroup';

    /** @var string The field holding the other course whose prototypes are being offered. */
    const COURSE_FIELD = 'pickercourseid';

    /** @var string The no-submit button that asks for another course. */
    const OTHER_COURSE_BUTTON = 'pickerothercourse';

    /** @var string Remembers, across submissions, that the author asked for another course. */
    const OTHER_COURSE_WANTED = 'pickerothercoursewanted';

    /**
     * Add the picker, the second step that reaches another course, and the
     * breadcrumb that says what the current choice inherits from.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     */
    public static function add_to_form(\MoodleQuickForm $mform, int $courseid, ?int $selectedid = null): void {
        $othercourseid = self::other_course_chosen();

        // The menu and the chain the choice inherits through, on one row: the
        // menu where every Moodle selector puts it, and the chain beside the
        // value it describes rather than in a panel of its own.
        $menu = $mform->createElement(
            'selectgroups',
            self::FIELD,
            // No label of its own: the group carries it, and an element that
            // repeats it renders the words a second time, above the menu.
            '',
            self::optgroups($courseid, $selectedid, $othercourseid),
            null,
            true
        );
        $chain = $mform->createElement('static', self::FIELD . '_chain', '', self::breadcrumb($selectedid));

        $mform->addGroup(
            [$menu, $chain],
            self::GROUP,
            get_string('coderunnertype', 'qtype_coderunner'),
            '&nbsp;&nbsp;',
            false
        );
        $mform->setType(self::FIELD, PARAM_INT);
        $mform->addRule(self::GROUP, get_string('prototyperequired', 'qtype_coderunner'), 'required', null, 'client');
        $mform->addHelpButton(self::GROUP, 'coderunnertype', 'qtype_coderunner');

        $summary = self::summary($selectedid);
        if ($summary !== '') {
            $mform->addElement('static', self::FIELD . '_summary', '', $summary);
        }

        self::add_other_course_step($mform, $courseid, $othercourseid);
    }

    /**
     * The second step: a button that asks for another course, and once it has
     * been pressed, the menu of courses the author may borrow from.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param int $courseid the question's own course.
     * @param int $othercourseid the course already chosen, or 0.
     */
    protected static function add_other_course_step(
        \MoodleQuickForm $mform,
        int $courseid,
        int $othercourseid
    ): void {
        if (!self::other_course_wanted()) {
            $mform->addElement(
                'submit',
                self::OTHER_COURSE_BUTTON,
                get_string('prototypefromanothercourse', 'qtype_coderunner'),
                ['class' => 'btn-secondary']
            );
            $mform->registerNoSubmitButton(self::OTHER_COURSE_BUTTON);
            return;
        }

        // Remembered so that the menu stays once it has been asked for, rather
        // than disappearing on the next submission.
        $mform->addElement('hidden', self::OTHER_COURSE_WANTED, 1);
        $mform->setType(self::OTHER_COURSE_WANTED, PARAM_INT);

        $courses = (new prototype_candidates())->borrowable_courses($courseid);
        if (!$courses) {
            $mform->addElement(
                'static',
                self::COURSE_FIELD . '_none',
                get_string('prototypeothercourse', 'qtype_coderunner'),
                get_string('prototypenoothercourses', 'qtype_coderunner')
            );
            return;
        }

        $mform->addElement(
            'autocomplete',
            self::COURSE_FIELD,
            get_string('prototypeothercourse', 'qtype_coderunner'),
            [0 => get_string('choosedots')] + $courses,
            ['noselectionstring' => get_string('choosedots')]
        );
        $mform->setType(self::COURSE_FIELD, PARAM_INT);
        $mform->setDefault(self::COURSE_FIELD, $othercourseid);
        $mform->addHelpButton(self::COURSE_FIELD, 'prototypeothercourse', 'qtype_coderunner');

        $mform->addElement(
            'submit',
            self::OTHER_COURSE_BUTTON,
            get_string('prototypeshowcourse', 'qtype_coderunner'),
            ['class' => 'btn-secondary']
        );
        $mform->registerNoSubmitButton(self::OTHER_COURSE_BUTTON);
    }

    /**
     * The picker's option groups, each holding its prototypes in tree order,
     * indented by their depth.
     *
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     * @param int $othercourseid another course whose prototypes to add, or 0.
     * @return array group heading => [prototype id => label].
     */
    public static function optgroups(int $courseid, ?int $selectedid = null, int $othercourseid = 0): array {
        $candidates = new prototype_candidates();
        $groups = $candidates->groups_for_course($courseid, $selectedid);

        if ($othercourseid && $othercourseid !== $courseid) {
            $othergroup = $candidates->for_other_course($othercourseid);
            if ($othergroup) {
                $groups[] = $othergroup;
            }
        }

        $optgroups = [];
        foreach ($groups as $group) {
            $heading = get_string('prototypegroup_' . $group['key'], 'qtype_coderunner');
            $optgroups[$heading] = [];
            foreach ($group['options'] as $id => $option) {
                $optgroups[$heading][$id] =
                    str_repeat('&nbsp;', 4 * $option['depth']) . s($option['label']);
            }
        }
        return $optgroups;
    }

    /**
     * What the chosen prototype inherits through, as a trail of names.
     *
     * **Site defaults is left out, and so is a trail that says nothing.**
     * Every prototype descends from Site defaults, so naming it tells an
     * author nothing they could act on; and once it is gone, a prototype whose
     * parent *is* Site defaults leaves a trail of one name — the name already
     * showing in the menu beside it — so there is nothing to show at all. What
     * survives is the case the trail is for: a prototype built on another
     * prototype, where the author wants to know which one.
     *
     * The trail is shown once, beside the menu, rather than on each row of it:
     * a chain repeated against every descendant is the same ancestors over and
     * over, and the full tree belongs in the prototype library, where browsing
     * the structure is the task.
     *
     * @param int|null $prototypeid the chosen prototype.
     * @return string the rendered trail, or an empty string if there is
     *      nothing worth saying.
     */
    public static function breadcrumb(?int $prototypeid): string {
        $chain = self::chain_of($prototypeid);
        if (count($chain) < 2) {
            return '';
        }

        $names = [];
        foreach ($chain as $prototype) {
            $names[] = s($prototype->name);
        }

        return \html_writer::tag(
            'small',
            implode(' › ', $names),
            ['class' => 'text-muted']
        );
    }

    /**
     * The chosen prototype's one-line summary.
     *
     * On its own row rather than beside the menu: it is a sentence, and the row
     * beside the menu is already carrying the trail. The section is one panel,
     * so a second row there does not read as a second thing.
     *
     * @param int|null $prototypeid the chosen prototype.
     * @return string the rendered summary, or an empty string if it has none.
     */
    public static function summary(?int $prototypeid): string {
        $chain = self::chain_of($prototypeid);
        if (!$chain) {
            return '';
        }

        $last = end($chain);
        $summary = trim($last->currentversion->summary ?? '');

        return $summary === ''
            ? ''
            : \html_writer::tag('small', s($summary), ['class' => 'text-muted']);
    }

    /**
     * The chosen prototype's chain, root first, without Site defaults.
     *
     * @param int|null $prototypeid the chosen prototype.
     * @return \local_coderunner_manager\prototype[] the chain, or an empty
     *      array if it cannot be read.
     */
    protected static function chain_of(?int $prototypeid): array {
        if (!$prototypeid) {
            return [];
        }

        try {
            $chain = (new prototype_repository())->chain_prototypes($prototypeid);
        } catch (\Exception $e) {
            // A prototype that has been deleted, or whose parent links form a
            // cycle. The question is broken and says so elsewhere; the picker
            // does not need to say it twice.
            return [];
        }

        return array_values(array_filter($chain, fn($prototype) => !$prototype->issitedefaults));
    }

    /**
     * Why the chosen prototype may not be used, if it may not.
     *
     * Called from the form's validation and again when the question is saved,
     * because a form can be submitted without having been built — a question
     * restored, imported or edited by a script — and the capability is the
     * thing standing between an author and another course's library.
     *
     * @param int|null $prototypeid the prototype chosen.
     * @param int|null $previousid the prototype the question used before, which
     *      it may always keep; see prototype_candidates::may_choose().
     * @return string|null the error to show, or null if the choice is allowed.
     */
    public static function validation_error(?int $prototypeid, ?int $previousid = null): ?string {
        if (empty($prototypeid)) {
            return get_string('prototyperequired', 'qtype_coderunner');
        }

        $candidates = new prototype_candidates();
        if (!$candidates->may_choose($prototypeid, $previousid)) {
            return get_string('prototypenotallowed', 'qtype_coderunner');
        }

        return null;
    }

    /**
     * The other course whose prototypes the author has asked to see.
     *
     * Read from the request rather than from the form's data, because the
     * options have to be in place before formslib will accept the submitted
     * value, which is to say before the form's data can be read at all. This is
     * the same reason a repeated element's count is read this way.
     *
     * @return int the course id, or 0 if none has been chosen.
     */
    public static function other_course_chosen(): int {
        return optional_param(self::COURSE_FIELD, 0, PARAM_INT);
    }

    /**
     * Whether the author has asked for another course's prototypes.
     *
     * @return bool true once the button has been pressed, and thereafter.
     */
    public static function other_course_wanted(): bool {
        return optional_param(self::OTHER_COURSE_BUTTON, '', PARAM_RAW) !== ''
            || optional_param(self::OTHER_COURSE_WANTED, 0, PARAM_INT) === 1
            || self::other_course_chosen() !== 0;
    }
}
