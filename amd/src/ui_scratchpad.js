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
 * edit code in both.
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
 * . The fields of that object are the names
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
 *    - html_output: when true, the output from run will be displayed as raw HTML instead of text.
 *    - disable_scratchpad: disable the scratchpad, effectively returning back to Ace UI
 *      from student perspective.
 *    - invert_prefix: inverts meaning of prefix_ans serialisation -- '1' means un-ticked, vice versa.
 *      This can be used to swap the default state.
 *
 * @module coderunner/ui_scratchpad
 * @copyright  Richard Lobb, 2022, The University of Canterbury
 * @copyright  James Napier, 2022, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

import ajax from 'core/ajax';
import {get_string as getLangString} from 'core/str';
import Templates from 'core/templates';

import {newUiWrapper} from 'qtype_coderunner/userinterfacewrapper';


const RESULT_SUCCESS = 15; // Code for a correct Jobe run.
const DEFAULT_MAX_OUTPUT_LEN = 30000;


/**
 * Escape text special HTML characters.
 * @param {string} text
 * @returns {string} text with various special chars replaced with equivalent
 * html entities. Newlines are replaced with <br>.
 */
const escapeHtml = text => {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) {
        return map[m];
    });
};

/**
 * Analyse the response for errors. There are two sorts of error: sandbox failures,
 * for which the field response.error is non-zero meaning the run didn't take
 * place at all and failures in the run
 * itself, such as compile errors, timeouts, runtime errors etc. The
 * various codes are documented in the CodeRunner file sandbox.php.
 * Some error returns, notably compilation error and runtime error, are not
 * treated as errors here, since the stdout + stderr should reveal what
 * happened anyway. More obscure errors are lumped together as 'Unknown
 * runtime error'.
 * @param {object} response The response from the web-service sandbox request.
 * @returns string The language string to use for an error message or '' if
 * no error message.
 */
const diagnose = response => {
    // Table of error conditions.
    // Each row is response.error, response.result, langstring
    // response.result is ignored if response.error is non-zero.
    // Any condition not in the table is deemed an "Unknown runtime error".
    const ERROR_RESPONSES = [
        [1, 0, 'error_access_denied'], // Sandbox AUTH_ERROR
        [2, 0, 'error_unknown_language'], // Sandbox WRONG_LANG_ID
        [3, 0, 'error_access_denied'], // Sandbox ACCESS_DENIED
        [4, 0, 'error_submission_limit_reached'], // Sandbox SUBMISSION_LIMIT_EXCEEDED
        [5, 0, 'error_sandbox_server_overload'], // Sandbox SERVER_OVERLOAD
        [0, 11, ''], // RESULT_COMPILATION_ERROR
        [0, 12, ''], // RESULT_RUNTIME_ERROR
        [0, 13, 'error_timeout'], // RESULT TIME_LIMIT
        [0, RESULT_SUCCESS, ''], // RESULT_SUCCESS
        [0, 17, 'error_memory_limit'], // RESULT_MEMORY_LIMIT
        [0, 21, 'error_sandbox_server_overload'], // RESULT_SERVER_OVERLOAD
        [0, 30, 'error_excessive_output'] // RESULT OUTPUT_LIMIT
    ];
    for (const row of ERROR_RESPONSES) {
        if (row[0] == response.error && (response.error != 0 || response.result == row[1])) {
            return row[2];
        }
    }
    return 'error_unknown_runtime';
};

/**
 * Get the specified language string using
 * AJAX and plug it into the given textarea
 * @param {string} langStringName The language string name.
 * @param {DOMnode} textarea The textarea into which the error message
 * should be plugged.
 * @param {string} additionalText Extra text to follow the result code.
 */
const setLangString = async(langStringName, textarea, additionalText) => {
    const message = await getLangString(langStringName, 'filter_ace_inline');
    textarea.show();
    textarea.html(escapeHtml("*** " + message + " ***\n" + additionalText));
};

/**
 * Concatenates the cmpinfo, stdout and stderr fields of the sandbox
 * response, truncating both stdout and stderr to a given maximum length
 * if necessary (in which case '... (truncated)' is appended.
 * @param {object} response Sandbox response object
 * @param {int} maxLen The maximum length of the trimmed stringlen.
 */
const combinedOutput = (response, maxLen) => {
    const limit = s => s.length <= maxLen ? s : s.substr(0, maxLen) + '... (truncated)';
    return response.cmpinfo + limit(response.output) + limit(response.stderr);
};

/**
 * Invert serialisation from '1' to '', vice versa.
 * @param {string} current serialisation.
 * @returns {string} inverted serialisation.
 */
const invertSerial = current => current[0] == '1' ? [''] : ['1'];

/**
 * Insert the answer code and test code into the wrapper. This may
 * defined by the user, in UI Params or globalextra. If prefixAns is
 * false: do not include answerCode in final wrapper.
 * @param {string} answerCode text.
 * @param {string} testCode text.
 * @param {string} prefixAns '1' for true, '' for false.
 * @param {string} template provided in UI Params or globalextra.
 * @returns {string} filled template.
 */
const fillWrapper = (answerCode, testCode, prefixAns, template) => {
    if (!template) {
        template = '{{ ANSWER_CODE }}\n' +
                   '{{ SCRATCHPAD_CODE }}';
    }
    if (!prefixAns) {
        answerCode = '';
    }
    template = template.replaceAll('{{ ANSWER_CODE }}', answerCode);
    template = template.replaceAll('{{ SCRATCHPAD_CODE }}', testCode);
    return template;
};

