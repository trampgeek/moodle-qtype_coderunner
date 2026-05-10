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
 * When called without parameters, this script displays an index of all
 * courses/contexts accessible to the current user, linking each to a
 * detailed prototype-usage view. When called with courseid, contextid and
 * name parameters it scans that context and builds a table showing all
 * available prototypes and the questions using those prototypes.
 *
 * @package   qtype_coderunner
 * @copyright 2017 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context;
use context_system;
use context_course;
use html_writer;
use moodle_url;
use qtype_coderunner;
use qtype_coderunner_util;
use qtype_coderunner_bulk_tester;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/../classes/bulk_tester.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

const BUTTONSTYLE = 'background-color: #FFFFD0; padding: 2px 2px 0px 2px;border: 4px solid white';

function display_course_header($coursecontextid, $coursename) {
    $litext = $coursecontextid . ' - ' . $coursename;
    echo html_writer::tag('h3', $litext);
}

function display_context_link($courseid, $name, $contextid, $numcoderunnerquestions) {

    $contexturl = new moodle_url(
        '/question/type/coderunner/scripts/prototypeusage.php',
        ['courseid' => $courseid, 'name' => $name, 'contextid' => $contextid]
    );
    $contextdescription = $contextid . ' - ' . $name . ' (' . $numcoderunnerquestions . ') ';

    $contextlink = html_writer::link(
        $contexturl,
        $contextdescription,
        ['style' => BUTTONSTYLE . ';cursor:pointer;text-decoration:none;']
    );

    if (strpos($name, ": Quiz: ") === false) {
        $class = 'questionbrowser coderunner context normal';
    } else {
        $class = 'questionbrowser coderunner context quiz';
    }

    echo html_writer::start_tag('li', ['class' => $class]);
    echo $contextlink;
    echo html_writer::end_tag('li');
}

// Get optional parameters.
$courseid = optional_param('courseid', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);
$name = optional_param('name', '', PARAM_RAW);

// Login and check permissions.
$context = context_system::instance();
require_login();

if ($courseid && $contextid) {
    // Worker mode: display prototype usage for the selected context.
    $PAGE->set_url(
        '/question/type/coderunner/scripts/prototypeusage.php',
        ['courseid' => $courseid, 'contextid' => $contextid, 'name' => $name]
    );
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('prototypeusage', 'qtype_coderunner'));

    $bulktester = new bulk_tester();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('prototypeusage', 'qtype_coderunner', $name));

    $coursecontext = context::instance_by_id($contextid);
    if (!has_capability('moodle/question:editall', $coursecontext)) {
        echo html_writer::tag('p', get_string('unauthorisedbulktest', 'qtype_coderunner'));
    } else {
        $questions = $bulktester->get_all_coderunner_questions_in_context($contextid, true);
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
} else {
    // Index mode: display list of available courses/contexts.
    $PAGE->set_url('/question/type/coderunner/scripts/prototypeusage.php');
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('prototypeusageindex', 'qtype_coderunner'));

    $oldskool = !(qtype_coderunner_util::using_mod_qbank());
    $availablequestionsbycontext = bulk_tester::get_num_available_coderunner_questions_by_context();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('prototypeusageindex', 'qtype_coderunner'));

    if (count($availablequestionsbycontext) == 0) {
        echo html_writer::tag('p', 'You do not have permission to browse questions in any contexts.');
    } else {
        if ($oldskool) {
            // Moodle 4 style.
            echo html_writer::tag(
                'p',
                '<strong>Instructions:</strong> Click the link to the course of interest'
            );
            $allcourses = bulk_tester::get_all_courses();
            echo html_writer::start_tag('ul');
            foreach ($allcourses as $course) {
                $courseid = $course->id;
                $contextid = $course->contextid;
                $coursecontext = context_course::instance($courseid);
                $hasviewall = has_capability('moodle/grade:viewall', $coursecontext);
                if (!$hasviewall || !array_key_exists($contextid, $availablequestionsbycontext)) {
                    continue;
                }
                $contextdata = $availablequestionsbycontext[$contextid];
                $numquestions = $contextdata['numquestions'];
                display_context_link($courseid, $course->name, $contextid, $numquestions);
            }
            echo html_writer::end_tag('ul');
        } else {
            // Deal with funky question bank madness in Moodle 5.0.
            echo html_writer::tag('p', 'Moodle >= 5.0 detected. Listing by course then question bank.');
            echo html_writer::tag('p', '<strong>Instructions:</strong> Click the link to the context of interest');
            $allcourses = bulk_tester::get_all_courses();
            foreach ($allcourses as $courseid => $course) {
                $coursecontext = context_course::instance($courseid);
                $allbanks = bulk_tester::get_all_qbanks_for_course($courseid);
                $headerdisplayed = false;
                if (count($allbanks) > 0) {
                    echo html_writer::start_tag('ul');
                    foreach ($allbanks as $bank) {
                        $contextid = $bank->contextid ?? $bank->cminfo->context->id;  // Need difft code for Moodle 5.2+.
                        if (array_key_exists($contextid, $availablequestionsbycontext)) {
                            if (!$headerdisplayed) {
                                display_course_header($coursecontext->id, $course->name);
                                $headerdisplayed = true;
                            }
                            $contextdata = $availablequestionsbycontext[$contextid];
                            $name = $contextdata['name'];
                            $numquestions = $contextdata['numquestions'];
                            display_context_link($courseid, $name, $contextid, $numquestions);
                        }
                    }
                    echo html_writer::end_tag('ul');
                }
            }
        }
    }
}

echo $OUTPUT->footer();
