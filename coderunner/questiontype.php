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
//
///////////////////
/// coderunner ///
///////////////////
/// CODERUNNER QUESTION TYPE CLASS //////////////////
// The class for programming code questions.
// A coderunner question consists of a specification for piece of program
// code, which might be a function or a complete program or (possibly in the
// future) a fragment of code.
// The student's response must be source code that defines
// the specified function. The student's code is executed by
// a set of test cases, all of which must pass for the question
// to be marked correct. The code execution takes place in an external
// sandbox.
// In a typical use case each coderunner question will have its
// own submit button and students will keep submitting until
// they pass all tests, so that their mark will be based on
// the number of submissions and the penalty per wrong
// submissions.  However, there is the capability to allow per-test-case
// part marks by turning off the "all-or-nothing" checkbox when authoring the
// question.

/**
 * @package 	qtype
 * @subpackage 	coderunner
 * @copyright 	&copy; 2012 Richard Lobb
 * @author 	Richard Lobb richard.lobb@canterbury.ac.nz
 */

define('COMPUTE_STATS', false);
define('DEFAULT_GRADER', 'EqualityGrader');

require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/sandbox_config.php');

/**
 * qtype_coderunner extends the base question_type to coderunner-specific functionality.
 * A coderunner question requires an additional DB table
 * that contains the definitions for the testcases associated with a programming code
 * question. There are an arbitrary number of these, so they can't be handled
 * by adding columns to the standard question table.
 * Each subclass cas its own testcase database table.
 *
 * Note: the database tables were to be given names like question_coderunner_testcases
 * but names are limited to 28 chars! So quest_coderunner_.* was used instead.
 */
class qtype_coderunner extends question_type {

    /**
     * Whether this question type can perform a frequency analysis of student
     * responses.
     *
     * If this method returns true, you must implement the get_possible_responses
     * method, and the question_definition class must implement the
     * classify_response method.
     *
     * @return bool whether this report can analyse all the student reponses
     * for things like the quiz statistics report.
     */
    public function can_analyse_responses() {
        return FALSE;  // TODO Consider if this functionality should be enabled
    }

    /**
     * If your question type has a table that extends the question table, and
     * you want the base class to automatically save, backup and restore the extra fields,
     * override this method to return an array where the first element is the table name,
     * and the subsequent entries are the column names (apart from id and questionid).
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */
    public function extra_question_fields() {
        return array('quest_coderunner_options', 'coderunner_type',
            'per_test_template', 'all_or_nothing',
            'show_source', 'showtest', 'showstdin', 'showexpected', 'showoutput',
            'showmark', 'grader', 'cputimelimitsecs', 'memlimitmb');
    }

    /**
     * If you use extra_question_fields, overload this function to return question id field name
     * in case you table use another name for this column.
     * [Don't really need this as we're returning the default value, but I
     * prefer to be explicit.]
     */
    public function questionid_column_name() {
        return 'questionid';
    }


    /**
     * Abstract function implemented by each question type. It runs all the code
     * required to set up and save a question of any type for testing purposes.
     * Alternate DB table prefix may be used to facilitate data deletion.
     */
    public function generate_test($name, $courseid=null) {
        // Closer inspection shows that this method isn't actually implemented
        // by even the standard question types and wouldn't be called for any
        // non-standard ones even if implemented. I'm leaving the stub in, in
        // case it's ever needed, but have set it to throw and exception, and
        // I've removed the actual test code.
        throw new coding_exception('Unexpected call to generate_test. Read code for details.');
    }


// Function to copy testcases from form fields into question->testcases
    private function copy_testcases_from_form(&$question) {
        $testcases = array();
        $numTests = count($question->testcode);
        assert(count($question->expected) == $numTests);
        for($i = 0; $i < $numTests; $i++) {
            $input = $this->filterCrs($question->testcode[$i]);
            $stdin = $this->filterCrs($question->stdin[$i]);
            $expected = $this->filterCrs($question->expected[$i]);
            if ($input == '' && $stdin == '' && $expected == '') {
                continue;
            }
            $testcase = new stdClass;
            $testcase->questionid = isset($question->id) ? $question->id : 0;
            $testcase->testcode = $input;
            $testcase->stdin = $stdin;
            $testcase->expected = $expected;
            $testcase->useasexample = isset($question->useasexample[$i]);
            $testcase->display = $question->display[$i];
            $testcase->hiderestiffail = isset($question->hiderestiffail[$i]);
            $testcase->mark = trim($question->mark[$i]) == '' ? 1.0 : floatval($question->mark[$i]);
            $testcases[] = $testcase;
        }

        $question->testcases = $testcases;  // Can't call setTestcases as question is a stdClass :-(
    }

    // This override saves all the extra question data, including
    // the set of testcases and any datafiles to the database.

