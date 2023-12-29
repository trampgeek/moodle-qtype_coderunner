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
 * Provides the information to backup coderunner questions.
 */
class backup_qtype_coderunner_plugin extends backup_qtype_plugin {
    // Legacy code, for supporting a subclassing of coderunner.
    protected function qtype() {
        return 'coderunner';
    }


    // Add the options to a coderunner question type structure.
    public function add_quest_coderunner_options($element) {
        $dummycoderunnerq = new qtype_coderunner();

        // Check $element is one nested_backup_element.
        if (! $element instanceof backup_nested_element) {
            throw new backup_step_exception("quest_coderunner_options_bad_parent_element", $element);
        }

        // Define the elements.
        $options = new backup_nested_element('coderunner_options');
        $optionfields = $dummycoderunnerq->extra_question_fields(); // It's not static :-(.
        array_shift($optionfields);
        $option = new backup_nested_element(
            'coderunner_option',
            ['id'],
            $optionfields
        );

        // Build the tree.
        $element->add_child($options);
        $options->add_child($option);

        // Set the source.
        $option->set_source_table('question_coderunner_options', ['questionid' => backup::VAR_PARENTID]);
    }


    // Add the testcases table to the coderunner question structure.
    private function add_quest_coderunner_testcases($element) {
        // Check $element is one nested_backup_element.
        if (! $element instanceof backup_nested_element) {
            throw new backup_step_exception("quest_testcases_bad_parent_element", $element);
        }

        // Define the elements.
        $testcases = new backup_nested_element('coderunner_testcases');
        $testcase = new backup_nested_element('coderunner_testcase', ['id'], [
            'testcode', 'testtype', 'expected', 'useasexample', 'display', 'hiderestiffail', 'mark', 'stdin', 'extra']);

        // Build the tree.
        $element->add_child($testcases);
        $testcases->add_child($testcase);

        // Set the source.
        $testcase->set_source_table("question_coderunner_tests", ['questionid' => backup::VAR_PARENTID], 'id ASC');
    }



    /**
     * Returns the qtype information to attach to question element.
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'coderunner');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Add in the testcases tables.
        $this->add_quest_coderunner_options($pluginwrapper);
        $this->add_quest_coderunner_testcases($pluginwrapper);

        // AFAIK I don't need to annotate files as backup_stepslib.php annotates
        // all files in its define_execution method.
        // Am I meant to annotate IDs? Not sure.
        return $plugin;
    }


    /**
     * Returns one array with filearea => mappingname elements for the qtype.
     *
     * Used by {@link get_components_and_fileareas} to know about all the qtype
     * files to be processed both in backup and restore.
     */
    public static function get_qtype_fileareas() {
        return ['datafile' => 'question_created',
                     'samplefile' => 'question_created'];
    }
}
