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
 * @group qtype_coderunner
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
 * @coversNothing
 * @copyright  2016 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

    public function test_restore() {
        global $CFG;

        $this->restore_backup($CFG->dirroot .
                '/question/type/coderunner/tests/fixtures/loadtesting_pseudocourse_backup.mbz');

        // Verify some restored questions look OK.
        [$options, $tests] = $this->load_question_data_by_name('c_to_fpy3');
        $this->assertCount(3, $tests);
        $this->assertNull($options->template);

        [$options, $tests] = $this->load_question_data_by_name('PROTOTYPE_clojure_with_combinator');
        $this->assertCount(1, $tests);
        $this->assertStringStartsWith('import subprocess', $options->template);
    }

    public function test_restore_from_v3_0_0() {
        global $CFG;

        $this->restore_backup($CFG->dirroot .
                '/question/type/coderunner/tests/fixtures/loadtesting_pseudocourse_backup_V3.0.0.mbz');

        // Verify some restored questions look OK.
        [$options, $tests] = $this->load_question_data_by_name('c_to_fpy3');
        $this->assertCount(3, $tests);
        $this->assertNull($options->template);

        [$options, $tests] = $this->load_question_data_by_name('PROTOTYPE_clojure_with_combinator');
        $this->assertCount(1, $tests);
        $this->assertStringStartsWith('import subprocess', $options->template);
    }
}
