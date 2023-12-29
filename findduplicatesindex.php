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
 * This script provides an index allowing the user to select a particular course
 * in which to run the findduplicates.php script, which looks for duplicate
 * coderunner questions in a given courses.
 *
 * @package   qtype_coderunner
 * @copyright 2018 and onwards Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Login and check permissions.
$context = context_system::instance();
require_login();

$PAGE->set_url('/question/type/coderunner/findduplicatesindex.php');
$PAGE->set_context($context);
$PAGE->set_title('Find duplicate questions');

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading('Courses containing CodeRunner questions');

// Find in which contexts the user can edit questions.
$questionsbycontext = $bulktester->get_num_coderunner_questions_by_context();
$availablequestionsbycontext = [];
foreach ($questionsbycontext as $contextid => $numcoderunnerquestions) {
    $context = context::instance_by_id($contextid);
    if (has_capability('moodle/question:editall', $context)) {
        $availablequestionsbycontext[$contextid] = $numcoderunnerquestions;
    }
}

// List all course contexts available to the user.
if (count($availablequestionsbycontext) == 0) {
    echo html_writer::tag('p', 'Unauthorised');
} else {
    echo html_writer::start_tag('ul');
    $buttonstyle = 'border: 1px solid gray; padding: 2px 2px 0px 2px;';
    $buttonstyle = 'border: 1px solid #F0F0F0; background-color: #FFFFC0; padding: 2px 2px 0px 2px;border: 4px solid white';
    foreach ($availablequestionsbycontext as $contextid => $numcoderunnerquestions) {
        $context = context::instance_by_id($contextid);
        $name = $context->get_context_name(true, true);
        if (strpos($name, 'Course:') === 0) {
            $class = 'bulktest coderunner context quiz';
            $findduplicatesurl = new moodle_url('/question/type/coderunner/findduplicates.php', ['contextid' => $contextid]);
            $findduplicateslink = html_writer::link(
                $findduplicatesurl,
                'Find duplicates',
                ['title' => 'Find all duplicates in this context',
                'style' => $buttonstyle]
            );
            $litext = $name . ' (' . $numcoderunnerquestions . ') ' . $findduplicateslink;
            echo html_writer::start_tag('li', ['class' => $class]);
            echo $litext;
        }
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();
