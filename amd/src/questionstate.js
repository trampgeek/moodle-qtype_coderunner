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
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared per-question UI-state persistence for CodeRunner question rendering.
 *
 * A small object is stored per question (keyed by the stable question id) in
 * sessionStorage, so independent rendering enhancements - the info-panel
 * toggle, the layout switcher, etc. - can each persist their own fields
 * without duplicating the storage plumbing or clobbering each other's data.
 *
 * @module     qtype_coderunner/questionstate
 * @copyright  The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    const STORAGE_KEY = 'coderunner_layout';

    /**
     * Read the whole state map from sessionStorage.
     * @returns {object} The map, or {} if unset, unavailable or corrupt.
     */
    function getStore() {
        try {
            const text = sessionStorage.getItem(STORAGE_KEY);
            return text === null ? {} : (JSON.parse(text) || {});
        } catch (e) {
            return {};
        }
    }

    /**
     * Persist the whole state map to sessionStorage (best-effort).
     * @param {object} obj The map to store.
     * @returns {void}
     */
    function setStore(obj) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(obj));
        } catch (e) {
            // sessionStorage may be unavailable; persistence is best-effort.
        }
    }

    /**
     * Read the remembered state object for a single question.
     * @param {string} key The stable question id.
     * @returns {object} The stored state, or {} if none is remembered.
     */
    function getState(key) {
        let entry = getStore()[key];
        if (typeof entry === 'string') {
            // Legacy format: the whole entry used to be a bare layout string.
            entry = {layout: entry};
        }
        return (entry && typeof entry === 'object') ? entry : {};
    }

    /**
     * Merge a partial state patch into what's remembered for a question.
     * @param {string} key The stable question id.
     * @param {object} patch Fields to merge into the stored state.
     * @returns {void}
     */
    function saveState(key, patch) {
        const obj = getStore();
        obj[key] = Object.assign({}, getState(key), patch);
        setStore(obj);
    }

    return {getState, saveState};
});
