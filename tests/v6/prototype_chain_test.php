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
 * Unit tests for resolving a prototype chain.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * Tests that a chain of layers resolves to the right effective settings, with
 * the right provenance, at more than two levels.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prototype_chain::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_settings::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class prototype_chain_test extends \basic_testcase {
    /**
     * The worked example from the design document: four layers, with each
     * value coming from the nearest layer that set it.
     */
    public function test_four_layer_chain_resolves_as_documented(): void {
        $settings = prototype_chain::from_arrays([
            'python3' => ['template' => 'PY3', 'language' => 'python3', 'cputimelimitsecs' => 5],
            'python3_scratchpad' => ['template' => 'SCRATCH', 'grader' => 'TemplateGrader', 'uiplugin' => 'scratchpad'],
            'cosc131_scratchpad' => ['penaltyregime' => '0, 10, ...', 'cputimelimitsecs' => 2],
            'the question' => ['globalextra' => 'data'],
        ])->resolve();

        $this->assertSame('SCRATCH', $settings->get('template'));
        $this->assertSame('python3_scratchpad', $settings->source_of('template'));

        $this->assertSame('python3', $settings->get('language'));
        $this->assertSame('python3', $settings->source_of('language'));

        $this->assertSame(2, $settings->get('cputimelimitsecs'));
        $this->assertSame('cosc131_scratchpad', $settings->source_of('cputimelimitsecs'));

        $this->assertSame('data', $settings->get('globalextra'));
        $this->assertSame('the question', $settings->source_of('globalextra'));
    }

    /**
     * A field no layer sets takes its registry default, and reports that no
     * layer supplied it.
     */
    public function test_unset_fields_take_the_registry_default(): void {
        $settings = prototype_chain::from_arrays(['root' => ['template' => 'x']])->resolve();

        $this->assertSame(field_registry::get('allornothing')->default, $settings->get('allornothing'));
        $this->assertFalse($settings->was_set('allornothing'));
        $this->assertSame('', $settings->source_of('allornothing'));

        $this->assertTrue($settings->was_set('template'));
        $this->assertSame('root', $settings->source_of('template'));
    }

    /**
     * An empty string set by a descendant overrides an inherited value. This is
     * the behaviour today's code cannot express, so it is the case most worth
     * pinning down.
     */
    public function test_a_descendant_can_blank_an_inherited_value(): void {
        $settings = prototype_chain::from_arrays([
            'root' => ['testsplitterre' => '/SPLIT/'],
            'child' => ['testsplitterre' => ''],
        ])->resolve();

        $this->assertSame('', $settings->get('testsplitterre'));
        $this->assertSame('child', $settings->source_of('testsplitterre'));
    }

    /**
     * The same for an explicit null, which must not read as "inherited".
     */
    public function test_a_descendant_can_null_an_inherited_value(): void {
        $settings = prototype_chain::from_arrays([
            'root' => ['sandbox' => 'jobesandbox'],
            'child' => ['sandbox' => null],
        ])->resolve();

        $this->assertNull($settings->get('sandbox'));
        $this->assertSame('child', $settings->source_of('sandbox'));
        $this->assertTrue($settings->was_set('sandbox'));
    }

    /**
     * A collected field keeps every layer's contribution, in root-first order,
     * because merging template parameters requires evaluating each layer in its
     * own language first.
     */
    public function test_collected_fields_keep_every_layer_in_order(): void {
        $settings = prototype_chain::from_arrays([
            'root' => ['templateparams' => '{"a":1}', 'templateparamslang' => 'twig'],
            'middle' => ['templateparams' => '{"b":2}'],
            'leaf' => ['templateparams' => '{"a":9}', 'templateparamslang' => 'python3'],
        ])->resolve();

        $layers = $settings->layers_of('templateparams');
        $this->assertCount(3, $layers);
        $this->assertSame(['root', 'middle', 'leaf'], array_column($layers, 'source'));
        $this->assertSame(['{"a":1}', '{"b":2}', '{"a":9}'], array_column($layers, 'value'));

        // The language travels with the parameters, per layer, not merged.
        $langs = $settings->layers_of('templateparamslang');
        $this->assertSame(['root', 'leaf'], array_column($langs, 'source'));
    }

    /**
     * A layer that does not set a collected field contributes nothing to it,
     * rather than contributing an empty value.
     */
    public function test_layers_that_set_nothing_do_not_contribute(): void {
        $settings = prototype_chain::from_arrays([
            'root' => ['templateparams' => '{"a":1}'],
            'middle' => ['template' => 'x'],
            'leaf' => ['templateparams' => '{"b":2}'],
        ])->resolve();

        $this->assertSame(['root', 'leaf'], array_column($settings->layers_of('templateparams'), 'source'));
    }

    /**
     * A collected field has no single value, and asking for one is a
     * programming error rather than a silently wrong answer.
     */
    public function test_getting_a_collected_field_as_one_value_throws(): void {
        $settings = prototype_chain::from_arrays(['root' => ['templateparams' => '{}']])->resolve();

        $this->expectException(\coding_exception::class);
        $settings->get('templateparams');
    }

    /**
     * And the converse, so the two kinds of field cannot be confused either way.
     */
    public function test_getting_layers_of_a_resolved_field_throws(): void {
        $settings = prototype_chain::from_arrays(['root' => ['template' => 'x']])->resolve();

        $this->expectException(\coding_exception::class);
        $settings->layers_of('template');
    }

    /**
     * The chain records its layers in order, which is what identifies it for
     * caching and for the prototype versions pinned into an attempt.
     */
    public function test_the_chain_reports_its_layers_in_order(): void {
        $chain = prototype_chain::from_arrays([
            'python3' => [],
            'python3_scratchpad' => [],
            'question' => [],
        ]);

        $this->assertSame(3, $chain->depth());
        $this->assertSame(['python3', 'python3_scratchpad', 'question'], $chain->source_labels());
        $this->assertSame($chain->source_labels(), $chain->resolve()->chain);
    }

    /**
     * A chain deeper than the advisory limit is allowed but flagged, since
     * nothing technical requires a limit and resolved settings are cached.
     */
    public function test_depth_beyond_the_advisory_limit_is_flagged_not_refused(): void {
        $layers = [];
        for ($i = 0; $i <= prototype_chain::ADVISORY_MAX_DEPTH; $i++) {
            $layers['layer' . $i] = [];
        }
        $chain = new prototype_chain(array_map(
            fn($source) => new override_set([], $source),
            array_keys($layers)
        ));

        $this->assertTrue($chain->exceeds_advisory_depth());
        $this->assertSame(prototype_chain::ADVISORY_MAX_DEPTH + 1, $chain->depth());
    }

    /**
     * A chain beyond the hard limit is refused, to catch runaway data.
     */
    public function test_depth_beyond_the_hard_limit_is_refused(): void {
        $layers = [];
        for ($i = 0; $i <= prototype_chain::HARD_MAX_DEPTH; $i++) {
            $layers[] = new override_set([], 'layer' . $i);
        }

        $this->expectException(\coding_exception::class);
        new prototype_chain($layers);
    }

    /**
     * A chain that visits the same prototype twice contains a cycle, which must
     * be refused rather than resolved into nonsense.
     */
    public function test_a_repeated_layer_is_refused_as_a_cycle(): void {
        $this->expectException(\coding_exception::class);
        new prototype_chain([
            new override_set([], 'python3'),
            new override_set([], 'scratchpad'),
            new override_set([], 'python3'),
        ]);
    }

    /**
     * A prototype has no instance data, so an instance field in any layer but
     * the last means the chain was built wrongly.
     */
    public function test_a_prototype_layer_may_not_supply_instance_fields(): void {
        $this->expectException(\coding_exception::class);
        new prototype_chain([
            new override_set(['answer' => 'print(42)'], 'a prototype'),
            new override_set([], 'the question'),
        ]);
    }

    /**
     * The question itself may of course supply instance fields.
     */
    public function test_the_last_layer_may_supply_instance_fields(): void {
        $settings = prototype_chain::from_arrays([
            'a prototype' => ['template' => 'x'],
            'the question' => ['answer' => 'print(42)', 'answerpreload' => '# start here'],
        ])->resolve();

        $this->assertSame('print(42)', $settings->get('answer'));
        $this->assertSame('the question', $settings->source_of('answer'));
    }

    /**
     * A chain of one layer, which is what a question with no prototype that
     * sets everything itself looks like.
     */
    public function test_a_single_layer_chain_resolves(): void {
        $settings = prototype_chain::from_arrays(['only' => ['template' => 'x']])->resolve();

        $this->assertSame(1, count($settings->chain));
        $this->assertSame('x', $settings->get('template'));
        $this->assertSame('only', $settings->source_of('template'));
    }

    /**
     * An empty chain resolves to the registry defaults throughout, which is the
     * Site defaults prototype's role at the root of every chain.
     */
    public function test_an_empty_chain_resolves_to_the_defaults(): void {
        $settings = (new prototype_chain([]))->resolve();

        foreach (field_registry::all() as $name => $field) {
            if ($field->resolves_to_one_value()) {
                $this->assertSame($field->default, $settings->get($name), "Field $name should take its default.");
                $this->assertFalse($settings->was_set($name));
            } else {
                $this->assertSame([], $settings->layers_of($name));
            }
        }
    }

    /**
     * Every field that resolves to one value appears in the resolved array, so
     * a consumer can rely on the whole set being present.
     */
    public function test_every_resolved_field_is_present(): void {
        $settings = prototype_chain::from_arrays(['root' => []])->resolve();
        $resolved = $settings->to_array();

        foreach (field_registry::all() as $name => $field) {
            if ($field->resolves_to_one_value()) {
                $this->assertArrayHasKey($name, $resolved, "Field $name missing from the resolved settings.");
            } else {
                $this->assertArrayNotHasKey($name, $resolved, "Collected field $name should not have a single value.");
            }
        }
    }
}
