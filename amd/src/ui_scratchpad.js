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
 * Implementation of the scratchpad_ui user interface plugin. For overall details
 * of the UI plugin architecture, see userinterfacewrapper.js.
 *
 * This plugin replaces the usual textarea answer element with a UI is designed to
 * allow the execution of code in the CodeRunner question in a manner similar to an IDE.
 * It contains two editor boxes, one on top of another, allowing users to enter and
 * edit code in both. It contains two embedded Ace UIs.
 *  By default, only the top editor is visible and the bottom editor (Scratchpad Area) is hidden,
 * clicking the Scratchpad button shows it. The Scratchpad area contains a second editor,
 * a Run button and a Prefix with Answer checkbox. Additionally, there is a help button that
 * provides information about how to use the Scratchpad.
 *  It's possible to run code 'in-browser' by clicking the Run Button,
 * without making a submission via the Check Button:
 *          If Prefix with Answer is not checked, only the code in the Scratchpad is run --
 *      allowing for a rough working spot to quickly check the result of code.
 *          Otherwise, when Prefix with Answer is checked, the code in the Scratchpad is
 *      appended to the code in the first editor before being run.
 *  The Run Button has some limitations when using its default configuration:
 *     Does not support programs that use STDIN (by default);
 *     Only supports textual STDOUT (by default).
 * Note: These features can be supported, see the README section on wrappers...
 *  The serialisation of this UI is a JSON object with the fields
 * with fields:
 *      answer_code: [""] A list containing a string with answer code from the first editor;
 *      test_code: [""] A list containing a string with containing answer code from the second editor;
 *      show_hide: ["1"] when scratchpad is visible, otherwise [""];
 *      prefix_ans: ["1"] when Prefix with Answer is checked, otherwise [""].
 *
 * UI Parameters:
 *    - scratchpad_name: display name of the scratchpad, used to hide/un-hide the scratchpad.
 *    - button_name: run button text.
 *    - prefix_name: prefix with answer check-box label text.
 *    - help_text: help text to show.
 *    - run_lang: language used to run code when the run button is clicked,
 *      this should be the language your wrapper is written in (if applicable).
 *    - wrapper_src: location of wrapper code to be used by the run button, if applicable:
 *      setting to globalextra will use text in global extra field,
 *    - prototypeextra will use the prototype extra field.
 *    - output_display_mode: control how program output is displayed on runs, there are three modes:
 *          - text: display program output as text, html escaped;
 *          - json: display program output, when it is json,
 *          - html: display program output as raw html.
 *      NOTE: see qtype_coderunner/outputdisplayarea.js for more info...
 *    - disable_scratchpad: disable the scratchpad, effectively reverting to the Ace UI
 *      from student perspective.
 *    - invert_prefix: inverts meaning of prefix_ans serialisation -- '1' means un-ticked, vice versa.
 *      This can be used to swap the default state.
 *
 * @module qtype_coderunner/ui_scratchpad
 * @copyright  Richard Lobb, 2022, The University of Canterbury
 * @copyright  James Napier, 2022, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


import Templates from 'core/templates';

import {newUiWrapper} from 'qtype_coderunner/userinterfacewrapper';
import {OutputDisplayArea} from 'qtype_coderunner/outputdisplayarea';


/**
 * Invert serialisation from '1' to '', vice versa.
 * @param {string} current serialisation.
 * @returns {string} inverted serialisation.
 */
const invertSerial = (current) => current[0] === '1' ? [''] : ['1'];

/**
 * Insert the answer code and test code into the wrapper. This may
 * be defined by the user, in UI Params or globalextra. If prefixAns is
 * false: do not include answerCode in final wrapper.
 * @param {string} answerCode text from first editor.
 * @param {string} testCode text from second editor.
 * @param {string} prefixAns '1' for true, '' for false.
 * @param {string} template provided in UI Params or globalextra.
 * @param {string} open delimiter to look for, e.g. '[['
 * @param {string} close delimiter to look for, e.g. ']]'
 * @returns {string} filled template.
 */
const fillWrapper = (answerCode, testCode, prefixAns, template, open = '\\(', close = '\\)') => {
    if (!template) {
        template = `${open} ANSWER_CODE ${close}\n` +
                   `${open} SCRATCHPAD_CODE ${close}`;
    }
    if (!prefixAns) {
        answerCode = '';
    }
    const escOpen = escapeRegExp(open);
    const escClose = escapeRegExp(close);
    const answerRegex = new RegExp(`${escOpen}\\s*ANSWER_CODE\\s*${escClose}`, 'g');
    const scratchpadRegex = new RegExp(`${escOpen}\\s*SCRATCHPAD_CODE\\s*${escClose}`, 'g');
    // Use arrow functions in replace operations to avoid special-case treatment of $.
    template = template.replaceAll(answerRegex, () => answerCode);
    template = template.replaceAll(scratchpadRegex, () => testCode);
    return template;
};

/**
 * Escapes a string for use in regex.
 * @param {string} string to escape.
 * @returns {string} RegEx escaped string
 */
