<?php
/** The NearEqualityGrader class. Compares the output from a given test case,
 *  awarding full marks if and only if the output "nearly matches" the expected
 *  output. Otherwise, zero marks are awarded. The output is deemed to "nearly
 *  match" the expected if the two are byte for byte identical after trailing
 *  white space and blank lines have been removed from both, sequences of spaces
 *  and tabs have been reduced to a single space and all letters have been
 *  converted to lower case.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('graderbase.php');
class NearEqualityGrader extends Grader {

    /** This grader tests if the expected output matches the actual
     *  output after removing all empty lines and trailing white space,
     *  collapsing all sequences of space or tab characters to a single
     *  space and converting all letters to lower case. [These changes
     *  are of course applied to both expected and actual outputs.]
     * 
     *  As requested by Ulrich Dangel.
     */
    function gradeKnownGood(&$output, &$testCase) {
        $cleanedOutput = Grader::clean($output);
        $cleanedExpected = Grader::clean($testCase->expected);
        
        $isCorrect = $this->reduce($cleanedOutput) == $this->reduce($cleanedExpected);
        $awardedMark = $isCorrect ? $testCase->mark : 0.0;

        if ($testCase->stdin) {
            $resultStdin = Grader::tidy($testCase->stdin);
        } else {
            $resultStdin = NULL;
        }

        return new TestResult(
                Grader::tidy($testCase->testcode),
                $testCase->mark,
                $isCorrect,
                $awardedMark,
                Grader::snip($cleanedExpected),
                Grader::snip($cleanedOutput),
                $resultStdin
        );
    }
    
    // Simplify the output string by removing empty lines, collapsing
    // sequences of tab or space characters to a single space and converting
    // to lower case.
    private function reduce(&$output) {
        $reduced = preg_replace("/\n\n+/", "\n", $output);
        $reduced2 = preg_replace("/^\n/", '', $reduced);  // Delete blank first line
        $reduced3 = preg_replace('/[ \t][ \t]+/', ' ', $reduced2);
        return strtolower($reduced3);
    }
}
