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

/* jshint esversion: 6 */

/**
 * JavaScript for handling UI actions in the question authoring form.
 *
 * @module qtype_coderunner/authorform
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'qtype_coderunner/userinterfacewrapper', 'core/str'], function($, ui, str) {

    // We need this to keep track of the current question type.
    let currentQtype = "";

    // Define a mapping from the fields of the JSON object returned by an AJAX
    // 'get question type' request to the form elements. Only fields that
    // belong to the question type should appear here. Keys are JSON field
    // names, values are a 3- or 4-element array of: a jQuery form element selector;
    // the element property to be set; a default value if the JSON field is
    // empty and an optional filter function to apply to the field value before
    // setting the property with it.
    var JSON_TO_FORM_MAP = {
        template:            ['#id_template', 'value', ''],
        iscombinatortemplate:['#id_iscombinatortemplate', 'checked', '',
                                function (value) {
                                    return value === '1' ? true : false;
                                }],  // Need nice clean boolean for 'checked' attribute.
        cputimelimitsecs:    ['#id_cputimelimitsecs', 'value', ''],
        memlimitmb:          ['#id_memlimitmb', 'value', ''],
        sandbox:             ['#id_sandbox', 'value', 'DEFAULT'],
        sandboxparams:       ['#id_sandboxparams', 'value', ''],
        testsplitterre:      ['#id_testsplitterre', 'value', '',
                                function (splitter) {
                                    return splitter.replace('\n', '\\n');
                                }],
        allowmultiplestdins: ['#id_allowmultiplestdins', 'checked', '',
                                function (value) {
                                    return value === '1' ? true : false;
                                }],
        grader:              ['#id_grader', 'value', 'EqualityGrader'],
        resultcolumns:       ['#id_resultcolumns', 'value', ''],
        language:            ['#id_language', 'value', ''],
        acelang:             ['#id_acelang', 'value', ''],
        uiplugin:            ['#id_uiplugin', 'value', 'ace']
    };

    /**
     * Set up the author edit form UI plugins and event handlers.
     * The template parameters and Ace language are passed to each
     * text area from PHP by setting its data-params and
     * data-lang attributes.
     */
    function initEditForm() {
        var typeCombo = $('#id_coderunnertype'),
            prototypeDisplay = $('#id_isprototype'),
            template = $('#id_template'),
            evaluatePerStudent = $('#id_templateparamsevalpertry'),
            globalextra = $('#id_globalextra'),
            prototypeextra = $('#id_prototypeextra'),
            useace = $('#id_useace'),
            language = $('#id_language'),
            acelang = $('#id_acelang'),
            customise = $('#id_customise'),
            isCombinator = $('#id_iscombinatortemplate'),
            testSplitterRe = $('#id_testsplitterre'),
            allowMultipleStdins = $('#id_allowmultiplestdins'),
            customisationFieldSet = $('#id_customisationheader'),
            advancedCustomisation = $('#id_advancedcustomisationheader'),
            isCustomised = customise.prop('checked'),
            prototypeType = $('#id_prototypetype'),
            preloadHdr = $('#id_answerpreloadhdr'),
            courseId = $('input[name="courseid"]').prop('value'),
            questiontypeHelpDiv = $('#qtype-help'),
            precheck = $('select#id_precheck'),
            testtypedivs = $('div.testtype'),
            testsection = $('#id_testcasehdr'),
            brokenQuestion = $('#id_broken_question'),
            badQuestionLoad = $('#id_bad_question_load'),
            uiplugin = $('#id_uiplugin'),
            uiparameters = $('#id_uiparameters');

        /**
         * Set up the UI controller for a given textarea (one of template,
         * answer or answerpreload).
         * We don't attempt to process changes in template parameters,
         * as these need to be merged with those of the prototype. But we do handle
         * changes in the language.
         * @param {string} taId The ID of the textarea element.
         * @param {string} uiname The name of the UI controller (may be empty or none).
         */
        function setUi(taId, uiname) {
            var ta = $(document.getElementById(taId)),  // The jquery text area element(s).
                lang,
                currentLang = ta.attr('data-lang'),     // Language set by PHP.
                paramsJson = ta.attr('data-params'),    // Ui params set by PHP.
                params = {},
                uiWrapper;

            // Set data attributes in the text area for UI components that need
            // global extra or testcase data (e.g. gapfiller UI).
            ta.attr('data-prototypeextra', prototypeextra.val());
            ta.attr('data-globalextra', globalextra.val());
            ta.attr('data-test0', $('#id_testcode_0').val());
            try {
                params = JSON.parse(paramsJson);
            } catch(err) {}
            uiname = uiname.toLowerCase();
            if (uiname === 'none') {
                uiname = '';
            }

            if (taId == 'id_templateparams' || taId == 'id_uiparameters') {
                lang = ''; // These fields may be twigged, so can't be parsed by Ace.
            } else {
                lang = language.prop('value');
                if (taId !== "id_template" && acelang.prop('value')) {
                    lang = preferredAceLang(acelang.prop('value'));
                }
            }

            uiWrapper = ta.data('current-ui-wrapper'); // Currently-active UI wrapper on this ta.

            if (uiWrapper && uiWrapper.uiname === uiname && currentLang == lang) {
                return; // We already have what we want - give up.
            }

            ta.attr('data-lang', lang);

            if (!uiWrapper) {
                uiWrapper = new ui.InterfaceWrapper(uiname, taId);
            } else {
                // Wrapper has already been set up - just reload the reqd UI.
                params.lang = lang;
                uiWrapper.loadUi(uiname, params);
            }

        }

        /**
         * Set the correct Ui controller on both the sample answer and the answer preload.
         * The sample answer and answer preload have the data-params attribute which contains
         * the UI params in a JSON from the current question merged with the prototype.
         * Both of them are identical and are changed simultaneously; only checking
         * answer as state is identical.
         * As a special case, we don't turn on the Ui controller in the answer
         * and answer preload fields when using Html-Ui and the ui-parameter
         * enable_in_editor is false.
         *
         */
        function setUis() {
            let uiname = uiplugin.val();
            let answer = $('#id_answer');
            let enableUi = true;
            if (uiname === 'html' && answer.attr('data-params') !== '') {
                try {
                    let answerparams = JSON.parse(answer.attr('data-params'));
                    if (answerparams.enable_in_editor === false) {
                        enableUi = false;
                    }
                } catch (error) {
                    alert("Invalid UI parameters.");
                }
            }
            if (enableUi) {
                setUi('id_answer', uiname);
                setUi('id_answerpreload', uiname);
            }
        }

        /**
         * Display or Hide all customisation parts of the form.
         * @param {bool} isVisible True to show, false to hide.
         */
        function setCustomisationVisibility(isVisible) {
            var display = isVisible ? 'block' : 'none';
            customisationFieldSet.css('display', display);
            advancedCustomisation.css('display', display);
            if (isVisible && useace.prop('checked')) {
                setUi('id_template', 'ace');
            }
        }


        /**
         * Turn on or off the Ace editor in the template and uiparameters fields
         * so we can reload the textareas with JavaScript.
         * Ignore if UseAce is unchecked.
         * @param {bool} stateOn True to stop Ace, false to restart it.
         */
        function enableAceInCustomisedFields(stateOn) {
            var taIds = ['id_template', 'id_uiparameters'];
            var uiWrapper, ta;
            if (useace.prop('checked')) {
                for(var i = 0; i < taIds.length; i++) {
                    ta = $(document.getElementById(taIds[i]));
                    uiWrapper = ta.data('current-ui-wrapper');
                    if (uiWrapper && stateOn) {
                        uiWrapper.restart();
                    } else if (uiWrapper && !stateOn) {
                        uiWrapper.stop();
                    }
                }
            }
        }


        /**
         * After loading the form with new question type data we have to
         * enable or disable both the testsplitterre and the allow multiple
         * stdins field. These are subsequently enabled/disabled via event handlers
         * set up by code in edit_coderunner_form.php (q.v.) but those event
         * handlers do not handle the freshly downloaded state.
         */
        function enableTemplateSupportFields() {
            var isCombinatorEnabled = isCombinator.prop('checked');

            testSplitterRe.prop('disabled', !isCombinatorEnabled);
            allowMultipleStdins.prop('disabled', !isCombinatorEnabled);
        }

        /**
         * Copy fields from the AJAX "get question type" response into the form.
         * @param {string} newType the newly selected question type.
         * @param {object} response The AJAX resopnse.
         */
        function copyFieldsFromQuestionType(newType, response) {
            var formspecifier, attrval, filter;

            enableAceInCustomisedFields(false);
            for (var key in JSON_TO_FORM_MAP) {
                formspecifier = JSON_TO_FORM_MAP[key];
                attrval = response[key] ? response[key] : formspecifier[2];
                if (formspecifier.length > 3) {
                    filter = formspecifier[3];
                    attrval = filter(attrval);
                }
                $(formspecifier[0]).prop(formspecifier[1], attrval);
            }

            customise.prop('checked', false);
            str.get_string('coderunner_question_type', 'qtype_coderunner').then(function (s) {
                questiontypeHelpDiv.html(detailsHtml(newType, s, response.questiontext));
            });

            setCustomisationVisibility(false);
            enableTemplateSupportFields();
        }

        /**
         * A JSON request for a question type returned a 'failure' response - probably a
         * missing question type. Report the error with an alert, and replace
         * the template contents with an error message in case the user
         * saves the question and later wonders why it breaks.
         * Returns the JSON error object for further use.
         * @param {string} questionType The CodeRunner (sub) question type.
         * @param {string} error The error message as JSON encoded error => langstring,
         * extra => components string.
         * @return {JSON object} The JSON error object for further parsing.
         */
        function reportError(questionType, error) {
            const errorObject = JSON.parse(error);
            str.get_string('prototype_error', 'qtype_coderunner').then(function(s) {
                str.get_string(errorObject.alert, 'qtype_coderunner', questionType).then(function(str) {
                    langStringAlert('prototype_load_failure', str);
                    let errorMessage = s + "\n";
                    errorMessage += str + '\n';
                    errorMessage += "CourseId: " + courseId + ", qtype: " + questionType;
                    template.prop('value', errorMessage);
                });
            });
            return errorObject;
        }

        /**
         * Local function to return the HTML to display in the
         * question type details section of the form.
         * @param {string} title The type of the question being described.
         * @param {string} coderunner_descr The language string to introduce
         * the detail.
         * @param {html} html The HTML description of the question type, namely
         * the question text from its prototype.
         * @return {html} The composite HTML describing the question type.
         */
        function detailsHtml(title, coderunner_descr, html) {

            var resultHtml = '<p class="question-type-details-header">';
            resultHtml += coderunner_descr;
            resultHtml += title + '</p>\n' + html;
            return resultHtml;

        }

        /**
         * Raise an alert with the given language string and possible additional
         * extra text.
         * @param {string} key The language string to put in the Alert.
         * @param {string} extra Extra text to append.
         */
        function langStringAlert(key, extra) {
            if (window.hasOwnProperty('behattesting') && window.behattesting) {
                return;
            }
            str.get_string(key, 'qtype_coderunner').then(function(s) {
                var message = s.replace(/\n/g, " ");
                if (extra) {
                    message += '\n' + extra;
                }
                alert(message);
            });
        }

        /**
         * Get the "preferred language" from the AceLang string supplied.
         * @param {string} acelang The AceLang string.
         * For multilanguage questions, this is either the default (i.e.,
         * the language with a '*' suffix), or the first language. Otherwise
         * it is simply the entire AceLang string.
         * @return {string} The language to pass to Ace for syntax highlighting.
         */
        function preferredAceLang(acelang) {
            var langs, i;
            if (acelang.indexOf(',') < 0) {
                return acelang;
            } else {
                langs = acelang.split(',');
                for (i = 0; i < langs.length; i++) {
                    if (langs[i].endsWith('*')) {
                        return langs[i].substr(0, langs[i].length - 1);
                    }
                }
                return langs.length > 0 ? langs[0] : '';
            }
        }

        /**
         * Load the various customisation fields into the form from the
         * CodeRunner question type currently selected by the combobox.
         * Looks at the preexisting type of the selected field.
         */
        function loadCustomisationFields() {
            let newType = typeCombo.children('option:selected').text();

            if (newType !== '' && newType !== 'Undefined') {
                // Prevent 'Undefined' ever being reselected.
                typeCombo.children('option:first-child').prop('disabled', 'disabled');

                // Load question type with ajax.
                $.getJSON(M.cfg.wwwroot + '/question/type/coderunner/ajax.php',
                    {
                        qtype: newType,
                        courseid: courseId,
                        sesskey: M.cfg.sesskey
                    },
                    function (outcome) {
                        // Clean all warnings regardless.
                        $('#id_qtype_coderunner_warning_div').empty();
                        if (outcome.success) {
                            copyFieldsFromQuestionType(newType, outcome);
                            setUis();
                            loadUiParametersDescription();
                            // Success, so remove the errors and change the current Qtype.
                            currentQtype = newType;
                            $('#id_qtype_coderunner_error_div').empty();
                        }
                        else {
                            const errorObject = reportError(newType, outcome.error);
                            // Checks to see if there has been a change in type from last saved.
                            // If so, put up a load error and keep type unchanged.
                            if (currentQtype !== newType && errorObject.error === 'duplicateprototype') {
                                showLoadTypeError(currentQtype, errorObject, newType);
                                $("#id_coderunnertype").val(currentQtype);
                            }
                        }
                    }
                ).fail(function () {
                    // AJAX failed. We're dead, Fred. The attempt to get the
                    // language translation for the error message will likely
                    // fail too, so use English for a start.
                    langStringAlert('error_loading_prototype');
                    template.prop('value', '*** AJAX ERROR. DON\'T SAVE THIS! ***');
                    str.get_string('ajax_error', 'qtype_coderunner').then(function(s) {
                        template.prop('value', s);  // Translates into current language (if it works).
                    });
                });
            }
        }

        /**
         * Build an HTML table describing all the UI parameters.
         * @param {object} uiParamInfo The object describing the parameters
         * for a particular UI.
         * @return {string} An HTML table describing each UI parameter.
         */
        function UiParameterDescriptionTable(uiParamInfo) {
            var html = '<div class="uiparamtablediv"><table class="uiparamtable">\n',
                hdrs = uiParamInfo.columnheaders, param, i;
            html += "<tr><th>" + hdrs[0] + "</th><th>" + hdrs[1] + "</th><th>" + hdrs[2] + "</th></tr>\n";
            for (i = 0; i < uiParamInfo.uiparamstable.length; i++) {
                param = uiParamInfo.uiparamstable[i];
                html += "<tr><td>" + param[0] + "</td><td>" + param[1] + "</td><td>" + param[2] + "</td></tr>\n";
            }
            html += "</table></div>\n";
            return html;
        }



        /**
         * Plug the UI info received by getJSON into the author form.
         * @param {object} uiInfo The response data from the getJSON call
         * @returns {undefined}
         */
        function updateUiParamsDescription(uiInfo) {
            let currentuiparameters = uiparameters.val();
            let paramDescriptionDiv = $('.ui_parameters_descr');
            let hideUiParamsDescription = function() {
                uiparameters.val(''); // Remove stray white space.
                $('#fgroup_id_uiparametergroup').hide();
            };
            paramDescriptionDiv.empty();
            if (uiInfo === null || (uiInfo.uiparamstable.length == 0 && currentuiparameters.trim() === '')) {
                hideUiParamsDescription();
            } else {
                paramDescriptionDiv.append(uiInfo.header);
                let showhidebutton = $('<button type="button" class="toggleuidetails">' + uiInfo.showdetails + '</button>');
                if (uiInfo.uiparamstable.length != 0) {
                    paramDescriptionDiv.append(showhidebutton);
                    let table = $(UiParameterDescriptionTable(uiInfo));
                    paramDescriptionDiv.append(table);
                    table.hide();
                    showhidebutton.click(function () {
                        if (showhidebutton.html() == uiInfo.showdetails) {
                            table.show();
                            showhidebutton.html(uiInfo.hidedetails);
                        } else {
                            table.hide();
                            showhidebutton.html(uiInfo.showdetails);
                        }
                    });
                }
                $('#fgroup_id_uiparametergroup').show();
                if (useace.prop('checked')) {
                    setUi('id_uiparameters', 'ace');
                }
            }
        }

        /**
         * Load the UI parameter description field by Ajax initially or
         * when the UI plugin is changed.
         */
        function loadUiParametersDescription() {
            let newUi = uiplugin.children('option:selected').text();
            if (newUi === '' || newUi === 'none') {
                updateUiParamsDescription(null);
            } else {
                $.getJSON(M.cfg.wwwroot + '/question/type/coderunner/ajax.php',
                    {
                        uiplugin: newUi,
                        courseid: courseId,
                        sesskey: M.cfg.sesskey
                    },
                    updateUiParamsDescription
                ).fail(function () {
                    // AJAX failed.
                    langStringAlert('error_loading_ui_descr', `UI: ${newUi}`);
                });
            }
        }

        /**
         * Show/hide all testtype divs in the testcases according to the
         * 'Precheck' selector.
         */
        function set_testtype_visibilities() {
            if (precheck.val() === '3') { // Show only for case of 'Selected'.
                testtypedivs.show();
            } else {
                testtypedivs.hide();
            }
        }

        /**
         * Check that the Ace language is correctly set for the answer and
         * answer preload fields.
         */
        function check_ace_lang() {
            if (uiplugin.val() === 'ace'){
                setUis();
            }
        }

        /**
         * Check that the Ace language is correctly set for the template,
         * if template_uses_ace is checked.
         */
        function check_template_lang() {
            if (useace.prop('checked')) {
                setUi('id_template', 'ace');
            }
        }

        /**
         * If the brokenquestionmessage hidden element is not empty, insert the
         * given message as an error at the top of the question.
         * itself to go back to the last valid value.
         */
        function checkForBrokenQuestion() {
            let brokenQuestionMessage = brokenQuestion.prop('value'),
                messagePara = null;
            if (brokenQuestionMessage !== '') {
                messagePara = $('<p>' + brokenQuestion.prop('value') + '</p>');
                $('#id_qtype_coderunner_error_div').append(messagePara);
            }
        }

        /**
         * Shows the load type error of the selected type if the selected type is
         * faulty.
         * @param {string} currentType The current type with its errors.
         * @param {JSON Object} errorObject The JSON object containing a list of all the errors.
         * @param {string} newType The new type string which it failed to load.
         */
        function showLoadTypeError(currentType, errorObject, newType) {
            str.get_string('loadprototypeerror', 'qtype_coderunner',
                { oldtype : currentType, crtype : newType, outputstring : errorObject.extras })
                      .then(function(str) {
                $('#id_qtype_coderunner_warning_div').append($('<p>' + str + '</p>'));
            });
        }

        /*************************************************************
         *
         * Body of initEditFormWhenReady starts here.
         *
         *************************************************************/

        if (prototypeType.prop('value') != 0) {
            // Display the prototype warning if it's a prototype and hide testboxes.
            testsection.css('display', 'none');
            prototypeDisplay.removeAttr('hidden');
            if (prototypeType.prop('value') == 1) {
                // Editing a built-in question type: Dangerous!
                str.get_string('proceed_at_own_risk', 'qtype_coderunner').then(function(s) {
                    alert(s);
                });
                prototypeType.prop('disabled', true);
                customise.prop('disabled', true);
            }
        }

        checkForBrokenQuestion();
        badQuestionLoad.prop('hidden'); // Until we check it once.
        // Keep track of the current prototype loaded.
        currentQtype = typeCombo.children('option:selected').text();

        setCustomisationVisibility(isCustomised);
        if (!isCustomised) {
            // Not customised so have to load fields from prototype.
            loadCustomisationFields();  // setUis is called when this completes.
        } else {
            setUis();  // Set up UI controllers on answer and answerpreload.
            str.get_string('info_unavailable', 'qtype_coderunner').then(function(s) {
                questiontypeHelpDiv.html("<p>" + s + "</p>");
            });
        }

        set_testtype_visibilities();

        if (useace.prop('checked')) {
            setUi('id_templateparams', 'ace');
            setUi('id_uiparameters', 'ace');
        }

        loadUiParametersDescription();

        // Set up event Handlers.

        customise.on('change', function() {
            let isCustomised = customise.prop('checked');
            if (isCustomised) {
                // Customisation is being turned on.
                setCustomisationVisibility(true);
            } else { // Customisation being turned off.
                str.get_string('confirm_proceed', 'qtype_coderunner').then(function(s) {
                    if (window.confirm(s)) {
                        setCustomisationVisibility(false);
                    } else {
                        customise.prop('checked', true);
                    }
                });
            }
        });

        acelang.on('change', check_ace_lang);
        language.on('change', function() {
            check_template_lang();
            check_ace_lang();
        });

        typeCombo.on('change', function() {
            if (customise.prop('checked')) {
                // Author has customised the question. Ask if they want to reload inherited stuff.
                str.get_string('question_type_changed', 'qtype_coderunner').then(function (s) {
                    if (window.confirm(s)) {
                        loadCustomisationFields();
                    }
                });
            } else {
                loadCustomisationFields();
            }
        });

        useace.on('change', function() {
            var isTurningOn = useace.prop('checked');
            if (isTurningOn) {
                setUi('id_template', 'ace');
                setUi('id_templateparams', 'ace');
                setUi('id_uiparameters', 'ace');
            } else {
                setUi('id_template', '');
                setUi('id_templateparams', '');
                setUi('id_uiparameters', '');
            }
        });

        evaluatePerStudent.on('change', function() {
            if (evaluatePerStudent.is(':checked')) {
                langStringAlert('templateparamsusingsandbox');
            }
        });

        uiplugin.on('change', function () {
            setUis();
            loadUiParametersDescription();
        });

        precheck.on('change', set_testtype_visibilities);

        // Displays and hides the reason for the question type to be disabled.
        // Also hides/shows the test cases section if prototype/not prototype.
        prototypeType.on('change', function () {
            if (prototypeType.prop('value') == '0') {
                testsection.css('display', 'block');
                prototypeDisplay.attr('hidden', '1');
            } else {
                testsection.css('display', 'none');
                prototypeDisplay.removeAttr('hidden');
            }
        });

        // In order to initialise the Ui plugin when the answer preload section is
        // expanded, we monitor attribute mutations in the Answer Preload
        // header.
        var observer = new MutationObserver( function () {
            setUis();
        });
        observer.observe(preloadHdr.get(0), {'attributes': true});

        // Setup click handler for the buttons that allow users to replace the
        // expected output  with the output got from testing the answer program.
        $('button.replaceexpectedwithgot').click(function() {
            var gotPre = $(this).prev('pre[id^="id_got_"]');
            var testCaseId = gotPre.attr('id').replace('id_got_', '');
            $('#id_expected_' + testCaseId).val(gotPre.text());
            $('#id_fail_expected_' + testCaseId).html(gotPre.text());
            $('.failrow_' + testCaseId).addClass('fixed');  // Fixed row.
            $(this).prop('disabled', true);
        });

        // On reloading the page, enable the typeCombo so that its value is POSTed.
        $('.btn-primary').click(function() {
            typeCombo.prop('disabled', false);
        });
    }

    return {initEditForm: initEditForm};
});