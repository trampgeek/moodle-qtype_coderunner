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
 * Unit tests for the prototype chain a Moodle XML export carries.
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
 * Tests what a question's Moodle XML carries of its prototype chain.
 *
 * There is one format (decision 32): a question always names its prototype by
 * uuid, and the chain's definition is written once per file, in the first
 * question that needs it. Built-ins and *Site defaults* never travel, because
 * the receiving site finds them for itself (decision 34).
 */
#[\PHPUnit\Framework\Attributes\CoversClass(chain_transport::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class chain_transport_test extends \advanced_testcase {
    /** @var int the course whose bank holds the questions. */
    private int $courseid;

    /** @var int the question category. */
    private int $categoryid;

    /** @var prototype_repository the manager's repository. */
    private prototype_repository $repo;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        chain_transport::reset();

        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
        $this->categoryid = (int) $this->getDataGenerator()
            ->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => \context_course::instance($this->courseid)->id])
            ->id;
        $this->repo = new prototype_repository();
    }

    /**
     * A question names its prototype by uuid and carries its definition.
     */
    public function test_a_question_carries_its_prototypes_definition(): void {
        $prototype = $this->repo->create(
            'carried_type',
            SITEID,
            new override_set(['template' => 'PROTOTYPE TEMPLATE'], 'carried_type'),
            null,
            'A summary'
        );

        $xml = $this->export([$this->make_question($prototype->id, 'carried_type')]);

        $this->assertStringContainsString(
            "<prototypeuuid>{$prototype->uuid}</prototypeuuid>",
            $xml,
            'The uuid is how the receiving site recognises a prototype it already has.'
        );
        $this->assertStringContainsString('<prototypechain>', $xml);
        $this->assertStringContainsString("<uuid>{$prototype->uuid}</uuid>", $xml);
        $this->assertStringContainsString('<text>carried_type</text>', $xml);
        $this->assertStringContainsString('PROTOTYPE TEMPLATE', $xml);
        $this->assertStringContainsString('<text>A summary</text>', $xml);
    }

    /**
     * A chain's definition is written once per file, in the first question.
     *
     * The reason the definition is not simply attached to every question:
     * python3_scratchpad carries around 150 KB of support files, so a
     * two-hundred-question export would repeat them two hundred times.
     */
    public function test_a_shared_chain_is_written_once_per_file(): void {
        $prototype = $this->repo->create(
            'shared_type',
            SITEID,
            new override_set(['template' => 'SHARED TEMPLATE'], 'shared_type')
        );

        $first = $this->make_question($prototype->id, 'shared_type');
        $second = $this->make_question($prototype->id, 'shared_type');
        $format = new \qformat_xml();

        $firstxml = $format->writequestion($this->questiondata($first));
        $secondxml = $format->writequestion($this->questiondata($second));

        $this->assertStringContainsString('<prototypechain>', $firstxml);
        $this->assertStringNotContainsString(
            '<prototypechain>',
            $secondxml,
            'The second question shares the chain, so it must carry the uuid alone.'
        );
        $this->assertStringContainsString(
            "<prototypeuuid>{$prototype->uuid}</prototypeuuid>",
            $secondxml,
            'Without the uuid the second question has no way to name the chain it uses.'
        );
    }

    /**
     * A second export writes the definition again.
     *
     * The per-file state belongs to the file being written, not to the request:
     * the question type instance is shared, so an export that reused another
     * export's record would silently omit the definition.
     */
    public function test_a_second_export_carries_the_definition_again(): void {
        $prototype = $this->repo->create(
            'second_export_type',
            SITEID,
            new override_set(['template' => 'T'], 'second_export_type')
        );
        $questionid = $this->make_question($prototype->id, 'second_export_type');

        $this->assertStringContainsString('<prototypechain>', $this->export([$questionid]));
        $this->assertStringContainsString('<prototypechain>', $this->export([$questionid]));
    }

    /**
     * A built-in ancestor does not travel, but its uuid does.
     *
     * The receiving site has its own copy, found by a uuid derived from the
     * canonical name, so carrying the definition would hand it a second one.
     */
    public function test_a_builtin_ancestor_is_named_but_not_carried(): void {
        // Made here rather than taken from the installed built-ins, which a
        // test database does not have: the rule under test is the flag.
        // *Site defaults* is excluded by the same condition.
        $builtin = $this->repo->create(
            'a_builtin',
            SITEID,
            new override_set(['template' => 'BUILT-IN TEMPLATE'], 'a_builtin'),
            null,
            '',
            '',
            true
        );

        $child = $this->repo->create(
            'child_of_builtin',
            SITEID,
            new override_set(['template' => 'CHILD TEMPLATE'], 'child_of_builtin'),
            $builtin->id
        );

        $xml = $this->export([$this->make_question($child->id, 'child_of_builtin')]);

        $this->assertStringContainsString("<uuid>{$child->uuid}</uuid>", $xml);
        $this->assertStringNotContainsString(
            "<uuid>{$builtin->uuid}</uuid>",
            $xml,
            'A built-in must not be carried: the receiving site has its own.'
        );
        $this->assertStringContainsString(
            "<parentuuid>{$builtin->uuid}</parentuuid>",
            $xml,
            'The child still has to say which built-in it hangs from.'
        );
    }

    /**
     * A chain of two user prototypes travels whole, root first.
     *
     * Root first because an import creates parents before children and the
     * file's order is the only ordering it has.
     */
    public function test_a_two_level_chain_travels_root_first(): void {
        $parent = $this->repo->create(
            'parent_type',
            SITEID,
            new override_set(['template' => 'PARENT TEMPLATE'], 'parent_type')
        );
        $child = $this->repo->create(
            'child_type',
            SITEID,
            new override_set(['penaltyregime' => '10, 20, ...'], 'child_type'),
            $parent->id
        );

        $xml = $this->export([$this->make_question($child->id, 'child_type')]);

        $parentat = strpos($xml, "<uuid>{$parent->uuid}</uuid>");
        $childat = strpos($xml, "<uuid>{$child->uuid}</uuid>");
        $this->assertNotFalse($parentat, 'The parent is a user prototype, so it travels.');
        $this->assertNotFalse($childat);
        $this->assertLessThan($childat, $parentat, 'The parent must come first.');
        $this->assertStringContainsString("<parentuuid>{$parent->uuid}</parentuuid>", $xml);
    }

    /**
     * A prototype's support files travel with its definition.
     */
    public function test_support_files_travel_with_the_definition(): void {
        $prototype = $this->repo->create(
            'filed_type',
            SITEID,
            new override_set(['template' => 'T'], 'filed_type')
        );
        get_file_storage()->create_file_from_string([
            'contextid' => $this->repo->file_contextid($prototype),
            'component' => prototype_repository::COMPONENT,
            'filearea' => prototype_repository::FILEAREA,
            'itemid' => $prototype->currentversion->id,
            'filepath' => '/',
            'filename' => 'helper.py',
        ], 'print("support")');

        $xml = $this->export([$this->make_question($prototype->id, 'filed_type')]);

        $this->assertStringContainsString('<files>', $xml);
        $this->assertStringContainsString('name="helper.py"', $xml);
        $this->assertStringContainsString(base64_encode('print("support")'), $xml);
    }

    /**
     * What is written is well-formed, and sits where an import will look.
     *
     * The paths asserted here are the reader's contract: `core\xml_parser`
     * is what `qformat_xml::readquestions()` runs, and `import_from_xml()` is
     * handed one question's subtree of exactly this shape.
     */
    public function test_the_written_xml_parses_where_the_reader_looks(): void {
        $prototype = $this->repo->create(
            'parsed_type',
            SITEID,
            new override_set(['template' => 'PARSED TEMPLATE'], 'parsed_type')
        );

        $xml = $this->export([$this->make_question($prototype->id, 'parsed_type')]);
        $data = (new \core\xml_parser())->parse('<quiz>' . $xml . '</quiz>', 0, 'UTF-8', true);
        $question = $data['quiz']['#']['question'][0];

        $this->assertSame(
            $prototype->uuid,
            $question['#']['prototypeuuid'][0]['#']
        );
        $definition = $question['#']['prototypechain'][0]['#']['prototype'][0];
        $this->assertSame($prototype->uuid, $definition['#']['uuid'][0]['#']);
        $this->assertSame('parsed_type', $definition['#']['name'][0]['#']['text'][0]['#']);
        $this->assertSame(
            '{"template":"PARSED TEMPLATE"}',
            $definition['#']['overrides'][0]['#']['text'][0]['#']
        );
        $this->assertSame('', $definition['#']['parentuuid'][0]['#']);
    }

    /**
     * A question with no prototype carries neither element.
     *
     * A legacy prototype question, and a question whose prototype could not be
     * resolved, both travel on the name in `coderunnertype` as they always did.
     */
    public function test_a_question_with_no_prototype_carries_nothing(): void {
        $xml = $this->export([$this->make_question(null, 'python3')]);

        $this->assertStringNotContainsString('<prototypeuuid>', $xml);
        $this->assertStringNotContainsString('<prototypechain>', $xml);
    }

    /**
     * The Moodle XML for one or more questions, written as one file.
     *
     * @param array $questionids the questions to export, in file order.
     * @return string the XML.
     */
    private function export(array $questionids): string {
        $format = new \qformat_xml();
        $xml = '';
        foreach ($questionids as $questionid) {
            $xml .= $format->writequestion($this->questiondata($questionid));
        }

        return $xml;
    }

    /**
     * A question's data as the export path loads it.
     *
     * `export_process` stops get_question_options() resolving the chain, so
     * export sees the raw columns and the two CodeRunner 6 ones.
     *
     * @param int $questionid the question.
     * @return object the question data.
     */
    private function questiondata(int $questionid): object {
        $questiondata = \question_bank::load_question_data($questionid);
        $questiondata->export_process = true;
        get_question_options($questiondata);
        $questiondata->qtype = 'coderunner';

        return $questiondata;
    }

    /**
     * A CodeRunner question in the course's bank, using the given prototype.
     *
     * Written with raw SQL rather than through the form, because the point is
     * what export makes of the stored columns.
     *
     * @param int|null $prototypeid the prototype it uses, or null for none.
     * @param string $name the prototype's name, as `coderunnertype` holds it.
     * @return int the question id.
     */
    private function make_question(?int $prototypeid, string $name): int {
        global $DB;

        $question = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question(
            'coderunner',
            null,
            ['category' => $this->categoryid, 'name' => 'Exported question']
        );
        $DB->update_record('question_coderunner_options', (object) [
            'id' => $DB->get_field('question_coderunner_options', 'id', ['questionid' => $question->id]),
            'prototypeid' => $prototypeid,
            'coderunnertype' => $name,
            'overrides' => '{}',
            'template' => null,
        ]);
        \question_bank::notify_question_edited($question->id);

        return (int) $question->id;
    }
}
