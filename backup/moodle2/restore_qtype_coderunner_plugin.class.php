<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  &copy; 2012 Richard Lobb
 * @author     Richard Lobb richard.lobb@canterbury.ac.nz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Restore plugin class for coderunner questions.
 */
class restore_qtype_coderunner_plugin extends restore_qtype_plugin {
    /**
     * Returns the paths to be handled by the plugin at question level.
     */
    public function define_question_plugin_structure() {

        $paths = [];

        // Add options and testcases to the restore structure.
        $this->add_question_options($paths);
        $this->add_question_testcases($paths);

        return $paths; // And return the paths.
    }

    /*
     * Add the options to the restore structure.
     */
    private function add_question_options(&$paths) {
        // Check $paths is an array.
        if (!is_array($paths)) {
            throw new restore_step_exception('paths_must_be_array', $paths);
        }

        $elename = 'coderunner_options';
        $elepath = $this->get_pathfor('/coderunner_options/coderunner_option');  // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element($elename, $elepath);
    }

    /*
     * Add the testcases to the restore structure.
     */
    private function add_question_testcases(&$paths) {
        // Check $paths is one array.
        if (!is_array($paths)) {
            throw new restore_step_exception('paths_must_be_array', $paths);
        }

        $elename = 'coderunner_testcases';
        $elepath = $this->get_pathfor('/coderunner_testcases/coderunner_testcase');
        $paths[] = new restore_path_element($elename, $elepath);
    }


    /*
     * Called during restore to process the testcases within the
     * backup element.
     */
    public function process_coderunner_testcases($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, insert the new testcase.
        if ($questioncreated) {
            $data->questionid = $newquestionid;
            // Insert record.
            $DB->insert_record("question_coderunner_tests", $data);
        }
        // Nothing to remap if the question already existed.
    }

    /*
     * Called during restore to process the options within the
     * backup element.
     */
    public function process_coderunner_options($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to insert a new options record.
        if ($questioncreated) {
            $data->questionid = $newquestionid;

            // Convert pre-version 3.1 fields to post 3.1.
            if (
                isset($data->pertesttemplate) &&
                    trim($data->pertesttemplate ?? '') != '' &&
                    empty($data->enablecombinator) &&
                    $data->grader != 'CombinatorTemplateGrader'
            ) {
                $data->template = $data->pertesttemplate;
                $data->iscombinatortemplate = 0;
            }
            if (
                isset($data->combinatortemplate) &&
                    trim($data->combinatortemplate ?? '') != '' &&
                    ((isset($data->enablecombinator) &&
                    $data->enablecombinator == 1 ) ||
                            $data->grader == 'CombinatorTemplateGrader')
            ) {
                $data->template = $data->combinatortemplate;
                $data->iscombinatortemplate = 1;
            }
            if ($data->grader == 'CombinatorTemplateGrader') {
                $data->grader = 'TemplateGrader';
            }

            // Insert the record.
            $DB->insert_record("question_coderunner_options", $data);
        }
        // Nothing to remap if the question already existed.
    }

    // Add the extra methods required by MDL-83541.
    // #[Override]  // This breaks the restore at run time if used with older versions of moodle so commented out.
    public static function convert_backup_to_questiondata(array $backupdata): stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);
        $qtype = $questiondata->qtype;

        if (isset($backupdata["plugin_qtype_{$qtype}_question"]['coderunner_testcases'])) {
            $questiondata->options->testcases = [];
            foreach ($backupdata["plugin_qtype_{$qtype}_question"]['coderunner_testcases']['coderunner_testcase'] as $record) {
                $testcase = new stdClass();
                $fields = [ 'testcode', 'testtype', 'expected', 'useasexample', 'display',
                    'hiderestiffail', 'mark', 'stdin', 'extra'];
                foreach ($fields as $field) {
                    if (isset($record[$field])) {   // Old backups don't have testtype.
                        $testcase->$field = $record[$field];
                    }
                }
                $questiondata->options->testcases[] = $testcase;
            }
        }

        if (isset($backupdata["plugin_qtype_{$qtype}_question"]['coderunner_options'])) {
            $questiondata->options = (object) array_merge(
                (array) $questiondata->options,
                $backupdata["plugin_qtype_{$qtype}_question"]['coderunner_options']['coderunner_option'][0]
            );
        }

        return $questiondata;
    }

    // #[Override]  // This breaks the restore at run time if used with older versions of moodle so commented out.
    protected function define_excluded_identity_hash_fields(): array {
        return [
            '/answers',
            '/hints',
            '/prototype',
            '/options/customise',
            '/options/templateparamsevald',
            '/options/testcases/id',
            '/options/testcases/questionid',
        ];
    }

    // #[Override]  // This breaks the restore at run time if used with older versions of moodle so commented out.
    public static function remove_excluded_question_data(stdClass $questiondata, array $excludefields = []): stdClass {
        if (isset($questiondata->options->customise)) {
            unset($questiondata->options->customise);
        }
        if (isset($questiondata->prototype)) {
            unset($questiondata->prototype);
        }
        if (isset($questiondata->answers)) {
            unset($questiondata->answers);
        }
        if (isset($questiondata->options->answers)) {
            unset($questiondata->options->answers);
        }
        if (isset($questiondata->hints)) {
            unset($questiondata->hints);
        }

        // Hack: convert all cases of allowmultiplestdins being false to null.
        if (isset($questiondata->options) && ! ($questiondata->options->allowmultiplestdins ?? null)) {
            $questiondata->options->allowmultiplestdins = null;
        }

        if (isset($questiondata->options->testcases)) {
            foreach ($questiondata->options->testcases as $testcase) {
                if (isset($testcase->id)) {
                    unset($testcase->id);
                }
                if (isset($testcase->questionid)) {
                    unset($testcase->questionid);
                }
            }
        }
        return parent::remove_excluded_question_data($questiondata, $excludefields);
    }
}
