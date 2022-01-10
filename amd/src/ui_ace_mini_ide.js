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
 * An alternative interface to the Ace editor that provides a few basic
 * IDE capabilities, such as running the existing file on the Jobe server
 * without its being an actual check or precheck.
 * EXPERIMENTAL!
 * The class defined in this module is a plugin for the InterfaceWrapper class
 * declared in userinterfacewrapper.js. See that file for an explanation of
 * the interface to this module.
 *
 * A special case behaviour of the AceMiniIdeWrapper is that it needs to know
 * the Programming language that is being edited. This MUST be provided in
 * the constructor params parameter (an associative array) as a string
 * with key 'lang'.
 *
 * @module qtype_coderunner/ui_ace_mini_ide
 * @copyright  Richard Lobb, 2015, 2017, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * WARNING: The ace editor must have already been loaded before this
 * module is used, as it assumes window.ace exists.
 */

define(['jquery', 'core/ajax'], function($, ajax) {

    /**
     * Constructor for the Ace interface object.
     * @param {string} textareaId The ID of the textarea HTML element.
     * @param {int} w The width of the textarea in pixels.
     * @param {int} h The height of the textarea in pixels.
     * @param {object} params The UI parameters object.
     */
    function AceMiniIdeWrapper(textareaId, w, h, params) {
        var textarea = $(document.getElementById(textareaId)),
            wrapper = $(document.getElementById(textareaId + '_wrapper')),
            focused = textarea[0] === document.activeElement,
            lang = params.lang,
            button = $('<button type="button" class="qtype-coderunner-ide-button">Run code</button>'),
            button_div = $('<div class="qtype-coderunner-ide-buttons"></div>'),
            session,
            ide_response_div,
            t = this;  // For embedded callbacks.

        try {
            window.ace.require("ace/ext/language_tools");
            this.modelist = window.ace.require('ace/ext/modelist');

            this.textarea = textarea;
            this.enabled = false;
            this.contents_changed = false;
            this.capturingTab = false;
            this.clickInProgress = false;
            this.language = lang;

            this.editNode = $("<div></div>"); // Ace editor manages this

            this.editor = window.ace.edit(this.editNode.get(0));
            if (textarea.prop('readonly')) {
                this.editor.setReadOnly(true);
            }

            this.editor.setOptions({
                enableBasicAutocompletion: true,
                newLineMode: "unix",
            });

            this.editor.$blockScrolling = Infinity;

            /**
             * Build the work area consisting of Ace + the mini-IDE
             */
            this.workArea = $('<div></div>');
            this.workArea.append(this.editNode);
            this.button = button;
            button_div.append(button);
            this.workArea.append(button_div); // Add the mini-ide buttons.
            this.ide_response = $('<textarea rows="5" class="edit_code" style="width:100%"></textarea>');
            ide_response_div = $('<div></div>');
            ide_response_div.append(this.ide_response);
            this.workArea.append(ide_response_div);
            this.editNode.css({
                resize: 'none',
                height: h / 2,
                width: "100%"
            });

            session = this.editor.getSession();
            session.setValue(this.textarea.val());

            /**
             * Set theme if available (not currently enabled).
             */
            if (params.theme) {
                this.editor.setTheme("ace/theme/" + params.theme);
            }

            this.setLanguage(lang);

            this.setEventHandlers();
            this.setMiniIdeHandlers();
            this.captureTab();

            /**
             * Try to tell Moodle about parts of the editor with z-index.
             * It is hard to be sure if this is complete. ACE adds all its CSS using JavaScript.
             * Here, we just deal with things that are known to cause a problem.
             * Can't do these operations until editor has rendered. So ...
             */
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

            this.fail = false;
        }
        catch(err) {
            /**
             * Something ugly happened. Probably ace editor hasn't been loaded
             */
            alert(err);
            this.fail = true;
        }
    }


    AceMiniIdeWrapper.prototype.failed = function() {
        return this.fail;
    };

    AceMiniIdeWrapper.prototype.failMessage = function() {
        return 'ace_ui_notready';
    };


    /**
     * Sync to TextArea
     */
    AceMiniIdeWrapper.prototype.sync = function() {
        /**
         * Nothing to do ... always sync'd
         */
    };

    /**
     * Disable autosync, too.
     */
    AceMiniIdeWrapper.prototype.syncIntervalSecs = function() {
        return 0;
    };

    AceMiniIdeWrapper.prototype.setLanguage = function(language) {
        var session = this.editor.getSession(),
            mode = this.findMode(language);
        if (mode) {
            session.setMode(mode.mode);
        }
        this.language = language;
    };

    AceMiniIdeWrapper.prototype.getElement = function() {
        return this.workArea;
    };

    AceMiniIdeWrapper.prototype.captureTab = function () {
        this.capturingTab = true;
        this.editor.commands.bindKeys({'Tab': 'indent', 'Shift-Tab': 'outdent'});
    };

    AceMiniIdeWrapper.prototype.releaseTab = function () {
        this.capturingTab = false;
        this.editor.commands.bindKeys({'Tab': null, 'Shift-Tab': null});
    };

    AceMiniIdeWrapper.prototype.setEventHandlers = function () {
        var TAB = 9,
            ESC = 27,
            KEY_M = 77,
            t = this;

        this.editor.getSession().on('change', function() {
            t.textarea.val(t.editor.getSession().getValue());
            t.contents_changed = true;
        });

        this.editor.on('blur', function() {
            if (t.contents_changed) {
                t.textarea.trigger('change');
            }
        });

        this.editor.on('mousedown', function() {
            /**
             * Event order seems to be (\ is where the mouse button is pressed, / released):
             * Chrome: \ mousedown, mouseup, focusin / click.
             * Firefox/IE: \ mousedown, focusin / mouseup, click.
             */
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

    AceMiniIdeWrapper.prototype.setMiniIdeHandlers = function() {
        var that = this;
        this.button.on('click', function() {
            var code = that.textarea.val();
            ajax.call([{
                methodname: 'qtype_coderunner_run_in_sandbox',
                args: {sourcecode: code, language: that.language, stdin: ''},
                done: function(responseJson) {
                    var response = JSON.parse(responseJson);
                    var text = response.cmpinfo + response.output + response.stderr;
                    that.ide_response.val(text);
                },
                fail: function(error) { alert("We're dead, Fred: " + error); }
            }]);
        });
    };


    AceMiniIdeWrapper.prototype.destroy = function () {
        var focused;
        if (!this.fail) {
            /**
             * Proceed only if this wrapper was correctly constructed
             */
            focused = this.editor.isFocused();
            this.textarea.val(this.editor.getSession().getValue()); // Copy data back
            this.editor.destroy();
            $(this.workArea).remove();
            if (focused) {
                this.textarea.focus();
                this.textarea[0].selectionStart = this.textarea[0].value.length;
            }
        }
    };

    AceMiniIdeWrapper.prototype.hasFocus = function() {
        return this.editor.isFocused();
    };

    AceMiniIdeWrapper.prototype.findMode = function (language) {
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


    AceMiniIdeWrapper.prototype.resize = function(w, h) {
        this.workArea.outerHeight(h);
        this.workArea.outerWidth(w);
        this.editNode.css({
                resize: 'none',
                height: h / 2,
                width: "100%"
            });
        this.ide_response.css({
                resize: 'none',
                height: h / 2,
                width: "100%"
            });
        this.editor.resize();
    };

     return {
        Constructor: AceMiniIdeWrapper
    };
});
