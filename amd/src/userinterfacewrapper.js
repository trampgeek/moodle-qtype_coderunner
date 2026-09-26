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
 * REQUIRED METHODS
 *
 * 1. A constructor SomeUiName(textareaId, width, height, params) that
 *    performs only synchronous, lightweight setup: recording parameters and
 *    reading the textarea's current value. It must NOT attempt to access
 *    window.ace or perform any async work (e.g. template rendering).
 *    textareaId is the ID of the textArea from which the UI element should
 *    obtain its initial serialisation and to which it should write the
 *    serialisation when its sync() or destroy() methods are called. params is
 *    a JavaScript object decoded from the JSON uiParams defined by the question
 *    plus any additional data required, such as 'lang' for the Ace editors.
 *
 * 2. A failed() method that returns true if the constructor detected an error
 *    (e.g. could not de-serialise the textarea's contents). When failed()
 *    returns true the wrapper calls destroy() and aborts UI loading; the
 *    textarea gets the uiloadfailed class and a visible error message.
 *
 * 3. A failMessage() method returning a CodeRunner language-string key
 *    describing the error. Only called when failed() returns true.
 *
 * 4. A getElement() method that returns the HTML element to be inserted into
 *    the document tree. Called only after ready() has resolved, so the element
 *    is guaranteed to exist by then.
 *
 * 5. A sync() method that copies the serialised representation of the UI
 *    plugin's data to the related textarea. Called periodically and on submit.
 *
 * 6. A destroy() method that syncs contents to the textarea then removes any
 *    HTML elements or other created content. Called when Ctrl-Alt-M toggles
 *    the UI off or when the UI is replaced.
 *
 * 7. A resize(width, height) method that resizes the entire UI element to the
 *    given dimensions.
 *
 * 8. A hasFocus() method that returns true if the UI element currently has
 *    keyboard focus.
 *
 * OPTIONAL METHODS
 *
 * 9. A ready() method returning a Promise that resolves when the UI element
 *    returned by getElement() is fully built and ready to be inserted into the
 *    DOM. If absent, the wrapper behaves as if ready() returns Promise.resolve().
 *    Use this for any async initialisation: waiting for window.ace to appear,
 *    rendering Mustache templates, etc. Reject the promise (or throw) to signal
 *    failure; the wrapper will then show the same error UI as a failed()
 *    constructor.
 *
 * 10. A postInsert(wrapperNode) method called immediately after the element
 *    returned by getElement() has been appended to wrapperNode in the live DOM.
 *    Use this for work that requires DOM presence: finding child elements by ID,
 *    creating nested InterfaceWrapper instances (e.g. sub-Ace editors), wiring
 *    event listeners that need real layout. If absent, nothing extra is done.
 *
 * 11. A syncIntervalSecs() method that returns the time interval in seconds
 *    between automatic calls to sync(). Return 0 to disable. The wrapper
 *    provides a default that returns the sync_interval_secs UI parameter when
 *    present, or DEFAULT_SYNC_INTERVAL_SECS otherwise.
 *
 * 12. An allowFullScreen() method that returns true if the UI supports the
 *    full-screen toggle button. Defaults to false if not implemented.
 *
 * 13. A setAllowFullScreen(allow) method (boolean) that overrides the value
 *    returned by allowFullScreen(). Provided so that a parent UI (e.g.
 *    Scratchpad) can control the full-screen behaviour of a child UI.
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







define(['core/templates', 'core/notification'], function(Templates, Notification) {


    /**
     * Checks that the textarea is an outer answer element which is where the overall
     * answer is stored.
     * That is, something like id_q58:5 answer
     * rather than something like id_q58:5_answer_answer-code
     *
     * @param {string} textareaId - The id for the element containing the text representation of the answer.
     * @returns {boolean} - Whether or not it's an outer answer element.
     */
    function isAnAnswer(textareaId) {
        const pattern = /^id_q\d+:\d+_answer$/;
        return pattern.test(textareaId);
    }

    /**
     * Computes the fnv1a32 hash for the given string (encoded to UTF-8)
     * @function fnv1a32
     * @param {string} str - The string to hash.
     * @returns {string} hexDigest - The hex digest of the hash value.
     */
    function fnv1a32(str) {
        /* eslint-disable no-bitwise */
        const encoder = new TextEncoder(); // UTF-8 by default
        const bytes = encoder.encode(str);
        let hash = 0x811c9dc5 >>> 0; // FNV offset basis.
        const prime = 0x01000193 >>> 0; // FNV prime.
        for (let i = 0; i < bytes.length; i++) {
            hash ^= bytes[i];
            const hashafterxor = hash >>> 0; // Ensure 32-bit unsigned integer.
            hash = (hashafterxor * prime) >>> 0; // Ensure 32-bit unsigned integer.
        }
        const hexDigest = hash.toString(16);
        return hexDigest;
    }



    /**
     * Computes the hash for the current answer and compares it to the hash of
     * the last checked answer.
     * If they are different, changes the style on the relevant results div to show
     * that the answer is different from the one that was checked.
     *
     * @async
     * @function compare_with_last_checked
     * @param {string} textareaId - The id for the element containing the text representation of the answer.
     * @returns {string} The hash in hexadecimal format.
     */
    async function compare_with_last_checked(textareaId) {
        if (!textareaId || !isAnAnswer(textareaId)) {
            return null;
        }
        const textArea = document.getElementById(textareaId);
        const params = textArea.getAttribute('data-params');
        if (params) {
            const uiParams = JSON.parse(params);
            const lastcheckedanswerhash = uiParams.lastcheckedanswerhash; // Will be "" if no last answer.
            const extractcodefromjson = uiParams.extractcodefromjson;
            var currentanswer = textArea.value;

            // Normalising to \n's everywhere...
            currentanswer = currentanswer.replace(/\r\n/g, '\n');

            if (extractcodefromjson == "1") {
                // Pull out the actual answer part from the JSON.
                // Otherwise changes in UI variables, eg, expanded/unexpanded scratchpad
                // will look like a changed answer.
                try {
                    const answerBits = JSON.parse(currentanswer);
                    if ('answer_code' in answerBits) {
                        // answer_code is currently the name in onstants::ANSWER_CODE_KEY
                        currentanswer = answerBits.answer_code[0];
                    }
                    // Otherwise leave the answer as it is.
                    // It could still be JSON, eg, a graphUI answer
                    // But the marker will be expecting it
                    // and it doesn't contain values that
                    // aren't related to the answer.
                } catch(error) {
                        // couldn't decode JSON so it's probably not JSON so leave it as is...
                    }
            }

            // Generate SHA256 of answer - old code...
            //const encoder = new TextEncoder();
            //const data = encoder.encode(currentanswer);
            //const hashHex = await crypto.createHash('sha256').update(data).digest('hex');
            //const hashBuffer = await crypto.subtle.digest("SHA-256", data);
            //const hashArray = Array.from(new Uint8Array(hashBuffer));
            //const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

            // Now using fnv1a32 in php and JS so as can handle the hash values natively.
            const hashHex = fnv1a32(currentanswer);

            // Compare with last checked answer if there was one
            if (lastcheckedanswerhash) {
                var thisQuestionObject = textArea.closest('[id*="question"]');
                var thisQuestionId = thisQuestionObject.id;
                const escapedQuestionId = thisQuestionId.replace(/([:\\.#\[\],=])/g, '\\$1');
                const areaToFind = '#' + escapedQuestionId + ' .coderunner-test-results';
                var feedbackArea = document.querySelector(areaToFind);
                if (feedbackArea) {
                    const noticeId = textareaId + "-changed-notice";
                    const specificNotice = document.querySelector(`[data-id="${CSS.escape(noticeId)}"]`);
                    if (hashHex !== lastcheckedanswerhash) {
                        if (!specificNotice) {
                            feedbackArea.classList.add('answer-changed');
                            const message = document.createElement("p");
                            message.textContent = "Results below are for a different answer to the answer above.";
                            message.style.color = "black";
                            message.style.backgroundColor = "greenyellow";
                            message.style.fontSize = "larger";
                            message.setAttribute("data-id", noticeId);
                            feedbackArea.parentNode.insertBefore(message, feedbackArea);
                            }
                    } else {
                        // revert back to normal.
                        feedbackArea.classList.remove('answer-changed');
                        if (specificNotice) {
                            specificNotice.remove();
                        }
                    }
                }
            }
            return hashHex;
        }
    }

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
     * When the wrapper has been set up on a text area, the text area
     * element has a reference, current_ui_wrapper, to the UI wrapper.
     */
    function InterfaceWrapper(uiname, textareaId) {

        let t = this; // For use by embedded functions.

        this.GUTTER = 16;  // Size of gutter at base of wrapper Node (pixels)
        this.DEFAULT_SYNC_INTERVAL_SECS = 5;

        this.uniqueId = Math.random();
        const PIXELS_PER_ROW = 19;  // For estimating height of textareas.
        const MAX_GROWN_ROWS = 50;  // Upper limit to artifically grown textarea rows.
        const MIN_WRAPPER_HEIGHT = 50;
        this.isFullScreenEnable = null;
        this.taId = textareaId;  // Why is this different to the way it's stored in UI's
        this.textareaId = textareaId;
        this.loadFailId = textareaId + '_loadfailerr';
        this.textArea = document.getElementById(textareaId);
        if (this.textArea.current_ui_wrapper) {
            alert(`JavaScript error: multiple UIs on ${textareaId}!`);
        }
        const params = this.textArea.getAttribute('data-params');
        if (params) {
            this.uiParams = JSON.parse(params);
        } else {
            this.uiParams = {};
        }

        this.uiParams.lang = this.textArea.getAttribute('data-lang');
        this.readOnly = this.textArea.readOnly;
        this.isLoading = false;   // True if we're busy loading a UI element.
        this.loadFailed = false;  // True if UI failed to initialise properly.
        this.retries = 0;         // Number of failed attempts to load a UI component.

        let h = this.textArea.clientHeight; // Just a first guess. Will be fine tuned in resize.

        // Grow height if textarea contents warrant.
        let content_lines = this.textArea.value.split('\n').length;
        let rows = this.textArea.rows;
        if (content_lines > rows) {
            // Allow reloaded text areas with lots of text to grow bigger, within limits.
            rows = Math.min(content_lines, MAX_GROWN_ROWS);
        }
        h = Math.max(h, rows * PIXELS_PER_ROW, MIN_WRAPPER_HEIGHT);
        this.textArea.style.height = h + 'px';
        /**
         * Construct a hidden empty wrapper div, inserted directly after the
         * textArea, ready to contain the actual UI.
         */
        this.wrapperNode = document.createElement('div');
        this.wrapperNode.id = `${this.taId}_wrapper`;
        this.wrapperNode.classList.add('ui_wrapper', 'position-relative');
        this.wrapperNode.uniqueId = this.uniqueId;
        this.wrapperNode.style.display = 'none';
        this.wrapperNode.style.resize = 'vertical';
        this.wrapperNode.style.overflow = 'hidden';
        this.wrapperNode.style.minHeight = h + "px";
        this.wrapperNode.style.width = '100%';
        this.wrapperNode.style.border = '1px solid darkgrey';
        this.textArea.insertAdjacentElement('afterend', this.wrapperNode);

        this.wLast = 0;  // Record last known width and height. See checkForResize().
        this.hLast = 0;


        /**
         * Record a reference to this wrapper in the text area
         * for use by external javascript that needs to interact with the
         * wrapper, e.g. the multilanguage.js module.
         */
        this.textArea.current_ui_wrapper = this;

        /**
         * Load the UI into the wrapper (aysnchronous).
         */
        this.uiInstance = null;  // Defined by loadUi asynchronously
        this.loadUi(uiname, this.uiParams);  // Load the required UI element

        // Change result so that it is clear if the answer is different from the last checked answer.
        compare_with_last_checked(textareaId);

        /**
         * Add event handlers
         */
        const resizeObserver = new ResizeObserver(function () {
            t.checkForResize();
        });
        resizeObserver.observe(this.wrapperNode);


        window.addEventListener('resize', function() {
            t.checkForResize();
        });

        const form = this.textArea.closest('form');
        if (form) {  // There may not be a form, e.g. when reviewing a submission.
            form.addEventListener('submit', function() {
                if (t.uiInstance !== null) {
                    t.uiInstance.sync();
                    compare_with_last_checked(t.textareaId);
                }
            });
        }

        document.body.addEventListener('keydown', function keyDown(e) {
            if (e.key === 'm' && e.ctrlKey && e.altKey) {
                // Before trying to handle ctrl-alt-m keypresses, make sure the
                // current instance of the wrapper in the DOM is the same as
                // when this event handler was created. This might not be
                // the case when userinterface wrappers are nested.
                const wrapper = document.getElementById(`${t.taId}_wrapper`);
                if (!wrapper || wrapper.uniqueId !== t.uniqueId) {
                    // This wrapper has apparently been killed. Stop listening.
                    // Should now be garbage collectable, too.
                    document.removeEventListener('keydown', keyDown);
                } else if (t.uiInstance !== null || t.loadFailed) {
                    t.stop();
                } else {
                    t.restart();        // Reactivate
                }
            }


        });
    }







    /**
     * Set the value of the allowFullScreen property.
     * If the value is true, the fullscreen mode will be shown.
     * If the value is false, the fullscreen will be hidden.
     *
     * @param {Boolean} enableFullScreen The value to set.
     */
    InterfaceWrapper.prototype.setAllowFullScreen = function(enableFullScreen) {
        this.isFullScreenEnable = enableFullScreen;
    };

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
        const MAX_RETRIES = 20; // Maximum number of attempts to load the UI.
        const t = this;
        const errPart1 = 'Failed to load ';
        const errPart2 = ' UI component. If this error persists, please report it to the forum on coderunner.org.nz';

        /**
         * Get the given language string and plug it into the given
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
                const s = str.get_string(langString, 'qtype_coderunner');
                const fallback = str.get_string('ui_fallback', 'qtype_coderunner');
                Promise.all([s, fallback]).then(function(results) {
                    const s = results[0];
                    const fallback = results[1];
                    errorDiv.innerHTML = s + '<br>' + fallback;
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
            if (this.retries > MAX_RETRIES) {
                alert(errPart1 + uiname + errPart2);
                this.retries = 0;
                this.isLoading = false;
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
                    const h = t.textArea.clientHeight - t.GUTTER;
                    const w = t.textArea.clientWidth;
                    const uiInstance = new ui.Constructor(t.taId, w, h, params);
                    if (uiInstance.failed()) {
                        /*
                         * Constructor failed to load serialisation.
                         * Set uiloadfailed class on text area.
                         */
                        t.loadFailed = true;
                        t.wrapperNode.style.display = 'none';
                        t.textArea.style.display = '';
                        uiInstance.destroy();
                        t.uiInstance = null;
                        t.textArea.classList.add('uiloadfailed');
                        const loadFailDiv = document.createElement('div');
                        loadFailDiv.id = t.loadFailId;
                        loadFailDiv.className = 'uiloadfailed';
                        t.textArea.parentNode.insertBefore(loadFailDiv, t.textArea);
                        setLoadFailMessage(uiInstance.failMessage(), loadFailDiv);  // Insert error by AJAX
                        t.isLoading = false;
                    } else {
                        // Wait for the UI to signal readiness (e.g. Ace loaded, Mustache rendered),
                        // then append its element and call postInsert for work requiring DOM presence.
                        const readyPromise = typeof uiInstance.ready === 'function'
                            ? uiInstance.ready()
                            : Promise.resolve();
                        readyPromise.then(function() {
                            t.textArea.style.display = 'none';
                            t.wrapperNode.style.display = '';
                            let elementToAdd = uiInstance.getElement();
                            if (elementToAdd && elementToAdd.jquery) {
                                elementToAdd = elementToAdd[0];
                            }
                            t.wrapperNode.appendChild(elementToAdd);

                            // With jQuery, any embedded <script> elements will have been executed.
                            // But not with pure JavaScript. We have to pull them out and append them to
                            // the head to trigger their execution.
                            const scriptNodes = elementToAdd.querySelectorAll('script');
                            scriptNodes.forEach(oldScript => {
                                const newScript = document.createElement('script');
                                if (oldScript.src) {
                                    newScript.src = oldScript.src;
                                } else {
                                    newScript.textContent = oldScript.textContent;
                                }
                                document.head.appendChild(newScript);
                                document.head.removeChild(newScript);
                            });

                            if (typeof uiInstance.postInsert === 'function') {
                                uiInstance.postInsert(t.wrapperNode);
                            }

                            t.uiInstance = uiInstance;
                            t.loadFailed = false;
                            t.checkForResize();

                            let canDoFullScreen = t.isFullScreenEnable !== null ?
                                t.isFullScreenEnable : uiInstance.allowFullScreen?.();
                            if (canDoFullScreen) {
                                t.initFullScreenToggle(t.taId);
                            } else {
                                t.removeFullScreenButton(t.taId);
                            }
                            let uiInstancePrototype = Object.getPrototypeOf(uiInstance);
                            uiInstancePrototype.syncIntervalSecs = uiInstancePrototype.syncIntervalSecs || syncIntervalSecsBase;
                            t.startSyncTimer(uiInstance);
                            t.startSyncTimerForAnswerWrapper(t.textareaId);
                        }).catch(function() {
                            t.loadFailed = true;
                            t.wrapperNode.style.display = 'none';
                            t.textArea.style.display = '';
                            uiInstance.destroy();
                            t.uiInstance = null;
                            t.textArea.classList.add('uiloadfailed');
                            const loadFailDiv = document.createElement('div');
                            loadFailDiv.id = t.loadFailId;
                            loadFailDiv.className = 'uiloadfailed';
                            t.textArea.parentNode.insertBefore(loadFailDiv, t.textArea);
                            setLoadFailMessage(uiInstance.failMessage(), loadFailDiv);
                        }).finally(function() {
                            t.isLoading = false;
                        });
                    }
                });
        }
    };


    /**
     * Remove the fullscreen button from the wrapper editor.
     *
     * @param {String} fieldId The id of answer field.
     */
    InterfaceWrapper.prototype.removeFullScreenButton = function(fieldId) {
        const wrapperEditor = document.getElementById(`${fieldId}_wrapper`);
        const screenModeButton = wrapperEditor.parentNode.querySelector('.screen-mode-button');
        if (screenModeButton) {
            screenModeButton.remove();
        }
    };

    /**
     * Initialize elements and event listeners for the fullscreen mode.
     *
     * @param {String} fieldId The id of answer field.
     */
    InterfaceWrapper.prototype.initFullScreenToggle = function(fieldId) {
        const wrapperEditor = document.getElementById(`${fieldId}_wrapper`);
        const screenModeButton = wrapperEditor.parentNode.querySelector('.screen-mode-button');
        if (screenModeButton) {
            return;
        }

        Templates.renderForPromise('qtype_coderunner/screenmode_button', {}).then(({html}) => {
            const screenModeButton = Templates.appendNodeContents(wrapperEditor, html, '')[0];
            const fullscreenButton = screenModeButton.querySelector('.button-fullscreen');
            const exitFullscreenButton = screenModeButton.querySelector('.button-exit-fullscreen');

            // When load successfully, show the fullscreen button.
            fullscreenButton.classList.remove('d-none');

            // Add event listeners to the fullscreen/exit-fullscreen button.
            fullscreenButton.addEventListener('click', enterFullscreen.bind(this,
                fullscreenButton, exitFullscreenButton));
            exitFullscreenButton.addEventListener('click', exitFullscreen.bind(this));
        });

        /**
         * Make the editor fullscreen.
         *
         * @param {HTMLElement} fullscreenButton The fullscreen button.
         * @param {HTMLElement} exitFullscreenButton The exit fullscreen button.
         * @param {Event} e The click event.
         */
        function enterFullscreen(fullscreenButton, exitFullscreenButton, e) {
            let t = this;
            e.preventDefault();
            // The editor can stretch out.
            // So we need to save the original height and width of the editor before going fullscreen.
            t.wrapperHeight = t.wrapperNode.clientHeight;
            t.heightEditNode = t.hLast;
            t.widthEditNode = t.wLast;

            fullscreenButton.classList.add('d-none');
            // Append exit fullscreen button to the wrapper editor.
            // So that when in the fullscreen mode, the exit fullscreen button will be in the wrapper editor.
            wrapperEditor.append(exitFullscreenButton);

            // Handle fullscreen event.
            wrapperEditor.addEventListener('fullscreenchange', () => {
                if (document.fullscreenElement === null) {
                    // When exit fullscreen using ESC key or press exit fullscreen button.
                    // We need to reset the editor to the original size.
                    t.uiInstance.resize(t.widthEditNode, t.heightEditNode);

                    // We need to reset the wrapper height to the original height.
                    // In fullscreen mode, the wrapper height can change by stretching it out.
                    wrapperEditor.style.height = t.wrapperHeight + 'px';

                    // Add and remove the d-none class to show and hide the buttons.
                    exitFullscreenButton.classList.add('d-none');
                    fullscreenButton.classList.remove('d-none');
                } else {
                    exitFullscreenButton.classList.remove('d-none');
                }
            });
            wrapperEditor.requestFullscreen().catch(Notification.exception);
        }

        /**
         * Exit the fullscreen mode.
         *
         * @param {Event} e the click event.
         */
        function exitFullscreen(e) {
            let t = this;
            e.preventDefault();
            document.exitFullscreen();

            // Reset the editor to the original size before going fullscreen.
            wrapperEditor.style.height = t.wrapperHeight + 'px';
            t.uiInstance.resize(t.widthEditNode, t.heightEditNode);
        }
    };


    /**
     * Start a sync timer on the answer wrapper, if it's a real answer text area.
     * @param {string} textareaId The textareaId for the wrapper.
     * timer is to be set up.
     */
    InterfaceWrapper.prototype.startSyncTimerForAnswerWrapper = function(textareaId) {
        if (isAnAnswer(textareaId)){
            this.timer = setInterval(
                function () {
                    compare_with_last_checked(textareaId);
                    },
                250);  // Every 250 ms.
            }
        };




    /**
     * Start a sync timer on the given uiInstance, unless its time interval is 0.
     * @param {object} uiInstance The instance of the user interface object whose
     * timer is to be set up.
     */
    InterfaceWrapper.prototype.startSyncTimer = function(uiInstance) {
        const timeout = uiInstance.syncIntervalSecs();
        if (timeout) {
            this.uiInstance.timer = setInterval(function () {
                uiInstance.sync();
            }, timeout * 1000);
        } else {
            this.uiInstance.time = null;
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
            this.textArea.style.display = '';
            if (this.uiInstance.hasFocus()) {
                this.textArea.focus();
                this.textArea.selectionStart = this.textArea.value.length;
            }
            this.uiInstance.destroy();
            this.uiInstance = null;
            this.wrapperNode.style.display = 'none';
        }
        this.loadFailed = false;
        this.textArea.classList.remove('uiloadfailed'); // Just in case it failed before
        const elementToRemove = document.getElementById(this.loadFailId);
        if (elementToRemove) {
            elementToRemove.parentNode.removeChild(elementToRemove);
        }
    };

    /*
     * Re-enable the ui element (e.g. after alt-ctrl-M). This is
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
        if (this.uiInstance) {
            const h = this.wrapperNode.clientHeight;
            const w = this.wrapperNode.clientWidth;
            const maxWidth = this.wrapperNode.clientWidth;
            const hAdjusted = h - this.GUTTER;
            const wAdjusted = Math.min(maxWidth, w);
            if (hAdjusted != this.hLast || wAdjusted != this.wLast) {
                this.uiInstance.resize(wAdjusted,  hAdjusted);
                this.hLast = hAdjusted;
                this.wLast = wAdjusted;
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
