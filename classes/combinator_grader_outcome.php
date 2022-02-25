<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/** Defines a subclass of the normal coderunner testing_outcome for use when
 * a combinator template grader is used.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
use qtype_coderunner\constants;

class qtype_coderunner_combinator_grader_outcome extends qtype_coderunner_testing_outcome {

    // A list of the allowed attributes in the combinator template grader return value.
    public $allowedfields = array('fraction', 'prologuehtml', 'testresults', 'epiloguehtml',
                    'feedbackhtml', 'columnformats', 'showdifferences',
                    'showoutputonly', 'graderstate'
    );

    public function __construct($isprecheck) {
        parent::__construct(1, 0, $isprecheck);
        $this->actualmark = 0;
        $this->epiloguehtml = null;
        $this->prologuehtml = null;
        $this->testresults = null;
        $this->columnformats = null;
        $this->outputonly = false;
    }


    /**
     * Method to set the mark and the various feedback values (prologuehtml,
     * testresults, columnformats, epiloguehtml, graderstate).
     * @param float $markfraction the mark in the range 0 - 1
     * @param array $feedback Associative array of attributes to add to the
     * outcome object, usually zero or more of prologuehtml, testresults,
     * columnformats and epiloguehtml.
     */
    public function set_mark_and_feedback($markfraction, $feedback) {
        $this->actualmark = $markfraction;  // Combinators work in the range 0 - 1.
        foreach ($feedback as $key => $value) {
            $this->$key = $value;
        }
        $this->validate_table_formats();
    }


    /** Mark this outcome as "outputonly", meaning only the prologuehtml and/or
     *  epiloguehtml are displayed, there is no result table and no grading.
     */

    public function set_output_only() {
        $this->outputonly = true;
        $this->actualmark = 1; // Shouldn't ever be used.
    }


    public function iscombinatorgrader() {
        return true;
    }

    // Return true if this is a question for which there is no result table
    // but just output to be displayed as supplied. There is no message
    // regarding success or failure with such questions.
    public function is_output_only() {
        return isset($this->outputonly) && $this->outputonly;
    }

    /**
     * Construct a customised error message for combinator grading outcomes if
     * practicable. Use the prologuehtml field (if given) followed by the first
     * wrong row of the result table if this table has been defined and if it
     * contains an 'iscorrect' column.
     * @return type
     */
    public function validation_error_message() {
        $error = '';
        if (!empty($this->prologuehtml)) {
            $error = $this->prologuehtml . "<br>";
        }
        if (!empty($this->testresults)) {
            $headerrow = $this->testresults[0];
            $iscorrectcol = array_search('iscorrect', $headerrow);
            if ($iscorrectcol !== false) {
                // Table has the optional 'iscorrect' column so find first fail.
                foreach (array_slice($this->testresults, 1) as $row) {
                    if (!$row[$iscorrectcol]) {
                        $error .= "First failing test:<br>";
                        for ($i = 0; $i < count($row); $i++) {
                            if ($headerrow[$i] != 'iscorrect' &&
                                    $headerrow[$i] != 'ishidden') {
                                $cell = htmlspecialchars($row[$i]);
                                $error .= "{$headerrow[$i]}: <pre>$cell</pre>";
                            }
                        }
                        break;
                    }
                }
            }
        }
        return $error . parent::validation_error_message();
    }

    // Getter methods for use by renderer.
    // ==================================.

    /**
     * A specialised version of get_test_results. With combinator template
     * grades the template may or may not choose to return a testresults table.
     * If there is one, it may or may not have an 'ishidden' column. If it has
     * one, and if the current user is a student, only the non-hidden rows
     * of the table should be returned. Otherwise all are returned.
     * There is no concept of 'hide-rest-if-fail' for combinator template
     * graders which must do all such logic themselves.
     * Usually table elements are just strings to be sanitised and wrapped in
     * pre elements for display. However the question author can also supply
     * in the combinator template grader return value a field
     * 'columnformats'. This should have one format specifier per
     * table column and each format specifier should either be '%s', in which
     * case all formatting is left to the renderer or '%h' in which case the
     * table cell is wrapped in an html_wrapper object to prevent further
     * processing by the renderer.
     * @global type $COURSE the current COURSE (if there is one)
     * @param qtype_coderunner_question $q The question being rendered (ignored)
     * @return A table of test results. See the parent class for details.
     */
    public function get_test_results(qtype_coderunner_question $q) {
        if (empty($this->testresults) || self::can_view_hidden()) {
            return $this->format_table($this->testresults);
        } else {
            return $this->format_table($this->visible_rows($this->testresults));
        }
    }

    // Function to apply the formatting specified in $this->columnformats
    // to the given table. This simply wraps cells in columns with a '%h' format
    // specifier in html_wrapper objects leaving other cells unchanged.
    // ishidden and iscorrect columns are copied across unchanged.
    private function format_table($table) {
        if (empty($table)) {
            return $table;
        }
        if (!$this->columnformats) {
            $newtable = $table;
        } else {
            $formats = $this->columnformats;
            $columnheaders = $table[0];
            $newtable = array($columnheaders);
            for ($i = 1; $i < count($table); $i++) {
                $row = $table[$i];
                $newrow = array();
                $formatindex = 0;
                for ($col = 0; $col < count($row); $col++) {
                    $cell = $row[$col];
                    if (in_array($columnheaders[$col], array('ishidden', 'iscorrect'))) {
                        $newrow[] = $cell;  // Copy control column values directly.
                    } else {
                        // Non-control columns are either '%s' or '%h' format.
                        if ($formats[$formatindex++] === '%h') {
                            $newrow[] = new qtype_coderunner_html_wrapper($cell);
                        } else {
                            $newrow[] = $cell;
                        }
                    }
                }
                $newtable[] = $newrow;
            }
        }
        return $newtable;
    }

    public function get_prologue() {
        return empty($this->prologuehtml) ? '' : $this->prologuehtml;
    }

    public function get_epilogue() {
        return empty($this->epiloguehtml) ? '' : $this->epiloguehtml;
    }

    public function show_differences() {
        return $this->actualmark != 1.0 && isset($this->showdifferences) &&
               $this->showdifferences &&  isset($this->testresults);
    }

    public function get_grader_state() {
        return empty($this->graderstate) ? '' : $this->graderstate;
    }


    // Check that if a columnformats field is supplied
    // the number of entries is correct and that each entry is either '%s'
    // or '%h'. If not, an appropriate status error message is set.
    private function validate_table_formats() {
        if ($this->columnformats && $this->testresults) {
            $numcols = 0;
            foreach ($this->testresults[0] as $colhdr) {
                // Count columns in header, excluding iscorrect and ishidden.
                if ($colhdr !== 'iscorrect' && $colhdr !== 'ishidden') {
                    $numcols += 1;
                }
            }
            $blah = count($this->columnformats);
            if (count($this->columnformats) !== $numcols) {
                $error = get_string('wrongnumberofformats', 'qtype_coderunner',
                        array('expected' => $numcols, 'got' => count($this->columnformats)));
                $this->set_status(self::STATUS_BAD_COMBINATOR, $error);
            } else {
                foreach ($this->columnformats as $format) {
                    if ($format !== '%s' && $format !== '%h') {
                        $error = get_string('illegalformat', 'qtype_coderunner',
                            array('format' => $format));
                        $this->set_status(self::STATUS_BAD_COMBINATOR, $error);
                        break;
                    }
                }
            }
        }
    }


    // Private method to filter result table so only visible rows are shown
    // to students. Only called if the user is not allowed to see hidden rows
    // And if there is a non-null non-empty resulttable.
    private static function visible_rows($resulttable) {
        $header = $resulttable[0];
        $ishiddencolumn = -1;
        for ($i = 0; $i < count($header); $i++) {
            if (strtolower($header[$i]) === 'ishidden') {
                $ishiddencolumn = $i;
            }
        }
        if ($ishiddencolumn === -1) {
            return $resulttable;  // No ishidden column so all rows visible.
        } else {
            $rows = array($header);
            for ($i = 1; $i < count($resulttable); $i++) {
                $row = $resulttable[$i];
                if (!$row[$ishiddencolumn]) {
                    $rows[] = $row;
                }
            }
            return $rows;
        }

    }
}
