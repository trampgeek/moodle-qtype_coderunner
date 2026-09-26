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

define([], function() {
    const GLOBAL_THEME_KEY = 'qtype_coderunner.ace.theme';
    const ACE_DARK_THEME = 'ace/theme/tomorrow_night';
    const ACE_LIGHT_THEME = 'ace/theme/textmate';
    /**
     * Constructor for the Ace interface object.
     * Stores parameters only; actual Ace initialisation happens in ready().
     * @param {string} textareaId The ID of the HTML textarea element to be wrapped.
     * @param {int} w The width in pixels of the textarea.
     * @param {int} h The height in pixels of the textarea.
     * @param {object} params The UI parameter object.
     */
    function AceWrapper(textareaId, w, h, params) {
        this.textareaId = textareaId;
        this.textarea = document.getElementById(textareaId);
        this.wrapper = document.getElementById(textareaId + '_wrapper');
        this.focused = this.textarea === document.activeElement;
        this.lang = params.lang;
        this.params = params;
        this.w = w;
        this.h = h;
        this.enabled = false;
        this.contents_changed = false;
        this.capturingTab = false;
        this.clickInProgress = false;
        this.editNode = null;
        this.editor = null;
        this.fail = false;
    }

    /**
     * Initialise the Ace editor, polling until window.ace is available.
     * Resolves when the editor is ready; rejects (after 3 s) if Ace never loads.
     * On rejection the caller falls back to showing the raw textarea with an error.
     * @returns {Promise}
     */
    AceWrapper.prototype.ready = function() {
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
                // window.ace is available; wait for fonts before Ace measures character metrics.
                document.fonts.ready.then(function() {
                try {
                    const textarea = t.textarea;
                    const wrapper = t.wrapper;
                    const focused = t.focused;
                    const params = t.params;
                    const lang = t.lang;

                    window.ace.require("ace/ext/language_tools");
                    t.modelist = window.ace.require('ace/ext/modelist');

                    t.editNode = document.createElement('div');
                    t.editNode.style.resize = 'none';
                    t.editNode.style.height = t.h + 'px';
                    t.editNode.style.width = '100%';

                    t.editor = window.ace.edit(t.editNode);
                    if (textarea.readOnly) {
                        t.editor.setReadOnly(true);
                    }

                    t.editor.setOptions({
                        enableBasicAutocompletion: true,
                        enableLiveAutocompletion: params.live_autocompletion,
                        fontSize: params.font_size ? params.font_size : "14px",
                        newLineMode: "unix",
                    });

                    t.editor.$blockScrolling = Infinity;

                    const session = t.editor.getSession();
                    let code = textarea.value;
                    if (params.import_from_scratchpad === undefined || params.import_from_scratchpad) {
                        code = t.extract_from_json_maybe(code);
                    }
                    session.setValue(code);

                    const userTheme = window.localStorage.getItem(GLOBAL_THEME_KEY);
                    const consider_prefers = params.auto_switch_light_dark && window.matchMedia;
                    if (userTheme !== null) {
                        t.editor.setTheme(userTheme);
                    } else if (consider_prefers && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        t.editor.setTheme(ACE_DARK_THEME);
                    } else if (consider_prefers && window.matchMedia('(prefers-color-scheme: light)').matches) {
                        t.editor.setTheme(ACE_LIGHT_THEME);
                    } else if (params.theme) {
                        t.editor.setTheme("ace/theme/" + params.theme);
                    } else {
                        t.editor.setTheme(ACE_LIGHT_THEME);
                    }
                    t.currentTheme = t.editor.getTheme();

                    t.fixSlowLoad();
                    t.setLanguage(lang);
                    t.setEventHandlers(textarea);
                    t.set_ace_aria_label(t.editor.container);
                    t.captureTab();

                    // Try to tell Moodle about parts of the editor with z-index.
                    // It is hard to be sure if this is complete. ACE adds all its CSS using JavaScript.
                    // Here, we just deal with things that are known to cause a problem.
                    // Can't do these operations until editor has rendered. So ...
                    t.editor.renderer.on('afterRender', function() {
                        const gutter = wrapper.querySelector('.ace_gutter');
                        if (!gutter || gutter.classList.contains('moodle-has-zindex')) {
                            return;
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

                    t.fail = false;
                    resolve();
                } catch(err) {
                    t.fail = true;
                    reject(err);
                }
                });
            }
            tryInit();
        });
    };

    AceWrapper.prototype.set_ace_aria_label = function(editor_container) {
        // Set the aria-label for the given Ace editor container to the
        // language string ace_aria_label.
        require(['core/str'], function(str) {
            /**
             * Get langString text via AJAX
             */
            str.get_string('ace_aria_label', 'qtype_coderunner').then(function(label) {
                editor_container.setAttribute('aria-label', label);
            });
        });
    };

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
        } else if (globalTheme && thisThemeNow != globalTheme) {
            // Another window has set the theme (since if there had been a
            // global theme when we started, we'd have used it.
            this.editor.setTheme(globalTheme);
            this.currentTheme = globalTheme;
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
    // synthesise a mousemove event when the editor div enters the viewport.
    AceWrapper.prototype.fixSlowLoad = function () {
        const observer = new IntersectionObserver(() => {
            document.dispatchEvent(new MouseEvent('mousemove'));
        });
        observer.observe(this.editNode);
    };

    AceWrapper.prototype.setEventHandlers = function (textarea) {
        var TAB = 9,
            ESC = 27,
            KEY_M = 77,
            t = this;

        this.editor.getSession().on('change', function() {
            textarea.value = t.editor.getSession().getValue();
            t.contents_changed = true;
        });

        this.editor.on('blur', function() {
            if (t.contents_changed) {
                textarea.dispatchEvent(new Event('change'));
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
        if (this.editor) {
            const focused = this.editor.isFocused();
            this.textarea.value = this.editor.getSession().getValue();
            this.editor.destroy();
            this.editNode.remove();
            if (focused) {
                this.textarea.focus();
                this.textarea.selectionStart = this.textarea.value.length;
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
                'c#': 'cs',
                'pypy3': 'python'
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
        this.editNode.style.height = h + 'px';
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
