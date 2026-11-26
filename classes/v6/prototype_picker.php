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
use local_coderunner_manager\scope_picker;

/**
 * The control by which a question author chooses the prototype their question
 * is built on, replacing CodeRunner 5's *Question type* menu.
 *
 * **The pair of menus itself is the manager's**
 * ({@see \local_coderunner_manager\scope_picker}), because the prototype
 * editor's *Inherits from* shows the same control over the same library and
 * the two were allowed to drift apart once already. The scope and ordering
 * rules are the manager's too ({@see prototype_candidates}). What is here is
 * everything that is about a *question*: the label and the help, the chain's
 * breadcrumb and the chosen prototype's summary beside the menus, the rule
 * that a question must be built on something, and the no-submit button that
 * rebuilds the question form when the choice changes.
 *
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

    /** @var string The field holding the course whose library the prototype menu offers. */
    const COURSE_FIELD = 'pickercourseid';

    /**
     * The no-submit button that rebuilds the form for a newly chosen prototype.
     *
     * Visually hidden, and clicked by `qtype_coderunner/prototypereload` when
     * the menu changes. It is a real button rather than a scripted form submit
     * because `no_submit_button_pressed()` recognises a rebuild by finding a
     * registered button's name in the submission.
     */
    const RELOAD_BUTTON = 'pickerreload';

    /**
     * The shared control, configured for a question's prototype.
     *
     * @return \local_coderunner_manager\scope_picker the pair of menus.
     */
    protected static function picker(): scope_picker {
        return new scope_picker(self::FIELD, self::COURSE_FIELD);
    }

    /**
     * Add the pair of menus and the breadcrumb that says what the choice
     * inherits from.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     */
    public static function add_to_form(\MoodleQuickForm $mform, int $courseid, ?int $selectedid = null): void {
        // The menus, and then the chain the choice inherits through, on one
        // row: the menus where every Moodle selector puts a menu, and the
        // chain beside the value it describes rather than in a panel of its
        // own. The group carries the label, so no element repeats it.
        [$elements, $separators] = self::picker()->elements($mform, $courseid, $selectedid);
        $elements[] = $mform->createElement('static', self::FIELD . '_chain', '', self::breadcrumb($selectedid));

        $mform->addGroup(
            $elements,
            self::GROUP,
            get_string('coderunnertype', 'qtype_coderunner'),
            $separators,
            false
        );
        $mform->setType(self::FIELD, PARAM_INT);

        // `addGroupRule()` naming the element, not `addRule()` on the group:
        // a rule on a group is handed the group's whole value, which with two
        // menus in it is an array, and `HTML_QuickForm_Rule_Required` casts
        // that to the string "Array" and passes however empty the prototype
        // menu is.
        //
        // **The required rule is not what refuses an unchosen prototype,
        // though, and cannot be.** `setType()` above cleans the submitted
        // placeholder with `PARAM_INT`, so the rule is asked about `0` rather
        // than about `''`, and "0" is a non-empty string. The refusal is
        // {@see validation_error()}, called from the form's `validation()`,
        // where `empty()` reads 0 as unanswered. Measured, not assumed: a
        // submission with the placeholder chosen passes every arrangement of
        // this rule and is refused by `validation()`. The rule is kept because
        // it does its work in the browser, where the value is still `''` when
        // the JavaScript sees it: pressing *Save changes* with nothing chosen
        // is blocked without a request, and the message is shown against the
        // group. Checked there, not inferred — the group-level rule it
        // replaced was silent in the browser too.
        $mform->addGroupRule(self::GROUP, [
            self::FIELD => [
                [get_string('prototyperequired', 'qtype_coderunner'), 'required', null, 'client'],
            ],
        ]);
        $mform->addHelpButton(self::GROUP, 'coderunnertype', 'qtype_coderunner');

        // Changing either menu rebuilds the form: a new prototype changes the
        // fields the question inherits, the notes saying where they come from
        // and the test case columns on offer, and a new course changes what the
        // prototype menu holds. Registered before it is created, so that it
        // marks itself as skipping client-side validation.
        $mform->registerNoSubmitButton(self::RELOAD_BUTTON);
        $mform->addElement(
            'submit',
            self::RELOAD_BUTTON,
            get_string('prototypereload', 'qtype_coderunner'),
            ['class' => 'visually-hidden']
        );

        $summary = self::summary($selectedid);
        if ($summary !== '') {
            $mform->addElement('static', self::FIELD . '_summary', '', $summary);
        }
    }

    /**
     * Settle what each menu shows, once the form holds its data.
     *
     * Called from the form's `definition_after_data()`; see
     * {@see \local_coderunner_manager\scope_picker::settle()} for why a
     * default cannot do it.
     *
     * @param \MoodleQuickForm $mform the form, with its data.
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     */
    public static function settle_selection(\MoodleQuickForm $mform, int $courseid, ?int $selectedid = null): void {
        self::picker()->settle($mform, self::GROUP, $courseid, $selectedid);
    }

    /**
     * Ask the page for the script that rebuilds the form on a change of
     * prototype.
     *
     * Called by the form rather than from the element definitions, so that
     * building a form has no side effects on $PAGE and can be done in a test.
     */
    public static function require_reload_on_change(): void {
        global $PAGE;

        $PAGE->requires->js_call_amd(
            'qtype_coderunner/prototypereload',
            'init',
            ['id_' . self::FIELD, 'id_' . self::RELOAD_BUTTON, 'id_' . self::COURSE_FIELD]
        );
    }

    /**
     * The picker's option groups, each holding its prototypes in tree order,
     * indented by their depth.
     *
     * The shared control's, by way of this one: kept as a method of the picker
     * because the question type's tests read the menu through it, and because
     * a caller here asks about a *question's* course rather than about a
     * scope.
     *
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     * @param int $scopecourseid the course whose library to show, or 0 for the
     *      question's own.
     * @return array group heading => [prototype id => label].
     */
    public static function optgroups(int $courseid, ?int $selectedid = null, int $scopecourseid = 0): array {
        return self::picker()->optgroups($courseid, $selectedid, $scopecourseid ?: $courseid);
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
}
