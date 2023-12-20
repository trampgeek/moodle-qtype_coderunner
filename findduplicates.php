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
 * Find all questions whose question text is exactly duplicated.
 *
 * This script checks all CodeRunner questions in a given context and
 * prints a list of all exact duplicates. Only the question text itself is
 * checked for equality.
 *
 * @package   qtype_coderunner
 * @copyright 2018 and beyond Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Get the parameters from the URL.
$contextid = required_param('contextid', PARAM_INT);

// Login and check permissions.
$context = context::instance_by_id($contextid);
require_login();
require_capability('moodle/question:editall', $context);
$PAGE->set_url('/question/type/coderunner/findduplicates.php', ['contextid' => $context->id]);
$PAGE->set_context($context);
$title = 'Duplicated CodeRunner questions';
$PAGE->set_title($title);

if ($context->contextlevel == CONTEXT_MODULE) {
    // Calling $PAGE->set_context should be enough, but it seems that it is not.
    // Therefore, we get the right $cm and $course, and set things up ourselves.
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $PAGE->set_cm($cm, $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST));
}

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Release the session, so the user can do other things while this runs.
\core\session\manager::write_close();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

echo "<table class='table table-bordered table-striped'>\n";
echo "<tr><th>Q1 name</th><th>Q1 Category</th><th>Q2 name</th><th>Q2 category</th></tr>\n";
// Find all the duplicates.
$allquestionsmap = $bulktester->get_all_coderunner_questions_in_context($contextid);
$allquestions = array_values($allquestionsmap);
$numduplicates = 0;
for ($i = 0; $i < count($allquestions); $i++) {
    $q1 = $allquestions[$i];
    $q1text = $q1->questiontext;
    for ($j = $i + 1; $j < count($allquestions); $j++) {
        $q2 = $allquestions[$j];
        $q2text = $q2->questiontext;
        if ($q1text === $q2text) {
            echo("<tr><td>{$q1->name}</td><td>{$q1->categoryname}</td><td>{$q2->name}<td>{$q2->categoryname}</td></tr>\n");
            $numduplicates++;
        }
    }
}
echo "</table>";
echo "<p>$numduplicates duplicated questions found</p>";
echo $OUTPUT->footer();
