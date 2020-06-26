<?php
// This file is part of CodeRunner - http://coderunner.org.nz/.
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
 * Script to download the export of a single CodeRunner question. It is copied
 * from the stack question type plugin, with relatively trivial changes.
 *
 * @copyright 2015 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../config.php');

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

// Get the parameters from the URL.
$questionid = required_param('questionid', PARAM_INT);

// Load the necessary data.
$questiondata = $DB->get_record('question', array('id' => $questionid), '*', MUST_EXIST);
get_question_options($questiondata);
$question = question_bank::load_question($questionid);

// Process any other URL parameters, and do require_login.
//
// Setup the context to whatever was specified by the tester.
// TODO - this appears also in questiontestrun.php - pull it out.
$urlparams = array('questionid' => $question->id);
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
$contexts = new question_edit_contexts($context);

// Check permissions.
question_require_capability_on($questiondata, 'edit');
require_sesskey();

// Initialise $PAGE.
$nexturl = new moodle_url('/question/type/coderunner/questiontestrun.php', $urlparams);
$PAGE->set_url($nexturl); // Since this script always ends in a redirect.
$PAGE->set_heading($COURSE->fullname);
$PAGE->set_pagelayout('admin');

// Set up the export format.
$qformat = new qformat_xml();
$filename = question_default_export_filename($COURSE, $questiondata) .
        $qformat->export_file_extension();
$qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
$qformat->setCourse($COURSE);
$qformat->setQuestions(array($questiondata));
$qformat->setCattofile(false);
$qformat->setContexttofile(false);

// Do the export.
if (!$qformat->exportpreprocess()) {
    send_file_not_found();
}
if (!$content = $qformat->exportprocess(true)) {
    send_file_not_found();
}
send_file($content, $filename, 0, 0, true, true, $qformat->mime_type());
