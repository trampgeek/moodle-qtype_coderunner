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
 *     {[ cols ]}
 *
 * or
 *
 *     {[ cols-maxCols ]}
 *
 * where cols and maxCols are integer literals. These respectively inject a single-line
 * "gap" into the editor of the specified width and maximum width. If maxCols is not
 * specified then the gap width can grow without bound.
 *
 * A gap can also span multiple lines, using a tag of the form
 *
 *     {[ rows, cols ]}
 *
 * where each of rows and cols is, just as above, either a plain integer or a
 * "min-max" range, e.g. {[ 3-8, 20-60 ]}. This defines a gap of (initially) 'rows'
 * lines, each of width 'cols', that the student can grow (by pressing Enter to add a
 * new line, or by typing/pasting beyond the current width) up to the given maxima, if
 * any. Whatever literal text precedes and follows the tag on its source line is
 * reproduced, unchanged, on every line of the gap - this is how, for example, a
 * multi-line gap can be kept indented (by preceding the tag with spaces) without the
 * UI needing to know anything about the language being edited. All lines of a given
 * gap share the same width, which grows and shrinks in lockstep across every line as
 * the student types.
 *
 * The serialisation of the answer box contents, i.e. the text that
 * copied back into the textarea for submissions
 * as the answer, is simply a list of all the field values (strings), in order.
 * The value of a multi-line gap is its lines joined with newline characters.
 *
 * As a special case of the serialisation, if the value list is empty, the
 * serialisation itself is the empty string.
 *
 * The delimiters for the gap tags are by default '{[' and
 * ']}'.
 *
 * @module qtype_coderunner/ui_ace_gapfiller
 * @copyright  Richard Lobb, 2019, 2026 The University of Canterbury
 * @copyright  Matthew Toohey, 2021, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var Range;  // Can't load this until ace has loaded.
    const fillChar = " ";
    const validChars = /[ !"#$%&'()*+,`\-./0-9\p{L}:;<=>?@\[\]\\^_{}|~]/u;
    const ACE_LIGHT_THEME = 'ace/theme/textmate';
    // Extra pixels below the last line, so a gap on it isn't drawn tight against the
    // bottom edge. Reported to the wrapper as part of requiredHeight rather than added
    // to the editor here, so that it makes the wrapper taller instead of eating into
    // the gutter the wrapper keeps below us for the full-screen button and drag handle.
    const BREATHING_SPACE = 10;

    /**
     * Constructor for the Ace interface object.
     * Stores parameters only; actual Ace initialisation happens in ready().
     * @param {string} textareaId The ID of the textarea html element.
     * @param {int} w The width of the text area in pixels.
     * @param {int} h The height of the text area in pixels.
     * @param {object} uiParams The UI parameter specifier object.
     */
    function AceGapfillerUi(textareaId, w, h, uiParams) {
        this.textArea = document.getElementById(textareaId);
        this.textareaId = textareaId;
        this.wrapper = document.getElementById(textareaId + '_wrapper');
        this.focused = this.textArea === document.activeElement;
        this.uiParams = uiParams;
        this.lang = uiParams.lang;
        this.w = w;
        this.h = h;
        this.gaps = [];
        this.source = uiParams.ui_source || 'globalextra';
        this.nextGapIndex = 0;
        if (this.source !== 'globalextra' && this.source !== 'test0') {
            alert('Invalid source for code in ui_ace_gapfiller');
            this.source = 'globalextra';
        }
        this.editNode = null;
        this.editor = null;
        this.fail = false;
    }

    /**
     * Initialise the Ace editor, polling until window.ace is available.
     * Resolves when ready; rejects (after 3 s) if Ace never loads.
     * @returns {Promise}
     */
    AceGapfillerUi.prototype.ready = function() {
        const t = this;
        const MAX_WAIT_MS = 3000;
        const POLL_MS = 50;
        return new Promise(function(resolve, reject) {
            var elapsed = 0;
            /**
             * Poll until window.ace is available, then initialise the editor.
             */
            function tryInit() {
                if (!window.ace) {
                    elapsed += POLL_MS;
                    if (elapsed >= MAX_WAIT_MS) {
                        t.fail = true;
                        reject(new Error('Ace editor not available'));
                        return;
                    }
                    setTimeout(tryInit, POLL_MS);
                    return;
                }
                try {
                    const wrapper = t.wrapper;
                    const focused = t.focused;
                    const uiParams = t.uiParams;
                    const lang = t.lang;

                    let code = "";
                    if (t.source === 'globalextra') {
                        code = t.textArea.dataset.globalextra;
                    } else {
                        code = t.textArea.dataset.test0;
                    }

                    window.ace.require("ace/ext/language_tools");
                    Range = window.ace.require("ace/range").Range;
                    t.modelist = window.ace.require('ace/ext/modelist');

                    t.enabled = false;
                    t.contents_changed = false;
                    t.capturingTab = false;
                    t.clickInProgress = false;

                    t.editNode = document.createElement('div');
                    t.editNode.className = 'ace-gapfiller';
                    t.editNode.style.resize = 'none';
                    t.editNode.style.height = t.h + 'px';
                    t.editNode.style.width = '100%';
                    if (uiParams.line_height) {
                        // Apply the author's chosen line spacing before Ace is created, so
                        // Ace's own font-metric measurement picks it up from the outset and
                        // uses it for its internal sizing too. The same value is what
                        // userinterfacewrapper.js used to size the box this editor sits in,
                        // so the two can't disagree. Left unset, the editor renders at
                        // whatever line-height the standard CodeRunner Ace CSS gives it.
                        t.editNode.style.lineHeight = uiParams.line_height + 'px';

                        // Ace sizes each gap marker to the full line height, as an inline
                        // style, so a taller line just gives a taller box - no help when
                        // gaps on adjacent lines would otherwise collide. Hand the CSS the
                        // numbers Ace won't: the height the text itself needs, and the
                        // spare space to centre it in.
                        const naturalHeight = t.textArea.current_ui_wrapper.DEFAULT_LINE_HEIGHT;
                        if (uiParams.line_height > naturalHeight) {
                            t.editNode.classList.add('ace-gapfiller-spaced');
                            t.editNode.style.setProperty('--gap-height', naturalHeight + 'px');
                            t.editNode.style.setProperty('--gap-inset',
                                    (uiParams.line_height - naturalHeight) / 2 + 'px');
                        }
                    }

                    t.editor = window.ace.edit(t.editNode);
                    if (t.textArea.readOnly) {
                        t.editor.setReadOnly(true);
                    }

                    t.editor.setOptions({
                        displayIndentGuides: false,
                        dragEnabled: false,
                        enableBasicAutocompletion: true,
                        newLineMode: "unix",
                        // Force off regardless of the user's global Ace wrap preference
                        // (persisted in localStorage and otherwise inherited here): gap
                        // markers are positioned by document column, and soft-wrapping a
                        // row onto more than one screen line breaks that box geometry.
                        wrap: false,
                    });
                    t.editor.$blockScrolling = Infinity;

                    // Use the uiParams theme if provided else use light.
                    if (uiParams.theme) {
                        t.editor.setTheme("ace/theme/" + uiParams.theme);
                    } else {
                        t.editor.setTheme(ACE_LIGHT_THEME);
                    }

                    t.setLanguage(lang);
                    t.setEventHandlers(t.textArea);
                    t.captureTab();

                    // Try to tell Moodle about parts of the editor with z-index.
                    // It is hard to be sure if this is complete. ACE adds all its CSS using JavaScript.
                    // Here, we just deal with things that are known to cause a problem.
                    // Can't do these operations until editor has rendered. So ...
                    t.editor.renderer.on('afterRender', function() {
                        const gutter = wrapper.querySelector('.ace_gutter');
                        if (!gutter || gutter.classList.contains('moodle-has-zindex')) {
                            return;  // So we only do what follows once.
                        }
                        gutter.classList.add('moodle-has-zindex');

                        if (focused) {
                            t.editor.focus();
                            t.editor.navigateFileEnd();
                        }
                        t.aceLabel = wrapper.querySelector('.answerprompt');
                        t.aceLabel?.setAttribute('for', 'ace_' + t.textareaId);

                        t.aceTextarea = wrapper.querySelector('.ace_text-input');
                        t.aceTextarea?.setAttribute('id', 'ace_' + t.textareaId);
                    });

                    t.createGaps(code);

                    // Growing or shrinking an expandable gap changes how many rows
                    // there are to show, so the wrapper has to be asked to re-read
                    // our requiredHeight whenever the document changes.
                    t.editor.session.on('change', function() {
                        t.textArea.current_ui_wrapper?.updateHeight();
                    });

                    // Intercept commands sent to ace.
                    t.editor.commands.on("exec", function(e) {
                        let cursor = t.editor.selection.getCursor();
                        let commandName = e.command.name;
                        let selectionRange = t.editor.getSelectionRange();

                        let gap = t.findCursorGap(cursor);

                        if (commandName.startsWith("go")) {  // If command just moves the cursor then do nothing.
                            let r = gap !== null ? gap.rowIndexAt(cursor.row) : -1;
                            if (gap !== null && commandName === "gotoright" && r !== -1 &&
                                    cursor.column === gap.startCol + gap.textSizes[r]) {
                                // In this case we jump out of gap over the empty space that contains
                                // nothing that the user has entered.
                                t.editor.moveCursorTo(cursor.row, gap.startCol + gap.getWidth() + 1);
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
                            t.editor.moveCursorTo(nextGap.rowRanges[0].start.row, nextGap.startCol + nextGap.textSizes[0]);
                            t.editor.selection.clearSelection(); // Clear selection.

                        } else if (commandName === "selectall") {
                            // Select all text on the current line of the gap (a gap can span several lines).
                            t.editor.selection.setSelectionRange(new Range(cursor.row, gap.startCol,
                                                                 cursor.row, gap.startCol + gap.getWidth()), false);

                        } else if (t.editor.selection.isEmpty()) {
                            // User is not selecting multiple characters.
                            let r = gap.rowIndexAt(cursor.row);
                            if (commandName === "insertstring") {
                                let char = e.args;
                                if (char === "\n") {
                                    // Enter within a gap: split this line of the gap in two, if the gap
                                    // isn't already at its maximum number of lines.
                                    let newCursor = gap.splitRowAt(t.gaps, cursor);
                                    if (newCursor !== null) {
                                        t.editor.moveCursorTo(newCursor.row, newCursor.column);
                                    }
                                } else if (validChars.test(char)) {
                                    // Only allow user to insert 'valid' chars.
                                    gap.insertChar(t.gaps, cursor, char);
                                }
                            } else if (commandName === "backspace") {
                                if (cursor.column > gap.startCol && gap.textSizes[r] > 0) {
                                    // Only delete chars that are actually in the gap.
                                    gap.deleteChar(t.gaps, {row: cursor.row, column: cursor.column-1});
                                } else if (cursor.column === gap.startCol && r > 0) {
                                    // At the start of a line other than the gap's first: join with the line above.
                                    let newCursor = gap.joinRows(t.gaps, r-1);
                                    t.editor.moveCursorTo(newCursor.row, newCursor.column);
                                }
                            } else if (commandName === "del") {
                                if (cursor.column < gap.startCol + gap.textSizes[r]) {
                                    // Only delete chars that are actually in the gap.
                                    gap.deleteChar(t.gaps, cursor);
                                } else if (cursor.column === gap.startCol + gap.textSizes[r] && r < gap.numRows() - 1) {
                                    // At the end of a line other than the gap's last: join with the line below.
                                    let newCursor = gap.joinRows(t.gaps, r);
                                    t.editor.moveCursorTo(newCursor.row, newCursor.column);
                                }
                            }
                            t.editor.selection.clearSelection(); // Keep selection clear.

                        } else if (!t.editor.selection.isEmpty() && gap.cursorInGap(selectionRange.start)
                                   && gap.cursorInGap(selectionRange.end) && selectionRange.start.row === selectionRange.end.row) {
                            // User is selecting multiple characters, all on one line of the gap.

                            // These are the commands that remove the selected text.
                            if (commandName === "insertstring" || commandName === "backspace"
                                || commandName === "del" || commandName === "paste"
                                || commandName === "cut") {

                                gap.deleteRange(t.gaps, selectionRange.start.row,
                                    selectionRange.start.column, selectionRange.end.column);
                                t.editor.selection.clearSelection(); // Clear selection.
                            }

                            if (commandName === "insertstring") {
                                let char = e.args;
                                if (char !== "\n" && validChars.test(char)) {
                                    gap.insertChar(t.gaps, selectionRange.start, char);
                                }
                            }
                        }

                        // Paste text into gap. Any newlines in the pasted text split the gap's
                        // current line, exactly as if the user had pressed Enter at that point.
                        if (gap !== null && commandName === "paste") {
                            let newCursor = gap.insertText(t.gaps, selectionRange.start, e.args.text);
                            t.editor.moveCursorTo(newCursor.row, newCursor.column);
                        }

                        e.preventDefault();
                        e.stopPropagation();
                    });

                    // Move cursor to where it should be if we click on a gap.
                    t.editor.selection.on('changeCursor', function() {
                        let cursor = t.editor.selection.getCursor();
                        let gap = t.findCursorGap(cursor);
                        if (gap !== null) {
                            let r = gap.rowIndexAt(cursor.row);
                            if (r !== -1 && cursor.column > gap.startCol + gap.textSizes[r]) {
                                t.editor.moveCursorTo(cursor.row, gap.startCol + gap.textSizes[r]);
                            }
                        }
                    });

                    t.gapToSelect = null;    // Stores gap that has been selected with triple click.
                    t.rowToSelect = null;    // The row (within gapToSelect) that was triple-clicked.

                    // Select all text on the clicked line of the gap on triple click within a gap.
                    t.editor.on("tripleclick", function(e) {
                        let cursor = t.editor.selection.getCursor();
                        let gap = t.findCursorGap(cursor);
                        if (gap !== null) {
                            t.editor.selection.setSelectionRange(new Range(cursor.row, gap.startCol,
                                                                           cursor.row, gap.startCol + gap.getWidth()), false);
                            t.gapToSelect = gap;
                            t.rowToSelect = cursor.row;
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    });

                    // Annoying hack to ensure the tripple click thing works.
                    t.editor.on("click", function(e) {
                        if (t.gapToSelect) {
                            let r = t.gapToSelect.rowIndexAt(t.rowToSelect);
                            let col = t.gapToSelect.startCol + (r === -1 ? 0 : t.gapToSelect.textSizes[r]);
                            t.editor.moveCursorTo(t.rowToSelect, col);
                            t.gapToSelect = null;
                            t.rowToSelect = null;
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    });

                    t.fail = false;
                    t.reload();
                    resolve();
                } catch (err) {
                    t.fail = true;
                    reject(err);
                }
            }
            tryInit();
        });
    };

    /**
     * Parse the contents of a single gap tag dimension, which is either a plain
     * integer (e.g. "20") or a min-max range (e.g. "20-40").
     * @param {string} spec The dimension text to parse.
     * @returns {object} An object {min: int, max: int}. If no max was given, max is Infinity.
     */
    function parseDimension(spec) {
        let parts = spec.split('-');
        return {
            min: parseInt(parts[0], 10),
            max: parts.length > 1 ? parseInt(parts[1], 10) : Infinity
        };
    }

    /**
     * Shift the row number of every row of every gap that lies strictly after
     * afterRow, by delta. Used to keep all gaps' bookkeeping in sync whenever a
     * line is physically inserted into, or removed from, the document by one gap
     * growing or shrinking its number of rows.
     * @param {Array} gaps The full list of gaps in the editor.
     * @param {int} afterRow Only rows after this document row are shifted.
     * @param {int} delta The amount (+1 or -1) to shift by.
     */
    function shiftRowsAfter(gaps, afterRow, delta) {
        for (let i = 0; i < gaps.length; i++) {
            let rowRanges = gaps[i].rowRanges;
            for (let r = 0; r < rowRanges.length; r++) {
                if (rowRanges[r].start.row > afterRow) {
                    rowRanges[r].start.row += delta;
                    rowRanges[r].end.row += delta;
                }
            }
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
        let dim = '\\d+(?: *- *\\d+)?';
        let splitter = new RegExp(sepLeft + ' *(' + dim + '(?: *, *' + dim + ')?) *' + sepRight);

        let outputLines = [];  // The lines of the editor content, built up as we go.

        for (let i = 0; i < lines.length; i++) {
            let bits = lines[i].split(splitter);
            let prefix = bits[0];
            let currentLine = prefix;

            for (let j = 1; j < bits.length; j += 2) {
                let dims = bits[j].split(',').map(s => s.trim());
                let suffix = (j + 1 < bits.length) ? bits[j + 1] : '';
                let rowsSpec = dims.length > 1 ? parseDimension(dims[0]) : {min: 1, max: 1};
                let colsSpec = parseDimension(dims[dims.length - 1]);

                let gap = new Gap(this.editor, outputLines.length, currentLine.length, rowsSpec, colsSpec, prefix, suffix);
                gap.index = this.nextGapIndex;
                this.nextGapIndex += 1;
                this.gaps.push(gap);

                currentLine += ' '.repeat(colsSpec.min);

                if (gap.numRows() > 1) {
                    // Multi-row gap: emit all but its last line now, each reproducing
                    // the prefix/suffix. Its last line is left in currentLine, both so
                    // it gets pushed exactly once, below, by the same code path used
                    // for every other line, and so that anything else on the same
                    // source line after this tag (not a combination we specially
                    // support) attaches to that last line rather than being lost.
                    currentLine += suffix;
                    outputLines.push(currentLine);
                    for (let r = 1; r < gap.numRows() - 1; r++) {
                        outputLines.push(prefix + ' '.repeat(colsSpec.min) + suffix);
                    }
                    currentLine = prefix + ' '.repeat(colsSpec.min) + suffix;
                } else {
                    currentLine += suffix;
                }
            }
            outputLines.push(currentLine);
        }
        this.editor.session.setValue(outputLines.join('\n'));
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
        if (this.fail || !this.editor) {
            return; // Leave the text area alone if Ace load failed or not yet ready.
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
            this.textArea.value = '';
        } else {
            this.textArea.value = JSON.stringify(serialisation);
        }
    };

    // Sync every 2 seconds in case quiz closes automatically without user
    // action.
    AceGapfillerUi.prototype.syncIntervalSecs = (() => 2);

    // Reload the HTML fields from the given serialisation.
    AceGapfillerUi.prototype.reload = function() {
        let content = this.textArea.value;
        if (content) {
            try {
                let values = JSON.parse(content);
                for (let i = 0; i < this.gaps.length; i++) {
                    let value = i < values.length ? values[i]: '???';
                    let gap = this.gaps[i];
                    gap.insertText(this.gaps, {row: gap.rowRanges[0].start.row, column: gap.startCol}, value);
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

    /**
     * Called once the editor is in the live DOM. Ace measures its font
     * asynchronously and can't measure at all while detached, so renderer
     * .lineHeight is still 0 at this point; ask for the measurement now rather
     * than waiting for Ace's observer, since the wrapper is about to call
     * requiredHeight, which depends on it.
     */
    AceGapfillerUi.prototype.postInsert = function() {
        this.editor.renderer.updateFontSize();
    };

    /**
     * The height needed for every row of the document, which grows and shrinks
     * as the student adds lines to, or deletes them from, an expandable
     * multiline gap, plus a little space below the last line so that a gap on
     * it isn't drawn tight against the bottom edge.
     * @returns {int} The required height of the editor, in pixels.
     */
    AceGapfillerUi.prototype.requiredHeight = function() {
        return this.editor.session.getLength() * this.editor.renderer.lineHeight + BREATHING_SPACE;
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
                t.textArea.dispatchEvent(new Event('change'));
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
        if (this.editor) {
            const focused = this.editor.isFocused();
            this.editor.destroy();
            this.editNode.remove();
            if (focused) {
                this.textArea.focus();
                this.textArea.selectionStart = this.textArea.value.length;
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
        this.editNode.style.height = h + 'px';
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
     * that the user is expected to fill. A gap has one or more lines (rows),
     * all of which share the same, currently-common, width - i.e. the gap is
     * always a rectangle, though the box can grow taller (more rows) or wider
     * (more columns) as the student types, within the given bounds.
     * @param {object} editor The Ace Editor object.
     * @param {int} row The initial row within the text of the first line of the gap.
     * @param {int} column The column within the text of the gap.
     * @param {object} rowsSpec {min, max} number of rows in the gap.
     * @param {object} colsSpec {min, max} width, in columns, of each row of the gap.
     * @param {string} prefix The text (if any) preceding the tag on its source line.
     *  Reproduced literally on every row of the gap beyond the first.
     * @param {string} suffix The text (if any) following the tag on its source line.
     *  Reproduced literally on every row of the gap beyond the first.
     */
    function Gap(editor, row, column, rowsSpec, colsSpec, prefix, suffix) {
        this.editor = editor;

        // A gap always needs at least one row to give the cursor somewhere to go,
        // unlike a zero-width single-line gap, which still has a valid column.
        this.minRows = Math.max(1, rowsSpec.min);
        this.maxRows = Math.max(this.minRows, rowsSpec.max);
        this.minCols = colsSpec.min;
        this.maxCols = colsSpec.max;
        this.prefix = prefix;
        this.suffix = suffix;
        this.startCol = column;

        this.rowRanges = [];
        this.outlineIds = [];
        this.backgroundIds = [];
        this.textSizes = [];

        for (let r = 0; r < this.minRows; r++) {
            this.addRowMarkers(row + r, this.minCols);
            this.textSizes.push(0);
        }
        // Every row was provisionally styled as a middle row (see addRowMarkers) -
        // now that the final row count is known, fix up the true top and bottom.
        this.restyleRowMarker(0);
        if (this.minRows > 1) {
            this.restyleRowMarker(this.minRows - 1);
        }
    }

    /**
     * Create the outline/background markers for one new row of the gap and
     * record them (and the row's Range) in this gap's bookkeeping arrays.
     * Does not touch the document itself, nor this.textSizes. The outline is
     * provisionally styled as a middle row; callers that change which row is
     * first or last must fix up styling via restyleRowMarker.
     * @param {int} absRow The absolute document row of the new gap row.
     * @param {int} width The current width (number of columns) of the gap.
     */
    Gap.prototype.addRowMarkers = function(absRow, width) {
        let range = new Range(absRow, this.startCol, absRow, this.startCol + width);
        this.rowRanges.push(range);
        this.backgroundIds.push(this.editor.session.addMarker(range, "ace-gap-background", "text", false));
        this.outlineIds.push(this.editor.session.addMarker(range, "ace-gap-outline-middle", "text", true));
    };

    Gap.prototype.numRows = function() {
        return this.rowRanges.length;
    };

    /**
     * The outline CSS class appropriate to row r (0-based), given the gap's
     * current number of rows: a single-row gap keeps the original all-round
     * outline; a multi-row gap gets a class per row that only borders the
     * sides that should be visible, so the rows read as one combined box
     * rather than as separate stacked rectangles.
     * @param {int} r A row index within this gap.
     * @returns {string} The CSS class to use for that row's outline marker.
     */
    Gap.prototype.outlineClassFor = function(r) {
        if (this.numRows() === 1) {
            return "ace-gap-outline";
        } else if (r === 0) {
            return "ace-gap-outline-top";
        } else if (r === this.numRows() - 1) {
            return "ace-gap-outline-bottom";
        }
        return "ace-gap-outline-middle";
    };

    /**
     * The background CSS class appropriate to this gap, which differs only in
     * that a single-row gap's fill can be shortened to match its outline when
     * a custom line_height leaves room to do so. The rows of a multi-row gap
     * have to stay flush, or the fill would show seams between them.
     * @returns {string} The CSS class to use for a row's background marker.
     */
    Gap.prototype.backgroundClassFor = function() {
        return this.numRows() === 1 ? "ace-gap-background-single" : "ace-gap-background";
    };

    /**
     * Recreate row r's outline and background markers using the classes
     * appropriate to its current position. Needed whenever a row is added or
     * removed at either end of the gap, since that can change whether row 0 or
     * the last row counts as "top"/"bottom"/"single" (interior rows are never
     * affected).
     * @param {int} r A row index within this gap.
     */
    Gap.prototype.restyleRowMarker = function(r) {
        this.editor.session.removeMarker(this.outlineIds[r]);
        this.outlineIds[r] = this.editor.session.addMarker(this.rowRanges[r], this.outlineClassFor(r), "text", true);
        this.editor.session.removeMarker(this.backgroundIds[r]);
        this.backgroundIds[r] = this.editor.session.addMarker(
                this.rowRanges[r], this.backgroundClassFor(), "text", false);
    };

    /**
     * @param {int} absRow An absolute document row number.
     * @returns {int} The index, within this gap, of the row at absRow, or -1
     * if this gap has no row there.
     */
    Gap.prototype.rowIndexAt = function(absRow) {
        for (let r = 0; r < this.rowRanges.length; r++) {
            if (this.rowRanges[r].start.row === absRow) {
                return r;
            }
        }
        return -1;
    };

    Gap.prototype.cursorInGap = function(cursor) {
        let r = this.rowIndexAt(cursor.row);
        return r !== -1 && cursor.column >= this.startCol && cursor.column <= this.startCol + this.getWidth();
    };

    // The current width (number of columns), shared by every row of the gap.
    Gap.prototype.getWidth = function() {
        return this.rowRanges[0].end.column - this.rowRanges[0].start.column;
    };

    // The most text the student has typed into any one row of the gap.
    Gap.prototype.maxTextSize = function() {
        return Math.max(...this.textSizes);
    };

    /**
     * Change the width of every row of this gap by delta, keeping the box a
     * rectangle, and shift every other gap that shares a row with this one
     * (and sits to its right) by the same amount, so as to stay aligned with it.
     * editedRow is the row the caller is itself about to insert/remove a real
     * character on - every OTHER row must have its actual document line
     * padded or trimmed here to keep pace, since Ace clips a marker's column
     * back down to the line's real length whenever it would otherwise extend
     * past it (ace.js's $clipPositionToDocument, used by toScreenRange before
     * every marker is drawn).
     * @param {Array} gaps The full list of gaps in the editor.
     * @param {int} delta The change (+1 or -1) in width.
     * @param {int} editedRow The absolute document row the caller is handling itself.
     */
    Gap.prototype.changeWidth = function(gaps, delta, editedRow) {
        for (let r = 0; r < this.rowRanges.length; r++) {
            let range = this.rowRanges[r];
            if (range.start.row !== editedRow) {
                if (delta > 0) {
                    this.editor.session.insert({row: range.start.row, column: range.end.column}, fillChar.repeat(delta));
                } else {
                    let end = range.end.column;
                    this.editor.session.remove(new Range(range.start.row, end + delta, range.start.row, end));
                }
            }
            range.end.column += delta;
        }

        for (let i = 0; i < gaps.length; i++) {
            let other = gaps[i];
            if (other === this) {
                continue;
            }
            let needsShift = false;
            for (let r = 0; r < this.rowRanges.length && !needsShift; r++) {
                let myRow = this.rowRanges[r].start.row;
                for (let s = 0; s < other.rowRanges.length; s++) {
                    if (other.rowRanges[s].start.row === myRow &&
                            other.rowRanges[s].start.column > this.rowRanges[r].start.column) {
                        needsShift = true;
                        break;
                    }
                }
            }
            if (needsShift) {
                other.startCol += delta;
                for (let s = 0; s < other.rowRanges.length; s++) {
                    other.rowRanges[s].start.column += delta;
                    other.rowRanges[s].end.column += delta;
                }
            }
        }

        this.editor.$onChangeBackMarker();
        this.editor.$onChangeFrontMarker();
    };

    Gap.prototype.insertChar = function(gaps, pos, char) {
        let r = this.rowIndexAt(pos.row);
        if (r === -1) {
            return;
        }
        if (this.textSizes[r] === this.getWidth() && this.getWidth() < this.maxCols) {    // Grow the size of gap and insert char.
            this.changeWidth(gaps, 1, pos.row);
            this.textSizes[r] += 1;  // Important to record that texSize has increased before insertion.
            this.editor.session.insert(pos, char);
        } else if (this.textSizes[r] < this.maxCols) {   // Insert char.
            let end = this.startCol + this.getWidth();
            this.editor.session.remove(new Range(pos.row, end - 1, pos.row, end));
            this.textSizes[r] += 1;  // Important to record that texSize has increased before insertion.
            this.editor.session.insert(pos, char);
        }
    };

    Gap.prototype.deleteChar = function(gaps, pos) {
        let r = this.rowIndexAt(pos.row);
        if (r === -1) {
            return;
        }
        this.textSizes[r] -= 1;
        this.editor.session.remove(new Range(pos.row, pos.column, pos.row, pos.column+1));

        // Only shrink the shared box once no row still needs the current width.
        if (this.maxTextSize() < this.getWidth() && this.getWidth() > this.minCols) {
            this.changeWidth(gaps, -1, pos.row);  // Shrink the size of the gap.
        } else {
            // Put new space at end so everything is shifted across.
            this.editor.session.insert({row: pos.row, column: this.startCol + this.getWidth() - 1}, fillChar);
        }
    };

    Gap.prototype.deleteRange = function(gaps, row, start, end) {
        for (let i = start; i < end; i++) {
            let r = this.rowIndexAt(row);
            if (start < this.startCol + this.textSizes[r]) {
                this.deleteChar(gaps, {row: row, column: start});
            }
        }
    };

    // Return the text the student has typed into row r (0-based, within this gap).
    Gap.prototype.getRowText = function(r) {
        let row = this.rowRanges[r].start.row;
        return this.editor.session.getTextRange(new Range(row, this.startCol, row, this.startCol + this.textSizes[r]));
    };

    // Replace the text of row r (0-based, within this gap) with the given text,
    // truncated to maxCols if necessary.
    Gap.prototype.setRowText = function(gaps, r, text) {
        let row = this.rowRanges[r].start.row;
        while (this.textSizes[r] > 0) {
            this.deleteChar(gaps, {row: row, column: this.startCol + this.textSizes[r] - 1});
        }
        for (let i = 0; i < text.length && i < this.maxCols; i++) {
            this.insertChar(gaps, {row: row, column: this.startCol + this.textSizes[r]}, text[i]);
        }
    };

    /**
     * Handle Enter being pressed at the given cursor position within this gap,
     * splitting the row's text there. If a next row already exists - as it
     * will, up to minRows, from the moment the gap is created - the trailing
     * text simply moves into it (mirroring the way typing into a single-line
     * gap fills its existing width before growing it), with no change to the
     * number of rows. Only once the cursor is in the last existing row does
     * this actually grow the gap by a row, and then only up to maxRows.
     * @param {Array} gaps The full list of gaps in the editor.
     * @param {object} cursor The current cursor position.
     * @returns {object} The new cursor position, or null if nothing happened
     *  (cursor not in this gap, or already at the last row and at maxRows).
     */
    Gap.prototype.splitRowAt = function(gaps, cursor) {
        let r = this.rowIndexAt(cursor.row);
        if (r === -1) {
            return null;
        }
        let colInRow = cursor.column - this.startCol;
        let rowText = this.getRowText(r);
        let before = rowText.slice(0, colInRow);
        let after = rowText.slice(colInRow);

        if (r < this.numRows() - 1) {
            // A next row already exists: move the trailing text into it
            // rather than creating a whole new row.
            let nextRowText = this.getRowText(r + 1);
            this.setRowText(gaps, r, before);
            this.setRowText(gaps, r + 1, after + nextRowText);
            return {row: this.rowRanges[r + 1].start.row, column: this.startCol};
        }

        if (this.numRows() >= this.maxRows) {
            return null;
        }

        // In the last existing row and still below the maximum: grow a new row.
        let absRow = cursor.row;
        let width = this.getWidth();
        let lineLen = this.editor.session.getLine(absRow).length;

        this.editor.session.insert({row: absRow, column: lineLen}, '\n' + this.prefix + ' '.repeat(width) + this.suffix);
        shiftRowsAfter(gaps, absRow, 1);

        let newRange = new Range(absRow + 1, this.startCol, absRow + 1, this.startCol + width);
        this.rowRanges.splice(r + 1, 0, newRange);
        this.backgroundIds.splice(r + 1, 0, this.editor.session.addMarker(newRange, "ace-gap-background", "text", false));
        // The new row is always the new last row, so it's always styled as "bottom".
        this.outlineIds.splice(r + 1, 0, this.editor.session.addMarker(newRange, "ace-gap-outline-bottom", "text", true));
        this.textSizes.splice(r + 1, 0, 0);
        // The old last row is no longer last - restyle it as "top" or "middle".
        this.restyleRowMarker(r);

        this.setRowText(gaps, r, before);
        this.setRowText(gaps, r + 1, after);

        this.editor.$onChangeBackMarker();
        this.editor.$onChangeFrontMarker();

        return {row: absRow + 1, column: this.startCol};
    };

    /**
     * Handle Backspace/Delete merging row r+1 of this gap into row r, as if
     * the student had deleted the line break between them. Above minRows this
     * removes row r+1 from the document entirely, exactly reversing the row
     * growth done by splitRowAt. At minRows the row itself can't go away (just
     * as a column at the minimum width can't either), so only the content
     * moves up, leaving row r+1 in place but empty.
     * @param {Array} gaps The full list of gaps in the editor.
     * @param {int} r The (0-based) row, within this gap, to merge its successor into.
     * @returns {object} The resulting cursor position (end of row r's original text).
     */
    Gap.prototype.joinRows = function(gaps, r) {
        let merged = (this.getRowText(r) + this.getRowText(r + 1)).slice(0, this.maxCols);
        let cursor = {row: this.rowRanges[r].start.row, column: this.startCol + this.textSizes[r]};

        if (this.numRows() > this.minRows) {
            let upperAbsRow = this.rowRanges[r].start.row;
            let lowerAbsRow = this.rowRanges[r + 1].start.row;
            let upperLineLen = this.editor.session.getLine(upperAbsRow).length;
            let lowerLineLen = this.editor.session.getLine(lowerAbsRow).length;

            this.editor.session.removeMarker(this.outlineIds[r + 1]);
            this.editor.session.removeMarker(this.backgroundIds[r + 1]);
            this.outlineIds.splice(r + 1, 1);
            this.backgroundIds.splice(r + 1, 1);
            this.rowRanges.splice(r + 1, 1);
            this.textSizes.splice(r + 1, 1);

            this.editor.session.remove(new Range(upperAbsRow, upperLineLen, lowerAbsRow, lowerLineLen));
            shiftRowsAfter(gaps, lowerAbsRow, -1);

            // If row r+1 was the last row, row r has taken over that role
            // (or become the sole row) and needs restyling to match.
            if (r === this.numRows() - 1) {
                this.restyleRowMarker(r);
            }
        } else {
            // At the floor: clear row r+1's content but keep the row itself.
            this.setRowText(gaps, r + 1, '');
        }

        this.setRowText(gaps, r, merged);

        this.editor.$onChangeBackMarker();
        this.editor.$onChangeFrontMarker();
        return cursor;
    };

    /**
     * Insert text at the given position within this gap. Any newline in the
     * text splits the current row, exactly as if the student had pressed
     * Enter there (subject to the gap's maximum number of rows - once that's
     * reached, further newlines in the text are simply dropped and the rest
     * of the text carries on filling the last row).
     * @param {Array} gaps The full list of gaps in the editor.
     * @param {object} pos The starting {row, column} position.
     * @param {string} text The text to insert.
     * @returns {object} The resulting cursor position.
     */
    Gap.prototype.insertText = function(gaps, pos, text) {
        let cursor = {row: pos.row, column: pos.column};
        for (let i = 0; i < text.length; i++) {
            let char = text[i];
            if (char === "\n") {
                let newCursor = this.splitRowAt(gaps, cursor);
                if (newCursor !== null) {
                    cursor = newCursor;
                }
                // Else: already at maxRows - drop this newline and keep going.
            } else {
                let r = this.rowIndexAt(cursor.row);
                if (r !== -1 && this.textSizes[r] < this.maxCols) {
                    this.insertChar(gaps, cursor, char);
                    cursor = {row: cursor.row, column: cursor.column + 1};
                }
            }
        }
        return cursor;
    };

    Gap.prototype.getText = function() {
        let parts = [];
        for (let r = 0; r < this.numRows(); r++) {
            parts.push(this.getRowText(r));
        }
        return parts.join('\n');
    };

    return {
        Constructor: AceGapfillerUi
    };
});
