<?php
// This file is part of CodeRunner - http://coderunner.org.nz
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

/**
 * A section of the question and prototype authoring forms.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * The section of the authoring forms in which a field appears.
 *
 * The order of the cases is the order the sections appear in the form, as set
 * out in the design document.
 */
enum form_section: string {
    /** Fields belonging to the question itself, shown at the top and open by default. */
    case QUESTION = 'question';

    /** Template parameters. */
    case PARAMETERS = 'parameters';

    /** Which test case fields the question form offers. */
    case TESTCASEFIELDS = 'testcasefields';

    /** Grading and penalties. */
    case GRADING = 'grading';

    /** User interface, including the answer box size. */
    case UI = 'ui';

    /** Template and grader. */
    case TEMPLATE = 'template';

    /** Sandbox, languages and limits. */
    case SANDBOX = 'sandbox';

    /** Global extra and prototype extra. */
    case EXTRAS = 'extras';

    /** Support files. */
    case FILES = 'files';
}
