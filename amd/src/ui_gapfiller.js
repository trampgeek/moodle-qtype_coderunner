/**
 * This file is part of Moodle - http:moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more util.details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http:www.gnu.org/licenses/>.
 */

/**
 * Implementation of the gapfiller_ui user interface plugin. For overall details
 * of the UI plugin architecture, see userinterfacewrapper.js.
 *
 * This plugin replaces the usual textarea answer box with a div
 * consisting of pre-formatted text supplied by the question author in either the
 * "globalextra" field or the testcode field of the first test case, according
 * to the ui parameter ui_source (default: globalextra).  HTML
 * entry or textarea elements are then inserted at
 * specified points. It is intended primarily for use with coding questions
 * where the answerbox presents the students with code that has smallish bits
 * missing.
 *
 * The locations within the globalextra text at which the input elements are
 * to be inserted are denoted by "tags" of the form
 *
 *     {[ size ]}
 *
 * for an HTML input element
 *
 * or
 *
 *     {[ rows, columns ]}
 *
 * for a textarea element
 *
 * where size, rows and column are integer literals. These respectively
 * inject an HTML input element or a textarea element of the
 * specified size.
 *
 * The serialisation of the answer box contents, i.e. the text that
 * copied back into the textarea for submissions
 * as the answer, is simply a list of all the field values (strings), in order.
 *
 * As a special case of the serialisation, if the value list is empty, the
 * serialisation itself is the empty string.
 *
 * The delimiters for the input element insertion tags are by default '{[' and
 * ']}', but can be changed by an optional ui parameter gap_filler_delimiters,
 * which must be a 2-element array of strings. For example:
 *
 *     {"gap_filler_delimiters": ["{{", "}}"]}
 *
 * Note that the double-brace delimiters in that example are the same as those
 * used by Twig, so using them instead of the default would prevent you from
 * ever adding Twig expansion (e.g. for randomisation) to the question.
 *
 * @module qtype_coderunner/ui_gapfiller
 * @copyright  Richard Lobb, 2019, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    /**
     * Constructor for UI. Source html comes from data-globalextra by default,
     * else from whatever source is specified by the uiParams parameter.
     * @param {string} textareaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea.
     * @param {int} height The height in pixels of the textarea.
     * @param {object} uiParams The UI parameter object.
     */
    function GapfillerUi(textareaId, width, height, uiParams) {
        var html;
        this.textArea = $(document.getElementById(textareaId));
        this.readOnly = this.textArea.prop('readonly');
        this.uiParams = uiParams;
        this.fail = false;
        this.htmlDiv = null;
        this.source = uiParams.ui_source || 'globalextra';
        if (this.source !== 'globalextra' && this.source !== 'test0') {
            alert('Invalid source for HTML in ui_gapfiller');
            this.source = 'globalextra';
        }
        if (this.source == 'globalextra') {
            html = this.textArea.attr('data-globalextra');
        } else {
            html = this.textArea.attr('data-test0');
        }
        this.html = html.replace('<', '&lt;');
        this.reload();
    }

    GapfillerUi.prototype.failed = function() {
        return this.fail; // Currently always true. See reload function.
    };

    /**
     * Copy the serialised version of the HTML UI area to the TextArea.
     */
    GapfillerUi.prototype.sync = function() {
        var
            serialisation = [],  // A list of field values.
            empty = true;

        this.getFields().each(function() {
            var name, value;
            name = $(this).attr('name');
            if (name !== 'cr_gapfiller_field') {
                alert('Unexpected UI element found in answer box');
            } else {
                value = $(this).val();
                serialisation.push(value);
                if (value !== "") {
                    empty = false;
                }
            }
        });
        if (empty) {
            this.textArea.val('');
        } else {
            this.textArea.val(JSON.stringify(serialisation));
        }
    };

    GapfillerUi.prototype.getElement = function() {
        return this.htmlDiv;
    };

    GapfillerUi.prototype.getFields = function() {
        return $(this.htmlDiv).find('.coderunner-ui-element');
    };

    /**
     * Set the value of the jQuery field to the given value.
     * If the field is a radio button or a checkbox,
     * the checked attribute is set. Otherwise the field's
     * val() function is called to set the value.
     * @param {object} field The JQuery field elemetn whose value is to be set.
     * @param {string} value The value to be used.
     */
    GapfillerUi.prototype.setField = function(field, value) {
        if (field.attr('type') === 'checkbox' || field.attr('type') === 'radio') {
            field.prop('checked', field.val() === value);
        } else {
            field.val(value);
        }
    };

    /**
     * Process the supplied HTML, HTML-escaping existing HTML
     * and inserting the input and textarea elements
     * at the marked locations.
     */
    GapfillerUi.prototype.markedUpHtml = function() {

        /**
         * Prefix any regular expression special chars in s with a backslash.
         * @param {string} s The string whose special values are to be escaped.
         * @return {string} The escaped string.
         */
        function reEscape(s) {
            var c, specials = '{[(*+\\', result='';
            for (var i = 0; i < s.length; i++) {
                c = s[i];
                for (var j = 0; j < specials.length; j++) {
                    if (c === specials[j]) {
                        c = '\\' + c;
                    }
                }
                result += c;
            }
            return result;
        }

        var sepLeft = reEscape('{['),
            sepRight = reEscape(']}'),
            splitter = new RegExp(sepLeft + ' *((?:\\d+)|(?:\\d+, *\\d+)) *' + sepRight),
            bits = this.html.split(splitter),
            result = '<pre>' + bits[0],
            i;

        for (i = 1; i < bits.length; i += 2) {
            result += this.markUp(bits[i]);
            if (i + 1 < bits.length) {
                result += bits[i + 1];
            }
        }

        result = result + '</pre>';
        return result;
    };


    /**
     * Return the HTML element to insert given the tag contents, which
     * should be either a single integer (size of input element) or
     * two integers separated by a comma (rows and cols of textarea).
     * @param {string} tagContents The text between the delimiters of a gap specifier.
     * @return {string} The HTML for an input or textarea element build
     * according to the given tagContents.
     */
    GapfillerUi.prototype.markUp = function(tagContents) {
        var numbers, result='';

        /**
         * The function to handle an 'input' tag.
         * @param {int} size The size of the input element to return.
         * @return {string} The html for a text area to the given specs.
         */
        function input(size) {
            return '<input name="cr_gapfiller_field" class="coderunner-ui-element" size="' + size + '">';
        }

        /**
         * The function to handle a 'textarea' tag.
         * @param {int} rows The number of rows of text required.
         * @param {int} cols The number of columns of text required.
         * @return The HTML for a textarea to the given specs.
         */
        function textarea(rows, cols) {
            return '<textarea name="cr_gapfiller_field" class ="coderunner-ui-element" ' +
                'rows="' + rows + '" ' + 'cols="' + cols + '" style="width:auto;"></textarea>';
        }

        numbers = tagContents.split(',');
        if (numbers.length == 1) {
            result = input(parseInt(numbers[0]));
        } else {
            result = textarea(parseInt(numbers[0]), parseInt(numbers[1]));
        }

        return result;
    };

    /**
     * Reload the HTML fields from the given serialisation.
     * Unlike other plugins, we don't actually fail the load if, for example
     * the number of fields doesn't match the number of values in the
     * serialisation. We simply set any excess fields for which data
     * in unavailable to '???' or discard extra values. This ensures
     * that at least the unfilled content is presented to the question author
     * when the number of fields is altered during editing.
     */
    GapfillerUi.prototype.reload = function() {
        var
            content = $(this.textArea).val(), // JSON-encoded HTML element settings.
            value,
            values,
            i,
            fields,
            outerDiv = "<div style='height:fit-content' class='qtype-coderunner-html-outer-div'>";

        this.htmlDiv = $(outerDiv + this.markedUpHtml() + "</div>");
        if (content) {
            try {
                values = JSON.parse(content);
                fields = this.getFields();
                for (i = 0; i < fields.length; i++) {
                    value = i < values.length ? values[i] : '???';
                    this.setField($(fields[i]), value);
                }
            } catch(e) {
                /**
                 * Just ignore errors
                 */
            }
        }
    };

    GapfillerUi.prototype.resize = function() {}; // Nothing to see here. Move along please.

    GapfillerUi.prototype.hasFocus = function() {
        var  focused = false;
        this.getFields().each(function() {
            if (this === document.activeElement) {
                focused = true;
            }
        });
        return focused;
    };

    /**
     * Destroy the GapFiller UI and serialise the result into the original text area.
     */
    GapfillerUi.prototype.destroy = function() {
        this.sync();
        $(this.htmlDiv).remove();
        this.htmlDiv = null;
    };

    return {
        Constructor: GapfillerUi
    };
});