    public function save_question_options($question) {
        global $DB;

        assert(isset($question->coderunner_type));
        if (!isset($question->customise) || !$question->customise) {
            // If customisation has been turned off, set all customisable
            // fields to their defaults
            $question->per_test_template = NULL;
            $question->cputimelimitsecs = NULL;
            $question->memlimitmb = NULL;
            $question->showtest = True;
            $question->showstdin = True;
            $question->showexpected = True;
            $question->showoutput = True;
            $question->showmark = False;
            $question->grader = NULL;
        } else {
            if (trim($question->per_test_template) == '') {
                $question->per_test_template = NULL;
            }
            if (trim($question->cputimelimitsecs) == '') {
                $question->cputimelimitsecs = NULL;
            }
            if (trim($question->memlimitmb) == '') {
                $question->memlimitmb = NULL;
            }
            if (trim($question->grader) === DEFAULT_GRADER) {
                $question->grader = NULL;
            }
        }

        parent::save_question_options($question);

        $testcaseTable = "quest_coderunner_testcases";

        if (!isset($question->testcases)) {
            $this->copy_testcases_from_form($question);
        }

        if (!$oldtestcases = $DB->get_records($testcaseTable,
                array('questionid' => $question->id), 'id ASC')) {
            $oldtestcases = array();
        }

        foreach ($question->testcases as $tc) {
            if (($oldtestcase = array_shift($oldtestcases))) { // Existing testcase, so reuse it
                $tc->id = $oldtestcase->id;
                $DB->update_record($testcaseTable, $tc);
            } else {
                // A new testcase
                $tc->questionid = $question->id;
                $id = $DB->insert_record($testcaseTable, $tc);
            }
        }

        // delete old testcase records
        foreach ($oldtestcases as $otc) {
            $DB->delete_records($testcaseTable, array('id' => $otc->id));
        }


        // Lastly, save any datafiles

        file_save_draft_area_files($question->datafiles, $question->context->id,
                'qtype_coderunner', 'datafile', (int) $question->id, $this->fileoptions);

        return true;
    }

    // Load the question options (all the question extension fields and
    // testcases) from the database into the question.
    // If the question has a custom template, it is set as the per-test-case
    // template and the combinator template is ignored. Otherwise both are
    // copied from the database into the question object.

    public function get_question_options($question) {
        global $CFG, $DB, $OUTPUT;
        parent::get_question_options($question);

        // Now add to the question all the fields from the question's type record.
        // Where two fields have the same name in both the options and type tables,
        // the former overrides the latter and the question is then deemed to
        // be customed.

        if (!$row = $DB->get_record('quest_coderunner_types',
                array('coderunner_type' => $question->options->coderunner_type))) {
            throw new coding_exception("Failed to load type info for question id {$question->id}");
        }

        $question->options->customise = False; // Starting assumption
        foreach ($row as $field => $value) {
            if ($field != 'id' && $field != 'coderunner_type') {
                if (isset($question->options->$field) && $question->options->$field !== '') {
                    $question->options->customise = True;
                } else {
                    $question->options->$field = $value;
                }
            }
        }

        if (!isset($question->options->sandbox))  {
            $question->options->sandbox = $this->getBestSandbox($question->options->language);
        }

        if (!isset($question->options->grader)) {
            $question->options->grader = DEFAULT_GRADER;
        }

        // Add in the testcases
        if (!$question->options->testcases = $DB->get_records('quest_coderunner_testcases',
                array('questionid' => $question->id), 'id ASC')) {
            throw new coding_exception("Failed to load testcases for question id {$question->id}");
        }

        return true;
    }


    // Initialise the question_definition object from the questiondata
    // read from the database (probably a cached version of the question
    // object from the database enhanced by a call to get_question_options).
    // Only fields not explicitly listed in extra_question_fields (i.e. those
    // fields not from the quest_coderunner_options table) need handling here.
    // All we do is flatten the question->options fields down into the
    // question itself, which will be all those fields of question->options
    // not already flattened down by the parent implementation.

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        foreach ($questiondata->options as $field=>$value) {
            if (!isset($question->$field)) {
                $question->$field = $value;
            }
        }
    }


    // Delete the testcases when this question is deleted.
    public function delete_question($questionid, $contextid) {
        global $DB;

        $success = $DB->delete_records("quest_coderunner_testcases",
                array('questionid' => $questionid));
        return $success && parent::delete_question($questionid, $contextid);
    }


