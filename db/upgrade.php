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
 * Upgrade code for the CodeRunner question type.
 *
 * @param $oldversion the version of this plugin we are upgrading from.
 * @return bool success/failure.
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_coderunner_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2016111105) {
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

    if ($oldversion < 2016111200) {
        // Define field precheck to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_tests');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '8', null, null, null, '0', 'questionid');

        // Conditionally launch add field type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, '2016111200', 'qtype', 'coderunner');
    }

    if ($oldversion < 2016111201) {

        // Rename field type on table question_coderunner_tests to testtype.
        $table = new xmldb_table('question_coderunner_tests');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '8', null, null, null, null, 'questionid');

        // Launch rename field testtype.
        $dbman->rename_field($table, $field, 'testtype');

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2016111201, 'qtype', 'coderunner');
    }

    if ($oldversion < 2016112107) {

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

    if ($oldversion < 2016120101) {

        // Define field answerpreload to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('answerpreload', XMLDB_TYPE_TEXT, null, null, null, null, null, 'answerboxcolumns');

        // Conditionally launch add field answerpreload.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop all the obsolete "show <column>" fields.

        $fieldmark = new xmldb_field('showmark');
        // Conditionally launch drop field showmark.
        if ($dbman->field_exists($table, $fieldmark)) {
            $dbman->drop_field($table, $fieldmark);
        }

        $fieldoutput = new xmldb_field('showoutput');
        // Conditionally launch drop field showoutput.
        if ($dbman->field_exists($table, $fieldoutput)) {
            $dbman->drop_field($table, $fieldoutput);
        }

        $fieldtest = new xmldb_field('showtest');
        // Conditionally launch drop field showtest.
        if ($dbman->field_exists($table, $fieldtest)) {
            $dbman->drop_field($table, $fieldtest);
        }

        $fieldstdin = new xmldb_field('showstdin');
        // Conditionally launch drop field showstdin.
        if ($dbman->field_exists($table, $fieldstdin)) {
            $dbman->drop_field($table, $fieldstdin);
        }

        $fieldexpected = new xmldb_field('showexpected');
        // Conditionally launch drop field showstdin.
        if ($dbman->field_exists($table, $fieldexpected)) {
            $dbman->drop_field($table, $fieldexpected);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2016120101, 'qtype', 'coderunner');
    }

    if ($oldversion < 2016123001) {

        // Define field validateonsave to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('validateonsave', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'answer');

        // Conditionally launch add field validateonsave.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2016123001, 'qtype', 'coderunner');
    }

    require_once(__DIR__ . '/upgradelib.php');
    update_question_types();

    return true;
}
