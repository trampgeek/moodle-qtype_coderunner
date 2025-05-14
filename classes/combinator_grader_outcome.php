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
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_coderunner\constants;

class qtype_coderunner_combinator_grader_outcome extends qtype_coderunner_testing_outcome {
    /** @var ?string Html that is displayed before the result table. */
    public $epiloguehtml;

    /** @var ?string Html that is displayed after the result table. */
    public $prologuehtml;

    /** @var array A per-column array of %s (string) or %h (html) values to control column formatting */
    public $columnformats;

    /** @var bool If true, the question does not display the result table and no grading. */
    public $outputonly;

    /** @var ?string HTML feedback set for teacher that is hidden from student. */
    public $instructorhtml;

    /** @var ?number The grade, out of 1. */
    public $fraction;

    /** @var bool If true, is used when the question is to be used only to display the output and perhaps images from a run, with no mark. */
    public $showoutputonly;

    /** @var array Array where each item is a rows of test result table  */
    public $testresults;

    /** @var ?string The feedback for a given question attempt (legacy support only) */
    public $feedbackhtml;

    /** @var bool Whether or no show differences is selected */
    public $showdifferences;

    /** @var ?int $outcomeid Random unique id for this testing outcome. */
    public $outcomeid;

    // A list of the allowed attributes in the combinator template grader return value.
    public $allowedfields = ['fraction', 'prologuehtml', 'testresults', 'files', 'epiloguehtml',
                    'columnformats', 'showdifferences', 'showoutputonly', 'graderstate', 'instructorhtml',
    ];

    public function __construct($isprecheck) {
        parent::__construct(1, 0, $isprecheck);
        $this->actualmark = 0;
        $this->epiloguehtml = null;
        $this->prologuehtml = null;
        $this->testresults = null;
        $this->columnformats = null;
        $this->outputonly = false;
        $this->instructorhtml = null;

        // Generate a (hopefully unique) id for this testoutcome object.
        // Used as an itemid when saving feedback images/files that
        // this class can generate. Strict uniqueness isn't actually
        // required however as the filenames are prefixed by the Unix
        // timestamp. So all we need is that the outcomeid is unique
        // within a time window of 1 second.
        $this->outcomeid = random_int(1, PHP_INT_MAX);
    }

    /**
     * Get the hopefully-unique id of this testing outcome, for use
     * as an itemid when saving/restoring feedback files.
     * @return int The unique ID (a random int in the range 1 to PHP_INT_MAX).
     */
    public function get_id() {
        return $this->outcomeid;
    }


    /**
     * Process all the files in $files, saving them to the Moodle file area
     * and generating an URL to reference the saved file. The URLs are
     * time-stamped to allow re-use of the same name over
     * multiple submissions of a question.
     * @param array $files An associate array mapping filenames to base64-encoded contents.
     * @param array An associate array mapping filenames to URLs that reference that file.
     */
    private function save_files($files) {
        global $COURSE;

        $fileurls = [];
        if ($files) {
            $itemid = $this->get_id();
            $contextid = context_course::instance($COURSE->id)->id;
            foreach ($files as $filename => $base64) {
                $extendedfilename = strval(time()) . $filename;
                $decoded = base64_decode($base64);

                // Prepare file record object.
                $fileinfo = [
                    'contextid' => $contextid,
                    'component' => 'qtype_coderunner',
                    'filearea'  => 'feedbackfiles',
                    'itemid'    => $itemid, // Item ID - question attempt step id.
                    'filepath'  => '/', // File path within the context.
                    'filename'  => $extendedfilename,
                ];

                // Create the file in Moodle's filesystem.
                $fs = get_file_storage();
                $file = $fs->create_file_from_string($fileinfo, $decoded);

                // Generate a @@PLUGINFILE@@ URL to the saved file.
                $url = '@@PLUGINFILE@@/' . $extendedfilename;
                $fileurls[$filename] = $url;
            }
        }
        return $fileurls;
    }