const escapeRegExp = (string) => string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); // $& means the whole matched string

/**
 * Returns a new object contain default values. If a matching key exists in
 * prescribed, the corresponding value from prescribed will replace the default value.
 * Does not add keys/values to the result if that key is not in defaults.
 * @param {object} defaults object with values to be overwritten.
 * @param {object} prescribed settings, typically set by a user.
 * @returns {object} filled with default values, overwritten by their prescribed value (iff included).
 */
const overwriteValues = (defaults, prescribed) => {
    let overwritten = {...defaults};
    if (prescribed) {
        for (const [key, value] of Object.entries(defaults)) {
            overwritten[key] = prescribed[key] || value;
        }
    }
    return overwritten;
};

/**
 * Is a collapsed element currently collapsed?
 * @param {Element} el which is collapsed using a bootstrap collapse.
 * @returns {boolean} true if el is collapsed.
 */
const isCollapsed = (el) => {
    if (!(el.classList.contains('collapse') || el.classList.contains('collapsing'))) {
        throw Error('Element does not have collapse class');
    }
    return !el.classList.contains('show');
};


/**
 * Constructor for the ScratchpadUi object.
 * @param {string} textAreaId The ID of the html textarea.
 * @param {int} width The width in pixels of the textarea.
 * @param {int} height The height in pixels of the textarea.
 * @param {object} uiParams The UI parameter object.
 */
class ScratchpadUi {
    constructor(textAreaId, width, height, uiParams) {
        const DEF_UI_PARAMS = {
            scratchpad_name: '',
            button_name: '',
            prefix_name: '',
            help_text: '',
            params: {},
            run_lang: uiParams.lang, // Use answer's ace language if not specified.
            output_display_mode: 'text',
            disable_scratchpad: false,
            wrapper_src: null,
            open_delimiter: '{|',
            close_delimiter: '|}',
            escape: false
        };
        this.textArea = document.getElementById(textAreaId);
        this.textAreaId = textAreaId;
        this.height = height;
        this.readOnly = this.textArea.readonly;
        this.fail = false;
        this.outerDiv = null;
        this.outputDisplay = null;
        this.invertPreload = uiParams.invert_prefix;
        this.lang = uiParams.lang;
        this.numRows = this.textArea.rows;
        this.uiParams = overwriteValues(DEF_UI_PARAMS, uiParams);
        this.runWrapper = this.getRunWrapper();
        const preloadString = this.textArea.value;
        let preload;
        try {
            preload = this.readJson(preloadString);
        } catch (error) {
            this.fail = true;
            this.failString = 'scratchpad_ui_invalidserialisation';
            return;
        }
        this.updateContext(preload);
        this.reload(); // Draw my beautiful blobs.
    }

    getRunWrapper() {
        const wrapperSrc = this.uiParams.wrapper_src;
        let runWrapper = null;
        if (wrapperSrc) {
            if (wrapperSrc === 'globalextra' || wrapperSrc === 'prototypeextra') {
                runWrapper = this.textArea.dataset[wrapperSrc];
            } else {
                this.fail = true;
                this.failString = 'scratchpad_ui_badrunwrappersrc';
            }
        }
        return runWrapper;
    }

    failed() {
        return this.fail;
    }

    failMessage() {
        return this.failString;
    }

    sync() {
        if (!this.context) {
            return;
        }
        const serialisation = this.getSerialisation();
        this.setSerialisation(serialisation);
    }

    getSerialisation() {
        const prefixAns = document.getElementById(this.context.prefix_ans.id);
        const showHide = document.getElementById(this.context.show_hide.id);
        // Initialise using the JSON string from the server.
        let serialisation = {
            answer_code: [this.context.answer_code.text],
            test_code: [this.context.test_code.text],
            show_hide: [this.context.show_hide.show],
            prefix_ans: [invertSerial(this.context.prefix_ans.checked)]
        };
        // If the UI is up and running, update elements from the UI.
        if (this.answerTextarea) {
            serialisation.answer_code = [this.answerTextarea.value];
        }
        if (this.testTextarea) {
            serialisation.test_code = [this.testTextarea.value];
        }
        if (showHide && !isCollapsed(showHide)) {
            serialisation.show_hide = ['1'];
        } else {
            serialisation.show_hide = [''];
        }
        if (prefixAns?.checked || this.context.disable_scratchpad) {
            serialisation.prefix_ans = ['1'];
        } else {
            serialisation.prefix_ans = [''];
        }
        if (this.invertPreload) {
            serialisation.prefix_ans = invertSerial(serialisation.prefix_ans);
        }
        return serialisation;
    }

    setSerialisation(serialisation) {
        serialisation.prefix_ans = invertSerial(serialisation.prefix_ans);
        if (Object.values(serialisation).some((val) => val.length === 1 && val[0].length > 0)) {
            serialisation.prefix_ans = invertSerial(serialisation.prefix_ans);
            this.textArea.value = JSON.stringify(serialisation);
        } else {
            this.textArea.value = ''; // All fields empty...
        }
    }

