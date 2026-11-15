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
 * Unit tests for the prototype data generator.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

defined('MOODLE_INTERNAL') || die();

/**
 * The generator exists so that a feature whose subject is something else can
 * say which prototypes it needs without driving the editor. These tests cover
 * what a feature would rely on: the overrides are really stored, a parent named
 * by name is resolved, and a misspelled setting is refused rather than silently
 * dropped.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner_generator::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class prototype_generator_test extends \advanced_testcase {
    /** @var \qtype_coderunner_generator the generator under test. */
    protected $generator;

    /** @var \stdClass a course to own the prototypes. */
    protected $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('qtype_coderunner');
    }

    /**
     * A row's settings become the prototype's overrides.
     */
    public function test_a_prototype_is_created_holding_its_overrides(): void {
        $prototype = $this->generator->create_prototype([
            'name' => 'python3_generated',
            'courseid' => $this->course->id,
            'template' => 'MY TEMPLATE',
        ]);

        $this->assertSame('python3_generated', $prototype->name);
        $this->assertSame((int) $this->course->id, $prototype->courseid);
        $this->assertSame('MY TEMPLATE', $prototype->currentversion->overrides->get('template'));
    }

    /**
     * A parent given by name is resolved to that prototype.
     */
    public function test_a_parent_is_resolved_by_name(): void {
        $parent = $this->generator->create_prototype([
            'name' => 'the_parent',
            'courseid' => $this->course->id,
            'template' => 'PARENT TEMPLATE',
        ]);
        $child = $this->generator->create_prototype([
            'name' => 'the_child',
            'courseid' => $this->course->id,
            'parent' => 'the_parent',
        ]);

        $chain = (new \local_coderunner_manager\prototype_repository())->chain_prototypes($child->id);
        $ids = array_map(fn($p) => $p->id, $chain);
        $this->assertContains($parent->id, $ids);
    }

    /**
     * A parent that is not in scope is an error, not a prototype with no parent.
     */
    public function test_an_unknown_parent_is_refused(): void {
        $this->expectException(\coding_exception::class);
        $this->generator->create_prototype([
            'name' => 'orphan',
            'courseid' => $this->course->id,
            'parent' => 'no_such_prototype',
        ]);
    }

    /**
     * A misspelled setting is refused by the override set rather than stored.
     */
    public function test_an_unknown_setting_is_refused(): void {
        $this->expectException(\coding_exception::class);
        $this->generator->create_prototype([
            'name' => 'typo',
            'courseid' => $this->course->id,
            'tempalte' => 'MY TEMPLATE',
        ]);
    }

    /**
     * A `<field>file` column takes that field's value from a fixture.
     *
     * A Gherkin table cell cannot hold a newline dependably, so a multi-line
     * setting - a combinator template, for one - is named instead of quoted.
     */
    public function test_a_field_can_be_read_from_a_fixture_file(): void {
        $prototype = $this->generator->create_prototype([
            'name' => 'from_a_fixture',
            'courseid' => $this->course->id,
            'templatefile' => 'combinator_grader_template.py',
        ]);

        $template = $prototype->currentversion->overrides->get('template');
        $this->assertStringContainsString("I am a prologue", $template);
        $this->assertStringContainsString("\n", $template, 'The fixture lost its line structure.');
    }

    /**
     * A fixture that is not there is an error, not an empty setting.
     */
    public function test_a_missing_fixture_file_is_refused(): void {
        $this->expectException(\coding_exception::class);
        $this->generator->create_prototype([
            'name' => 'no_such_fixture',
            'courseid' => $this->course->id,
            'templatefile' => 'there_is_no_such_file.py',
        ]);
    }

    /**
     * Naming no course makes a site prototype, whose scope is SITEID.
     */
    public function test_a_prototype_with_no_course_is_a_site_prototype(): void {
        $prototype = $this->generator->create_prototype(['name' => 'site_wide']);

        $this->assertSame((int) SITEID, $prototype->courseid);
        $this->assertTrue($prototype->is_site_prototype());
    }
}
