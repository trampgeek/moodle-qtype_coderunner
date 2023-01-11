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
    const DEFUALT_MAX_OUTPUT_LEN = 30000;




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
        const limit = s => s.length <= maxLen ? s : s.substr(0, maxLen) + '... (truncated)';
        return response.cmpinfo + limit(response.output) + limit(response.stderr);
        return response.cmpinfo + (response.output) + (response.stderr);
    }


    /**
     * Create HTML for a text area.
     * @param {string} id for text area.
     * @param {string} name The ID of the html textarea.
     * @param {string} value The ID of the html textarea.
     * @return {string} HTML string.
     */
    function htmlTextArea(id, name, value) {
        return `<textarea
                    id='${id}'
                    class='coderunner-ui-element' 
                    name='${name}' 
                    style='width: 100%'
                   >${value}</textarea>`;
    }


    /**
     * Create HTML for an input.
     * @param {string} id for input.
     * @param {string} labelId for label tag.
     * @param {string} name for the html input.
     * @param {string} label text.
     * @param {string} value trype of the html input.
     * @param {string} type type of the html input.
     * @return {string} HTML string with iput and label.
     */
    function htmlInput(id, labelId, name, label, value, type) {
        const checked = (value && value) ? 'checked' : '';
        const labelHtml = `<label
            id='${labelId}' 
            for='${id}' 
            style='display:inline-block; margin-left: 3px;'
            >${label}</label>`;
        const inputHtml = `<input
                id='${id}'
                type='${type}'
                ${checked}
                class='coderunner-ui-element'
                name='${name}' 
                value='${value}'>`;
        return inputHtml + labelHtml;
    }

    /**
     * Invert serialisation from '1' to '', vice versa.
     * @param {string} current serialisation.
     * @returns {string} inverted serialisation.
     */
    function invertSerial(current) {
        return current[0] == '1' ? [''] : ['1'];
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
        template = template.replaceAll('{{ ANSWER_CODE }}', answerCode);
        template = template.replaceAll('{{ SCRATCHPAD_CODE }}', testCode);
        return template;
    }


    /**
     * Returns a new Ace UI that serializes to the text area with provided ID.
     * @param {string} textAreaId for ace to manage.
     * @returns {InterfaceWrapper} new user interface wrapper containing Ace UI.
     */
    function newAceUiWrapper(textAreaId) {
        let ace;
        require(['qtype_coderunner/userinterfacewrapper'], function (uiWrapper) {
            ace = uiWrapper.newUiWrapper('ace', textAreaId);
        });
        return ace;
    }


    /**
     * Turn camelCase to hyphon-case.
     * @param {String} str camelString.
     * @returns {String} hyphon-string.
     */
    function camelToHyphenated(str) {
        const isUpperCase = char => char === char.toUpperCase();
        const isAlpha = char => char.toUpperCase() !== char.toLowerCase();
        return [...str].map((char) => {
            if (isAlpha(char) && isUpperCase(char)) {
                return `-${char.toLowerCase()}`;
            } else {
                return char;
            }
        }).join("");
    }


    /**
     * ...
     * @param {String} parentId of parent node.
     * @param {Object} nodes of DOM to be managed
     */
    function LangStringManager(parentId, nodes) {
        this.parentId = parentId;
        this.nodes = {};
        if (nodes) {
            this.addNodes(nodes);
        }
    }

    LangStringManager.prototype.addNode = function (name, key) {
        if (name in this.nodes) {
            throw "Cannot add a node twice!";
        }
        this.nodes[name] = {
            id: this.parentId + camelToHyphenated(name),
            key
        };
    };

    LangStringManager.prototype.addNodes = function (obj) {
        for (const [name, key] of Object.entries(obj)) {
            this.addNode(name, key);
        }
    };

    LangStringManager.prototype.setCallback = function (name, callback) {
        this.nodes[name].callback = callback;
    };

    LangStringManager.prototype.getNode = function (name) {
        if (!this.nodes[name]) {
            throw `Cannot find node: ${name}`;
        }
        return this.nodes[name];
    };

    LangStringManager.prototype.getId = function (name) {
        return this.getNode(name).id;
    };

    LangStringManager.prototype.getKey = function (name) {
        return this.getNode(name).key;
    };

    LangStringManager.prototype.setLangString = async function (name, str) {
        const id = this.getId(name);
        const key = this.getKey(name);
        const element = document.getElementById(id);
        let langStr;
        if (!element) {
            return;
        }
        try {
            langStr = await str.get_string(key, 'qtype_coderunner');
        } catch (error) {
            langStr = key;
        }
        if (this.nodes[name].callback) {
            this.nodes[name].callback(element, langStr);
        } else if (!element.textContent) { // Only replace if not already set.
            element.textContent = langStr;
        }
    };

    LangStringManager.prototype.setAllLangStrings = async function (str) {
        for (const name of Object.keys(this.nodes)) {
            await this.setLangString(name, str);
        }
    };




    /**
     * Constructor for the ScratchpadUi object.
     * @param {string} textAreaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea.
     * @param {int} height The height in pixels of the textarea.
     * @param {object} uiParams The UI parameter object.
     */
    function ScratchpadUi(textAreaId, width, height, uiParams) {
        const UI_LANGUAGE_STR = {
            scratchpadName: 'scratchpadui_def_scratchpad_name',
            buttonName: 'scratchpadui_def_button_name',
            prefixName: 'scratchpadui_def_prefix_name',
            helpText: 'scratchpadui_def_help_text'
        };

        this.textArea = $(document.getElementById(textAreaId));
        this.textAreaId = textAreaId;
        this.height = height;
        this.readOnly = this.textArea.prop('readonly');
        this.uiParams = uiParams;
        this.fail = false;

        this.langStringManager = new LangStringManager(this.textAreaId, UI_LANGUAGE_STR);
        this.langStringManager.setCallback('scratchpadName', (element, langStr) => {
            if (!element.innerText) {
                element.insertAdjacentText('beforeend',langStr);
            }
        });
        this.langStringManager.setCallback('helpText', (element, langStr) => {
            if (!element.dataset.content) {
                element.dataset.content = langStr;
            }
        });

        this.spName = uiParams.scratchpad_name || '';
        this.spButtonName = uiParams.button_name || '';
        this.spPrefixName = uiParams.prefix_name || '';
        this.spHelptext = uiParams.help_text || '';

        this.spRunLang = uiParams.run_lang || this.uiParams.lang; // use answer's ace language if not specified.
        this.spHtmlOutput = uiParams.html_output || false;

        // Find the run wrapper source location.
        this.spRunWrapper = null;
        const wraperSrc = uiParams.wrapper_src;
        if (wraperSrc) {
            if (wraperSrc === 'globalextra' || wraperSrc === 'prototypeextra') {
                this.spRunWrapper = this.textArea.attr('data-' + wraperSrc);
            } else {
                // TODO: raise some sort of exception? Invalid, params.
                //  Bad wrapper src provided by user...
                this.spRunWrapper = null;
            }
        }

        this.outerDiv = null;
        this.scratchpadDiv = null;
        this.reload(); // Draw my beautiful blobs.
    }

    ScratchpadUi.prototype.failed = function () {
        return this.fail;
    };

    ScratchpadUi.prototype.failMessage = function () {
        return 'ScratchpadUiloadfail';
    };

    ScratchpadUi.prototype.sync = function () {
        const prefixAns = $(document.getElementById(this.textAreaId + '-prefix-ans'));
        let serialisation = {
            answer_code: [this.answerTextArea.val() || ''],
            test_code: [this.spCodeTextArea.val() || ''],
            show_hide: [''],
            prefix_ans: ['']
        };
        if (this.scratchpadDiv.is(':visible')) {
            serialisation.show_hide = ['1'];
        }
        if (prefixAns.is(':checked')) {
            serialisation.prefix_ans = ['1'];
        }
        serialisation.prefix_ans = invertSerial(serialisation.prefix_ans);
        if (Object.values(serialisation).some((val) => val[0].length > 0)) {
            serialisation.prefix_ans = invertSerial(serialisation.prefix_ans);
            this.textArea.val(JSON.stringify(serialisation));
        } else {
            this.textArea.val(''); // All feilds empty...
        }
    };

    ScratchpadUi.prototype.getElement = function () {
        return this.outerDiv;
    };

    ScratchpadUi.prototype.handleRunButtonClick = async function (ajax, outputDisplayArea) {
        this.sync(); // Use up-to-date serialization.

        const htmlOutput = this.spHtmlOutput;
        const maxLen = this.uiParams['max-output-length'] || DEFUALT_MAX_OUTPUT_LEN;
        const preloadString = this.textArea.val();
        const serial = JSON.parse(preloadString);
        const params = this.uiParams.params;
        let code;

        // Clear all output areas.
        outputDisplayArea.html('');
        if (htmlOutput) {
            outputDisplayArea.hide();
        }
        outputDisplayArea.next('div.filter-ace-inline-html').remove(); //TODO: Naming

        if (this.spRunWrapper) { // Wrap the code if a wrapper exists.
            code = fillWrapper(
                    serial.answer_code,
                    serial.test_code,
                    serial.prefix_ans[0],
                    this.spRunWrapper
                    );
        } else { // No wrapper.
            code = combineCode(serial.answer_code, serial.test_code, serial.prefix_ans[0]);
        }

        ajax.call([{
                methodname: 'qtype_coderunner_run_in_sandbox',
                args: {
                    contextid: M.cfg.contextid, // Moodle context ID
                    sourcecode: code,
                    language: this.spRunLang,
                    params: JSON.stringify(params) // Sandbox params
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
                fail: function (error) {
                    alert(error.message);
                }
            }]);
    };

    ScratchpadUi.prototype.reload = function () {
        const preloadString = $(this.textArea).val();
        const answerTextAreaId = this.textAreaId + '-answer-code';
        const spTextAreaId = this.textAreaId + '-sp-code';
        let preload = {
            answer_code: [''],
            test_code: [''],
            show_hide: [''],
            prefix_ans: ['1'] // Ticked by defualt!
        };
        if (preloadString) {
            try {
                preload = JSON.parse(preloadString);
            } catch (error) {
                this.fail = true;
                this.failString = 'scratchpad_ui_invalidserialisation';
                return;
            }
        }

        this.drawUi(answerTextAreaId, preload);
        this.drawScratchpadUi(spTextAreaId, preload);
        require(['core/str'], (str) =>
            this.langStringManager.setAllLangStrings(str)
        );
        this.answerCodeUi = newAceUiWrapper(answerTextAreaId);
        this.spCodeUi = newAceUiWrapper(spTextAreaId);

        // No resizing the outer wrapper. Instead, resize the two sub UIs,
        // they will expand accordingly.
        $(document.getElementById(this.textAreaId + '_wrapper')).css('resize', 'none');
    };

    ScratchpadUi.prototype.drawUi = function (answerTextAreaId, preload) {
        const divHtml = "<div style='min-height:100%' class='qtype-coderunner-sp-outer-div'></div>";
        const answerTextAreaHtml = htmlTextArea(answerTextAreaId, 'answer_code', preload['answer_code'][0]);
        const showButtonHtml = `<a 
            role='button'
            id='${this.langStringManager.getId('scratchpadName')}'
            title='show_hide'
            data-toggle="collapse"
            class="btn btn-sm btn-icon icons-collapse-expand text-info"
            style="margin-top: 5px;margin-left: 5px;margin-bottom: 5px;width: 30px;height: 30px;"
            >
            <span class="expanded-icon icon-no-margin p-2" title="Collapse">
                <i class="icon fa fa-chevron-down fa-fw " aria-hidden="true"></i>
            </span>
            <span class="collapsed-icon icon-no-margin p-2" title="Expand">
                <span class="dir-rtl-hide"><i class="icon fa fa-chevron-right fa-fw" aria-hidden="true"></i></span>
            </span>
            ${this.spName}
            </a>`;
        const answerTextArea = $(answerTextAreaHtml);
        const showButton = $(showButtonHtml);

        answerTextArea.attr('rows', this.textArea.attr('rows'));

        this.outerDiv = $(divHtml);

        this.answerTextArea = answerTextArea;
        this.answerTextArea.attr('data-lang', this.uiParams.lang); //Set language for Ace to use.
        this.outerDiv.append([answerTextArea, showButton]);

        this.scratchpadDiv = $(`<fieldset class="collapse show" id="${this.textAreaId}-scratchpad"></fieldset>`);

        $(showButton).attr('href', `#${ $.escapeSelector($(this.scratchpadDiv).attr('id')) }`);

        if (!preload['show_hide'][0]) {
            $(this.scratchpadDiv).removeClass("show");
        }
    };

    ScratchpadUi.prototype.scratchpadControls = function (outputDisplayArea, preload) {
        const controlsDiv = $("<div class='scratchpad-contols' " +
                "style='border-bottom: darkgray solid 1px;'></div>");
        const prefixAns = $(htmlInput(
                this.textAreaId + '-prefix-ans',
                this.langStringManager.getId('prefixName'),
                'prefix_ans',
                this.spPrefixName,
                preload['prefix_ans'][0],
                'checkbox'
                ));
        const runButton = $(`<button type='button'
                id='${this.langStringManager.getId('buttonName')}'
                class='btn btn-secondary'
                style='margin:6px; margin-right:10px; padding:2px 8px;'
                >${this.spButtonName}</button>`);
        // Help popover.
        const helpButton = $(`<a 
            id="${this.langStringManager.getId('helpText')}" 
            class="btn btn-link p-0" role="button" data-container="body"
            data-toggle="popover" data-placement="right" data-content="${this.spHelptext}"
            data-html="true" tabindex="0" data-trigger="focus" 
            data-original-title="" title="">
                <i class="icon fa fa-question-circle text-info" style="margin-right: 0px;" 
                title="Help with Scratchpad" role="img" aria-label="Help with Scratchpad">
                </i>
            </a>`);

        const rightSpan = $('<span style="float:right;color:#0f6cbf;padding:8px"></span>');
        const t = this;
        runButton.on('click', function () {
            require(['core/ajax'], function (ajax) {
                t.handleRunButtonClick(ajax, outputDisplayArea);
            });
        });
        rightSpan.append(helpButton);
        controlsDiv.append([runButton, prefixAns, rightSpan]);
        return controlsDiv;
    };

    ScratchpadUi.prototype.drawScratchpadUi = function (spTextAreaId, preload) {
        const testCodeHtml = htmlTextArea(spTextAreaId, 'test_code', preload['test_code']);
        const outputDisplayArea = $("<pre style='width:100%;white-space:pre-wrap;background-color:#eff;" +
                "border:1px gray;padding:5px;overflow-wrap:break-word;max-height:600px;overflow:auto;'></pre>");
        const scratchpadControls = this.scratchpadControls(outputDisplayArea, preload);

        this.spCodeTextArea = $(testCodeHtml);
        this.spCodeTextArea.attr('data-lang', this.uiParams.lang); //Set language for Ace to use.
        this.spCodeTextArea.attr('rows', '6'); //Set intial SP size.
        this.scratchpadDiv.append([this.spCodeTextArea, scratchpadControls, outputDisplayArea]);
        this.outerDiv.append(this.scratchpadDiv);
    };

    ScratchpadUi.prototype.resize = function () {}; // Nothing to see here. Move along please.

    ScratchpadUi.prototype.hasFocus = function () {
        let focused = false;
        if (this.answerCodeUi && this.answerCodeUi.hasFocus()) {
            focused = true;
        }
        if (this.spCodeUi && this.spCodeUi.hasFocus()) {
            focused = true;
        }
        return focused;
    };

    // Destroy the HTML UI and serialise the result into the original text area.
    ScratchpadUi.prototype.destroy = function () {
        this.sync();
        $(this.outerDiv).remove();
        this.outerDiv = null;
    };

    return {
        Constructor: ScratchpadUi
    };
});
