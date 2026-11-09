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
 * The '<<' buttons in the table of failed test cases, which replace a test
 * case's expected output with what the sample answer actually produced.
 *
 * Lifted out of qtype_coderunner/authorform, which the CodeRunner 6 question
 * form does not load: the rest of that module drove the Customise checkbox, the
 * question type menu's AJAX reload and the other machinery of a form that no
 * longer exists, and would throw on the elements it can no longer find. This
 * part depends on nothing but the ids the validation failure table renders, so
 * it stands on its own.
 *
 * @module qtype_coderunner/replaceexpected
 * @copyright  Richard Lobb, 2026, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        /**
         * Listen for clicks on any '<<' button in the failed test case table.
         *
         * Delegated from the document, because the table is rendered by the
         * form's validation and so does not exist when this runs.
         */
        init: function() {
            document.addEventListener('click', function(event) {
                if (!event.target.matches('button.replaceexpectedwithgot')) {
                    return;
                }

                // The 'got' output sits in a <pre> before the button, with the
                // test case's row number in its id.
                let gotPre = event.target.previousElementSibling;
                while (gotPre && (!gotPre.matches('pre') || !gotPre.id.startsWith('id_got_'))) {
                    gotPre = gotPre.previousElementSibling;
                }
                if (!gotPre) {
                    return;
                }

                const testCaseId = gotPre.id.replace('id_got_', '');
                const expectedField = document.getElementById('id_expected_' + testCaseId);
                const expectedFailField = document.getElementById('id_fail_expected_' + testCaseId);
                const failRow = document.querySelector('.failrow_' + testCaseId);

                if (expectedField) {
                    expectedField.value = gotPre.textContent;
                }
                if (expectedFailField) {
                    expectedFailField.innerHTML = gotPre.textContent;
                }
                if (failRow) {
                    failRow.classList.add('fixed');
                }
                event.target.disabled = true;
            });
        }
    };
});
