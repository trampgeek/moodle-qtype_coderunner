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
 * Implementation of the gapfiller_ui user interface plugin. For overall details
 * of the UI plugin architecture, see userinterfacewrapper.js.
 *
 * This plugin replaces the usual textarea answer box with a div
 * consisting of pre-formatted text supplied by the question author in the
 * "globalextra" field, with HTML entry or textarea elements inserted at
 * specified points. It is intended primarily for use with coding questions
 * where the answerbox presents the students with code that has smallish bits
 * missing.
 *
 * The locations within the globalextra text at which the input elements are
 * to be inserted are denoted by "tags" of the form
 *
 *     {[input("name", size)]}
 *
 * or
 *
 *     {[textarea("name", rows, cols)]}
 *
 * where size, rows and cols are integer literals. These respectively
 * inject an HTML input element or a textarea element of the
 * specified size. All parameters are optional; the name defaults to "value"
 * and the other defaults are size = 10, rows=2, cols=60. An empty string for
 * name, as in {[ input("", 20) ]} is also converted to "value".
 *
 * The serialisation of the answer box contents, i.e. the text that
 * copied back into the textarea for submissions
 * as the answer, is a JSON object. The fields of that object are the names
 * of all the entry elements. The
 * associated field values are lists. Each list contains all the values, in
 * document order, of the all the UI elements with that name. It can sometimes
 * be helpful to use default or empty names for all elements in the answer box,
 * as that results in a JSON serialisation in which all values appear in a list
 * associated with the attribute "value".
 *
 * As a special case of the serialisation, if all values in the serialisation
 * are either empty strings or a list of empty strings, the serialisation is
 * itself the empty string.
 *
 * The delimiters for the input element insertion tags are by default '{[' and
 * ']}', but can be changed by an optional template parameter gap_filler_delimiters,
 * which must be a 2-element array of strings. For example:
 *
 *     {"gap_filler_delimiters": ["{{", "}}"]}
 *
 * Note that the double-brace delimiters in that example are the same as those
 * used by Twig, so using them instead of the default would prevent you from
 * ever adding Twig expansion (e.g. for randomisation) to the question.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2019, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    function GapfillerUi(textareaId, width, height, templateParams) {
        this.textArea = $(document.getElementById(textareaId));
        this.html = this.textArea.attr('data-globalextra').replace('<', '&lt;');
        this.readOnly = this.textArea.prop('readonly');
        this.templateParams = templateParams;
        this.fail = false;
        this.htmlDiv = null;
        this.prefix = 'crui_';
        this.reload();
    }

    GapfillerUi.prototype.failed = function() {
        return this.fail;
    };

    // Copy the serialised version of the HTML UI area to the TextArea.
    // If name has the expected prefix, inserted by the plugin, the prefix
    // is stripped again for insertion into the serialisation.
    GapfillerUi.prototype.sync = function() {
        var
            serialisation = {},
            name,
            t = this,
            empty = true;

        this.getFields().each(function() {
            var value, type;
            type = $(this).attr('type');
            name = $(this).attr('name');
            if (name.startsWith(t.prefix)) {
                name = name.substring(t.prefix.length, name.length);
            }
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

    GapfillerUi.prototype.getElement = function() {
        return this.htmlDiv;
    };

    GapfillerUi.prototype.getFields = function() {
        return $(this.htmlDiv).find('.coderunner-ui-element');
    };

    // Set the value of the jQuery field to the given value.
    // If the field is a radio button or a checkbox,
    // the checked attribute is set. Otherwise the field's
    // val() function is called to set the value.
    GapfillerUi.prototype.setField = function(field, value) {
        if (field.attr('type') === 'checkbox' || field.attr('type') === 'radio') {
            field.prop('checked', field.val() === value);
        } else {
            field.val(value);
        }
    };

    // Process the supplied HTML, HTML-escaping existing HTML
    // and inserting the input and textarea elements
    // at the marked locations.
    GapfillerUi.prototype.markedUpHtml = function() {

        // Prefix any regular expression special chars in s with a backslash.
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
            splitter = new RegExp(sepLeft + '(.+?)' + sepRight),
            bits = this.html.split(splitter),
            result = '<pre>' + bits[0],
            i;

        for (i = 1; i < bits.length; i += 2) {
            result += this.markUp(bits[i]);
            if (i + 1 < bits.length) {
                result += bits[i + 1];
            }
        }

        return result + '</pre>';
    };


    // Return the HTML element to insert given the tag (input, textarea) and
    // its parameters as a string, e.g. input('fred', 10).
    GapfillerUi.prototype.markUp = function(tag) {
        var pattern, match, result='';
        var t = this;

        // The function to handle an 'input' tag.
        function input(name, size) {
            name = name || 'value';
            size = size || 10;
            return '<input name="' + t.prefix + name +
                '" class="coderunner-ui-element" size="' + size + '">';
        }

        // The function to handle a 'textarea' tag.
        function textarea(name, rows, cols) {
            name = name || 'value';
            cols = cols || 60;
            rows = rows || 2;
            return '<textarea name="' + t.prefix + name +
                    '" class ="coderunner-ui-element" rows="' + rows + '" ' +
                    'cols="' + cols + '" style="width:auto;" />';
        }

        tag = tag.trim();
        pattern = /(\w+)?\((?:(['"])(\w*)\2)?(?:, *(\d+))?(?:, (\d+))?\)/;
        match = tag.match(pattern);
        if (match && match[1] === 'input') {
            result = input(match[3], match[4]);
        } else if (match && match[1] === 'textarea') {
            result = textarea(match[3], match[4], match[5]);
        }
        return result;
    };

    GapfillerUi.prototype.reload = function() {
        var
            content = $(this.textArea).val(), // JSON-encoded HTML element settings.
            valuesToLoad,
            values,
            i,
            prefixedName,
            fields,
            leftOvers,
            outerDiv = "<div style='height:fit-content' class='qtype-coderunner-html-outer-div'>";

        this.htmlDiv = $(outerDiv + this.markedUpHtml() + "</div>");
        this.htmlDiv.data('templateparams', this.templateParams); // For use by  scripts embedded in html.
        if (content) {
            try {
                valuesToLoad = JSON.parse(content);
                leftOvers = {};
                for (var name in valuesToLoad) {
                    prefixedName = this.prefix + name;
                    values = valuesToLoad[name];
                    fields = this.getFields().filter("[name='" + prefixedName + "']");
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
                    this.htmlDiv.data('leftovers', leftOvers);
                }

            } catch(e) {
                alert('Failed to initialise GapFiller UI');
                this.fail = true;
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

    // Destroy the GapFiller UI and serialise the result into the original text area.
    GapfillerUi.prototype.destroy = function() {
        this.sync();
        $(this.htmlDiv).remove();
        this.htmlDiv = null;
    };

    return {
        Constructor: GapfillerUi
    };
});