/**
 * Returns a new object contain default values. If a matching key exists in
 * prescribed, the corresponding value from prescribed will replace the defualt value.
 * Does not add keys/values to the result if that key is not in defualts.
 * @param {object} defaults object with values to be overwritten.
 * @param {object} prescribed settings, typically set by a user.
 * @returns {object} filled with defualt values, overwritten by their prescribed value (iff included).
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
    if (!el.classList.contains('collapse')) {
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
            run_lang: uiParams.lang, // Use answer's ace language if not specified.
            html_output: false,
            disable_scratchpad: false,
            wrapper_src: null
        };
        this.textArea = document.getElementById(textAreaId);
        this.textAreaId = textAreaId;
        this.height = height;
        this.readOnly = this.textArea.readonly;
        this.fail = false;
        this.outerDiv = null;
        this.invertPreload = uiParams.invert_prefix;
        this.lang = uiParams.lang; // Todo: this vs this.ui params
        this.numRows = this.textArea.rows;
        this.uiParams = overwriteValues(DEF_UI_PARAMS, uiParams);
        this.runWrapper = this.getRunWrapper();
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
        let serialisation = {
            answer_code: [''],
            test_code: [''],
            show_hide: [''],
            prefix_ans: ['']
        };
        if (this.answerTextarea) {
            serialisation.answer_code = [this.answerTextarea.value];
        }
        if (this.testTextarea) {
            serialisation.test_code = [this.testTextarea.value];
        }
        if (showHide && !isCollapsed(showHide)) {
            serialisation.show_hide = ['1'];
        }
        if (prefixAns?.checked || this.context.disable_scratchpad) {
            serialisation.prefix_ans = ['1'];
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

    async handleRunButtonClick(ajax, outputDisplayArea) {
        outputDisplayArea = $(outputDisplayArea);
        this.sync(); // Use up-to-date serialization.

        const htmlOutput = this.uiParams.html_output;
        const maxLen = this.uiParams['max-output-length'] || DEFAULT_MAX_OUTPUT_LEN;
        const preloadString = $(this.textArea).val();
        const serial = this.readJson(preloadString);
        const params = this.uiParams.params;
        const code = fillWrapper(
                serial.answer_code,
                serial.test_code,
                serial.prefix_ans[0],
                this.runWrapper
        );

        // Clear all output areas.
        outputDisplayArea.html('');
        if (htmlOutput) {
            outputDisplayArea.hide();
        }
        outputDisplayArea.next('div.filter-ace-inline-html').remove(); // TODO: Naming


        ajax.call([{
                methodname: 'qtype_coderunner_run_in_sandbox',
                args: {
                    contextid: M.cfg.contextid, // Moodle context ID
                    sourcecode: code,
                    language: this.uiParams.run_lang,
                    params: JSON.stringify(params) // Sandbox params
                },
                done: function(responseJson) {
                    const response = JSON.parse(responseJson);
                    const error = diagnose(response);
                    if (error === '') {
                        // If no errors or compilation error or runtime error
                        if (!htmlOutput || response.result !== RESULT_SUCCESS) {
                            // Either it's not HTML output or it is but we have compilation or runtime errors.
                            const text = combinedOutput(response, maxLen);
                            outputDisplayArea.show();
                            if (text.trim() === '') {
                                outputDisplayArea.html('<span style="color:red">< No output! ></span>');
                            } else {
                                outputDisplayArea.html(escapeHtml(text));
                            }
                        } else { // Valid HTML output - just plug in the raw html to the DOM.
                            // Repeat the deletion of previous output in case of multiple button clicks.
                            outputDisplayArea.next('div.filter-ace-inline-html').remove();

                            const html = $("<div class='filter-ace-inline-html '" +
                                    "style='background-color:#eff;padding:5px;'>" +
                                    response.output + "</div>");
                            outputDisplayArea.after(html);
                        }
                    } else {
                        // If an error occurs, display the language string in the
                        // outputDisplayArea plus additional info.
                        let extra = response.error == 0 ? combinedOutput(response, maxLen) : '';
                        if (error === 'error_unknown_runtime') {
                            extra += response.error ? '(Sandbox error code ' + response.error + ')' :
                                    '(Run result: ' + response.result + ')';
                        }
                        setLangString(error, outputDisplayArea, extra);
                    }
                },
                fail: function(error) {
                    alert(error.message);
                }
            }]);
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
                "id": this.textAreaId + '_output-displayarea'
            },
            // Bootstrap collapse requires jQuerry friendly ids to work...
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
        try {
            const {html} = await Templates.renderForPromise('qtype_coderunner/scratchpad_ui', this.context);
            this.drawUi(html);
            this.addAceUis();
            this.addEventListeners();
        } catch (e) {
            this.fail = true;
            this.failString = "scratchpad_ui_templateloadfail";
            return;
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
        if (this.testTextarea) {
            this.testCodeUi = newUiWrapper('ace', this.context.test_code.id);
        }
    }

    addEventListeners() {
        const runButton = document.getElementById(this.textAreaId + '_run-btn');
        const outputDisplayarea = document.getElementById(this.context.output_display.id);
        if (runButton) {
            runButton.addEventListener('click', () => this.handleRunButtonClick(ajax, outputDisplayarea));
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
