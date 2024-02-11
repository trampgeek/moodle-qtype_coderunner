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
 * Strings for component 'qtype_coderunner', language 'en', branch 'MOODLE_40_STABLE'
 *
 * @package   qtype_coderunner
 * @copyright Richard Lobb 2012-2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aborted'] = 'Testing was aborted due to error.';
$string['ace_gapfillerui_ui_source_descr'] = '"globalextra" to take the code to display from the globalextra field or "test0" to take it from the testcode field of the first test';
$string['ace_ui_notready'] = 'Ace editor not ready. Perhaps reload page?';
$string['aceui_auto_switch_light_dark_descr'] = 'Allow a browser or OS preference for dark themes to override a preset Ace light theme.';
$string['aceui_live_autocompletion_descr'] = 'Enable the Ace editor\'s live autocompletion mode.';
$string['aceui_font_size_descr'] = 'Ace editor font size.';
$string['aceui_import_from_scratchpad_descr'] = 'True to allow the Ace editor to receive the JSON-format answer used by the scratchpad UI and extract the answer code from it. Facilitates switching UIs. Leave true unless you want Ace to edit JSON objects with an "answer_code" key.';
$string['aceui_theme_descr'] = 'Ace editor theme. Default is the light theme "textmate". An alternative dark theme is "tomorrow_night". Light theme be overridden by user preferences - see auto_switch_dark.';
$string['addingcoderunner'] = 'Adding a new CodeRunner Question';
$string['ajax_error'] = '*** AJAX ERROR. DON\'T SAVE THIS! ***';
$string['allok'] = 'Passed all tests! ';
$string['allornone'] = 'Test code must be provided either for all testcases or for none.';
$string['allornothing'] = 'All-or-nothing grading';
$string['allornothing_help'] = 'If \'All-or-nothing\' is checked, all test cases must be satisfied for the submission to earn any marks. Otherwise, the mark is obtained by summing the marks for all the test cases that pass and expressing this as a fraction of the maximum possible mark.

The per-test-case marks can be specified only if the all-or-nothing checkbox is unchecked.

If using a template grader that awards part marks to test cases, \'All-or-nothing\' should generally be unchecked.';
$string['allowattachments'] = 'Allow attachments';
$string['allowattachments_help'] = 'Whether to allow students to add attachments to their submissions and, if so, how many. Attachments are copied into the runtime working directory and a comma-separated list of the names of the attachments is provided  to the template in the Twig variable {{ ATTACHMENTS }}. Warning: allowing attachments could have performance or disk-space implications for the Moodle and Jobe servers with large classes and/or large attachments. The Moodle server, and Jobe servers prior to February 2019, store all attachments indefinitely.';
$string['allowedfilenames'] = 'Allowed file names';
$string['allowedfilenamesregex'] = 'Allowed file names (regular expression)';
$string['allowedfilenames_help'] = 'All uploaded file names must match the given PHP (Perl) regular expression, if non empty. For example, use \'.+\\\\.cpp\' to allow any C++ file or \'(?!Prog)\\\\.java\' to allow any Java file except \'Prog.java\'. Additionally, filenames must contain only alphanumeric characters plus underscore, hyphen and period, must not start with double-underscore (\'\_\_\') and must not conflict with any of the support file names. The Description is a text message shown to the student to explain what file(s) are expected. Leave empty to display the regular expression itself. Leave both empty to bypass the regular expression checking.';
$string['allowmultiplestdins'] = 'Allow multiple stdins';
$string['answer'] = 'Sample answer';
$string['answerprompt'] = 'Answer:';
$string['answer_help'] = 'A sample answer can be entered here and used for checking by the question author and optionally shown to students during review. It is also used by the bulk tester script. The correctness of a non-empty answer is checked when saving unless \'Validate on save\' is unchecked';
$string['answerunchanged'] = 'You must complete or edit the preloaded answer.';
$string['answerrequired'] = 'Please provide a non-empty answer';
$string['answertooshort'] = 'Answer too short. Must be at least {$a} characters.';
$string['atleastonetest'] = 'You must provide at least one test case for this question.';
$string['ace-language'] = 'Ace language';
$string['advanced_customisation'] = 'Advanced customisation';
$string['answer'] = 'Answer';
$string['answerbox_group'] = 'Answer box';
$string['answerboxlines'] = 'Rows';
$string['answerbox_group_help'] = 'Set the number of rows to allocate for the answer box. This sets the minimum height of the User Interface element (e.g. Ace) that controls the answer box. The width is set to fit the window. If the answer overflows the box vertically or horizontally, scrollbars will appear.';
$string['answerpreload'] = 'Answer box preload';
$string['answerpreload_help'] = 'Text supplied here will be preloaded into the student\'s answer box.';
$string['asolutionis'] = 'Show/hide question author\'s solution:';
$string['attachmentoptions'] = 'Attachment options';

$string['attachmentsoptional'] = 'Attachments are optional';
$string['attachmentsrequired'] = 'Require attachments';
$string['attachmentsrequired_help'] = 'This option specifies the minimum number of attachments required for a response to be graded.';

$string['autotagbycategorytitle'] = 'CodeRunner autotag by category';
$string['autotagbycategoryindextitle'] = 'CodeRunner question autotagger';

$string['badacelangstring'] = 'Bad Ace-language string';
$string['badcputime'] = 'CPU time limit must be left blank or must be an integer greater than zero';
$string['bad_dotdotdot'] = 'Misuse of \'...\'. Must be at end, after two increasing numeric penalties';
$string['bademptyprecheck'] = 'Precheck failed with the following unexpected output.';
$string['bad_empty_splitter'] = 'Test splitter cannot be empty when using a combinator template';
$string['badfilenamesregex'] = 'Invalid regular expression';
$string['badfiles'] = 'Disallowed file name(s): {$a}';
$string['badjsonfunc'] = 'Unknown JSON embedded func ({$a->func})';
$string['badjson'] = 'Bad JSON output from combinator grader. Output was: {$a->output}';
$string['badmemlimit'] = 'Memory limit must either be left blank or must be a non-negative integer';
$string['bad_new_prototype_name'] = 'Illegal name for new prototype: already in use';
$string['badpenalties'] = 'Penalty regime must be a comma separated list of numbers in the range [0, 100]';
$string['badquestion'] = 'Error in question';
$string['badrandomintarg'] = 'Bad argument to JSON @randomint function';
$string['badrandompickarg'] = 'Bad argument to JSON @randompic function';
$string['badsandboxparams'] = '\'Other\' field (sandbox params) must be either blank or a valid JSON record';
$string['badtemplateparams'] = 'Template parameters must evaluate to blank or a valid JSON record. Got: <pre class="templateparamserror">{$a}</pre>';
$string['baduiparams'] = 'UI parameters must be blank or a valid JSON record.';
$string['brokencombinator'] = 'Expected {$a->numtests} test results, got {$a->numresults}. Perhaps excessive output or error in question?';
$string['brokentemplategrader'] = 'Bad output from grader: {$a->output}. Your program execution may have aborted (e.g. a timeout or memory limit exceeded).';
$string['bulkquestiontester'] = 'The <a href="{$a->link}">bulk tester script</a> tests that the sample answers for all questions in the current context are marked right. Useful only once some questions with sample answers have been added; the initial install has none.';
$string['bulktestallincontext'] = 'Test all';
$string['bulktestcontinuefromhere'] = 'Run again or resume, starting from here';
$string['bulktestindextitle'] = 'CodeRunner bulk testing';
$string['bulktestrun'] = 'Run all the question tests for all the questions in the system (slow, admin only)';
$string['bulktesttitle'] = 'Testing questions in {$a}';

$string['cannotrunprototype'] = 'This is a prototype and cannot be run. If you wish to use this prototype, create a new question and set this question type.';
$string['coderunnercategories'] = 'Categories with CodeRunner questions';
$string['coderunnercontexts'] = 'Contexts with CodeRunner questions';
$string['coderunner'] = 'Program Code';

$string['coderunner_install_testsuite_title'] = 'A test suite for CodeRunner sample answers';
$string['coderunner_install_testsuite_title_desc'] = 'The <a href="{$a->link}">sample-answer-test script</a> verifies that the questions with sample answers are performing correctly.';
$string['coderunner_install_testsuite_intro'] = 'This page allows you to test that the CodeRunner questions with sample answers are functioning correctly.';
$string['coderunner_install_testsuite_failures'] = 'Tests that failed';
$string['coderunner_install_testsuite_noanswer'] = 'Questions without sample answers';

$string['coderunner:sandboxwsaccess'] = 'Allow access to the Jobe sandbox via web services';
$string['coderunner:viewhiddentestcases'] = 'See hidden testcases when reviewing questions';
$string['coderunner_help'] = 'In response to a question, which is a specification for a program fragment, function or whole program, the respondent enters source code in a specified computer language that satisfies the specification.';
$string['coderunner_link'] = 'question/type/coderunner';
$string['coderunner_question_type'] = 'CodeRunner question type: ';
$string['coderunnersettings'] = 'CodeRunner settings';
$string['coderunnersummary'] = 'Answer is program code that is executed in the context of a set of test cases to determine its correctness.';
$string['coderunnertype'] = 'Question type';
$string['coderunnertype_help'] = 'Select the programming language and question type. Once a type has been selected, details can be seen in the Question type details panel below.';
$string['coderunnerwssettings'] = 'Sandbox web-service settings';
$string['columncontrols'] = 'Result table';
$string['columncontrols_help'] = 'The checkboxes select which columns of the results table should be displayed to the student after submission';

$string['confirm_proceed'] = 'If you save this question with \'Customise\' unchecked, any customisations made will be lost. Proceed?';
$string['confirmreset'] = 'Discard all your work on this question and reset answer box to original preloaded value?';
$string['corruptuiparams'] = 'The UI parameters for this question or its prototype are broken. Proceed with caution.';
$string['cputime'] = 'TimeLimit (secs)';
$string['customisationcontrols'] = 'Customisation';
$string['customise'] = 'Customise';
$string['customisation'] = 'Customisation';

$string['datafiles'] = 'Support files';
$string['datafiles_help'] = 'Any files uploaded here will be added to the working directory when the expanded template program is executed. This allows large data or support files to be conveniently added.';
$string['default_penalty_regime'] = 'Default penalty regime';
$string['default_penalty_regime_desc'] = 'The default penalty regime to apply to new questions, consisting of a comma separated list of penalty percentages, optionally ending in ", ..." to signify an on-going arithmetic progression.';

