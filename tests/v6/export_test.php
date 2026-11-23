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
 * Unit tests for what plain Moodle XML export writes.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Tests the reference-only shape of a plain Moodle XML export.
 *
 * The format is what makes a 6.0 export readable by a 5.x site: an inherited
 * field is written empty, so the importing site fills it from its own prototype
 * of the name in `coderunnertype`, exactly as CodeRunner 5 always did
 * (decision 32).
 *
 * The derivation is the save path's own `set_legacy_columns()`, so export
 * cannot drift from what save writes, and dropping the columns in phase 4 will
 * not move the format.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class export_test extends \advanced_testcase {
    /** @var int the course whose bank holds the question. */
    private int $courseid;

    /** @var int the question category. */
    private int $categoryid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
        $this->categoryid = (int) $this->getDataGenerator()
            ->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => \context_course::instance($this->courseid)->id])
            ->id;
    }

    /**
     * A question on a prototype that exists only in the manager exports its
     * inherited fields empty.
     *
     * The case the old export path could not handle. It looked the prototype up
     * among CodeRunner 5 prototype *questions*, and a prototype created through
     * the manager has none, so the lookup failed and the row was exported
     * verbatim -- every inherited value written out, and the question arriving
     * at the far end fully pinned instead of following the prototype it names.
     */
    public function test_inherited_fields_are_written_empty(): void {
        $prototype = (new prototype_repository())->create(
            'manager_only_type',
            SITEID,
            new override_set([
                'template' => 'PROTOTYPE TEMPLATE',
                'language' => 'python3',
                'grader' => 'EqualityGrader',
            ], 'manager_only_type')
        );

        $xml = $this->export($this->make_question($prototype->id, 'manager_only_type'));

        $this->assertStringContainsString(
            '<coderunnertype>manager_only_type</coderunnertype>',
            $xml,
            'The prototype is named by the one thing that is portable, so it must be written.'
        );
        foreach (['template', 'language', 'grader'] as $field) {
            $this->assertStringContainsString(
                "<$field></$field>",
                $xml,
                "An inherited field was exported with a value, so the importing site cannot supply its own."
            );
        }
    }

    /**
     * A field the question overrides is written with its value.
     *
     * The other half of the same rule: blank means inherit, so a pinned field
     * must not be blank or the override is lost in transit.
     */
    public function test_an_overridden_field_is_written_with_its_value(): void {
        $prototype = (new prototype_repository())->create(
            'overridden_type',
            SITEID,
            new override_set(['template' => 'PROTOTYPE TEMPLATE'], 'overridden_type')
        );

        $xml = $this->export($this->make_question(
            $prototype->id,
            'overridden_type',
            ['template' => 'THE QUESTIONS OWN']
        ));

        $this->assertStringContainsString('<template>THE QUESTIONS OWN</template>', $xml);
    }

    /**
     * Neither CodeRunner 6 column is written, whatever the question overrides.
     *
     * A prototype id means nothing on another site, and the overrides document
     * is derived from the columns written beside it, so an import recomputes
     * both from `coderunnertype`.
     */
    public function test_the_version_6_columns_are_not_written(): void {
        $prototype = (new prototype_repository())->create(
            'unwritten_type',
            SITEID,
            new override_set(['template' => 'T'], 'unwritten_type')
        );

        $xml = $this->export($this->make_question($prototype->id, 'unwritten_type'));

        $this->assertStringContainsString('<prototypeid></prototypeid>', $xml);
        $this->assertStringContainsString('<overrides></overrides>', $xml);
    }

    /**
     * A CodeRunner question in the course's bank, using the given prototype.
     *
     * Written with raw SQL rather than through the form, because the point is
     * what export makes of the stored columns.
     *
     * @param int $prototypeid the prototype it uses.
     * @param string $name the prototype's name, as `coderunnertype` holds it.
     * @param array $overrides the fields the question pins.
     * @return int the question id.
     */
    private function make_question(int $prototypeid, string $name, array $overrides = []): int {
        global $DB;

        $question = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question(
            'coderunner',
            null,
            ['category' => $this->categoryid, 'name' => 'Exported question']
        );

        $row = (object) [
            'id' => $DB->get_field('question_coderunner_options', 'id', ['questionid' => $question->id]),
            'prototypeid' => $prototypeid,
            'coderunnertype' => $name,
            'overrides' => json_encode($overrides),
        ];
        // The columns an inherited field must hold at rest: null means
        // inherited, which is what the migration and the save path write.
        foreach (['template', 'language', 'grader'] as $field) {
            $row->$field = $overrides[$field] ?? null;
        }
        $DB->update_record('question_coderunner_options', $row);
        \question_bank::notify_question_edited($question->id);

        return (int) $question->id;
    }

    /**
     * The Moodle XML for a question, as the export page produces it.
     *
     * `export_process` is set before the options are loaded, which is what
     * get_questions_category() does on the real export path: it stops
     * get_question_options() resolving the chain, so export sees the raw
     * columns and the two CodeRunner 6 ones.
     *
     * @param int $questionid the question to export.
     * @return string the XML.
     */
    private function export(int $questionid): string {
        $questiondata = \question_bank::load_question_data($questionid);
        $questiondata->export_process = true;
        get_question_options($questiondata);
        $questiondata->qtype = 'coderunner';

        return (new \qformat_xml())->writequestion($questiondata);
    }
}
