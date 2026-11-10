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
 * Unit tests for CodeRunner restore code.
 *
 * @package    qtype_coderunner
 * @copyright  2016 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');


/**
 * Unit tests for CodeRunner restore code.
 *
 * @copyright  2016 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
class restore_test extends \advanced_testcase {
    /**
     * @var stdClass generated question category to restore into.
     */
    protected $category;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->category = $generator->create_category();
    }

    /**
     * Restore a backup file.
     *
     * @param string $backupfile full path of the backup file to restore.
     * @return int the id of the newly restored course.
     */
    protected function restore_backup($backupfile) {
        global $USER;

        // Unzip the backup to a temp folder.
        $folder = 'restore_test';
        $folderpath = make_temp_directory('backup/' . $folder);

        // Extract the backup to tmpdir.
        $fb = get_file_packer('application/vnd.moodle.backup');
        $fb->extract_to_pathname($backupfile, $folderpath);

        // Restore one of the example backups.
        $newcourseid = \restore_dbops::create_new_course(
            'Restore test',
            'RT100',
            $this->category->id
        );
        $rc = new \restore_controller(
            $folder,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Load the data about a question.
     *
     * @param string $name the question name. Must be globally unique.
     * @return array with two elements, the question options object and
     *      an array of question tests.
     */
    protected function load_question_data_by_name($name) {
        global $DB;
        $questionid = $DB->get_field('question', 'id', ['name' => $name], MUST_EXIST);
        return [
                $DB->get_record(
                    'question_coderunner_options',
                    ['questionid' => $questionid],
                    '*',
                    MUST_EXIST
                ),
                $DB->get_records(
                    'question_coderunner_tests',
                    ['questionid' => $questionid]
                )];
    }

    public function test_restore_from_v3_1_2(): void {
        global $CFG;
        $moodleversion = (float) get_config('core', 'release');
        if ($moodleversion >= 5) {
            $this->markTestSkipped('This test fails on Moodle 5 due to warnings.');
        } else {
            $this->restore_backup($CFG->dirroot .
                    '/question/type/coderunner/tests/fixtures/backuptester_V3.1.2.mbz');

            // Verify a couple of restored questions look OK.
            [$options, $tests] = $this->load_question_data_by_name('c_to_fpy3');
            $this->assertCount(3, $tests);
            $this->assertNull($options->template);

            [$options, $tests] = $this->load_question_data_by_name('PROTOTYPE_clojure_with_combinator');
            $this->assertCount(1, $tests);
            $this->assertStringStartsWith('import subprocess', $options->template);
        }
    }

    public function test_restore_from_v4_5_3(): void {
        global $CFG;
        $moodleversion = (float) get_config('core', 'release');
        if ($moodleversion < 4.5 || $moodleversion >= 5) {
            $this->markTestSkipped('This test requires Moodle 4.5.3 or later and less than Moodle 5.');
        } else {
            $this->restore_backup($CFG->dirroot .
                    '/question/type/coderunner/tests/fixtures/backuptester_V4.5.3.mbz');

            // Verify a couple of restored questions look OK.
            [$options, $tests] = $this->load_question_data_by_name('c_to_fpy3');
            $this->assertCount(3, $tests);
            $this->assertNull($options->template);

            [$options, $tests] = $this->load_question_data_by_name('PROTOTYPE_clojure_with_combinator');
            $this->assertCount(1, $tests);
            $this->assertStringStartsWith('import subprocess', $options->template);
        }
    }

    public function test_restore_from_v5_0_3(): void {
        global $CFG;
        $moodleversion = (float) get_config('core', 'release');
        if ($moodleversion < 5) {
            $this->markTestSkipped('This test requires Moodle 5.0 or later.');
        } else {
            $this->restore_backup($CFG->dirroot .
                    '/question/type/coderunner/tests/fixtures/backuptester_V5.0.3.mbz');

            // Verify a couple of restored questions look OK.
            [$options, $tests] = $this->load_question_data_by_name('Question in quiz');
            $this->assertCount(1, $tests);
            $this->assertNull($options->template);

            [$options, $tests] = $this->load_question_data_by_name('Java Class: bod');
            $this->assertCount(3, $tests);
            $this->assertNull($options->template);
        }
    }

    /**
     * A pre-6.0 backup restores into a question that keeps its own settings.
     *
     * The failure this guards is silent. A restored question whose overrides
     * document was left null means "overrides nothing", so every inherited
     * column is overwritten from the chain and the sixteen fields CodeRunner 5
     * read straight from the column take the prototype's values instead of the
     * question's. It renders and grades, with the wrong settings, which is why
     * what is asserted is the resolved values and not that the restore
     * completed.
     */
    public function test_a_legacy_backup_keeps_each_questions_own_settings(): void {
        global $CFG, $DB;

        if ((float) get_config('core', 'release') < 5) {
            $this->markTestSkipped('This test requires Moodle 5.0 or later.');
        }

        // A prototype of the name the backup's questions use, as a 6.0 site
        // has, holding values no question in the backup holds.
        (new \local_coderunner_manager\prototype_repository())->create(
            'java_class',
            (int) SITEID,
            new \qtype_coderunner\v6\override_set([
                'template' => 'PROTOTYPE TEMPLATE',
                'penaltyregime' => '33, 66, ...',
                'answerboxlines' => 7,
            ], 'java_class')
        );

        $this->restore_backup($CFG->dirroot .
                '/question/type/coderunner/tests/fixtures/backuptester_V5.0.3.mbz');

        [$stored] = $this->load_question_data_by_name('Java Class: bod');

        // Converted during the restore, not left for the migration task.
        $this->assertNotNull($stored->prototypeid, 'The prototype was not resolved by name.');
        $this->assertNotNull($stored->overrides, 'The question was left unconverted.');

        $loaded = \question_bank::load_question_data(
            $DB->get_field('question', 'id', ['name' => 'Java Class: bod'], MUST_EXIST)
        );

        // Its own, from its own columns.
        $this->assertSame($stored->penaltyregime, $loaded->options->penaltyregime);
        $this->assertEquals($stored->answerboxlines, $loaded->options->answerboxlines);
        $this->assertNotSame('33, 66, ...', $loaded->options->penaltyregime);
        $this->assertNotEquals(7, $loaded->options->answerboxlines);

        // And still inherited where the question's column is null, which is how
        // CodeRunner 5 spelt "inherited".
        $this->assertNull($stored->template);
        $this->assertSame('PROTOTYPE TEMPLATE', $loaded->options->template);
    }

    /**
     * A restored question that could not be resolved asks for the prototype
     * sweep, so the course does not stay broken.
     *
     * A pre-6.0 backup carries its course's own prototype *questions*, which
     * become prototypes in the manager only when the install task runs. Until
     * then nothing of their name exists to resolve against, so every question
     * built on one arrives broken. The task fixes both halves in the right
     * order; nothing was asking it to run.
     */
    public function test_an_unresolvable_restore_asks_for_the_prototype_sweep(): void {
        global $CFG, $DB;

        if ((float) get_config('core', 'release') < 5) {
            $this->markTestSkipped('This test requires Moodle 5.0 or later.');
        }

        // No prototype of any name the backup uses, which is the state a site
        // is in for a course whose prototypes travelled with it.
        $DB->delete_records('task_adhoc');

        $this->restore_backup($CFG->dirroot .
                '/question/type/coderunner/tests/fixtures/backuptester_V5.0.3.mbz');

        [$stored] = $this->load_question_data_by_name('Java Class: bod');
        $this->assertNull($stored->prototypeid, 'The fixture is only meaningful while nothing resolves.');
        $this->assertNotNull($stored->overrides, 'The question is still converted.');

        $queued = $DB->get_records_select(
            'task_adhoc',
            $DB->sql_like('classname', '?'),
            ['%install_prototypes%']
        );
        $this->assertCount(1, $queued, 'The sweep was asked for exactly once.');
    }

    /**
     * A legacy prototype question restores as one, with neither column set.
     *
     * It has nothing to inherit and nothing to override; the prototype
     * installer turns it into a prototype of its own when it next runs, which
     * is what keeps the old prototype questions working as a way in to the
     * prototype manager.
     */
    public function test_a_legacy_prototype_question_gets_neither_column(): void {
        $data = (object) [
            'coderunnertype' => 'java_class',
            'prototypetype' => 2,
            'prototypeid' => 12345,
            'penaltyregime' => '0, 10, 20, ...',
        ];

        \restore_qtype_coderunner_plugin::set_v6_columns($data, \context_system::instance());

        $this->assertNull($data->prototypeid);
        $this->assertNull($data->overrides);
    }

    /**
     * An overrides document that travelled in the backup is kept, and an empty
     * element is not mistaken for one.
     *
     * Moodle backup writes a null column as an empty element and the restore
     * carries it through as the empty string, which `json_decode()` reads as
     * nothing at all. Testing presence rather than emptiness would leave such a
     * question overriding nothing, which is the silent failure above.
     */
    public function test_an_empty_overrides_element_is_not_a_document(): void {
        $own = ['penaltyregime' => '0, 10, 20, ...'];

        $carried = (object) [
            'coderunnertype' => 'java_class',
            'prototypetype' => 0,
            'overrides' => json_encode($own),
        ];
        \restore_qtype_coderunner_plugin::set_v6_columns($carried, \context_system::instance());
        $this->assertSame($own, json_decode($carried->overrides, true), 'A real document was overwritten.');

        foreach (['', null] as $empty) {
            $blank = (object) [
                'coderunnertype' => 'java_class',
                'prototypetype' => 0,
                'overrides' => $empty,
                'penaltyregime' => '0, 10, 20, ...',
            ];
            \restore_qtype_coderunner_plugin::set_v6_columns($blank, \context_system::instance());
            $decoded = json_decode($blank->overrides, true);
            $this->assertIsArray($decoded, 'An empty element was taken for a document.');
            $this->assertSame('0, 10, 20, ...', $decoded['penaltyregime']);
        }
    }
}
