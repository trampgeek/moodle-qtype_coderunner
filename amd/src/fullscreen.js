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
 * JavaScript module for managing fullscreen/exit fullscreen mode in the editor.
 * This module provides functions to enable fullscreen mode and exit fullscreen mode for an editor.
 *
 * @module qtype_coderunner/fullscreen
 * @copyright 2023 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';

/**
 * EditorFullscreenToggle class is the base class for all the fullscreen toggle classes.
 * It provides the basic functions for all the fullscreen toggle classes.
 */
class EditorFullscreenToggle {

    /**
     * Represents the question div element.
     *
     * @type {HTMLElement}
     */
    questionDiv;

    /**
     * Represents the full screen button.
     *
     * @type {HTMLElement}
     */
    fullScreenButton;

    /**
     * Represents the exit fullscreen button.
     *
     * @type {HTMLElement}
     */
    exitFullscreenButton;

    constructor(questionId) {
        this.questionDiv = document.getElementById(questionId);
    }

    /**
     * Each ui plugin has different editor element wrapper.
     * This function designed to get the editor element wrapper for the fullscreen zone.
     */
    getWrapperElement() {}

    /**
     * Save original height of the editor.
     */
    saveCurrentEditorSize() {}

    /**
     * Reset the editor to the original size.
     */
    resetEditorToSavedSize() {}

    /**
     * Initialize elements for the fullscreen toggle.
     *
     * @param {String} fieldId The id of answer field.
     */
    initFullScreenToggle(fieldId) {
        // Initialize wrapper editor.
        const wrapperEditor = this.getWrapperElement(fieldId);
        this.fullScreenButton = this.questionDiv.querySelector('.button-fullscreen');
        this.exitFullscreenButton = this.questionDiv.querySelector('.button-exit-fullscreen');

        // When load successfully, show the screen mode element and the fullscreen button.
        this.fullScreenButton.classList.remove('d-none');

        // Attach an event to the fullscreen/exit-fullscreen button.
        this.fullScreenButton.addEventListener('click', this.enterFullscreen.bind(this, wrapperEditor));
        this.exitFullscreenButton.addEventListener('click', this.exitFullscreen.bind(this));
    }

    /**
     * Make the editor fullscreen.
     *
     * @param {HTMLElement} wrapperEditor The wrapper editor element.
     * @param {Event} e The click event.
     */
    enterFullscreen(wrapperEditor, e) {
        e.preventDefault();
        this.saveCurrentEditorSize(wrapperEditor);
        this.fullScreenButton.classList.add('d-none');
        // Append exit fullscreen button to the wrapper editor.
        // So that when in the fullscreen mode, the exit fullscreen button will be in the wrapper editor.
        wrapperEditor.append(this.exitFullscreenButton);
        // Handle fullscreen event.
        wrapperEditor.addEventListener('fullscreenchange', () => {
            // When exit fullscreen.
            if (document.fullscreenElement === null) {
                this.resetEditorToSavedSize(wrapperEditor);
                this.exitFullscreenButton.classList.add('d-none');
                this.fullScreenButton.classList.remove('d-none');
            } else {
                this.exitFullscreenButton.classList.remove('d-none');
            }
        });

        wrapperEditor.requestFullscreen().catch(Notification.exception);
    }

    /**
     * Exit fullscreen mode.
     *
     * @param {Event} e The click event.
     */
    exitFullscreen(e) {
        e.preventDefault();
        document.exitFullscreen();
    }
}

/**
 * AceEditorFullscreenToggle is handle the fullscreen mode for the Ace editor.
 */
class AceEditorFullscreenToggle extends EditorFullscreenToggle {

    /**
     * Represents the size configuration of the Ace editor.
     *
     * @type {Object}
     */
    editorSize = {};

    constructor(questionId) {
        super(questionId);
    }
    getWrapperElement(fieldId) {
        return document.getElementById(`${fieldId}_wrapper`);
    }

    saveCurrentEditorSize(wrapperEditor) {
        this.editorSize.wrapper = wrapperEditor.style.minHeight;
        this.editorSize.heightWraper = wrapperEditor.style.height;
        this.editorSize.editor = this.questionDiv.querySelector('.ace_editor').style.height;
        this.editorSize.content = this.questionDiv.querySelector('.ace_content').style.height;
    }

    resetEditorToSavedSize(wrapperEditor) {
        wrapperEditor.style.minHeight = this.editorSize.wrapper;
        wrapperEditor.style.height = this.editorSize.heightWraper;
        this.questionDiv.querySelector('.ace_editor').style.height = this.editorSize.editor;
        this.questionDiv.querySelector('.ace_content').style.height = this.editorSize.content;
    }
}

/**
 * Initialize the full screen.
 *
 * @param {String} questionId id of the outer question div.
 * @param {String} fieldId The id of answer field.
 * @param {String} uiPluginType The input UI type.
 */
export const init = (questionId, fieldId, uiPluginType) => {
    switch (uiPluginType) {
        case 'ace':
            new AceEditorFullscreenToggle(questionId).initFullScreenToggle(fieldId);
            break;
        case 'ace_gapfiller':
            new AceEditorFullscreenToggle(questionId).initFullScreenToggle(fieldId);
            break;
        default:
            return;
    }
};
