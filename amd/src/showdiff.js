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
 * This AMD module provides the functionality for the "Show differences"
 * button that is shown in the student's result page if their answer
 * isn't right and an "exact-match" (or near equivalent) grader is being used.
 *
 * @module qtype_coderunner/showdiff
 * @copyright  Richard Lobb, 2016, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define(['jquery'], function($) {

    var NLCHAR = '\u21A9';  // Unicode "leftwards arrow with hook" to show newlines.

    /**
     *  Given two lists of items, items1 and item2, return the length matrix
     * M defined as M[i][j] = max subsequence length of the two item lists
     * items1[0:i], items2[0:j]
     * @param {array} items1 The first list of items.
     * @param {array} items2 The second list of items.
     * @return {array} The length matrix.
     */
    function lcsLengths(items1, items2) {

        var n1 = items1.length,
            n2 = items2.length,
            lengths, i, j,
            has_fill = typeof [1].fill === 'function';

        lengths = [];

        for (i = 0; i <= n1; i += 1) {
            lengths[i] = new Array(n2 + 1);
            if (has_fill) {
                   lengths[i].fill(0);
            } else {
                // Bloody IE.
                for (j = 0; j < n2 + 1; j++) {
                    lengths[i][j] = 0;
                }
            }
        }
        for (i = 0; i < n1; i += 1) {
            for (j = 0; j < n2; j += 1) {
                if (items1[i] == items2[j]) {
                    lengths[i + 1][j + 1] = 1 + lengths[i][j];
                } else {
                    lengths[i + 1][j + 1] = Math.max(lengths[i][j + 1], lengths[i + 1][j]);
                }
            }
        }
        return lengths;
    }

    /**
     * Given two lists of items, items1 and item2, return the longest common
     * subsequence.
     * @param {array} items1 The first list of items.
     * @param {array} items2 The second list of items.
     * @return {array} The longest common subsequence.
     */
    function lcss(items1, items2) {

        var M, i, j, n, result, length;
        M = lcsLengths(items1, items2);
        length = M[items1.length][items2.length];
        result = [];
        i = items1.length;
        j = items2.length;
        n = length - 1;
        while (n >= 0) {
            if (items1[i - 1] == items2[j - 1]) {
                result[n] = items1[i - 1];
                n -= 1;
                i -= 1;
                j -= 1;
            } else if (M[i - 1][j] == M[i][j]) {
                i -= 1;
            } else {
                j -= 1;
            }
        }
        return result;
    }

    /**
     * Process the given token list and a subsequence of it, concatenating
     * tokens and wrapping all items not in the subsequence with
     * del tags (or whatever strings are specified by startDel, endDel).
     * @param {array} tokens A list of tokens in the original string.
     * @param {array} subSeq A subsequence of the tokens array.
     * @param {string} startDel An optional string that denotes the start of
     * a sequence of tokens to be deleted. Default '<del>'
     * @param {string} endDel An optional string to mark the end of a sequence
     * of deleted tokens. Default '</del>'.
     * @return {string} The concatenated sequence of tokens with start and
     * end delete tokens inserted to mark where the tokens from the first
     * parameter are not present in the second.
     */
    function insertDels(tokens, subSeq, startDel, endDel) {
        var html = "",
            deleting = false,
            i,
            ssi = 0;
        if (startDel === undefined) {
            startDel = '<del>';
        }
        if (endDel === undefined) {
            endDel = '</del>';
        }
        for (i = 0; i < tokens.length; i += 1) {
            if (ssi < subSeq.length && tokens[i] == subSeq[ssi]) {
                if (deleting) {
                    html += endDel;
                    deleting = false;
                }
                ssi += 1;
            } else {
                if (!deleting) {
                    html += startDel;
                    deleting = true;
                }
            }

            html += tokens[i];
        }
        if (deleting) {
            html += endDel;
        }
        return html;
    }

    /**
     * @param {string} elem An HTML element
     * @return {string} The HTML element type (i.e. its tag name) in lower case
     */
    function elType(elem) {
        return elem.tagName.toLowerCase();
    }

    /**
     * Return the sequence of tokens from the given HTML element.
     * A token is either a single character or an HTML entity (&.*;)
     * Extra 'leftward-arrow-with-hook' characters (\u21A9) are added
     * at the ends of lines.
     * @param {string} element The HTML element whose contents are to be tokenised.
     * @return {array} The list of tokens extracted from the element.
     */
    function getTokens(element) {
        var isPre = elType(element) === 'pre',
            text = element.innerHTML,
            seq,
            i = 0;

        /**
         * Extract and return the next token starting at text[i]. Update i.
         * Precondition: i < text.length.
         */
        function nextToken() {
            var token, match;
            if (text[i] != '&') {
                token = text[i];
                i = i + 1;
            } else {
                match = text.substring(i, text.length).match(/(^&[a-zA-Z]+;)|(^&#[0-9]+;)|(^&#[xX][0-9a-fA-F]+;)/);
                if (match === null) {
                    token = text[i];
                    i = i + 1;
                } else {
                    token = match[0];
                    i = i + token.length;
                }
            }
            return token;
        }

        if (isPre) {
            text = text.replace(/\n/g, NLCHAR + '\n');
        }
        text = text.replace(/(<br ?.*?>)/g, NLCHAR + '$1');
        seq = [];
        i = 0;
        while (i < text.length) {
            seq.push(nextToken());
        }

        return seq;
    }

    /**
     *  Given (references to) two HTML elements, extract the innerHTML
     * of both, find the longest common subsequence of chars and wrap text not
     * in that subsequence in del elements.
     * <br> elements within the innerHTML are preceded by a
     * Unicode "leftwards arrow with hook" ('\u21A9') so that line break changes
     * can be highlighted.
     * @param {string} firstEl The first HTML element to be processed.
     * @param {string} secondEl The second HTML element to be processed.
     */
    function showDifferences(firstEl, secondEl) {
        var openDelTag = '<del>',
            closeDelTag = '</del>',
            seq1,
            seq2,
            css;

        seq1 = getTokens(firstEl);
        seq2 = getTokens(secondEl);
        css = lcss(seq1, seq2);
        firstEl.innerHTML = insertDels(seq1, css, openDelTag, closeDelTag);
        secondEl.innerHTML = insertDels(seq2, css, openDelTag, closeDelTag);
    }

    /**
     *  Given (references to) two DOM elements, delete all <del ...> and </del>
     * tags from the innerHTML of both. Also remove the "leftwards arrows with
     * hooks".
     * @param {string} firstEl The first HTML element to be processed.
     * @param {string} secondEl The second HTML element to be processed.
     */
    function hideDifferences(firstEl, secondEl) {
        var replPat = new RegExp('(</?del[^>]*>)|(' + NLCHAR + ')', 'g');
        firstEl.innerHTML = firstEl.innerHTML.replace(replPat, '');
        secondEl.innerHTML = secondEl.innerHTML.replace(replPat, '');
    }

    /**
     * Now the API for applying diffs to rows in a CodeRunner
     * results table. Defines a class with methods initDiffButton and
     * processAllRows.
     * @param {array} tableRows The list of rows from the CodeRunner results table.
     * @param {int} gotCol The column number of the 'Got' column in the table.
     * @param {int} expectedCol The column number of the 'Expected' column.
     * @param {function} f The function to apply to the (expected, got) pair.
     */
    function processAllRows(tableRows, gotCol, expectedCol, f) {
        var row,
            cells,
            expected,
            got;

        for (var i = 0; i < tableRows.length; i++) {
            row = tableRows[i];
            cells = row.getElementsByTagName('td');
            expected = cells[expectedCol].children[0];
            got = cells[gotCol].children[0];
            f(expected, got);
        }
    }

    /**
     * Initialise the Show Differences button.
     * @param {string} buttonId The ID of the Show Differences button.
     * @param {string} showValue The text in the button initially.
     * @param {string} hideValue The text in the button when differences are showing.
     * @param {string} expectedString The column header denoting the 'Expected' column.
     * @param {string} gotString The column header denoting the 'Got' column.
     */
    function initDiffButton(buttonId, showValue, hideValue, expectedString, gotString) {
        var diffButton = $('[id="' + buttonId + '"]'),
            table,
            tableRows,
            thEls,
            columnCount=0,
            gotCol=-1,
            expectedCol=-1;

        table = diffButton.closest('div.coderunner-test-results');
        thEls = table.find('thead tr').children();
        tableRows = table.find('tbody tr');

        // Find 'Expected' and 'Got' columns.
        thEls.each(function() {
            if ($(this).html() === gotString) {
                gotCol = columnCount;
            } else if ($(this).html() === expectedString) {
                expectedCol = columnCount;
            }
            columnCount += 1;
        });

        if (gotCol !== -1 && expectedCol !== -1) {
            diffButton.on("click", function() {
                if (diffButton.prop('value') === showValue) {
                    processAllRows(tableRows.toArray(), gotCol, expectedCol, showDifferences);
                    diffButton.prop('value', hideValue);
                } else {
                    processAllRows(tableRows.toArray(), gotCol, expectedCol, hideDifferences);
                    diffButton.prop('value', showValue);
                }
            });
        } else {
            diffButton.enabled = false;
            diffButton.hide();
        }
    }

    return { "initDiffButton": initDiffButton };
});
