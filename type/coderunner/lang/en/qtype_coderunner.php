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
 * Strings for component 'qtype_coderunner', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   qtype_coderunner
 * @copyright Richard Lobb 2012
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aborted'] = 'Testing was aborted due to error.';
$string['addingcoderunner'] = 'Adding a new CodeRunner Question';
$string['allok'] = 'Passed all tests! ';
$string['allornone'] = 'Test code must be provided either for all '
    . 'testcases or for none.';
$string['allornothing'] = 'All-or-nothing grading';
$string['allornothing_help'] = 'If \'All-or-nothing\' is checked, all test cases must be satisfied ' .
        'for the submission to earn any marks. Otherwise, the mark is obtained ' .
        'by summing the marks for all the test cases that pass ' .
        'and expressing this as a fraction of the maximum possible mark. ' .
        'The per-test-case marks can be specified only if the all-or-nothing ' .
        'checkbox is unchecked. If using a template grader that awards ' .
        'part marks to test cases, \'All-or-nothing\' should generally be unchecked.';
$string['answerrequired'] = 'Please provide a non-empty answer';
$string['atleastonetest'] = 'You must provide at least one test case '
    . 'for this question.';
$string['badcputime'] = 'CPU time limit must be left blank or must be an integer greater than zero';
$string['bad_new_prototype_name'] = 'Illegal name for new prototype: already in use';
$string['badmemlimit'] = 'Memory limit must either be left blank or must be a non-negative integer';
$string['badpenalties'] = 'Penalty regime must be a comma separated list of numbers in the range [0, 100]';
$string['columncontrols'] = 'Result table';
$string['coderunner'] = 'Program Code';
$string['coderunnertype'] = "Question type:";
$string['coderunnertype_help'] = "Select the programming language and question type.

Predefined types include

* function/method: where the student writes one or more functions/methods and the
tests call those functions. The tests are automatically wrapped into a main
function/method so usually each test is a one-liner that calls the function and
prints the result.
* class (Java): where the student writes an entire class and the test code
then instantiates that student class and tests it by calling its methods. Note
that the student-written class must not be public.
* program:  where the student writes the entire program which is then executed
with the standard input provided in each testcase.
* full_main_tests (C): where the student writes various declarations and
each test case is a full main function.

These various types are not applicable to Python, where the student's code is
always run first, followed by the test code.

It is also possible to customise the question type; click the 'Customise'
checkbox and read the help available on the newly-visible form elements for
more information.

If the template-debugging checkbox is clicked, the program generated
for each testcase will be displayed in the output.
";
$string['ace-language'] = "Ace language";
$string['advanced_customisation'] = "Advanced customisation";
$string['answer'] = "Answer";
$string['answerbox_group'] = "Answer box";
$string['answerboxcolumns'] = "Columns";
$string['answerboxlines'] = 'Rows';
$string['answerbox_group_help'] = 'Set the number of rows and columns to allocate for the answer box. ' .
        'If the answer overflows the box vertically or horizontally, scrollbars will appear.' .
        'If \'Use ace\' is checked, the ACE JavaScript code editor will manage the answer box.';
$string['asolutionis'] = "Question author's solution:";
$string['bad_dotdotdot'] = "Misuse of '...'. Must be at end, after two increasing numeric penalties";
$string['badpenalties'] = 'Penalty regime must be a comma separated list of numbers in the range [0, 100]';
$string['badtemplateparams'] = 'Template parameters must be either blank or a valid JSON record';
$string['badsandboxparams'] = '"Other" field (sandbox params) must be either blank or a valid JSON record';
$string['coderunnersummary'] = 'Answer is program code that is executed '
    . 'in the context of a set of test cases to determine its correctness.';
$string['coderunner_help'] = 'In response to a question, which is a '
    . 'specification for a program fragment, function or whole program, '
    . 'the respondent enters source code in a specified computer '
    . 'language that satisfies the specification.';
$string['coderunner_link'] = 'question/type/coderunner';
$string['columncontrols_help'] = 'The checkboxes select which columns of the ' .
        'results table should be displayed to the student after submission';

