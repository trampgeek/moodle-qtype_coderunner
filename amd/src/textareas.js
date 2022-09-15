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
 * JavaScript for handling textareas and form actions in CodeRunner question
 * editing forms and student question answering forms.
 *
 * @module qtype_coderunner/textareas
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define(['jquery'], function($) {

    /**
     * Function to initialise all code-input text-areas in a page.
     * Used by the form editor but can't be used for question text areas as
     * renderer.php is called once for each question in a quiz, and there is
     * no communication between the questions.
     */
    function setupAllTAs() {
        $('textarea.edit_code').each(initTextArea);
    }

    /**
     * Initialise a particular text area (TA), given its ID (which may contain
     * colons, so can't use jQuery selector).
     * @param {string} taId The ID of the textarea html element.
     */
    function initQuestionTA(taId) {
        $(document.getElementById(taId)).each(initTextArea);
    }


    /**
     * Set up to expand or collapse the question author's answer when the user clicks
     * on the show/hide answer button.
     * @param {string} linkId The ID of the link that the user clicks.
     * @param {sting} divId The ID of the div to be shown or hidden.
     */
    function setupShowHideAnswer(linkId, divId) {
        let link = $(document.getElementById(linkId)),
            div = $(document.getElementById(divId)),
            hdr = link.children()[0], // The <h6> element.
            hdrText = hdr.innerText;  // Its body.
        link.click(() => {
            if (div.is(":visible")) {
                div.hide();
                hdr.innerText = hdrText.replace("\u25BE", "\u25B8");
            } else {
                div.show();
                div.trigger('mousemove'); // So user sees contents.
                hdr.innerText = hdrText.replace("\u25B8", "\u25BE");
            }
        });
    }

    /**
     * Set up the JavaScript to handle a text area 'this'.
     * It just does rudimentary autoindent on return and replaces tabs with
     * 4 spaces always.
     * For info on key handling browser inconsistencies see
     * http://unixpapa.com/js/key.html
     * If the AceEditor is handling a text area, this code is unused as
     * the actual textarea is hidden by Ace.
     */
    function initTextArea() {
        var TAB = 9,
            ENTER = 13,
            ESC = 27,
            KEY_M = 77;

        $(this).data('clickInProgress', false);
        $(this).data('capturingTab', true);

        $(this).on('mousedown', function() {
            /*
             * Event order seems to be (\ is where the mouse button is pressed, / released):
             * Chrome: \ mousedown, mouseup, focusin / click.
             * Firefox/IE: \ mousedown, focusin / mouseup, click.
             */
            $(this).data('clickInProgress', true);
        });

        $(this).on('focusin', function() {
            /*
             * At first, pressing TAB moves focus.
             */
            $(this).data('capturingTab', $(this).data('clickInProgress'));
        });

        $(this).on('click', function() {
            $(this).data('clickInProgress', false);
        });

        $(this).on('keydown', function(e) {
            /*
             * Don't autoindent when behat testing in progress.
             */
            if (window.hasOwnProperty('behattesting') && window.behattesting) { return; }

            if (e.which === undefined || e.which !== 0) { // Normal keypress?
                if (e.keyCode == TAB && $(this).data('capturingTab')) {
                    /*
                     * Ignore SHIFT/TAB. Insert 4 spaces on TAB.
                     */
                    if (e.shiftKey || insertString(this, "    ")) {
                        e.preventDefault();
                    }
                }
                else if (e.keyCode === ENTER && this.selectionStart !== undefined) {
                    /*
                     * Handle autoindent only on non-IE.
                     */
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
                    /*
                     * Once the user has started typing, TAB indents.
                     */
                    $(this).data('capturingTab', true);
                }
                else if (e.keyCode === KEY_M && e.ctrlKey && !e.altKey) {
                    /*
                     * CTRL + M toggles TAB capturing mode.
                     * This is the short-cut recommended by
                     * https:www.w3.org/TR/wai-aria-practices/#richtext.
                     */
                    $(this).data('capturingTab', !$(this).data('capturingTab'));
                    e.preventDefault(); // Firefox uses this for mute audio in current browser tab.
                }
                else if (e.keyCode === ESC) {
                    /*
                     * ESC always stops capturing TAB.
                     */
                    $(this).data('capturingTab', false);
                }
                else if (!(e.ctrlKey || e.altKey)) {
                    /*
                     * Once the user has started typing (not modifier keys) TAB indents.
                     */
                    $(this).data('capturingTab', true);
                }
            }
        });
    }

    /**
     * Insert into the given textarea ta the given string sToInsert.
     * @param {html_element} ta The textarea to be updated.
     * @param {string} sToInsert The string to be inserted at the current selection
     * point.
     */
    function insertString(ta, sToInsert) {
        if (ta.selectionStart !== undefined) {  // Firefox etc.
            var before = ta.value.substring(0, ta.selectionStart);
            var selSave = ta.selectionEnd;
            var after = ta.value.substring(ta.selectionEnd, ta.value.length);

            /**
             * Update the text field.
             */
            var tmp = ta.scrollTop;  // Inhibit annoying auto-scroll.
            ta.value = before + sToInsert + after;
            var pos = selSave + sToInsert.length;
            ta.selectionStart = pos;
            ta.selectionEnd = pos;
            ta.scrollTop = tmp;
            return true;

        }
        else if (document.selection && document.selection.createRange) { // IE.
            /*
             * TODO: check if this still works. OLD CODE.
             */
            var r = document.selection.createRange();
            var dr = r.duplicate();
            dr.moveToElementText(ta);
            dr.setEndPoint("EndToEnd", r);
            r.text = sToInsert;
            return true;
        }
        /*
         * Other browsers we can't handle.
         */
        else {
            return false;
        }
    }

    return {
        setupAllTAs: setupAllTAs,
        initQuestionTA: initQuestionTA,
        setupShowHideAnswer: setupShowHideAnswer,
    };
});
