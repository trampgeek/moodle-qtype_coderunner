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
$string['answerrequired'] = 'Please provide a non-empty answer';
$string['atleastonetest'] = 'You must provide at least one test case '
    . 'for this question.';
$string['display'] = 'Display';
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
$string['missingoutput'] = 'You must supply the expected output from '
    . 'this test case.';
$string['morehidden'] = 'Some other hidden test cases failed, too.';
$string['noerrorsallowed'] = 'Your code must pass all tests to earn any '
    . 'marks. Try again.';
$string['qWrongBehaviour'] = 'Detailed test results unavailable. '
    . 'Perhaps question not using Adaptive Mode?';
$string['output'] = 'Output';
$string['pluginname'] = 'CodeRunner';
$string['pluginnameadding'] = 'Adding a CodeRunning question';
$string['pluginnamesummary'] = 'CodeRunner: runs student-submitted code in a sandbox';
$string['pluginname_help'] = 'Use the "Question type" combo box to select the ' .
        'computer language that will be used to run the student\'s submission. ' .
        'Specify the problem that the student must write code for, then define '.
        'a set of tests to be run on the student\'s submission';
$string['pluginnameediting'] = 'Editing a CodeRunner question';
$string['coderunner'] = 'Program Code';
$string['coderunner_type'] = 'Question type';
$string['coderunner_type_help'] = "Select the programming question type

Predefined types include

* c_function: where the student writes one or more C functions and the tests call those functions
* c_program:  where the student writes the entire C program
* python3_basic: where the student's Python3 code is executed first, followed by the author's test code
";
$string['coderunnersummary'] = 'Answer is program code that is executed '
    . 'in the context of a set of test cases to determine its correctness.';
$string['coderunner_help'] = 'In response to a question, which is a '
    . 'specification for a program fragment, function or whole program, '
    . 'the respondent enters source code in a specified computer '
    . 'language that satisfies the specification.';
$string['coderunner_link'] = 'question/type/coderunner';
$string['SHOW'] = 'Show';
$string['stdin'] = 'Standard Input (only for programs that explicitly '
    . 'read stdin)';
$string['testcase'] = 'Test case.';
$string['testcode'] = 'Test code';
$string['type_header'] = 'Select language etc';
$string['typerequired'] = 'Please select the type of question (language, format, etc)';
$string['useasexample'] = 'Use as example';
$string['xmlcoderunnerformaterror'] = 'XML format error in coderunner question';
