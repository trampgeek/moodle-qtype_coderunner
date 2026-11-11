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
 * What an author sees on opening a CodeRunner 5 prototype question.
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
 * The banner design section 7 asks for: *the prototype now lives in the
 * manager, here it is, and nothing you do on this page affects any other
 * question*.
 *
 * A CodeRunner 5 prototype was a question with `prototypetype` set, and
 * questions found it by name. The migration left every one of them in place —
 * which is what made it non-destructive — and converted each into a prototype
 * of the same name and scope in the prototype manager. Nothing resolves to the
 * question any more. It is an unused question that looks like the thing it
 * used to be, and the one way to mistake it is to edit it expecting the
 * question type to change.
 *
 * That mistake is now harmless rather than merely discouraged: the installer's
 * snapshot is create-only, so a prototype question that has been converted is
 * never read again. Before that it was an upsert, and an edit here would have
 * been written over the prototype — and over any work an author had done on it
 * in the editor — the next time the task ran.
 *
 * **The link is to the prototype, and only when it can be followed.** The
 * prototype is found the way everything else finds one: by the question's
 * `coderunnertype` within the author's course scope. Where it cannot be found,
 * or where the author may not open it — a site prototype is an
 * administrator's, by the same rule as the library's own links — the name is
 * named without a link, because an author who cannot follow it is better told
 * where to ask than shown a page that refuses them.
 */
class legacy_prototype_notice {
    /** @var string The element the banner is rendered as, and that save errors attach to. */
    const ELEMENT = 'legacyprototypenotice';

    /**
     * Add the banner, which is the whole of the form for such a question.
     *
     * @param \MoodleQuickForm $mform the form being built.
     * @param object $question the question being edited.
     * @param int $courseid the course the author is working in.
     */
    public static function add_to_form(\MoodleQuickForm $mform, $question, int $courseid): void {
        $name = (string) ($question->options->coderunnertype ?? '');

        $mform->addElement(
            'header',
            self::ELEMENT . 'header',
            get_string('legacyprototypeheader', 'qtype_coderunner')
        );
        $mform->setExpanded(self::ELEMENT . 'header', 1);
        $mform->addElement('static', self::ELEMENT, '', self::body($name, $courseid));
    }

    /**
     * What the banner says.
     *
     * @param string $name the prototype name the question defined.
     * @param int $courseid the course the author is working in.
     * @return string HTML.
     */
    public static function body(string $name, int $courseid): string {
        $prototype = self::prototype_for($name, $courseid);

        if ($prototype === null) {
            $message = get_string('legacyprototypenoprototype', 'qtype_coderunner', s($name));
        } else if (prototype_candidates::may_edit($prototype)) {
            // The id alone, as the library's own links carry it: `edit.php`
            // takes the prototype's course from the prototype, so a `courseid`
            // beside an `id` is read and then discarded.
            $url = new \moodle_url('/local/coderunner_manager/edit.php', ['id' => $prototype->id]);
            $message = get_string('legacyprototypemoved', 'qtype_coderunner', [
                'name' => s($name),
                'url' => $url->out(false),
            ]);
        } else {
            $message = get_string('legacyprototypeelsewhere', 'qtype_coderunner', s($name));
        }

        return \html_writer::div($message, 'alert alert-warning', ['role' => 'alert']);
    }

    /**
     * The prototype this question became, if it can be found and named.
     *
     * @param string $name the prototype name.
     * @param int $courseid the course the author is working in.
     * @return prototype|null the prototype, or null if there is none to point at.
     */
    protected static function prototype_for(string $name, int $courseid): ?prototype {
        if ($name === '') {
            return null;
        }
        return (new prototype_repository())->find_by_name_for_course($name, $courseid);
    }
}