    getElement() {
        return this.outerDiv;
    }

    handleRunButtonClick() {
        if (this.outputDisplay === null) {
            return;
        }
        this.sync(); // Use up-to-date serialization.
        const preloadString = this.textArea.value;
        const serial = this.readJson(preloadString);
        const escape = (code) => this.uiParams.escape ? JSON.stringify(code).slice(1, -1) : code;
        const answerCode = escape(serial.answer_code[0]);
        const testCode = escape(serial.test_code[0]);
        const code = fillWrapper(
            answerCode,
            testCode,
            serial.prefix_ans[0],
            this.runWrapper,
            this.uiParams.open_delimiter,
            this.uiParams.close_delimiter
        );
        this.outputDisplay.runCode(code, '', true); // Call with no stdin.
    }

    updateContext(preload) {
        this.context = {
            "id": this.textAreaId,
            "disable_scratchpad": this.uiParams.disable_scratchpad,
            "scratchpad_name": this.uiParams.scratchpad_name,
            "button_name": this.uiParams.button_name,
            "help_text": {"text": this.uiParams.help_text},
            "answer_code": {
                "id": this.textAreaId + '_answer-code',
                "name": "answer_code",
                "text": preload.answer_code[0],
                "lang": this.lang,
                "rows": this.numRows
            },
            "test_code": {
                "id": this.textAreaId + '_test-code',
                "name": "test_code",
                "text": preload.test_code[0],
                "lang": this.lang,
                "rows": 6
            },
            "show_hide": {
                "id": this.textAreaId + '_scratchpad',
                "show": preload.show_hide[0]
            },
            "prefix_ans": {
                "id": this.textAreaId + '_prefix-ans',
                "label": this.uiParams.prefix_name,
                "checked": preload.prefix_ans[0]
            },
            "output_display": {
                "id": this.textAreaId + '_run-output'
            },
            // Bootstrap collapse requires jQuery friendly ids to work...
            "jquery_escape": function() {
                return function(text, render) {
                    return CSS.escape(render(text));
                };
            }
        };
    }

    readJson(preloadString) {
        const defaultSerial = {
            "answer_code": [''],
            "test_code": [''],
            "show_hide": [''],
            "prefix_ans": ['1'] // Ticked by default!
        };
        let serial;
        if (preloadString !== "") {
            try {
                serial = JSON.parse(preloadString);
            } catch {
                // Preload is not JSON, so use preloaded string as answer_code.
                serial = {"answer_code": [preloadString]};
            }
            if (!serial.hasOwnProperty("answer_code")) {
                // No student_answer field... something is wrong!
                throw TypeError("JSON has wrong signature, missing answer_code field.");
            }
        }
        serial = overwriteValues(defaultSerial, serial);

        if (this.invertPreload) {
            serial.prefix_ans = invertSerial(serial.prefix_ans);
        }
        return serial;
    }

    async reload() {
        try {
            const {html} = await Templates.renderForPromise('qtype_coderunner/scratchpad_ui', this.context);
            this.drawUi(html);
            this.addAceUis();
            this.outputDisplay = new OutputDisplayArea(
                this.context.output_display.id,
                this.uiParams.output_display_mode,
                this.uiParams.run_lang,
                this.uiParams.params
            );
            this.addEventListeners();
        } catch (e) {
            this.fail = true;
            this.failString = "scratchpad_ui_templateloadfail";
        }
    }

    drawUi(html) {
        const wrapperDiv = document.getElementById(this.textAreaId).nextSibling;
        wrapperDiv.innerHTML = html;
        this.outerDiv = wrapperDiv.firstChild;
        // No resizing the outer wrapper. Instead, resize the two sub UIs,
        // they will expand accordingly.
        wrapperDiv.style.resize = 'none';
    }

    addAceUis() {
        this.answerTextarea = document.getElementById(this.context.answer_code.id);
        this.testTextarea = document.getElementById(this.context.test_code.id);
        this.answerCodeUi = newUiWrapper('ace', this.context.answer_code.id);
        this.answerCodeUi.setAllowFullScreen(true);
        if (this.testTextarea) {
            this.testCodeUi = newUiWrapper('ace', this.context.test_code.id);
            this.testCodeUi.setAllowFullScreen(true);
        }
    }

    addEventListeners() {
        const runButton = document.getElementById(this.textAreaId + '_run-btn');
        if (runButton) {
            runButton.addEventListener('click', () => this.handleRunButtonClick());
        }
    }

    resize() {} // Nothing to see here. Move along please.

    hasFocus() {
        let focused = false;
        if (this.answerCodeUi?.uiInstance.hasFocus()) {
            focused = true;
        }
        if (this.testCodeUi?.uiInstance.hasFocus()) {
            focused = true;
        }
        return focused;
    }

    destroy() {
        this.sync();
        this.answerCodeUi?.uiInstance.destroy();
        this.testCodeUiCodeUi?.uiInstance.destroy();
        this.outerDiv?.remove();
        this.outerDiv = null;
    }


}


export {ScratchpadUi as Constructor};
