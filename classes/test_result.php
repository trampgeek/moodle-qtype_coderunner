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

/** Defines a test_result object, which captures the result of a single
 *  testcase run. It contains all the information required to display
 *  one row of the test result table, including all the fields from the
 *  original testcase.
 *  It is treated as a simple record rather than a true class object.
 *
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[AllowDynamicProperties]
class qtype_coderunner_test_result {
    public function __construct($testcase, $iscorrect, $awardedmark, $got) {
        // Flatten testcase into this, tidying up text fields.
        foreach (get_object_vars($testcase) as $key => $value) {
            if (in_array($key, ['expected', 'testcode', 'stdin', 'extra'])) {
                $this->$key = qtype_coderunner_util::tidy($value);
            } else {
                $this->$key = $value;
            }
        }
        $this->iscorrect = $iscorrect;
        $this->awarded = $awardedmark;
        $this->got = qtype_coderunner_util::tidy($got);
    }

    // Return the value from this testresult as specified by the given
    // $fieldspecifier, which is either a fieldname within the test result
    // or an expression of the form diff(fieldspec1, fieldspec2). Both forms
    // now return the same result, namely the rtrimmed fieldspecifier or fieldspec1
    // in the diff case. The diff variant is obsolete - it was
    // used to provide a Show Differences button but that functionality is
    // now provided in JavaScript.
    public function gettrimmedvalue($fieldspecifier) {
        $matches = [];
        if (preg_match('|diff\((\w+), ?(\w+)\)|', $fieldspecifier, $matches)) {
            $fieldspecifier = $matches[1];
        }
        if (property_exists($this, $fieldspecifier)) {
            $value = rtrim($this->$fieldspecifier);
        } else {
            $value = "Unknown field '$fieldspecifier'";
        }
        return $value;
    }
}
