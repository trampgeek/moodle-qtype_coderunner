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
 * Unit tests for the CodeRunner 6 field registry.
 *
 * These are structural tests: they check the registry against the database
 * schema and against the field lists the current code uses, so that a wrong or
 * forgotten registry entry fails here rather than surfacing during migration.
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
 * Tests that the field registry describes the question fields correctly.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(field_registry::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class field_registry_test extends \advanced_testcase {
    /**
     * Every field in today's extra_question_fields_static() must be either a
     * registry field or an explicitly declared legacy column, so that no field
     * can be forgotten when the options row is converted.
     */
    public function test_every_current_field_is_accounted_for(): void {
        $extra = \qtype_coderunner::extra_question_fields_static();
        array_shift($extra);  // Discard the table name.

        $accounted = array_merge(
            array_keys(field_registry::all()),
            array_keys(field_registry::legacy_columns())
        );
        $unaccounted = array_diff($extra, $accounted);

        $this->assertEmpty(
            $unaccounted,
            'Question fields neither in the registry nor declared legacy: ' . implode(', ', $unaccounted)
        );
    }

    /**
     * A field cannot be both a live registry field and a legacy column.
     */
    public function test_no_field_is_both_live_and_legacy(): void {
        $both = array_intersect(
            array_keys(field_registry::all()),
            array_keys(field_registry::legacy_columns())
        );

        $this->assertEmpty($both, 'Fields declared both live and legacy: ' . implode(', ', $both));
    }

    /**
     * Every registry field that claims to be stored in a column must exist in
     * question_coderunner_options.
     */
    public function test_column_backed_fields_exist_in_the_schema(): void {
        global $DB;

        $columns = array_keys($DB->get_columns('question_coderunner_options'));
        $missing = [];
        foreach (field_registry::all() as $field) {
            if ($field->incolumn && !in_array($field->name, $columns)) {
                $missing[] = $field->name;
            }
        }

        $this->assertEmpty($missing, 'Fields marked incolumn but absent from the schema: ' . implode(', ', $missing));
    }

    /**
     * Nothing that is Twig expanded today may quietly stop being so, and the
     * template must stay out of the set because it is expanded later, during
     * grading, when the student's answer is available.
     */
    public function test_twigable_set_covers_current_behaviour(): void {
        $registryfields = array_keys(field_registry::all());
        $twigabletoday = array_intersect(\qtype_coderunner::twigablefields(), $registryfields);
        $twigablenow = array_keys(field_registry::twigable());

        $lost = array_diff($twigabletoday, $twigablenow);
        $this->assertEmpty($lost, 'Fields Twig expanded today but not in the registry: ' . implode(', ', $lost));
        $this->assertNotContains('template', $twigablenow, 'The template is expanded during grading, not for display.');
    }

    /**
     * Instance fields are the question's own and may not be given a merge rule
     * other than replace, since nothing is ever merged into them.
     */
    public function test_instance_fields_are_not_merged(): void {
        foreach (field_registry::of_kind(field_kind::INSTANCE) as $field) {
            $this->assertSame(
                merge_rule::REPLACE,
                $field->merge,
                "Instance field {$field->name} should not have a merge rule."
            );
            $this->assertFalse($field->is_inheritable(), "Instance field {$field->name} should not be inheritable.");
        }
    }

    /**
     * Collected fields are those the resolver cannot reduce to one value, so
     * each must carry a merge rule that says how its layers combine.
     */
    public function test_collected_fields_declare_how_they_combine(): void {
        $collected = field_registry::of_kind(field_kind::COLLECTED);
        $this->assertNotEmpty($collected, 'There should be collected fields.');
        foreach ($collected as $field) {
            $this->assertNotSame(
                merge_rule::REPLACE,
                $field->merge,
                "Collected field {$field->name} must say how its layers combine, not replace."
            );
            $this->assertFalse(
                $field->resolves_to_one_value(),
                "Collected field {$field->name} should not resolve to a single value."
            );
        }
    }

    /**
     * Inherited fields resolve to one value, so replace is the only rule that
     * makes sense for them.
     */
    public function test_inherited_fields_replace(): void {
        foreach (field_registry::of_kind(field_kind::INHERITED) as $field) {
            $this->assertSame(
                merge_rule::REPLACE,
                $field->merge,
                "Inherited field {$field->name} should use the replace rule."
            );
            $this->assertTrue($field->resolves_to_one_value());
            $this->assertTrue($field->is_inheritable());
        }
    }

    /**
     * Fields that travel together per layer must name fields that exist, and
     * must themselves be collected fields, since the grouping only means
     * anything for values gathered layer by layer.
     */
    public function test_per_layer_groupings_are_consistent(): void {
        foreach (field_registry::all() as $field) {
            foreach ($field->perlayerwith as $companion) {
                $this->assertTrue(
                    field_registry::exists($companion),
                    "Field {$field->name} travels with unknown field $companion."
                );
                $this->assertSame(
                    field_kind::COLLECTED,
                    field_registry::get($companion)->kind,
                    "Field {$field->name} travels with $companion, which is not a collected field."
                );
            }
        }
    }

    /**
     * Asking for a field that does not exist is a programming error, not a
     * silent null as it is when reading an undeclared question property today.
     */
    public function test_unknown_field_throws(): void {
        $this->assertFalse(field_registry::exists('no_such_field'));
        $this->expectException(\coding_exception::class);
        field_registry::get('no_such_field');
    }

    /**
     * Every field's name is a plain lower-case identifier, since it doubles as
     * a column name, a form element name and a language string identifier.
     *
     * The kind, type, section and merge rule need no checking: they are enums,
     * so an invalid value cannot be constructed.
     */
    public function test_field_names_are_usable_as_identifiers(): void {
        foreach (field_registry::all() as $name => $field) {
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $name,
                "Field name $name is not usable as a column or element name."
            );
        }
    }

    /**
     * Fields stored in a column must have a type that a column can hold, and
     * the one field that is not stored in a column must be the file set.
     */
    public function test_file_valued_fields_are_not_columns(): void {
        foreach (field_registry::all() as $field) {
            if ($field->type === field_type::FILES) {
                $this->assertFalse($field->incolumn, "File-valued field {$field->name} cannot be a column.");
            }
        }
    }
}
