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
 * Report on the health of the prototypes visible from a course.
 *
 * When called without parameters this script lists the courses the user may
 * check. With a courseid it reports on that course; with allcourses=1 it
 * reports on every course, which requires the management capability at site
 * level. The report is read-only.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context_course;
use context_system;
use html_writer;
use moodle_url;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Display one table of problem rows.
 *
 * @param string $heading the already-formatted section heading.
 * @param string $intro the explanatory paragraph for the section.
 * @param array $rows the rows to show.
 * @param array $columns column header strings, keyed by row property name.
 * @param bool $showcandidates true to add a column listing competing prototypes.
 */
function display_problem_table($heading, $intro, array $rows, array $columns, $showcandidates = false) {
    global $OUTPUT;

    if (empty($rows)) {
        return;
    }
    echo $OUTPUT->heading($heading, 4);
    echo html_writer::tag('p', $intro, ['class' => 'text-muted']);

    $headers = array_values($columns);
    if ($showcandidates) {
        $headers[] = get_string('prototypehealth_candidates', 'qtype_coderunner');
    }
    echo html_writer::start_tag('table', ['class' => 'table table-bordered table-striped']);
    echo html_writer::start_tag('tr');
    foreach ($headers as $header) {
        echo html_writer::tag('th', $header);
    }
    echo html_writer::end_tag('tr');

    foreach ($rows as $row) {
        echo html_writer::start_tag('tr');
        foreach (array_keys($columns) as $property) {
            echo html_writer::tag('td', s($row->$property ?? ''));
        }
        if ($showcandidates) {
            $names = [];
            foreach ($row->candidates ?? [] as $candidate) {
                $location = trim(($candidate->bankname ?? '') . ' / ' . $candidate->categoryname, ' /');
                $names[] = $candidate->name . ' (' . $location . ')';
            }
            echo html_writer::tag('td', s(implode('; ', $names)));
        }
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('table');
}

/**
 * Report on one course.
 *
 * @param int $courseid the course to report on.
 * @param string $coursename the course name, for the heading.
 * @param int $versions prototype_health::LATEST_VERSIONS or ALL_VERSIONS.
 */
function display_course_report($courseid, $coursename, $versions) {
    global $OUTPUT;

    $result = prototype_health::analyse($courseid, $versions);

    echo $OUTPUT->heading(s($coursename), 3);
    $summarykey = $result->allversions ? 'prototypehealth_summary_all' : 'prototypehealth_summary_latest';
    echo html_writer::tag('p', get_string($summarykey, 'qtype_coderunner', $result));

    // Which rules decided the result, since the same course reads differently
    // under each and the reader cannot otherwise tell which they are seeing.
    $manager = $result->mode == prototype_health::MODE_MANAGER;
    $modekey = $manager ? 'prototypehealth_mode_manager' : 'prototypehealth_mode_legacy';
    // The reason a question has no prototype differs by mode, and so does what
    // to do about it, so the explanation cannot be shared.
    $missingintrokey = $manager ? 'prototypehealth_missing_intro_manager' : 'prototypehealth_missing_intro';
    echo html_writer::tag('p', get_string($modekey, 'qtype_coderunner'), ['class' => 'text-muted']);

    if ($result->numquestions == 0) {
        echo html_writer::tag('p', get_string('prototypehealth_noquestions', 'qtype_coderunner'));
        return;
    }

    $questioncolumns = [
        'name' => get_string('prototypehealth_questionname', 'qtype_coderunner'),
        'coderunnertype' => get_string('prototypehealth_type', 'qtype_coderunner'),
        'bankname' => get_string('prototypehealth_context', 'qtype_coderunner'),
        'categoryname' => get_string('prototypehealth_category', 'qtype_coderunner'),
        'version' => get_string('prototypehealth_version', 'qtype_coderunner'),
    ];
    $prototypecolumns = [
        'coderunnertype' => get_string('prototypehealth_prototypename', 'qtype_coderunner'),
        'name' => get_string('prototypehealth_questionname', 'qtype_coderunner'),
        'bankname' => get_string('prototypehealth_context', 'qtype_coderunner'),
        'categoryname' => get_string('prototypehealth_category', 'qtype_coderunner'),
    ];

    display_problem_table(
        get_string('prototypehealth_missing_heading', 'qtype_coderunner', count($result->missing)),
        get_string($missingintrokey, 'qtype_coderunner'),
        $result->missing,
        $questioncolumns
    );
    // Shown before the sections a teacher has to act on, because one task run
    // clears the whole list and may clear some of those too.
    display_problem_table(
        get_string('prototypehealth_unconverted_heading', 'qtype_coderunner', count($result->unconverted ?? [])),
        get_string('prototypehealth_unconverted_intro', 'qtype_coderunner'),
        $result->unconverted ?? [],
        $questioncolumns
    );
    display_problem_table(
        get_string('prototypehealth_ambiguous_heading', 'qtype_coderunner', count($result->ambiguous)),
        get_string('prototypehealth_ambiguous_intro', 'qtype_coderunner'),
        $result->ambiguous,
        $questioncolumns,
        true
    );
    display_problem_table(
        get_string('prototypehealth_duplicates_heading', 'qtype_coderunner', count($result->duplicates)),
        get_string('prototypehealth_duplicates_intro', 'qtype_coderunner'),
        $result->duplicates,
        [
            'coderunnertype' => get_string('prototypehealth_prototypename', 'qtype_coderunner'),
            'count' => get_string('prototypehealth_duplicatecount', 'qtype_coderunner'),
        ],
        true
    );
    display_problem_table(
        get_string('prototypehealth_shadowed_heading', 'qtype_coderunner', count($result->shadowed)),
        get_string('prototypehealth_shadowed_intro', 'qtype_coderunner'),
        $result->shadowed,
        $prototypecolumns
    );
    display_problem_table(
        get_string('prototypehealth_strandedextra_heading', 'qtype_coderunner', count($result->strandedextra)),
        get_string('prototypehealth_strandedextra_intro', 'qtype_coderunner'),
        $result->strandedextra,
        $prototypecolumns
    );
    display_problem_table(
        get_string('prototypehealth_legacyui_heading', 'qtype_coderunner', count($result->legacyuiparams)),
        get_string('prototypehealth_legacyui_intro', 'qtype_coderunner'),
        $result->legacyuiparams,
        $questioncolumns + ['clashingparams' => get_string('prototypehealth_clashingparams', 'qtype_coderunner')]
    );
    display_problem_table(
        get_string('prototypehealth_prototypeui_heading', 'qtype_coderunner', count($result->prototypeuiparams)),
        get_string('prototypehealth_prototypeui_intro', 'qtype_coderunner'),
        $result->prototypeuiparams,
        $prototypecolumns + ['clashingparams' => get_string('prototypehealth_clashingparams', 'qtype_coderunner')]
    );

    if ($result->uncheckableparams) {
        echo $OUTPUT->notification(
            get_string('prototypehealth_uncheckable', 'qtype_coderunner', count($result->uncheckableparams)),
            'notifymessage'
        );
    }

    if (!prototype_health::has_problems($result)) {
        if ($result->olderproblems) {
            // Clean in what was checked, but not clean overall. Saying only
            // "no problems found" here would be misleading.
            echo $OUTPUT->notification(
                get_string('prototypehealth_olderonly', 'qtype_coderunner', $result->olderproblems),
                'notifywarning'
            );
        } else {
            echo $OUTPUT->notification(
                get_string('prototypehealth_clean', 'qtype_coderunner'),
                'notifysuccess'
            );
        }
    } else if ($result->olderproblems) {
        echo $OUTPUT->notification(
            get_string('prototypehealth_olderwarning', 'qtype_coderunner', $result->olderproblems),
            'notifywarning'
        );
    }
}

// Get optional parameters.
$courseid = optional_param('courseid', 0, PARAM_INT);
$allcourses = optional_param('allcourses', 0, PARAM_BOOL);
$allversions = optional_param('allversions', 0, PARAM_BOOL);
$versions = $allversions ? prototype_health::ALL_VERSIONS : prototype_health::LATEST_VERSIONS;

require_login();

if ($courseid) {
    // Worker mode: report on one course.
    $context = context_course::instance($courseid);
    require_capability('qtype/coderunner:management', $context);
    $course = get_course($courseid);

    $PAGE->set_url('/question/type/coderunner/scripts/prototypehealth.php', ['courseid' => $courseid]);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('prototypehealth_title', 'qtype_coderunner', $course->shortname));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('prototypehealth_index_heading', 'qtype_coderunner'));
    echo html_writer::tag(
        'p',
        get_string(
            $allversions ? 'prototypehealth_viewing_all' : 'prototypehealth_viewing_latest',
            'qtype_coderunner'
        ) . ' ' . html_writer::link(
            new moodle_url('/question/type/coderunner/scripts/prototypehealth.php', [
                'courseid' => $courseid,
                'allversions' => $allversions ? 0 : 1,
            ]),
            get_string(
                $allversions ? 'prototypehealth_showlatestonly' : 'prototypehealth_showolder',
                'qtype_coderunner'
            ),
            ['class' => 'btn btn-secondary btn-sm ms-2']
        )
    );
    display_course_report($courseid, $course->fullname, $versions);
} else if ($allcourses) {
    // Whole-site mode: needs the capability at site level.
    $context = context_system::instance();
    require_capability('qtype/coderunner:management', $context);

    $PAGE->set_url('/question/type/coderunner/scripts/prototypehealth.php', ['allcourses' => 1]);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('prototypehealth_index_heading', 'qtype_coderunner'));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('prototypehealth_index_heading', 'qtype_coderunner'));

    $anyproblems = false;
    foreach (bulk_tester::get_all_courses() as $course) {
        $result = prototype_health::analyse($course->id, $versions);
        if ($result->numquestions == 0) {
            continue;
        }
        if (prototype_health::has_problems($result) || $result->olderproblems) {
            $anyproblems = true;
            display_course_report($course->id, $course->name, $versions);
        }
        flush();
    }
    if (!$anyproblems) {
        echo $OUTPUT->notification(
            get_string('prototypehealth_clean', 'qtype_coderunner'),
            'notifysuccess'
        );
    }
} else {
    // Index mode: list the courses the user may check.
    $context = context_system::instance();
    $PAGE->set_url('/question/type/coderunner/scripts/prototypehealth.php');
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('prototypehealth_index_heading', 'qtype_coderunner'));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('prototypehealth_index_heading', 'qtype_coderunner'));
    echo html_writer::tag('p', get_string('prototypehealth_index_intro', 'qtype_coderunner'));

    if (has_capability('qtype/coderunner:management', $context)) {
        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/question/type/coderunner/scripts/prototypehealth.php', ['allcourses' => 1]),
            get_string('prototypehealth_runallcourses', 'qtype_coderunner'),
            ['class' => 'btn btn-secondary btn-sm']
        ) . ' ' . html_writer::tag(
            'span',
            get_string('prototypehealth_runallcourses_intro', 'qtype_coderunner'),
            ['class' => 'text-muted']
        ));
    }

    echo html_writer::start_tag('ul');
    foreach (bulk_tester::get_all_courses() as $course) {
        $coursecontext = context_course::instance($course->id);
        if (!has_capability('qtype/coderunner:management', $coursecontext)) {
            continue;
        }
        $url = new moodle_url(
            '/question/type/coderunner/scripts/prototypehealth.php',
            ['courseid' => $course->id]
        );
        echo html_writer::tag('li', html_writer::link($url, s($course->name)));
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();
