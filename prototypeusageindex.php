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
 * Find all the uses of all the prototypes.
 *
 * This script scans all question categories to which the current user
 * has access and builds a table showing all available prototypes and
 * the questions using those prototypes.
 *
 * @package   qtype_coderunner
 * @copyright 2017 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Login and check permissions.
$context = context_system::instance();
require_login();

$PAGE->set_url('/question/type/coderunner/prototypeusageindex.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('prototypeusageindex', 'qtype_coderunner'));

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();
$allcourses = $bulktester->get_all_courses();

// Start display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('prototypeusageindex', 'qtype_coderunner'));

echo html_writer::start_tag('ul');
foreach ($allcourses as $course) {
    $courseid = $course->id;
    $coursecontext = context_course::instance($courseid);
    if (!has_capability('moodle/grade:viewall', $coursecontext)) {
        continue;
    }
    $contextid = $course->contextid;
    $context = context::instance_by_id($contextid);
    echo html_writer::tag(
        'li',
        html_writer::link(
            new moodle_url(
                '/question/type/coderunner/prototypeusage.php',
                ['courseid' => $course->id,
                      'contextid' => $contextid,
                'coursename' => $course->name]
            ),
            $course->name
        )
    );
}

echo html_writer::end_tag('ul');

echo $OUTPUT->footer();
