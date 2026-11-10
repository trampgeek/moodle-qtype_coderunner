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
 * Unit tests for the language a layer's template parameters are evaluated in.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

/**
 * An unrecorded language means no evaluation, which is what *None* means.
 *
 * The empty string reaches `evaluate_template_params()` from every place that
 * casts an absent language to one: `(string) $this->templateparamslang` on a
 * question whose column is null, and the same cast on a prototype in the
 * authoring form's fallback path. It can now also be reached by design — a
 * question saved with its language at *None* stores no override, so
 * `legacy_converter::question_columns()` writes null to the column. Read as
 * neither *None* nor *Twig* it fell through to the sandbox, which was asked to
 * evaluate the parameters in a language called "".
 *
 * The run-time path never sees it, because `chain_layers()` substitutes the
 * registry default for a layer that sets no language; this is about every
 * other reader.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner_question::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class template_params_language_test extends \advanced_testcase {
    /**
     * An unrecorded language leaves the document alone, exactly as *None* does.
     */
    public function test_an_unrecorded_language_means_no_evaluation(): void {
        $question = new \qtype_coderunner_question();
        $document = '{"colour": "red", "size": 3}';

        $unrecorded = $question->evaluate_template_params($document, '', 0);

        $this->assertSame($document, $unrecorded);
        $this->assertSame(
            $question->evaluate_template_params($document, 'None', 0),
            $unrecorded,
            'An unrecorded language should mean what None means.'
        );
    }

    /**
     * And an empty document is still nothing, whatever the language says.
     */
    public function test_an_empty_document_evaluates_to_nothing(): void {
        $question = new \qtype_coderunner_question();

        foreach (['', 'None', 'twig', 'python3'] as $lang) {
            $this->assertSame(
                '{}',
                $question->evaluate_template_params('', $lang, 0),
                "An empty document in language '$lang' should evaluate to nothing."
            );
        }
    }

    /**
     * The registry's default language is the one the empty string now agrees
     * with, so the form, the resolver and the evaluator say the same thing.
     */
    public function test_the_registry_default_is_the_same_language(): void {
        $this->assertSame('None', field_registry::get('templateparamslang')->default);
    }
}