$string['combinatorcontrols'] = "Combinator";
$string['combinatorcontrols_help'] = <<<EO_TEMPLATE_HELP
Like the per-test-case template above, the combinator template defines a
program to be run given the student submission and the test data. Unlike the
per-test-case version this one attempts to build a single program using all
the test data, so that a single compile-and-run sequence is possible. However,
combinator templates are much more complex and not recommended for the faint
of heart. The combinator template is not used if there is any standard
input specified or if "template does grading" is checked.
Also, if a combinator run results in a runtime exception, the
system falls back to using a per-test-case template run with each test case,
in which case the gain from using the combinator is actually negative. The
output resulting from a combinator template run is split using the associated
test-splitter regular expression into a set of per-test outputs. If you alter
the per-test-case template you are strongly advised to disable the use of the
combinator altogether, rather than try to adjust it to match, until you have
shown that you really have a performance problem.
If the template-debugging checkbox is clicked, the program generated
for each testcase will be displayed in the output.
EO_TEMPLATE_HELP;
$string['combinator_required'] = "When using a combinator-template grader, the 'enable combinator' checkbox must be checked";
$string['combinatortemplate'] = "Template";
$string['cputime'] = 'TimeLimit (secs)';
$string['customisationcontrols'] = 'Customisation';
$string['customise'] = 'Customise';
$string['customisation'] = 'Customisation';
$string['datafiles'] = 'Run-time data';
$string['datafiles_help'] = 'Any files uploaded here will be added to the ' .
        'working directory when the expanded template program is executed. ' .
        'This allows large data or support files to be conveniently added.';
$string['display'] = 'Display';

$string['editingcoderunner'] = 'Editing a CodeRunner Question';
$string['empty_new_prototype_name'] = 'New question type name cannot be empty';
$string['enable'] = 'Enable';
$string['enablecombinator'] = 'Enable combinator';
$string['enable_sandbox_desc'] = 'Permit use of the specified sandbox for ' .
         'running student submissions';
$string['expected'] = 'Expected output';
$string['expected_help'] = 'The expected output from the test. Seen by the template as {{TEST.expected}}.';
$string['extra'] = 'Extra template data';
$string['extra_help'] = 'A sometimes-useful extra text field for use by the template, accessed as {{TEST.extra}}';
$string['failedhidden'] = 'Your code failed one or more hidden tests.';
$string['fileheader'] = 'Support files';
$string['filloutoneanswer'] = 'You must enter source code that '
    . 'satisfies the specification. The code you enter will be '
    . 'executed to determine its correctness and a grade awarded '
    . 'accordingly.';
$string['grader'] = 'Grader';
$string['grading'] = 'Grading';
$string['gradingcontrols'] = 'Grading controls';
$string['gradingcontrols_help'] = <<<EO_GC_HELP
The default 'exact match' grader
awards marks only if the output from the run matches the expected value defined
by the testcase. Trailing white space is stripped from all lines before the
equality test is made. The near-equality grader is similar except that it
also collapses multiple spaces and tabs to a single space, deleted all blank
lines and converts both strings to lower case.
The 'regular expression' grader uses the 'expected'
field of the test case as a regular expression and tests the output to see
if a match to the expected result can be found anywhere within the output.
To force matching of the entire output, start and end the regular expression
with '^' and '$' respectively. Regular expression matching uses MULTILINE
and DOT_ALL options. The 'template does grading' option assumes that the output
obtained by running the program generated by the template is actually a
grading result. It MUST be a JSON-encoded record containing
at least a 'fraction' field, which is multiplied by TEST.mark to decide how
many marks the test case is awarded. It should usually also contain a 'got'
field, which is the value displayed in the 'Got' column of the results table.
The other columns of the results table (testcode, stdin, expected) can also
be defined by the custom grader and will be used instead of the values from
the testcase. As an example, if the output of the program is the string
'{"fraction":0.5, "got": "Half the answers were right!"}', half marks would be
given for that particular test case and the 'Got' column would display the
text "Half the answers were right!".
EO_GC_HELP;
$string['hidden'] = 'Hidden';
$string['HIDE'] = 'Hide';
$string['HIDE_IF_FAIL'] = 'Hide if fail';
$string['HIDE_IF_SUCCEED'] = 'Hide if succeed';
$string['hiderestiffail'] = 'Hide rest if fail';

$string['ideone_user'] = 'Ideone server user';
$string['ideone_user_desc'] = 'The login name to use when connecting to the ' .
        'Ideone server (if the ideone sandbox is enabled)';
$string['ideone_pass'] = 'Ideone server password';
$string['ideone_pass_desc'] = 'The password to use when connecting to the ' .
        'Ideone server (if the ideone sandbox is enabled)';
