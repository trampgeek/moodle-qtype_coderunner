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
/** The NearEqualityGrader class. Compares the output from a given test case,
 *  awarding full marks if and only if the output "nearly matches" the expected
 *  output. Otherwise, zero marks are awarded. The output is deemed to "nearly
 *  match" the expected if the two are byte for byte identical after trailing
 *  white space and blank lines have been removed from both, sequences of spaces
 *  and tabs have been reduced to a single space and all letters have been
 *  converted to lower case.
 */

/**
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_near_equality_grader extends qtype_coderunner_grader {
    /** This grader tests if the expected output matches the actual
     *  output after removing all empty lines and trailing white space,
     *  collapsing all sequences of space or tab characters to a single
     *  space and converting all letters to lower case. [These changes
     *  are of course applied to both expected and actual outputs.]
     *
     *  As requested by Ulrich Dangel.
     */

    public function name() {
        return 'NearEqualityGrader';
    }


    protected function grade_known_good(&$output, &$testcase) {
        $cleanedoutput = qtype_coderunner_util::clean($output);
        $cleanedexpected = qtype_coderunner_util::clean($testcase->expected);
        $iscorrect = $this->reduce($cleanedoutput) == $this->reduce($cleanedexpected);
        $awardedmark = $iscorrect ? $testcase->mark : 0.0;
        return new qtype_coderunner_test_result($testcase, $iscorrect, $awardedmark, $cleanedoutput);
    }

    // Simplify the output string by removing empty lines, collapsing
    // sequences of tab or space characters to a single space and converting
    // to lower case.
    private function reduce(&$output) {
        $reduced = preg_replace("/\n\n+/", "\n", $output);
        $reduced2 = preg_replace("/^\n/", '', $reduced);  // Delete blank first line.
        $reduced3 = preg_replace('/[ \t][ \t]+/', ' ', $reduced2);
        return strtolower($reduced3);
    }
}
