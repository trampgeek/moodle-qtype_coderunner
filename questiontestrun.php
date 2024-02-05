<?php
// This file is part of CodeRunner - http://coderunner.org.nz
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
 * This script lets the user test a question using a sample answer supplied
 * in the question (if there is one).
 * It is based on the file of the same name in the qtype_stack plugin.
 *
 * Users with moodle/question:view capability can use this script to view the
 * results of the tests.
 *
 * The script takes one parameter id which is a questionid as a parameter.
 * Only the latest version of the given question is tested.
 *
 * @package    qtype_coderunner
 * @copyright  2012 the Open University, 2016 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/questionlib.php');
use qtype_coderunner\display_options;

// Get the parameters from the URL.
$questionid = required_param('questionid', PARAM_INT);

// Load the necessary data.
$qbe = get_question_bank_entry($questionid);
$question = question_bank::load_question($questionid);

// Setup the context to whatever was specified by the tester.
$urlparams = ['questionid' => $questionid];
if ($cmid = optional_param('cmid', 0, PARAM_INT)) {
    $cm = get_coursemodule_from_id(false, $cmid);
    require_login($cm->course, false, $cm);
    $context = context_module::instance($cmid);
    $urlparams['cmid'] = $cmid;
} else if ($courseid = optional_param('courseid', 0, PARAM_INT)) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    $urlparams['courseid'] = $courseid;
} else {
    throw new coding_exception('Missing context');
}

// Check permissions.
question_require_capability_on($question, 'view');
$canedit = question_has_capability_on($question, 'edit');

// Initialise $PAGE.
$PAGE->set_url('/question/type/coderunner/questiontestrun.php', $urlparams);
$title = get_string('testingquestion', 'qtype_coderunner', format_string($question->name));
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('popup');

// Create some other useful links.
$qbankparams = $urlparams;
unset($qbankparams['questionid']);
unset($qbankparams['seed']);
$qbankparams['qperpage'] = 1000; // Should match MAXIMUM_QUESTIONS_PER_PAGE but that constant is not easily accessible.
$qbankparams['category'] = $qbe->questioncategoryid . ',' . $question->contextid;
$qbankparams['lastchanged'] = $questionid;

$questionbanklink = new moodle_url('/question/edit.php', $qbankparams);
$exporttoxmlparams = $urlparams;
unset($exporttoxmlparams['questionid']);
$exporttoxmlparams['id'] = $questionid;
$exportquestionlink = new moodle_url('/question/bank/exporttoxml/exportone.php', $exporttoxmlparams);
$exportquestionlink->param('sesskey', sesskey());

// Create the question usage we will use.

$quba = question_engine::make_questions_usage_by_activity('qtype_coderunner', $context);
$quba->set_preferred_behaviour('adaptive');

$slot = $quba->add_question($question, $question->defaultmark);
$quba->start_question($slot);

// Prepare the display options.
$options = new display_options();
$options->readonly = true;
$options->flags = question_display_options::HIDDEN;
$options->suppressruntestslink = true;

// Test the question with its sample answer.
$response = $question->get_correct_response();
$runparams = ['-submit' => 'Submit', 'answer' => $response['answer']];
if (isset($response['attachments'])) {
    $runparams['attachments'] = $response['attachments'];
}
$templateparams = isset($question->templateparams) ? json_decode($question->templateparams, true) : [];
if (isset($templateparams['answer_language'])) {
    $runparams['language'] = $templateparams['answer_language'];
}
$quba->process_action($slot, $runparams);

// Start output.
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('qtype_coderunner');

// Display the question.
echo $OUTPUT->heading(get_string('questionpreview', 'qtype_coderunner'), 3);

echo html_writer::tag('p', html_writer::link(
    $questionbanklink,
    get_string('seethisquestioninthequestionbank', 'qtype_coderunner')
));

if ($canedit) {
    echo html_writer::tag(
        'p',
        html_writer::link($exportquestionlink, get_string('exportthisquestion', 'qtype_coderunner')) .
        $OUTPUT->help_icon('exportthisquestion', 'qtype_coderunner')
    );
}

echo $quba->render_question($slot, $options);

// Finish output.
echo $OUTPUT->footer();
