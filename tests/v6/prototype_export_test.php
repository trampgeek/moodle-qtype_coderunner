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
 * Unit tests for the CodeRunner 5 compatible prototype export.
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
 * Tests the one prototype export file, which both CodeRunner versions read.
 *
 * Its CodeRunner 5 half is the one place flattening happens: a chain resolved
 * into a single prototype question, which is what a 5.x site needs because
 * `get_prototype()` does not recurse. That half is read back here through the
 * same `import_from_xml()` a 5.x site would use, since what matters is not the
 * shape of the file but what an importer makes of it. Its CodeRunner 6 half is
 * `<prototypeuuid>` and `<prototypechain>`, which the same file carries beside
 * the resolved columns (decision 36).
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prototype_export::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class prototype_export_test extends \advanced_testcase {
    /** @var prototype_repository the manager's repository. */
    private prototype_repository $repo;

    /** @var int the course the prototypes belong to. */
    private int $courseid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->repo = new prototype_repository();
        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
    }

    /**
     * A two-level chain becomes one prototype question holding both layers.
     *
     * The whole point: 5.x inheritance is one level deep, so what it gets must
     * already be resolved.
     */
    public function test_a_chain_is_written_as_one_resolved_prototype_question(): void {
        $parent = $this->repo->create(
            'cosc_base',
            $this->courseid,
            new override_set([
                'template' => 'PARENT TEMPLATE',
                'language' => 'python3',
                'grader' => 'EqualityGrader',
            ], 'cosc_base')
        );
        $child = $this->repo->create(
            'cosc_child',
            $this->courseid,
            new override_set(['penaltyregime' => '10, 20, ...'], 'cosc_child'),
            $parent->id
        );

        $columns = $this->import(prototype_export::file($child, $this->repo));

        $this->assertSame('cosc_child', $columns->coderunnertype, 'The frozen type takes the leaf\'s name.');
        $this->assertSame('2', (string) $columns->prototypetype, 'It has to arrive as a user prototype.');
        $this->assertSame(
            'PARENT TEMPLATE',
            $columns->template,
            "An inherited field must be written out: there is no parent on the receiving site to take it from."
        );
        $this->assertSame('python3', $columns->language);
        $this->assertSame('EqualityGrader', $columns->grader);
        $this->assertSame('10, 20, ...', $columns->penaltyregime, "The leaf's own value must win.");
        $this->assertSame('PROTOTYPE_cosc_child', $columns->name);
    }

    /**
     * Neither CodeRunner 6 column travels, since neither means anything there.
     */
    public function test_the_version_6_columns_are_not_written(): void {
        $prototype = $this->repo->create(
            'bare_type',
            $this->courseid,
            new override_set(['template' => 'T'], 'bare_type')
        );

        $xml = prototype_export::file($prototype, $this->repo);

        $this->assertStringContainsString('<prototypeid></prototypeid>', $xml);
        $this->assertStringContainsString('<overrides></overrides>', $xml);
    }

    /**
     * The chain's support files travel, merged as the chain merges them.
     *
     * A prototype whose __pystylechecker.py stayed behind would import and
     * then fail at the first run.
     */
    public function test_support_files_travel_with_the_prototype(): void {
        $parent = $this->repo->create(
            'filed_parent',
            $this->courseid,
            new override_set(['template' => 'T'], 'filed_parent')
        );
        $child = $this->repo->create(
            'filed_child',
            $this->courseid,
            new override_set([], 'filed_child'),
            $parent->id
        );
        $this->write_file($parent, 'from_parent.py', 'PARENT FILE');
        $this->write_file($child, 'from_child.py', 'CHILD FILE');

        $xml = prototype_export::file($child, $this->repo);

        $this->assertStringContainsString('name="from_parent.py"', $xml, 'An inherited file must travel too.');
        $this->assertStringContainsString('name="from_child.py"', $xml);
        $this->assertStringContainsString(base64_encode('PARENT FILE'), $xml);
        $this->assertStringContainsString(base64_encode('CHILD FILE'), $xml);
        $this->assertStringContainsString(
            '</file>',
            substr($xml, 0, strpos($xml, '</testcases>')),
            'CodeRunner 5 reads a question\'s files from inside its testcases element.'
        );
    }

    /**
     * Plain JSON parameters are merged along the chain, a child's keys winning.
     *
     * The only case where combining two layers' parameters is possible at all:
     * with the language None, the text already is the document.
     */
    public function test_plain_json_parameters_are_merged_along_the_chain(): void {
        $parent = $this->repo->create(
            'params_parent',
            $this->courseid,
            new override_set([
                'template' => 'T',
                'templateparams' => '{"n": 1, "fromparent": true}',
                'templateparamslang' => 'None',
            ], 'params_parent')
        );
        $child = $this->repo->create(
            'params_child',
            $this->courseid,
            new override_set([
                'templateparams' => '{"n": 2}',
                'templateparamslang' => 'None',
            ], 'params_child'),
            $parent->id
        );

        $columns = $this->import(prototype_export::file($child, $this->repo));

        $params = json_decode($columns->templateparams, true);
        $this->assertSame(2, $params['n'], "The child's value must win.");
        $this->assertTrue($params['fromparent'], "The parent's other keys must survive.");
        $this->assertSame('None', $columns->templateparamslang);
    }

    /**
     * One layer's parameters are carried whatever their language.
     *
     * Nothing is combined, so nothing needs evaluating: a Python3 set travels
     * as it stands and is evaluated on the recipient's own sandbox, exactly as
     * it is here.
     */
    public function test_a_single_layers_parameters_travel_in_their_own_language(): void {
        $parent = $this->repo->create(
            'quiet_parent',
            $this->courseid,
            new override_set(['template' => 'T'], 'quiet_parent')
        );
        $child = $this->repo->create(
            'python_params_child',
            $this->courseid,
            new override_set([
                'templateparams' => 'import json; print(json.dumps({"n": 1}))',
                'templateparamslang' => 'python3',
                'templateparamsevalpertry' => 1,
            ], 'python_params_child'),
            $parent->id
        );

        $columns = $this->import(prototype_export::file($child, $this->repo));

        $this->assertSame('import json; print(json.dumps({"n": 1}))', $columns->templateparams);
        $this->assertSame('python3', $columns->templateparamslang);
        $this->assertSame('1', (string) $columns->templateparamsevalpertry);
    }

    /**
     * Two layers whose parameters need evaluating cannot be combined.
     *
     * The author's objection to the first attempt at this, and he was right:
     * template parameters are a program, not a document, unless the language
     * is None. Twig is rendered against an attempt's seed and everything else
     * runs on the sandbox, so merging two layers' source texts is meaningless.
     */
    public function test_two_layers_needing_evaluation_are_refused(): void {
        $parent = $this->repo->create(
            'twig_parent',
            $this->courseid,
            new override_set([
                'template' => 'T',
                'templateparams' => '{"a": "{{ seed }}"}',
                'templateparamslang' => 'twig',
            ], 'twig_parent')
        );
        $child = $this->repo->create(
            'json_child',
            $this->courseid,
            new override_set([
                'templateparams' => '{"b": 2}',
                'templateparamslang' => 'None',
            ], 'json_child'),
            $parent->id
        );

        $this->expectException(\qtype_coderunner_exception::class);
        $this->expectExceptionMessageMatches('/[Tt]wig/');
        prototype_export::file($child, $this->repo);
    }

    /**
     * UI parameters from a layer on another UI are not merged in.
     *
     * The rule evaluate_merged_ui_parameters() applies at run time:
     * parameters meant for the graph UI must not be handed to Ace.
     */
    public function test_ui_parameters_from_another_ui_are_left_behind(): void {
        $parent = $this->repo->create(
            'graph_parent',
            $this->courseid,
            new override_set([
                'template' => 'T',
                'uiplugin' => 'graph',
                'uiparameters' => '{"isdirected": false}',
            ], 'graph_parent')
        );
        $child = $this->repo->create(
            'ace_child',
            $this->courseid,
            new override_set([
                'uiplugin' => 'ace',
                'uiparameters' => '{"theme": "dark"}',
            ], 'ace_child'),
            $parent->id
        );

        $columns = $this->import(prototype_export::file($child, $this->repo));

        $params = json_decode($columns->uiparameters, true);
        $this->assertSame('dark', $params['theme'], "The resolved UI's own parameters must travel.");
        $this->assertArrayNotHasKey(
            'isdirected',
            $params,
            'Parameters written for the graph UI must not be handed to Ace.'
        );
    }

    /**
     * UI parameters from a layer on the same UI are merged in.
     */
    public function test_ui_parameters_on_the_resolved_ui_are_merged(): void {
        $parent = $this->repo->create(
            'ace_parent',
            $this->courseid,
            new override_set([
                'template' => 'T',
                'uiplugin' => 'ace',
                'uiparameters' => '{"theme": "light", "fromparent": true}',
            ], 'ace_parent')
        );
        $child = $this->repo->create(
            'ace_child_two',
            $this->courseid,
            new override_set(['uiparameters' => '{"theme": "dark"}'], 'ace_child_two'),
            $parent->id
        );

        $columns = $this->import(prototype_export::file($child, $this->repo));

        $params = json_decode($columns->uiparameters, true);
        $this->assertSame('dark', $params['theme'], "The child's value must win.");
        $this->assertTrue($params['fromparent'], "The parent's other keys must survive.");
    }

    /**
     * Read a file back the way a CodeRunner 5 site would.
     *
     * Through `import_from_xml()`, which pulls the importing site's own
     * `extra_question_fields()` out of the XML -- the mechanism that makes a
     * file written here readable there.
     *
     * @param string $xml the whole file.
     * @return object the question as an importer builds it.
     */
    private function import(string $xml): object {
        $format = new \qformat_xml();
        $parsed = $this->parse_xml($xml);
        $question = $parsed['quiz']['#']['question'][0];

        $imported = \question_bank::get_qtype('coderunner')->import_from_xml($question, null, $format);
        $this->assertNotFalse($imported, 'A CodeRunner 5 importer would have rejected the file.');

        return $imported;
    }

    /**
     * Put a support file on a prototype's current version.
     *
     * @param \local_coderunner_manager\prototype $prototype whose version it is.
     * @param string $filename the file's name.
     * @param string $content its content.
     */
    private function write_file(\local_coderunner_manager\prototype $prototype, string $filename, string $content): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $this->repo->file_contextid($prototype),
            'component' => prototype_repository::COMPONENT,
            'filearea' => prototype_repository::FILEAREA,
            'itemid' => $prototype->currentversion->id,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * The same file carries the CodeRunner 6 chain beside the resolved columns.
     *
     * Decision 36: there is no CodeRunner 5 export and no CodeRunner 6 export.
     * A 5.x site reads the columns and cannot see these two elements; a 6.0
     * site reads these and treats the columns as write-only. Both halves have
     * to be in the one file or the decision is not implemented.
     */
    public function test_the_file_carries_both_halves(): void {
        $parent = $this->repo->create(
            'cosc_base',
            $this->courseid,
            new override_set(['template' => 'PARENT TEMPLATE'], 'cosc_base')
        );
        $child = $this->repo->create(
            'cosc_child',
            $this->courseid,
            new override_set(['penaltyregime' => '10, 20, ...'], 'cosc_child'),
            $parent->id
        );

        $xml = prototype_export::file($child, $this->repo);

        // The CodeRunner 6 half: this prototype named by uuid, and the chain
        // it needs, the leaf's own definition included.
        $this->assertStringContainsString("<prototypeuuid>{$child->uuid}</prototypeuuid>", $xml);
        $this->assertStringContainsString('<prototypechain>', $xml);
        $this->assertStringContainsString("<uuid>{$parent->uuid}</uuid>", $xml);
        $this->assertStringContainsString(
            "<uuid>{$child->uuid}</uuid>",
            $xml,
            "The leaf's own definition travels too, as a question's does."
        );

        // The CodeRunner 5 half, unaffected by the company it now keeps.
        $columns = $this->import($xml);
        $this->assertSame('PARENT TEMPLATE', $columns->template);
        $this->assertSame('2', (string) $columns->prototypetype);

        // And the chain says what the columns cannot: which layer owns what.
        $tree = $this->parse_xml($xml);
        $definitions = $tree['quiz']['#']['question'][0]['#']['prototypechain'][0]['#']['prototype'];
        $overrides = [];
        foreach ($definitions as $definition) {
            $name = $definition['#']['name'][0]['#']['text'][0]['#'];
            $overrides[$name] = json_decode($definition['#']['overrides'][0]['#']['text'][0]['#'], true);
        }
        $this->assertSame('PARENT TEMPLATE', $overrides['cosc_base']['template'] ?? null);
        $this->assertArrayNotHasKey(
            'template',
            $overrides['cosc_child'],
            'The child must not claim its parent\'s template as its own override.'
        );
        $this->assertSame('10, 20, ...', $overrides['cosc_child']['penaltyregime'] ?? null);
    }

    /**
     * A selection is written parents first, whatever order it was made in.
     *
     * An importer takes the file in document order, so a child's
     * `<parentuuid>` has to name something it has already seen. The library
     * page does not list in that order -- remotely-parented roots come first,
     * then the tree -- and an author ticks boxes in any order at all, so the
     * emitter sorts rather than trusting the selection.
     */
    public function test_a_selection_is_written_parents_first(): void {
        $parent = $this->repo->create(
            'base',
            $this->courseid,
            new override_set(['template' => 'T'], 'base')
        );
        $child = $this->repo->create(
            'middle',
            $this->courseid,
            new override_set([], 'middle'),
            $parent->id
        );
        $grandchild = $this->repo->create(
            'leaf',
            $this->courseid,
            new override_set([], 'leaf'),
            $child->id
        );

        // Deliberately the wrong way round.
        $xml = prototype_export::file_for([$grandchild, $child, $parent], $this->repo);

        preg_match_all('|<coderunnertype>([^<]*)</coderunnertype>|', $xml, $matches);
        $this->assertSame(['base', 'middle', 'leaf'], $matches[1]);
    }

    /**
     * A shared ancestor's definition is written once for the whole file.
     *
     * This is what makes the single format affordable for a library export
     * (decision 36): the ancestors are where the support files are, and
     * repeating them per child is what made a 200-question export unusable on
     * the question side. The resolved columns are still written per prototype,
     * because each has to stand alone for CodeRunner 5.
     */
    public function test_a_shared_ancestor_is_written_once(): void {
        $parent = $this->repo->create(
            'shared_base',
            $this->courseid,
            new override_set(['template' => 'T'], 'shared_base')
        );
        $first = $this->repo->create(
            'first_child',
            $this->courseid,
            new override_set([], 'first_child'),
            $parent->id
        );
        $second = $this->repo->create(
            'second_child',
            $this->courseid,
            new override_set([], 'second_child'),
            $parent->id
        );

        $xml = prototype_export::file_for([$parent, $first, $second], $this->repo);

        $this->assertSame(
            1,
            substr_count($xml, "<uuid>{$parent->uuid}</uuid>"),
            'The shared ancestor was written more than once.'
        );
        $this->assertSame(
            3,
            substr_count($xml, '<prototypeuuid>'),
            'Every prototype still names itself, however few definitions the file holds.'
        );
        $this->assertSame(
            3,
            substr_count($xml, '<prototypetype>2</prototypetype>'),
            'Each prototype still stands alone for CodeRunner 5.'
        );
    }

    /**
     * A prototype file fed to Moodle's own question import lands in the
     * manager, and does so idempotently.
     *
     * Decision 36 tolerates this door rather than preventing it, because core
     * gives a question type no way to refuse a question and act instead
     * (decision 34). What makes it tolerable is the uuid: the prototype is
     * adopted where this site has it and created where it does not, so a
     * second import of the same file changes nothing. The stray prototype
     * question core insists on creating is cleanup, not the authority.
     */
    public function test_a_prototype_file_imported_as_a_question_reaches_the_manager(): void {
        $parent = $this->repo->create(
            'imported_base',
            $this->courseid,
            new override_set(['template' => 'BASE TEMPLATE'], 'imported_base')
        );
        $child = $this->repo->create(
            'imported_child',
            $this->courseid,
            new override_set(['penaltyregime' => '5, 10, ...'], 'imported_child'),
            $parent->id
        );
        $xml = prototype_export::file($child, $this->repo);
        $childuuid = $child->uuid;
        $parentuuid = $parent->uuid;

        // A receiving site that has neither: the rows go, the file stays.
        $this->repo->delete($this->repo->get_or_fail($child->id));
        $this->repo->delete($this->repo->get_or_fail($parent->id));
        $this->assertSame([], $this->repo->find_by_uuid($childuuid), 'The fixture must start absent.');

        $this->assertSame(
            2,
            $this->import_as_question($xml),
            'The file should have created the prototype it describes and the ancestor it needs.'
        );

        $landed = array_values($this->repo->find_by_uuid($childuuid));
        $this->assertCount(1, $landed);
        $this->assertSame('imported_child', $landed[0]->name);
        $this->assertSame(
            'BASE TEMPLATE',
            $this->repo->chain_for($landed[0]->id)->resolve()->get('template'),
            'The ancestor must have been created too, so the chain resolves as it did.'
        );
        $this->assertSame(
            '5, 10, ...',
            $this->repo->chain_for($landed[0]->id)->resolve()->get('penaltyregime'),
            "And the child's own override must survive as an override."
        );

        // The same file again: adopted by uuid, so nothing is duplicated and
        // nothing of the recipient's is rewritten.
        $versions = count($this->repo->history($landed[0]->id));
        $this->import_as_question($xml);
        $this->assertCount(1, $this->repo->find_by_uuid($childuuid), 'A second import duplicated the prototype.');
        $this->assertCount(1, $this->repo->find_by_uuid($parentuuid), 'A second import duplicated the ancestor.');
        $this->assertSame(
            $versions,
            count($this->repo->history($landed[0]->id)),
            'This door must not add versions: that is the manager import, which asks first.'
        );
    }

    /**
     * Import a prototype file the way Moodle's question import would.
     *
     * Both halves of what core does for each question, in its order: the
     * question type reads the XML, and then the save path is called with the
     * result.
     *
     * The rows core inserts between the two are stood in for by `contextid`,
     * which is the first thing `question_contextid()` looks at. A real import
     * has no such property -- `importprocess()` sets `context`, not
     * `contextid` -- and the helper falls through to a query on the question's
     * own id, which by then exists because core inserted it before calling the
     * save path. Setting it here is what lets the test skip those three
     * inserts; it is not a shape the import produces.
     *
     * @param string $xml the file.
     * @return int how many prototypes the manager holds that it did not before.
     */
    private function import_as_question(string $xml): int {
        $format = new \qformat_xml();
        $parsed = $this->parse_xml($xml);
        $before = count($this->repo->list_for_course($this->courseid));

        foreach ($parsed['quiz']['#']['question'] as $questionxml) {
            $qtype = \question_bank::get_qtype('coderunner');
            $imported = $qtype->import_from_xml($questionxml, null, $format);
            $this->assertNotFalse($imported);

            $imported->contextid = \context_course::instance($this->courseid)->id;
            $qtype->set_prototype_reference($imported);
        }

        return count($this->repo->list_for_course($this->courseid)) - $before;
    }

    /**
     * Parse XML the way the importing site's own reader would.
     *
     * `core\xml_parser` arrived in Moodle 5.1 (MDL-86256 moved the XML
     * parsing classes into autoloaded namespaces), and the plugin supports
     * 5.0, where the class does not exist and six tests died on
     * *Class "core\xml_parser" not found* -- in CI only, since the
     * development site is 5.3. `xmlize()` is no use as the single call for
     * both: it survives in 5.1+ only as a deprecated wrapper that emits a
     * deprecation, and an unexpected debugging message fails a Moodle unit
     * test. So the version decides, as it does in `qformat_xml` itself.
     *
     * @param string $xml the XML to parse.
     * @return array the parsed tree, in xmlize's shape.
     */
    private function parse_xml(string $xml): array {
        if (class_exists('\\core\\xml_parser')) {
            return (new \core\xml_parser())->parse($xml, 0, 'UTF-8', true);
        }
        return xmlize($xml, 0, 'UTF-8', true);
    }
}
