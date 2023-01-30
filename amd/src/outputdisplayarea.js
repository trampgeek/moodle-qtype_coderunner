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
 * @copyright  James Napier, 2023, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

import ajax from 'core/ajax';
import {get_string as getLangString} from 'core/str';

const ENTER_KEY = 13;
const INPUT_INTERRUPT = 42;
const INPUT_CLASS = 'coderunner-run-input';

/**
 * Get the specified language string using
 * AJAX and plug it into the given textarea
 * @param {string} langStringName The language string name.
 * @param {DOMnode} textarea The textarea into which the error message
 * should be plugged.
 * @param {string} additionalText Extra text to follow the result code.
 */
const setLangString = async(langStringName, textarea, additionalText) => {
    const message = await getLangString(langStringName, 'filter_ace_inline'); // TODO: FIX!!
    textarea.show();
    textarea.html(escapeHtml("*** " + message + " ***\n" + additionalText));
};

const diagnoseWebserviceResponse = response => {
    // Table of error conditions.
    // Each row is response.error, response.result, langstring
    // response.result is ignored if response.error is non-zero.
    // Any condition not in the table is deemed an "Unknown runtime error".
    const ERROR_RESPONSES = [
        [1, 0, 'Sandbox access denied.'], // Sandbox AUTH_ERROR
        [2, 0, 'error_unknown_language'], // Sandbox WRONG_LANG_ID
        [3, 0, 'Sandbox access denied.'], // Sandbox ACCESS_DENIED
        [4, 0, 'error_submission_limit_reached'], // Sandbox SUBMISSION_LIMIT_EXCEEDED
        [5, 0, 'Sandbox overload. Please wait and try again later'], // Sandbox SERVER_OVERLOAD
        [0, 11, ''], // RESULT_COMPILATION_ERROR
        [0, 12, 'Scratchpad crashed. Out of memory, perhaps?'], // RESULT_RUNTIME_ERROR (supervisor process broke)
        [0, 13, 'Scratchpad time limit error. Please report'], // RESULT TIME_LIMIT (supervisor process broke)
        [0, 15, ''], // RESULT_SUCCESS
        [0, 17, 'Scratchpad memory limit error. Please report'], // RESULT_MEMORY_LIMIT
        [0, 21, 'Sandbox overload. Please wait and try again later'], // RESULT_SERVER_OVERLOAD
        [0, 30, 'Excessive output.'] // RESULT OUTPUT_LIMIT
    ];
    for (let i = 0; i < ERROR_RESPONSES.length; i++) {
        let row = ERROR_RESPONSES[i];
        if (row[0] == response.error && (response.error != 0 || response.result == row[1])) {
            return row[2];
        }
    }
    return 'error_unknown_runtime'; // We're dead, Fred.
};

const getImage = base64 => {
    let image = $(`<img src="data:image/png;base64,${base64}">`);
    return image;
};

/**
 * Constructor for OutputDisplayArea object.
 * @param {string} displayAreaId The id of the display area div.
 * @param {string} outputMode The mode being used for output, must be text, html or json.
 */
class OutputDisplayArea {
    constructor(displayAreaId, outputMode) {
        this.displayAreaId = displayAreaId;
        this.displayArea = document.getElementById(displayAreaId);
        this.textDisplay = document.getElementById(displayAreaId + '-text');
        this.imageDisplay = document.getElementById(displayAreaId + '-images');
        this.mode = outputMode;

        this.stdIn = [];
        this.prevRunSettings = null;
    }

    clearDisplay() {
        this.textDisplay.innerHTML = "";
        this.imageDisplay.innerHTML = "";
    }

    displayText(response) {
        const output = response.output;
        const error = response.stderr;
        this.textDisplay.innerText = output + error;
    }

    displayHtml(response) {
        const output = response.output;
        const error = response.stderr;
        this.textDisplay.innerHTML = output + error;
        const inputEl = this.textDisplay.querySelector('.' + INPUT_CLASS);
        if (inputEl) {
            this.addInputEvents(inputEl);
        }
    }

    displayNoOutput(response) {
        const isNoOutput = response.output === '' && response.stderr === '';
        if (isNoOutput) {
            this.textDisplay.innerHTML = '<span style="color:red">&lt; No output! &gt;</span>'; // TODO: Lang string
        }
        return isNoOutput;
    }

    display(response) {
        if (this.displayNoOutput(response)) {
            return;
        }
        if (this.mode === 'json') {
            // TODO: error handling.
            this.displayJson(response);
        } else if (this.mode === 'html') {
            this.displayHtml(response);
        } else if (this.mode === 'text') {
            this.displayText(response);
        } else {
            throw Error(`Invalid outputMode given: "${this.mode}"`);
        }
    }

    async runCode(code, stdin, lang, sandboxParams, shouldClearDisplay = false) {
        this.prevRunSettings = [code, stdin, lang, sandboxParams];
        if (shouldClearDisplay) {
            this.clearDisplay();
        }
        ajax.call([{
            methodname: 'qtype_coderunner_run_in_sandbox',
            args: {
                contextid: M.cfg.contextid, // Moodle context ID
                sourcecode: code,
                language: lang,
                stdin: stdin,
                params: JSON.stringify(sandboxParams) // Sandbox params
            },
            done: (responseJson) => {
                // TODO: error handling.
                const response = JSON.parse(responseJson);
                // Const error = diagnoseWebserviceResponse(response);
                this.display(response);
            },
            fail: (error) => {
                alert(error.message);
            }
        }]);
    }

    displayJson(response) {
        var result = JSON.parse(response.output);
        var text = result.stdout;

        if (result.returncode !== INPUT_INTERRUPT) {
            text += result.stderr;
        }
        if (result.returncode == 13) { // Timeout
            text += "\n*** Timeout error ***\n"; // TODO: lang string
        }

        var numImages = 0;
        if (result.files) {
            for (var prop in result.files) {
                const image = getImage(result.files[prop]);
                this.imageDisplay.append(image);
                numImages += 1;
            }
        }

        if (text.trim() === '' && result.returncode !== INPUT_INTERRUPT) {
            if (numImages == 0) {
                this.textDisplay.innerHTML = '<span style="color:red">&lt; No output! &gt;</span>'; // TODO: Lang string
            }
        } else {
            this.textDisplay.innerText = text;
        }

        if (result.returncode === INPUT_INTERRUPT) {
            this.addInput();
        }
    }

    addInput() {
        const inputId = `${this.displayAreaId}-input-field`;
        this.textDisplay.innerHTML += `<input type="text" id="${inputId}" class="${INPUT_CLASS}">`;
        const inputEl = document.getElementById(inputId);
        this.addInputEvents(inputEl);
    }

    addInputEvents(inputEl) {
        inputEl.focus();

        inputEl.addEventListener('keydown', (e) => {
            if (e.keyCode === ENTER_KEY) {
                e.preventDefault(); // Do NOT form submit.
            }
        });
        inputEl.addEventListener('keyup', (e) => {
            if (e.keyCode === ENTER_KEY) {
                const line = inputEl.value;
                inputEl.remove();
                this.textDisplay.innterHTML += line; // Perhaps this should be sanitized.
                this.prevRunSettings[1] += line + '\n';
                this.runCode(...this.prevRunSettings, false);
            }
        });

    }
}


export {
    OutputDisplayArea
};
