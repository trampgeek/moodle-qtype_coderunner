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
 * TODO: is there a race problem here??
 */

define(['jquery'], function($) {
    /**
     * Initialise the language selector dropdown when the document is ready.
     * @param {string} taId The ID of the student answer box text area.
     */
    function initLangSelector(taId) {
        $().ready(function() {
            initLangSelectorWhenReady(taId);
        });
    }

    /**
     * Initialise the language selector. Called by initLanSelector when the
     * document is ready so the name is a bit of a misnomer.
     * @param {string} taId The ID of the student answer box text area.
     */
    function initLangSelectorWhenReady(taId) {
        var ta = $(document.getElementById(taId)),  // The jquery text area element(s).
            selector = $(".coderunner-lang-select");

        /**
         * Set the language for the Ace editor.
         */
        function setAceLang() {
            // Set the language for the Ace plugin (or any other plugin if it
            // has a setLanguage method).
            var lang = selector.val(),
                uiWrapper = ta.data('current-ui-wrapper'); // Currently-active UI wrapper on reqd ta.

            if (uiWrapper && uiWrapper.uiInstance && typeof uiWrapper.uiInstance.setLanguage === 'function') {
                // TODO: define setLanguage as a required method of all UI plugins.
                uiWrapper.uiInstance.setLanguage(lang);
            }
        }

        selector.on('change', setAceLang);
    }

    return {'initLangSelector' : initLangSelector};
});