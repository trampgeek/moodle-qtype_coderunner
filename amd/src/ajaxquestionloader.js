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
 * @module qtype_coderunner/ajaxquestionloader
 * @copyright  Richard Lobb, 2019; Paul McKeown 2023. The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// jshint esversion:6

/**
 * Append to the question text div in the question a data-URL containing
 * the contents of the question specification file (usu. a pdf).
 * @param {int} qid The question ID in the database.
 * @param {string} divId The ID of the question text <div> element.
 * @param {string} questionFilename The name of the problem spec file within
 * the problem zip file.
 */
export function loadQuestionText(qid, divId, questionFilename) {
    const questionTextDiv = document.getElementById(divId),
        error = 'Failed to load problem spec',
        errorElem = document.createTextNode(error),
        url = M.cfg.wwwroot + '/question/type/coderunner/problemspec.php?questionid=' + qid +
        '&sesskey='+M.cfg.sesskey+'&filename='+questionFilename;

    fetch(url)
        .then((response) => {
            if (response.ok) {
                return response.json();}
            else {
                throw new Error(error);  // fetch failed for some reason
            }
        })
        .then(function (data) {
            const anchorElem = document.createElement('a');
            if (data.filecontentsb64) {
                anchorElem.href = 'data:application/pdf;base64,'+data.filecontentsb64;
                anchorElem.text = 'Problem Spec';
                anchorElem.download = "problem_spec";  // suggested filename for download
                questionTextDiv.appendChild(anchorElem);
            } else {
                throw new Error(error);  // didn't get expected contents
            }
        })
        .catch(() =>  {questionTextDiv.appendChild(errorElem);});
}
