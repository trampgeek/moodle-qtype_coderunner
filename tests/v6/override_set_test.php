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
 * Unit tests for one layer of a prototype chain.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * Tests that a layer distinguishes an absent field from an empty one, and
 * rejects field names that do not exist.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(override_set::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class override_set_test extends \basic_testcase {
    /**
     * A field the layer does not mention is inherited; one it sets to the empty
     * string is an override to the empty string. Today's code cannot express
     * the second, so this is the rule the class exists to keep.
     */
    public function test_absent_is_not_the_same_as_empty(): void {
        $set = new override_set(['template' => '', 'language' => 'python3']);

        $this->assertTrue($set->has('template'), 'An empty string is an override.');
        $this->assertSame('', $set->get('template'));

        $this->assertFalse($set->has('grader'), 'An unmentioned field is inherited.');
        $this->assertNull($set->get('grader'));
    }

    /**
     * A field explicitly set to null is still an override, so that a prototype's
     * value can be blanked by a descendant.
     */
    public function test_explicit_null_is_an_override(): void {
        $set = new override_set(['sandbox' => null]);

        $this->assertTrue($set->has('sandbox'));
        $this->assertNull($set->get('sandbox'));
        $this->assertSame(['sandbox'], $set->names());
    }

    /**
     * A field may be named either by its registry entry or by its name.
     */
    public function test_fields_may_be_given_as_objects_or_names(): void {
        $set = new override_set(['memlimitmb' => 64]);
        $field = field_registry::get('memlimitmb');

        $this->assertTrue($set->has($field));
        $this->assertSame(64, $set->get($field));
        $this->assertSame($set->get('memlimitmb'), $set->get($field));
    }

    /**
     * A mistyped field name is a programming error, not a silently unset field.
     */
    public function test_unknown_field_name_in_constructor_throws(): void {
        $this->expectException(\coding_exception::class);
        new override_set(['tempalte' => 'oops'], 'a layer');
    }

    /**
     * The same applies when reading, so a typo cannot read as "inherited".
     */
    public function test_unknown_field_name_on_read_throws(): void {
        $set = new override_set(['template' => 'x']);
        $this->expectException(\coding_exception::class);
        $set->has('tempalte');
    }

    /**
     * with() and without() return new sets, leaving the original alone, so a
     * layer can be shared without being copied defensively.
     */
    public function test_with_and_without_do_not_mutate(): void {
        $original = new override_set(['template' => 'x'], 'root');

        $added = $original->with('grader', 'TemplateGrader');
        $this->assertFalse($original->has('grader'), 'The original must be unchanged.');
        $this->assertTrue($added->has('grader'));
        $this->assertSame('root', $added->source, 'The source label carries over.');

        $removed = $added->without('template');
        $this->assertTrue($added->has('template'), 'The original must be unchanged.');
        $this->assertFalse($removed->has('template'));
        $this->assertTrue($removed->has('grader'));
    }

    /**
     * with() can set the empty string, which must register as an override
     * rather than removing the field.
     */
    public function test_with_can_set_an_empty_override(): void {
        $set = (new override_set())->with('testsplitterre', '');

        $this->assertTrue($set->has('testsplitterre'));
        $this->assertSame('', $set->get('testsplitterre'));
        $this->assertSame(1, $set->count());
    }

    /**
     * An empty layer supplies nothing, which is what a prototype that overrides
     * nothing looks like.
     */
    public function test_an_empty_layer_supplies_nothing(): void {
        $set = new override_set();

        $this->assertSame(0, $set->count());
        $this->assertSame([], $set->names());
        $this->assertSame([], $set->fields());
        $this->assertSame([], $set->to_array());
    }

    /**
     * fields() returns registry entries, so a caller can ask a layer what kind
     * of fields it supplies without consulting the registry itself.
     */
    public function test_fields_returns_registry_entries(): void {
        $set = new override_set(['template' => 'x', 'templateparams' => '{}']);
        $fields = $set->fields();

        $this->assertSame(['template', 'templateparams'], array_keys($fields));
        $this->assertInstanceOf(field::class, $fields['template']);
        $this->assertSame(field_kind::INHERITED, $fields['template']->kind);
        $this->assertSame(field_kind::COLLECTED, $fields['templateparams']->kind);
    }

    /**
     * A field one set has and the other does not is an addition or a removal,
     * and a field both have with different values is a change.
     */
    public function test_differences_names_what_changed(): void {
        $before = new override_set(['template' => 'OLD', 'grader' => 'EqualityGrader'], 'v1');
        $after = new override_set(['template' => 'NEW', 'penaltyregime' => '0, 10, ...'], 'v2');

        $diff = $after->differences_from($before);

        $this->assertSame('changed', $diff['template']['change']);
        $this->assertSame('OLD', $diff['template']['from']);
        $this->assertSame('NEW', $diff['template']['to']);
        $this->assertSame('removed', $diff['grader']['change']);
        $this->assertSame('added', $diff['penaltyregime']['change']);
    }

    /**
     * A field neither set supplies is not a difference, so a diff lists only
     * what an author actually did.
     */
    public function test_untouched_fields_are_not_differences(): void {
        $before = new override_set(['template' => 'SAME'], 'v1');
        $after = new override_set(['template' => 'SAME'], 'v2');

        $this->assertSame([], $after->differences_from($before));
    }

    /**
     * Values are compared as the registry compares them, so a stored '1' and a
     * resolved 1 do not show up as a change that nobody made.
     */
    public function test_differences_use_the_registry_normalisation(): void {
        $before = new override_set(['allornothing' => '1'], 'v1');
        $after = new override_set(['allornothing' => 1], 'v2');

        $this->assertSame([], $after->differences_from($before));
    }

    /**
     * Blanking a field is a change, not a removal: the layer still supplies a
     * value, and that value is the empty string.
     */
    public function test_blanking_a_field_is_a_change_not_a_removal(): void {
        $before = new override_set(['template' => 'SOMETHING'], 'v1');
        $after = new override_set(['template' => ''], 'v2');

        $diff = $after->differences_from($before);

        $this->assertSame('changed', $diff['template']['change']);
        $this->assertSame('', $diff['template']['to']);
    }
}
