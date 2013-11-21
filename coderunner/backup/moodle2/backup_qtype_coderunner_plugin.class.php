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
 * Provides the information to backup coderunner questions.
 */
class backup_qtype_coderunner_plugin extends backup_qtype_plugin {

    // Legacy code, for supporting a subclassing of coderunner.
    protected function qtype() {
        return 'coderunner';
    }


   // Add the options to a coderunner question type structure
    function add_quest_coderunner_options($element) {
        // Check $element is one nested_backup_element
        if (! $element instanceof backup_nested_element) {
            throw new backup_step_exception("quest_coderunner_options_bad_parent_element", $element);
        }

        // Define the elements
        $options = new backup_nested_element('coderunner_options');
        $option = new backup_nested_element('coderunner_option', array('id'),
                array('coderunner_type', 'all_or_nothing', 'custom_template',
                      'showtest', 'showstdin', 'showexpected',
                      'showoutput', 'showmark', 'grader', 'cputimelimitsecs',
                      'memlimitmb'));

        //Build the tree
        $element->add_child($options);
        $options->add_child($option);

        // Set the source
        $option->set_source_table('quest_coderunner_options', array('questionid' => backup::VAR_PARENTID));

    }


    // Add the testcases table to the coderunner question structure
    function add_quest_coderunner_testcases($element) {
        // Check $element is one nested_backup_element
        if (! $element instanceof backup_nested_element) {
            throw new backup_step_exception("quest_testcases_bad_parent_element", $element);
        }

        // Define the elements
        $testcases = new backup_nested_element('coderunner_testcases');
        $testcase = new backup_nested_element('coderunner_testcase', array('id'), array(
            'testcode', 'expected', 'useasexample', 'display', 'hiderestiffail', 'mark', 'stdin'));

        // Build the tree
        $element->add_child($testcases);
        $testcases->add_child($testcase);

        // Set the source
        $testcase->set_source_table("quest_coderunner_testcases", array('questionid' => backup::VAR_PARENTID));

        // TODO Find out what the next line means
        // don't need to annotate ids nor files
    }



    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill
        $plugin = $this->get_plugin_element(null, '../../qtype', 'coderunner');

        // Create one standard named plugin element (the visible container)
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // connect the visible container ASAP
        $plugin->add_child($pluginwrapper);

        // Add in the testcases tables
        $this->add_quest_coderunner_options($pluginwrapper);
        $this->add_quest_coderunner_testcases($pluginwrapper);

        // don't need to annotate ids nor files
        // TODO Check what the above line means

        return $plugin;
    }
}