$string['is_prototype'] = 'Use as prototype';

$string['jobe_apikey'] = 'Jobe API-key';
$string['jobe_apikey_desc'] = 'The API key to be included in all REST requests ' .
       'to the Jobe server (if required). Max 40 chars. Leave blank to omit the API Key from requests';
$string['jobe_host'] = 'Jobe server';
$string['jobe_host_desc'] = 'The host name of the Jobe server plus the port ' .
       'number if other than port 80, e.g. jobe.somewhere.edu:4010';

$string['language'] = 'Sandbox language';
$string['languages'] = 'Languages';
$string['languages_help'] = 'The sandbox language is the computer language used'
    . ' to run the submission. '
    . ' Must be known to the chosen sandbox (if a specific one has been'
    . ' selected) or to at least one of the enabled sandboxes (otherwise).'
    . ' This should not usually need altering from the value in the'
    . ' parent template; tweak it at your peril.  Ace-language is the'
    . ' language used by the Ace code editor (if enabled) for the student\'s answer.'
    . ' By default this is the same as the sandbox language; enter a different'
    . ' value here only if the template language is different from the language'
    . ' that the student is expected to write (e.g. if a Python preprocessor is'
    . ' used to validate a student\'s C program prior to running it).';
$string['mark'] = 'Mark';
$string['marking'] = 'Mark allocation';
$string['memorylimit'] = 'MemLimit (MB)';
$string['missingoutput'] = 'You must supply the expected output from '
    . 'this test case.';
$string['morehidden'] = 'Some hidden test cases failed, too.';
$string['noerrorsallowed'] = 'Your code must pass all tests to earn any '
    . 'marks. Try again.';
$string['nonnumericmark'] = 'Non-numeric mark';
$string['negativeorzeromark'] = 'Mark must be greater than zero';
$string['qWrongBehaviour'] = 'Detailed test results unavailable. '
    . 'Perhaps an empty answer, or question not using Adaptive Mode?';
$string['options'] = 'Options';
$string['ordering'] = 'Ordering';
$string['penaltyregime'] = 'Penalty regime';
$string['markinggroup'] = 'Marking';
$string['markinggroup_help'] = 'If \'All-or-nothing\' is checked, all test cases must be satisfied ' .
        'for the submission to earn any marks. Otherwise, the mark is obtained ' .
        'by summing the marks for all the test cases that pass ' .
        'and expressing this as a fraction of the maximum possible mark. ' .
        'The per-test-case marks can be specified only if the all-or-nothing ' .
        'checkbox is unchecked. If using a template grader that awards ' .
        'part marks to test cases, \'All-or-nothing\' should generally be unchecked.' .
        '<p>The penalty regime is a comma-separated list of penalties (each a percent) ' .
        'to apply to successive submissions. These are absolute, not cumulative. As a ' .
        'special case the last penalty can be "..." to mean "extend the previous ' .
        'two penalties as an arithmetic progression up to 100". For example, ' .
        '"0,5,10,30,..." is equivalent to "0,5,10,30,50,70,90,100".' .
        'Leave blank for standard Moodle behaviour. ' .
        'If there are more submissions than defined penalties, the last value is used</p>';
$string['parameterise_template'] = 'Set template params';

$string['pluginname'] = 'CodeRunner';
$string['pluginnameadding'] = 'Adding a CodeRunner question';
$string['pluginnameediting'] = 'Editing a CodeRunner question';
$string['pluginnamesummary'] = 'CodeRunner: runs student-submitted code in a sandbox';
$string['pluginname_help'] = 'Use the "Question type" combo box to select the ' .
        'computer language that will be used to run the student\'s submission. ' .
        'Specify the problem that the student must write code for, then define '.
        'a set of tests to be run on the student\'s submission';
$string['pluginname_link'] = 'question/type/coderunner'; 

