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

    public function __construct() {
        parent::__construct(1, 0);
        $this->epiloguehtml = null;
        $this->prologuehtml = null;
        $this->testresults = null;
    }


    /**
     * Method to set the mark and the various feedback values (prologuehtml,
     * testresults, epiloguehtml).
     * @param float $markfraction the mark in the range 0 - 1
     * @param array $feedback Associative array of attributes to add to the
     * outcome object, usually zero or more of prologuehtml, testresults and epiloguehtml.
     */
    public function set_mark_and_feedback($markfraction, $feedback) {
        $this->actualmark = $markfraction;  // Combinators work in the range 0 - 1.
        foreach ($feedback as $key => $value) {
            $this->$key = $value;
        }
    }

    public function iscombinatorgrader() {
        return true;
    }

    // Getter methods for use by renderer.
    // ==================================.

    public function get_test_results(qtype_coderunner_question $q) {
        return $this->testresults;
    }

    public function get_prologue() {
        return empty($this->prologuehtml) ? '' : $this->prologuehtml;
    }

    public function get_epilogue() {
        return empty($this->epiloguehtml) ? '' : $this->epiloguehtml;
    }
}