/// IMPORT/EXPORT FUNCTIONS /////////////////

    /*
     * Imports question from the Moodle XML format
     *
     * Overrides default since coderunner questions contain a list of testcases,
     * not a list of answers.
     *
     */
    function import_from_xml($data, $question, qformat_xml $format, $extra=null) {

        if ($extra != null) {
            throw new coding_exception("coderunner:import_from_xml: unexpected 'extra' parameter");
        }

        $question_type = $data['@']['type'];
        if ($question_type != $this->name()) {
            return false;
        }

        $extraquestionfields = $this->extra_question_fields();
        if (!is_array($extraquestionfields)) {
            return false;
        }

        //omit table name
        array_shift($extraquestionfields);
        $qo = $format->import_headers($data);
        $qo->qtype = $question_type;

        foreach ($extraquestionfields as $field) {
            if ($field === 'per_test_template'  && isset($data['#']['custom_template'])) {
                // Legacy import
                $qo->per_test_template = $format->getpath($data, array('#', 'custom_template', 0, '#'), '');
            }
            else {
                $qo->$field = $format->getpath($data, array('#', $field, 0, '#'), '');
            }
        }


        if (!isset($qo->all_or_nothing)) {
            $qo->all_or_nothing = 1; // Force all-or-nothing on old exports
        }

        $testcases = $data['#']['testcases'][0]['#']['testcase'];

        $qo->testcases = array();

        foreach ($testcases as $testcase) {
            $tc = new stdClass;
            $tc->testcode = $testcase['#']['testcode'][0]['#']['text'][0]['#'];
            $tc->stdin = $testcase['#']['stdin'][0]['#']['text'][0]['#'];
            if (isset($testcase['#']['output'])) { // Handle old exports
                $tc->expected = $testcase['#']['output'][0]['#']['text'][0]['#'];
            }
            else {
                $tc->expected = $testcase['#']['expected'][0]['#']['text'][0]['#'];
            }
            $tc->display = 'SHOW';
            $tc->mark = 1.0;
            if (isset($testcase['@']['mark'])) {
                $tc->mark = floatval($testcase['@']['mark']);
            }
            if (isset($testcase['@']['hidden']) && $testcase['@']['hidden'] == "1") {
                $tc->display = 'HIDE';  // Handle old-style export too
            }
            if (isset($testcase['#']['display'])) {
                $tc->display = $testcase['#']['display'][0]['#']['text'][0]['#'];
            }
            if (isset($testcase['@']['hiderestiffail'] )) {
                $tc->hiderestiffail = $testcase['@']['hiderestiffail'] == "1" ? 1 : 0;
            }
            else {
                $tc->hiderestiffail = 0;
            }
            $tc->useasexample = $testcase['@']['useasexample'] == "1" ? 1 : 0;
            $qo->testcases[] = $tc;
        }

        $datafiles = $format->getpath($data,
                array('#', 'testcases', 0, '#', 'file'), array());
        $qo->datafiles = $format->import_files_as_draft($datafiles);

        return $qo;
    }

    /*
     * Export question to the Moodle XML format
     *
     * We override the default method because we don't have 'answers' but
     * testcases.
     *
     */

    function export_to_xml($question, qformat_xml $format, $extra=null) {
        if ($extra !== null) {
            throw new coding_exception("coderunner:export_to_xml: Unexpected parameter");
        }

        $expout = parent::export_to_xml($question, $format, $extra);;

        $expout .= "    <testcases>\n";
        foreach ($question->options->testcases as $testcase) {
            $useasexample = $testcase->useasexample ? 1 : 0;
            $hiderestiffail = $testcase->hiderestiffail ? 1 : 0;
            $mark = sprintf("%.7f", $testcase->mark);
            $expout .= "      <testcase useasexample=\"$useasexample\" hiderestiffail=\"$hiderestiffail\" mark=\"$mark\" >\n";
            foreach (array('testcode', 'stdin', 'expected', 'display') as $field) {
                //$exportedValue = $format->xml_escape($testcase->$field);
                $exportedValue = $format->writetext($testcase->$field, 4);
                $expout .= "      <{$field}>\n        {$exportedValue}      </{$field}>\n";
            }
            $expout .= "    </testcase>\n";
        }

        // Add datafiles within the scope of the <testcases> element
        $fs = get_file_storage();
        $contextid = $question->contextid;
        $datafiles = $fs->get_area_files(
                $contextid, 'qtype_coderunner', 'datafile', $question->id);
        $expout .= $format->write_files($datafiles);

        $expout .= "    </testcases>\n";
        return $expout;
    }


    /** Utility func: remove all '\r' chars from $s and also trim trailing newlines */
    private function filterCrs($s) {
        $s = str_replace("\r", "", $s);
        while (substr($s, strlen($s) - 1, 1) == '\n') {
            $s = substr($s, 0, strlen($s) - 1);
        }
        return $s;
    }


    /** Find the 'best' sandbox for a given language, defined to be the
     *  first one in the ordered list of active sandboxes in sandbox_config.php
     *  that supports the given language.
     *  It's public so the tester can call it (yuck, hacky).
     *  @param type $language to run. Must match a language supported by at least one
     *  sandbox or an exception is thrown.
     * @return the preferred sandbox
     */
    public function getBestSandbox($language) {
        global $ACTIVE_SANDBOXES;
        foreach($ACTIVE_SANDBOXES as $sandbox) {
            require_once("Sandbox/$sandbox.php");
            $sb = new $sandbox();
            $langsSupported = $sb->getLanguages()->languages;
            if (in_array($language, $langsSupported)) {
                return $sandbox;
            }
        }
        throw new coding_exception("Config error: no sandbox found for language '$language'");
    }
}