$string['prototypecontrols'] = 'Prototyping';
$string['prototypecontrols_help'] = 'If \'Is prototype\' is ' .
        'true, this question becomes a prototype for other questions. ' .
        'After saving, the specified question type name will appear in the ' .
        'dropdown list of question types. New questions based on this type ' .
        'will then by default inherit all the customisation ' .
        'attributes specified for this question. Subsequent ' .
        'changes to this question will then affect all derived questions ' .
        'unless they are themselves customised, which breaks the connection. ' .
        'Prototypal inheritance is single-level only, so this question, when ' .
        'saved as a prototype, loses its connection to its original base type, ' .
        'becoming a new base type in its own right. Be warned that when ' .
        'exporting derived questions you must ensure that this question is ' .
        'included in the export, too, or the derived question will be an ' .
        'orphan when imported into another system. Also, you are responsible ' .
        'for keeping track of which questions you are using as prototypes; ' .
        'it is strongly recommended that you rename the question to something ' .
        'like \'PROTOTYPE_for_my_new_question_type\' to make subsequent ' .
        'maintenance easier.';
$string['prototypeQ'] = 'Is prototype?';
$string['questiontype'] = 'Question type';
$string['questiontype_help'] = <<<QUESTION_TYPE_HELP
        Select the particular type of question.
        
        The combo-box selects one of the built-in types, each of which 
        specifies a particular language and, sometimes, a sandbox in which
        the program will be executed. Each question type has a
        template that defines how the executable program is built from the
        testcase data and the student answer.
            
        The template can be viewed and optionally customised by clicking
        the "Customise" checkbox.
        
        If the template-debugging checkbox is clicked, the program generated
        for each testcase will be displayed in the output.
QUESTION_TYPE_HELP;

$string['questiontype_required'] = 'You must select the type of question';
$string['resultcolumns'] = 'Result columns';
$string['resultcolumns_help'] = 'By default the result table displays '
    . 'the testcode, stdin, expected and got columns, provided the columns '
    . 'are not empty. You can change the default, and/or the column headers '
    . 'by entering a value for the resultcolumns (leave blank for the default '
    . 'behaviour). If supplied, the resultcolumns field must be a JSON-encoded '
    . 'list of column specifiers. Each column specifier is itself a list, '
    . 'typically with just two or three elements. The first element is the '
    . 'column header, the second element is the field from the TestResult '
    . 'object being displayed in the column and the optional third '
    . 'element is an sprintf format string used to display the field. The fields '
    . 'available in the standard TestResult object are: testcode, stdin, expected, ' 
    . 'got, extra, awarded, and mark. testcode, stdin, expected and extra are '
    . 'the fields from the testcase while got is the actual output generated '
    . 'and awarded and mark are the actual awarded mark and the maximum mark '
    . 'for the testcase respsectively. Custom-grader templates may add their '
    . 'own fields, which can also be selected for display. It is also possible '
    . 'to combine multiple fields into a column by adding extra fields to the '
    . 'specifier: these must precede the sprintf format specifier, which then '
    . 'becomes mandatory. For example, to display a Mark Fraction column in the '
    . 'form 0.74/1.00, say, a column format specifier of ["Mark Fraction", "awarded"'
    . ', "mark", "%.2f/%.2f"] could be used. As a further special case, a format '
    . 'of %h means that the test result field should be taken as ready-to-output '
    . 'HTML and should not be subject to further processing; this is useful '
    . 'only with custom-grader templates that generate HTML output, such as '
    . 'SVG graphics.  The default value of resultcolumns is [["Test", "testcode"],'
    . '["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]].';
        
$string['resultcolumnsnotjson'] = 'Result columns field is not a valid JSON string';
$string['resultcolumnsnotlist'] = 'Result columns field must a JSON-encoded list of column specifiers';
$string['resultcolumnspecbad'] = 'Invalid column specifier found: each one must be a list of two or more strings';
$string['run_failed'] = 'Failed to run tests';
$string['sampleanswer'] = 'Sample answer';
$string['sandboxcontrols'] = 'Sandbox';
$string['sandboxcontrols_help'] = 'Select what sandbox you wish the student ' .
        'submissions to run in; choosing DEFAULT will use the highest ' .
        'priority sandbox available for the chosen language (recommended unless ' .
        'the question has special needs). ' .
        'You can also set the maximum CPU time in seconds ' .
        'allowed for each testcase run and the maximum memory a single testcase ' .
        'run can consume (MB). A blank entry uses the sandbox\'s ' .
        'default value (typically 5 secs for the CPU time limit and a ' .
        'language-dependent amount of memory), but the defaults may not be suitable for resource-demanding ' .
        'programs. A value of zero for the maximum memory ' .
        'results in no limit being imposed. The amount of memory specified here ' .
        'is the total amount needed for the run including all libraries, interpreters, ' .
        'VMs etc. The "Parameters" entry is used to pass ' .
        'further sandbox-specific data, such as compile options and API-keys. ' .
        'It should generally be left ' .
        'blank but if non-blank it must be a valid JSON record. In the case of ' .
        'The jobe sandbox, available attributes include disklimit, streamsize, numprocs, ' .
        'compileargs and interpreterargs. For example {"compileargs":["-std=c89"]} ' .
        'for a C question would force C89 compliance and no other C options would ' .
        'be used. See the jobe documentation for details. ' .
        'Some sandboxes (e.g. Ideone) may ' .
        'silently ignore any or all of these settings.';
