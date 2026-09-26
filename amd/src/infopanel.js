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
 * Adds a Hide/Show toggle to a CodeRunner question's info panel, letting a
 * student collapse the question-info sidebar to reclaim horizontal space.
 * The choice is persisted per question via qtype_coderunner/questionstate.
 *
 * This is independent of the layout switcher: it needs only the standard
 * Moodle .info panel, so it works whether or not the layout switcher is
 * present.
 *
 * @module     qtype_coderunner/infopanel
 * @copyright  The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['qtype_coderunner/questionstate'], function(state) {

    // Tracks, across every CodeRunner question on the page, which ones
    // currently have their info panel collapsed. #topofscroll only reclaims
    // its reserved margin while at least one panel is collapsed, and reverts
    // once every panel has been reopened (this set is empty again) - so the
    // page doesn't shrink back while another question still relies on the room.
    const collapsedInfoQuestions = new Set();

    /**
     * Boost (and derivatives) wrap page content in a #topofscroll element whose
     * side margins reserve space for the nav/block drawers. Collapsing the info
     * panel frees horizontal room, so mirror that onto #topofscroll if present.
     * A silent no-op on themes that don't use this markup.
     * @param {boolean} expanded True while at least one info panel is collapsed.
     * @returns {void}
     */
    function applyPageExpand(expanded) {
        const topofscroll = document.querySelector('#topofscroll');
        if (topofscroll) {
            topofscroll.classList.toggle('topofscroll-collapsed', expanded);
        }
    }

    /**
     * Inject the info-panel toggle button into a question and wire up its
     * handler.
     * @param {string} questionId The id of the outer .que.coderunner element.
     * @param {string} storageKey The stable (per-question) persistence key.
     * @returns {void}
     */
    function init(questionId, storageKey) {
        const que = document.getElementById(questionId);
        if (!que) {
            return;
        }
        const infoDiv = que.querySelector('.info');
        if (!infoDiv) {
            return;
        }

        const btn = document.createElement('button');
        btn.className = 'info-toggle-btn';
        btn.type = 'button';
        infoDiv.prepend(btn);

        const applyInfoCollapse = collapsed => {
            que.classList.toggle('info-collapsed', collapsed);
            if (collapsed) {
                collapsedInfoQuestions.add(questionId);
            } else {
                collapsedInfoQuestions.delete(questionId);
            }
            applyPageExpand(collapsedInfoQuestions.size > 0);
            btn.innerHTML = collapsed ? 'Show' : 'Hide';
            btn.title = collapsed ? 'Show question info' : 'Hide question info';
            btn.ariaLabel = btn.title;
            state.saveState(storageKey, {infoCollapsed: collapsed});
        };

        btn.addEventListener('click', () => {
            applyInfoCollapse(!que.classList.contains('info-collapsed'));
        });

        applyInfoCollapse(!!state.getState(storageKey).infoCollapsed);
    }

    return {init};
});
