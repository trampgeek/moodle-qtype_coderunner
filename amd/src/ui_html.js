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
 * associated field values are the results of calling the jquery val() method
 * on the UI elements. This means that at least input, select and textarea
 * elements are supported. The author is responsible for checking the
 * compatibility of other elements with jquery's val() method.
 *
 * The HTML to use in the answer area must be specified in a field 'html' in the
 * template parameters. Additionally the author can specify htmlminwidth
 * and htmlminheight template parameters; defaults are 200 and 300 respectively.
 *
 * If any fields of the answer html are to be preloaded, these should be specified
 * in the answer preload with json of the form '{"<fieldName>": "<fieldValue>", ...}'
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2018, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    function HtmlUi(textareaId, width, height, templateParams) {
        this.html = templateParams.html;
        this.textArea = $(document.getElementById(textareaId));
        this.readOnly = this.textArea.prop('readonly');
        this.templateParams = templateParams;
        this.fail = false;
        this.htmlDiv = "";
        this.fields = null;
        this.minWidth = templateParams['htmlminwidth'] ? templateParams['htmlminwidth'] : 200;
        this.minHeight = templateParams['htmlminheight'] ? templateParams['htmlminheight'] : 300;
        this.reload();
    }

    HtmlUi.prototype.failed = function() {
        return this.fail;
    };

    HtmlUi.prototype.getElement = function() {
        return this.htmlDiv;
    };

    HtmlUi.prototype.getMinSize = function() {
        return { minWidth: this.minWidth, minHeight: this.minHeight };
    };

    HtmlUi.prototype.getFields = function() {
        if (this.fields === null) {
            this.fields = $(this.htmlDiv).find('.coderunner-ui-element');
        }
        return this.fields;
    };

    HtmlUi.prototype.reload = function() {
        var
            content = $(this.textArea).val(), // JSON-encoded HTML element settings
            fieldValues,
            name;

        this.htmlDiv = $("<div>" + this.html + "</div>");
        if (content) {
            try {
                fieldValues = JSON.parse(content);
                this.getFields().each(function() {
                    name = $(this).attr('name');
                    if (fieldValues.hasOwnProperty(name)) {
                        $(this).val(fieldValues[name]);
                    }
                });

            } catch(e) {
                alert('Failed to initialise HTML UI');
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

    HtmlUi.prototype.destroy = function() {
        var
            serialisation = {},
            name,
            t = this;

        this.getFields().each(function() {
            name = $(this).attr('name');
            serialisation[name] = $(this).val();
            t.textArea.val(JSON.stringify(serialisation));
        });
        $(this.htmlDiv).remove();
        this.htmlDiv = '';
    };

    return {
        Constructor: HtmlUi
    };
});