$string['sandboxparams'] = 'Parameters';
$string['SHOW'] = 'Show';
$string['showcolumns'] = 'Show columns:';
$string['showcolumns_help'] = 'Select which columns of the results table should ' .
        'be displayed to students. Empty columns will be hidden regardless. ' .
        'The defaults are appropriate for most uses.';
$string['showsource'] = 'Template debugging';
$string['stdin'] = 'Standard Input';
$string['stdin_help'] = 'The standard input to the test, seen by the template as {{TEST.stdin}}';
$string['syntax_errors'] = 'Syntax Error(s)';
$string['template'] = 'Template';
$string['template_help'] = <<<EO_TEMPLATE_HELP
The template defines the program that is to be run for each test case, depending
on the student answer and the particular test case. The template is processed
by the Twig template engine (see twig.sensiolabs.org)
in a context in which STUDENT_ANSWER is the student's
response and TEST.testcode is the code for the current testcase. These values
(and other testcase values like TEST.expected, TEST.stdin, TEST.mark)
can be inserted into the template by enclosing them in double braces, e.g.
{{TEST.testcode}}. For use within literal strings, an appropriate escape
function should be applied, e.g. {{STUDENT_ANSWER | e('py')}} is the student
answer escaped in a manner suitable for use within Python triple-double-quoted
strings. Other escape functions are e('c'), e('java'), e('matlab'). The
program that is output by Twig is then compiled and executed
with the language of the selected built-in type and with stdin set
to TEST.stdin. Output from that program is then passed to the grader,
unless the 'Template is also a grader' option is set. See the help under
'Grading controls' for more on that. Note that if a customised template is used
there will be a compile-and-execute cycle for every test case, whereas most
built-in question types attempt to combine test cases into a single run.
Hence custom types may be a significantly slower, particularly if the question
has many test cases.
If the template-debugging checkbox is clicked, the program generated
for each testcase will be displayed in the output.
EO_TEMPLATE_HELP;
$string['templateparams'] = 'Template params';
$string['templateparams_help'] = <<<EO_TEMPLATE_PARAMS_HELP
The template parameters field lets you pass string parameters to a question's
template(s). If non-blank, this must be a JSON-format record. The fields of
the record can then be used within the template, where they appear as
QUESTION.parameters.&lt;&lt;param&gt;&gt;. For example, if template params
is
        
        {"age": 23}
        
the value 23 would be substituted into the template in place of the
template variable {{ QUESTION.parameters.age }}.
EO_TEMPLATE_PARAMS_HELP;
$string['testcase'] = 'Test case {$a}';
$string['testcasecontrols'] = 'Row properties:';
$string['testcasecontrols_help'] = <<<EO_TESTCASECTRLS_HELP
If "Use as example" is checked, this test will be automatically included in the
question's "For example:" results table.<br>
The "Display" combobox determines when this testcase is shown to the student
in the results table.<br>
If "Hide rest if fail" is checked and this test fails, all subsequent tests will
be hidden from the student, regardless of the setting of the "Display" combobox.<br>
"Mark" sets the value of this test case; meaningful only if this is not an
"All-or-nothing" question.
"Ordering" can be used to change the order of testcases when the question is
saved: testcases are ordered by this field.
EO_TESTCASECTRLS_HELP;
$string['testcases'] = 'Test cases';
$string['testcode'] = 'Test code';
$string['testsplitterre'] = 'Test splitter (regex)';
$string['testcode_help'] = 'The code for the test, seen by the template as {{TEST.testcode}}';
$string['type_header'] = 'CodeRunner question type';
$string['typename'] = 'Question type';
$string['typerequired'] = 'Please select the type of question (language, format, etc)';
$string['useasexample'] = 'Use as example';
$string['useace'] = 'Use ace';
$string['xmlcoderunnerformaterror'] = 'XML format error in coderunner question';
