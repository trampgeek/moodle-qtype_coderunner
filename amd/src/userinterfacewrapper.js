/******************************************************************************
 *
 * This module provides a wrapper for user-interface modules, handling hiding
 * of the textArea that is being replaced by the UI element,
 * resizing of the UI component, and support of such usability functions as
 * ctrl-alt-M to disable/re-enable the entire user interface, including the
 * wrapper.
 *
 * @module coderunner/userinterfacewrapper
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
 * 1. A constructor InterfaceWrapper(uiname, textareaId) which
 *    hides the given text area, replaces it with a wrapper div (resizable in
 *    height by the user but with width resizing managed by changes in window
 *    width), created an instance of nameInstance as defined in the file
 *    ui_name.js (see below).
 *    params is a record containing the decoded value of
 *
 * 2. A stop() method that destroys the embedded UI and hides the wrapper.
 *
 * 3. A restart() method that shows the wrapper again and re-creates the prior
 *    embedded UI component within it.
 *
 * 4. A loadUi(uiname, params) method that kills any currently running UI element
 *    (if there is one) and (re)loads the specified one. The params parameter
 *    is a record that allows additional parameters to be passed in, such as
 *    those from the question's uiParams field and, in the case of the
 *    Ace UI, the 'lang' (language) that the editor is editing. This data
 *    is supplied by the PHP via the data-params attribute of the answer's
 *    base textarea.
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
 *    decoded from the JSON uiParams defined by the question plus any
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
 * 4. A method failMessage() that will be called only when failed() returns
 *    True. It should be a defined CodeRunner language string key.
 *
 * 5. A sync() method that copies the serialised represention of the UI plugin's
 *    data to the related TextArea. This is used when submit is clicked.
 *
 * 6. A destroy() method that should sync the contents to the text area then
 *    destroy any HTML elements or other created content. This method is called
 *    when CTRL-ALT-M is typed by the user to turn off all UI plugins
 *
 * 7. A resize(width, height) method that should resize the entire UI element
 *    to the given dimensions.
 *
 * 8. A hasFocus() method that returns true if the UI element has focus.
 *
 * 9. A syncIntervalSecs() method that returns the time interval between
 *    calls to the sync() method. 0 for no sync calls. The userinterfacewrapper
 *    provides all instances with a generic (base-class) version that returns
 *    the value of a UI parameter sync_interval_secs if given else uses the
 *    UI interface wrapper default (currently 10).
 *
 * The return value from the module define is a record with a single field
 * 'Constructor' that references the constructor (e.g. Graph, AceWrapper etc)
 *
 *****************************************************************************/

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


