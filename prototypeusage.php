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
 * This script displays an index of all courses to which the current user
 * as question:editall access, linking each course to a script that analyses
 * prototype usage within that course.
 *
 * @package   qtype_coderunner
 * @copyright 2017 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

// Get the parameters from the URL.
$courseid = required_param('courseid', PARAM_INT);
$coursename = required_param('coursename', PARAM_RAW);
$coursecontextid = required_param('contextid', PARAM_INT);

// Login and check permissions.
$context = context_system::instance();
require_login();

$PAGE->set_url('/question/type/coderunner/prototypeusage.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('prototypeusage', 'qtype_coderunner'));

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Start display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('prototypeusage', 'qtype_coderunner', $coursename));

$coursecontext = context::instance_by_id($coursecontextid);
if (!has_capability('moodle/question:editall', $coursecontext)) {
    echo html::tag('p', get_string('unauthorisedbulktest', 'qtype_coderunner'));
} else {
    $questions = $bulktester->get_all_coderunner_questions_in_context($coursecontextid, true);
    $prototypequestions = qtype_coderunner::get_all_prototypes($courseid);
    $prototypes = [];
    foreach ($prototypequestions as $prototype) {
        $prototypes[$prototype->coderunnertype] = $prototype;
    }

    // Analyse the prototype usage.

    $missing = [];
    foreach ($questions as $id => $question) {
        $type = $question->coderunnertype;
        if (!isset($prototypes[$type])) {
            if (isset($missing[$type])) {
                $missing[$type][] = $question;
            } else {
                $missing[$type] = [$question];
            }
        } else {
            if ($question->prototypetype != 0) {
                $prototypes[$type]->name = $question->name;
                $prototypes[$type]->category = $question->categoryname;
            }
            if (isset($prototypes[$type]->usages)) {
                $prototypes[$type]->usages[] = $question;
            } else {
                $prototypes[$type]->usages = [$question];
            }
        }
    }
    $bulktester->display_prototypes($courseid, $prototypes, $missing);
}

echo $OUTPUT->footer();