$string['display'] = 'Display';
$string['downloadquizattempts'] = 'Download quiz attempts';
$string['downloadquizattemptshelp'] = 'Click the appropriate course and/or download button
        for the course and quiz you wish to download. Numbers in parentheses
        after courses are the number of quizzes in the course with at least
        one submission. The numbers in parentheses after the quiz name
        are the numbers of submissions.';
$string['duplicateprototype'] = 'This question was defined to be of type \'{$a->crtype}\' but the prototype is non-unique in the following questions: {$a->outputstring} Please remove all but one instance, or select another question type.';
$string['editingcoderunner'] = 'Editing a CodeRunner Question';
$string['empty_new_prototype_name'] = 'New question type name cannot be empty';
$string['emptypenaltyregime'] = 'Penalty regime must be defined (since version 3.1)';
$string['emptysandboxlanguage'] = 'Sandbox language cannot be empty when creating a prototype.';
$string['enable'] = 'Enable';
$string['enablecombinator'] = 'Enable combinator';
$string['enable_diff_check'] = 'Enable \'Show differences\' button';
$string['enable_diff_check_desc'] = 'Present students with a \'Show differences\' button if their answer is wrong and an exact-match validator is being used';
$string['enable_sandbox_desc'] = 'Permit use of the specified sandbox for running student submissions';
$string['enter_to_submit'] = 'Enter to submit...';
$string['equalitygrader'] = 'Exact match';
$string['error_loading_prototype'] = 'Error loading prototype. Network problems or server down, perhaps?';
$string['error_loading_ui_descr'] = 'Error loading UI description. Network problems or server down, perhaps?';
$string['erroroninit'] = '**** ERROR WHEN INITIALISING QUESTION ****<br>{$a->error}<br>';
$string['errorstring-ok'] = 'OK';
$string['errorstring-autherror'] = 'Unauthorised to use sandbox';
$string['errorstring-blocked-url'] = 'The URL is blocked. Check the Jobe URL and Moodle\'s HTTP security settings.';
$string['errorstring-duplicate-name'] = 'Rename class name; this name conflicts with support files for this question.';
$string['errorstring-jobe400'] = 'Error from Jobe sandbox server: ';
$string['errorstring-jobe-failed'] = 'Jobe server request failed. ';
$string['errorstring-overload'] = 'Job could not be run due to server overload. Perhaps try again shortly?';
$string['errorstring-pastenotfound'] = 'Requesting status of non-existent job';
$string['errorstring-wronglangid'] = 'Non-existent language requested';
$string['errorstring-accessdenied'] = 'Access to sandbox denied';
$string['errorstring-submissionlimitexceeded'] = 'Sandbox submission limit reached';
$string['errorstring-submissionfailed'] = 'Submission to sandbox failed';
$string['errorstring-unknown'] = 'Unexpected error while executing your code. The sandbox server may be down or overloaded. Perhaps try again shortly?';

// Webservice errors for output display area.
$string['error_access_denied'] = 'Sandbox server access denied';
$string['error_excessive_output'] = 'Excessive output';
$string['error_json_params'] = 'Params set are not in correct JSON format';
$string['error_jobe_unknown'] = 'Unknown error from Jobe server';
$string['error_memory_limit'] = 'Memory limit exceeded';
$string['error_sandbox_server_overload'] = 'Jobe server overload';
$string['error_submission_limit_reached'] = 'Jobe sandbox submission limit reached';
$string['error_timeout'] = 'Time limit exceeded';
$string['error_unknown_language'] = 'Unknown language requested';
$string['error_unknown_runtime'] = 'Unknown runtime error';

$string['event_sandboxwebserviceexec'] = 'CR sandbox exec';
$string['event_sandboxwebserviceexec_desc'] = 'A job was executed via the CodeRunner sandbox web service.';

$string['exit_fullscreen'] = 'Exit full screen';
$string['expand'] = 'Expand';
$string['expandtitle'] = 'Show question categories';
$string['expected'] = 'Expected output';
$string['expectedcolhdr'] = 'Expected';
$string['expected_help'] = 'The expected output from the test. Seen by the template as {{TEST.expected}}.';
$string['exportthisquestion'] = 'Export this question';
$string['exportthisquestion_help'] = 'This will create a Moodle XML export file containing just this one question. One example of when this is useful if you think this question demonstrates a bug in CodeRunner that you would like to report to the developers.';
$string['extra'] = 'Extra template data';
$string['extra_help'] = 'A sometimes-useful extra text field for use by the template, accessed as {{TEST.extra}}';
$string['extractcodefromjson'] = "Ace/Scratchpad compliant";

$string['fail'] = 'Fail';
$string['fails'] = 'failures';
$string['failedhidden'] = 'Your code failed one or more hidden tests.';
$string['failedntests'] = 'Failed {$a->numerrors} test(s)';
$string['failedtesting'] = 'Failed testing.';
$string['feedback'] = 'Feedback';
$string['feedback_quiz'] = 'Set by quiz';
$string['feedback_show'] = 'Force show';
$string['feedback_hide'] = 'Force hide';
$string['feedback_help'] = 'Choose \'Set by quiz\' to allow the quiz\'s review options (specifically the
\'Specific feedback\' setting) to control display of the result table, \'Force show\' to show the result table regardless and \'Force hide\' to hide it regardless';
$string['fileheader'] = 'Support files';
$string['filenamesexplain'] = 'Description';
$string['filenamesregex'] = 'Regular expression';
$string['filloutoneanswer'] = 'You must enter source code that satisfies the specification. The code you enter will be executed to determine its correctness and a grade awarded accordingly.';
$string['firstfailure'] = 'First failing test case: {$a}';
$string['forexample'] = 'For example';

$string['fullscreen'] = 'Full screen';
$string['gapfillerui_delimiters_descr'] = 'A 2-element array of the strings used to open and close the gap description';
$string['gapfillerui_ui_source_descr'] = '"globalextra" to take the HTML to display from the globalextra field or "test0" to take it from the testcode field of the first test';
$string['gapfillerui_sync_interval_secs_descr'] = 'The time interval in seconds between calls to sync the UI contents back to the question answer. 0 for no such auto-syncing.';

$string['giveup'] = 'Stop button';
$string['giveup_aftermaxmarks'] = 'Available once mark cannot be improved';
$string['giveup_always'] = 'Always available';
$string['giveup_help'] = 'If this option is enabled, students will see a button to stop interacting with the question, and instead display the general feedback.

The \'Stop and read final feedback\' can be shown from the start, or only once the student can no longer improve their mark, due to the penalty regime.';
$string['giveup_never'] = 'Never available';

$string['globalextra'] = 'Global extra';
$string['globalextra_help'] = 'A field of text for general-purpose use by template authors, like the extra field of each test case, but global to all tests. Available to the template author as {{ QUESTION.globalextra }}.';
$string['graphhelp'] = '- Double click at a blank space to create a new node/state.
- Double click an existing node to "mark" it e.g. as an accept state for Finite State Machines
  (FSMs). Double click again to unmark it.
- Click and drag to move a node.
- Alt click (or Ctrl Alt click) and drag to move a (sub)graph.
- Shift click inside one node and drag to another to create a link.
- Shift click on a blank space, drag to a node to create a start link (FSMs only).
- Click and drag a link to alter its curve.
- Click on a link/node to edit its text.
- Click in link text and drag to move it.
- Typing _ followed by a digit makes that digit a subscript.
- Typing \\epsilon creates an epsilon character (and similarly for \\alpha, \\beta etc).
- Click on a link/node then press the Delete key to remove it (or function-delete on a Mac).
- Ctrl+z to undo and Ctrl+y or Ctrl+Shift+z to redo.';
$string['goodemptyprecheck'] = 'Passed';
$string['gotcolhdr'] = 'Got';
$string['grader'] = 'Grader';
$string['grading'] = 'Grading';
$string['gradingcontrols'] = 'Grading controls';
$string['gradingcontrols_help'] = 'The default \'exact match\' grader
awards marks only if the output from the run exactly matches the expected value defined
by the testcase. Trailing white space is stripped from all lines, and any trailing
blank lines are deleted, before the
equality test is made.

The near-equality grader is similar except that it
also collapses multiple spaces and tabs to a single space, deletes all blank
lines and converts the strings to lower case.

The \'regular expression\' grader uses the \'expected\'
field of the test case as a regular expression (without PERL-type delimiters)
and tests the output to see
if a match to the expected result can be found anywhere within the output. For
example, an expected value of \'ab.*z\' would match any output that contains the
the characters \'ab\' anywhere in the output and a \'z\' character somewhere later.
To force matching of the entire output, start and end the regular expression
with \'\A\' and \'\Z\' respectively. Regular expression matching uses MULTILINE
and DOTALL options.

The \'template grader\' option assumes that the output
from the program is actually a
grading result, i.e. that the template tests *and grades* the student answer.
The only output from such a template program must be a JSON-encoded record.

If the template is a per-test template (i.e., not a combinator), the JSON string must describe a row of the
results table and should contain at least a \'fraction\' field, which is multiplied by TEST.mark to decide how
many marks the test case is awarded. It should usually also contain a \'got\'
field, which is the value displayed in the \'Got\' column of the results table.
The other columns of the results table (testcode, stdin, expected) can also
be defined by the template grading program and will be used instead of the values from
the testcase. As an example, if the output of the program is the string
<code>{"fraction":0.5, "got": "Half the answers were right!"}</code>, half marks would be
given for that particular test case and the \'Got\' column would display the
text "Half the answers were right!". Other columns can be added to the result
table by adding extra attributes to the JSON record and also to the question\'s
Result Columns field.

