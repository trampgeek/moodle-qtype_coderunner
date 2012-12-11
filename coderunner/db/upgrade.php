<?php

function xmldb_qtype_coderunner_upgrade($oldversion) {
    global $CFG, $DB;

    // Add/replace standard question types

    // Add the most simple Python type.
    // This type executes the student code followed by all the testcase
    // code.

    // ===============================================================
    $python3Basic =  array(
        'coderunner_type' => 'python3_basic',
        'is_custom' => 0,
        'comment' => 'Used for most Python3 questions. For each test case, ' .
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
        'language' => 'python3',
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
    );

    // ===============================================================
    $python2Basic =  array(
        'coderunner_type' => 'python2_basic',
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
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
    );


    // ===============================================================
    $python2pypy =  array(
        'coderunner_type' => 'python2_pypy',
        'is_custom' => 0,
        'comment' => 'Used for Python 2.7 questions, using the pypy sandbox',
        'combinator_template' => NULL,
        'per_test_template' => "{{STUDENT_ANSWER}}\n\n{{TEST.testcode}}",
        'language' => 'python2',
        'sandbox'  => 'PypySandbox',
        'validator' => 'BasicValidator'
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
#define SEPARATOR "#<ab@17943918#@>#"

{{ STUDENT_ANSWER }}

int main() {
{% for TEST in TESTCASES %}
    {{ TEST.testcode }};
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
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
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
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
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
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
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
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
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
        'sandbox'  => 'NullSandbox',
        'validator' => 'BasicValidator'
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
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
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
    String SEPARATOR = "#<ab@17943918#@>#";
    {{ STUDENT_ANSWER }}

    public static void main(String[] args) {
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
        'test_splitter_re' => "|#<ab@17943918#@>#\n|ms",
        'per_test_template' => <<<EOT
public class Main {

    {{ STUDENT_ANSWER }}

    public static void main(String[] args) {
        {{ TEST.testcode }}
    }
}
EOT
,
        'language' => 'Java',
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
    );

    // ===============================================================
    $javaMethodSideEffects = array(
        'coderunner_type' => 'java_method_side_effects',
        'is_custom' => 0,
        'comment' => 'Used for Java write-a-method questions where ' .
                'the method is essentially a stand-alone function ' .
                'but might have side effects (e.g. generate output). ' .
                'Differs from type java_method in that it does not attempt to ' .
                'wrap all tests into a single run, but tests each case ' .
                'with a separate program.',
        'combinator_template' => NULL,
        'test_splitter_re' => '',
        'per_test_template' => <<<EOT
public class Main {

    {{ STUDENT_ANSWER }}

    public static void main(String[] args) {
        {{ TEST.testcode }};
    }
}
EOT
,
        'language' => 'Java',
        'sandbox'  => 'LiuSandbox',
        'validator' => 'BasicValidator'
    );

    $types = array($python3Basic, $python2Basic, $cFunction,
        $cFunctionSideEffects, $cProgram, $cFullMainTests, $matlabFunction);

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