    /**
     * Replace any occurrences of substrings of the form src="filename" or
     * href=filename within the given html string to reference the URL of
     * the file, if the filename is found in $this->fileurls.
     * No action is taken for non-matching filename.
     * @param string html The html string to be updates.
     * @param array $urls An associative array mapping from filenames to URLs
     * @return string The html string with the above-described substitutions made.
     */
    private function insert_file_urls($html, $urls) {
        if ($urls) {
            foreach ($urls as $filename => $url) {
                $filename = preg_quote($filename, '/');
                $patterns = [
                    "src *= *'$filename'",
                    "src *= *\"$filename\"",
                    "href *= *'$filename'",
                    "href *= *\"$filename\"",
                ];
                foreach ($patterns as $pat) {
                    if (strpos($pat, 'src') !== false) {
                        $html = preg_replace("/$pat/", "src=\"$url\"", $html);
                    } else {
                        $html = preg_replace("/$pat/", "href=\"$url\"", $html);
                    }
                }
            }
        }
        return $html; // Return the modified HTML.
    }


    /**
     * Method to set the mark and the various feedback values (prologuehtml,
     * testresults, columnformats, epiloguehtml, graderstate).
     * @param float $markfraction the mark in the range 0 - 1
     * @param array $feedback Associative array of additional attributes as
     * listed in the $this->allowedfields.
     */
    public function set_mark_and_feedback($markfraction, $feedback) {
        $this->actualmark = $markfraction;  // Combinators work in the range 0 - 1.
        $testresults = $feedback['testresults'] ?? null;
        $files = $feedback['files'] ?? null;
        $urls = $files ? $this->save_files($files) : null;

        foreach ($feedback as $field => $value) {
            if ($urls && in_array($field, ['prologuehtml', 'epiloguehtml', 'instructorhtml'])) {
                $this->$field = $this->insert_file_urls($value, $urls);
            } else if (! in_array($field, ['files', 'testresults'])) {
                $this->$field = $value;
            }
        }

        if ($this->valid_table_formats($testresults, $this->columnformats)) {
            $this->format_results_table($testresults, $this->columnformats, $urls);
        }
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
        return $this->outputonly ?? false;
    }


    /**
     * Return a message describing the first failing test, to the extent
     * possible. Only called if there is a valid 'correct' column.
     * @return string HTML error message describing the first validation failure.
     */
    private function format_first_failing_test($correctcol) {
        $error = '';
        foreach (array_slice($this->testresults, 1) as $row) {
            if (!$row[$hascorrect]) {
                $n = count($row);
                for ($i = 0; $i < $n; $i++) {
                    if ($headerrow[$i] != 'iscorrect') {
                        $cell = htmlspecialchars($row[$i]);
                        $error .= "{$headerrow[$i]}: <pre>$cell</pre>";
                    }
                }
                break;
            }
        }
        return $error;
    }


    /**
     * Return a table describing all the validation failures. An issue here is
     * that the replaceExpectedWithGot button contains a link to the failed test
     * based on the row number in the question-author-generated result table.
     * This row number is not necessarily the same as the row number in the
     * actual test results table, due to reordering (tests are actually
     * run in the order defined by the 'ordering' field in the test case).
     * This must be fixed by a post-processing step.
     * @param int $correctcol The table column number of the 'iscorrect' column.
     * @param int $expectedcol The table column number of the 'Expected' column.
     * @param int $gotcol The table column number of the 'Got' column.
     * @return string An HTML table with one row for each failed test case, and
     * a button to copy the 'got' column into the 'expected' column of the test
     * case.
     */
    private function make_validation_fail_table($correctcol, $expectedcol, $gotcol, $sanitise) {
        $error = '';
        $rownum = 0;
        $codecol = array_search(get_string('testcolhdr', 'qtype_coderunner'), $this->testresults[0]);
        foreach (array_slice($this->testresults, 1) as $row) {
            if (!$row[$correctcol]) {
                if ($codecol !== false) {
                    $code = $row[$codecol];
                } else {
                    $code = '';
                }
                $this->add_failed_test($rownum, $code, $row[$expectedcol], $row[$gotcol], $sanitise);
            }
            $rownum += 1;
        }
        return html_writer::table($this->failures) . get_string('replaceexpectedwithgot', 'qtype_coderunner');
    }


