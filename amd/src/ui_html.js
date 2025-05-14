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
 * @copyright  Richard Lobb, 2018, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    /**
     * Constructor for the HtmlUi object.
     * @param {string} textareaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea.
     * @param {int} height The height in pixels of the textarea.
     * @param {object} uiParams The UI parameter object.
     */
    function HtmlUi(textareaId, width, height, uiParams) {
        this.textArea = $(document.getElementById(textareaId));
        this.textareaId = textareaId;
        var srcField = uiParams.html_src || 'globalextra';
        this.html = this.textArea.attr('data-' + srcField);
        this.html = this.html.replace(/___textareaId___/gm, textareaId);
        this.readOnly = this.textArea.prop('readonly');
        this.uiParams = uiParams;
        this.fail = false;
        this.htmlDiv = null;
        this.reload();
    }

    HtmlUi.prototype.failed = function() {
        return this.fail;
    };


    HtmlUi.prototype.failMessage = function() {
        return 'htmluiloadfail';
    };


    // Copy the serialised version of the HTML UI area to the TextArea.
    HtmlUi.prototype.sync = function() {
        var
            serialisation = {},
            name,
            empty = true;

        this.getFields().each(function() {
            var value, type;
            type = $(this).attr('type');
            name = $(this).attr('name');
            if ((type === 'checkbox' || type === 'radio') && !($(this).is(':checked'))) {
                value = '';
            } else {
                value = $(this).val();
            }
            if (serialisation.hasOwnProperty(name)) {
                serialisation[name].push(value);
            } else {
                serialisation[name] = [value];
            }
            if (value !== '') {
                empty = false;
            }
        });
        if (empty) {
            this.textArea.val('');
        } else {
            this.textArea.val(JSON.stringify(serialisation));
        }
    };


    HtmlUi.prototype.getElement = function() {
        return this.htmlDiv;
    };

    HtmlUi.prototype.getFields = function() {
        return $(this.htmlDiv).find('.coderunner-ui-element');
    };

    // Set the value of the jQuery field to the given value.
    // If the field is a radio button or a checkbox and its name matches
    // the given value, the checked attribute is set. Otherwise the field's
    // val() function is called to set the value.
    HtmlUi.prototype.setField = function(field, value) {
        if (field.attr('type') === 'checkbox' || field.attr('type') === 'radio') {
            field.prop('checked', field.val() === value);
        } else {
            field.val(value);
        }
    };

    HtmlUi.prototype.reload = function() {
        var
            content = $(this.textArea).val(), // JSON-encoded HTML element settings.
            valuesToLoad,
            values,
            i,
            fields,
            leftOvers,
            outerDivId = 'qtype-coderunner-outer-div-' + this.textareaId,
            outerDiv = "<div style='height:fit-content' class='qtype-coderunner-html-outer-div' id='" + outerDivId + "'>";

        this.htmlDiv = $(outerDiv + this.html + "</div>");
        this.htmlDiv.data('uiparams', this.uiParams);       // For use by  scripts embedded in html.
        this.htmlDiv.data('templateparams', this.uiParams); // Legacy support only. DEPRECATED.
        if (content) {
            try {
                valuesToLoad = JSON.parse(content);
                leftOvers = {};
                for (var name in valuesToLoad) {
                    values = valuesToLoad[name];
                    fields = this.getFields().filter("[name='" + name + "']");
                    leftOvers[name] = [];
                    for (i = 0; i < values.length; i++) {
                        if (i < fields.length) {
                            this.setField($(fields[i]), values[i]);
                        } else {
                            leftOvers[name].push(values[i]);
                        }
                    }
                    if (leftOvers[name].length === 0) {
                        delete leftOvers[name];
                    }
                }

                if (!$.isEmptyObject(leftOvers)) {
                    this.htmlDiv.attr('data-leftovers', JSON.stringify(leftOvers));
                }

            } catch(e) {
                this.fail = true;
            }
        }
    };

    HtmlUi.prototype.resize = function() {}; // Nothing to see here. Move along please.

    HtmlUi.prototype.hasFocus = function() {
        var  focused = false;
        this.getFields().each(function() {
            if (this === document.activeElement) {
                focused = true;
            }
        });
        return focused;
    };

    // Destroy the HTML UI and serialise the result into the original text area.
    HtmlUi.prototype.destroy = function() {
        this.sync();
        $(this.htmlDiv).remove();
        this.htmlDiv = null;
    };

    return {
        Constructor: HtmlUi
    };
});
