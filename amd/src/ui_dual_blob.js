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
 * @copyright  James Napier, 2022, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
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

        this.readOnly = this.textArea.prop('readonly');
        this.uiParams = uiParams;
        this.fail = false;

        this.blobDiv = null;
        this.reload(); // Draw my beautiful blobs.
    }

    DualBlobUi.prototype.failed = function() {
        return this.fail;
    };


    DualBlobUi.prototype.failMessage = function() {
        return 'DualBlobUiloadfail';
    };


    // Copy the serialised version of the HTML UI area to the TextArea.
    DualBlobUi.prototype.sync = function() {
        let serialization = [];
        $(this.blobDiv).find('textarea').each(function() {
            serialization.push($(this).val());
        });
        this.textArea.val(JSON.stringify(serialization));
    };


    DualBlobUi.prototype.getElement = function() {
        return this.blobDiv;
    };


    DualBlobUi.prototype.reload = function() {
        let
            html = "<div style='height:fit-content'>",
            preloadString = $(this.textArea).val(),
            preload = ["", ""];
        if (preloadString) {
            preload = JSON.parse(preloadString);
        }
        preload.forEach( (item) => {
            html += "<textarea>" + item + "</textarea>";
        });
        html += "</div>";
        this.blobDiv = $(html);
    };

    DualBlobUi.prototype.resize = function() {}; // Nothing to see here. Move along please.

    DualBlobUi.prototype.hasFocus = function() {
        // TODO.
        return true;
    };

    // Destroy the HTML UI and serialise the result into the original text area.
    DualBlobUi.prototype.destroy = function() {
        this.sync();
        $(this.blobDiv).remove();
        this.blobDiv = null;
    };

    return {
        Constructor: DualBlobUi
    };
});
