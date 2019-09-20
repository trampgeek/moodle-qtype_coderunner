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
 * JavaScript for filling in the question text with the contents of one
 * of the question's support files. Intended primarily for program contest
 * problems, where the support file is a single domjudge or ICPC problem zip,
 * with the problem spec within it.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2019, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define(['jquery'], function($) {

    function loadQuestionText(questionId, divId) {
        $.getJSON(M.cfg.wwwroot + '/question/type/coderunner/problemspec.php',
            {
                questionid: questionId,
                sesskey: M.cfg.sesskey
            },
            function (response) {
                if (response.filecontents) {
                    window.alert("Yay");
                    $('#' + divId).html(response.filecontents);
                }
                else {
                    window.alert("Bad response object");
                }

            }
        ).fail(function () {
            // AJAX failed. We're dead, Fred.
            window.alert('Failed to load problem spec');
            $('#' + divId).html("<h2>Not tonight, Josephine</h2>");
        });
    }

    return {
        loadQuestionText: loadQuestionText
    };
});
