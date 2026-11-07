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
 * **One autocomplete, with the groups as disabled options.** Moodle's
 * `autocomplete` is a `select` with a filter box and the accessibility
 * behaviour already written, which decision 21 asks for in preference to a
 * custom combobox. It does not support `optgroup`: `form-autocomplete.js`
 * rebuilds its suggestions from `option` elements that are *direct children* of
 * the select, so anything inside an optgroup would vanish the moment
 * JavaScript loaded. It does support a disabled option — the suggestions
 * template documents one, and renders it `aria-disabled="true"`, which keyboard
 * navigation then skips — so each group is introduced by a disabled option and
 * the tree is shown by indenting with non-breaking spaces. Both work the same
 * in the plain select and in the enhanced list, since HTML_QuickForm writes an
 * option's text unescaped and the suggestion template renders it as HTML.
 *
 * That last point cuts both ways: a prototype's name is author-supplied text
 * going into a context that treats it as markup, so every label is escaped
 * here.
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

        $element = $mform->addElement(
            'autocomplete',
            self::FIELD,
            get_string('coderunnertype', 'qtype_coderunner'),
            [],
            ['noselectionstring' => get_string('choosedots')]
        );
        foreach (self::options($courseid, $selectedid, $othercourseid) as $option) {
            $element->addOption(
                $option['label'],
                $option['value'],
                $option['disabled'] ? ['disabled' => 'disabled'] : []
            );
        }
        $mform->setType(self::FIELD, PARAM_INT);
        $mform->addRule(self::FIELD, get_string('prototyperequired', 'qtype_coderunner'), 'required', null, 'client');
        $mform->addHelpButton(self::FIELD, 'coderunnertype', 'qtype_coderunner');

        $mform->addElement(
            'static',
            self::FIELD . '_chain',
            '',
            self::breadcrumb($selectedid)
        );

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
     * The picker's options in the order they are shown: a disabled heading for
     * each group, then the group's prototypes, indented by their depth in the
     * tree.
     *
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     * @param int $othercourseid another course whose prototypes to add, or 0.
     * @return array a list of ['value', 'label', 'disabled'].
     */
    public static function options(int $courseid, ?int $selectedid = null, int $othercourseid = 0): array {
        $candidates = new prototype_candidates();
        $groups = $candidates->groups_for_course($courseid, $selectedid);

        if ($othercourseid && $othercourseid !== $courseid) {
            $othergroup = $candidates->for_other_course($othercourseid);
            if ($othergroup) {
                $groups[] = $othergroup;
            }
        }

        $options = [];
        foreach ($groups as $group) {
            $options[] = [
                'value' => '',
                'label' => get_string('prototypegroup_' . $group['key'], 'qtype_coderunner'),
                'disabled' => true,
            ];
            foreach ($group['options'] as $id => $option) {
                $options[] = [
                    'value' => (string) $id,
                    'label' => str_repeat('&nbsp;', 4 * ($option['depth'] + 1)) . s($option['label']),
                    'disabled' => false,
                ];
            }
        }
        return $options;
    }

    /**
     * What the chosen prototype inherits from, as a trail of names, with its
     * one-line summary.
     *
     * The trail is shown once, beneath the picker, rather than on each row of
     * it: a chain repeated against every descendant is the same ancestors over
     * and over, and the full tree belongs in the prototype library, where
     * browsing the structure is the task.
     *
     * @param int|null $prototypeid the chosen prototype.
     * @return string the rendered breadcrumb, or an empty string if there is
     *      nothing to say.
     */
    public static function breadcrumb(?int $prototypeid): string {
        if (!$prototypeid) {
            return '';
        }

        $repository = new prototype_repository();
        try {
            $chain = $repository->chain_prototypes($prototypeid);
        } catch (\Exception $e) {
            // A prototype that has been deleted, or whose parent links form a
            // cycle. The question is broken and says so elsewhere; the picker
            // does not need to say it twice.
            return '';
        }

        $names = [];
        foreach ($chain as $prototype) {
            $names[] = s($prototype->name);
        }
        $trail = \html_writer::tag('span', implode(' › ', $names), ['class' => 'text-muted']);

        $last = end($chain);
        $summary = $last->currentversion->summary ?? '';
        if ($summary !== '') {
            $trail .= \html_writer::tag('div', s($summary), ['class' => 'text-muted']);
        }

        return \html_writer::tag('small', $trail);
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
