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
// GNU General Public License for more util.details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Implementation of the table_ui user interface plugin. For overall details
 * of the UI plugin architecture, see userinterfacewrapper.js.
 *
 * This plugin replaces the usual textarea answer element with a div
 * containing an HTML table. The number of columns, and
 * the initial number of rows are specified by required UI parameters
 * num_columns and num_rows respectively.
 * Optional additional UI parameters are:
 *   1. column_headers: a list of strings that can be used to provide a
 *      fixed header row at the top.
 *   2. row_labels: a list of strings that can be used to provide a
 *      fixed row label column at the left.
 *   3. dynamic_rows, which, if true, allows the user to add rows.
 *   4. locked_cells: a list of [row, column] pairs, being the coordinates
 *      of table cells that cannot be changed by the user. row and column numbers
 *      are zero origin and do not include the header row or the row labels.
 *   5. width_percents: a list of the percentages of the width occupied
 *      by each column. This list must include a value for the row labels, if present.
 *
 * Individual cells are textareas except when the number of rows per cell is set to
 * 1, in which case input elements are used instead.
 *
 * The serialisation of the table, which is what is essentially copied back
 * into the original answer box textarea for submissions as the answer, is a JSON array. Each
 * element in the array is itself an array containing the values of one row
 * of the table. Empty cells are empty strings. The table header row and row
 * label columns are not provided in the serialisation.
 *
 * To preload the table with data, simply set the answer_preload of the question
 * to a json array of row values (each itself an array). If the number of rows
 * in the preload exceeds the number set by num_rows, extra rows are
 * added. If the number is less than num_rows, or if there is no
 * answer preload, undefined rows are simply left blank.
 *
 * As a special case of the serialisation, if all cells in the serialisation
 * are empty strings, the serialisation is itself the empty string.
 *
 * @module qtype_coderunner/ui_table
 * @copyright  Richard Lobb, 2018, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    /**
     * Constructor for the TableUI object.
     * @param {string} textareaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea.
     * @param {int} height The height in pixels of the textarea.
     * @param {object} uiParams The UI parameter object.
     */
    function TableUi(textareaId, width, height, uiParams) {
        this.textArea = $(document.getElementById(textareaId));
        this.readOnly = this.textArea.prop('readonly');
        this.tableDiv = null;
        this.uiParams = uiParams;
        if (!uiParams.num_columns ||
            !uiParams.num_rows) {
            this.fail = true;
            this.failString = 'table_ui_missingparams';
            return;  // We're dead, fred.
        }

        this.fail = false;
        this.lockedCells = uiParams.locked_cells || [];
        this.hasHeader = uiParams.column_headers && uiParams.column_headers.length > 0 ? true : false;
        this.hasRowLabels = uiParams.row_labels && uiParams.row_labels.length > 0 ? true : false;
        this.numDataColumns = uiParams.num_columns;
        this.rowsPerCell = uiParams.lines_per_cell || 2;
        this.totNumColumns = this.numDataColumns + (this.hasRowLabels ? 1 : 0);
        this.columnWidths = this.computeColumnWidths();
        this.reload();
    }

    // Return an array of the percentage widths required for each of the
    // totNumColumns columns.
    TableUi.prototype.computeColumnWidths = function() {
        var defaultWidth = Math.trunc(100 / this.totNumColumns),
            columnWidths = [];
        if (this.uiParams.column_width_percents && this.uiParams.column_width_percents.length > 0) {
            return this.uiParams.column_width_percents;
        } else if (Array.prototype.fill) { // Anything except bloody IE.
            return new Array(this.totNumColumns).fill(defaultWidth);
        } else { // IE. What else?
            for (var i = 0; i < this.totNumColumns; i++) {
                columnWidths.push(defaultWidth);
            }
            return columnWidths;
        }
    };

    // Return True if the cell at the given row and column is locked.
    // The given row and column numbers exclude column headers and row labels.
    TableUi.prototype.isLockedCell = function(row, col) {
        for (var i = 0; i < this.lockedCells.length; i++) {
            if (this.lockedCells[i][0] == row && this.lockedCells[i][1] == col) {
                return true;
            }
        }
        return false;
    };

    TableUi.prototype.getElement = function() {
        return this.tableDiv;
    };

    TableUi.prototype.failed = function() {
        return this.fail;
    };

    TableUi.prototype.failMessage = function() {
        return this.failString;
    };

    // Copy the serialised version of the Table UI area to the TextArea.
    TableUi.prototype.sync = function() {
        var
            serialisation = [],
            empty = true,
            tableRows = $(this.tableDiv).find('table tbody tr');

        tableRows.each(function () {
            var rowValues = [];
            $(this).find('.table_ui_cell').each(function () {
                var cellVal = $(this).val();
                rowValues.push(cellVal);
                if (cellVal) {
                    empty = false;
                }
            });
            serialisation.push(rowValues);
        });

        if (empty) {
            this.textArea.val('');
        } else {
            this.textArea.val(JSON.stringify(serialisation));
        }
    };

    // Return the HTML for row number iRow.
    TableUi.prototype.tableRow = function(iRow, preload) {
        const cellStyle = "width:100%;padding:0;font-family:monospace;";
        let html = '<tr>', widthIndex = 0, width, disabled, value;

        // Insert the row label if required.
        if (this.hasRowLabels) {
            width = this.columnWidths[0];
            widthIndex = 1;
            html += "<th style='padding-top:8px;text-align:center;width:" + width + "%' scope='row'>";
            if (iRow < this.uiParams.row_labels.length) {
                html += this.uiParams.row_labels[iRow];
            }
            html += "</th>";
        }

        for (let iCol = 0; iCol < this.numDataColumns; iCol++) {
            width = this.columnWidths[widthIndex++];
            disabled = this.isLockedCell(iRow, iCol) ? ' disabled' : '';
            value = iRow < preload.length ? preload[iRow][iCol] : '';

            if (iRow < preload.length) {
                value = preload[iRow][iCol];
            }
            html += "<td style='padding:2px;margin:0,width:" + width + "'%>";
            if (this.rowsPerCell == 1) {
                // Use input element for 1-row cells.
                html += `<input type="text" class="table_ui_cell" style="${cellStyle}" value="${value}"${disabled}>`;

            } else {
                // Use textarea elements everywhere else.
                html += `<textarea class="table_ui_cell" rows="${this.rowsPerCell}"`;
                html += ` style="${cellStyle}resize:vertical;"${disabled}>${value}</textarea>`;
            }
            html += "</td>";
        }
        html += '</tr>';
        return html;
    };

    // Return the HTML for the table's head section.
    TableUi.prototype.tableHeadSection = function() {
        let html = "<thead>\n",
            colIndex = 0;  // Column index including row label if present.

        if (this.hasHeader) {
            html += "<tr>";

            if (this.hasRowLabels) {
                html += "<th style='width:" + this.columnWidths[0] + "%'></th>";
                colIndex += 1;
            }

            for(let iCol = 0; iCol < this.numDataColumns; iCol++) {
                html += "<th style='width:" + this.columnWidths[colIndex] + "%'>";
                if (iCol < this.uiParams.column_headers.length) {
                    html += this.uiParams.column_headers[iCol];
                }
                colIndex++;
                html += "</th>";
            }
            html += "</tr>\n";
        }
        html += "</thead>\n";
        return html;
    };

    // Build the HTML table, filling it with the data from the serialisation
    // currently in the textarea (if there is any).
    TableUi.prototype.reload = function() {
        var
            preloadJson = $(this.textArea).val(), // JSON-encoded table values.
            preload = [],
            divHtml = "<div style='height:fit-content' class='qtype-coderunner-table-outer-div'>\n" +
                      "<table class='table table-bordered qtype-coderunner_table'>\n";

        if (preloadJson) {
            try {
                preload = JSON.parse(preloadJson);
            } catch(error)  {
                this.fail = true;
                this.failString = 'table_ui_invalidjson';
                return;
            }
        }

        try {
            // Build the table head section.
            divHtml += this.tableHeadSection();

            // Build the table body. Each table cell has a textarea inside it
            // except when the number of rows is 1, when input elements are used instead.
            // except for row labels (if present).
            divHtml += "<tbody>\n";
            var num_rows_required = Math.max(this.uiParams.num_rows, preload.length);
            for (var iRow = 0; iRow < num_rows_required; iRow++) {
                divHtml += this.tableRow(iRow, preload);
            }

            divHtml += '</tbody>\n</table>\n</div>';
            this.tableDiv = $(divHtml);
            if (this.uiParams.dynamic_rows) {
                this.addButtons();
            }

            // When using input elements, prevent Enter from submitting form.
            if (this.rowsPerCell == 1) {
                const ENTER = 13;
                $(this.tableDiv).find('.table_ui_cell').each(function() {
                    $(this).on('keydown', (e) => {
                        if (e.keyCode === ENTER) {
                            e.preventDefault();
                        }
                    });
                });
            }

        } catch (error) {
            this.fail = true;
            this.failString = 'table_ui_invalidserialisation';
        }
    };

    // Add 'Add row' and 'Delete row' buttons at the end of the table.
    TableUi.prototype.addButtons = function() {
        var deleteButtonHtml = '<button type="button"' +
                'style="float:right;margin-right:6px" disabled>Delete row</button>',
            deleteButton = $(deleteButtonHtml),
            t = this;
        this.tableDiv.append(deleteButton);
        deleteButton.click(function() {
            var numRows = t.tableDiv.find('table tbody tr').length,
                lastRow = t.tableDiv.find('tr:last');
            if (numRows > t.uiParams.num_rows) {
                lastRow.remove();
            }
            lastRow = t.tableDiv.find('tr:last'); // New last row.
            if (numRows == t.uiParams.num_rows + 1) {
                $(this).prop('disabled', true);
            }
        });

        var addButtonHtml = '<button type="button"' +
                'style="float:right;margin-right:6px">Add row</button>',
            addButton = $(addButtonHtml);
        t.tableDiv.append(addButton);
        addButton.click(function() {
            var lastRow, newRow;
            lastRow = t.tableDiv.find('table tbody tr:last');
            newRow = lastRow.clone();  // Copy the last row of the table.
            newRow.find('.table_ui_cell').each(function() {  // Clear all td elements in it.
                $(this).val('');
            });
            lastRow.after(newRow);
            $(this).prev().prop('disabled', false);
        });
    };

    TableUi.prototype.resize = function() {}; // Nothing to see here. Move along please.

    TableUi.prototype.hasFocus = function() {
        var focused = false;
        $(this.tableDiv).find('.table_ui_cell').each(function() {
            if (this === document.activeElement) {
                focused = true;
            }
        });
        return focused;
    };

    // Destroy the HTML UI and serialise the result into the original text area.
    TableUi.prototype.destroy = function() {
        this.sync();
        $(this.tableDiv).remove();
        this.tableDiv = null;
    };

    return {
        Constructor: TableUi
    };
});
