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
 * JavaScript to interface to the Ace editor, which is used both in
 * the author editing page and by the student question submission page.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The interface 'glue code' between the other AMD modules in CodeRunner
// and the Ace editor.

// Thanks to Ulrich Dangel for the initial implementation of Ace within
// CodeRunner.

// WARNING: The ace editor must have already been loaded before this
// module is used, as it assumes window.ace exists.

define(['jquery'], function($) {

    /***********************************************************************
     *  First, a class to wrap a specific instance of an AceEditor managing
     *  a particular text area with a particular Ace mode.
     *  A div with a roll-your-own resize handle is wrapped around the
     *  Ace editor node so that users can resize the editor panel.
     ***********************************************************************/
    function AceInstance(textareaId, mode) {
        // Warning: IDs from Moodle can contain colons - don't work with jQuery!
        var textarea =  $(document.getElementById(textareaId)),
            h = parseInt(textarea.css("height")),
            w = parseInt(textarea.css("width")),
            focused = textarea[0] === document.activeElement;

        this.HANDLE_SIZE = 6;
        this.MIN_WIDTH = 300;
        this.MIN_HEIGHT = 100;

        this.editNode = $("<div></div>"); // Ace editor manages this
        this.wrapperNode = $("<div id='" + textareaId + "_wrapper' class='ace_wrapper'></div>"); // Outer div with resize handle
        this.editor = null;
        this.contents_changed = false;
        this.hLast = h - this.HANDLE_SIZE;
        this.wLast = w - this.HANDLE_SIZE;
        this.textarea = textarea;
        this.capturingTab = true;
        this.clickInProgress = false;

        this.wrapperNode.css({
            resize: 'both',
            overflow: 'hidden',
            height: h,
            width: w,
            minWidth: this.MIN_WIDTH,
            minHeight: this.MIN_HEIGHT
        });

        this.editNode.css({
            resize: 'none', // Chrome wrongly inherits this.
            height: h - this.HANDLE_SIZE,
            width: w - this.HANDLE_SIZE
        });

        textarea.after(this.wrapperNode);
        this.wrapperNode.append(this.editNode);

        this.editor = window.ace.edit(this.editNode.get(0));
        if (textarea.prop('readonly')) {
            this.editor.setReadOnly(true);
        }

        this.editor.setOptions({
            enableBasicAutocompletion: true,
            newLineMode: "unix",
        });
        this.editor.$blockScrolling = Infinity;

        this.reload(mode);

        this.setEventHandlers(textarea);
        this.captureTab();

        // Try to tell Moodle about parts of the editor with z-index.
        // It is hard to be sure if this is complete. ACE adds all its CSS using JavaScript.
        // Here, we just deal with things that are known to cause a problem.
        $('.ace_gutter').addClass('moodle-has-zindex');

        textarea.hide();
        if (focused) {
            this.editor.focus();
            this.editor.navigateFileEnd();
            // var session = this.editor.getSession(),
            //     lines = session.getLength();
            // this.editor.gotoLine(lines, session.getLine(lines - 1).length);
        }
    }

    AceInstance.prototype.captureTab = function () {
        this.capturingTab = true;
        this.editor.commands.bindKeys({'Tab': 'indent', 'Shift-Tab': 'outdent'});
    };

    AceInstance.prototype.releaseTab = function () {
        this.capturingTab = false;
        this.editor.commands.bindKeys({'Tab': null, 'Shift-Tab': null});
    };

    AceInstance.prototype.setEventHandlers = function () {
        var parent = this.wrapperNode.parent();
        var TAB = 9,
            ESC = 27,
            KEY_M = 77;

        this.editor.getSession().on('change', function() {
            this.textarea.val(this.editor.getSession().getValue());
            this.contents_changed = true;
        }.bind(this));

        this.editor.on('blur', function() {
            if (this.contents_changed) {
                this.textarea.trigger('change');
            }
        }.bind(this));

        // Chrome doesn't generate mutation events when a user resizes a
        // resizable div, so we use mouse motion events to monitor the size.
        // We use the DOM event rather than its jQuery wrapper so that 'this'
        // can be bound to the AceInstance rather than the object on which the
        // event occurred.
        parent.get(0).onmousemove = function () {
            var h = this.wrapperNode.outerHeight(),
                w = this.wrapperNode.outerWidth();
            if (h != this.hLast || w != this.wLast) {
                this.editNode.outerHeight(h - this.HANDLE_SIZE);
                this.editNode.outerWidth(w - this.HANDLE_SIZE);
                this.editor.resize();
                this.hLast = h;
                this.wLast = w;
            }
        }.bind(this);

        var t = this;
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

    AceInstance.prototype.close = function () {
        var focused = this.editor.isFocused();
        this.textarea.val(this.editor.getSession().getValue()); // Copy data back
        this.editor.destroy();
        this.wrapperNode.remove();
        this.textarea.show();
        if (focused) {
            this.textarea.focus();
            this.textarea[0].selectionStart = this.textarea[0].value.length;
        }
    };


    /**
     * Restore an existing session (e.g. after it has been unhidden) and
     * set the mode to the given value.
     * @param Ace-editor-mode mode
     * @returns {undefined}
     */
    AceInstance.prototype.reload = function (mode) {
        var session = this.editor.getSession();
        session.setValue(this.textarea.val());
        if (mode) {
            session.setMode(mode.mode);
        }
    };

    /****************************************************************
     *
     * Now the external interface class
     *
     ****************************************************************/

    var AceInterface = function() {
        // Constructor for AceInterface class.

        this.editableFields = {};
        this.activeEditors = {};
        this.modelist = window.ace.require('ace/ext/modelist');
        window.ace.require("ace/ext/language_tools");

        var t = this;
        $(document.body).on('keydown', function(e) {
            var KEY_M = 77;

            if (e.keyCode === KEY_M && e.ctrlKey && e.altKey) {
                if (Object.keys(t.activeEditors).length === 0) {
                    t.startUsingAce();
                } else {
                    t.stopUsingAce();
                }
            }
        });
    };


    // Try to find the correct ace language mode.
    AceInterface.prototype.findMode = function (language) {
        var candidate,
            filename,
            result,
            candidates = []; // List of candidate modes.

        if (language.toLowerCase() === 'octave') {
            language = 'matlab';
        } else if (language.toLowerCase() === 'nodejs') {
            language = 'javascript';
        }

        candidates = [language, language.replace(/\d*$/, "")];
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


    AceInterface.prototype.initAce = function (textareaId, lang) {
        // Initialise an Ace editor for a textarea (given its ID) and language.
        // Keep track of all active editors on this page; a call to initAce
        // on an existing textarea is converted to a reload call to
        // refresh the editor contents from the textarea.

        var mode = this.findMode(lang);

        this.editableFields[textareaId] = lang;
        if (this.activeEditors[textareaId]) {
            this.activeEditors[textareaId].reload(mode);
        } else {  // Otherwise create a new editor.
            this.activeEditors[textareaId] = new AceInstance(textareaId, mode);
        }
    };

    // Turn off all current Ace editors
    AceInterface.prototype.stopUsingAce = function () {
        for (var aceinstance in this.activeEditors) {
            this.activeEditors[aceinstance].close();
        }
        this.activeEditors = {};
    };

    // Turn all editableFields into Ace editors
    AceInterface.prototype.startUsingAce = function () {
        for (var aceinstance in this.editableFields) {
            this.initAce(aceinstance, this.editableFields[aceinstance]);
        }
    };

    return new AceInterface();
});
