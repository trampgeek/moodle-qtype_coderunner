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
// GNU General Public License for more util.details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Implementation of the ace_gapfiller_ui user interface plugin. For overall details
 * of the UI plugin architecture, see userinterfacewrapper.js.
 *
 * This plugin uses the usual ace editor but only makes some portions of the text editable.
 * The pre-formatted text is supplied by the question author in either the
 * "globalextra" field or the testcode field of the first test case, according
 * to the ui parameter ui_source (default: globalextra).
 * Editable "gaps" are inserted into the ace editor at specified points.
 * It is intended primarily for use with coding questions where the answerbox presents
 * the students with code that has smallish bits missing.
 *
 * The locations within the globalextra text at which the gaps are
 * to be inserted are denoted by "tags" of the form
 *
 *     {[ size ]}
 *
 * or
 *
 *     {[ size-maxSize ]}
 *
 * where size and maxSize are integer literals. These respectively inject a "gap" into
 * the editor of the specified size and maxSize. If maxSize is not specified then the
 * "gap" has no maximum size and can grow without bound.
 *
 * The serialisation of the answer box contents, i.e. the text that
 * copied back into the textarea for submissions
 * as the answer, is simply a list of all the field values (strings), in order.
 *
 * As a special case of the serialisation, if the value list is empty, the
 * serialisation itself is the empty string.
 *
 * The delimiters for the gap tags are by default '{[' and
 * ']}'.
 *
 * @module qtype_coderunner/ui_ace_gapfiller
 * @copyright  Richard Lobb, 2019, The University of Canterbury
 * @copyright  Matthew Toohey, 2021, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    var Range;  // Can't load this until ace has loaded.
    const fillChar = " ";
    const validChars = /[ !"#$%&'()*+,`\-./0-9\p{L}:;<=>?@\[\]\\^_{}|~]/u;
    const ACE_LIGHT_THEME = 'ace/theme/textmate';

    /**
     * Constructor for the Ace interface object
     * @param {string} textareaId The ID of the textarea html element.
     * @param {int} w The width of the text area in pixels.
     * @param {int} h The height of the text area in pixels.
     * @param {object} uiParams The UI parameter specifier object.
     */
    function AceGapfillerUi(textareaId, w, h, uiParams) {
        this.textArea = $(document.getElementById(textareaId));
        var wrapper = $(document.getElementById(textareaId + '_wrapper')),
            focused = this.textArea[0] === document.activeElement,
            lang = uiParams.lang,
            t = this;  // For embedded callbacks.

        let code = "";
        this.uiParams = uiParams;
        this.gaps = [];
        this.source = uiParams.ui_source || 'globalextra';
        this.nextGapIndex = 0;
        if (this.source !== 'globalextra' && this.source !== 'test0') {
            alert('Invalid source for code in ui_ace_gapfiller');
            this.source = 'globalextra';
        }
        if (this.source == 'globalextra') {
            code = this.textArea.attr('data-globalextra');
        } else {
            code = this.textArea.attr('data-test0');
        }

        try {
            window.ace.require("ace/ext/language_tools");
            Range = window.ace.require("ace/range").Range;
            this.modelist = window.ace.require('ace/ext/modelist');

            this.enabled = false;
            this.contents_changed = false;
            this.capturingTab = false;
            this.clickInProgress = false;

            this.editNode = $("<div></div>"); // Ace editor manages this
            this.editNode.css({
                resize: 'none',
                height: h,
                width: "100%"
            });

            this.editor = window.ace.edit(this.editNode.get(0));
            if (this.textArea.prop('readonly')) {
                this.editor.setReadOnly(true);
            }

            this.editor.setOptions({
                displayIndentGuides: false,
                dragEnabled: false,
                enableBasicAutocompletion: true,
                newLineMode: "unix",
            });
            this.editor.$blockScrolling = Infinity;

            // Use the uiParams theme if provided else use light.
            if (uiParams.theme) {
                this.editor.setTheme("ace/theme/" + uiParams.theme);
            } else {
                this.editor.setTheme(ACE_LIGHT_THEME);
            }

            this.setLanguage(lang);

            this.setEventHandlers(this.textArea);
            this.captureTab();

            // Try to tell Moodle about parts of the editor with z-index.
            // It is hard to be sure if this is complete. ACE adds all its CSS using JavaScript.
            // Here, we just deal with things that are known to cause a problem.
            // Can't do these operations until editor has rendered. So ...
            this.editor.renderer.on('afterRender', function() {
                var gutter =  wrapper.find('.ace_gutter');
                if (gutter.hasClass('moodle-has-zindex')) {
                    return;  // So we only do what follows once.
                }
                gutter.addClass('moodle-has-zindex');

                if (focused) {
                    t.editor.focus();
                    t.editor.navigateFileEnd();
                }
                t.aceLabel = wrapper.find('.answerprompt');
                t.aceLabel.attr('for', 'ace_' + textareaId);

                t.aceTextarea = wrapper.find('.ace_text-input');
                t.aceTextarea.attr('id', 'ace_' + textareaId);
            });

            this.createGaps(code);

            // Intercept commands sent to ace.
            this.editor.commands.on("exec", function(e) {
                let cursor = t.editor.selection.getCursor();
                let commandName = e.command.name;
                let selectionRange = t.editor.getSelectionRange();

                let gap = t.findCursorGap(cursor);

                if (commandName.startsWith("go")) {  // If command just moves the cursor then do nothing.
                    if (gap !== null && commandName === "gotoright" && cursor.column === gap.range.start.column+gap.textSize) {
                        // In this case we jump out of gap over the empty space that contains nothing that the user has entered.
                        t.editor.moveCursorTo(cursor.row, gap.range.end.column+1);
                    } else {
                        return;
                    }
                }

                if (gap === null) {
                    // Not in a gap
                    if (commandName === "selectall") {
                        t.editor.selection.selectAll();
                    }

                } else if (commandName === "indent") {
                    // Instead of indenting, move to next gap.
                    let nextGap = t.gaps[(gap.index+1) % t.gaps.length];
                    t.editor.moveCursorTo(nextGap.range.start.row, nextGap.range.start.column+nextGap.textSize);
                    t.editor.selection.clearSelection(); // Clear selection.

                } else if (commandName === "selectall") {
                    // Select all text in a gap if we are in a gap.
                    t.editor.selection.setSelectionRange(new Range(gap.range.start.row,
                                                         gap.range.start.column,
                                                         gap.range.start.row,
                                                         gap.range.end.column), false);

                } else if (t.editor.selection.isEmpty()) {
                    // User is not selecting multiple characters.
                    if (commandName === "insertstring") {
                        let char = e.args;
                        // Only allow user to insert 'valid' chars.
                        if (validChars.test(char)) {
                            gap.insertChar(t.gaps, cursor, char);
                        }
                    } else if (commandName === "backspace") {
                        // Only delete chars that are actually in the gap.
                        if (cursor.column > gap.range.start.column && gap.textSize > 0) {
                            gap.deleteChar(t.gaps, {row: cursor.row, column: cursor.column-1});
                        }
                    } else if (commandName === "del") {
                        // Only delete chars that are actually in the gap.
                        if (cursor.column < gap.range.start.column + gap.textSize && gap.textSize > 0) {
                            gap.deleteChar(t.gaps, cursor);
                        }
                    }
                    t.editor.selection.clearSelection(); // Keep selection clear.

                } else if (!t.editor.selection.isEmpty() && gap.cursorInGap(selectionRange.start)
                           && gap.cursorInGap(selectionRange.end)) {
                    // User is selecting multiple characters and is in a gap.

                    // These are the commands that remove the selected text.
                    if (commandName === "insertstring" || commandName === "backspace"
                        || commandName === "del" || commandName === "paste"
                        || commandName === "cut") {

                        gap.deleteRange(t.gaps, selectionRange.start.column, selectionRange.end.column);
                        t.editor.selection.clearSelection(); // Clear selection.
                    }

                    if (commandName === "insertstring") {
                        let char = e.args;
                        if (validChars.test(char)) {
                            gap.insertChar(t.gaps, selectionRange.start, char);
                        }
                    }
                }

                // Paste text into gap.
                if (gap !== null && commandName === "paste") {
                    gap.insertText(t.gaps, selectionRange.start.column, e.args.text);
                }

                e.preventDefault();
                e.stopPropagation();
            });

            // Move cursor to where it should be if we click on a gap.
            t.editor.selection.on('changeCursor', function() {
                let cursor = t.editor.selection.getCursor();
                let gap = t.findCursorGap(cursor);
                if (gap !== null) {
                    if (cursor.column > gap.range.start.column+gap.textSize) {
                        t.editor.moveCursorTo(gap.range.start.row, gap.range.start.column+gap.textSize);
                    }
                }
            });

            this.gapToSelect = null;    // Stores gap that has been selected with triple click.

            // Select all text in gap on triple click within gap.
            this.editor.on("tripleclick", function(e) {
                let cursor = t.editor.selection.getCursor();
                let gap = t.findCursorGap(cursor);
                if (gap !== null) {
                    t.editor.selection.setSelectionRange(new Range(gap.range.start.row,
                                                                   gap.range.start.column,
                                                                   gap.range.start.row,
                                                                   gap.range.end.column), false);
                    t.gapToSelect = gap;
                    e.preventDefault();
                    e.stopPropagation();
                }
            });

            // Annoying hack to ensure the tripple click thing works.
            this.editor.on("click", function(e) {
                if (t.gapToSelect) {
                    t.editor.moveCursorTo(t.gapToSelect.range.start.row, t.gapToSelect.range.start.column+t.gapToSelect.textSize);
                    t.gapToSelect = null;
                    e.preventDefault();
                    e.stopPropagation();
                }
            });

            this.fail = false;
            this.reload();
        }
        catch(err) {
            // Something ugly happened. Probably ace editor hasn't been loaded
            this.fail = true;
        }
    }

    /**
     * The method that creates the gaps at all places containing the appropriate
     * marker (default {[ ... ]}).
     * Do not call until after this.editor has been instantiated.
     * @param {string} code The initial raw text code
     */
    AceGapfillerUi.prototype.createGaps = function(code) {
        this.gaps = [];
        /**
         * Escape special characters in a given string.
         * @param {string} s The input string.
         * @returns {string} The updated string, with escaped specials.
         */
        function reEscape(s) {
            var c, specials = '{[(*+\\', result='';
            for (var i = 0; i < s.length; i++) {
                c = s[i];
                for (var j = 0; j < specials.length; j++) {
                    if (c === specials[j]) {
                        c = '\\' + c;
                    }
                }
                result += c;
            }
            return result;
        }

        let lines = code.split(/\r?\n/);

        let sepLeft = reEscape('{[');
        let sepRight = reEscape(']}');
        let splitter = new RegExp(sepLeft + ' *((?:\\d+)|(?:\\d+- *\\d+)) *' + sepRight);

        let editorContent = "";
        for (let i = 0; i < lines.length; i++) {
            let bits = lines[i].split(splitter);
            editorContent += bits[0];

            let columnPos = bits[0].length;
            for (let j = 1; j < bits.length; j += 2) {
                let values = bits[j].split('-');
                let minWidth = parseInt(values[0]);
                let maxWidth = (values.length > 1 ? parseInt(values[1]) : Infinity);

                // Create new gap.
                let gap = new Gap(this.editor, i, columnPos, minWidth, maxWidth);
                gap.index = this.nextGapIndex;
                this.nextGapIndex += 1;
                this.gaps.push(gap);

                columnPos += minWidth;
                editorContent += ' '.repeat(minWidth);
                if (j + 1 < bits.length) {
                    editorContent += bits[j+1];
                    columnPos += bits[j+1].length;
                }

            }

            if (i < lines.length-1) {
                editorContent += '\n';
            }
        }
        this.editor.session.setValue(editorContent);
    };

    /**
     * Return the gap that the cursor is in. This will actually return a gap if
     * the cursor is 1 outside the gap as this will be needed for
     * backspace/insertion to work. Rigth now this is done as a simple
     * linear search but could be improved later.
     * @param {object} cursor The ace editor cursor position.
     * @returns {object} The gap that the cursor is current in, or null otherwise.
     */
    AceGapfillerUi.prototype.findCursorGap = function(cursor) {
        for (let i=0; i < this.gaps.length; i++) {
            let gap = this.gaps[i];
            if (gap.cursorInGap(cursor)) {
                return gap;
            }
        }
        return null;
    };

    AceGapfillerUi.prototype.failed = function() {
        return this.fail;
    };

    AceGapfillerUi.prototype.failMessage = function() {
        return 'ace_ui_notready';
    };


    // Sync to TextArea
    AceGapfillerUi.prototype.sync = function() {
        if (this.fail) {
            return; // Leave the text area alone if Ace load failed.
        }
        let serialisation = [];  // A list of field values.
        let empty = true;

        for (let i=0; i < this.gaps.length; i++) {
            let gap = this.gaps[i];
            let value = gap.getText();
            serialisation.push(value);
            if (value !== "") {
                empty = false;
            }
        }
        if (empty) {
            this.textArea.val('');
        } else {
            this.textArea.val(JSON.stringify(serialisation));
        }
    };

    // Sync every 2 seconds in case quiz closes automatically without user
    // action.
    AceGapfillerUi.prototype.syncIntervalSecs = (() => 2);

    // Reload the HTML fields from the given serialisation.
    AceGapfillerUi.prototype.reload = function() {
        let content = this.textArea.val();
        if (content) {
            try {
                let values = JSON.parse(content);
                for (let i = 0; i < this.gaps.length; i++) {
                    let value = i < values.length ? values[i]: '???';
                    this.gaps[i].insertText(this.gaps, this.gaps[i].range.start.column, value);
                }
            } catch(e) {
                // Just ignore errors
            }
        }
    };

    AceGapfillerUi.prototype.setLanguage = function(language) {
        var session = this.editor.getSession(),
            mode = this.findMode(language);
        if (mode) {
            session.setMode(mode.mode);
        }
    };

    AceGapfillerUi.prototype.getElement = function() {
        return this.editNode;
    };

    AceGapfillerUi.prototype.captureTab = function () {
        this.capturingTab = true;
        this.editor.commands.bindKeys({'Tab': 'indent', 'Shift-Tab': 'outdent'});
    };

    AceGapfillerUi.prototype.releaseTab = function () {
        this.capturingTab = false;
        this.editor.commands.bindKeys({'Tab': null, 'Shift-Tab': null});
    };

    AceGapfillerUi.prototype.setEventHandlers = function () {
        var TAB = 9,
            ESC = 27,
            KEY_M = 77,
            t = this;

        this.editor.getSession().on('change', function() {
            t.contents_changed = true;
        });

        this.editor.on('blur', function() {
            if (t.contents_changed) {
                t.textArea.trigger('change');
            }
        });

        this.editor.on('mousedown', function() {
            // Event order seems to be (\ is where the mouse button is pressed, / released):
            // Chrome: \ mousedown, mouseup, focusin / click.
            // Firefox/IE: \ mousedown, focusin / mouseup, click.
            t.clickInProgress = true;
        });

        this.editor.on('focus', function() {
            if (t.clickInProgress) {
                t.captureTab();
            } else {
                t.releaseTab();
            }
        });

        this.editor.on('click', function() {
            t.clickInProgress = false;
        });

        this.editor.container.addEventListener('keydown', function(e) {
            if (e.which === undefined || e.which !== 0) { // Normal keypress?
                if (e.keyCode === KEY_M && e.ctrlKey && !e.altKey) {
                    if (t.capturingTab) {
                        t.releaseTab();
                    } else {
                        t.captureTab();
                    }
                    e.preventDefault(); // Firefox uses this for mute audio in current browser tab.
                }
                else if (e.keyCode === ESC) {
                    t.releaseTab();
                }
                else if (!(e.shiftKey || e.ctrlKey || e.altKey || e.keyCode == TAB)) {
                    t.captureTab();
                }
            }
        }, true);
    };

    AceGapfillerUi.prototype.destroy = function () {
        this.sync();
        var focused;
        if (!this.fail) {
            // Proceed only if this wrapper was correctly constructed
            focused = this.editor.isFocused();
            this.editor.destroy();
            $(this.editNode).remove();
            if (focused) {
                this.textArea.focus();
                this.textArea[0].selectionStart = this.textArea[0].value.length;
            }
        }
    };

    AceGapfillerUi.prototype.hasFocus = function() {
        return this.editor.isFocused();
    };

    AceGapfillerUi.prototype.findMode = function (language) {
        var candidate,
            filename,
            result,
            candidates = [], // List of candidate modes.
            nameMap = {
                'octave': 'matlab',
                'nodejs': 'javascript',
                'c#': 'cs'
            };

        if (typeof language !== 'string') {
            return undefined;
        }
        if (language.toLowerCase() in nameMap) {
            language = nameMap[language.toLowerCase()];
        }

        candidates = [language, language.replace(/\d+$/, "")];
        for (var i = 0; i < candidates.length; i++) {
            candidate = candidates[i];
            filename = "input." + candidate;
            result = this.modelist.modesByName[candidate] ||
                this.modelist.modesByName[candidate.toLowerCase()] ||
                this.modelist.getModeForPath(filename) ||
                this.modelist.getModeForPath(filename.toLowerCase());

            if (result && result.name !== 'text') {
                return result;
            }
        }
        return undefined;
    };

    AceGapfillerUi.prototype.resize = function(w, h) {
        this.editNode.outerHeight(h);
        this.editNode.outerWidth(w);
        this.editor.resize();
    };

    /**
     * Allow fullscreen mode for the Ace Gapfiller UI.
     *
     * @return {Boolean} True if fullscreen mode is allowed, false otherwise.
     */
    AceGapfillerUi.prototype.allowFullScreen = function() {
        return true;
    };

    /**
     * Constructor for the Gap object that represents a gap in the source code
     * that the user is expected to fill.
     * @param {object} editor The Ace Editor object.
     * @param {int} row The row within the text of the gap.
     * @param {int} column The column within the text of the gap.
     * @param {int} minWidth The minimum width (in characters) of the gap.
     * @param {int} maxWidth The maximum width (in characters) of the gap.
     */
    function Gap(editor, row, column, minWidth, maxWidth=Infinity) {
        this.editor = editor;

        this.minWidth = minWidth;
        this.maxWidth = maxWidth;

        this.range = new Range(row, column, row, column+minWidth);
        this.textSize = 0;

        // Create markers
        this.editor.session.addMarker(this.range, "ace-gap-outline", "text", true);
        this.editor.session.addMarker(this.range, "ace-gap-background", "text", false);
    }

    Gap.prototype.cursorInGap = function(cursor) {
        return (cursor.row >= this.range.start.row && cursor.column >= this.range.start.column &&
                cursor.row <= this.range.end.row && cursor.column <= this.range.end.column);
    };

    Gap.prototype.getWidth = function() {
        return (this.range.end.column-this.range.start.column);
    };

    Gap.prototype.changeWidth = function(gaps, delta) {
        this.range.end.column += delta;

        // Update any gaps that come after this one on the same line
        for (let i=0; i < gaps.length; i++) {
            let other = gaps[i];
            if (other.range.start.row === this.range.start.row && other.range.start.column > this.range.end.column) {
                other.range.start.column += delta;
                other.range.end.column += delta;
            }
        }

        this.editor.$onChangeBackMarker();
        this.editor.$onChangeFrontMarker();
    };

    Gap.prototype.insertChar = function(gaps, pos, char) {
        if (this.textSize === this.getWidth() && this.getWidth() < this.maxWidth) {    // Grow the size of gap and insert char.
            this.changeWidth(gaps, 1);
            this.textSize += 1;  // Important to record that texSize has increased before insertion.
            this.editor.session.insert(pos, char);
        } else if (this.textSize < this.maxWidth) {   // Insert char.
            this.editor.session.remove(new Range(pos.row, this.range.end.column-1, pos.row, this.range.end.column));
            this.textSize += 1;  // Important to record that texSize has increased before insertion.
            this.editor.session.insert(pos, char);
        }
    };

    Gap.prototype.deleteChar = function(gaps, pos) {
        this.textSize -= 1;
        this.editor.session.remove(new Range(pos.row, pos.column, pos.row, pos.column+1));

        if (this.textSize >= this.minWidth) {
            this.changeWidth(gaps, -1);  // Shrink the size of the gap.
        } else {
            // Put new space at end so everything is shifted across.
            this.editor.session.insert({row: pos.row, column: this.range.end.column-1}, fillChar);
        }
    };

    Gap.prototype.deleteRange = function(gaps, start, end) {
        for (let i = start; i < end; i++) {
            if (start < this.range.start.column+this.textSize) {
                this.deleteChar(gaps, {row: this.range.start.row, column: start});
            }
        }
    };

    Gap.prototype.insertText = function(gaps, start, text) {
        for (let i = 0; i < text.length; i++) {
            if (start+i < this.range.start.column+this.maxWidth) {
                this.insertChar(gaps, {row: this.range.start.row, column: start+i}, text[i]);
            }
        }
    };

    Gap.prototype.getText = function() {
        return this.editor.session.getTextRange(new Range(this.range.start.row, this.range.start.column,
                                                this.range.end.row, this.range.start.column+this.textSize));

    };

    return {
        Constructor: AceGapfillerUi
    };
});
