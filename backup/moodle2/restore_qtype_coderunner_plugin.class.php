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
 * restore plugin class that provides the necessary information
 * needed to restore one coderunner qtype plugin
 */
class restore_qtype_coderunner_plugin extends restore_qtype_plugin {

    private $legacynamemap = null; // Map from legacy DB column names to current.

    // Constructor builds a map from old DB field names to new ones for use
    // in function process_coderunner_options.
    public function __construct($plugintype, $pluginname, $step) {
        parent::__construct($plugintype, $pluginname, $step);
        $this->legacynamemap = array('custom_template' => 'pertesttemplate');
        $revmap = qtype_coderunner::legacy_field_name_map();
        foreach ($revmap as $new => $old) {
            $this->legacynamemap[$old] = $new;  // Invert the mapping.
        }
    }

    // Legacy code, for supporting a subclassing of coderunner.
    protected function qtype() {
        return 'coderunner';
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

    /**
     * Returns the paths to be handled by the plugin at question level.
     */
    public function define_question_plugin_structure() {

        $paths = array();

        // Add options and testcases to the restore structure.
        $this->add_question_options($paths);
        $this->add_question_testcases($paths);

        return $paths; // And return the paths.
    }

    /*
     * Called during restore to process the testcases within the
     * backup element.
     */
    public function process_coderunner_testcases($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its question_testcases and options too.
        if ($questioncreated) {
            $data->questionid = $newquestionid;
            if (isset($data->output)) {  // Handle old saved files.
                $data->expected = $data->output;
                unset($data->output);
            }
            // Insert record.
            $newitemid = $DB->insert_record("question_coderunner_tests", $data);
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
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its question_testcases and options too.
        if ($questioncreated) {
            $data->questionid = $newquestionid;
            // Remap various legacy names to new
            // names, in case we're restoring an earlier-version
            // backup.
            foreach ($this->legacynamemap as $old => $new) {
                if (isset($data->$old)) {
                    $data->$new = $data->$old;
                    unset($data->$old);
                }
            }

            // Insert the record.
            $newitemid = $DB->insert_record("question_coderunner_options", $data);
        }
        // Nothing to remap if the question already existed.

    }
}
