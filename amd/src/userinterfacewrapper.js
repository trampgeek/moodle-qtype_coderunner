/******************************************************************************
 *
 * This module provides a wrapper for user-interface modules, handling hiding
 * of the textArea that is being replaced by the UI element,
 * resizing of the UI component, and support of such usability functions as
 * ctrl-alt-M to disable/re-enable the entire user interface, including the
 * wrapper.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The InterfaceWrapper class is constructed either by Moodle PHP calls of
 * the form
 *
 * $PAGE->requires->js_call_amd($modulename, $functionname, $params)
 *
 * (e.g. from within render.php) or by JavaScript require calls, e.g. from
 * authorform.js when the question author changes UI type.
 *
 * The InterfaceWrapper provides:
 *
 * 1. A constructor InterfaceWrapper(uiname, textareaId, templateParamsJson) which
 *    hides the given text area, replaces it with a wrapper div (resizable in
 *    height by the user but with width resizing managed by changes in window
 *    width), created an instance of nameInstance as defined in the file
 *    ui_name.js (see below). templateParamsJson is the json-encoded template
 *    parameters value from the original question.
 *
 * 2. A stop() method that destroys the embedded UI and hides the wrapper.
 *
 * 3. A restart() method that shows the wrapper again and re-creates an
 *    embedded UI component within it.
 *
 * 4. A loadUi(uiname, lang) method that kills any currently running UI element
 *    (if there is one) and (re)loads the specified one.
 *
 * 5. Regular checking for any resizing of the wrapper, which are passed on to
 *    the embedded UI element's resize() method.
 *
 * 6. Monitoring of alt-ctrl-M key presses which toggle the visibility of the
 *    wrapper plus UI element and the syncronised textArea by calls to stop()
 *    and restart
 *
 * =========================================================================
 *
 * The embedded user-interface module must be defined in a JavaScript file
 * of the form ui_name.js which must define a class nameInstance with
 * the following functionality:
 *
 * 1. A constructor SomeUiName(textareaId, width, height, templateParams) that
 *    builds an HTML component of the given width and height. textareaId is the
 *    ID of the textArea from which the UI element should obtain its initial
 *    serialisation and to which it should write the serialisation when its save
 *    or destroy methods are called. templateParams is a JavaScript object,
 *    decoded from the JSON-version stored in the question.
 *
 * 2. A getElement() method that returns the HTML element that the
 *    InterfaceWrapper is to insert into the document tree.
 *
 * 3. A method failed() that should return true unless the constructor
 *    failed (e.g. because it was not able to de-serialise the text area's
 *    contents). The wrapper will call destroy() on the object if failed()
 *    returns true and abort the use of the UI element. THe element should
 *    issue its own alert if it fails.
 *
 * 4. A getMinSize() method that should return a record with minWidth and
 *    minHeight values. These will be used to control the minimum size of the
 *    userinterface wrapper.
 *
 * 5. A destroy() method that should save the contents to the text area then
 *    destroy any HTML elements or other created content.
 *
 * 6. A resize(width, height) method that should resize the entire UI element
 *    to the given dimensions.
 *
 * 7. A hasFocus() method that returns true if the UI element has focus.
 *
 * The return value from the module define is a record with a single field
 * 'Constructor' that references the constructor (e.g. Graph, AceWrapper etc)
 *
 * If the module needs any strings from one of the language files, it should
 * access them via a call like M.util.get_string('graphfail', 'qtype_coderunner').
 * Any such strings must be defined explicitly in the function
 * constants::ui_plugin_keys().
 *
 *****************************************************************************/

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


