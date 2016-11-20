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

/* The qtype_coderunner_combinator_template_grader class. This isn't actually a
 * grader at all and is never called. Combinator Template grading uses the
 * combinator template to generate a mark and the feedback to the student in
 * a single run. The output is not split into separate test runs, so the normal
 * interface does not apply. See the doCombinatorGrading method of the question
 * class.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2014, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


class qtype_coderunner_combinator_template_grader extends qtype_coderunner_grader {

    public function name() {
        return 'CombinatorTemplateGrader';
    }

    public function grade_known_good(&$output, &$testcase) {
        throw new coding_exception("CombinatorGrader shouldn't be called");
    }
}

