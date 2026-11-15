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
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http:www.gnu.org/licenses/>.
 */

/**
 * Keep the author form's answer boxes showing what the student will see.
 *
 * A UI plugin reads the text it is to lay out from data attributes on the
 * textarea it manages: `data-globalextra`, `data-prototypeextra` and
 * `data-test0`. On the attempt page the renderer sets all three from the
 * saved question (see renderer.php). On the author form two of them can be
 * set server-side, because they are settings and so resolve through the
 * chain - but `data-test0` is the first test case's code, which is not a
 * setting at all: it is a row of question_coderunner_tests, and on this form
 * it is a repeated element the author is in the middle of editing. There is
 * nothing in the resolved settings to read it from, and a value rendered
 * before the author touched the field would be wrong as soon as they did.
 *
 * So it is read here, from the field, which is what CodeRunner 5 did in
 * authorform.js until that file was removed with the form it drove. Without
 * it a gap-filler question whose `ui_source` is `test0` has no gaps in the
 * editor at all: the UI reads an attribute that is not there, and fails.
 *
 * Read when a UI is attached, and not tracked as the author types, which is
 * also what CodeRunner 5 did: its `setUis()` refreshed these attributes, and
 * ran on page load, on a change of UI plugin, of question type or of Ace
 * language, and when the preload section was expanded - never on an edit to
 * the fields themselves. So an author who changes test 0 still has to save
 * and continue editing before the gaps follow, as they always did. Reloading
 * the box on every edit would be a change of behaviour rather than a repair,
 * and it would discard whatever the author had typed into the gaps.
 *
 * The same refresh answers a second loss. The plugin a box uses is chosen
 * server-side when the page is built, so changing the *Student answer* menu
 * did nothing until the question was saved, and an author could not see what
 * they had just chosen.
 *
 * @module qtype_coderunner/answerboxui
 * @copyright  Richard Lobb, 2026, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['qtype_coderunner/userinterfacewrapper'], function(ui) {
    'use strict';

    /** The data attribute each UI plugin reads, and the field it comes from. */
    const SOURCES = {
        'data-globalextra': 'id_globalextra',
        'data-prototypeextra': 'id_prototypeextra',
        'data-test0': 'id_testcode_0',
    };

    /** The boxes a UI plugin runs on. Both show the author the student's view. */
    const BOXES = ['id_answer', 'id_answerpreload'];

    /**
     * The current text of a field, or the empty string if the form has none.
     *
     * Reading the textarea is enough even for a field Ace manages: Ace writes
     * every change straight back to it (see ui_ace.js), which is also what
     * makes a `change` listener on it fire when the author leaves the editor.
     *
     * @param {string} id the field's element id.
     * @return {string} its value.
     */
    function liveValue(id) {
        const field = document.getElementById(id);
        return field ? field.value : '';
    }

    /**
     * The UI plugin the answer boxes should be running.
     *
     * Mirrors answer_box_ui_plugin() in edit_coderunner_form.php, including
     * its one special case: the HTML UI may declare `enable_in_editor` false,
     * which a UI written in Twig needs, since the form holds the definition
     * unexpanded and the UI would be handed the Twig rather than the HTML.
     *
     * @param {HTMLSelectElement} menu the Student answer menu.
     * @param {HTMLTextAreaElement} answer the answer box, which carries the params.
     * @return {string} the plugin's name, or the empty string for no UI.
     */
    function wantedPlugin(menu, answer) {
        const name = (menu.value || '').toLowerCase();
        if (name === '' || name === 'none') {
            return '';
        }
        if (name === 'html') {
            try {
                const params = JSON.parse(answer.getAttribute('data-params') || '{}');
                if (params.enable_in_editor === false) {
                    return '';
                }
            } catch (e) {
                // Unparseable UI parameters are the form's business to report on
                // save, not this module's to interrupt the author over.
                return name;
            }
        }
        return name;
    }

    /**
     * Copies the live field values onto a box, for the UI to read.
     *
     * @param {HTMLTextAreaElement} box the textarea a UI plugin manages.
     */
    function refreshSources(box) {
        for (const attribute in SOURCES) {
            box.setAttribute(attribute, liveValue(SOURCES[attribute]));
        }
    }

    /**
     * Brings both answer boxes up to date with the form.
     *
     * @param {string} uiname the plugin the boxes should run, or '' for none.
     * @param {boolean} create whether to start a UI on a box that has none,
     *      which is wanted when the author changes the menu but not on first
     *      load, where the form has already asked for whatever it chose.
     */
    function refresh(uiname, create) {
        BOXES.forEach(function(id) {
            const box = document.getElementById(id);
            if (!box) {
                return;
            }
            const wrapper = box.current_ui_wrapper;
            if (uiname === '') {
                // Nothing will read the source attributes, so they are not
                // set: one of them is a whole UI definition - 86 KB of it for
                // the RoboTurtle type - and the form deliberately leaves them
                // off a box whose UI will not run. See answer_box_ui_data().
                if (wrapper) {
                    wrapper.stop();
                }
                return;
            }
            refreshSources(box);

            if (!wrapper) {
                // Nothing is running yet. Either the form asked for a UI and
                // it has not arrived - in which case the attributes just set
                // are there for it to read - or there is none to reload.
                if (create) {
                    ui.newUiWrapper(uiname, id);
                }
                return;
            }
            let params = {};
            try {
                params = JSON.parse(box.getAttribute('data-params') || '{}');
            } catch (e) {
                params = {};
            }
            wrapper.loadUi(uiname, params);
        });
    }

    return {
        /**
         * Follows the menu and the fields the UI plugins read.
         */
        init: function() {
            const menu = document.getElementById('id_uiplugin');
            const answer = document.getElementById('id_answer');
            if (!menu || !answer) {
                return;
            }

            const reload = function(create) {
                return function() {
                    refresh(wantedPlugin(menu, answer), create);
                };
            };

            // Once now, so that a box whose UI reads `data-test0` has it:
            // the server cannot supply that one. No UI is started here, since
            // the form has already asked for the one it chose.
            reload(false)();

            menu.addEventListener('change', reload(true));
        }
    };
});
