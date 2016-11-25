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
    $dbman = $DB->get_manager();

    if ($oldversion != 0 && $oldversion < 2016111105) {
        // Define field precheck to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('precheck', XMLDB_TYPE_INTEGER, '8', null, null, null, '0', 'answerboxlines');

        // Conditionally launch add field precheck.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2016111105, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2016111200) {
        // Define field precheck to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_tests');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '8', null, null, null, '0', 'questionid');

        // Conditionally launch add field precheck.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, '2016111200', 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2016111201) {

        // Rename field type on table question_coderunner_tests to testtype.
        $table = new xmldb_table('question_coderunner_tests');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '8', null, null, null, null, 'questionid');

        // Launch rename field testtype.
        $dbman->rename_field($table, $field, 'testtype');

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2016111201, 'qtype', 'coderunner');
    }

    update_question_types();

    if ($oldversion != 0 && $oldversion < 2016112107) {

        // Add fields 'template' and 'iscombinator'. Leave old pertesttemplate
        // and combinatortemplate fields in the DB for now, but they're defunct.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('template', XMLDB_TYPE_TEXT, null, null, null, null, null, 'resultcolumns');

        // Conditionally launch add field template.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('iscombinatortemplate', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'template');

        // Conditionally launch add field iscombinator.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // For each question that has its own template(s) defined (mostly
        // prototypes) copy either the per-test-template or combinator-template
        // into the new template field and set iscombinator accordingly.
        $sql1 = "UPDATE {question_coderunner_options}
                    SET template = pertesttemplate, iscombinatortemplate = 0
                  WHERE enablecombinator = 0
                    AND grader <> 'CombinatorTemplateGrader'
                    AND TRIM(COALESCE(pertesttemplate, '')) <> ''";
        $DB->execute($sql1);

        $sql2 = "UPDATE {question_coderunner_options}
                    SET template = combinatortemplate, iscombinatortemplate = 1
                  WHERE (enablecombinator = 1
                         OR grader = 'CombinatorTemplateGrader')
                    AND TRIM(COALESCE(combinatortemplate, '')) <> ''";
        $DB->execute($sql2);

        $sql3 = "UPDATE {question_coderunner_options}
                    SET grader = 'TemplateGrader'
                  WHERE grader = 'CombinatorTemplateGrader'";
        $DB->execute($sql3);

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2016112107, 'qtype', 'coderunner');
    }

    return true;

}

/**
 * Add/replace standard question types by deleting all questions in the
 * category CR_PROTOTYPES and reloading them from the file
 * questions-CR_PROTOTYPES.xml.
 *
 * @return bool true if successful
 */
function update_question_types() {

    // Find id of CR_PROTOTYPES category.
    global $DB;

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

/**
 * Load all the questions from the given import file into the given category.
 * The category from the import file will be ignored if present.
 *
 * @param stdClass $category row from the question_categories table.
 * @param string $importfilename full path of the file to import.
 * @param int $contextid id of the context to import into.
 */
function load_questions($category, $importfilename, $contextid) {
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

    ob_start(); // Discard import output during installs & upgrades.

    // Do anything before that we need to.
    if (!$qformat->importpreprocess()) {
        ob_end_clean();
        throw new coding_exception('Upgrade failed: error preprocessing prototype upload');
    }

    // Process the given file.
    if (!$qformat->importprocess($category)) {
        ob_end_clean();
        throw new coding_exception('Upgrade failed: error uploading prototype questions');
    }

    // In case anything needs to be done after.
    if (!$qformat->importpostprocess()) {
        ob_end_clean();
        throw new coding_exception('Upgrade failed: error postprocessing prototype upload');
    }
    ob_end_clean();
}
