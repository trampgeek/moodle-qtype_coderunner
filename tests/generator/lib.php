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

/**
 * Data generator for CodeRunner.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_coderunner_manager\prototype_repository;
use qtype_coderunner\v6\field_registry;
use qtype_coderunner\v6\override_set;

/**
 * Creates the things a CodeRunner test needs that are not questions.
 *
 * Prototypes are stored by local_coderunner_manager, but they are made here
 * because qtype_coderunner declares that plugin as a dependency and so may
 * call it. The reverse would not hold: the manager declares no dependency on
 * the question type, and could not, since that would be a cycle.
 */
class qtype_coderunner_generator extends component_generator_base {
    /**
     * Creates a prototype.
     *
     * Named fields are the prototype's own; everything else in the row is a
     * setting it overrides, validated by {@see override_set}, which rejects a
     * name the field registry does not know.
     *
     * @param array|stdClass $data name, optional courseid, parent and summary,
     *      plus any number of CodeRunner settings to override.
     * @return \local_coderunner_manager\prototype the new prototype.
     */
    public function create_prototype($data) {
        $data = (array) $data;

        if (empty($data['name'])) {
            throw new coding_exception('A prototype must be given a name.');
        }
        $name = $data['name'];

        // No course named means a site prototype, whose scope is recorded as
        // SITEID rather than 0; see prototype::is_site_prototype().
        $courseid = (int) ($data['courseid'] ?? SITEID);

        $repository = new prototype_repository();

        $parentid = null;
        if (!empty($data['parent'])) {
            $parent = $repository->find_by_name_for_course($data['parent'], $courseid);
            if ($parent === null) {
                throw new coding_exception(
                    "Cannot make '{$name}': no prototype named '{$data['parent']}' " .
                    "is in scope for course {$courseid}."
                );
            }
            $parentid = $parent->id;
        }

        $reserved = ['name', 'course', 'courseid', 'parent', 'summary'];
        $overrides = [];
        foreach ($data as $field => $value) {
            if (in_array($field, $reserved, true)) {
                continue;
            }
            [$field, $value] = self::resolve_fixture_file($field, $value);
            $overrides[$field] = $value;
        }

        return $repository->create(
            $name,
            $courseid,
            new override_set($overrides),
            $parentid,
            $data['summary'] ?? ''
        );
    }

    /**
     * Expands a `<field>file` column into that field's value, read from a fixture.
     *
     * A Gherkin table cell cannot hold a newline dependably - the `\n` escape
     * is honoured only under the newer table-cell parsing mode - and a
     * twenty-line combinator template would be unreadable on one line even if
     * it were. So a template, a global extra or any other multi-line setting
     * is named rather than quoted: `templatefile | combinator_grader.py` takes
     * the `template` override from `tests/fixtures/combinator_grader.py`.
     *
     * Only a column whose name without the suffix is a real field is treated
     * this way, so an ordinary setting that happens to end in those letters is
     * left alone and a misspelling still reaches {@see override_set}, which
     * refuses it.
     *
     * @param string $field the column name.
     * @param mixed $value the cell's value.
     * @return array the field name and value to override with.
     */
    protected static function resolve_fixture_file(string $field, $value): array {
        if (!str_ends_with($field, 'file') || field_registry::exists($field)) {
            return [$field, $value];
        }

        $target = substr($field, 0, -strlen('file'));
        if (!field_registry::exists($target)) {
            return [$field, $value];
        }

        $path = __DIR__ . '/../fixtures/' . $value;
        if (!is_readable($path)) {
            throw new coding_exception(
                "Cannot set '{$target}' from fixture '{$value}': no readable file at {$path}."
            );
        }

        return [$target, file_get_contents($path)];
    }
}
