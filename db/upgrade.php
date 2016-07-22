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

require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/lib/questionlib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');

function xmldb_qtype_coderunner_upgrade($oldversion) {
    global $CFG, $DB;
    update_question_types();

    return true;

}


function update_to_use_prototypes() {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    $table = new xmldb_table('quest_coderunner_options');

    $field = new xmldb_field('prototype_type', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'coderunner_type');

    // Conditionally launch add field prototype_type.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('combinator_template', XMLDB_TYPE_TEXT, null, null, null, null, null, 'showmark');

    // Conditionally launch add field combinator_template.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('test_splitter_re', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'combinator_template');

    // Conditionally launch add field test_splitter_re.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('language', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'per_test_template');

    // Conditionally launch add field language.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('sandbox', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'language');

    // Conditionally launch add field sandbox.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $index = new xmldb_index('prototype_type', XMLDB_INDEX_NOTUNIQUE, array('prototype_type'));

    // Conditionally launch add index prototype_type.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

    $index = new xmldb_index('coderunner_type', XMLDB_INDEX_NOTUNIQUE, array('coderunner_type'));

    // Conditionally launch add index coderunner_type.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

    $table = new xmldb_table('quest_coderunner_types');
    // Conditionally launch drop table coderunner_types.
    if ($dbman->table_exists($table)) {
        $dbman->drop_table($table);
    }
}


function update_question_types() {

    // Add/replace standard question types by deleting all questions in the
    // category CR_PROTOTYPES and reloading them from the file
    // questions-CR_PROTOTYPES.xml.

    // Find id of CR_PROTOTYPES category.
    global $DB, $CFG;

    $success = true;
    $systemcontext = context_system::instance();
    $systemcontextid = $systemcontext->id;
    if (!$systemcontextid) {
        $systemcontextid = 1; // HACK ALERT: occurs when phpunit initialising itself.
    }
    $category = $DB->get_record('question_categories',
                array('contextid' => $systemcontextid, 'name' => 'CR_PROTOTYPES'));
    if ($category) {
        $prototypecategoryid = $category->id;
    } else { // CR_PROTOTYPES category not defined yet. Add it.
        $category = array(
            'name'       => 'CR_PROTOTYPES',
            'contextid'  => $systemcontextid,
            'info'       => 'Category for CodeRunner question built-in prototypes. FOR SYSTEM USE ONLY.',
            'infoformat' => 0,
            'parent'     => 0,
        );
        $prototypecategoryid = $DB->insert_record('question_categories', $category);
        if (!$prototypecategoryid) {
            throw new coding_exception("Upgrade failed: couldn't create CR_PROTOTYPES category");
        }
        $category = $DB->get_record('question_categories',
                array('id' => $prototypecategoryid));
    }

    // Delete all existing prototypes.
    $prototypes = $DB->get_records_select('question',
            "category = $prototypecategoryid and name like '%PROTOTYPE_%'");
    foreach ($prototypes as $question) {
        $DB->delete_records('question_coderunner_options',
             array('questionid' => $question->id));
        $DB->delete_records('question', array('id' => $question->id));
    }

    $dbfiles = scandir(__DIR__);
    foreach ($dbfiles as $file) {
        // Load any files in the db directory ending with _PROTOTYPES.xml.
        if (strpos(strrev($file), strrev('_PROTOTYPES.xml')) === 0) {
            $filename = __DIR__ . '/' . $file;
            load_questions($category, $filename, $systemcontextid);
        }
    }

    return $success;
}


function load_questions($category, $importfilename, $contextid) {
    // Load all the questions from the given import file into the given category.
    // The category from the import file will be ignored if present.
    global $COURSE;
    $qformat = new qformat_xml();
    $qformat->setCategory($category);
    $systemcontext = context::instance_by_id($contextid);
    $contexts = new question_edit_contexts($systemcontext);
    $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
    $qformat->setCourse($COURSE);
    $qformat->setFilename($importfilename);
    $qformat->setRealfilename($importfilename);
    $qformat->setMatchgrades('error');
    $qformat->setCatfromfile(false);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);

    // Do anything before that we need to.
    if (!$qformat->importpreprocess()) {
        throw new coding_exception('Upgrade failed: error preprocessing prototype upload');
    }

    // Process the given file.
    if (!$qformat->importprocess($category)) {
        throw new coding_exception('Upgrade failed: error uploading prototype questions');
    }

    // In case anything needs to be done after.
    if (!$qformat->importpostprocess()) {
        throw new coding_exception('Upgrade failed: error postprocessing prototype upload');
    }
}


function make_result_columns() {
    // Find all questions using non-standard result table display and
    // build a result_columns field that matches the currently defined
    // set of showtest, showstdin, showexpected, showoutput and showmark
    // This code should only run prior to the version 2.4 upgrade.
    global $DB;

    $questions = $DB->get_records_select('quest_coderunner_options',
            "showtest != 1 or showstdin != 1 or showexpected != 1 or showoutput != 1 or showmark = 1");
    foreach ($questions as $q) {
        $cols = array();
        if ($q->showtest) {
            $cols[] = '["Test", "testcode"]';
        }
        if ($q->showstdin) {
            $cols[] = '["Input", "stdin"]';
        }
        if ($q->showexpected) {
            $cols[] = '["Expected", "expected"]';
        }
        if ($q->showoutput) {
            $cols[] = '["Got", "got"]';
        }
        if ($q->showmark) {
            $cols[] = '["Mark", "awarded", "mark", "%.2f/%.2f"]';
        }
        $field = '[' . implode(",", $cols) . ']';
        $rowid = $q->id;
        $query = "UPDATE {quest_coderunner_options} "
            . "SET result_columns = '$field' WHERE id=$rowid";
        $DB->execute($query);
    }
}


