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
 * Making a prototype out of a question's overrides.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype;
use local_coderunner_manager\prototype_candidates;
use local_coderunner_manager\prototype_repository;

/**
 * *Make prototype from this question*: design section 7's answer to CodeRunner
 * 5's practice of turning a question into a prototype by flipping a flag.
 *
 * An author who has customised a question and wants to reuse the result names
 * a prototype; a child of the prototype the question is built on is created,
 * holding exactly the question's overrides, in the question's own course. The
 * question is then pointed at it unless the author says otherwise.
 *
 * **Exactly those overrides, redundant ones included.** A question migrated
 * from CodeRunner 5 carries about twenty overrides of which six or seven differ
 * from what it would inherit, so a promoted prototype pins fields it need not.
 * Dropping those would be a nicer library and a change of behaviour: an
 * override equal to the parent's value still *freezes* it, and a question
 * re-pointed at a prototype that had dropped it would begin following the
 * grandparent for that field. M3 settled that unfreezing what the migration
 * froze is the author's decision, not a form's, and the design's wording —
 * "holding exactly those overrides" — says the same thing.
 *
 * **What does not move.** Support files and the question's own fields — its
 * sample answer, answer preload, validate-on-save and template debugging —
 * stay with the question. Neither is in an overrides document to begin with:
 * {@see settings_form::overrides_from()} refuses instance fields and file
 * fields, which is what makes the hand-over to a prototype a straight copy
 * rather than a filter. A prototype holding an instance field would break
 * every question beneath it.
 *
 * **The promotion happens as the question is saved**, not when the button is
 * pressed. The button only reveals the name box. Doing the work on the press
 * would mean rebuilding the form with every promoted field freshly inherited,
 * against formslib's repopulation of the controls from the submission — and
 * would leave a prototype behind if the author then cancelled the question.
 */
class prototype_promotion {
    /** @var string The name the new prototype is given. */
    const NAME_FIELD = 'promotename';

    /** @var string Whether the question is pointed at the new prototype. */
    const REPOINT_FIELD = 'promoterepoint';

    /** @var string The no-submit button that reveals the name box. */
    const BUTTON = 'promotebutton';

    /** @var string Remembers, across submissions, that the box was asked for. */
    const WANTED = 'promotewanted';

    /**
     * Add the button that reveals the name box, or the name box.
     *
     * Offered only to an author who may create a prototype in the question's
     * own course, and only for a question that has a prototype to be a child
     * of. The capability is asked again on save, because a form's offer is not
     * an authorisation.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param int $courseid the course the question belongs to.
     * @param int|null $prototypeid the prototype the question is built on.
     */
    public static function add_to_form(\MoodleQuickForm $mform, int $courseid, ?int $prototypeid): void {
        // Registered unconditionally and before the element exists, for the two
        // reasons the picker's other-course step records: the press arrives on
        // a different request from the one that drew the button, and
        // `MoodleQuickForm_submit` asks `isNoSubmitButton()` as it is built, so
        // a button registered afterwards still runs the whole form's
        // client-side validation in the browser.
        $mform->registerNoSubmitButton(self::BUTTON);

        if ($prototypeid === null || !(new prototype_candidates())->may_create_in_course($courseid)) {
            return;
        }

        if (!self::wanted()) {
            $mform->addElement(
                'submit',
                self::BUTTON,
                get_string('promotebutton', 'qtype_coderunner'),
                ['class' => 'btn-secondary']
            );
            return;
        }

        $mform->addElement('hidden', self::WANTED, 1);
        $mform->setType(self::WANTED, PARAM_INT);

        $mform->addElement(
            'text',
            self::NAME_FIELD,
            get_string('promotename', 'qtype_coderunner'),
            ['size' => 40]
        );
        $mform->setType(self::NAME_FIELD, PARAM_RAW_TRIMMED);
        $mform->addHelpButton(self::NAME_FIELD, 'promotename', 'qtype_coderunner');

        $mform->addElement(
            'advcheckbox',
            self::REPOINT_FIELD,
            get_string('promoterepoint', 'qtype_coderunner'),
            get_string('promoterepointlabel', 'qtype_coderunner')
        );
        $mform->setType(self::REPOINT_FIELD, PARAM_BOOL);
        $mform->setDefault(self::REPOINT_FIELD, 1);
        $mform->addHelpButton(self::REPOINT_FIELD, 'promoterepoint', 'qtype_coderunner');
    }

    /**
     * Whether the author has asked for the name box.
     *
     * Read from the submission rather than from the form, because the press
     * that reveals the box arrives on the request that has to draw it.
     *
     * @return bool true if the box is wanted.
     */
    public static function wanted(): bool {
        return optional_param(self::BUTTON, '', PARAM_RAW) !== ''
            || optional_param(self::WANTED, 0, PARAM_INT) === 1;
    }

    /**
     * The name the author gave, or an empty string for no promotion.
     *
     * An empty box is not an error. An author who reveals the box and thinks
     * better of it has no way to hide it again, so an empty name has to mean
     * *never mind* rather than *tell me off*.
     *
     * @param array $data the submitted data.
     * @return string the name, trimmed, or '' for none.
     */
    public static function requested_name(array $data): string {
        return trim((string) ($data[self::NAME_FIELD] ?? ''));
    }

    /**
     * Whether the question is to be pointed at the new prototype.
     *
     * @param array $data the submitted data.
     * @return bool true to re-point.
     */
    public static function repoint_wanted(array $data): bool {
        return !empty($data[self::REPOINT_FIELD]);
    }

    /**
     * What is wrong with the promotion the author asked for.
     *
     * @param array $data the submitted data.
     * @param int $courseid the course the question belongs to.
     * @param int|null $prototypeid the prototype the question is built on.
     * @return array element name => message, empty when there is nothing wrong.
     */
    public static function validate(array $data, int $courseid, ?int $prototypeid): array {
        $name = self::requested_name($data);
        if ($name === '') {
            return [];
        }

        if ($prototypeid === null) {
            return [self::NAME_FIELD => get_string('promotenoparent', 'qtype_coderunner')];
        }
        if (!(new prototype_candidates())->may_create_in_course($courseid)) {
            return [self::NAME_FIELD => get_string('promotenopermission', 'qtype_coderunner')];
        }

        $clash = (new prototype_repository())->name_clash($name, $courseid);
        if ($clash !== null) {
            return [self::NAME_FIELD => get_string($clash, 'local_coderunner_manager', $name)];
        }

        return [];
    }

    /**
     * Create the prototype.
     *
     * The caller has validated the name; a clash arriving here anyway — two
     * authors promoting the same name at once — surfaces as the repository's
     * own exception rather than being swallowed, because losing the question's
     * save is better than saving it against a prototype that is not the one
     * the author named.
     *
     * @param string $name the name the author gave.
     * @param int $courseid the course the prototype belongs to.
     * @param int $parentid the prototype it is a child of.
     * @param override_set $overrides what the question overrides.
     * @return prototype the new prototype.
     */
    public static function promote(
        string $name,
        int $courseid,
        int $parentid,
        override_set $overrides
    ): prototype {
        return (new prototype_repository())->create(
            $name,
            $courseid,
            new override_set($overrides->to_array(), $name),
            $parentid,
            '',
            '',
            false,
            '',
            false,
            [],
            FORMAT_HTML
        );
    }
}
