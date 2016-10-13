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
 * JavaScript for handling UI actions in the question authoring form.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    // Define a mapping from the fields of the JSON object returned by an AJAX
    // 'get question type' request to the form elements. Keys are JSON field
    // names, values are a 3- or 4-element array of: a jQuery form element selector;
    // the element property to be set; a default value if the JSON field is
    // empty and an optional filter function to apply to the field value before
    // setting the property with it.
    var JSON_TO_FORM_MAP = {
        pertesttemplate:    ['#id_pertesttemplate', 'value', ''],
        combinatortemplate: ['#id_combinatortemplate', 'value', ''],
        cputimelimitsecs:   ['#id_cputimelimitsecs', 'value', ''],
        memlimitmb:         ['#id_memlimitmb', 'value', ''],
        sandbox:            ['#id_sandbox', 'value', 'DEFAULT'],
        sandboxparams:      ['#id_sandboxparams', 'value', ''],
        enablecombinator:   ['#id_enablecombinator', 'checked', true,
                                function (enablecombinator) {
                                    return enablecombinator == '1';
                                }],
        testsplitterre:     ['#id_testsplitterre', 'value', '',
                                function (splitter) {
                                    return splitter.replace('\n', '\\n');
                                }],
        language:           ['#id_language', 'value', '']
    };

    // Postpone initialisation until document ready, because strings_for_js
    // strings aren't defined at the time this is called.
    function initEditForm() {
        $().ready(initEditFormWhenReady);
    }

    function initEditFormWhenReady() {
        var typeCombo = $('#id_coderunnertype'),
            pertesttemplate = $('#id_pertesttemplate'),
            enablecombinator = $('#id_enablecombinator'),
            useace = $('#id_useace'),
            combinatortemplate = $('#id_combinatortemplate'),
            language = $('#id_language'),
            customise = $('#id_customise'),
            customisationFieldSet = $('#id_customisationheader'),
            advancedCustomisation = $('#id_advancedcustomisationheader'),
            isCustomised = customise.prop('checked'),
            prototypeType = $("#id_prototypetype"),
            typeName = $('#id_typename'),
            courseId = $('input[name="courseid"]').prop('value'),
            questiontypeHelpDiv = $('#qtype-help'),
            alertIssued = false;

        // Check if need to (re-)initialise Ace in a textarea. Do this if
        // the textarea or its Ace wrapper is visible and Ace is enabled.
        // If Ace is already enabled, a call to initAce will reload its contents.
        function checkAceStatus(template) {
            var lang = language.prop('value').toLowerCase();
            var templateVisible = $('#id_' + template).is(':visible') ||
                    $('#id_' + template + '_wrapper').is(':visible');
            if (useace.prop('checked') && templateVisible) {
                require(['qtype_coderunner/aceinterface'], function(AceInterface) {
                    AceInterface.initAce('id_' + template, lang);
                });
            }
        }

        function setCustomisationVisibility(isVisible) {
            var display = isVisible ? 'block' : 'none';
            customisationFieldSet.css('display', display);
            advancedCustomisation.css('display', display);
            if (isVisible) {
                checkAceStatus('pertesttemplate');
                checkAceStatus('combinatortemplate');
            }
        }

        // Copy fields from the AJAX "get question type" response into the form.
        function copyFieldsFromQuestionType(newType, response) {
            var formspecifier, attrval, filter;
            for (var key in JSON_TO_FORM_MAP) {
                formspecifier = JSON_TO_FORM_MAP[key];
                attrval = response[key] ? response[key] : formspecifier[2];
                if (formspecifier.length > 3) {
                    filter = formspecifier[3];
                    attrval = filter(attrval);
                }
                $(formspecifier[0]).prop(formspecifier[1], attrval);
            }

            typeName.prop('value', newType);
            customise.prop('checked', false);
            questiontypeHelpDiv.html(detailsHtml(newType, response.questiontext));
            setCustomisationVisibility(false);
        }

        // A JSON request for a question type returned a 'failure' response - probably a
        // missing question type. Report the error with an alert, and replace
        // the pertesttemplate contents with an error message in case the user
        // saves the question and later wonders why it breaks.
        function reportError(questionType, error) {
            var errorMessage;
            window.alert(getString('prototype_load_failure') + error);
            errorMessage = getString('prototype_error') + "\n";
            errorMessage += error + '\n';
            errorMessage += "CourseId: " + courseId + ", qtype: " + questionType;
            pertesttemplate.prop('value', errorMessage);
        }

        function detailsHtml(title, html) {
            // Local function to return the HTML to display in the
            // question type details section of the form.
            var resultHtml = '<p class="question-type-details-header">';
            resultHtml += getString('coderunner_question_type');
            resultHtml += title + '</p>\n' + html;
            return resultHtml;

        }

        // Compact call to M.util.get_string.
        function getString(key) {
            return M.util.get_string(key, 'qtype_coderunner');
        }

        // Load the various customisation fields into the form from the
        // CodeRunner question type currently selected by the combobox.
        function loadCustomisationFields() {
            var newType = typeCombo.children('option:selected').text();

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
                        if (outcome.success) {
                            copyFieldsFromQuestionType(newType, outcome);
                        }
                        else {
                            reportError(newType, outcome.error);
                        }

                    }
                ).error(function () {
                    // AJAX failed. We're dead, Fred.
                    window.alert(getString('error_loading_prototype'));
                    pertesttemplate.prop('value', getString('ajax_error'));
                });
            }
        }

        // Body of initEditFormWhenReady starts here.

        if (prototypeType.prop('value') == 1) {
            // Editing a built-in question type: Dangerous!
            window.alert(getString('proceed_at_own_risk'));
            prototypeType.prop('disabled', true);
            typeCombo.prop('disabled', true);
            customise.prop('disabled', true);
        }

        setCustomisationVisibility(isCustomised);
        if (!isCustomised) {
            loadCustomisationFields();
        } else {
            questiontypeHelpDiv.setHTML("<p>" + getString('info_unavailable') + "</p>");
        }

        // Set up event Handlers.

        customise.on('change', function() {
            var isCustomised = customise.prop('checked');
            if (isCustomised) {
                // Customisation is being turned on.
                setCustomisationVisibility(true);
            } else { // Customisation being turned off.
                if (window.confirm(getString('confirm_proceed'))) {
                    setCustomisationVisibility(false);
                } else {
                    customise.prop('checked', true);
                }
            }
        });

        pertesttemplate.on('change', function() {
            // Per-test pertesttemplate has been changed. Check if combinator should
            // be disabled.
            var combinatornonblank = combinatortemplate.prop('value').trim() !== '';
            if (combinatornonblank &&
                 !alertIssued &&
                  enablecombinator.prop('checked') &&
                  window.confirm(getString('template_changed'))
               ) {
                enablecombinator.prop('checked', false);
            }
            alertIssued = true;
        });

        typeCombo.on('change', function() {
            if (!customise.prop('checked') ||
                    window.confirm(getString('question_type_changed'))) {
                loadCustomisationFields();
            }
        });

        useace.on('change', function() {
            var isTurningOn = useace.prop('checked');
            if (isTurningOn) {
                checkAceStatus('pertesttemplate');
                checkAceStatus('combinatortemplate');
            } else {
                require(['qtype_coderunner/aceinterface'], function(AceInterface) {
                    AceInterface.stopUsingAce();
                });
            }
        });

        // In order to initialise Ace when the combinator template becomes
        // visible, we monitor attribute mutations in the Advanced Customisation
        // header.
        var observer = new MutationObserver( function () {
            checkAceStatus('combinatortemplate');
        });

        observer.observe(advancedCustomisation.get(0), {'attributes': true});
    }

    return {initEditForm: initEditForm};
});