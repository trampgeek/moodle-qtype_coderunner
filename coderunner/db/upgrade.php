<?php

function xmldb_qtype_coderunner_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    if ($oldversion != 0 && $oldversion < 2013010201) {
        $table = new xmldb_table('quest_coderunner_options');
        $allOrNothingField = new xmldb_field('all_or_nothing', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, TRUE, null, '1');
        $dbman->add_field($table, $allOrNothingField);

        $DB->set_field('quest_coderunner_options', 'coderunner_type', 'python3', array('coderunner_type' => 'python3_basic'));
        $DB->set_field('quest_coderunner_options', 'coderunner_type', 'python2', array('coderunner_type' => 'python2_basic'));
        $DB->delete_records('quest_coderunner_types', array('coderunner_type' => 'python3_basic'));
        $DB->delete_records('quest_coderunner_types', array('coderunner_type' => 'python2_basic'));
        upgrade_plugin_savepoint(true, 2013010201, 'qtype', 'coderunner');

    }

    if ($oldversion != 0 && $oldversion < 2013010202) {
        $table = new xmldb_table('quest_coderunner_testcases');
        $mark = new xmldb_field('mark', XMLDB_TYPE_NUMBER, '12', XMLDB_UNSIGNED, TRUE, null, '1.0');
        $dbman->add_field($table, $mark);
        upgrade_plugin_savepoint(true, 2013010202, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013010301) {
        // Allow NULL sandbox and validator fields
        $table = new xmldb_table('quest_coderunner_types');
        $sandbox = new xmldb_field('sandbox', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, FALSE, null);
        $validator = new xmldb_field('validator', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, FALSE, null);
        $dbman->change_field_type($table, $sandbox);
        $dbman->change_field_type($table, $validator);
        upgrade_plugin_savepoint(true, 2013010301, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013010501) {
        // Add custom template option to question
        $table = new xmldb_table('quest_coderunner_options');
        $customTemplate = new xmldb_field('custom_template', XMLDB_TYPE_TEXT, 'medium', XMLDB_UNSIGNED, FALSE, null);
        $dbman->add_field($table, $customTemplate);
        // Remove is_custom field from quest_coderunner_types
        $table = new xmldb_table('quest_coderunner_types');
        $fieldToDrop = new xmldb_field('is_custom', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, TRUE, null, '0');
        $dbman->drop_field($table, $fieldToDrop);
        upgrade_plugin_savepoint(true, 2013010502, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013013001) {
        $table = new xmldb_table('quest_coderunner_testcases');
        $mark = new xmldb_field('mark', XMLDB_TYPE_NUMBER, '8,3', XMLDB_UNSIGNED, TRUE, null, '1.0');
        $dbman->change_field_type($table, $mark);
        upgrade_plugin_savepoint(true, 2013013001, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013013101) {
        // Add show source option to question
        $table = new xmldb_table('quest_coderunner_options');
        $showSource = new xmldb_field('show_source', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, TRUE, null, '0');
        $dbman->add_field($table, $showSource);
        upgrade_plugin_savepoint(true, 2013013101, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013102401) {
        // Add booleans to control display of result table columns
        $table = new xmldb_table('quest_coderunner_options');
        foreach (array('showtest', 'showstdin', 'showexpected', 'showoutput', 'showmark') as $newBool) {
            $default = $newBool === 'showmark' ? 0 : 1;
            $field = new xmldb_field($newBool, XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, TRUE, null, $default);
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2013102401, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013102601) {
        $table = new xmldb_table('quest_coderunner_types');
        $validatorField = new xmldb_field('validator', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, FALSE, null);
        $dbman->rename_field($table, $validatorField, 'grader');
        upgrade_plugin_savepoint(true, 2013102601, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013103102) {
        $table = new xmldb_table('quest_coderunner_testcases');
        $outputField = new xmldb_field('output', XMLDB_TYPE_TEXT, 'medium', XMLDB_UNSIGNED, FALSE, null);
        $dbman->rename_field($table, $outputField, 'expected');
        upgrade_plugin_savepoint(true, 2013103102, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013110201) {
        $table = new xmldb_table('quest_coderunner_options');
        $template_does_grading = new xmldb_field('template_does_grading',
                XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, TRUE, null, 0, 'custom_template');
        $dbman->add_field($table, $template_does_grading);
        upgrade_plugin_savepoint(true, 2013110201, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013110401) {
        // Add booleans to control display of result table columns
        $table = new xmldb_table('quest_coderunner_options');
        $timelimit = new xmldb_field('timelimitsecs', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, FALSE, null, null);
        $dbman->add_field($table, $timelimit);
        $memlimit = new xmldb_field('memlimitmb', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, FALSE, null, null);
        $dbman->add_field($table, $memlimit);
        upgrade_plugin_savepoint(true, 2013110401, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013110701) {
        $table = new xmldb_table('quest_coderunner_options');
        $timelimit = new xmldb_field('timelimitsecs', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, FALSE, null, null);
        $dbman->rename_field($table, $timelimit, 'cputimelimitsecs');
        upgrade_plugin_savepoint(true, 2013110701, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013110702) {
        $table = new xmldb_table('quest_coderunner_types');
        $timelimit = new xmldb_field('cputimelimitsecs', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, FALSE, null, null);
        $dbman->add_field($table, $timelimit);
        $memlimit = new xmldb_field('memlimitmb', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, FALSE, null, null);
        $dbman->add_field($table, $memlimit);
        upgrade_plugin_savepoint(true, 2013110702, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013112101) {
        $table = new xmldb_table('quest_coderunner_options');
        $grader = new xmldb_field('grader', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, FALSE, null, null);
        $dbman->add_field($table, $grader);
        upgrade_plugin_savepoint(true, 2013112101, 'qtype', 'coderunner');
    }


    if ($oldversion != 0 && $oldversion < 2013112102) {
        $DB->set_field('quest_coderunner_options', 'grader', 'TemplateGrader', array('template_does_grading' => 1));
        $table = new xmldb_table('quest_coderunner_options');
        $template_does_grading = new xmldb_field('template_does_grading');
        $dbman->drop_field($table, $template_does_grading);
        upgrade_plugin_savepoint(true, 2013112102, 'qtype', 'coderunner');
    }


    if ($oldversion != 0 && $oldversion < 2013112202) {
        $table = new xmldb_table('quest_coderunner_options');
        $customTemplate = new xmldb_field('custom_template', XMLDB_TYPE_TEXT, 'medium', XMLDB_UNSIGNED, FALSE, null);
        $dbman->rename_field($table, $customTemplate, 'per_test_template');
        upgrade_plugin_savepoint(true, 2013112202, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2013112203) {
        upgrade_plugin_savepoint(true, 2013112203, 'qtype', 'coderunner');
    }

    if ($oldversion != 0 && $oldversion < 2014021501) {
        $table = new xmldb_table('quest_coderunner_options');
        $field = new xmldb_field('penalty_regime', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, FALSE, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2014021501, 'qtype', 'coderunner');
    }


    return updateQuestionTypes();

}


function updateQuestionTypes() {

    // Add/replace standard question types

    // Add the most simple Python type.
    // This type executes the student code followed by all the testcase
    // code.

    // ===============================================================
    $python3 =  array(
        'coderunner_type' => 'python3',
        'is_custom' => 0,
        'comment' => 'Used for most Python3 questions. For each test case, ' .
                     'runs the student code followed by the test code',
        'combinator_template' => <<<EOT
{{ STUDENT_ANSWER }}

__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

SEPARATOR = "#<ab@17943918#@>#"

{% for TEST in TESTCASES %}
{{ TEST.testcode }}
{% if not loop.last %}
print(SEPARATOR)
{% endif %}
{% endfor %}
EOT
,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
{{STUDENT_ANSWER}}

__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

{{TEST.testcode}}
EOT
,
        'language' => 'python3',
    );


    // ===============================================================
    //
    // Python3 Pylint Func
    //
    // ===============================================================
    $combinator_pylint_func = <<<'EOT'
__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

import subprocess

def check_code(s):
    try:
        source = open('source.py', 'w')
        source.write(s)
        source.close()
        result = subprocess.check_output(['pylint', 'source.py'], stderr=subprocess.STDOUT)
    except Exception as e:
        result = e.output.decode('utf-8')

    if result.strip():
        print("pylint doesn't approve of your program")
        print(result)
        raise Exception("Submission rejected")

check_code(__student_answer__)

{{ STUDENT_ANSWER }}

SEPARATOR = "#<ab@17943918#@>#"

{% for TEST in TESTCASES %}
{{ TEST.testcode }}
{% if not loop.last %}
print(SEPARATOR)
{% endif %}
{% endfor %}
EOT;

    $perTestTemplate_pylint_func = <<<'EOT'
__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

import subprocess

def check_code(s):
    try:
        source = open('source.py', 'w')
        source.write('"""Dummy module comment to keep pylint quiet"""\n' + s)
        source.close()
        result = subprocess.check_output(['pylint', 'source.py'], stderr=subprocess.STDOUT)
    except Exception as e:
        result = e.output.decode('utf-8')

    if result.strip():
        print("pylint doesn't approve of your program")
        print(result)
        raise Exception("Submission rejected")

check_code(__student_answer__)

{{ STUDENT_ANSWER }}
{{ TEST.testcode }}
EOT;

    $python3PylintFunc =  array(
        'coderunner_type' => 'python3_pylint_func',
        'is_custom' => 0,
        'comment' => 'Python3 functions with a pre-check by pylint',
        'combinator_template' => $combinator_pylint_func,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => $perTestTemplate_pylint_func,
        'language' => 'python3',
        'sandbox'  => 'RunguardSandbox',
        'memlimitmb' => 500
    );

    // ===============================================================
    //
    // Python3 Pylint Prog
    //
    // ===============================================================
    $combinator_pylint_prog = <<<'EOT'
__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

import subprocess

def check_code(s):
    try:
        source = open('source.py', 'w')
        source.write(s)
        source.close()
        result = subprocess.check_output(['pylint', 'source.py'], stderr=subprocess.STDOUT)
    except Exception as e:
        result = e.output.decode('utf-8')

    if result.strip():
        print("pylint doesn't approve of your program")
        print(result)
        raise Exception("Submission rejected")

check_code(__student_answer__)

{{ STUDENT_ANSWER }}

SEPARATOR = "#<ab@17943918#@>#"

{% for TEST in TESTCASES %}
{{ TEST.testcode }}
{% if not loop.last %}
print(SEPARATOR)
{% endif %}
{% endfor %}
EOT;

    $perTestTemplate_pylint_prog = <<<'EOT'
__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

import subprocess

def check_code(s):
    try:
        source = open('source.py', 'w')
        source.write(s)
        source.close()
        result = subprocess.check_output(['pylint', 'source.py'], stderr=subprocess.STDOUT)
    except Exception as e:
        result = e.output.decode('utf-8')

    if result.strip():
        print("pylint doesn't approve of your program")
        print(result)
        raise Exception("Submission rejected")

check_code(__student_answer__)

{{ STUDENT_ANSWER }}
{{ TEST.testcode }}
EOT;

    $python3PylintProg =  array(
        'coderunner_type' => 'python3_pylint_prog',
        'is_custom' => 0,
        'comment' => 'Python3 programs with a pre-check by pylint',
        'combinator_template' => $combinator_pylint_prog,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => $perTestTemplate_pylint_prog,
        'language' => 'python3',
        'sandbox'  => 'RunguardSandbox',
        'memlimitmb' => 500
    );

// ===============================================================
    $python3Ideone =  array(
        'coderunner_type' => 'python3_ideone',
        'is_custom' => 0,
        'comment' => 'Used for testing the Ideone sandbox.',
        'combinator_template' => <<<EOT
{{ STUDENT_ANSWER }}

__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

SEPARATOR = "#<ab@17943918#@>#"

{% for TEST in TESTCASES %}
{{ TEST.testcode }}
{% if not loop.last %}
print(SEPARATOR)
{% endif %}
{% endfor %}
EOT
,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
{{STUDENT_ANSWER}}

__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

{{ TEST.testcode }}
EOT
,
        'language' => 'python3',
        'sandbox'  => 'IdeoneSandbox',
    );

    // ===============================================================
    //
    // Python2
    //
    // ===============================================================
    $python2 =  array(
        'coderunner_type' => 'python2',
        'is_custom' => 0,
        'comment' => 'Used for most Python2 questions. For each test case, ' .
                     'runs the student code followed by the test code',
        'combinator_template' => <<<EOT
{{ STUDENT_ANSWER }}

__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

SEPARATOR = "#<ab@17943918#@>#"

{% for TEST in TESTCASES %}
{{ TEST.testcode }}
{% if not loop.last %}
print(SEPARATOR)
{% endif %}
{% endfor %}
EOT
,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
{{STUDENT_ANSWER}}

__student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

{{TEST.testcode}}
EOT
,
        'language' => 'python2',
    );


    // ===============================================================
    $cFunction = array(
        'coderunner_type' => 'c_function',
        'is_custom' => 0,
        'comment' => 'Used for C write-a-function questions but ' .
                'ONLY IF the function should have no side-effects. ' .
                'Must not be used for C functions that generate or consume ' .
                'output or alter global state in any way, as it attempts to ' .
                'wrap all tests into a single compile-and-run step',
        'combinator_template' => <<<EOT
#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>
#include <string.h>
#include <stdbool.h>
#define SEPARATOR "#<ab@17943918#@>#"

{{ STUDENT_ANSWER }}

int main() {
{% for TEST in TESTCASES %}
   {
    {{ TEST.testcode }};
   }
    {% if not loop.last %}printf("%s\\n", SEPARATOR);{% endif %}
{% endfor %}
    return 0;
}
EOT
,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>
#include <string.h>
#include <stdbool.h>

{{ STUDENT_ANSWER }}

int main() {
    {{ TEST.testcode }};
    return 0;
}
EOT
,
        'language' => 'C',
    );


    // ===============================================================
    $cProgram = array(
        'coderunner_type' => 'c_program',
        'is_custom' => 0,
        'comment' => 'Used for C write-a-program questions, where there is ' .
                'no per-test-case code, and the different tests just use ' .
                'different stdin data.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => '{{ STUDENT_ANSWER }}',
        'language' => 'C',
    );

    // ===============================================================
    $cFullMainTests = array(
        'coderunner_type' => 'c_full_main_tests',
        'is_custom' => 0,
        'comment' => 'Used for C questions where the student writes global ' .
             'declarations (types, functions etc) and each test case ' .
             'contains a complete main function that follows the student code.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => <<<EOT
#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>
#include <stdbool.h>
{{STUDENT_ANSWER}}

{{ TEST.testcode }}
EOT
,
        'language' => 'C',
    );

    // ===============================================================
    $matlabFunction =  array(
        'coderunner_type' => 'matlab_function',
        'is_custom' => 0,
        'comment' => 'Used for Matlab function questions. Student code must be ' .
                     'a function declaration, which is tested with each testcase.',
        'combinator_template' => <<<EOT
function tester()
   {% for TEST in TESTCASES %}
   {{ TEST.testcode }};
   {% if not loop.last %}
   disp('#<ab@17943918#@>#');
{% endif %}
{% endfor %}
   quit();
end

{{ STUDENT_ANSWER }}
EOT
,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => "function tester()\n  {{ TEST.testcode }};\n  quit();\nend\n\n{{ STUDENT_ANSWER }}",
        'language' => 'matlab',
        'cputimelimitsecs' => 10,  // Slow matlab :-(
        'memlimitmb' => 0          // TODO: why won't matlab run with a memory limit??
    );


    // ===============================================================
    $javaMethod = array(
        'coderunner_type' => 'java_method',
        'is_custom' => 0,
        'comment' => 'Used for Java write-a-method questions where ' .
                'the method is essentially a stand-alone function, but ' .
                'ONLY IF the function should have no side-effects. ' .
                'Must not be used for methods that generate or consume ' .
                'output or alter global state in any way, as it attempts to ' .
                'wrap all tests into a single compile-and-run step',
        'combinator_template' => <<<EOT
public class Main {
    static String SEPARATOR = "#<ab@17943918#@>#";
    {{ STUDENT_ANSWER }}

    public static void main(String[] args) {
        Main main = new Main();
        main.runTests();
    }

    public void runTests() {
{% for testCase in TESTCASES %}
    {{ testCase.testcode }};
    {% if not loop.last %}
    System.out.println(SEPARATOR);
    {% endif %}
{% endfor %}
    }
}
EOT
,
        // Now the test-per-program template
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
public class Main {

    {{ STUDENT_ANSWER }}

    public static void main(String[] args) {
        Main main = new Main();
        main.runTests();
    }

    public void runTests() {
        {{ TEST.testcode }};
    }
}
EOT
,
        'language' => 'Java',
        'sandbox'  => 'RunguardSandbox',
        'memlimitmb' => 2000  // 2GB!! Silly Java!
    );


   // ===============================================================
    $javaClass = array(
        'coderunner_type' => 'java_class',
        'is_custom' => 0,
        'comment' => 'Used for Java write-a-class questions where ' .
                'the student submits a complete class as their answer. ' .
                'Since the test cases for such questions will typically ' .
                'instantiate an object of the class and perform some tests' .
                'on it, no attempt is made to combine the different test cases ' .
                'into a single executable. Hence, this type of question is ' .
                'likely to be relatively slow to mark, requiring multiple ' .
                'compilations and runs. Each test case code is assumed to be ' .
                'a set of statements to be wrapped into the static void main ' .
                'method of a separate Main class.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => <<<EOT
{{ STUDENT_ANSWER }}

public class __Tester__ {

    public static void main(String[] args) {
        __Tester__ main = new __Tester__();
        main.runTests();
    }

    public void runTests() {
        {{ TEST.testcode }};
    }
}

EOT
,
        'language' => 'Java',
        'memlimitmb' => 2000  // 2GB!! Silly Java!
    );


  // ===============================================================
    $javaProgram = array(
        'coderunner_type' => 'java_program',
        'is_custom' => 0,
        'comment' => 'Used for Java write-a-program questions where ' .
                'the student submits a complete program as their answer. ' .
                'The program is executed for each test case. There is no ' .
                'test code, just stdin test data (though this isn\'t actually ' .
                'checked: caveat emptor). ' ,
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => <<<EOT
{{ STUDENT_ANSWER }}
EOT
,
        'language' => 'Java',
        'memlimitmb' => 2000  // 2GB!! Silly Java!
    );


    // ==============================================================
    $clojure = array(
        'coderunner_type' => 'clojure',
        'is_custom' => 0,
        'comment' => 'Test of Clojure questions where the student\' code is' .
                'run then the test code. There is currently no combinator, ' .
                'so the program is executed for each test case. Written mainly ' .
                'as a test of the Ideone sandbox.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => <<<EOT
{{ STUDENT_ANSWER }}
{{TEST.testcode}}
EOT
,
        'language' => 'Clojure (clojure 1.5.0-RC2)',
    );

    // List of currently supported question types
    // ==========================================
    $types = array(
        $python2,
        $python3,
        $python3PylintFunc,
        $python3PylintProg,
        $python3Ideone,
        $cFunction,
        $cProgram,
        $cFullMainTests,
        $matlabFunction,
        $javaMethod,
        $javaClass,
        $javaProgram,
        $clojure);

    $success = TRUE;
    foreach ($types as $type) {
        $success = $success && update_question_type($type);
    }

    // Delete defunct types.
    // NB: NO CHECKS ON EXISTING QUESTIONS USING THESE TYPES.
    // They must really be defunct, in the sense of no questions using them.
    $defunctTypes = array();
    foreach ($defunctTypes as $type) {
        $success = $success && delete_question_type($type);
    }

    return $success;
}


function update_question_type($newRecord) {
    global $DB;
    $DB->delete_records('quest_coderunner_types', array('coderunner_type' => $newRecord['coderunner_type']));
    if (!$DB->insert_record('quest_coderunner_types', $newRecord)) {
        throw new coding_exception("Upgrade failed: couldn't insert coderunner_type record");
        return FALSE;
    }
    return TRUE;
}


function delete_question_type($type) {
    global $DB;
    return $DB->delete_records('quest_coderunner_types', array('coderunner_type' => $type));
}
?>
