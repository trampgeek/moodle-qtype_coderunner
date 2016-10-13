// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for handling textareas and form actions in CodeRunner question
 * editing forms and student question answering forms.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define(['jquery'], function($) {

    // Function to initialise all code-input text-areas in a page.
    // Used by the form editor but can't be used for question text areas as
    // renderer.php is called once for each question in a quiz, and there is
    // no communication between the questions.
    function setupAllTAs() {
        $('textarea.edit_code').each(initTextArea);
    }

    // Initialise a particular text area (TA), given its ID (which may contain
    // colons, so can't use jQuery selector).
    function initQuestionTA(taId) {
        $(document.getElementById(taId)).each(initTextArea);
    }

    /* Set up the JavaScript to handle a text area 'this'.
     * It just does rudimentary autoindent on return and replaces tabs with
     * 4 spaces always.
     * For info on key handling browser inconsistencies see
     * http://unixpapa.com/js/key.html
     * If the AceEditor is handling a text area, this code is unused as
     * the actual textarea is hidden by Ace.
     */
    function initTextArea() {
        var ENTER = 13,
            TAB = 9;

        $(this).keydown(function(e) {
            // Don't autoindent when behat testing in progress.
            if (window.hasOwnProperty('behattesting') && window.behattesting) { return; }

            if(e.which === undefined || e.which !== 0) { // Normal keypress?
                if (e.keyCode == TAB) {
                    // Ignore SHIFT/TAB. Insert 4 spaces on TAB.
                    if (e.shiftKey || insertString(this, "    ")) {
                        e.preventDefault();
                    }
                }
                else if (e.keyCode === ENTER && this.selectionStart !== undefined) {
                    // Handle autoindent only on non-IE.
                    var before = this.value.substring(0, this.selectionStart);
                    var eol = before.lastIndexOf("\n");
                    var line = before.substring(eol + 1);  // Take from eol to end.
                    var indent = "";
                    for (var i = 0; i < line.length && line.charAt(i) === ' '; i++) {
                        indent = indent + " ";
                    }
                    if (insertString(this, "\n" + indent)) {
                        e.preventDefault();
                    }
                }
            }
        });
    }

    /*
     * Insert into the given textarea ta the given string sToInsert.
     */
    function insertString(ta, sToInsert) {
        if (ta.selectionStart !== undefined) {  // Firefox etc.
            var before = ta.value.substring(0, ta.selectionStart);
            var selSave = ta.selectionEnd;
            var after = ta.value.substring(ta.selectionEnd, ta.value.length);

            // Update the text field.
            var tmp = ta.scrollTop;  // Inhibit annoying auto-scroll.
            ta.value = before + sToInsert + after;
            var pos = selSave + sToInsert.length;
            ta.selectionStart = pos;
            ta.selectionEnd = pos;
            ta.scrollTop = tmp;
            return true;

        }
        else if (document.selection && document.selection.createRange) { // IE.
            // TODO: check if this still works. OLD CODE.
            var r = document.selection.createRange();
            var dr = r.duplicate();
            dr.moveToElementText(ta);
            dr.setEndPoint("EndToEnd", r);
            r.text = sToInsert;
            return true;
        }
        // Other browsers we can't handle.
        else {
            return false;
        }
    }

    return {
        setupAllTAs: setupAllTAs,
        initQuestionTA: initQuestionTA
    };
});