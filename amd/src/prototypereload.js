/**
 * This file is part of Moodle - http:moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http:www.gnu.org/licenses/>.
 */

/**
 * Rebuild the question form when the author picks a different prototype.
 *
 * Changing prototype changes more than three lines of prose: every field the
 * question does not override takes a new value and a new provenance note, and
 * the prototype's test case field switches decide which test case columns the
 * form offers at all. Rather than patch all of that from the browser, the form
 * is rebuilt by the server, which gets it right by construction and keeps
 * whatever the author has typed, because the rebuilt form is populated from the
 * submission.
 *
 * This is an enhancement and not a requirement: with no JavaScript the author
 * changes the menu, saves, and the saved question shows the new settings.
 *
 * @module qtype_coderunner/prototypereload
 * @copyright  Richard Lobb, 2026, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        /**
         * Submit the form through its no-submit reload button whenever the
         * prototype menu changes.
         *
         * @param {string} menuid the id of the prototype menu.
         * @param {string} buttonid the id of the form's no-submit reload button.
         */
        init: function(menuid, buttonid) {
            const menu = document.getElementById(menuid);
            const button = document.getElementById(buttonid);
            if (!menu || !button) {
                return;
            }

            // Remembered so that a change back to the original value still
            // counts as a change from whatever was last loaded.
            let loaded = menu.value;

            menu.addEventListener('change', function() {
                if (menu.value === loaded || menu.value === '') {
                    return;
                }
                loaded = menu.value;
                button.click();
            });
        }
    };
});
