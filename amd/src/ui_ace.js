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
 * The class defined in this module is a plugin for the InterfaceWrapper class
 * declared in userinterfacewrapper.js. See that file for an explanation of
 * the interface to this module.
 *
 * A special case behaviour of the AceWrapper is that it needs to know
 * the Programming language that is being edited. This MUST be provided in
 * the constructor params parameter (an associative array) as a string
 * with key 'lang'.
 *
 * @module qtype_coderunner/ui_ace
 * @copyright  Richard Lobb, 2015, 2017, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Thanks to Ulrich Dangel for the initial implementation of Ace within
// CodeRunner.

// WARNING: The ace editor must have already been loaded before this
// module is used, as it assumes window.ace exists.

define(['jquery'], function($) {
    const GLOBAL_THEME_KEY = 'qtype_coderunner.ace.theme';
    const ACE_DARK_THEME = 'ace/theme/tomorrow_night';
    const ACE_LIGHT_THEME = 'ace/theme/textmate';
    /**
     * Constructor for the Ace interface object.
     * @param {string} textareaId The ID of the HTML textarea element to be wrapped.
     * @param {int} w The width in pixels of the textarea.
     * @param {int} h The height in pixels of the textarea.
     * @param {object} params The UI parameter object.
     */
    function AceWrapper(textareaId, w, h, params) {
        var textarea = $(document.getElementById(textareaId)),
            wrapper = $(document.getElementById(textareaId + '_wrapper')),
            focused = textarea[0] === document.activeElement,
            lang = params.lang,
            session,
            code,
            t = this;  // For embedded callbacks.

        try {
            window.ace.require("ace/ext/language_tools");
            this.modelist = window.ace.require('ace/ext/modelist');
            this.textareaId = textareaId;
            this.textarea = textarea;
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
            if (textarea.prop('readonly')) {
                this.editor.setReadOnly(true);
            }

            this.editor.setOptions({
                enableBasicAutocompletion: true,
                enableLiveAutocompletion: params.live_autocompletion,
                fontSize: params.font_size ? params.font_size : "14px",
                newLineMode: "unix",
            });

            this.editor.$blockScrolling = Infinity;

            session = this.editor.getSession();
            code = this.textarea.val();
            if (params.import_from_scratchpad === undefined || params.import_from_scratchpad) {
                code = this.extract_from_json_maybe(code);
            }
            session.setValue(code);

            // If there's a user-defined theme in local storage, use that.
            // Otherwise use the 'prefers-color-scheme' option if given or
            // the question/system defaults if not.
            const userTheme = window.localStorage.getItem(GLOBAL_THEME_KEY);
            const consider_prefers = params.auto_switch_light_dark && window.matchMedia;
            if (userTheme !== null) {
                this.editor.setTheme(userTheme);
            } else if (consider_prefers && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                this.editor.setTheme(ACE_DARK_THEME);
            } else if (consider_prefers && window.matchMedia('(prefers-color-scheme: light)').matches) {
                this.editor.setTheme(ACE_LIGHT_THEME);
            }  else if (params.theme) {
                this.editor.setTheme("ace/theme/" + params.theme);
            } else {
                this.editor.setTheme(ACE_LIGHT_THEME);
            }
            this.currentTheme = this.editor.getTheme();

            this.fixSlowLoad();

            this.setLanguage(lang);

            this.setEventHandlers(textarea);
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

            this.fail = false;
        }
        catch(err) {
            // Something ugly happened. Probably ace editor hasn't been loaded
            this.fail = true;
        }
    }

    AceWrapper.prototype.extract_from_json_maybe = function(code) {
        // If the given code looks like JSON from the Scratchpad UI,
        // extract and return the answer_code attribute.
        try {
            const jsonObj = JSON.parse(code);
            code = jsonObj.answer_code[0];
        } catch(err) {}

        return code;
    };

    AceWrapper.prototype.failed = function() {
        return this.fail;
    };

    AceWrapper.prototype.failMessage = function() {
        return 'ace_ui_notready';
    };

    // Sync to TextArea
    AceWrapper.prototype.sync = function() {
        // The data is always sync'd to the text area. But here we use sync to
        // poll the value of the current theme and record in browser local
        // storage if the value for this particular Ace instance has changed
        // from the current working theme (set by code),
        // implying a user menu action. If that happens the global user theme
        // is set and is subsequently used by all Ace windows.
        const thisThemeNow = this.editor.getTheme();
        const globalTheme = window.localStorage.getItem(GLOBAL_THEME_KEY);
        if (thisThemeNow !== this.currentTheme) {
            // User has changed the theme via menu. Record in global storage so
            // other editor instances can switch to it.
            this.currentTheme = thisThemeNow;
            window.localStorage.setItem(GLOBAL_THEME_KEY, thisThemeNow);
            // console.log(`Menu theme change. Global theme now ${thisThemeNow}`);
        } else if (globalTheme && thisThemeNow != globalTheme) {
            // Another window has set the theme (since if there had been a
            // global theme when we started, we'd have used it.
            this.editor.setTheme(globalTheme);
            this.currentTheme = globalTheme;
            // console.log(`Global theme change found: ${globalTheme}`);
        }
    };

    AceWrapper.prototype.syncIntervalSecs = function() {
        return 2;
    };

    AceWrapper.prototype.setLanguage = function(language) {
        var session = this.editor.getSession(),
            mode = this.findMode(language);
        if (mode) {
            session.setMode(mode.mode);
        }
    };

    AceWrapper.prototype.getElement = function() {
        return this.editNode;
    };

    AceWrapper.prototype.captureTab = function () {
        this.capturingTab = true;
        this.editor.commands.bindKeys({'Tab': 'indent', 'Shift-Tab': 'outdent'});
    };

    AceWrapper.prototype.releaseTab = function () {
        this.capturingTab = false;
        this.editor.commands.bindKeys({'Tab': null, 'Shift-Tab': null});
    };

    // Sometimes Ace editors do not load until the mouse is moved. To fix this,
    // 'move' the mouse using JQuery when the editor div enters the viewport.
    AceWrapper.prototype.fixSlowLoad = function () {
        const observer = new IntersectionObserver( () => {
            $(document).trigger('mousemove');
        });
        const editNode = this.editNode.get(0); // Non-JQuerry node.
        observer.observe(editNode);
    };

    AceWrapper.prototype.setEventHandlers = function () {
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

    AceWrapper.prototype.destroy = function () {
        var focused;
        if (!this.fail) {
            // Proceed only if this wrapper was correctly constructed
            focused = this.editor.isFocused();
            this.textarea.val(this.editor.getSession().getValue()); // Copy data back
            this.editor.destroy();
            $(this.editNode).remove();
            if (focused) {
                this.textarea.focus();
                this.textarea[0].selectionStart = this.textarea[0].value.length;
            }
        }
    };

    AceWrapper.prototype.hasFocus = function() {
        return this.editor.isFocused();
    };

    AceWrapper.prototype.findMode = function (language) {
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

    AceWrapper.prototype.resize = function(w, h) {
        this.editNode.outerHeight(h);
        this.editNode.outerWidth(w);
        this.editor.resize();
    };

    /**
     * Allow fullscreen mode for the Ace editor.
     *
     * @return {Boolean} True if fullscreen mode is allowed, false otherwise.
     */
    AceWrapper.prototype.allowFullScreen = function() {
        return true;
    };

     return {
        Constructor: AceWrapper
    };
});
