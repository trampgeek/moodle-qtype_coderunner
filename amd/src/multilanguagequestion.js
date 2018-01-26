/******************************************************************************
 *
 * This module simply handles changes in the Language selection dropdown for
 * multilanguage questions as seen by students. It switches the Ace language
 * accordingly.
 * It should only be loaded in conjunction with the ui_ace module.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2018, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * TODO: is there a race prolemn here??
 */

define(['jquery'], function($) {

    function initLangSelector(taId) {
        $().ready(function() {
            initLangSelectorWhenReady(taId);
        });
    }

    function initLangSelectorWhenReady(taId) {
        var ta = $(document.getElementById(taId)),  // The jquery text area element(s)
            selector = $(".coderunner-lang-select"),
            lang;

        selector.on('change', function() {
            var uiWrapper = ta.data('current-ui-wrapper'); // Currently-active UI wrapper on this ta

            if (uiWrapper) {
                lang = selector.val();
                uiWrapper.loadUi("ace", {'lang': lang});
            }
        });
    }

    return {'initLangSelector' : initLangSelector};
});