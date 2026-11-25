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
 * The order of the cases is the order the sections appear in **both** forms:
 * the prototype editor's order, which the question form was changed to match
 * on 26 November at the author's request. Before that each form kept its own
 * list and the two had drifted apart.
 */
enum form_section: string {
    /** Fields belonging to the question itself, shown at the top and open by default. */
    case QUESTION = 'question';

    /** Template, result table and the rest of what runs a test. */
    case TEMPLATE = 'template';

    /** Template parameters. */
    case PARAMETERS = 'parameters';

    /** Grading and penalties, the grader among them. */
    case GRADING = 'grading';

    /** User interface, the answer box size and attachments. */
    case UI = 'ui';

    /**
     * Support files.
     *
     * Beside the user interface, at the author's request of 26 November: a
     * question's files are something an author touches while writing it, and
     * the sections below it are mostly inherited.
     */
    case FILES = 'files';

    /** Which test case fields the question form offers. */
    case TESTCASEFIELDS = 'testcasefields';

    /** Sandbox, languages and limits. */
    case SANDBOX = 'sandbox';

    /** Global extra and prototype extra. */
    case EXTRAS = 'extras';

    /**
     * The heading this section is shown under.
     *
     * Here rather than in either form, because both forms show the same
     * sections and a label defined twice is a label that drifts. The prototype
     * editor reads it from here too, although the strings live in the question
     * type: the sections are the registry's, and the registry is the question
     * type's (decision 1).
     *
     * @return string the heading.
     */
    public function label(): string {
        return get_string('section' . $this->value, 'qtype_coderunner');
    }
}
