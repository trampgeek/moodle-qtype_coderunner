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
$string['allornothing'] = 'Test code must be provided either for all '
    . 'testcases or for none.';
$string['all_or_nothing'] = 'All-or-nothing grading';
$string['all_or_nothing_help'] = 'If \'All-or-nothing\' is checked, all test cases must be satisfied ' .
        'for the submission to earn any marks. Otherwise, the mark is obtained ' .
        'by summing the marks for all the test cases that pass ' .
        'and expressing this as a fraction of the maximum possible mark. ' .
        'The per-test-case marks can be specified only if the all-or-nothing ' .
        'checkbox is unchecked.';
$string['answerrequired'] = 'Please provide a non-empty answer';
$string['atleastonetest'] = 'You must provide at least one test case '
    . 'for this question.';
$string['badcputime'] = 'CPU time limit must be left blank or must be an integer greater than zero';
$string['badmemlimit'] = 'Memory limit must either be left blank or must be a non-negative integer';
$string['columncontrols'] = 'Result table';
$string['coderunner'] = 'Program Code';
$string['coderunner_type_required'] = 'You must select a language and question type';
$string['coderunner_type'] = "Question type:";
$string['coderunner_type_help'] = "Select the programming language and question type.

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
";
$string['coderunnersummary'] = 'Answer is program code that is executed '
    . 'in the context of a set of test cases to determine its correctness.';
$string['coderunner_help'] = 'In response to a question, which is a '
    . 'specification for a program fragment, function or whole program, '
    . 'the respondent enters source code in a specified computer '
    . 'language that satisfies the specification.';
$string['coderunner_link'] = 'question/type/coderunner';
$string['columncontrols_help'] = 'The checkboxes select which columns of the ' .
        'results table should be displayed to the student after submission';
$string['cputime'] = 'CPU time limit (secs)';
$string['customisationcontrols'] = 'Customisation';
$string['customise'] = 'Customise';
$string['customisation'] = 'Customisation';
$string['datafiles'] = 'Run-time data';
$string['datafiles_help'] = 'Any files uploaded here will be added to the ' .
        'working directory when the expanded template program is executed. ' .
        'This allows large data files to be conveniently added, but be warned ' .
        'that at present the data files are not included with the question ' .
        'if it is exported. They are however backed up and restored as part ' .
        'of the usual Moodle course backup-and-restore mechanism.';
$string['display'] = 'Display';

$string['editingcoderunner'] = 'Editing a CodeRunner Question';
$string['expected'] = 'Expected output';
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
equality test is made. The 'regular expression' grader uses the 'expected'
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
$string['language'] = 'Language';

$string['mark'] = 'Mark';
$string['marking'] = 'Mark allocation';
$string['memorylimit'] = 'Memory limit (MB)';
$string['missingoutput'] = 'You must supply the expected output from '
    . 'this test case.';
$string['morehidden'] = 'Some other hidden test cases failed, too.';
$string['noerrorsallowed'] = 'Your code must pass all tests to earn any '
    . 'marks. Try again.';
$string['nonnumericmark'] = 'Non-numeric mark';
$string['negativeorzeromark'] = 'Mark must be greater than zero';
$string['qWrongBehaviour'] = 'Detailed test results unavailable. '
    . 'Perhaps an empty answer, or question not using Adaptive Mode?';
$string['options'] = 'Options';
$string['penalty_regime'] = 'Penalty regime';
$string['penalty_regime_help'] = 'A comma-separated list of penalties (each a percent)
    to apply to successive submissions. Leave blank for standard Moodle behaviour.
    If there are more submissions than defined penalties, the last value is used';
$string['pluginname'] = 'CodeRunner';
$string['pluginnameadding'] = 'Adding a CodeRunner question';
$string['pluginnamesummary'] = 'CodeRunner: runs student-submitted code in a sandbox';
$string['pluginname_help'] = 'Use the "Question type" combo box to select the ' .
        'computer language that will be used to run the student\'s submission. ' .
        'Specify the problem that the student must write code for, then define '.
        'a set of tests to be run on the student\'s submission';
$string['pluginnameediting'] = 'Editing a CodeRunner question';
$string['questiontype'] = 'Question type';
$string['questiontype_help'] = 'Select the particular type of question. ' .
        'The combo-box selects one of the built-in types, each of which ' .
        'specifies a particular language and, sometimes, a sandbox in which ' .
        'the program will be executed. Each question type has a ' .
        'template that defines how the executable program is built from the ' .
        'testcase data and the student answer. The template, and other
            parameters of the question type, can be customised ' .
        'by clicking the "Customise" checkbox.';

$string['questiontype_required'] = 'You must select the type of question';
$string['row_properties'] = 'Row properties:';
$string['sandboxcontrols'] = 'Sandbox params';
$string['sandboxcontrols_help'] = 'You can set the maximum CPU time in seconds ' .
        'allowed for each testcase run and the maximum memory a single testcase ' .
        'run can consume (MB) here. A blank entry uses the sandbox\'s ' .
        'default value, but this may not be suitable for resource-demanding ' .
        'languages like Java and Matlab). A value of zero for the maximum memory ' .
        'results in no limit being imposed.';
$string['SHOW'] = 'Show';


$string['show_columns'] = 'Show columns:';
$string['show_columns_help'] = 'Select which columns of the results table should ' .
        'be displayed to students. Empty columns will be hidden regardless. ' .
        'The defaults are appropriate for most uses.';
$string['show_expected'] = 'expected';
$string['show_mark'] = 'mark';
$string['show_source'] = 'Template debugging';
$string['show_stdin'] = 'stdin';
$string['show_test'] = 'test';
$string['show_output'] = 'got';

$string['stdin'] = 'Standard Input';
$string['testcase'] = 'Test case {$a}';
$string['testcases'] = 'Test cases';
$string['testcode'] = 'Test code';
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
$string['type_header'] = 'Coderunner question type';
$string['typerequired'] = 'Please select the type of question (language, format, etc)';
$string['useasexample'] = 'Use as example';
$string['xmlcoderunnerformaterror'] = 'XML format error in coderunner question';
