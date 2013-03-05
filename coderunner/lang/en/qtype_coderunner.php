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
$string['all_or_nothing'] = 'All-or-nothing question';
$string['all_or_nothing_help'] = 'If checked, all test cases must be satisfied ' .
        'for the submission to earn any marks';
$string['answerrequired'] = 'Please provide a non-empty answer';
$string['atleastonetest'] = 'You must provide at least one test case '
    . 'for this question.';
$string['coderunner'] = 'Program Code';
$string['customise'] = 'Customise type';
$string['customise_help'] = 'Customise the template used to construct the program' .
        'to execute for each test case. The template is processed by the Twig ' .
        'template engine in a context in which STUDENT_ANSWER is the student\' ' .
        'response and TEST.testcode is the code for the current testcase. The ' .
        'output from the template processing is then compiled and executed ' .
        'with the current language and with stdin set to the input data for the ' .
        'current testcase. Note that if a customised template is set there will ' .
        'be a compile-and-execute cycle for every test case, whereas most ' .
        'built-in question types attempt to combine test cases into a single run. ' .
        'Hence custom types may be a bit slower. ';
$string['language'] = 'Language';
$string['coderunner_type_required'] = 'You must select a language and question type';
$string['coderunner_type'] = "Question type";
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

These various types are not applicable to Python, where the student's code is always run first,
followed by the test code.

It is also possible to customise the question type: click the help for the
'customise' button for more information.
";
$string['coderunnersummary'] = 'Answer is program code that is executed '
    . 'in the context of a set of test cases to determine its correctness.';
$string['coderunner_help'] = 'In response to a question, which is a '
    . 'specification for a program fragment, function or whole program, '
    . 'the respondent enters source code in a specified computer '
    . 'language that satisfies the specification.';
$string['coderunner_link'] = 'question/type/coderunner';$string['display'] = 'Display';
$string['editingcoderunner'] = 'Editing a CodeRunner Question';
$string['failedhidden'] = 'Your code failed one or more hidden tests.';
$string['filloutoneanswer'] = 'You must enter source code that '
    . 'satisfies the specification. The code you enter will be '
    . 'executed to determine its correctness and a grade awarded '
    . 'accordingly.';
$string['hidden'] = 'Hidden';
$string['HIDE'] = 'Hide';
$string['HIDE_IF_FAIL'] = 'Hide if fail';
$string['HIDE_IF_SUCCEED'] = 'Hide if succeed';
$string['hiderestiffail'] = 'Hide rest if fail';
$string['mark'] = 'Mark';
$string['missingoutput'] = 'You must supply the expected output from '
    . 'this test case.';
$string['morehidden'] = 'Some other hidden test cases failed, too.';
$string['noerrorsallowed'] = 'Your code must pass all tests to earn any '
    . 'marks. Try again.';
$string['nonnumericmark'] = 'Non-numeric mark';
$string['negativeorzeromark'] = 'Mark must be greater than zero';
$string['qWrongBehaviour'] = 'Detailed test results unavailable. '
    . 'Perhaps question not using Adaptive Mode?';
$string['options'] = 'Options';
$string['output'] = 'Output';
$string['pluginname'] = 'CodeRunner';
$string['pluginnameadding'] = 'Adding a CodeRunner question';
$string['pluginnamesummary'] = 'CodeRunner: runs student-submitted code in a sandbox';
$string['pluginname_help'] = 'Use the "Question type" combo box to select the ' .
        'computer language that will be used to run the student\'s submission. ' .
        'Specify the problem that the student must write code for, then define '.
        'a set of tests to be run on the student\'s submission';
$string['pluginnameediting'] = 'Editing a CodeRunner question';
$string['questiontype_required'] = 'You must select the type of question';
$string['SHOW'] = 'Show';
$string['show_source'] = 'Template debugging';
$string['show_source_help'] = 'If checked, the full source code of the program ' .
        'that\'s run is displayed in the result page, for template debugging.';
$string['stdin'] = 'Standard Input (only for programs that explicitly '
    . 'read stdin)';
$string['testcase'] = 'Test case.';
$string['testcode'] = 'Test code';
$string['template'] = 'Template';
$string['type_header'] = 'Select language etc';
$string['typerequired'] = 'Please select the type of question (language, format, etc)';
$string['useasexample'] = 'Use as example';
$string['xmlcoderunnerformaterror'] = 'XML format error in coderunner question';
