/******************************************************************************
 *
 * This module simply handles changes in the Language selection dropdown for
 * multilanguage questions as seen by students. It switches the Ace language
 * accordingly.
 * It should only be loaded in conjunction with the ui_ace module.
 *
 * @module qtype_coderunner/multilanguagequestion
 * @copyright  Richard Lobb, 2018, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

define(['jquery', 'core/str'], function($, str) {

    let NO_LANGUAGE_MESSAGE = ''; // The 'no language chosen' message, to be set by AJAX.

    /**
     * Initialise the language selector dropdown when the document is ready.
     * @param {string} taId The ID of the student answer box text area.
     */
    function initLangSelector(taId) {
        $().ready(function() {
            initLangSelectorWhenReady(taId);
        });
        const promise = str.get_string('nolanguage', 'qtype_coderunner');
        $.when(promise).done(function(message) {
            NO_LANGUAGE_MESSAGE = message; // Get the 'no language chosen' message.
        });
    }

    /**
     * Initialise the language selector. Called by initLangSelector when the
     * document is ready, so the name is a bit of a misnomer.
     * @param {string} taId The ID of the student answer box text area.
     */
    function initLangSelectorWhenReady(taId) {
        const ta = $(document.getElementById(taId));  // The jquery text area element(s).
        const langSelectorId = taId.replace('answer', 'language');
        const selector = document.getElementById(langSelectorId);
        const uiWrapperId = taId + '_wrapper';

        /**
         * Check if an element has an ancestor with a given ID
         * @param {DOM element} element The element being tested
         * @param {string} id the ancestor id to search for
         * @returns {Boolean} True iff the specified ancestor exists
         */
        function hasAncestorWithId(element, id) {
            while (element) {
                if (element.id === id) {
                    return true;
                }
                element = element.parentElement;
            }
            return false;
        }

        /**
         * Set the language for the Ace editor.
         */
        function setAceLang() {
            // Set the language for the Ace plugin (or any other plugin if it
            // has a setLanguage method).
            const lang = selector.value;
            const uiWrapper = ta.data('current-ui-wrapper'); // Currently-active UI wrapper on reqd ta.

            if (uiWrapper && uiWrapper.uiInstance && typeof uiWrapper.uiInstance.setLanguage === 'function') {
                uiWrapper.uiInstance.setLanguage(lang);
            }
        }

        selector.onchange = setAceLang;

        // Prevent processing of keydown events in the UI until the dropdown
        // has been set.
        document.body.onkeydown = function(event) {
            if (event.target && hasAncestorWithId(event.target, uiWrapperId)) {
                // Here only if keypress is within the UI wrapper for this question.
                if (selector.value === '' && NO_LANGUAGE_MESSAGE) {
                    alert(NO_LANGUAGE_MESSAGE);
                    event.preventDefault();
                }
            }
        };

    }

    return {'initLangSelector' : initLangSelector};
});