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
 * This script allows a suitably-authorised user to delete all categories
 * in a selected question category tree, provided all those categories
 * are empty.
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

$PAGE->set_url('/question/type/coderunner/deletecategorytreeindex.php');
$PAGE->set_context($context);
$PAGE->set_title('Delete category tree');

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Display.
echo $OUTPUT->header();
echo html_writer::tag('h2', 'DANGER! EXPERIMENTAL SCRIPT. USE AT YOUR OWN RISK!');
echo $OUTPUT->heading('Question Categories');
echo html_writer::tag('p', 'Click a link to delete the entire question category tree'
        . ' rooted at that point. If the tree contains any questions the deletion '
        . 'will not take place.');

// Find in which contexts the user can manage categories.
$courses = $bulktester->get_all_courses();
$availablecontexids = array();
foreach ($courses as $course) {
    $context = context::instance_by_id($course->contextid);
    if (has_capability('moodle/question:managecategory', $context)) {
        $availablecontextids[] = $course->contextid;
    }
}


// List all contexts available to the user.
// Within each context, list the question categories.
if (count($availablecontextids) == 0) {
    echo html_writer::tag('p', 'Unauthorised DB access');
} else {
    echo html_writer::start_tag('ul');
    foreach ($availablecontextids as $contextid) {
        $context = context::instance_by_id($contextid);
        $name = $context->get_context_name(true, true);
        $class = 'context course';

        echo html_writer::tag('li',
                $name . " ContextId $contextid", array('class' => $class));
        $categories = $bulktester->get_all_categories_for_context($contextid);
        echo html_writer::start_tag('ul');
        $keyedcategories = array();
        foreach ($categories as $catid => $row) {
            $categorypath = $bulktester->category_path($catid);
            $keyedcategories[$categorypath] = array($catid, $row->count);
        }
        ksort($keyedcategories);
        foreach ($keyedcategories as $path => $idandcount) {
            echo html_writer::tag('li', html_writer::link(
                    new moodle_url('/question/type/coderunner/deletecategorytree.php',
                            array('contextid' => $contextid,
                                  'categoryid' => $idandcount[0],
                                  'categoryname' => $path)),
                    $path . ' (' . $idandcount[1] . ')',
                    array('target' => '_blank')));
        }
        echo html_writer::end_tag('ul');
    }

    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();