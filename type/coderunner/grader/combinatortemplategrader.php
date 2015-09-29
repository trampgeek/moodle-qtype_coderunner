<?php
/** The qtype_coderunner_combinator_template_grader class. This isn't actually a 
 *  grader at all and is never called. Combinator Template grading uses the
 *  combinator template to generate a mark and the feedback to the student in 
 *  a single run. The output is not split into separate test runs, so the normal
 *  interface does not apply. See the doCombinatorGrading method of the question
 *  class.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2014, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once('graderbase.php');

class qtype_coderunner_combinator_template_grader extends qtype_coderunner_grader {

    function grade_known_good(&$output, &$testcase) {
        throw new coding_exception("CombinatorGrader shouldn't be called");
    }
}

