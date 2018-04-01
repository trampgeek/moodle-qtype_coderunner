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
 * This script, which takes mandatory contextid, categoryid and tag parameters
 * adds the given tag to all CodeRunner questions in the given
 * category.
 * If tag is not supplied, a form is presented to the user who must then enter
 * a tag and the form then posts back to this script.
 *
 *
 * @package   qtype_coderunner
 * @copyright 2018 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../../../tag/classes/tag.php');
require_once($CFG->libdir . '/questionlib.php');

// Get the parameters from the URL.
$contextid = required_param('contextid', PARAM_INT);
$categoryid = required_param('categoryid', PARAM_INT);
$categoryname = required_param('categoryname', PARAM_RAW);
$tag = optional_param('tag', '', PARAM_RAW);

// Login and check permissions.
$context = context::instance_by_id($contextid);
require_login();
require_capability('moodle/question:editall', $context);
$thisurl = '/question/type/coderunner/autotagbycategory.php';
$PAGE->set_url($thisurl);
$PAGE->set_context($context);
$title = get_string('autotagbycategorytitle', 'qtype_coderunner');
$PAGE->set_title($title);

if ($context->contextlevel == CONTEXT_MODULE) {
    // Calling $PAGE->set_context should be enough, but it seems that it is not.
    // Therefore, we get the right $cm and $course, and set things up ourselves.
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $PAGE->set_cm($cm, $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST));
}

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Release the session, so the user can do other things while this runs.
\core\session\manager::write_close();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo "<p>Tagging all questions in category <em>$categoryname</em></p>\n";

if (empty($tag)) {
    $fullurl = $_SERVER['REQUEST_URI'];
    echo "<form action=$fullurl method=POST>\n";
    echo "<input type='hidden' name='contextid' value='$contextid' />\n";
    echo "<input type='hidden' name='categoryid' value='$categoryid' \>\n";
    echo "<input type='hidden' name='categoryname' value='$categoryname' \>\n";
    echo "<label for='tagvalue'>Tag to add to all questions in this category: </label>\n";
    echo "<input id='tagvalue' type='text' name='tag' />";
    echo "<br><input type='submit' name='submit' value='Submit'/>";
} else {
    // Do the tagging.
    $tag = str_replace(',', '', $tag);
    $bulktester->tag_by_category($contextid, $categoryid, $tag);

    // Display the final summary.
    echo "Tagging done";
}
echo $OUTPUT->footer();
