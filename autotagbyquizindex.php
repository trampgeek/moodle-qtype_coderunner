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
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script provides a top-level index for autotagging questions in bulk.
 * This version tags all questions in a selected quiz. A different file
 * (autotagbycategoryindex.php) handles autotagging of all questions in a
 * given category.
 * Both scripts are derived from bulktestindex.php, which provides bulk testing of questions.
 *
 * @package   qtype_coderunner
 * @copyright 2018 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Login and check permissions.
$context = context_system::instance();
require_login();

$PAGE->set_url('/question/type/coderunner/autotaggerindex.php');
$PAGE->set_context($context);
$PAGE->set_title('Autotagging by quiz');

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading('Autotagging questions in a selected quiz');

// Find in which contexts the user can edit questions.
$questionsbycontext = $bulktester->get_num_coderunner_questions_by_context();
$availablequestionsbycontext = array();
foreach ($questionsbycontext as $contextid => $numcoderunnerquestions) {
    $context = context::instance_by_id($contextid);
    if (has_capability('moodle/question:editall', $context)) {
        $availablequestionsbycontext[$contextid] = $numcoderunnerquestions;
    }
}

// List all contexts available to the user.
// Within each context, list the quizzes.
if (count($availablequestionsbycontext) == 0) {
    echo html_writer::tag('p', get_string('unauthoriseddbaccess', 'qtype_coderunner'));
} else {
    echo html_writer::start_tag('ul');
    foreach ($availablequestionsbycontext as $contextid => $numcoderunnerquestions) {
        $context = context::instance_by_id($contextid);
        $name = $context->get_context_name(true, true);
        if (strpos($name, 'Course:') === false) {
            continue;
        }
        $class = 'autotag coderunner context course';

        echo html_writer::tag('li',
                $name . " ContextId $contextid" . ' (' . $numcoderunnerquestions . ')', array('class' => $class));
        $quizzes = $bulktester->get_quizzes_for_context($contextid);
        echo html_writer::start_tag('ul');
        $keyedquizzes = array();
        foreach ($quizzes as $quizid => $row) {
            if ($row->count > 0) {  // Check at least 1 question in category.
                $keyedquizzes[$row->name] = array($quizid, $row->count);
            }
        }
        ksort($keyedquizzes);
        foreach ($keyedquizzes as $name => $idandcount) {
            echo html_writer::tag('li', html_writer::link(
                    new moodle_url('/question/type/coderunner/autotagbyquiz.php',
                            array('contextid' => $contextid,
                                  'quizid' => $idandcount[0],
                                  'quizname' => $name)),
                    $name . ' (' . $idandcount[1] . ')',
                    array('target' => '_blank')));
        }
        echo html_writer::end_tag('ul');
    }

    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();