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

// TODO: Test me!

/**
 * restore plugin class that provides the necessary information
 * needed to restore one coderunner qtype plugin
 */
class restore_qtype_coderunner_plugin extends restore_qtype_plugin {

    // Legacy code, for supporting a subclassing of coderunner.
    protected function qtype() {
        return 'coderunner';
    }

	/*
	 * Add the testcases to the restore structure
	 */
    private function add_question_testcases(&$paths) {
        // Check $paths is one array
        if (!is_array($paths)) {
            throw new restore_step_exception('paths_must_be_array', $paths);
        }

        $elename = 'testcases';
        $elepath = $this->get_pathfor('/testcases/testcase');
        $paths[] = new restore_path_element($elename, $elepath);
    }

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    function define_question_plugin_structure() {

        $paths = array();

        // This qtype uses testcases rather than answers; add them
        $this->add_question_testcases($paths);

        return $paths; // And return the paths
    }

    /*
     * Called during restore to process the testcases within the
     * backup element.
     * TODO Check this out. It's copied from multichoice with some trepidation.
     */
    public function process_testcases($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its question_testcases too
        if ($questioncreated) {
            $data->questionid = $newquestionid;
            // Insert record
            $qtype= $this->qtype();
            $newitemid = $DB->insert_record("question_{$qtype}_testcases", $data);
        } else {
            // Nothing to remap if the question already existed
            // TODO: determine if the above statement is true!!
        }
    }
}