If the template is a combinator, the JSON string output by the template grader
should again contain a \'fraction\' field, this time for the total mark,
and may contain zero or more of \'prologuehtml\', \'testresults\',
\'epiloguehtml\', \'columnformats\', \'showoutputonly\', \'showdifferences\'
and \'graderstate\'.
The \'prologuehtml\' and \'epiloguehtml\' fields are html
that is displayed respectively before and after the (optional) result table. The
\'testresults\' field, if given, is a list of lists used to display some sort
of result table. The first row is the column-header row and all other rows
define the table body. Two special column header values exist: \'iscorrect\'
and \'ishidden\'. The \'iscorrect\' column(s) are used to display crosses or
ticks for 0 and 1 respectively. The \'ishidden\' column isn\'t
actually displayed but 0 or 1 values in the column can be used to turn on and
off row visibility. Students do not see hidden rows but markers and other
staff do. If a \'testresults\' table is supplied an optional
\'columnformats\' field can also be supplied. This should be a list
of strings, one per column excluding the \'iscorrect\' and the \'ishidden\'
columns. The strings specify the format to be used to display the cell values;
currently the only supported formats are \'%s\' for a normal string display
(which is sanitised and wrapped in a \'pre\' element) and \'%h\' for an html
value that should not be further processed before display.
The \'showdifferences\' field turns on display of a \'Show Differences\'
button after the results table if the awarded mark fraction is not 1.0.
The \'showoutputonly\' field, if true, is used when the question is to be
used only to display the output and perhaps images from a run, with no mark.
The \'graderstate\' variable is a string value that the question author can
use to pass grading information between question attempts. If included
in the grading response to a submission it will be available on the next
submission as the Twig variable \'QUESTION.stepinfo.graderstate\'.
';
$string['graph_ui_invalidserialisation'] = 'GraphUI: invalid serialisation';
$string['graphui_isfsm_descr'] = 'True if the graph represents a Finite State Machine, in which case it can contain an incoming edge from nowhere (the start edge) and can have \'accept\' nodes';
$string['graphui_isdirected_descr'] = 'True if edges are directed';
$string['graphui_noderadius_descr'] = 'The radius of a node in pixels';
$string['graphui_fontsize_descr'] = 'The font size in points used for node and edge labels.';
$string['graphui_helpmenutext_descr'] = 'Text which if non-empty replaces the standard help menu text defined in the CodeRunner language strings';
$string['graphui_textoffset_descr'] = 'The offset in pixels of a link label from its link (deprecated - use dragging instead).';
$string['graphui_locknodepositions_descr'] = 'If true, prevents the user from moving nodes. Useful when the answer box is preloaded with a graph that the student has to annotate by changing node or edge labels or by adding/removing edges. Note, though that nodes can still be added and deleted. See locknodeset.';
$string['graphui_locknodeset_descr'] = 'If true, prevents the the user from adding or deleting nodes or toggling node types to/from acceptors.';
$string['graphui_locknodelabels_descr'] = 'If true, prevent the user from editing node labels. This will also prevent any new nodes having non-empty labels';
$string['graphui_lockedgepositions_descr'] = 'If true prevents the user from dragging edges to change their curvature. Possibly useful if the answer box is preloaded with a graph that the student has to annotate by changing node or edge labels or by adding/removing edges. Also ensures that edges added by a student are straight, e.g. to draw a polygon on a set of given points. Note, though that edges can still be added and deleted. See lockedgeset.';
$string['graphui_lockedgeset_descr'] = 'If true prevents the user from adding or deleting edges.';
$string['graphui_lockedgelabels_descr'] = 'True to prevent the user from editing edge labels. This also prevents any new edges from having labels.';
$string['hidden'] = 'Hidden';
$string['hidecheck'] = 'Hide check';
$string['hidedetails'] = 'Hide details';
$string['hidedifferences'] = 'Hide differences';
$string['HIDE'] = 'Hide';
$string['HIDE_IF_FAIL'] = 'Hide if fail';
$string['HIDE_IF_SUCCEED'] = 'Hide if succeed';
$string['hiderestiffail'] = 'Hide rest if fail';
$string['hoisttemplateparams'] = 'Hoist template parameters';
$string['howtogetmore'] = 'For more detailed information, save the question with \'Validate on save\' unchecked and test manually';
$string['htmlui_html_src_descr'] = "Sets the source for the HTML code. Must be either 'globalextra' or 'prototypeextra'.";
$string['htmlui_sync_interval_secs_descr'] = 'The time interval in seconds between calls to sync the UI contents back to the question answer. 0 for no such auto-syncing.';
$string['htmlui_enable_in_editor_descr'] = 'If true, use the UI to display the sample answer and answer preload within the question editing form, rather than the serialised version. Set this to false if using Twig in the HTML src field.';
$string['htmluiloadfail'] = 'The HTML UI plugin failed to initialise. Probably the JSON state string is invalid.';

$string['illegaluiparamname'] = 'The following are not valid parameters for the {$a->uiname} UI: ';
$string['iscombinatortemplate'] = 'Is combinator';
$string['ideone_user'] = 'Ideone server user';
$string['ideone_user_desc'] = 'The login name to use when connecting to the deprecated Ideone server (if the ideone sandbox is enabled)';
$string['ideone_pass'] = 'Ideone server password';
$string['ideone_pass_desc'] = 'The password to use when connecting to the deprecated Ideone server (if the ideone sandbox is enabled)';
$string['info_unavailable'] = 'Question type information is not available for customised questions.';
$string['illegalformat'] = 'Illegal format ({$a->format}) in columnformats';
$string['inputcolhdr'] = 'Input';
$string['insufficientattachments'] = 'Not enough attachments, {$a} required.';
$string['is_prototype'] = 'Use as prototype';

$string['jobe_apikey'] = 'Jobe API-key';
$string['jobe_apikey_desc'] = 'The API key to be included in all REST requests to the Jobe server (if required). Max 40 chars. Leave blank to omit the API Key from requests';
$string['jobe_host'] = 'Jobe server';
$string['jobe_host_desc'] = 'The host name of the Jobe server plus the port number if other than port 80, e.g. jobe.somewhere.edu:4010. The URL for the Jobe request is obtained by default by prefixing this string with http:// and appending /jobe/index.php/restapi/<REST_METHOD>. You may either specify the https:// protocol in front of the host name (e.g. https://jobe.somewhere.edu) if the Jobe server is set behind a reverse proxy which act as an SSL termination. Multiple jobe servers, separated by a semicolon, are possible for handling higher loads: one is chosen at random.';
$string['jobe_host_ws'] = 'Jobe server to use for web services';
$string['jobe_host_ws_desc'] = 'The sandbox server web service will use whatever sandbox is configured for the specified
    language. This is virtually always a Jobe server, and the particular Jobe server to use is configured via the admin interface (above).
    However, for best web service security it is better to use an alternative
    Jobe server, set by this field. Multiple jobe servers, separated by a semicolon, are possible for handling higher loads: one is chosen at random. Leave blank to use the default. ';
$string['jobe_warning_html'] = "<p style='background-color:yellow'>Run using the University of Canterbury's Jobe server. This is for initial testing only. Please set up your own Jobe server as soon as possible. See <a href='https://github.com/trampgeek/moodle-qtype_coderunner/blob/master/Readme.md#sandbox-configuration' target='_blank'>here</a>.</p>";
$string['jobe_canterbury_html'] = "<p style='color:gray; font-style:italic; font-size:smaller'>Run on the University of Canterbury's Jobe server.</p>";

$string['language'] = 'Sandbox language';
$string['languages'] = 'Languages';
$string['languages_help'] = 'The sandbox language is the computer language used
to run the submission. This should not usually need altering from the value in the
parent template; tweak it at your peril.

Ace-language is the
language used by the Ace code editor (if enabled) for the student\'s answer.
By default this is the same as the sandbox language; enter a different
value here only if the template language is different from the language
that the student is expected to write (e.g. if a Python template is
used to preprocess a student\'s C program and then execute it in a subprocess).

Multi-language questions, that is questions that students can answer in
more than one language, are enabled by setting the Ace-language to a comma-separated
list of languages. Students are then presented with a drop-down menu to select
the language in which their answer is written. If exactly one of the languages
has an asterisk (\'\*\') appended, that language is chosen as the default language,
which is selected as the initial state of the drop-down menu. For example,
an Ace-language value of "C,C++,Java\*,Python3" would allow student to submit in
C, C++, Java or Python3 but the drop-down menu would initially show Java which
would be the default. If no default is specified the
initial state of the drop-down is empty and the student must choose a language.
Multilanguage questions require a special template that uses the {{ANSWER\_LANGUAGE}}
template variable to control how to execute the student code. See the built-in
sample multilanguage question type. The {{ANSWER\_LANGUAGE}} variable is defined
<i>only</i> for multilanguage questions.

If the author wishes to supply a sample answer to a multilanguage question,
they must write it in the default language, if specified, or the
first of the allowed languages otherwise.';

$string['languageselectlabel'] = 'Language';
$string['legacyuiparams'] = 'UI parameters can no longer be defined within the template parameters field. Please move the following to the UI parameters field instead: ';
$string['legacyuiparams2'] = 'UI parameters can no longer be defined within the template parameters field. Please move the following to the UI parameters field instead, removing the \'{$a->uiname}_\' prefix: ';
$string['listprototypeduplicates'] = 'Question ID: {$a->id} <ul><li>Name: {$a->name}</li><li>Category: {$a->category}</li></ul>';
$string['loadprototypeerror'] = 'Reverted to question type: \'{$a->oldtype}\' <br>Could not load question type \'{$a->crtype}\' as the prototype is non-unique in the following questions:</p>{$a->outputstring}';
$string['mark'] = 'Mark';
$string['marking'] = 'Mark allocation';
$string['markinggroup'] = 'Marking';
$string['markinggroup_help'] = 'If \'All-or-nothing\' is checked, all test cases must be satisfied
for the submission to earn any marks. Otherwise, the mark is obtained
by summing the marks for all the test cases that pass
and expressing this as a fraction of the maximum possible mark.
The per-test-case marks can be specified only if the all-or-nothing
checkbox is unchecked. If using a template grader that awards
part marks to test cases, \'All-or-nothing\' should generally be unchecked.

The mandatory penalty regime is a comma-separated list of penalties (each a percent)
to apply to successive submissions. These are absolute, not cumulative. As a
special case the last penalty can be \'...\' to mean "extend the previous
two penalties as an arithmetic progression up to 100". For example,
<code>0,5,10,30,...</code> is equivalent to <code>0,5,10,30,50,70,90,100</code>.
If there are more submissions than defined penalties, the last value is used.
Spaces can be used in lieu of commas as a separator.

The default penalty regime can be set site-wide by a system administrator using
Site administration > Plugins > Question types > CodeRunner.

Set the penalty regime to \'0\' for zero penalties on all submissions.

The penalty regime is ignored and no penalties are applied if the quiz is
run using the \'Adaptive (no penalties)\' behaviour.';
$string['maxfilesize'] = 'Max allowed file size (bytes)';
$string['maxfilesize_help'] = 'Select the maximum file upload size (bytes). Allowing large file uploads with large classes can impact performance and and disk space on both Moodle and Jobe servers.';
$string['memorylimit'] = 'MemLimit (MB)';
$string['missinganswers'] = 'missing answers';
$string['missingorbadfraction'] = 'Bad or missing fraction in output from template grader. Output was: {$a->output}';
$string['missingoutput'] = 'You must supply the expected output from this test case.';
$string['missingprototype'] = 'This question was defined to be of type \'{$a->crtype}\' but the prototype does not exist, or is unavailable in this context. You should Cancel and try to (re)install the prototype.
Proceed to edit only if you know what you are doing!';
$string['missingprototypes'] = 'Missing prototypes';
$string['missingprototypewhenrunning'] = 'Broken question (missing or duplicate prototype \'{$a->crtype}\'). Cannot be run.';
$string['missinguiparams'] = 'The following UI parameters are required but not defined: ';
$string['multipledefaults'] = 'At most one language can be selected as default';
$string['multipleprototypes'] = 'Multiple prototypes found for \'{$a->crtype}\'';
$string['mustrequirefewer'] = 'You cannot require more attachments than you allow.';

$string['nearequalitygrader'] = 'Nearly exact match';
$string['nodetailsavailable'] = 'Select a question type to see detailed help.';
$string['nouiparameters'] = 'The {$a->uiname} UI does not take parameters.';
$string['noqtype'] = 'No question type selected';
$string['nolanguage'] = 'Please select language first';
$string['morehidden'] = 'Some hidden test cases failed, too.';
$string['noerrorsallowed'] = 'Your code must pass all tests to earn any marks. Try again.';
$string['nonnumericmark'] = 'Non-numeric mark';
$string['nosampleanswer'] = 'No sample answer';
$string['nooutput'] = '< No output! >';
$string['negativeorzeromark'] = 'Mark must be greater than zero';

$string['options'] = 'Options';
$string['ordering'] = 'Ordering';
$string['overallresult'] = 'Overall result';
$string['overloadoninit'] = 'Sandbox server overload prevented question initialisation';

// Output Display Area Errors (Used in Scratchpad UI) for displaying code output. See outputdisplayarea.js.
$string['outputdisplayarea_invalid_mode'] = 'Invalid output display mode:';
$string['outputdisplayarea_invalid_json'] = 'Error parsing JSON. Output from wrapper:';
$string['outputdisplayarea_missing_json_fields'] = 'Output display JSON is missing required fields:';
$string['outputdisplayarea_missing_image_extension'] = 'Cannot display image, include file extension in filename:';

$string['passes'] = 'passes';
$string['penaltyregime'] = '(penalty regime: {$a} %)';
$string['penaltyregimelabel'] = 'Penalty regime:';

$string['pass'] = 'Pass';
$string['pluginname'] = 'CodeRunner';
$string['pluginnameadding'] = 'Adding a CodeRunner question';
$string['pluginnameediting'] = 'Editing a CodeRunner question';
$string['pluginnamesummary'] = 'CodeRunner: runs student-submitted code in a sandbox';
$string['pluginname_help'] = 'Use the \'Question type\' combo box to select the
computer language and question type that will be used to run the student\'s submission.
Specify the problem that the student must write code for, then define
a set of tests to be run on the student\'s submission';
$string['pluginname_link'] = 'question/type/coderunner';
$string['precheck'] = 'Precheck';
$string['precheck_disabled'] = 'Disabled';
$string['precheck_empty'] = 'Empty';
$string['precheck_examples'] = 'Examples';
$string['precheck_selected'] = 'Selected';
$string['precheck_all'] = 'All';
$string['precheck_help'] = 'Set what button are available for students to
submit answers. Usually at least a Check button is shown but this can be
hidden (e.g. for use in Deferred Feedback contexts) if <i>Hide check</i> is checked.

If Precheck is enabled, students will have an extra button to the left of the
usual check button to give them a penalty-free run to check their code against
a subset of the question test cases. Then<ul>
<li>If \'Empty\' is selected, a single run
will be done with the per-test template using a testcase in which all the
fields (testcode, stdin, expected, etc) are the empty string. Non-empty output
is deemed to be a precheck failure. Use with caution:
some question types will not handle this correctly, e.g. write-a-program questions
that generate output.
</li><li>If \'Examples\' is selected, the code will
be tested against all the tests for which \'use_as_example\' has been checked.
</li><li>If \'Selected\' is selected, an extra UI element is added to each test case
to allow the author to select a specific subset of the tests.
</li><li>If \'All\' is selected, all test cases are run (although their behaviour might
be different from the normal Check, if the template code so chooses).
</ul>
The template can check whether or not the run is a precheck run using the
Twig parameter {{ IS_PRECHECK }}, which is "1" during precheck runs and
"0" otherwise.';
$string['precheck_only'] = 'Precheck only';
$string['precheckingemptyset'] = 'Prechecking examples, but there aren\'t any!';
$string['privacy:metadata'] = 'The CodeRunner question type plugin does not store any personal data.';
$string['proceed_at_own_risk'] = 'Editing a built-in question prototype?! Proceed at your own risk!';
$string['prototypecontrols'] = 'Prototyping';
$string['prototypeexists'] = 'This is a prototype; cannot change question type.';
$string['prototypeextra'] = 'Prototype extra';
$string['prototypeextra_help'] = 'A field of text for general-purpose use by question type authors, like global extra, but part of the prototype state. Available to the template author as {{ QUESTION.prototypeextra }}.';

$string['prototypeusage'] = 'CodeRunner question prototype usage for course {$a}';
$string['prototypeusageindex'] = 'Available courses';
$string['prototypecontrols_help'] = 'If \'Is prototype\' is true, this question becomes a prototype for other questions.
After saving, the specified question type name will appear in the dropdown list
of question types. New questions based on this type will then by default inherit
all the customisation attributes specified for this question. Subsequent changes
to this question will then affect all derived questions unless they are
themselves customised, which breaks the connection.

Prototypal inheritance is
single-level only, so this question, when saved as a prototype, loses its
connection to its original base type, becoming a new base type in its own right.
Be warned that when exporting derived questions you must ensure that this
question is included in the export, too, or the derived question will be an
orphan when imported into another system. Also, you are responsible for keeping
track of which questions you are using as prototypes; it is strongly recommended
that you rename the question to something like \'PROTOTYPE_for_my_new_question_type\'
to make subsequentmaintenance easier.';
$string['prototype_error'] = '*** PROTOTYPE LOAD FAILURE. DON\'T SAVE THIS! ***';
$string['prototype_load_failure'] = 'Error loading prototype: ';
$string['prototype_missing_alert'] = 'Missing prototype: Check if {$a} prototype exists in this context.';
$string['prototype_duplicate_alert'] = 'Duplicate prototype: Duplicate {$a} prototypes exist. Can only load one.';
$string['prototypeQ'] = 'Is prototype?';

$string['qtype_c_function'] = '<p>A question type for C write-a-function questions.
The student answer is expected to be a complete C function, but it can optionally
be preceded by other self-contained C code such as preprocessor directives and
support functions.</p>
<p>The test code for such questions typically calls the student function with
some test arguments and prints the result, such as
<pre>printf("%d\n", someIntFunction(blah1, blah2))</pre>
The test case\'s <i>Expected</i> field is the expected output from the test.</p>
<p>
If there is no standard input supplied for any of the test cases, a single
test program is constructed, consisting of:</p>
<ol>
<li>The following standard #includes: stdlib.h, ctype.h, string.h, stdbool.h, math.h</li>
<li>The student answer.</li>
<li>A sequence of blocks wrapped in braces for each of the given test cases.
Each block contains just the test case\'s test code. There is also a <i>printf</i>
statement added between code blocks to print a special separator that is used
to split the output back into individual test case outputs.</li>
</ol>
<p>However, if any of the test cases has non-empty standard input, multiple test
programs are run, one for each test case.
</p><p>The test case\'s <i>extra</i> field is ignored.</p>';

$string['qtype_cpp_function'] = '<p>A question type for C++ write-a-function questions.
The student answer is expected to be a complete C++ function, but it can optionally
be preceded by other self-contained C++ code such as preprocessor directives and
support functions.</p>
<p>In each test case, the test code for such questions typically calls the student function with
some test arguments and prints the result, such as
<pre>cout << someIntFunction(blah1, blah2))</pre>
The test case\'s <i>Expected</i> field is the expected output from the test.
<p>
If there is no standard input supplied for any of the test cases, a single
test program is constructed, consisting of:</p>
<ol>
<li>The following standard #includes: iostream, fstream, string, math, vector and algorithm</li>
<li><code>using namespace std;</code></li>
<li>The student answer</li>
<li>A sequence of blocks wrapped in braces for each of the given test cases.
Each block consists of the test case\'s <i>extra</i> field (usually empty)
followed by the test code. There is also a <i>printf</i>
statement added between code blocks to print a special separator that is used
to split the output back into individual test case outputs.</li>
</ol>
<p>However, if any of the test cases has non-empty standard input, multiple test
programs are run, one for each test case.
</p>';

$string['qtype_c_program'] = '<p>Used for C write-a-program questions, where
there is no per-test-case code, and the different tests just use different
standard input (stdin) data. The student answer is expected to be a complete
runnable program, which is run as-is, without modification by CodeRunner,
once for each test case. The values of the test code and extra fields of each
test case are ignored.</p><p>If you need to set special compile or link
arguments for the question, you can customise it (click the Customise
checkbox), open the <i>Advanced customisation</i> section, and enter suitable
values into the <i>Sandbox &gt; Parameters</i> field. For example<pre>
{"linkargs":["-lm"]}</pre>to link with the math library.';

$string['qtype_cpp_program'] = '<p>Used for C++ write-a-program questions, where
there is no per-test-case code, and the different tests just use different
standard input (stdin) data. The student answer is expected to be a complete
runnable program, which is run as-is, without modification by CodeRunner,
once for each test case. The values of the test code and extra fields of each
test case are ignored.</p>';

$string['qtype_directed_graph'] = '<p>A python3 question type that asks the student to draw
a directed graph to satisfy some specification. The question author has to write
Python3 code to check the resulting graph.</p><p>Note that it is not actually
necessary to use this question type for directed graphs, as the functionality
is mainly provided by the GraphUI plugin. If the graph pre-processing performed
by this question type does not suit your needs, you can instead just use a normal
Python3 question (or any other language), set the UI to GraphUI, and analyse
the JSON-serialised version of the graph (the Twig STUDENT_ANSWER&nbsp; variable)
yourself. However, this question type does provide an example of how to use the
GraphUI plugin. Click <i>Customise</i>&nbsp;to see the template code.</p>
<p>The specification will ask the student to draw a directed graph to satisfy
certain requirements. It might for example be a DFA (deterministic finite-state
automaton) or a Turning machine. The test case code and/or the extra code will
then analyse the graph and print a message to the student, such as OK if the
graph is correct or a suitably informative error message otherwise.</p>
<p>The template for this question analyses the JSON-serialised graph, extracting
its topology in the form of an adjacency dictionary&nbsp;<i>graph</i>. This
variable is available to the test or extra code in the test case. Keys in the
dictionary are node names, if given, or arbitrary node identifying labels of
the form #1, #2 etc otherwise. Values in the dictionary are lists of outgoing
edges, sorted by neighbour node name or identifier, each edge being a tuple
(neighbourId, edgeLabel).</p><p>Each entry in the adjacency list is of the form
(nodeNameOrId, neighbours) where neighbours is a list of tuples
(neighbourNodeNameOrId, edgeLabel). If nodes are given names, those are used
as node identifiers, otherwise the names #1, #2 etc are used. The adjacency
list and the neighbour list are both sorted in order of node name or identifier.</p>
<p>The template is a combinator one: the <i>testcode</i>&nbsp;and
<i>extra</i>&nbsp;code are both executed for each test case.</p><p>As a simple
example, if the specification were just "Draw a directed graph with two nodes
labelled A and B, with an edge from A to B", a suitable test case (albeit with
unhelpful error output) might be:</p><pre>
if set(graph.keys()) == {\'A\', \'B\'} and len(graph[\'A\']) == 1 and len(graph[\'B\']) == 0 and graph[\'A\'][0][0] == \'B\':
    print(\'OK\')
else:
    print(\'Nope\')
</pre>
<p>Alternatively, there could be a set of test cases, each one checking
one of the aspects of the specification. For example, the first test case might
print the sorted keys, expecting to see \'A\', \'B\'. The second test case might
print the outgoing edges from node \'A\', and so on.</p>';

$string['qtype_java_class'] = '<p>A Java write-a-class question, where the student submits a
complete class as their answer. Each test will  typically instantiate an object of the specified
class and perform one or more tests on it. It is not a combinator question type, meaning that
each test case runs as a separate sandbox program.
</p><p>The program generated for each test case consists of the student answer, with
the <i>public</i>&nbsp;attribute stripped if present. That (now local)
class definition is followed by a public <i>__Tester__&nbsp;</i>&nbsp;class that
has a <i>main</i>&nbsp;method that instantiates the Tester class and calls its
<i>runTests</i>&nbsp;method. The <i>runTests</i>&nbsp;method simply contains the
test case code. See the template for clarification.</p><p>It should be noted that
the algorithm used to strip the public attribute from the student-supplied class
is simplistic; it only works if the words <i>public class</i>&nbsp;exist exactly
once in the student code, separated by a single space.</p>
<p>The test case extra field is ignored.</p>
<p>This question type is inefficient if there are many test, as a separate
compile-and-execute job is sent to the sandbox for each test case. This could be
resolved by writing a combinator-style question type. See the coderunner
documentation (coderunner.org.nz) for more information.</p>';

$string['qtype_java_method'] = '<p>Used for Java write-a-method questions where the
student is asked to write a method that is essentially a standalone function.
The author-supplied test is typically just one or two lines of code that
(apparently) just call the student supplied method, as in C. Under the hood, the
template constructs a Main class containing the student-supplied method
(and any other support methods, if they choose to write them) plus a \'runTests\'
method that wraps the testcase(s). The main function for the class constructs an
instance of Main and calls its runTests method. See the template code for details.</p>';

$string['qtype_java_program'] = '<p>A Java write-a-program question where the student
submits a complete program as their answer. The program is compiled and executed for each
test case. There is no test code, just stdin test data, though this isn\'t
actually checked: caveat emptor. The extra fields of the test cases are likewise
ignored.</p>
<p>This question type becomes very inefficient if there are many test cases, since
each one necessitates a full compile-and-execute cycle on the Jobe server. It is
possible to wrap all tests into a single Python job that is sent to the sandbox
server and compiles the program just once, then runs it on each test case.
For details of this approach, see the question author forum on
coderunner.org.nz.</p>';

$string['qtype_multilanguage'] = '<p>A "write a program" style
of question in which the student can submit an answer in any one of several
different languages. The languages C, C++, Java, Python3, PHP and JavaScript (nodejs)
are standard on Jobe servers so should all work directly. The question type also
allows for the use of perl, ruby, golang and C# but these require the
appropriate additional compilers and/or interpreters to be installed on Jobe.
For example, <code>sudo apt-get install mono-mcs</code> to install the mono C#
compiler, mcs.</p>
<p>If the question author supplies a sample answer, they can specify the language
of their answer in one of three ways. <ol><li>By adding a template parameter like
<code>{"answer_language": "cpp"}</code> to specify the language they are using.</li>
<li>By using the default language, if one is set. The default languages is denoted
by an asterisk after the language name in the "Ace language" field in the
Advanced Customisation panel, if the author is using a customised question. There
is no default language otherwise.</li>
<li>By using the first language listed in the "Ace language" field in the
Advanced Customisation panel - default is C.</li></ol></p>
<p>The student\'s question answer
box has a drop-down menu at the top, with which the student must select
the language in which their answer is written.</p>
<p>Further languages can be added, if supported on the Jobe server, by
adding the language name to the <i>AceLang</i> field of the question edit
form and then extending the template (q.v.) to handle the new language.</p>
<p>The submitted program code is run as-is for each test case. The testcode
and extra fields of each test case are ignored.</p>';

$string['qtype_nodejs'] = '<p>A JavaScript question type, run using nodejs. The
test program to be executed starts with the student answer. That is followed
by each of the test case codes in turn, with a separator string being printed
between them. However, if there is any standard input present for any of the
test cases, a separate test run will be done for each test case.</p><p>
If there is a risk of side-effects from a test case affecting later test cases
you can add standard input to any one of the test cases to force the one-run-per-test-case
mode.</p>';

$string['qtype_octave_function'] = '<p>A question type that specifies an
Octave function, which the student has to submit in its entirety. Each test
case will typically call the student function with test arguments and print
the result or some value derived from it. If there is no standard input present
in any of the questions, the program consists of the student answer, the
statement <code>format free</code> and the test code from each test case,
plus an extra <i>disp</i> statement to print a separator string between
test case outputs.</p><p>If there is any standard input present, each test
case is instead run separately.</p>';

$string['qtype_pascal_function'] = '<p>A Pascal question type where the student
 is asked to write a procedure or function. The program to be run consists of
 the student answer followed by the CodeRunner <i>testcode</i> wrapped
 in <code>begin ... end.</code>.<br>
 This is not a combinator question type, so a separate jobe run will be done
 for each test case.</p>';

$string['qtype_pascal_program'] = '<p>A Pascal question type where the student
 answer is a complete Pascal program. The program is compiled and run once for
 each test case, using the standard input provided in the test case and
 ignoring the <i>testcode</i> and <i>extra</i> fields.</p>';

$string['qtype_php'] = '<p>A php question in which the student submission is
php code. In the simplest case, the student code will start with</p><pre>
&lt;?php
</pre>but <i>will not close the PHP tag</i>. The reason for the non-closure
can be seen by inspecting the template: the student answer is followed by each
of the test case test codes. If instead you wish the student code to end by
closing the PHP tag, you should edit the template to re-open the PHP tag before
the sequence of tests.
</p><p>The output from each test case, which should match the test case
<i>expected</i> field, will be the output from the student\'s PHP code
(including any content outside the scope of &lt;?php...?&gt; tags) plus the
output from the test code.</p><p>Inspect the template code (by clicking
<i>Customise</i>) to understand this.</p>';

$string['qtype_python2'] = '<p>A Python2 question type, which can handle
write-a-function, write-a-class or write-a-program question types. For each
test case, the student-answer code is executed followed by the test code.
Thus, for example, if the student is asked to write a function definition,
their definition will be executed first, followed by the author-supplied
test code, which will typically call the function and print the result or
some value derived from it.</p>
<p>If there are no standard inputs defined for all test cases, the question
actually wraps all the tests
into a single run, printing a separator string between each test case output.
Please be aware that this isn\'t necessarily the same as running each test
case separately. For example, if there are any global variables defined by
the student code, these will hold their values across the multiple runs.
If this is likely to prove a problem, the easiest work-around is to define
one of the test case standard input fields to be a non-empty value - this
forces CodeRunner into a fallback mode of running each test case separately.</p>';

$string['qtype_python3'] = '<p>A Python3 question type, which can handle
write-a-function, write-a-class or write-a-program question types. For each
test case, the student-answer code is executed followed by the test code.
Thus, for example, if the student is asked to write a function definition,
their definition will be executed first, followed by the author-supplied
test code, which will typically call the function and print the result or
some value derived from it.</p>
<p>If there are no standard inputs defined for all test cases, the question
actually wraps all the tests
into a single run, printing a separator string between each test case output.
Please be aware that this isn\'t necessarily the same as running each test
case separately. For example, if there are any global variables defined by
the student code, these will hold their values across the multiple runs.
If this is likely to prove a problem, the easiest work-around is to define
one of the test case standard input fields to be a non-empty value - this
forces CodeRunner into a fallback mode of running each test case separately.</p>';

$string['qtype_undirected_graph'] = '<p>A python3 question type that asks the student to draw
an undirected graph to satisfy some specification. The question author has to write
Python3 code to check the resulting graph.</p><p>Note that it is not actually
necessary to use this question type for undirected graphs, as the functionality
is mainly provided by the GraphUI plugin. If the graph pre-processing performed
by this question type does not suit your needs, you can instead just use a normal
Python3 question (or any other language), set the UI to GraphUI, and analyse
the JSON-serialised version of the graph (the Twig STUDENT_ANSWER&nbsp; variable)
yourself. However, this question type does provide an example of how to use the
GraphUI plugin. Click <i>Customise</i>&nbsp;to see the template code.</p>
<p>The specification will ask the student to draw an undirected graph to satisfy
certain requirements, e.g. a graph representation of a set of towns connected
by two-way roads. The test case code and/or the extra code will
then analyse the graph and print a message to the student, such as OK if the
graph is correct or a suitably informative error message otherwise.</p>
<p>The template for this question analyses the JSON-serialised graph, extracting
its topology in the form of an adjacency dictionary&nbsp;<i>graph</i>. This
variable is available to the test or extra code in the test case. Keys in the
dictionary are node names, if given, or arbitrary node identifying labels of
the form #1, #2 etc otherwise. Values in the dictionary are lists of edges,
sorted by neighbour node name or identifier, each edge being a tuple
(neighbourId, edgeLabel).</p><p>Each entry in the adjacency list is of the form
(nodeNameOrId, neighbours) where neighbours is a list of tuples
(neighbourNodeNameOrId, edgeLabel). If nodes are given names, those are used
as node identifiers, otherwise the names #1, #2 etc are used. The adjacency
list and the neighbour list are both sorted in order of node name or identifier.</p>
<p>The template is a combinator one: the <i>testcode</i>&nbsp;and
<i>extra</i>&nbsp;code are both executed for each test case.</p><p>As a simple
example, if the specification were just "Draw an undirected graph with two nodes
labelled A and B, with an edge between the two nodes", a suitable test case (albeit with
unhelpful error output) might be:</p><pre>
if set(graph.keys()) == {\'A\', \'B\'} and len(graph[\'A\']) == 1 and len(graph[\'B\']) == 1 and graph[\'A\'][0][0] == \'B\':
    print(\'OK\')
else:
    print(\'Nope\')
</pre>
<p>Alternatively, there could be a set of test cases, each one checking
one of the aspects of the specification. For example, the first test case might
print the sorted keys, expecting to see \'A\', \'B\'. The second test case might
print the edges connected to node \'A\', and so on.</p>';

$string['qtype_python3_w_input'] = '<p>A Python3 question type, which can handle
write-a-function, write-a-class or write-a-program question types. It differs
from the slightly simpler <i>python3</i> question type in that the usual
python3 <i>input</i> function is replaced with a custom version that echoes
standard input to standard output as it is consumed. This results in the output
mimicking that which is seen by students when testing with keyboard input.
It is recommended instead for the <i>python3</i> question type for any
questions that involve calls to <i>input</i> in introductory programming
courses, where students are likely to be confused by the non-echoing of
standard input when taken from a file.</p><p>A slight downside of this question
type compared to the <i>python3</i> question type is that any error messages
in the student code will have confusing line numbers, since the substitute
input function is inserted before the student code.</p>
<p>For each
test case, the student-answer code is executed followed by the test code.
Thus, for example, if the student is asked to write a function definition,
their definition will be executed first, followed by the author-supplied
test code, which will typically call the function and print the result or
some value derived from it.</p>
<p>If there are no standard inputs defined for all test cases, the question
actually wraps all the tests
into a single run, printing a separator string between each test case output.
Please be aware that this isn\'t necessarily the same as running each test
case separately. For example, if there are any global variables defined by
the student code, these will hold their values across the multiple runs.
If this is likely to prove a problem, the easiest work-around is to define
one of the test case standard input fields to be a non-empty value - this
forces CodeRunner into a fallback mode of running each test case separately.</p>';

$string['qtype_sql'] = '<p>A SQL question type, using sqlite3,
 run from Python3. sqlite3 must be installed on the Jobe server for this question
 type.</p>
 <p>The working directory is searched for files with an extension \'.db\'. If
 there is only one such file, it is used as the sqlite3 database for all tests.
 Multiple .db files currently issues an error message; a possible extension is
 to use different db files for each test, e.g. in sorted order.</p>
 <p>For each test, an sqlite3 command script of the form</p>
 <pre>.mode column<br>.headers on<br>&lt;code in extra&gt;<br>&lt;student answer&gt;<br>&lt;testcode&gt;</pre>
 <p>is run.</p>
 <p>A fresh copy of the db file is used for each test case.&nbsp;</p>
 <p>A template parameter <i>columnwidths</i>&nbsp;can be used to set the report
 column widths. By default sqlite3 sets each column width to be the maximum of
 three numbers: 10, the width of the header, and the width of the first row of data.
 A template string like</p><pre><code>{"columnwidths": [10, 50, 10, 5]}
</code></pre>
<p>will instead use column widths of 10, 50, 10 and 5 for the first four columns.</p>';

$string['qtypehelp'] = 'Help with q-type';
$string['questioncheckboxes'] = 'Customisation';
$string['questioncheckboxes_help'] = 'To customise the question type, e.g. to edit
the question template, user interface or sandbox parameters, click the \'Customise\'
checkbox and read the help available on the newly-visible form elements for
more information.

If the template-debugging checkbox is clicked, the program generated
for each Jobe sandbox run will be displayed in the output.';
$string['questionloaderror'] = 'Failed to load question';
$string['questionpreview'] = 'Question preview';
$string['questiontype'] = 'Question type';
$string['question_type_changed'] = 'Changing question type. Click OK to reload customisation fields, Cancel to retain your customised ones.';
$string['questiontype_help'] = 'Select the particular type of question.

The combo-box selects one of the built-in types, each of which
specifies a particular language and, sometimes, a sandbox in which
the program will be executed. Each question type has a
template that defines how the executable program is built from the
testcase data and the student answer.

The template can be viewed and optionally customised by clicking
the \'Customise\' checkbox.

If the template-debugging checkbox is clicked, the program generated
for each testcase will be displayed in the output.';
$string['questiontypedetails'] = 'Question type details';

$string['questiontype_required'] = 'You must select the type of question';
$string['qWrongBehaviour'] = 'Please use Adaptive Behaviour for all CodeRunner questions, or there can be massive performance hits. For example, all questions on a page will need to be regraded when the page is re-displayed.';

$string['regexgrader'] = 'Regular expression';
$string['replacedollarscount'] = 'This category contains {$a} CodeRunner questions.';
$string['replaceexpectedwithgot'] = 'Click on the &lt;&lt; button to replace the expected output of this testcase with actual output.';
$string['resultcolumns'] = 'Result columns';
$string['reset'] = 'Reset answer';
$string['resethover'] = 'Discard changes and reset answer to original preloaded value';
$string['resultcolumnheader'] = 'Result';
$string['resultcolumns_help'] = 'By default the result table displays the testcode, stdin, expected and got
columns, provided the columns are not empty. You can change the default, and/or
the column headers by entering a value for the resultcolumns (leave blank for
the default behaviour).

If supplied, the resultcolumns field must be a
JSON-encoded list of column specifiers. Each column specifier is itself a list,
typically with just two or three elements. The first element is the column
header, the second element is the field from the TestResult object being
displayed in the column and the optional third element is an sprintf format
string used to display the field.

The fields available in the standard
TestResult object are: testcode, stdin, expected, got, extra, awarded, and mark.
testcode, stdin, expected and extra are the fields from the testcase while got
is the actual output generated and awarded and mark are the actual awarded mark
and the maximum mark for the testcase respsectively.

Per-test template-graders may
add their own fields, which can also be selected for display. It is also
possible to combine multiple fields into a column by adding extra fields to the
specifier: these must precede the sprintf format specifier, which then becomes
mandatory. For example, to display a Mark Fraction column in the form 0.74/1.00,
say, a column format specifier of ["Mark Fraction", "awarded", "mark",
"%.2f/%.2f"] could be used.

As a further special case, a format of %h means that
the test result field should be taken as ready-to-output HTML and should not be
subject to further processing; this is useful only with custom-grader templates
that generate HTML output, such as SVG graphics.

The default value of
resultcolumns is [["Test", "testcode"],["Input", "stdin"], ["Expected",
"expected"], ["Got", "got"]].

The setting of the resultcolumns field has no effect if a combinator template
grader is being used. The question author is then responsible for formatting
the result table in any desired way.';
$string['resultcolumnsnotjson'] = 'Result columns field is not a valid JSON string';
$string['resultcolumnsnotlist'] = 'Result columns field must a JSON-encoded list of column specifiers';
$string['resultcolumnspecbad'] = 'Invalid column specifier found: each one must be a list of two or more strings';
$string['resultstring-norun'] = 'No run';
$string['resultstring-compilationerror'] = 'Compilation error';
$string['resultstring-runtimeerror'] = 'Run error';
$string['resultstring-timelimit'] = 'Time limit exceeded';
$string['resultstring-success'] = 'OK';
$string['resultstring-memorylimit'] = 'Memory limit exceeded';
$string['resultstring-illegalsyscall'] = 'Illegal function call';
$string['resultstring-internalerror'] = 'CodeRunner error (IE): please tell a tutor';
$string['resultstring-sandboxpending'] = 'CodeRunner error (PD): please tell a tutor';
$string['resultstring-sandboxpolicy'] = 'CodeRunner error (BP): please tell a tutor';
$string['resultstring-sandboxoverload'] = 'Sandbox server overload. Perhaps try again soon?';
$string['resultstring-outputlimit'] = 'Excessive output';
$string['resultstring-abnormaltermination'] = 'Abnormal termination';
$string['run_failed'] = 'Failed to run tests';

$string['sampleanswerattachments'] = 'Sample answer attachments';
$string['sampleanswerattachments_help'] = 'If the sample answer needs attachments files, upload them here';
$string['sandboxcontrols'] = 'Sandbox';
$string['sandboxcontrols_help'] = 'All jobs are run on the Jobe sandbox, which imposes
constraints on memory, CPU time, file output etc. Here is where you adjust those constraints.

\'TimeLimit (secs)\' sets the maximum CPU time in seconds  allowed for each sandbox run
and \'MemLimit (MB)\' sets the maximum memory the run can use. A blank entry uses the sandbox\'s
default value (typically 5 secs for the CPU time limit and a language-dependent
amount of memory), but the defaults may not be suitable for resource-demanding
programs. A value of zero for the maximum memory results in no limit being
imposed. The amount of memory specified here is the total amount needed for
the run including all libraries, interpreters, VMs etc.

The \'Parameters\' entry
is used to pass further sandbox-specific data, such as compile options and
API-keys. It should generally be left blank but if non-blank it must be a valid
JSON record. In the case of the jobe sandbox, available attributes include
disklimit, streamsize, numprocs, compileargs, linkargs and interpreterargs. For
example <code>{"compileargs":["-std=c89"]}</code> for a C question would force C89
compliance and no other C options would be used. See the jobe documentation
for details. Some sandboxes (e.g. the deprecated Ideone sandbox) may silently ignore any or all of
these settings.

It is possible to select a different jobeserver by defining a \'jobeserver\'
parameter and also, optionally, a \'jobeapikey\' parameter. For example, if the
\'Parameters\' field is set to <code>{"jobeserver": "myspecialjobe.com"}</code>, the run
will instead by submitted to the server "myspecialjobe.com".
';
$string['enable_sandbox_ws'] = "Enable sandbox web service";
$string['enable_sandbox_ws_desc'] = 'Enable the web service allowing direct
access to the sandbox server (usually Jobe). EXPERIMENTAL FEATURE.';
$string['sandboxerror'] = 'Error from the sandbox [{$a->sandbox}]: {$a->message}';
$string['sandboxparams'] = 'Parameters';
$string['seethisquestioninthequestionbank'] = 'See this question in the question bank';
$string['SHOW'] = 'Show';
$string['showcolumns'] = 'Show columns:';
$string['showcolumns_help'] = 'Select which columns of the results table should
be displayed to students. Empty columns will be hidden regardless.
The defaults are appropriate for most uses.';
$string['showdetails'] = 'Show details';
$string['showdifferences'] = 'Show differences';
$string['showsource'] = 'Template debugging';
$string['sourcecodeallruns'] = 'Debug: source code from all test runs';
$string['stdin'] = 'Standard Input';
$string['stdin_help'] = 'The standard input to the test, seen by the template as {{TEST.stdin}}';
$string['student_answer'] = 'Student answer';
$string['submitbuttons'] = 'Submit buttons';
$string['supportscripts'] = 'Support scripts';
$string['syntax_errors'] = 'Syntax Error(s)';

// SCRATCHPAD UI Default text.
$string['scratchpadui_def_button_name'] = 'Run';
$string['scratchpadui_def_scratchpad_name'] = 'Scratchpad';
$string['scratchpadui_def_prefix_name'] = 'Prefix with Answer';
$string['scratchpadui_def_help_text'] = '<p>You can enter code into this panel and click \'Run\' to execute it.</p>
<p>By default, the code in this panel is prefixed with the contents of the answer box, giving you an easy way to test your answer.</p>
<p>You can uncheck the \'Prefix with answer\' checkbox to run the code in this panel standalone, e.g. to explore how small code fragments behave.</p>';
// SCRATCHPAD UI Parameter descriptions.
$string['scratchpadui_scratchpad_name_descr'] = 'Display name of the scratchpad, used to hide/un-hide the scratchpad.';
$string['scratchpadui_button_name_descr'] = 'Run button text.';
$string['scratchpadui_prefix_name_descr'] = 'Prefix with answer check-box label text.';
$string['scratchpadui_run_lang_descr'] = 'Language used to run code when the run button is clicked, this should be the language your wrapper is written in (if applicable).';
$string['scratchpadui_params_descr'] = 'Parameters for the sandbox webservice.';
$string['scratchpadui_output_display_mode_descr'] = 'Control how program output is displayed on runs, there are three modes:
    <ul>
        <li>text: Display the output as text, html escaped. (default)</li>
        <li>json: Display programs that output JSON, useful for capturing stdin and displaying images. (recommended)</li>
        <ul>
            <li>Accepts JSON in run output with the fields:</li>
            <ul>
                <li>returncode: Exit code from program run.</li>
                <li>stdout: Stdout text from program run.</li>
                <li>stderr: Error text from program run.</li>
                <li>files: An object containing filenames mapped to base64 encoded images. These will be displayed below any stdout text.</li>
            </ul>
           <li>When the returncode is set to 42, an HTML input field will be added after the last stdout received. When the enter key is pressed inside the input, the input\'s value is added to stdin and the program is run again with this updated stdin. This is repeated until returncode is not set to 42. </li></ul>
        <li>html: Display program output as raw html inside the output area. (advanced)</li>
            <ul>
                <li>This can be used to show images and insert other HTML.</li>
                <li>Giving an &lt;input&gt; element the class coderunner-run-input will add an event: when the enter key is pressed inside the input, the input\'s value is added to stdin and the program is run again with this updated stdin.</li>
            </ul>
    </ul>';
$string['scratchpadui_open_delimiter_descr'] = 'The opening delimiter to use when inserting answer or Scratchpad code into the wrapper. It will replace the default value \'{|\'.';
$string['scratchpadui_close_delimiter_descr'] = 'The closing delimiter to use when inserting answer or Scratchpad code into the wrapper. It will replace the default value \'|}\'.';
$string['scratchpadui_help_text_descr'] = 'The help text to show.';
$string['scratchpadui_wrapper_src_descr'] = 'The location of wrapper to be used by the run button: setting to \'globalextra\' will use text in global extra field, \'prototypeextra\' will use the prototype extra field.';
$string['scratchpadui_disable_scratchpad_descr'] = 'Disable the scratchpad, effectively revert back to Ace UI from student perspective.';
$string['scratchpadui_invert_prefix_descr'] = 'Inverts meaning of prefix_ans serialisation: \'1\' means un-ticked -- and vice versa. This can be used to swap the default state.';
$string['scratchpadui_escape_descr'] = 'Escape (JSON with " removed from start and end) ANSWER_CODE and SCRATCHPAD_CODE before insertion into wrapper. Useful when inserting code into a string. NOTE: single quotes \' are NOT escaped.';
// SCRATCHPAD UI Errors.
$string['scratchpad_ui_badrunwrappersrc'] = 'Invalid run wrapper source given, please contact question author.';
$string['scratchpad_ui_invalidserialisation'] = 'Invalid JSON serialisation provided, must include \"answer_code\" field.';
$string['scratchpad_ui_templateloadfail'] = 'Scratchpad UI template failed to load, please refresh the page. If this persists please report.';

$string['tableui_num_rows_descr'] = 'The (initial) number of rows in the table, excluding the top header row (if headers are given). Required.';
$string['tableui_num_columns_descr'] = 'The number of columns in the table, excluding the left-most label column (if labels are given). Required.';
$string['tableui_column_headers_descr'] = 'A list of strings for the column headers.';
$string['tableui_dynamic_rows_descr'] = 'If true, and "Add row" button is provided to allow users to add rows to the table.';
$string['tableui_row_labels_descr'] = 'A list of strings for the row labels, i.e. the values in column 0.';
$string['tableui_locked_cells_descr'] = 'A list of 2-element lists giving the 0-origin coordinates of cells that the user cannot alter. Coordinates do not include a possible header row or row-label column.';
$string['tableui_column_width_percents_descr'] = 'A list of floating point numbers giving the percentage of the available table width to allocate to the columns, including the row-label column if present.';
$string['tableui_lines_per_cell_descr'] = 'The number of text rows in each textarea/cell';
$string['tableui_sync_interval_secs_descr'] = 'The time interval in seconds between calls to sync the UI contents back to the question answer. 0 for no such auto-syncing.';

$string['table_ui_invalidjson'] = 'Table UI: invalid JSON serialisation.';
$string['table_ui_invalidserialisation'] = 'Table UI: invalid serialisation.';
$string['table_ui_missingparams'] = 'Table UI needs parameters num_columns and
num_rows.';
$string['template'] = 'Template';
$string['template_changed'] = 'Per-test template changed - disable combinator? [\'Cancel\' leaves it enabled.]';
$string['templatecontrols'] = 'Template controls';
$string['templatecontrols_help'] = 'Checking the \'Is combinator\' checkbox
specifies that the template is a combinator template, which combines (or attempts
to combine) the student answer plus all test cases into a single run. If this
checkbox is checked, you will also need to define the value of the test_splitter_re
field, which is the PHP regular expression used to split the output from the
program run back into a set of individual test runs. However, you do not need
to define this if you\'re also using a template grader, as in that case the
template code is responsible for splitting the output itself, and grading it.

Combinator templates do not get passed a TEST Twig variable. Instead they
receive a variable TESTCASES, which is a list of all the tests in the
question. The program produced by the template is generally assumed to combine the
STUDENT_ANSWER and all the TESTCASES into a single program which, when it is run,
outputs the test results from each test case, separated by a unique string.
The separator string is defined by a regular expression given by the form
field \'test_splitter_re\' below.

However, if testcases have standard input defined, combinator templates become
problematic. If the template constructs a single program, what should the standard
input be? The simplest (and default) solution is to
run the test cases one at a time, using the combinator template to build
each program, passing it a TESTCASES variable containing just a single test.
This \'trick\' allows the combinator template to serve a dual role: it behaves
as a per-test-case template (with a 1-element TESTCASES array) when the question
author supplies standard input but as a proper combinator (with an n-element
TESTCASES array) otherwise. To change this behaviour so that the combinator
receives all testcases, even when stdin is present, check the \'Allow multiple
stdins\' checkbox.

If a run of the combinator program results in any output to stderr, that
is interpreted as a run error. To ensure the student gets credit for as many
valid tests as possible, the system behaves as it does when standard input
is present, falling back to running each test separately. This does not
apply to combinator graders, however, which are required to deal with all
runtime errors themselves and must always return a valid JSON outcome.';
$string['templateerror'] = 'TEMPLATE ERROR';
$string['templategrader'] = 'Template grader';
$string['template_help'] = 'The template defines the program(s) that run in the sandbox for a given
student answer and test(s). There are two
types of template:

* a per-test template, which defines a program to be run for a single test case and,
* a \'combinator\' template which defines a program that combines all the different cases into a single program.

The \'is_combinator\' checkbox is left unchecked for a per-test template and is
set checked for a combinator template. The rest of this help panel assumes you
are using a per-test template; see the full documentation for the use of
combinator templates.

The template is processed
by the Twig template engine (see http://twig.sensiolabs.org)
in a context in which STUDENT_ANSWER is the student\'s
response and TEST.testcode is the code for the current testcase. These values
(and other testcase values like TEST.expected, TEST.stdin, TEST.mark)
can be inserted into the template by enclosing them in double braces, e.g.
<code>{{TEST.testcode}}</code>. For use within literal strings, an appropriate escape
function should be applied, e.g. <code>{{STUDENT_ANSWER | e(\'py\')}}</code> is
suitable for use within Python triple-double-quoted
strings. Other escape functions are <code>e(\'c\')</code>, <code>e(\'java\')</code>,
<code>e(\'matlab\')</code>. The program that is output by Twig is then compiled and executed
with the language of the selected built-in type and with stdin set
to TEST.stdin. Output from that program is then passed to the selected grader.
See the help under \'Grading controls\' for more on that.

Note that if a customised per-test template is used
there will be a compile-and-execute job submitted to the sandbox for every test case,
whereas most built-in question types define instead a combinator template that combines
all test cases into a single run.

If the template-debugging checkbox is clicked, the program generated
for each sandbox run will be displayed in the output.';
$string['templateparams'] = 'Template params';
$string['templateparamsevalpertry'] = 'Evaluate per student';
$string['templateparamslang'] = 'Preprocessor';
$string['templateparamsusingsandbox'] = 'Preprocessors other than Twig use
the sandbox server. If "Evaluate per student" is also set, then when a student
starts a quiz all such questions initiate
a sandbox run before the question can even be displayed. In a test or exam,
this can overload the sandbox server. Caveat emptor!';
$string['templateparams_help'] = 'If non-blank, the template parameters field must
evaluate to a JSON-format record. In its simplest form the field <i>is</i> just a JSON
record defining a set of variables that are added to the environment for the Twig
template engine when it expands the template (and other fields if Twig All is set).

If a preprocessor is specified in the Template param controls section,
the template parameters are first processed by the specified language
to yield a JSON record. See <a href="https://coderunner.org.nz/mod/url/view.php?id=199">
the documentation</a> for details.

<b>Warning:</b> use of a preprocessor other than Twig can have drastic performance
implications if the Evaluate-per-student checkbox is
checked, which it has to be if used for randomisation or for per-student question
customisation. Preprocessing must be done before a question
can be displayed to a student and, except for Twig, takes place on the Jobe sandbox
server. Every attempt on every question of this sort by every student will result in a job
being sent to that server. This can result in thousands of jobs hitting the
Jobe server at once at the start of a large test or exam, which will probably
overload it. Caveat Emptor!';
$string['testalltitle'] = 'Test all questions in this context';
$string['testallincategory'] = 'Test all questions in this category';
$string['testcase'] = 'Test case {$a}';
$string['testcasecontrols'] = 'Test properties:';
$string['testcasecontrols_help'] = 'If \'Use as example\' is checked, this test will be automatically included in the
question\'s \'For example:\' results table.

The \'Display\' combobox determines when this testcase is shown to the student
in the results table.

If \'Hide rest if fail\' is checked and this test fails, all subsequent tests will
be hidden from the student, regardless of the setting of the \'Display\' combobox.

\'Mark\' sets the value of this test case; meaningful only if this is not an
\'All-or-nothing\' question.

\'Ordering\' can be used to change the order of testcases when the question is
saved: testcases are ordered by this field.';
$string['testcases'] = 'Test cases';
$string['testcode'] = 'Test code';
$string['testcolhdr'] = 'Test';
$string['testingquestion'] = 'Testing question {$a}';
$string['testsplitterre'] = 'Test splitter (regex)';
$string['testcode_help'] = 'The code for the test, seen by the template as {{TEST.testcode}}';
$string['testtype'] = 'Precheck test type';
$string['testtype_help'] = 'If Prechecking is enabled and set to \'selected\', this setting controls whether
the test is used only with a normal run, only with a precheck run or in both runs.
If Prechecking is set to anything other than \'selected\', this setting is
ignored.';
$string['testtype_normal'] = 'Check only';
$string['testtype_precheck'] = 'Precheck only';
$string['testtype_both'] = 'Both';
$string['tooshort'] = 'Answer is too short to be meaningful and has been ignored without penalty';
$string['twigall'] = 'Twig all';
$string['twigcontrols'] = 'Template param controls';
$string['twigcontrols_help'] = 'Template parameters were traditionally referred to during Twig expansion in the form
{{QUESTION.parameters.someparam}} However, if the Hoist Template Parameters
checkbox is checked, the parameters are hoisted into the Twig global name space
and can be referenced simply as {{someparam}}.

Ace/Scratchpad compliance allows seamless switching between the Ace and
Scratchpad UIs. Leave checked unless you wish Ace to be able to edit a JSON string
with an "answer_code" key, which would be taken to be a Scratchpad serialisation.

If Twig All is checked, Twig macro expansion is applied to the
question text, sample answer, answer preload, UI parameters and all test case fields
using the template parameters as an environment. You will usually
need to turn on TwigAll if using randomisation within the template parameters.
Note that this Twig All expansion occurs when the question is first initialised, whereas
the Twig expansion of the template occurs much later, when the student submits
an answer. The environment for expanding the template includes the QUESTION
Twig variable (a subset of the entire question record), some fields of which
might have been expanded as a result of using Twig All.

The text in the template parameters field must either be JSON or must evaluate
to yield JSON when processed by the specified Preprocessor. Be warned that choosing
a preprocessor other than Twig results in a submission to the Jobe sandbox before
the question can even be displayed. See <a href="https://coderunner.org.nz/mod/url/view.php?id=199">
the documentation</a> for how to write a non-Twig preprocessor.

If using a preprocessor other than Twig, a Jobe sandbox submission is usually
required for each question for each student when they start the quiz.
If <i>Evaluate per student</i> is unchecked a single sandbox submission will
take place only when the question is saved; this is a relatively low-cost operation but
is not normally useful, as it essentially prevents
any use of per-student randomisation. It can however be used to generate
question content in some situations.';
$string['twigerror'] = 'Twig error {$a}';
$string['twigerrorintest'] = 'Twig error when processing this test {$a}';
$string['type_header'] = 'CodeRunner question type';
$string['typename'] = 'Question type';
$string['typerequired'] = 'Please select the type of question (language, format, etc)';

$string['uichanged'] = 'UI changed. Save and reload page to see and edit available ui parameters (if any).';
$string['uicontrols'] = 'Input UIs';
$string['uicontrols_help'] = 'Select the User Interface controllers for the student answer and
the question author\'s template.

The Student Answer dropdown displays a list
of available plugins. For coding questions, the Ace editor is usually used.
A value of \'None\' can be used to provide just a raw text box.

The value
\'Graph\' provides the user with a simple graph-drawing user-interface for use
with questions that ask the student to draw a graph to some specification; such
questions will usually have a single test case, graded with a template
that analyses the serialised
representation of the graph and prints a message like "OK" if the answer is
correct or a suitably informative error message otherwise.

The \'Table\' user interface element, which displays a table of text
areas for the student to
fill in. It is used by the \'python3_program_testing\' question type, which is
included in the sample questions on github.

The \'Gapfiller\' and \'Html\' user interfaces are documented in the
main CodeRunner documentation at https://github.com/trampgeek/moodle-qtype_coderunner#code-runner.

Students with poor eyesight, or authors wishing to inspect serialisations
(say to understand the representation used by the Graph UI),
can toggle the use of all UI plugins on the current page by typing
Ctrl-Alt-M.

Whatever value is selected for the student answer will also be used within
the editor form for the Sample Answer and the Answer Preload fields.

If \'Template uses ace\' is checked,
the Ace code editor will manage both the template and the template parameters
boxes. Otherwise a raw text box will be used.';
$string['ui_fallback'] = 'Falling back to raw text area.';
$string['uiparametergroup'] = 'UI parameters';
$string['uiparametergroup_help'] = 'A JSON string defining any User Interface
parameter values that are either required by the UI plugin or which override the
default values. For example, to draw larger nodes when using the GraphUI: \'{"noderadius": 30}\'';
$string['uiparameters'] = 'UI parameters (JSON)';
$string['uiparametertablehead'] = 'The {$a->uiname} UI takes the following parameters: ';
$string['uiparamname'] = 'Name';
$string['uiparamdesc'] = 'Description';
$string['uiparamdefault'] = 'Default';
$string['unauthorisedbulktest'] = 'You do not have suitable access to any CodeRunner questions';
$string['unauthoriseddbaccess'] = 'You are not authorised to use this script';
$string['unknownerror'] = 'An unexpected error occurred. The sandbox may be down. Try again shortly.';
$string['unknowncombinatorgraderfield'] = 'Unknown field name ({$a->fieldname}) in combinator grader output';
$string['unknownuiplugin'] = 'Information on an unknown plugin ({$a->pluginname}) was requested.';
$string['unserializefailed'] = 'Stored test results could not be deserialised. Perhaps try regrading?';
$string['useasexample'] = 'Use as example';
$string['useace'] = 'Template uses ace';

$string['validateonsave'] = 'Validate on save';

$string['wrongnumberofformats'] = 'Wrong number of test results column formats. Expected {$a->expected}, got {$a->got}';
$string['wsbadjson'] = 'Params and file parameters must be blank or a valid JSON record';
$string['wscputimeexcess'] = 'CPU time specified exceeds set maximum CPU time';
$string['wsdisabled'] = 'Sandbox web service disabled. Talk to a sysadmin';
$string['wsloggingenable'] = 'Log sandbox web service usage';
$string['wsloggingenable_desc'] = 'If this option is checked, every code execution via the sandbox web service will be logged. This option must be enabled if user rate throttling is to work.';
$string['wsmaxcputime'] = 'Max CPU time (secs)';
$string['wsmaxcputime_desc'] = 'Limits the maximum CPU time that a web service job can use, even if it explicitly sets the CPU time sandbox parameter.';
$string['wsmaxhourlyrate'] = 'Max hourly rate of submissions';
$string['wsmaxhourlyrate_desc'] = 'If a user attempts to exceed this rate of submissions in any given hour their submissions will be disallowed. 0 for no rate throttling.';
$string['wsnoaccess'] = 'Only logged-in non-guest users can access this functionality';
$string['wsnolanguage'] = 'Language "{$a}" is not known';
$string['wssubmissionrateexceeded'] = 'You have exceeded the maximum hourly \'Try it!\' submission rate. Request denied.';

$string['xmlcoderunnerformaterror'] = 'XML format error in coderunner question';
