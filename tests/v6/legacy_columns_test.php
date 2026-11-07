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
 * Unit tests for writing a question's CodeRunner 5 columns from its overrides.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Tests the inverse of the question converter: the CodeRunner 5 columns a
 * question's overrides and resolved settings imply.
 *
 * The new question form decides `prototypeid` and `overrides` itself, so the
 * legacy columns are written from them rather than the other way round
 * (decision 26). The property that makes that safe is that the two converters
 * are inverses, which is what most of these tests assert: the verification
 * script, Moodle XML export and the question backup all read the legacy
 * columns, and all three stay correct exactly as long as those columns say what
 * the migration would have said.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(legacy_converter::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class legacy_columns_test extends \advanced_testcase {
    /**
     * A two-layer chain: a prototype supplying a template, a penalty regime
     * and template parameters, and the question's own overrides on top.
     *
     * @param array $own the question's overrides.
     * @return array [override_set, question_settings] the question's own
     *      overrides and what the chain resolves to with them applied.
     */
    private function chain(array $own): array {
        $overrides = new override_set($own, 'the question');
        $resolved = prototype_chain::from_arrays([
            'python3' => [
                'template' => 'PROTOTYPE TEMPLATE',
                'penaltyregime' => '10, 20, ...',
                'templateparams' => '{"xxx": 1}',
                'memlimitmb' => 400,
            ],
            'the question' => $own,
        ])->resolve();

        return [$overrides, $resolved];
    }

    /**
     * A field CodeRunner 5 inherited is written null when the question does not
     * override it, because null is how CodeRunner 5 spells "inherited" and the
     * prototype question named in `coderunnertype` supplies it.
     */
    public function test_a_field_inherited_in_v5_is_null_when_not_overridden(): void {
        [$own, $resolved] = $this->chain([]);

        $columns = legacy_converter::question_columns($own, $resolved);

        $this->assertNull($columns['template']);
        $this->assertNull($columns['grader']);
        $this->assertNull($columns['memlimitmb']);
    }

    /**
     * A collected field follows the same rule, because CodeRunner 5 merged the
     * prototype's contribution with the question's own: an empty column is a
     * question that contributes nothing, not a question with no parameters.
     */
    public function test_a_collected_field_is_null_when_not_overridden(): void {
        [$own, $resolved] = $this->chain([]);

        $columns = legacy_converter::question_columns($own, $resolved);

        $this->assertNull($columns['templateparams']);
        $this->assertNull($columns['uiparameters']);
    }

    /**
     * A field CodeRunner 5 read straight from the column is written at its
     * effective value, because CodeRunner 5 has no other source for it. This is
     * the one case where the columns cannot record that a value is inherited,
     * and writing the effective value is what keeps a 5.x restore, an export
     * and the verifier's "before" side all correct.
     */
    public function test_a_directly_read_field_gets_its_effective_value(): void {
        [$own, $resolved] = $this->chain([]);

        $columns = legacy_converter::question_columns($own, $resolved);

        $this->assertSame('10, 20, ...', $columns['penaltyregime']);
    }

    /**
     * An override is written to its own column, whichever group it is in.
     */
    public function test_an_override_is_written_to_its_column(): void {
        [$own, $resolved] = $this->chain([
            'template' => 'MY TEMPLATE',
            'templateparams' => '{"yyy": 2}',
            'penaltyregime' => '0',
        ]);

        $columns = legacy_converter::question_columns($own, $resolved);

        $this->assertSame('MY TEMPLATE', $columns['template']);
        $this->assertSame('{"yyy": 2}', $columns['templateparams']);
        $this->assertSame('0', $columns['penaltyregime']);
    }

    /**
     * Instance fields are not written here. They are the question's own in both
     * models, they are never in the overrides document, and the form writes
     * them to their columns directly.
     */
    public function test_instance_fields_get_no_column(): void {
        [$own, $resolved] = $this->chain([]);

        $columns = legacy_converter::question_columns($own, $resolved);

        foreach (field_registry::all() as $name => $field) {
            if ($field->kind === field_kind::INSTANCE) {
                $this->assertArrayNotHasKey($name, $columns, "Instance field '$name' was written as a column.");
            }
        }
    }

    /**
     * Every inheritable column is written, so that no field is left holding
     * whatever the previous save put there.
     *
     * A column silently skipped is the failure this guards against: the value
     * would be stale rather than absent, and stale is what export and backup
     * cannot survive.
     */
    public function test_every_inheritable_column_is_written(): void {
        [$own, $resolved] = $this->chain([]);

        $columns = legacy_converter::question_columns($own, $resolved);

        $expected = [];
        foreach (field_registry::all() as $name => $field) {
            if ($field->kind !== field_kind::INSTANCE && $field->incolumn) {
                $expected[] = $name;
            }
        }

        sort($expected);
        $written = array_keys($columns);
        sort($written);
        $this->assertSame($expected, $written);
    }

    /**
     * An empty integer box means "not set", not zero.
     *
     * Decision 2 turns on the difference, and the form is where it bites: an
     * author clearing the CPU time limit means "no limit", and `isset()` is
     * what `get_sandbox_params()` tests, so a cleared box stored as 0 would be
     * a limit of zero seconds and every run of that question would fail. A
     * deliberate 0 is still a 0.
     */
    public function test_an_empty_integer_box_is_not_set_rather_than_zero(): void {
        $this->assertNull(legacy_converter::cast('cputimelimitsecs', ''));
        $this->assertNull(legacy_converter::cast('cputimelimitsecs', '  '));
        $this->assertSame(0, legacy_converter::cast('cputimelimitsecs', '0'));
        $this->assertSame(5, legacy_converter::cast('cputimelimitsecs', '5'));
        // A string field keeps its empty string, which is a value for it.
        $this->assertSame('', legacy_converter::cast('acelang', ''));
    }

    /**
     * The forward converter reads back exactly the overrides the columns were
     * written from.
     *
     * This is the property the whole arrangement rests on, and it is asserted
     * over the fields rather than over one example: a question's columns are
     * converted to overrides, written back to columns, and converted again, and
     * the two override sets must be identical.
     */
    public function test_the_forward_converter_reads_back_the_same_overrides(): void {
        $row = [
            // Inherited in CodeRunner 5: set means overridden, null means not.
            'template' => 'MY TEMPLATE',
            'grader' => 'EqualityGrader',
            'language' => null,
            'memlimitmb' => null,
            'acelang' => 'python3',
            // Collected, and the question's own.
            'templateparams' => '{"yyy": 2}',
            'uiparameters' => null,
            // Read straight from the column by CodeRunner 5, so pinned.
            'penaltyregime' => '10, 20, ...',
            'allornothing' => 1,
            'precheck' => 2,
            'answerboxlines' => 15,
            'twigall' => 0,
        ];

        $first = legacy_converter::question_overrides($row, 'the question');
        $resolved = prototype_chain::from_arrays([
            'python3' => ['template' => 'PROTOTYPE TEMPLATE', 'penaltyregime' => '100'],
            'the question' => $first->to_array(),
        ])->resolve();

        $again = legacy_converter::question_overrides(
            legacy_converter::question_columns($first, $resolved),
            'the question'
        );

        $this->assertEquals($first->to_array(), $again->to_array());
    }

    /**
     * Dropping the override of a directly read field leaves a question that
     * means the same thing.
     *
     * An author clearing such an override is asking for something CodeRunner 5
     * could not express, so this is the one case that is not a true inverse:
     * converting the columns again pins the field at the value it now inherits.
     * The question behaves identically either way, which is what matters, and
     * nothing re-converts a saved question in any case — the migration skips
     * every question that already has a prototype id.
     */
    public function test_dropping_an_override_of_a_directly_read_field_keeps_the_question_the_same(): void {
        [$own, $resolved] = $this->chain(['template' => 'MY TEMPLATE']);

        $columns = legacy_converter::question_columns($own, $resolved);
        $reconverted = legacy_converter::question_overrides($columns, 'the question');

        // The penalty regime was not overridden, so it is pinned at the value
        // the chain gave it rather than being absent.
        $this->assertFalse($own->has('penaltyregime'));
        $this->assertSame('10, 20, ...', $reconverted->get('penaltyregime'));
        $this->assertSame($resolved->get('penaltyregime'), $reconverted->get('penaltyregime'));

        // And the field that was overridden is unchanged.
        $this->assertSame('MY TEMPLATE', $reconverted->get('template'));
    }
}
