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

function xmldb_qtype_coderunner_upgrade($oldversion) {
    global $DB;
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
        upgrade_plugin_savepoint(true, 2016111200, 'qtype', 'coderunner');
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

    if ($oldversion < 2017071100) {
        // Define field allowmultiplestdins to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('allowmultiplestdins', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'combinatortemplate');

        // Conditionally launch add field allowmultiplestdins.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017071100, 'qtype', 'coderunner');
    }

    if ($oldversion < 2017072800) {
        // Changing type of field templateparams on table question_coderunner_options to text.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('templateparams', XMLDB_TYPE_TEXT, null, null, null, null, null, 'pertesttemplate');

        // Launch change of type for field templateparams.
        $dbman->change_field_type($table, $field);

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2017072800, 'qtype', 'coderunner');
    }

    if ($oldversion < 2017121101) {
        // Define field uiplugin to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('uiplugin', XMLDB_TYPE_TEXT, null, null, null, null, null, 'useace');

        // Conditionally launch add field uiplugin.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Changing attributes of field useace in table question_coderunner_options to isnull.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('useace', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'answerpreload');

        // Launch change of type for field templateparams.
        $dbman->change_field_type($table, $field);

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2017121101, 'qtype', 'coderunner');
    }

    if ($oldversion < 2018040400) {
        // Define field hoisttemplateparams to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('hoisttemplateparams', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'templateparams');

        // Conditionally launch add field hoisttemplateparams.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2018040400, 'qtype', 'coderunner');
    }

    if ($oldversion < 2018041700) {
        // Define field twigall to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('twigall', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'hoisttemplateparams');

        // Conditionally launch add field twigall.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2018041700, 'qtype', 'coderunner');
    }

    if ($oldversion < 2018120500) {
         // Define fields attachments, attachmentsrequired, maxfilesize and
        // filetypeslist to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');

        $field = new xmldb_field('attachments', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'uiplugin');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('attachmentsrequired', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'attachments');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxfilesize', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0', 'attachmentsrequired');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('filenamesregex', XMLDB_TYPE_TEXT, null, null, null, null, null, 'maxfilesize');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('filenamesexplain', XMLDB_TYPE_TEXT, null, null, null, null, null, 'filenamesregex');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2018120500, 'qtype', 'coderunner');
    }

    if ($oldversion < 2018121002) {
        // Define field displayfeedback to control display of result table.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('displayfeedback', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'filenamesregex');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2018121002, 'qtype', 'coderunner');
    }

    if ($oldversion < 2019051600) {
        // Changing the default of field useace on table question_coderunner_options to 1.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('useace', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'answerpreload');

        // Launch change of default for field useace.
        $dbman->change_field_default($table, $field);

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2019051600, 'qtype', 'coderunner');
    }

    if ($oldversion < 2019080500) {
        // Define field globalextra to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('globalextra', XMLDB_TYPE_TEXT, null, null, null, null, null, 'answerpreload');

        // Conditionally launch add field globalextra.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2019080500, 'qtype', 'coderunner');
    }

    if ($oldversion < 2019111300) {
        // Change default for field validateonsave from false to true.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('validateonsave', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'answer');

        // Launch change of type for field templateparams.
        $dbman->change_field_default($table, $field);

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2019111300, 'qtype', 'coderunner');
    }

    if ($oldversion < 2020120701) {
        // Define field templateparamslang to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('templateparamslang', XMLDB_TYPE_CHAR, '50', null, null, null, 'twig', 'templateparams');

        // Conditionally launch add field templateparamslang.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2020120701, 'qtype', 'coderunner');
    }

    if ($oldversion < 2020121501) {
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('templateparamsevalpertry', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'templateparamslang');

        // Conditionally launch add field templateparamsevalpertry.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('templateparamsevald', XMLDB_TYPE_TEXT, null, null, null, null, null, 'templateparamsevalpertry');

        // Conditionally launch add field templateparamsevald.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2020121501, 'qtype', 'coderunner');
    }

    if ($oldversion < 2021010113) {
        // Define field uiparameters to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('uiparameters', XMLDB_TYPE_TEXT, null, null, null, null, null, 'twigall');

        // Conditionally launch add field uiparameters.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2021010113, 'qtype', 'coderunner');
    }

    if ($oldversion < 2021012200) {
        // Define field hidecheck to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('hidecheck', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'precheck');

        // Conditionally launch add field hidecheck.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2021012200, 'qtype', 'coderunner');
    }

    if ($oldversion < 2021111000) {
        // Define field giveupallowed to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('giveupallowed', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'displayfeedback');

        // Conditionally launch add field giveupallowed.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2021111000, 'qtype', 'coderunner');
    }

    if ($oldversion < 2022012703) {
        // Define field prototypeextra to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('prototypeextra', XMLDB_TYPE_TEXT, null, null, null, null, null, 'giveupallowed');

        // Conditionally launch add field prototypeextra.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2022012703, 'qtype', 'coderunner');
    }

    if ($oldversion < 2023013002) {
        // Define field extractcodefromjson to be added to question_coderunner_options.
        $table = new xmldb_table('question_coderunner_options');
        $field = new xmldb_field('extractcodefromjson', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'hoisttemplateparams');

        // Conditionally launch add field extractcodefromjson.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        } else {
            $dbman->change_field_type($table, $field); // Change from NOTNULL to NULL if already there.
        }

        // Coderunner savepoint reached.
        upgrade_plugin_savepoint(true, 2023013002, 'qtype', 'coderunner');
    }

    require_once(__DIR__ . '/upgradelib.php');
    update_question_types();

    return true;
}
