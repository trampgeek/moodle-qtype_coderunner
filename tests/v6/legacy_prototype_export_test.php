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
 * Tests the one place flattening happens: a prototype export for 5.x.
 *
 * A chain resolved into a single prototype question, which is what a 5.x site
 * needs because `get_prototype()` does not recurse. Read back here through the
 * same `import_from_xml()` a 5.x site would use, since what matters is not the
 * shape of the file but what an importer makes of it.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(legacy_prototype_export::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class legacy_prototype_export_test extends \advanced_testcase {
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

        $columns = $this->import(legacy_prototype_export::file($child, $this->repo));

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

        $xml = legacy_prototype_export::file($prototype, $this->repo);

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

        $xml = legacy_prototype_export::file($child, $this->repo);

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

        $columns = $this->import(legacy_prototype_export::file($child, $this->repo));

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

        $columns = $this->import(legacy_prototype_export::file($child, $this->repo));

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
        legacy_prototype_export::file($child, $this->repo);
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

        $columns = $this->import(legacy_prototype_export::file($child, $this->repo));

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

        $columns = $this->import(legacy_prototype_export::file($child, $this->repo));

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
        $parsed = (new \core\xml_parser())->parse($xml, 0, 'UTF-8', true);
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
}
