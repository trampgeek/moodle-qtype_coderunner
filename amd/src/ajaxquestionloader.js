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


define(['jquery'], function ($) {
    var pdfjsLib = window['pdfjs-dist/build/pdf'];
    var pdfDoc = null,
            pageNumSpan,
            pageRendering = false,
            pageNumPending = null,
            scale = 1.2,
            canvas,
            next,
            previous,
            pageNum = 1,
            numPagesSpan,
            ctx,
            pageRendering;

    pdfjsLib.GlobalWorkerOptions.workerSrc = '//mozilla.github.io/pdf.js/build/pdf.worker.js';

    /**
     * Get page info from document, resize canvas accordingly, and render page.
     * @param num Page number.
     */
    function renderPage(num) {
        pageRendering = true;
        // Using promise to fetch the page
        pdfDoc.getPage(num).then(function (page) {
            var viewport = page.getViewport({scale: scale});
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            // Render PDF page into canvas context
            var renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            var renderTask = page.render(renderContext);

            // Wait for rendering to finish
            renderTask.promise.then(function () {
                pageRendering = false;
                pageNumSpan.html('' + num);  // Update page counter
                if (num == 1) {
                    previous.attr('disabled', true);
                } else {
                    previous.removeAttr('disabled');
                }
                if (num == pdfDoc.numPages) {
                    next.attr('disabled', true);
                } else {
                    next.removeAttr('disabled');
                }
                if (pageNumPending !== null) {
                    // New page rendering is pending
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
        });
    }

    /**
     * If another page rendering in progress, waits until the rendering is
     * finised. Otherwise, executes rendering immediately.
     */
    function queueRenderPage(num) {
        if (pageRendering) {
            pageNumPending = num;
        } else {
            renderPage(num);
        }
    }

    /**
     * Displays previous page.
     */
    function onPrevPage() {
        if (pageNum <= 1) {
            return;
        }
        pageNum--;
        queueRenderPage(pageNum);
    }

    /**
     * Displays next page.
     */
    function onNextPage() {
        if (pageNum >= pdfDoc.numPages) {
            return;
        }
        pageNum++;
        queueRenderPage(pageNum);
    }

    function loadQuestionText(qid, divId, questionFilename) {
        var questionTextDiv = $('#' + divId),
            qDiv;
        if (questionTextDiv.length != 1) {
            window.alert("Can't load question text. Expected div not found");
            return;
        }
        qDiv = '<div>\n' +
            '<button type="button" class="qtype_coderunner_previous">Previous</button>\n' +
            '<button type="button" class="qtype_coderunner_next">Next</button>' +
            '&nbsp; &nbsp;' +
            '<span>Page: <span class="qtype_coderunner_pagenum"></span> / <span ' +
            'class="qtype_coderunner_numpages"></span></span>\n' +
            '</div>\n' +
            '<canvas id="qtype_coderunner_problemspec_' + qid + '"></canvas>\n' +
            '</div>\n';
        questionTextDiv.append(qDiv);
        canvas = document.getElementById('qtype_coderunner_problemspec_' + qid);
        next = $('#' + divId + ' button.qtype_coderunner_next');
        previous = $('#' + divId + ' button.qtype_coderunner_previous');
        pageNumSpan = $('#' + divId + ' span.qtype_coderunner_pagenum');
        numPagesSpan = $('#' + divId + ' span.qtype_coderunner_numpages');
        ctx = canvas.getContext('2d');
        next.click(onNextPage);
        previous.click(onPrevPage);
        $.getJSON(M.cfg.wwwroot + '/question/type/coderunner/problemspec.php',
                {
                    questionid: qid,
                    sesskey: M.cfg.sesskey,
                    filename: questionFilename
                },
                function (response) {
                    var pdfcontents;
                    var loadingTask;
                    if (response == "FILE NOT FOUND") {
                        window.alert("Problem spec file not found");
                    } else if (response.filecontentsb64) {
                        pdfcontents = atob(response.filecontentsb64);
                        loadingTask = pdfjsLib.getDocument({data: pdfcontents});
                        loadingTask.promise.then(function (pdf) {
                            pdfDoc = pdf;
                            numPagesSpan.html('' + pdf.numPages);
                            renderPage(1);
                        });

                    } else {
                        window.alert("Bad response object");
                    }

                }
        ).fail(function () {
            // AJAX failed. We're dead, Fred.
            window.alert('Failed to load problem spec');
        });
    }

    return {
        loadQuestionText: loadQuestionText
    };
});
