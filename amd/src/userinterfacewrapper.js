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
 * 1. A constructor InterfaceWrapper(uiname, textareaId, strings, params) which
 *    hides the given text area, replaces it with a wrapper div (resizable in
 *    height by the user but with width resizing managed by changes in window
 *    width), created an instance of nameInstance as defined in the file
 *    ui_name.js (see below). strings is an associative array mapping string
 *    name (from lang/en) to string.
 *    params is a record containing the decoded value of
 *    the question's templateParams, possibly enhanced by additional
 *    data such as a 'lang' value for the Ace editor.
 *
 * 2. A stop() method that destroys the embedded UI and hides the wrapper.
 *
 * 3. A restart() method that shows the wrapper again and re-creates the prior
 *    embedded UI component within it.
 *
 * 4. A loadUi(uiname, params) method that kills any currently running UI element
 *    (if there is one) and (re)loads the specified one. The params parameter
 *    is a record that allows additional parameters to be passed in, such as
 *    those from the question's templateParams field and, in the case of the
 *    Ace UI, the 'lang' (language) that the editor is editing.
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
 * 1. A constructor SomeUiName(textareaId, width, height, params) that
 *    builds an HTML component of the given width and height. textareaId is the
 *    ID of the textArea from which the UI element should obtain its initial
 *    serialisation and to which it should write the serialisation when its save
 *    or destroy methods are called. params is a JavaScript object,
 *    decoded from the JSON templateParams defined by the question plus any
 *    additional data required, such as the 'lang' in the case of Ace.
 *
 * 2. A getElement() method that returns the HTML element that the
 *    InterfaceWrapper is to insert into the document tree.
 *
 * 3. A method failed() that should return true unless the constructor
 *    failed (e.g. because it was not able to de-serialise the text area's
 *    contents). The wrapper will call destroy() on the object if failed()
 *    returns true and abort the use of the UI element. The text area will
 *    have the uiloadfailed class added, which CSS will display in some
 *    error mode (e.g. a red border).
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

    function InterfaceWrapper(uiname, textareaId, strings, params) {
        // Constructor for a new user interface.
        // uiname is the name of the interface element (e.g. ace, graph, etc)
        // which should be in file ui_ace.js, ui_graph.js etc.
        // textareaId is the id of the text area that the UI is to manage
        // strings is an associative array of language strings, namely
        // all those specified by constants::ui_plugin_keys.
        // params is a record containing whatever additional parameters might
        // be needed by the User interface. As a minimum it should contain all
        // the template params from the JSON-encode template-params field of
        // the question so that question authors can pass in additional data
        // such as whether graph edges are bidirectional or not in the case of
        // the graph UI. Additionally the Ace editor requires a 'lang' field
        // to specify what language the editor is editing.
        // When the wrapper has been set up on a text area, the text area's
        // data attribute contains an entry for 'current-ui-wrapper' that is
        // a reference to the wrapper ('this').
        var  h,
             t = this; // For use by embedded functions.

        this.MIN_WIDTH = 400;
        this.MIN_HEIGHT = 100;
        this.GUTTER = 14;  // Size of gutter at base of wrapper Node (pixels)

        this.taId = textareaId;
        this.loadFailId = textareaId + '_loadfailerr';
        this.textArea = $(document.getElementById(textareaId));
        this.readOnly = this.textArea.prop('readonly');
        this.isLoading = false;  // True if we're busy loading a UI element
        this.loadFailed = false;  // True if UI couldn't deserialise TA contents
        this.loadFailMessage = strings['uiloadfail'];
        this.retries = 0;        // Number of failed attempts to load a UI component

        h = parseInt(this.textArea.css("height"));

        // Construct an empty hidden wrapper div, inserted directly after the
        // textArea, ready to contain the actual UI.
        this.wrapperNode = $("<div id='" + this.taId + "_wrapper' class='ui_wrapper'></div>");
        this.textArea.after(this.wrapperNode);
        this.wrapperNode.hide();
        this.wrapperNode.css({
            resize: 'vertical',
            overflow: 'hidden',
            height: h,
            width: "auto",
            border: "1px solid darkgrey"
        });

        // Record a reference to this wrapper in the text area's data attribute
        // for use by external javascript that needs to interact with the
        // wrapper, e.g. the multilanguage.js module.
        this.textArea.data('current-ui-wrapper', this);

        // Load the UI into the wrapper (aysnchronous).
        this.uiInstance = null;  // Defined by loadUi asynchronously
        this.loadUi(uiname, params);  // Load the required UI element

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
                if (t.uiInstance !== null || t.loadFailed) {
                    t.stop();
                } else {
                    t.restart();        // Reactivate
                }
            }
        });
    }


    InterfaceWrapper.prototype.loadUi = function(uiname, params) {
        // Load the specified UI element (which in the case of Ace will need
        // to know the language, lang, as well - this must be supplied as
        // a 'lang' attribute of the record params.
        // When ui is up and running, this.uiInstance will reference it.
        // To avoid a potential race problem, if this method is already busy
        // with a load, try again in 200 msecs.
        //
        var t = this;

        if (this.isLoading) {  // Oops, we're loading a UI element already
            this.retries += 1;
            if (this.retries > 20) {
                alert("Failed to load UI component. If this error persists, please report it to the forum on coderunner.org.nz");
                this.retries = 0;
                this.loading = 0;
            } else {
                setTimeout(function() {
                    t.loadUi(uiname, params);
                }, 200); // Try again in 200 msecs
            }
            return;
        }
        this.retries = 0;
        this.params = params;  // Save in case need to restart

        this.stop();  // Kill any active UI first
        this.uiname = uiname;

        if (this.uiname === '' || this.uiname === 'none' || sessionStorage.getItem('disableUis')) {
            this.uiInstance = null;
        } else {
            this.isLoading = true;
            require(['qtype_coderunner/ui_' + this.uiname],
                function(ui) {
                    var minSize, uiInstance,loadFailWarn, h, w;

                    h = t.wrapperNode.innerHeight() - t.GUTTER;
                    w = t.wrapperNode.innerWidth();
                    uiInstance = new ui.Constructor(t.taId, w, h, params);
                    if (uiInstance.failed()) {
                        // Constructor failed to load serialisation.
                        // Set uiloadfailed class on text area.
                        t.wrapperNode.hide();
                        uiInstance.destroy();
                        t.uiInstance = null;
                        t.loadFailed = true;
                        t.textArea.addClass('uiloadfailed');
                        loadFailWarn = '<div id="' + t.loadFailId + '"class="uiloadfailed">' + t.loadFailMessage + '</div>';
                        $(loadFailWarn).insertBefore(t.textArea);
                    } else {
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
                        t.loadFailed = false;
                        t.checkForResize();
                    }
                    t.isLoading = false;
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
        this.loadFailed = false;
        this.textArea.removeClass('uiloadfailed'); // Just in case it failed before
        $(document.getElementById(this.loadFailId)).remove();
    };


    InterfaceWrapper.prototype.restart = function() {
        // Re-enable the ui element (e.g. after alt-cntrl-M). This is
        // a full re-initialisation of the ui element.
        if (this.uiInstance === null) {
            // Restart the UI component in the textarea
            this.loadUi(this.uiname, this.params);
        }
    };



    InterfaceWrapper.prototype.checkForResize = function() {
        // Check for wrapper resize - propagate to ui element.
        var h, hAdjusted, w, wAdjusted, xLeft, maxWidth;
        var SIZE_HACK = 25;  // Horrible but best I can do. TODO: FIXME

        if (this.uiInstance) {
            h = this.wrapperNode.innerHeight();
            w = this.wrapperNode.innerWidth();
            xLeft = this.wrapperNode.offset().left;
            maxWidth = $(window).innerWidth() - xLeft - SIZE_HACK;

            hAdjusted = Math.max(this.MIN_HEIGHT, h) - this.GUTTER;
            wAdjusted = Math.max(this.MIN_WIDTH, Math.min(maxWidth, w));

            if (hAdjusted !== this.hLast || wAdjusted !== this.wLast && this.uiInstance) {
                this.uiInstance.resize(wAdjusted,  hAdjusted);
                this.hLast = hAdjusted;
                this.wLast = wAdjusted;
            }
        }
    };

    /**
     *  The external entry point from the PHP.
     * @param string uiname, e.g. 'ace'
     * @param string textareaId
     * @param associative array strings language strings required by any plugins
     * @param string templateParamsJson - the JSON-encoded template params
     * @param string lang (relevant only to Ace) - the language to be edited
     * @returns {userinterfacewrapperL#111.InterfaceWrapper}
     */
    function newUiWrapper(uiname, textareaId, strings, templateParamsJson, lang) {
        var params;

        if (uiname) {
            try {
                params = window.JSON.parse(templateParamsJson);
            } catch(e) {
                params = {};
            }
            if (lang) {
                params.lang = lang;
            }
            return new InterfaceWrapper(uiname, textareaId, strings, params);
        } else {
            return null;
        }
    }


    return {
        newUiWrapper: newUiWrapper,
        InterfaceWrapper: InterfaceWrapper
    };
});
