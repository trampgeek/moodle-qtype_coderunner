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
 * Implementation of the html_ui user interface plugin. For overall details
 * of the UI plugin architecture, see userinterfacewrapper.js.
 *
 * This plugin replaces the usual textarea answer element with a div
 * containing the author-supplied HTML. The serialisation of that HTML,
 * which is what is essentially copied back into the textarea for submissions
 * as the answer, is a JSON object. The fields of that object are the names
 * of all author-supplied HTML elements with a class 'coderunner-ui-element';
 * all such objects are expected to have a 'name' attribute as well. The
 * associated field values are lists. Each list contains all the values, in
 * document order, of the results of calling the jquery val() method in turn
 * on each of the UI elements with that name.
 * This means that at least input, select and textarea
 * elements are supported. The author is responsible for checking the
 * compatibility of other elements with jquery's val() method.
 *
 * The HTML to use in the answer area must be provided as the contents of
 * either the globalextra field or the prototypeextra field in the question
 * authoring form. The choice of which is set by the html_src UI parameter, which
 * must be either 'globalextra' or 'prototypeextra'.
 *
 * If any fields of the answer html are to be preloaded, these should be specified
 * in the answer preload with json of the form '{"<fieldName>": "<fieldValueList>",...}'
 * where fieldValueList is a list of all the values to be assigned to the fields
 * with the given name, in document order.
 *
 * To accommodate the possibility of dynamic HTML, any leftover preload values,
 * that is, values that cannot be positioned within the HTML either because
 * there is no field of the required name or because, in the case of a list,
 * there are insufficient elements, are assigned to the data['leftovers']
 * attribute of the outer html div, as a sub-object of the original object.
 * This outer div can be located as the 'closest' (in a jQuery sense)
 * div.qtype-coderunner-html-outer-div. The author-supplied HTML must include
 * JavaScript to make use of the 'leftovers'.
 *
 * As a special case of the serialisation, if all values in the serialisation
 * are either empty strings or a list of empty strings, the serialisation is
 * itself the empty string.
 *
 * @module coderunner/ui_html
 * @copyright  Richard Lobb, 2022, The University of Canterbury
 * @copyright  James Napier, 2022, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    const RESULT_SUCCESS = 15; // Code for a correct Jobe run.
    const DEFUALT_MAX_OUTPUT_LEN = 100;


    /**
     * Escape text special HTML characters.
     * @param {string} text
     * @returns {string} text with various special chars replaced with equivalent
     * html entities. Newlines are replaced with <br>.
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }


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
    function diagnose(response) {
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
            [0, 30, 'error_excessive_output']  // RESULT OUTPUT_LIMIT
        ];
        for (const row of ERROR_RESPONSES) {
            if (row[0] == response.error && (response.error != 0 || response.result == row[1])) {
                return row[2];
            }
        }
        return 'error_unknown_runtime';
    }

    /**
     * Get the specified language string using
     * AJAX and plug it into the given textarea
     * @param {string} langStringName The language string name.
     * @param {DOMnode} textarea The textarea into which the error message
     * should be plugged.
     * @param {string} additionalText Extra text to follow the result code.
     */
    function setLangString(langStringName, textarea, additionalText) {
        require(['core/str'], function (str) {
            const promise = str.get_string(langStringName, 'filter_ace_inline');
            $.when(promise).then(function (message) {
                textarea.show();
                textarea.html(escapeHtml("*** " + message + " ***\n" + additionalText));
            });
        });
    }


    /**
     * Concatenates the cmpinfo, stdout and stderr fields of the sandbox
     * response, truncating both stdout and stderr to a given maximum length
     * if necessary (in which case '... (truncated)' is appended.
     * @param {object} response Sandbox response object
     * @param {int} maxLen The maximum length of the trimmed stringlen.
     */
    function combinedOutput(response, maxLen) {
//        const limit = s => s.length <= maxLen ? s : s.substr(0, maxLen) + '... (truncated)';
        //return response.cmpinfo + limit(response.output) + limit(response.stderr);
        return response.cmpinfo + (response.output) + (response.stderr);
    }


    /**
     * Create text area...
     * @param {string} name The ID of the html textarea.
     * @param {string} value The ID of the html textarea.
     * @return {string} value area html string.
     */
    function htmlTextArea(name, value) {
        return `<textarea
                    class='coderunner-ui-element' 
                    name='${name}' 
                    style='width:100%;height:250px'>${value}</textarea>`;
    }


    /**
     * Create input feild...
     * @param {string} name The name for the html input.
     * @param {string} label text.
     * @param {string} value trype of the html input.
     * @param {string} type type of the html input.
     * @return {string} HTML string containing both input and label elements.
     */
    function htmlInput(name, label, value, type) {
        const checked = (value && value[0]) ? 'checked' : '';
        const labelHtml = `<label for='${name}'>${label}</label>`;
        const inputHtml = "<input " +
                `type='${type}' ` +
                `${checked} ` +
                "class='coderunner-ui-element' " +
                `name='${name}' ` +
                `value='${value}'>`;
        return inputHtml + labelHtml;
    }


    /**
     * Combine answer code with scratchpad code. If prefixAns is false:
     * only include testCode.
     * @param {string} answerCode text.
     * @param {string} testCode text.
     * @param {string} prefixAns '1' for true, '' for false.
     * @returns {string} The combined code.
     */
    function combineCode(answerCode, testCode, prefixAns) {
        let combined = prefixAns ? (answerCode + '\n') : '';
        combined += testCode;
        return combined;
    }


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
    function fillWrapper(answerCode, testCode, prefixAns, template) {
        if (!prefixAns) {
            answerCode = '';
        }
        template = template.replaceAll('{{ STUDENT_ANSWER }}', answerCode);
        template = template.replaceAll('{{ TEST_CODE }}', testCode);
        return template;
    }



    /**
     * Constructor for the DualBlobUi object.
     * @param {string} textareaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea.
     * @param {int} height The height in pixels of the textarea.
     * @param {object} uiParams The UI parameter object.
     */
    function DualBlobUi(textareaId, width, height, uiParams) {
        this.textArea = $(document.getElementById(textareaId));
        this.textareaId = textareaId;
        this.height = height;
        this.readOnly = this.textArea.prop('readonly');
        this.uiParams = uiParams;
        this.fail = false;

        this.spName = uiParams.sp_name || 'Scratchpad';
        this.spButtonName = uiParams.sp_button_name || 'Run!';
        this.spPrefixName = uiParams.sp_prefix_name || 'Prefix Answer?';
        this.spRunLang = uiParams.sp_run_lang || this.uiParams.lang; // use answer's ace language if not specified.
        this.spHtmlOutput = uiParams.sp_html_out || false;

        this.spRunWrapper = uiParams.sp_run_wrapper || null;
        if (this.spRunWrapper && this.spRunWrapper === '___GLOBAL_EXTRA___') {
            this.spRunWrapper = this.textArea.attr('data-globalextra');
        }

        this.blobDiv = null;
        this.scratchpadDiv = null;
        this.reload(); // Draw my beautiful blobs.
    }

    DualBlobUi.prototype.failed = function () {
        return this.fail;
    };

    DualBlobUi.prototype.failMessage = function () {
        return 'DualBlobUiloadfail';
    };

    // Copy the serialised version of the HTML UI area to the TextArea.
    DualBlobUi.prototype.sync = function () {
        const t = this;
        let serialisation = {};

        this.getFields().each(function () {
            const name = $(this).attr('name');
            const type = $(this).attr('type');
            let value;
            if ((type === 'checkbox' || type === 'radio')) {
                value = $(this).is(':checked') ? '1' : '';
            } else if ($(this).is('a')) {
                value = t.scratchpadDiv.is(':visible') ? '1' : '';
            } else {
                value = $(this).val();
            }
            if (serialisation.hasOwnProperty(name)) {
                serialisation[name].push(value);
            } else {
                serialisation[name] = [value];
            }
        });
        this.textArea.val(JSON.stringify(serialisation));
    };

    DualBlobUi.prototype.getElement = function () {
        return this.blobDiv;
    };

    DualBlobUi.prototype.getFields = function () {
        return $(this.blobDiv).find('.coderunner-ui-element');
    };

    DualBlobUi.prototype.handleRunButtonClick = async function (ajax, outputDisplayArea) {
        this.sync(); // Make sure to use up-to-date serialization.
        const htmlOutput = this.spHtmlOutput;
        const maxLen = this.uiParams['max-output-length'] || DEFUALT_MAX_OUTPUT_LEN;
        const preloadString = this.textArea.val();
        const serial = JSON.parse(preloadString);
        const params = this.uiParams.params;
        let code;
        
        outputDisplayArea.html(''); // Clear all output areas.
        if (htmlOutput) { 
            outputDisplayArea.hide();
        }
        outputDisplayArea.next('div.filter-ace-inline-html').remove();
        
        if (this.spRunWrapper) { // Wrap the code if a wrapper exists.
            code = fillWrapper(
                    serial.answer_code[0],
                    serial.test_code[0],
                    serial.prefix_ans[0],
                    this.spRunWrapper
                    );
        } else {
            // Combine code if no wrapper.
            code = combineCode(serial.answer_code[0], serial.test_code[0], serial.prefix_ans[0]);
        }

        ajax.call([{
                methodname: 'qtype_coderunner_run_in_sandbox',
                args: {
                    contextid: M.cfg.contextid, // Moodle context ID
                    sourcecode: code,
                    language: this.spRunLang,
                    params: JSON.stringify(params) // todo memlimit? (read the docs)    
                },
                done: function (responseJson) {
                    const response = JSON.parse(responseJson);
                    const error = diagnose(response);
                    if (error === '') {
                        // If no errors or compilation error or runtime error
                        if (!htmlOutput || response.result !== RESULT_SUCCESS) {
                            // Either it's not HTML output or it is but we have compilation or runtime errors.
                            const text = combinedOutput(response, maxLen);
                            outputDisplayArea.show();
                            outputDisplayArea.html(escapeHtml(text));
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
                fail: function (error) {
                    alert(error.message);
                }
            }]);
    };

    DualBlobUi.prototype.reload = async function () {
        const preloadString = $(this.textArea).val();
        let preload = {
            answer_code: [''],
            test_code: [''],
            show_hide: [''],
            prefix_ans: ['']
        };
        try {
            if (preloadString) {
                preload = JSON.parse(preloadString);
            }
        } catch (error) {
            this.fail = true;
            this.failString = 'blob_ui_invalidserialisation';
            return;
        }

        // Main ide.
        const divHtml = "<div></div>";
        const answerTextHtml = htmlTextArea('answer_code', preload['answer_code']);
        const showButtonHtml = "<a class='coderunner-ui-element' " +
                `name='show_hide'>▼${this.spName}</a>`;
        const showButton = $(showButtonHtml);
        const t = this;
        showButton.click(function () {
            const arrow = $(t.scratchpadDiv).is(':visible') ? '▶' : '▼';
            t.scratchpadDiv.toggle();
            showButton.html(arrow + t.spName);
        });

        this.blobDiv = $(divHtml);
        this.blobDiv.css({
                resize: 'none',
                height: this.height,
                width: "100%"
            });
        this.blobDiv.append([$(answerTextHtml), showButton]);


        // Scratchpad.
        this.scratchpadDiv = $(divHtml);
        if (!preload.show_hide[0]) {
            this.scratchpadDiv.hide();
            showButton.html(`▶${this.spName}`);
        }
        const testCodeHtml = htmlTextArea('test_code', preload['test_code']);
        const prefixAnsHtml = htmlInput('prefix_ans', this.spPrefixName, preload['prefix_ans'], 'checkbox');

        const runButton = $("<button type='button' " +
                "class='btn btn-secondary' " +
                "style='margin-bottom:6px;padding:2px 8px;'>" +
                `${this.spButtonName}</button>`);
        require(['core/ajax'], function (ajax) {
            runButton.on('click', function () {
                t.handleRunButtonClick(ajax, outputDisplayArea, preload.test_code[0]);
            });
        });
        const outputDisplayArea = $("<pre style='width:100%;white-space:pre-wrap;background-color:#eff;" +
                "border:1px gray;padding:5px;overflow-wrap:break-word;max-height:600px;overflow:auto;'></pre>");
        outputDisplayArea.hide();
        this.scratchpadDiv.append([$(testCodeHtml), runButton, $(prefixAnsHtml), outputDisplayArea]);

        this.blobDiv.append(this.scratchpadDiv);
    };

    DualBlobUi.prototype.resize = function () {}; // Nothing to see here. Move along please.

    DualBlobUi.prototype.hasFocus = function () {
        let focused = false;
        this.blobDiv.find('textarea').each(function () {
            if (this === document.activeElement) {
                focused = true;
            }
        });
        return focused;
    };

    // Destroy the HTML UI and serialise the result into the original text area.
    DualBlobUi.prototype.destroy = function () {
        this.sync();
        $(this.blobDiv).remove();
        this.blobDiv = null;
    };

    return {
        Constructor: DualBlobUi
    };
});
