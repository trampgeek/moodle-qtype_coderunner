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
 * Keep an inherited code field uneditable while its Inherit box is ticked.
 *
 * Every other control on the settings form is greyed by core's `disabledIf`,
 * which sets the `disabled` attribute on the input. That is enough until Ace
 * arrives: Ace hides the textarea, puts an editor of its own in its place, and
 * reads the textarea's `readonly` attribute once, when it is constructed. So a
 * disabled template was still fully editable, and because a disabled field is
 * not submitted, an edit made to one was dropped without a word on save.
 *
 * The initial state comes from the markup — the form adds `readonly` to a
 * field it is rendering as inherited, which Ace honours as it starts up — and
 * this module follows the checkbox from then on.
 *
 * @module qtype_coderunner/inheritedcode
 * @copyright  Richard Lobb, 2026, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    /**
     * Lock or unlock one code field.
     *
     * The class goes on the textarea's parent rather than on Ace's wrapper
     * node, because the wrapper is inserted asynchronously and may not exist
     * yet; it is inserted as the textarea's next sibling, so a rule written
     * for the parent catches it whenever it lands.
     *
     * @param {HTMLTextAreaElement} textarea the field's textarea.
     * @param {boolean} locked true if the field is inherited.
     */
    const apply = function(textarea, locked) {
        textarea.readOnly = locked;

        const wrapper = textarea.current_ui_wrapper;
        const editor = wrapper && wrapper.uiInstance && wrapper.uiInstance.editor;
        if (editor) {
            editor.setReadOnly(locked);
        }
        if (textarea.parentElement) {
            textarea.parentElement.classList.toggle('coderunner-inherited', locked);
        }
    };

    return {
        /**
         * Follow each field's Inherit box.
         *
         * @param {string[]} fieldnames the inheritable code fields on the form.
         */
        init: function(fieldnames) {
            fieldnames.forEach(function(name) {
                const textarea = document.getElementById('id_' + name);
                const box = document.getElementById('id_inherit_' + name);
                if (!textarea || !box) {
                    return;
                }

                // A form the author may only read is frozen by formslib, which
                // renders the checkbox as a real input that is disabled -- and
                // unchecked, where the layer overrides the field. Reading that
                // as "not inherited, so unlock it" is how this module came to
                // undo the freeze and hand back an editable Ace editor on a
                // page nobody could save. A disabled checkbox is not a
                // statement about inheritance: it says the author has no say,
                // so the markup's own state stands.
                //
                // The textarea cannot be tested instead: `disabledIf` disables
                // it on an ordinary form whenever the box is ticked.
                if (box.disabled) {
                    return;
                }

                apply(textarea, box.checked);
                box.addEventListener('change', function() {
                    apply(textarea, box.checked);
                });
            });
        }
    };
});