define(['jquery'], function($) {

    function InterfaceWrapper(uiname, textareaId, templateParamsJson, lang) {
        // Constructor for a new user interface.
        // uiname is the name of the interface element (e.g. ace, graph, etc)
        // which should be in file ui_ace.js, ui_graph.js etc.
        // textareaId is the id of the text area that the UI is to manage
        // templateParamsJson is the json-encoded set of template parameters
        // from the question
        // lang, which is unnecessary unless uiname == 'ace', is the language
        // for ace.
        var  h,
             t = this; // For use by embedded functions.

        this.MIN_WIDTH = 400;
        this.MIN_HEIGHT = 100;
        this.GUTTER = 14;  // Size of gutter at base of wrapper Node (pixels)

        this.taId = textareaId;
        this.textArea = $(document.getElementById(textareaId));
        this.readOnly = this.textArea.prop('readonly');

        if (templateParamsJson) {
            this.templateParams = window.JSON.parse(templateParamsJson);
        } else {
            this.templateParams = {};
        }

        h = parseInt(this.textArea.css("height"));

        this.wrapperNode = $("<div id='" + this.taId + "_wrapper' class='ui_wrapper'></div>");
        this.wrapperNode.css({
            resize: 'vertical',
            overflow: 'hidden',
            height: h,
            width: "100%",
            border: "1px solid darkgrey"
        });

        this.uiInstance = null;  // Defined by loadUi asynchronously
        this.loadUi(uiname, lang);  // Load the required UI element

        // Add event handlers

        $(document).mousemove(function() {
            t.checkForResize();
        });

        $(window).resize(function() {
            t.checkForResize();
        });

        $(document.body).on('keydown', function(e) {
            var KEY_M = 77;

            if (e.keyCode === KEY_M && e.ctrlKey && e.altKey) {
                if (t.uiInstance !== null) {
                    t.stop();
                } else {
                    t.restart();        // Reactivate
                }
            }
        });
    }


    InterfaceWrapper.prototype.loadUi = function(uiname, lang) {
        // Load the specified UI element (which in the case of Ace will need
        // to know the language, lang, as well).
        // When ui is up and running, this.uiInstance will reference it.
        //
        var t = this;

        this.stop();  // Kill any active UI first
        this.uiname = uiname;
        this.lang = lang;

        if (this.uiname === '' || this.uiname === 'none' || sessionStorage.getItem('disableUis')) {
            this.uiInstance = null;
        } else {
            require(['qtype_coderunner/ui_' + this.uiname],
                function(ui) {
                    var minSize, uiInstance, h, hInner, w;

                    h = t.wrapperNode.outerHeight();
                    hInner = h - t.GUTTER;
                    w = t.wrapperNode.outerWidth();
                    uiInstance = new ui.Constructor(t.taId, w, hInner, t.templateParams, t.lang);
                    if (uiInstance.failed()) {
                        // Constructor failed. Abort.
                        uiInstance.destroy();
                        t.wrapperNode.hide();
                        t.uiInstance = null;  //
                    } else {
                        t.textArea.after(t.wrapperNode);
                        minSize = uiInstance.getMinSize();
                        t.wrapperNode.css({
                            minWidth: minSize.minWidth,
                            minHeight: minSize.minHeight + t.GUTTER
                        });
                        t.hLast = 0;  // Force resize (and hence redraw)
                        t.wLast = 0;  // ... on first call to checkForResize
                        t.wrapperNode.append(uiInstance.getElement());
                        t.textArea.hide();
                        t.wrapperNode.show();
                        t.uiInstance = uiInstance;
                        t.checkForResize();
                    }
                });
        }
    };


    InterfaceWrapper.prototype.stop = function() {
        // Disable (shutdown) the embedded ui component.
        // The wrapper remains active for ctrl-alt-M events, but is hidden.
        if (this.uiInstance !== null) {
            this.textArea.show();
            if (this.uiInstance.hasFocus()) {
                this.textArea.focus();
                this.textArea[0].selectionStart = this.textArea[0].value.length;
            }
            this.uiInstance.destroy();
            this.uiInstance = null;
            this.wrapperNode.hide();
        }
    };


    InterfaceWrapper.prototype.restart = function() {
        // Re-enable the ui element (e.g. after alt-cntrl-M). This is
        // a full re-initialisation of the ui element.
        if (this.uiInstance === null) {
            // Restart the UI component in the textarea
            this.loadUi(this.uiname, this.lang);
        }
        this.wrapperNode.show();
    };



    InterfaceWrapper.prototype.checkForResize = function() {
        // Check for wrapper resize - propagate to ui element.
        var h, w;

        if (this.uiInstance) {
            h = this.wrapperNode.outerHeight();
            w = this.wrapperNode.outerWidth();

            h = Math.max(this.MIN_HEIGHT, h);
            w = Math.max(this.MIN_WIDTH, w);

            if (h !== this.hLast || w !== this.wLast && this.uiInstance) {
                this.uiInstance.resize(w,  h - this.GUTTER);
                this.hLast = h;
                this.wLast = w;
            }
        }
    };


    function newUiWrapper(uiname, textareaId, templateParamsJson, lang) {
        // Need this because php call doesn't allow 'new'
        return new InterfaceWrapper(uiname, textareaId, templateParamsJson, lang);
    }


    return {
        newUiWrapper: newUiWrapper
    };
});
