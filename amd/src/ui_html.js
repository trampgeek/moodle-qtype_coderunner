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
 * document order, of the results of reading the native value property (or,
 * for checkboxes and radio buttons, the empty string when unchecked) of
 * each of the UI elements with that name. This means that at least input,
 * select and textarea elements are supported. The author is responsible
 * for checking the compatibility of other elements with a native .value
 * property.
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
 * This outer div can be located as the closest ancestor matching
 * div.qtype-coderunner-html-outer-div (e.g. via Element.closest()). The
 * author-supplied HTML must include JavaScript to make use of the 'leftovers'.
 *
 * As a special case of the serialisation, if all values in the serialisation
 * are either empty strings or a list of empty strings, the serialisation is
 * itself the empty string.
 *
 * Radio buttons are a special case. The browser enforces "only one checked
 * radio per name" across the whole document (or nearest enclosing form),
 * not per UI instance. Since more than one html_ui instance built from the
 * same author HTML can appear on a single page at once (e.g. the live
 * answer box and a revealed sample-answer box), unprefixed radio names
 * would let one instance silently steal the checked state from another.
 * To avoid this, radio button 'name' attributes are transparently prefixed
 * with the textarea id (plus '___') in the live DOM only; the JSON
 * serialisation written to/read from the textarea always uses the
 * author's original, unprefixed names, so existing stored answers and
 * question HTML are unaffected. Checkboxes, text inputs, textareas and
 * selects are left with their original names since they have no
 * equivalent cross-instance browser behaviour to guard against, and
 * embedded scripts may reasonably select them by name.
 *
 * @module coderunner/ui_html
 * @copyright  Richard Lobb, 2018, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    /**
     * Constructor for the HtmlUi object.
     * @param {string} textareaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea (unused).
     * @param {int} height The height in pixels of the textarea (unused).
     * @param {object} uiParams The UI parameter object.
     */
    function HtmlUi(textareaId, width, height, uiParams) {
        this.textareaId = textareaId;
        this.textArea = document.getElementById(textareaId);
        const srcField = uiParams.html_src || 'globalextra';
        this.html = this.textArea.getAttribute('data-' + srcField);
        this.html = this.html.replace(/___textareaId___/gm, textareaId);
        this.readOnly = this.textArea.readOnly;
        this.uiParams = uiParams;
        this.fail = false;
        this.htmlDiv = null;
        this.namePrefix = textareaId + '___';
        this.reload();
    }

    HtmlUi.prototype.failed = function() {
        return this.fail;
    };


    HtmlUi.prototype.failMessage = function() {
        return 'htmluiloadfail';
    };


    /**
     * The logical (author-facing) name of a field, undoing the internal
     * per-instance prefix transparently added to radio button names by
     * reload(). See the module docstring for why this is needed.
     * @param {Element} field An element with class coderunner-ui-element.
     * @returns {string} The field's name as it appears in the serialised JSON.
     */
    HtmlUi.prototype.fieldName = function(field) {
        const name = field.name;
        if (field.type === 'radio' && name && name.indexOf(this.namePrefix) === 0) {
            return name.slice(this.namePrefix.length);
        }
        return name;
    };


    // Copy the serialised version of the HTML UI area to the TextArea.
    HtmlUi.prototype.sync = function() {
        const serialisation = {};
        let empty = true;

        this.getFields().forEach(field => {
            const name = this.fieldName(field);
            let value;
            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                value = '';
            } else {
                value = field.value;
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
            this.textArea.value = '';
        } else {
            this.textArea.value = JSON.stringify(serialisation);
        }
    };


    HtmlUi.prototype.getElement = function() {
        return this.htmlDiv;
    };

    HtmlUi.prototype.getFields = function() {
        return Array.from(this.htmlDiv.querySelectorAll('.coderunner-ui-element'));
    };

    // Set the value of the given field to the given value.
    // If the field is a radio button or a checkbox and its value matches
    // the given value, the checked attribute is set. Otherwise the field's
    // value property is set directly.
    HtmlUi.prototype.setField = function(field, value) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            field.checked = field.value === value;
        } else {
            field.value = value;
        }
    };

    HtmlUi.prototype.reload = function() {
        const content = this.textArea.value; // JSON-encoded HTML element settings.
        const outerDivId = 'qtype-coderunner-outer-div-' + this.textareaId;

        this.htmlDiv = document.createElement('div');
        this.htmlDiv.id = outerDivId;
        this.htmlDiv.className = 'qtype-coderunner-html-outer-div';
        this.htmlDiv.style.height = 'fit-content';
        this.htmlDiv.innerHTML = this.html;

        // For use by scripts embedded in html, e.g. via
        // this.closest('div.qtype-coderunner-html-outer-div').dataset.uiparams
        // or, using jQuery if available, .data('uiparams').
        this.htmlDiv.setAttribute('data-uiparams', JSON.stringify(this.uiParams));
        this.htmlDiv.setAttribute('data-templateparams', JSON.stringify(this.uiParams)); // Legacy support only. DEPRECATED.

        // Prefix radio button names so that this instance's radio groups can
        // never collide with those of another html_ui instance on the same
        // page. See the module docstring for the rationale.
        this.getFields().forEach(field => {
            if (field.type === 'radio' && field.name) {
                field.name = this.namePrefix + field.name;
            }
        });

        if (content) {
            try {
                const valuesToLoad = JSON.parse(content);
                const leftOvers = {};
                for (const name in valuesToLoad) {
                    const values = valuesToLoad[name];
                    const fields = this.getFields().filter(field => this.fieldName(field) === name);
                    leftOvers[name] = [];
                    for (let i = 0; i < values.length; i++) {
                        if (i < fields.length) {
                            this.setField(fields[i], values[i]);
                        } else {
                            leftOvers[name].push(values[i]);
                        }
                    }
                    if (leftOvers[name].length === 0) {
                        delete leftOvers[name];
                    }
                }

                if (Object.keys(leftOvers).length > 0) {
                    this.htmlDiv.setAttribute('data-leftovers', JSON.stringify(leftOvers));
                }

            } catch(e) {
                this.fail = true;
            }
        }
    };

    HtmlUi.prototype.resize = function() {}; // Nothing to see here. Move along please.

    HtmlUi.prototype.hasFocus = function() {
        return this.getFields().some(field => field === document.activeElement);
    };

    // Destroy the HTML UI and serialise the result into the original text area.
    HtmlUi.prototype.destroy = function() {
        this.sync();
        if (this.htmlDiv) {
            this.htmlDiv.remove();
        }
        this.htmlDiv = null;
    };

    return {
        Constructor: HtmlUi
    };
});
