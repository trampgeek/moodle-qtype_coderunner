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

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the editing form for the coderunner question type.
 *
 * @package 	questionbank
 * @subpackage 	questiontypes
 * @copyright 	&copy; 2013 Richard Lobb
 * @author 		Richard Lobb richard.lobb@canterbury.ac.nz
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
define("NUM_TESTCASES_START", 5); // Num empty test cases with new questions
define("NUM_TESTCASES_ADD", 3);   // Extra empty test cases to add

/**
 * coderunner editing form definition.
 */
class qtype_coderunner_edit_form extends question_edit_form {

    /**testcode
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    var $_textarea_or_htmleditor_generalfb;   //addElement type for general feedback
    var $_editor_options_generalfb;           //in dependence of editor type set a different array for its options

    function qtype() {
        return 'coderunner';
    }


    protected function definition() {
        // Override to add my coderunner_type selector at the top
        $mform = $this->_form;
        $options = array_merge(array('undefined' => 'Set type...'), $this->get_types());

        $mform->addElement('header', 'generalheader', get_string('type_header','qtype_coderunner'));
        $mform->addElement('select', 'coderunner_type', get_string('coderunner_type', 'qtype_coderunner'), $options);
        $mform->addHelpButton('coderunner_type', 'coderunner_type', 'qtype_coderunner');
        parent::definition($mform);
    }


    public function definition_inner($mform) {

        // TODO: what was the purpose of the next 2 lines?
        //$mform->addElement('static', 'answersinstruct');
        //$mform->closeHeaderBefore('answersinstruct');

        $gradeoptions = array(); // Unused
        if (isset($this->question->testcases)) {
            $numTestcases = count($this->question->testcases) + NUM_TESTCASES_ADD;
        }
        else {
            $numTestcases = NUM_TESTCASES_START;
        }


        $this->add_per_answer_fields($mform, get_string('testcase', 'qtype_coderunner'), $gradeoptions, $numTestcases);
        $this->add_interactive_settings();
    }


    /*
     *  Overridden so each 'answer' is a test case containing a ProgramCode testcode to be evaluated
     *  and a ProgramCode expected output value.
     */
    public function get_per_answer_fields($mform, $label, $gradeoptions,
            &$repeatedoptions, &$answersoption) {
        $repeated = array();
        $repeated[] = & $mform->createElement('header', 'answerhdr', $label);
        $repeated[] = & $mform->createElement('textarea', 'testcode',
                get_string('testcode', 'qtype_coderunner'),
                array('cols' => 80, 'rows' => 4, 'class' => 'testcaseexpression'));
        $repeated[] = & $mform->createElement('textarea', 'stdin',
                get_string('stdin', 'qtype_coderunner'),
                array('cols' => 80, 'rows' => 4, 'class' => 'testcasestdin'));
        $repeated[] = & $mform->createElement('textarea', 'output',
                get_string('output', 'qtype_coderunner'),
                array('cols' => 80, 'rows' => 4, 'class' => 'testcaseresult'));
        $repeated[] = & $mform->createElement('checkbox', 'useasexample', get_string('useasexample', 'qtype_coderunner'), false);
        $options = array();
        foreach ($this->displayOptions() as $opt) {
            $options[$opt] = get_string($opt, 'qtype_coderunner');
        }
        $repeated[] = & $mform->createElement('select', 'display', get_string('display', 'qtype_coderunner'), $options);
        $repeated[] = & $mform->createElement('checkbox', 'hiderestiffail', get_string('hiderestiffail', 'qtype_coderunner'), false);
        $repeatedoptions['output']['type'] = PARAM_RAW;

        // Lastly, a bit of hacking to keep add_per_answer_fields function happy
        if (isset($this->question->options)) {
            $this->question->options->answers = array();
        }
        $answersoption = 'answers';

        return $repeated;
    }



    // A list of the allowed values of the DB 'display' field for each testcase.
    protected function displayOptions() {
        return array('SHOW', 'HIDE', 'HIDE_IF_FAIL', 'HIDE_IF_SUCCEED');
    }


    public function data_preprocessing($question) {
        // Although it's not wildly obvious from the documentation, this method
        // needs to set up fields of the current question whose names match those
        // specified in get_per_answer_fields. These are used to load the
        // data into the form.
        if (isset($question->testcases)) { // Reloading a saved question?
            $question->testcode = array();
            $question->output = array();
            $question->useasexample = array();
            $question->display = array();
            $question->hiderestifail = array();
            foreach ($question->testcases as $tc) {
                $question->testcode[] = $tc->testcode;
                $question->stdin[] = $tc->stdin;
                $question->output[] = $tc->output;
                $question->useasexample[] = $tc->useasexample;
                $question->display[] = $tc->display;
                $question->hiderestiffail[] = $tc->hiderestiffail;
            }
        }
        return $question;
    }


    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['coderunner_type'] == 'undefined') {
            $errors['coderunner_type'] = get_string('typerequired', 'qtype_coderunner');
        }
        $testcodes = $data['testcode'];
        $stdins = $data['stdin'];
        $outputs = $data['output'];
        $count = 0;
        $cntNonemptyTests = 0;
        $num = max(count($testcodes), count($stdins), count($outputs));
        for ($i = 0; $i < $num; $i++) {
            $testcode = trim($testcodes[$i]);
            if ($testcode != '') {
                $cntNonemptyTests++;
            }
            $stdin = trim($stdins[$i]);
            $output = trim($outputs[$i]);
            if ($testcode !== '' || $stdin != '' || $output !== '') {
                $count++;
            }
        }

        if ($count == 0) {
            $errors["testcode[0]"] = get_string('atleastonetest', 'qtype_coderunner');
        }
        else if ($cntNonemptyTests != 0 && $cntNonemptyTests != $count) {
            $errors["testcode[0]"] = get_string('allornothing', 'qtype_coderunner');
        }
        return $errors;
    }



    private function get_types() {
        // Return a table (name => name) of all the non-custom coderunner types in the DB
        global $DB;
        $records = $DB->get_records('quest_coderunner_types', array('is_custom' => 0),
                'coderunner_type', 'coderunner_type');
        $types = array();
        foreach ($records as $row) {
            $types[$row->coderunner_type] = $row->coderunner_type;
        }
        return $types;

    }

}
