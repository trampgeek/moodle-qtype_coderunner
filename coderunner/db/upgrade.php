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

__student_answer__ = """{{ ESCAPED_STUDENT_ANSWER }}"""

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

__student_answer__ = """{{ ESCAPED_STUDENT_ANSWER }}"""

{{TEST.testcode}}
EOT
,
        'language' => 'python3',
    );

    // ===============================================================
    $python2 =  array(
        'coderunner_type' => 'python2',
        'is_custom' => 0,
        'comment' => 'Used for most Python2 questions. For each test case, ' .
                     'runs the student code followed by the test code',
        'combinator_template' => <<<EOT
{{ STUDENT_ANSWER }}

__student_answer__ = """{{ ESCAPED_STUDENT_ANSWER }}"""

SEPARATOR = "#<ab@17943918#@>#"

{% for TEST in TESTCASES %}
{{ TEST.testcode }};
{% if not loop.last %}
print(SEPARATOR)
{% endif %}
{% endfor %}
EOT
,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
{{STUDENT_ANSWER}}

__student_answer__ = """{{ ESCAPED_STUDENT_ANSWER }}"""

{{TEST.testcode}}
EOT
,
        'language' => 'python2',
    );

    // ===============================================================
    $python2NullSandbox =  array(
        'coderunner_type' => 'python2_nullsandbox',
        'is_custom' => 0,
        'comment' => 'Used for testing the null sandbox.',
        'combinator_template' => <<<EOT
{{ STUDENT_ANSWER }}

__student_answer__ = """{{ ESCAPED_STUDENT_ANSWER }}"""

SEPARATOR = "#<ab@17943918#@>#"

{% for TEST in TESTCASES %}
{{ TEST.testcode }};
{% if not loop.last %}
print(SEPARATOR)
{% endif %}
{% endfor %}
EOT
,
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
{{STUDENT_ANSWER}}

__student_answer__ = """{{ ESCAPED_STUDENT_ANSWER }}"""

{{TEST.testcode}}
EOT
,
        'language' => 'python2',
        'sandbox'  => 'NullSandbox',
    );


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
    $cFunctionSideEffects = array(
        'coderunner_type' => 'c_function_side_effects',
        'is_custom' => 0,
        'comment' => 'Used for C write-a-function questions where ' .
                'the function might have side effects (e.g. generate output). ' .
                'Differs from type c_function in that it does not attempt to ' .
                'wrap all tests into a single run, but tests each case ' .
                'with a separate program.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => <<<EOT
#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>

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
        'per_test_template' => "#include <stdio.h>\n#include <stdlib.h>\n#include <ctype.h>\n{{STUDENT_ANSWER}}\n\n{{TEST.testcode}}",
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
        'per_test_template' => "function tester()\n  {{TEST.testcode}};quit();\nend\n\n{{STUDENT_ANSWER}}",
        'language' => 'matlab',
    );
    // ===============================================================
    $javaProgram = array(
        'coderunner_type' => 'java_program',
        'is_custom' => 0,
        'comment' => 'Used for Java write-a-program questions, where there is ' .
                'no per-test-case code, and the different tests just use ' .
                'different stdin data.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => '{{ STUDENT_ANSWER }}',
        'language' => 'Java',
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
        'sandbox'  => 'NullSandbox',
        'validator' => 'BasicValidator'
    );

    // ===============================================================
    $javaMethodSideEffects = array(
        'coderunner_type' => 'java_method_side_effects',
        'is_custom' => 0,
        'comment' => 'Used for Java write-a-method questions where ' .
                'the method is essentially a stand-alone function ' .
                'but might have side effects (e.g. consume input). ' .
                'Differs from type java_method in that it does not attempt to ' .
                'wrap all tests into a single run, but tests each case ' .
                'with a separate program.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
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
    );

    // List of currently supported question types
    // ==========================================
    $types = array(
        $python2,
        $python3,
        $cFunction,
        $cFunctionSideEffects,
        $cProgram,
        $cFullMainTests,
        $matlabFunction,
        //$python2NullSandbox,
        $javaMethod,
        //$javaMethodSideEffects,
        $javaClass,
        $javaProgram);

    $success = TRUE;
    foreach ($types as $type) {
        $success = $success && update_question_type($type);
    }
    return $success;
}


function update_question_type($newRecord) {
    global $CFG, $DB;
    $DB->delete_records('quest_coderunner_types', array('coderunner_type' => $newRecord['coderunner_type']));
    if (!$DB->insert_record('quest_coderunner_types', $newRecord)) {
        throw new coding_exception("Upgrade failed: couldn't insert coderunner_type record");
        return FALSE;
    }
    return TRUE;
}
?>
