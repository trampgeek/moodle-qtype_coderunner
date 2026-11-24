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
 * Unit tests for importing a question that carries its prototype chain.
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
 * Tests the import side of the one Moodle XML format.
 *
 * Moodle's own importer does the work: `import_from_xml()` reads the chain the
 * file carries and `save_question_options()` adopts a prototype already here
 * with that uuid, or creates what the file defined, parents before children
 * (decision 32). Nothing is offered to the user and no new plugin is involved.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(chain_transport::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class chain_import_test extends \advanced_testcase {
    /** @var int the course whose bank questions are exported from. */
    private int $sourcecourseid;

    /** @var int the category questions are exported from. */
    private int $sourcecategoryid;

    /** @var prototype_repository the manager's repository. */
    private prototype_repository $repo;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        chain_transport::reset();

        $this->sourcecourseid = (int) $this->getDataGenerator()->create_course()->id;
        $this->sourcecategoryid = (int) $this->getDataGenerator()
            ->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => \context_course::instance($this->sourcecourseid)->id])
            ->id;
        $this->repo = new prototype_repository();
    }

    /**
     * A prototype the receiving course does not have is created by the import.
     *
     * The *include prototypes* case, which is now simply the format: the file
     * carries the definition and the import acquires the type.
     */
    public function test_an_unknown_prototype_is_created_by_the_import(): void {
        $prototype = $this->repo->create(
            'imported_type',
            $this->sourcecourseid,
            new override_set(['template' => 'IMPORTED TEMPLATE'], 'imported_type'),
            null,
            'Imported summary'
        );
        $xml = $this->export($this->make_question($prototype->id, 'imported_type'));
        [$xml, $uuids] = $this->from_another_site($xml, [$prototype]);

        $target = (int) $this->getDataGenerator()->create_course()->id;
        [$imported] = $this->import($xml, $target);

        $created = $this->repo->find_by_name_for_course('imported_type', $target);
        $this->assertNotNull($created, 'The definition the file carried was not created.');
        $this->assertSame(
            $uuids[$prototype->uuid],
            $created->uuid,
            "A created prototype keeps the exporting site's identifier, so a later file matches it."
        );
        $this->assertSame(
            'IMPORTED TEMPLATE',
            $created->currentversion->overrides->get('template'),
            "The created prototype must hold the exporting site's settings."
        );
        $this->assertSame('Imported summary', $created->currentversion->summary);
        $this->assertSame(
            $created->id,
            $this->prototypeid_of($imported),
            'The imported question must point at the prototype the import created.'
        );
    }

    /**
     * A prototype already here with that uuid is adopted, not duplicated.
     *
     * The *reference only* case, which is now the same format arriving where
     * the match succeeds.
     */
    public function test_a_known_prototype_is_adopted_rather_than_duplicated(): void {
        $prototype = $this->repo->create(
            'adopted_type',
            SITEID,
            new override_set(['template' => 'SITE TEMPLATE'], 'adopted_type')
        );
        $xml = $this->export($this->make_question($prototype->id, 'adopted_type'));

        $target = (int) $this->getDataGenerator()->create_course()->id;
        [$imported] = $this->import($xml, $target);

        $this->assertCount(
            1,
            $this->repo->find_by_uuid($prototype->uuid),
            'A prototype already here with that uuid must be adopted, not copied.'
        );
        $this->assertSame(
            $prototype->id,
            $this->prototypeid_of($imported),
            'The imported question must use the prototype this site already had.'
        );
    }

    /**
     * A whole chain is created, with the child still hanging from its parent.
     */
    public function test_a_two_level_chain_is_created_whole(): void {
        $parent = $this->repo->create(
            'chain_parent',
            $this->sourcecourseid,
            new override_set(['template' => 'PARENT TEMPLATE'], 'chain_parent')
        );
        $child = $this->repo->create(
            'chain_child',
            $this->sourcecourseid,
            new override_set(['penaltyregime' => '10, 20, ...'], 'chain_child'),
            $parent->id
        );
        $xml = $this->export($this->make_question($child->id, 'chain_child'));
        [$xml] = $this->from_another_site($xml, [$parent, $child]);

        $target = (int) $this->getDataGenerator()->create_course()->id;
        [$imported] = $this->import($xml, $target);

        $newparent = $this->repo->find_by_name_for_course('chain_parent', $target);
        $newchild = $this->repo->find_by_name_for_course('chain_child', $target);
        $this->assertNotNull($newparent, 'The parent travels, so it must be created.');
        $this->assertNotNull($newchild);
        $this->assertSame(
            $newparent->id,
            $newchild->currentversion->parentid,
            'The created child must hang from the created parent, not stand alone.'
        );
        $this->assertSame(
            'PARENT TEMPLATE',
            $this->repo->chain_for($newchild->id)->resolve()->get('template'),
            'The restored chain must resolve as it did on the exporting site.'
        );
    }

    /**
     * A created prototype gets the support files its definition carried.
     */
    public function test_support_files_are_restored_with_the_prototype(): void {
        $prototype = $this->repo->create(
            'filed_import_type',
            $this->sourcecourseid,
            new override_set(['template' => 'T'], 'filed_import_type')
        );
        get_file_storage()->create_file_from_string([
            'contextid' => $this->repo->file_contextid($prototype),
            'component' => prototype_repository::COMPONENT,
            'filearea' => prototype_repository::FILEAREA,
            'itemid' => $prototype->currentversion->id,
            'filepath' => '/',
            'filename' => 'helper.py',
        ], 'print("support")');
        $xml = $this->export($this->make_question($prototype->id, 'filed_import_type'));
        [$xml] = $this->from_another_site($xml, [$prototype]);

        $target = (int) $this->getDataGenerator()->create_course()->id;
        [$imported] = $this->import($xml, $target);

        $created = $this->repo->find_by_name_for_course('filed_import_type', $target);
        $files = $this->repo->version_files($created, $created->currentversion->id);
        $this->assertArrayHasKey('helper.py', $files, 'A prototype without its files cannot run.');
        $this->assertSame('print("support")', $files['helper.py']->get_content());
    }

    /**
     * A file carrying no chain still resolves its prototype by name.
     *
     * The CodeRunner 5 shape, and the shape of a file whose definitions have
     * been edited away. Nothing about the old route may change.
     */
    public function test_a_file_with_no_chain_resolves_by_name(): void {
        $prototype = $this->repo->create(
            'named_only_type',
            SITEID,
            new override_set(['template' => 'SITE TEMPLATE'], 'named_only_type')
        );
        $xml = $this->export($this->make_question($prototype->id, 'named_only_type'));
        $stripped = preg_replace('{<prototypeuuid>.*?</prototypeuuid>}s', '', $xml);
        $stripped = preg_replace('{<prototypechain>.*?</prototypechain>}s', '', $stripped);

        $target = (int) $this->getDataGenerator()->create_course()->id;
        [$imported] = $this->import($stripped, $target);

        $this->assertSame(
            $prototype->id,
            $this->prototypeid_of($imported),
            'A file with no uuid must still find a site prototype of that name.'
        );
    }

    /**
     * A prototype nothing can supply refuses the import, saving nothing.
     *
     * The case the file itself cannot answer: a uuid this site does not have,
     * no definition carried for it -- a file split by hand, or an older
     * reference-only export -- and a name that resolves to nothing either.
     * Moodle's stop-on-error gate sits between the parse and the first write,
     * so the refusal costs nothing.
     */
    public function test_an_unsatisfiable_prototype_refuses_the_import(): void {
        $prototype = $this->repo->create(
            'vanished_type',
            $this->sourcecourseid,
            new override_set(['template' => 'T'], 'vanished_type')
        );
        $xml = $this->export($this->make_question($prototype->id, 'vanished_type'));
        [$xml] = $this->from_another_site($xml, [$prototype]);
        $xml = preg_replace('{<prototypechain>.*?</prototypechain>}s', '', $xml);

        $target = (int) $this->getDataGenerator()->create_course()->id;
        $this->import($xml, $target, true);

        $this->assertNull(
            $this->repo->find_by_name_for_course('vanished_type', $target),
            'A refused import must write nothing at all.'
        );
        $this->assertStringContainsString(
            'vanished_type',
            $this->notifications(),
            'A refusal has to name the question type that is missing.'
        );
    }

    /**
     * A name already taken by another lineage refuses the import.
     *
     * Two unrelated question types of one name. Renaming the incoming one, as
     * a restore does, would quietly duplicate the type and leave an author
     * guessing which of the two their questions use, so decision 34 refuses
     * and names both.
     */
    public function test_a_name_taken_by_another_lineage_refuses_the_import(): void {
        $prototype = $this->repo->create(
            'contested_name',
            $this->sourcecourseid,
            new override_set(['template' => 'INCOMING TEMPLATE'], 'contested_name')
        );
        $xml = $this->export($this->make_question($prototype->id, 'contested_name'));
        [$xml, $uuids] = $this->from_another_site($xml, [$prototype]);

        // The receiving course has its own, unrelated, question type of that
        // name: same name, different identifier.
        $target = (int) $this->getDataGenerator()->create_course()->id;
        $local = $this->repo->create(
            'contested_name',
            $target,
            new override_set(['template' => 'LOCAL TEMPLATE'], 'contested_name')
        );

        $this->import($xml, $target, true);

        $messages = $this->notifications();
        $this->assertStringContainsString('contested_name', $messages);
        $this->assertStringContainsString($uuids[$prototype->uuid], $messages, 'Both lineages must be named.');
        $this->assertStringContainsString($local->uuid, $messages, 'Both lineages must be named.');
        $this->assertCount(
            1,
            $this->repo->list_for_course($target),
            'A refused import must leave the receiving course exactly as it was.'
        );
    }

    /**
     * An ancestor that is neither in the file nor here refuses the import.
     *
     * The missing built-in case. Rooting the orphan instead would change what
     * every question beneath it resolves to, silently.
     */
    public function test_a_missing_ancestor_refuses_the_import(): void {
        $builtin = $this->repo->create(
            'absent_builtin',
            SITEID,
            new override_set(['template' => 'BUILT-IN TEMPLATE'], 'absent_builtin'),
            null,
            '',
            '',
            true
        );
        $child = $this->repo->create(
            'orphaned_child',
            $this->sourcecourseid,
            new override_set(['penaltyregime' => '10, 20, ...'], 'orphaned_child'),
            $builtin->id
        );
        $xml = $this->export($this->make_question($child->id, 'orphaned_child'));
        // The built-in is named but never carried, so rewriting its uuid is
        // enough to make it a built-in the receiving site has never heard of.
        [$xml] = $this->from_another_site($xml, [$child, $builtin]);

        $target = (int) $this->getDataGenerator()->create_course()->id;
        $this->import($xml, $target, true);

        $this->assertStringContainsString('orphaned_child', $this->notifications());
        $this->assertNull(
            $this->repo->find_by_name_for_course('orphaned_child', $target),
            'A refused import must write nothing at all.'
        );
    }

    /**
     * A local prototype of the same uuid but different settings warns.
     *
     * Not a refusal: the local one is used, and the importer is told, in both
     * directions. Version order carries no information about whether a
     * question will run, because that depends on the sandbox.
     */
    public function test_differing_settings_warn_but_import(): void {
        $prototype = $this->repo->create(
            'drifted_type',
            SITEID,
            new override_set(['template' => 'AS EXPORTED'], 'drifted_type')
        );
        $xml = $this->export($this->make_question($prototype->id, 'drifted_type'));

        // The site's own copy moves on, as a prototype author's would.
        $this->repo->save_new_version(
            $prototype,
            new override_set(['template' => 'AS IT IS NOW'], 'drifted_type'),
            $prototype->currentversion->parentid
        );

        $target = (int) $this->getDataGenerator()->create_course()->id;
        [$imported] = $this->import($xml, $target);

        $this->assertSame(
            $prototype->id,
            $this->prototypeid_of($imported),
            "The site's own prototype is used, so the question must point at it."
        );
        $messages = $this->notifications();
        $this->assertStringContainsString('drifted_type', $messages);
        $this->assertStringContainsString('bulk tester', $messages, 'The warning has to say what to do next.');
    }

    /**
     * Everything the notification queue has been told, as one string.
     *
     * @return string the queued messages.
     */
    private function notifications(): string {
        $messages = '';
        foreach (\core\notification::fetch() as $notification) {
            $messages .= $notification->get_message() . "\n";
        }

        return $messages;
    }

    /**
     * The same file, as though it came from a site this one has never met.
     *
     * Every uuid in it is replaced by a fresh one. Without this the import has
     * nothing to create: both courses are on one site, and a uuid match
     * deliberately reaches across courses, so the import would adopt the very
     * prototype the file was exported from -- which is right, and is what
     * test_a_known_prototype_is_adopted_rather_than_duplicated covers.
     *
     * @param string $xml the exported questions.
     * @param array $prototypes the prototypes whose uuids appear in it.
     * @return array{0: string, 1: array} the rewritten file, and old uuid to new.
     */
    private function from_another_site(string $xml, array $prototypes): array {
        $map = [];
        foreach ($prototypes as $prototype) {
            $map[$prototype->uuid] = \core\uuid::generate();
            $xml = str_replace($prototype->uuid, $map[$prototype->uuid], $xml);
        }

        return [$xml, $map];
    }

    /**
     * The prototype id recorded for an imported question.
     *
     * @param int $questionid the imported question, as the format reported it.
     * @return int|null the question's prototype id.
     */
    private function prototypeid_of(int $questionid): ?int {
        global $DB;

        $id = $DB->get_field('question_coderunner_options', 'prototypeid', ['questionid' => $questionid]);

        return $id === null || $id === false ? null : (int) $id;
    }

    /**
     * Import a file into a fresh category in the given course.
     *
     * The real import path: `importprocess()` parses, then saves each question
     * through `save_question_options()`, which is where the chain is resolved.
     *
     * @param string $xml the questions, without the surrounding quiz element.
     * @param int $courseid the course to import into.
     * @param bool $expectrefusal true if the import is expected to refuse.
     * @return array the ids of the questions the import created.
     */
    private function import(string $xml, int $courseid, bool $expectrefusal = false): array {
        $context = \context_course::instance($courseid);
        $category = $this->getDataGenerator()
            ->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);

        $file = make_request_directory() . '/import.xml';
        file_put_contents($file, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n$xml</quiz>\n");

        $qformat = new \qformat_xml();
        $qformat->setCategory($category);
        $qformat->setContexts([$context]);
        $qformat->setCourse(get_course($courseid));
        $qformat->setFilename($file);
        $qformat->setRealfilename('import.xml');
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);
        $qformat->set_display_progress(false);

        // Buffered because the importer reports itself by echoing: a refused
        // import prints Moodle's own "no questions have been imported" line,
        // which PHPUnit counts as unexpected output from the test.
        $this->assertTrue($qformat->importpreprocess(), 'The import did not start.');
        ob_start();
        $imported = $qformat->importprocess();
        $finished = $imported ? $qformat->importpostprocess() : false;
        ob_end_clean();

        if ($expectrefusal) {
            $this->assertFalse($imported, 'The import should have been refused.');
            $this->assertGreaterThan(0, $qformat->importerrors, 'A refusal must be counted as an error.');

            return $qformat->questionids;
        }
        $this->assertTrue($imported, 'The import failed.');
        $this->assertTrue($finished, 'The import did not finish.');

        return $qformat->questionids;
    }

    /**
     * The Moodle XML for one question, as the export page produces it.
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

    /**
     * A CodeRunner question in the source bank, using the given prototype.
     *
     * @param int $prototypeid the prototype it uses.
     * @param string $name the prototype's name, as `coderunnertype` holds it.
     * @return int the question id.
     */
    private function make_question(int $prototypeid, string $name): int {
        global $DB;

        $question = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question(
            'coderunner',
            null,
            ['category' => $this->sourcecategoryid, 'name' => 'Travelling question']
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
