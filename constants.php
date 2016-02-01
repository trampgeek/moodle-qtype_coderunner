<?php
/* Constants for use within qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

class constants {
    const TEMPLATE_LANGUAGE = 0;
    const USER_LANGUAGE = 1;
    const DEFAULT_GRADER = 'EqualityGrader';  // External name of default grader
    const FUNC_MIN_LENGTH = 1;  /* Minimum no. of bytes for a valid bit of code */
}