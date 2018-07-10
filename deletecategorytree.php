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
 * This script deletes all categories in the category tree rooted at the
 * categoryid passed as a parameter, provided all nodes in that tree are
 * empty.
 *
 *
 * @package   qtype_coderunner
 * @copyright 2018 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

function display_and_count($catid, $level) {
    global $bulktester;
    $count = $bulktester->count_questions_in_category($catid);
    $s = '';
    for ($i = 0; $i < $level; $i++) {
        $s .= '    ';
    }
    $s .= $bulktester->category_name($catid) . " ($count questions)";
    echo html_writer::tag('pre', $s);
    $children = $bulktester->child_categories($catid);
    foreach ($children as $child) {
        $count += display_and_count($child, $level + 1);
    }
    return $count;
}

// Delete all categories rooted at the given category id.
// The tree must be empty of questions.
function delete_tree($categoryid) {
    global $bulktester;
    $children = $bulktester->child_categories($categoryid);
    foreach ($children as $child) {
        delete_tree($child);
    }
    $bulktester->delete_category($categoryid);
}

// Get the parameters from the URL.
$contextid = required_param('contextid', PARAM_INT);
$categoryid = required_param('categoryid', PARAM_INT);
$categoryname = required_param('categoryname', PARAM_RAW);

// Login and check permissions.
require_login();
$context = context::instance_by_id($contextid);
require_capability('moodle/question:managecategory', $context);

$PAGE->set_url('/question/type/coderunner/deletecategorytree.php');
$PAGE->set_context($context);
$PAGE->set_title('Delete category tree');

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading('Deleting Category Tree');

echo html_writer::tag('h3', 'Preparing to delete the following tree');
$num_questions = display_and_count($categoryid, 0);
if ($num_questions != 0) {
    echo html_writer::tag('p', "Non-empty category tree ($num_questions questions). No deletions performed");
} else {
    delete_tree($categoryid);
    echo html_writer::tag('p', "All the above categories have now been deleted");
}


echo $OUTPUT->footer();