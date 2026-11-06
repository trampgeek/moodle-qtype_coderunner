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
 * Unit tests that the question class's property docblock matches the registry.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

defined('MOODLE_INTERNAL') || die();

/**
 * The 39 question settings are reached through __get() rather than being
 * declared properties, so the only thing telling an IDE, a type checker or a
 * static analyser that they exist is the @property-read block on
 * qtype_coderunner_question. These tests assert it still lists exactly the
 * registry's columns, so the block cannot drift as fields are added or removed.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(field_registry::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class question_property_docblock_test extends \basic_testcase {
    /**
     * The field names the class docblock declares, in the order it declares
     * them.
     *
     * @return string[] the names.
     */
    private function documented_fields(): array {
        global $CFG;

        $source = file_get_contents($CFG->dirroot . '/question/type/coderunner/question.php');
        $class = strpos($source, 'class qtype_coderunner_question');
        $this->assertNotFalse($class, 'The question class was not found.');

        preg_match_all(
            '/@property-read\s+\S+\s+\$(\w+)/',
            substr($source, 0, $class),
            $matches
        );
        return $matches[1];
    }

    /**
     * Every registry field that has a column is documented, and nothing else
     * is. A field that is not a column — support files, and the test case field
     * switches — was never a property and must not be listed.
     */
    public function test_documented_fields_are_exactly_the_registry_columns(): void {
        $expected = [];
        foreach (field_registry::all() as $name => $field) {
            if ($field->incolumn) {
                $expected[] = $name;
            }
        }

        $documented = $this->documented_fields();

        $this->assertSame(
            [],
            array_values(array_diff($expected, $documented)),
            'Registry columns missing from the @property-read block in question.php.'
        );
        $this->assertSame(
            [],
            array_values(array_diff($documented, $expected)),
            'The @property-read block in question.php lists names that are not registry columns.'
        );
    }

    /**
     * The block is generated from the registry, so it is in registry order.
     * Keeping the order tied down means a regeneration produces no spurious
     * diff, which is what makes regenerating it cheap enough to actually do.
     */
    public function test_documented_fields_are_in_registry_order(): void {
        $expected = [];
        foreach (field_registry::all() as $name => $field) {
            if ($field->incolumn) {
                $expected[] = $name;
            }
        }

        $this->assertSame($expected, $this->documented_fields());
    }

    /**
     * None of the documented fields is also a declared property. A declared
     * property shadows __get(), so the two lists must be disjoint or a field
     * would be read from the wrong place.
     */
    public function test_no_documented_field_is_also_a_declared_property(): void {
        $declared = array_keys(get_class_vars(\qtype_coderunner_question::class));
        $clash = array_intersect($this->documented_fields(), $declared);

        $this->assertSame(
            [],
            array_values($clash),
            'These fields are both documented as __get() properties and declared, so the declaration wins.'
        );
    }
}
