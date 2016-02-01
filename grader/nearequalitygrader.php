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

defined('MOODLE_INTERNAL') || die();

require_once('graderbase.php');

class qtype_coderunner_near_equality_grader extends qtype_coderunner_grader {

    /** This grader tests if the expected output matches the actual
     *  output after removing all empty lines and trailing white space,
     *  collapsing all sequences of space or tab characters to a single
     *  space and converting all letters to lower case. [These changes
     *  are of course applied to both expected and actual outputs.]
     * 
     *  As requested by Ulrich Dangel.
     */
    function grade_known_good(&$output, &$testCase) {
        $cleanedOutput = qtype_coderunner_grader::clean($output);
        $cleanedExpected = qtype_coderunner_grader::clean($testCase->expected);
        
        $isCorrect = $this->reduce($cleanedOutput) == $this->reduce($cleanedExpected);
        $awardedMark = $isCorrect ? $testCase->mark : 0.0;

        if (isset($testCase->stdin)) {
            $resultStdin = qtype_coderunner_grader::tidy($testCase->stdin);
        } else {
            $resultStdin = null;
        }

        return new qtype_coderunner_test_result(
                qtype_coderunner_grader::tidy($testCase->testcode),
                $testCase->mark,
                $isCorrect,
                $awardedMark,
                qtype_coderunner_grader::snip($cleanedExpected),
                qtype_coderunner_grader::snip($cleanedOutput),
                $resultStdin,
                qtype_coderunner_grader::tidy($testCase->extra)
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
