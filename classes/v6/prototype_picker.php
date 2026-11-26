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
 * **Two menus, reading as a sentence: _from_ ‹course› _use_
 * ‹prototype›.** The second is the picker proper; the first says whose
 * library it offers, and is drawn only where it would hold more than one
 * entry, so an author with rights in one course sees a single menu. The course
 * menu holds the question's own course and every other course that owns
 * prototypes and that the author may borrow from; *Site* is not among them,
 * because the built-ins are site-scope and are offered in the question's own
 * scope, where a course that writes most of its questions directly on
 * `python3` needs them.
 *
 * Choosing a course rebuilds the form, and the prototype menu then holds that
 * course's library and nothing else. What it offers is the manager's to say
 * ({@see prototype_candidates::for_other_course()}), including the one case
 * that is not a plain list: a prototype the question already uses is offered
 * even where the author may not browse its course.
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
     * Add the pair of menus and the breadcrumb that says what the choice
     * inherits from.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     */
    public static function add_to_form(\MoodleQuickForm $mform, int $courseid, ?int $selectedid = null): void {
        $candidates = new prototype_candidates();
        $scopes = self::scope_options($candidates, $courseid, $selectedid);
        $scopeid = self::scope_courseid($candidates, $courseid, $selectedid, $scopes);

        // The menus and the chain the choice inherits through, on one row,
        // where every Moodle selector puts a menu: beside its label, with the
        // current value showing in it.
        //
        // None of them carries a label of its own: the group carries it, and an
        // element that repeats it renders the words a second time, above the
        // menu. Each menu is given an `aria-label` instead, because a group
        // label names the row rather than the controls in it, and two unnamed
        // menus side by side would be two unnamed menus to a screen reader.
        $elements = [];
        $separators = [];

        if (count($scopes) > 1) {
            $elements[] = $mform->createElement(
                'select',
                self::COURSE_FIELD,
                '',
                $scopes,
                ['aria-label' => get_string('prototypescope', 'qtype_coderunner')]
            );
            $separators[] = '&nbsp;' . get_string('prototypescopeuse', 'qtype_coderunner') . '&nbsp;';
        }

        $elements[] = self::menu_element($mform, self::optgroups($courseid, $selectedid, $scopeid));
        $separators[] = '&nbsp;&nbsp;';
        $elements[] = $mform->createElement('static', self::FIELD . '_chain', '', self::breadcrumb($selectedid));

        $mform->addGroup(
            $elements,
            self::GROUP,
            get_string('coderunnertype', 'qtype_coderunner'),
            $separators,
            false
        );
        $mform->setType(self::FIELD, PARAM_INT);
        $mform->setType(self::COURSE_FIELD, PARAM_INT);

        // `addGroupRule()` naming the element, not `addRule()` on the group:
        // a rule on a group is handed the group's whole value, which with two
        // menus in it is an array, and `HTML_QuickForm_Rule_Required` casts
        // that to the string "Array" and passes however empty the prototype
        // menu is.
        //
        // **The required rule is not what refuses an unchosen prototype,
        // though, and cannot be.** `setType()` below cleans the submitted
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
     * Called from the form's `definition_after_data()`, and it has to be: on a
     * no-submit reload formslib populates every element from the submission, in
     * preference to the question's stored values, and the submission is one
     * render out of date. An author who looks at another course and comes back
     * submits the *placeholder* as the prototype, because that is what the menu
     * showed them while they were away, and the stored prototype would then
     * read as *Choose…* in a scope that holds it.
     *
     * So the selection is decided here rather than inherited: the chosen
     * prototype where the current scope holds it, and the placeholder where it
     * does not. This settles the rendering only. What is saved is whatever the
     * author submits, and a placeholder submitted as the prototype fails
     * {@see validation_error()} as an unanswered required field, which is the
     * right answer for someone who pressed *Save changes* while reading another
     * course's library.
     *
     * @param \MoodleQuickForm $mform the form, with its data.
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     */
    public static function settle_selection(\MoodleQuickForm $mform, int $courseid, ?int $selectedid = null): void {
        if (!$mform->elementExists(self::GROUP)) {
            return;
        }

        $candidates = new prototype_candidates();
        $scopes = self::scope_options($candidates, $courseid, $selectedid);
        $scopeid = self::scope_courseid($candidates, $courseid, $selectedid, $scopes);
        $inscope = self::selected_in_scope($selectedid, self::optgroups($courseid, $selectedid, $scopeid));

        foreach ($mform->getElement(self::GROUP)->getElements() as $element) {
            switch ($element->getName()) {
                case self::FIELD:
                    $element->setSelected($inscope === null ? '' : (string) $inscope);
                    break;
                case self::COURSE_FIELD:
                    $element->setSelected((string) $scopeid);
                    break;
            }
        }
    }

    /**
     * The courses whose libraries this author may offer the question, in the
     * order the course menu lists them.
     *
     * The question's own course first, named for its relation to the question
     * rather than by its shortname, because that is the entry an author who
     * never borrows anything will use. Then the courses that own prototypes and
     * that the author may borrow from, by name.
     *
     * **And the course that owns the question's current prototype, whatever the
     * author's rights in it.** A question built on another course's prototype
     * must stay editable by an author with rights only in its own course, and a
     * course menu that could not reach the scope holding the question's own
     * value would make the value unreachable.
     *
     * @param prototype_candidates $candidates the manager's scope rules.
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     * @return array course id => the name to show.
     */
    protected static function scope_options(
        prototype_candidates $candidates,
        int $courseid,
        ?int $selectedid
    ): array {
        $scopes = [$courseid => get_string('prototypescope_thiscourse', 'qtype_coderunner')];
        foreach ($candidates->borrowable_courses($courseid) as $id => $name) {
            $scopes[$id] = $name;
        }

        $owner = $candidates->course_of($selectedid);
        if ($owner !== null && $owner !== (int) SITEID && !isset($scopes[$owner])) {
            $scopes[$owner] = $candidates->course_name($owner);
        }

        return $scopes;
    }

    /**
     * The course whose library the prototype menu is showing.
     *
     * The author's own choice while they have made one, and otherwise the
     * course that owns the question's prototype, so that an existing question
     * opens on the scope its own value is in. A site prototype needs no scope
     * of its own: the site library is offered within the question's own course.
     *
     * Read with `optional_param()` rather than from the form's data, because
     * the options have to be in place before formslib will accept the submitted
     * value, which is to say before the form's data can be read at all. This is
     * the same reason a repeated element's count is read this way.
     *
     * @param prototype_candidates $candidates the manager's scope rules.
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     * @param array $scopes the courses on offer, as {@see scope_options()}.
     * @return int the course id.
     */
    protected static function scope_courseid(
        prototype_candidates $candidates,
        int $courseid,
        ?int $selectedid,
        array $scopes
    ): int {
        $submitted = optional_param(self::COURSE_FIELD, 0, PARAM_INT);
        if ($submitted) {
            return isset($scopes[$submitted]) ? $submitted : $courseid;
        }

        $owner = $candidates->course_of($selectedid);
        if ($owner !== null && $owner !== (int) SITEID && isset($scopes[$owner])) {
            return $owner;
        }

        return $courseid;
    }

    /**
     * The chosen prototype, if the menu as built is offering it.
     *
     * @param int|null $selectedid the prototype the question currently uses.
     * @param array $optgroups the menu, as {@see optgroups()} builds it.
     * @return int|null the prototype id, or null if this scope does not hold it.
     */
    protected static function selected_in_scope(?int $selectedid, array $optgroups): ?int {
        if (!$selectedid) {
            return null;
        }
        foreach ($optgroups as $options) {
            if (isset($options[$selectedid])) {
                return $selectedid;
            }
        }
        return null;
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
     * The menu itself: grouped when there is more than one group to choose
     * between, and a plain menu when there is not.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param array $optgroups group heading => [prototype id => label].
     * @return object the form element.
     */
    protected static function menu_element(\MoodleQuickForm $mform, array $optgroups) {
        if (count($optgroups) === 1) {
            // One group is no choice between groups, so the heading would be an
            // unselectable row at the top of the menu saying nothing. A site
            // whose courses add no prototypes of their own — most sites, for a
            // while — sees a plain menu of the built-ins.
            return $mform->createElement(
                'select',
                self::FIELD,
                '',
                ['' => get_string('choose') . '...'] + reset($optgroups),
                ['aria-label' => get_string('coderunnertype', 'qtype_coderunner')]
            );
        }

        // The placeholder is prepended rather than asked for through
        // `selectgroups`' own $showchoose, which does not work on this path:
        // its export_for_template() appends the entry to $optionsgroups while
        // rendering $optiongroups, so the option is silently dropped and the
        // browser selects the first real prototype. A new question therefore
        // opened claiming to be built on whichever prototype sorted first,
        // which the author had not chosen. The toHtml() path has it right, so
        // the fault shows only where a theme renders through the template --
        // which is to say everywhere, in Boost.
        //
        // Core is not ours to patch, and a group of our own survives either
        // path. Its heading is empty because the entry is not a group of
        // prototypes; browsers draw an unlabelled optgroup as a plain
        // separator above it.
        $placeholder = ['' => ['' => get_string('choose') . '...']];

        return $mform->createElement(
            'selectgroups',
            self::FIELD,
            '',
            $placeholder + $optgroups,
            ['aria-label' => get_string('coderunnertype', 'qtype_coderunner')]
        );
    }

    /**
     * The picker's option groups, each holding its prototypes in tree order,
     * indented by their depth.
     *
     * One scope's library: the question's own course, which is two groups —
     * the course's own prototypes, then the site's — or another course, which
     * is one group and so renders as a plain menu with no heading at all.
     *
     * @param int $courseid the course the question belongs to.
     * @param int|null $selectedid the prototype the question currently uses.
     * @param int $scopecourseid the course whose library to show, or 0 for the
     *      question's own.
     * @return array group heading => [prototype id => label].
     */
    public static function optgroups(int $courseid, ?int $selectedid = null, int $scopecourseid = 0): array {
        $candidates = new prototype_candidates();
        $scopecourseid = $scopecourseid ?: $courseid;

        if ($scopecourseid === $courseid) {
            $groups = $candidates->groups_for_course($courseid);
        } else {
            $group = $candidates->for_other_course($scopecourseid, $selectedid);
            $groups = $group ? [$group] : [];
        }

        $optgroups = [];
        foreach ($groups as $group) {
            $heading = get_string('prototypegroup_' . $group['key'], 'qtype_coderunner');
            $optgroups[$heading] = [];
            foreach ($group['options'] as $id => $option) {
                // The indentation is the manager's to spell: the same rule
                // shows the same library in its own *Inherits from* menu.
                $optgroups[$heading][$id] = prototype_candidates::indented_label($option);
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
}