define(['jquery'], function($) {
    /**
     * Constructor for a new user interface.
     * @param {string} uiname The name of the interface element (e.g. ace, graph, etc)
     * which should be in file ui_ace.js, ui_graph.js etc.
     * @param {string} textareaId The id of the text area that the UI is to manage.
     * The text area should have an attribute data-params, which is a
     * JSON encoded record containing whatever additional parameters might
     * be needed by the User interface. As a minimum it should contain all
     * the parameters from the uiparameters field of
     * the question so that question authors can pass in additional data
     * such as whether graph edges are bidirectional or not in the case of
     * the graph UI. Additionally the Ace editor requires a 'lang' field
     * to specify what language the editor is editing.
     * When the wrapper has been set up on a text area, the text area's
     * data attribute contains an entry for 'current-ui-wrapper' that is
     * a reference to the wrapper ('this').
     */
    function InterfaceWrapper(uiname, textareaId) {

        var  h,
            params,
            t = this; // For use by embedded functions.

        this.GUTTER = 14;  // Size of gutter at base of wrapper Node (pixels)
        this.MIN_WRAPPER_HEIGHT = 50;
        this.DEFAULT_SYNC_INTERVAL_SECS = 5;

        this.taId = textareaId;
        this.loadFailId = textareaId + '_loadfailerr';
        this.textArea = $(document.getElementById(textareaId));
        params = this.textArea.attr('data-params');
        if (params) {
            this.uiParams = JSON.parse(params);
        } else {
            this.uiParams = {};
        }
        this.uiParams.lang = this.textArea.attr('data-lang');
        this.readOnly = this.textArea.prop('readonly');
        this.isLoading = false;  // True if we're busy loading a UI element
        this.loadFailed = false;  // True if UI failed to initialise properly
        this.retries = 0;        // Number of failed attempts to load a UI component

        h = Math.max(parseInt(this.textArea.css("height")), this.MIN_WRAPPER_HEIGHT);

        /**
         * Construct an empty hidden wrapper div, inserted directly after the
         * textArea, ready to contain the actual UI.
         */
        this.wrapperNode = $("<div id='" + this.taId + "_wrapper' class='ui_wrapper'></div>");
        this.textArea.after(this.wrapperNode);
        this.wrapperNode.hide();
        this.wrapperNode.css({
            resize: 'vertical',
            overflow: 'hidden',
            minHeight: h,
            width: "100%",
            border: "1px solid darkgrey"
        });

        /**
         * Record a reference to this wrapper in the text area's data attribute
         * for use by external javascript that needs to interact with the
         * wrapper, e.g. the multilanguage.js module.
         */
        this.textArea.data('current-ui-wrapper', this);

        /**
         * Load the UI into the wrapper (aysnchronous).
         */
        this.uiInstance = null;  // Defined by loadUi asynchronously
        this.loadUi(uiname, this.uiParams);  // Load the required UI element

        /**
         * Add event handlers
         */
        $(document).mousemove(function() {
            t.checkForResize();
        });
        $(window).resize(function() {
            t.checkForResize();
        });
        this.textArea.closest('form').submit(function() {
            if (t.uiInstance !== null) {
                t.uiInstance.sync();
            }
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

    /**
     * Load the specified UI element (which in the case of Ace will need
     * to know the language, lang, as well - this must be supplied as
     * a 'lang' attribute of the record params.
     * When ui is up and running, this.uiInstance will reference it.
     * To avoid a potential race problem, if this method is already busy
     * with a load, try again in 200 msecs.
     * @param {string} uiname The name of the User Interface to be used.
     * @param {object} params The UI parameters object that passes parameters
     * to the actual UI object.
     */
    InterfaceWrapper.prototype.loadUi = function(uiname, params) {
        var t = this,
            errPart1 = 'Failed to load ',
            errPart2 = ' UI component. If this error persists, please report it to the forum on coderunner.org.nz';

        /**
         * Get the given language string and plug it into the given jQuery
         * div element as its html, plus a 'fallback' message on a separate line.
         * @param {string} langString The language string specifier for the error message,
         * to be loaded by AJAX.
         * @param {object} errorDiv The div object into which the error message
         * is to be inserted.
         */
        function setLoadFailMessage(langString, errorDiv) {
            require(['core/str'], function(str) {
                /**
                 * Get langString text via AJAX
                 */
                var
                    s = str.get_string(langString, 'qtype_coderunner'),
                    fallback = str.get_string('ui_fallback', 'qtype_coderunner');
                $.when(s, fallback).done(function(s, fallback) {
                    errorDiv.html(s + '<br>' + fallback);
                });
            });
        }

        /**
         * The default method for a UIs sync_interval_secs method.
         * Returns the sync_interval_secs parameter if given, else
         * DEFAULT_SYNC_INTERVAL_SECS.
         */
        function syncIntervalSecsBase() {
            if (params.hasOwnProperty('sync_interval_secs')) {
                return parseInt(params.sync_interval_secs);
            } else {
                return t.DEFAULT_SYNC_INTERVAL_SECS;
            }
        }

        if (this.isLoading) {  // Oops, we're loading a UI element already
            this.retries += 1;
            if (this.retries > 20) {
                alert(errPart1 + uiname + errPart2);
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
                    var uiInstance,loadFailDiv, jqLoadFailDiv, h, w, uiInstancePrototype;

                    h = t.wrapperNode.innerHeight() - t.GUTTER;
                    w = t.wrapperNode.innerWidth();
                    uiInstance = new ui.Constructor(t.taId, w, h, params);
                    if (uiInstance.failed()) {
                        /*
                         * Constructor failed to load serialisation.
                         * Set uiloadfailed class on text area.
                         */
                        t.loadFailed = true;
                        t.wrapperNode.hide();
                        uiInstance.destroy();
                        t.uiInstance = null;
                        t.textArea.addClass('uiloadfailed');
                        loadFailDiv = '<div id="' + t.loadFailId + '"class="uiloadfailed"></div>';
                        jqLoadFailDiv = $(loadFailDiv);
                        jqLoadFailDiv.insertBefore(t.textArea);
                        setLoadFailMessage(uiInstance.failMessage(), jqLoadFailDiv);  // Insert error by AJAX
                    } else {
                        t.hLast = 0;  // Force resize (and hence redraw)
                        t.wLast = 0;  // ... on first call to checkForResize
                        t.textArea.hide();
                        t.wrapperNode.show();
                        t.wrapperNode.append(uiInstance.getElement());
                        t.uiInstance = uiInstance;
                        t.loadFailed = false;
                        t.checkForResize();

                        /*
                         * Set a default syncIntervalSecs method if uiInstance lacks one.
                         */
                        uiInstancePrototype = Object.getPrototypeOf(uiInstance);
                        uiInstancePrototype.syncIntervalSecs = uiInstancePrototype.syncIntervalSecs || syncIntervalSecsBase;
                        t.startSyncTimer(uiInstance);
                    }
                    t.isLoading = false;
                });
        }
    };


    /**
     * Start a sync timer on the given uiInstance, unless its time interval is 0.
     * @param {object} uiInstance The instance of the user interface object whose
     * timer is to be set up.
     */
    InterfaceWrapper.prototype.startSyncTimer = function(uiInstance) {
        var timeout = uiInstance.syncIntervalSecs();
        if (timeout) {
            this.uiInstance.timer = setInterval(function () {
                uiInstance.sync();
            }, timeout * 1000);
        } else {
            this.uiInstance.timer = null;
        }
    };


    /**
     * Stop the sync timer on the given uiInstance, if running.
     * @param {object} uiInstance The instance of the user interface object whose
     * timer is to be set up.
     */
    InterfaceWrapper.prototype.stopSyncTimer = function(uiInstance) {
        if (uiInstance.timer) {
            clearTimeout(uiInstance.timer);
        }
    };


    InterfaceWrapper.prototype.stop = function() {
        /*
         * Disable (shutdown) the embedded ui component.
         * The wrapper remains active for ctrl-alt-M events, but is hidden.
         */
        if (this.uiInstance !== null) {
            this.stopSyncTimer(this.uiInstance);
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

    /*
     * Re-enable the ui element (e.g. after alt-cntrl-M). This is
     * a full re-initialisation of the ui element.
     */
    InterfaceWrapper.prototype.restart = function() {
        if (this.uiInstance === null) {
            /**
             * Restart the UI component in the textarea
             */
            this.loadUi(this.uiname, this.params);
        }
    };


    /**
     * Check for wrapper resize - propagate to ui element.
     */
    InterfaceWrapper.prototype.checkForResize = function() {

        var h, hAdjusted, w, wAdjusted, xLeft, maxWidth;
        var SIZE_HACK = 25;  // Horrible but best I can do. TODO: FIXME

        if (this.uiInstance) {
            h = this.wrapperNode.innerHeight();
            w = this.wrapperNode.innerWidth();
            if (h != this.hLast || w != this.wLast) {
                xLeft = this.wrapperNode.offset().left;
                maxWidth = $(window).innerWidth() - xLeft - SIZE_HACK;
                hAdjusted = h - this.GUTTER;
                wAdjusted = Math.min(maxWidth, w);
                this.uiInstance.resize(wAdjusted,  hAdjusted);
                this.hLast = this.wrapperNode.innerHeight();
                this.wLast = this.wrapperNode.innerWidth();
            }
        }
    };

    /**
     * The external entry point from the PHP.
     * @param {string} uiname The name of the User Interface to use e.g. 'ace'
     * @param {string} textareaId The ID of the textarea to be wrapped.
     */
    function newUiWrapper(uiname, textareaId) {
        if (uiname) {
            return new InterfaceWrapper(uiname, textareaId);
        } else {
            return null;
        }
    }


    return {
        newUiWrapper: newUiWrapper,
        InterfaceWrapper: InterfaceWrapper
    };
});