    /**
     * Check if a column is formatted in raw HTML. Messy, because the column
     * formats array does not include is ishidden and iscorrect fields.

     * @param int $col
     * @return bool true if the given column is to be displayed in html
     */
    private function is_html_column($col) {
        $hdrs = $this->testresults[0];
        $formats = $this->columnformats;
        if (!$formats) {
            return false;
        }
        $i = 0;    // Column number.
        $icol = 0; // Column number excluding ishidden and iscorrect.
        while ($i < count($hdrs)) {
            if ($hdrs[$i] != 'iscorrect' && $hdrs[$i] != 'ishidden') {
                if ($i == $col) {
                    $ishtml = $formats[$icol] == '%h';
                    return $ishtml;
                }
                $icol += 1;
            }
            $i++;
        }
        return false;
    }

    /**
     * Construct a customised error message for combinator grading outcomes if
     * practicable. Use the prologuehtml field (if given) followed by a table
     * of all the failing tests in the result table if this table has been defined
     * and if it contains an 'iscorrect' column.
     * @return string An HTML error message.
     */
    public function validation_error_message() {
        $error = '';
        if (!empty($this->prologuehtml)) {
            $error = $this->prologuehtml . "<br>";
        }
        if (!empty($this->testresults)) {
            $headerrow = $this->testresults[0];
            $correctcol = array_search('iscorrect', $headerrow);
            $expectedcol = array_search(get_string('expectedcolhdr', 'qtype_coderunner'), $headerrow);
            $gotcol = array_search(get_string('gotcolhdr', 'qtype_coderunner'), $headerrow);
            $sanitise = true;
            if ($correctcol !== false && $expectedcol !== false && $gotcol !== false) {
                // This looks like a pretty conventional results table, so we can
                // try using the parent way of formatting the failed test cases, with
                // copy-got-to-expected button.

                $sanitise = !$this->is_html_column($gotcol);
                $error .= $this->make_validation_fail_table($correctcol, $expectedcol, $gotcol, $sanitise);
            } else if ($correctcol) {
                // Can't use the fancy table presentation as missing got and/or
                // expected. So just make a simple 'first failing test' string.
                $error .= $this->format_first_failing_test($correctcol, $sanitise);
            }
        }

        $error .= '<br>' . parent::validation_error_message();
        return $error;
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
            return $this->testresults;
        } else {
            return self::visible_rows($this->testresults);
        }
    }

    /**
     * Build the testresults table from the initial given testresults by
     * applying all the column formats. Cells with '%h' format are first
     * processed to replace any src or href assignments with references to
     * the $urls of the saved files. Then the cells are wrapped in
     * html_wrapper objects. All other cells are unchanged.
     * ishidden and iscorrect columns are copied across unchanged.
     * @param array $testresults The 'raw' $testresults from the run.
     * @param array $columnformats The array of '%s' or '%h' column formats
     * @param array $urls. The map from filename to URL
     */
    private function format_results_table($testresults, $columnformats, $urls) {
        if (!$testresults || !$columnformats) {
            $this->testresults = $testresults;
        } else {
            $formats = $columnformats;
            $columnheaders = $testresults[0];
            $newtable = [$columnheaders];
            $nrows = count($testresults);
            for ($i = 1; $i < $nrows; $i++) {
                $row = $testresults[$i];
                $newrow = [];
                $formatindex = 0;
                $ncols = count($row);
                for ($col = 0; $col < $ncols; $col++) {
                    $cell = $row[$col];
                    if (in_array($columnheaders[$col], ['ishidden', 'iscorrect'])) {
                        $newrow[] = $cell;  // Copy control column values directly.
                    } else {
                        // Non-control columns are either '%s' or '%h' format.
                        if ($formats[$formatindex++] === '%h') {
                            $cell = $this->insert_file_urls($cell, $urls);
                            $newrow[] = new qtype_coderunner_html_wrapper($cell);
                        } else {
                            $newrow[] = $cell;
                        }
                    }
                }
                $newtable[] = $newrow;
            }
            $this->testresults = $newtable;
        }
    }

    public function get_prologue() {
        return empty($this->prologuehtml) ? '' : $this->prologuehtml;
    }

    public function get_epilogue() {
        if (empty($this->instructorhtml)) {
            $this->instructorhtml = '';
        }
        if (empty($this->epiloguehtml)) {
            $this->epiloguehtml = '';
        }
        if (self::can_view_hidden()) {
            return $this->instructorhtml . $this->epiloguehtml;
        } else {
            return $this->epiloguehtml;
        }
    }

    public function show_differences() {
        return $this->actualmark != 1.0 && isset($this->showdifferences) &&
               $this->showdifferences &&  isset($this->testresults);
    }

    public function get_grader_state() {
        return empty($this->graderstate) ? '' : $this->graderstate;
    }


    /**
     * Check if the given values of column formats and test results are
     * valid in the sense that the number of entries in column formats
     * matches the number of columns in the table and that each column
     * foramt is either '%s'
     * or '%h'. If not, an appropriate status error message is set and
     * the return value is false. Otherwise the return value is true.
     * @param array $testresults An array of test results, where the
     * first row is the column headers and the remaining rows are the
     * results of each test.
     * @param array $columnformats An array of strings specifying the
     * column formats, each either %s or %h.
     * @return bool True if either of $columnformats or $testresults is
     * null or empty or if all column formats are valid. Otherwise
     * return false, and set an error status.
     */

    private function valid_table_formats($testresults, $columnformats) {
        $ok = true;
        if ($columnformats && $testresults) {
            $numcols = 0;
            foreach ($testresults[0] as $colhdr) {
                // Count columns in header, excluding iscorrect and ishidden.
                if ($colhdr !== 'iscorrect' && $colhdr !== 'ishidden') {
                    $numcols += 1;
                }
            }
            if (count($columnformats) !== $numcols) {
                $error = get_string(
                    'wrongnumberofformats',
                    'qtype_coderunner',
                    ['expected' => $numcols, 'got' => count($columnformats)]
                );
                $this->set_status(self::STATUS_BAD_COMBINATOR, $error);
                $ok = false;
            } else {
                foreach ($columnformats as $format) {
                    if ($format !== '%s' && $format !== '%h') {
                        $error = get_string(
                            'illegalformat',
                            'qtype_coderunner',
                            ['format' => $format]
                        );
                        $this->set_status(self::STATUS_BAD_COMBINATOR, $error);
                        $ok = false;
                        break;
                    }
                }
            }
        }
        return $ok;
    }


    /**
     * Filter the given result table to return only the visible rows.
     * Only called if the user is not allowed to see hidden rows
     * And if there is a non-null non-empty resulttable.
     * */
    private static function visible_rows($resulttable) {
        $header = $resulttable[0];
        $ishiddencolumn = -1;
        $n = count($header);
        for ($i = 0; $i < $n; $i++) {
            if (strtolower($header[$i]) === 'ishidden') {
                $ishiddencolumn = $i;
            }
        }
        if ($ishiddencolumn === -1) {
            return $resulttable;  // No ishidden column so all rows visible.
        } else {
            $rows = [$header];
            $n = count($resulttable);
            for ($i = 1; $i < $n; $i++) {
                $row = $resulttable[$i];
                if (!$row[$ishiddencolumn]) {
                    $rows[] = $row;
                }
            }
            return $rows;
        }
    }
}
