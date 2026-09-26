<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
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
 * Student time-on-task estimates for a given course or activity.
 *
 * Reads the Moodle logstore_standard_log table directly, discards autosave
 * events, and computes per-student hit counts and total time spent, using a
 * configurable idle-gap threshold to detect session boundaries.  In the same
 * pass it accumulates per-day cohort totals for a time-series chart.
 *
 * Access is permitted to site admins and to any user holding a teacher,
 * editing teacher, manager, or course creator role in at least one course.
 * The course selector is filtered to show only courses the current user
 * may report on.
 *
 * Place this file inside  question/type/coderunner/scripts/  (alongside the other
 * admin scripts) and access it as:
 *   https://<your-moodle>/question/type/coderunner/scripts/studenttimeanalysis.php
 *
 * @package   qtype_coderunner
 * @copyright  2024 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/lib/grouplib.php');

// -------------------------------------------------------------------------
// Access control.
require_login();

$systemcontext = context_system::instance();
$issiteadmin   = has_capability('moodle/site:config', $systemcontext);

// Role shortnames whose holders may run this report.
$staffrolenames = ['manager', 'coursecreator', 'editingteacher', 'teacher'];

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/question/type/coderunner/scripts/studenttimeanalysis.php'));
$PAGE->set_title('CodeRunner: Student time-on-task analysis');
$PAGE->set_heading('CodeRunner: Student time-on-task analysis');

// -------------------------------------------------------------------------
// Parameters.
$courseid     = optional_param('courseid', 0, PARAM_INT);
$cmid         = optional_param('cmid', 0, PARAM_INT);  // 0 = all activities
$groupid      = optional_param('groupid', 0, PARAM_INT);  // 0 = all groups
$studentid    = optional_param('studentid', 0, PARAM_INT);  // 0 = whole cohort
$startdatestr = optional_param('startdate', '', PARAM_TEXT);
$enddatestr   = optional_param('enddate', '', PARAM_TEXT);
$gapminutes   = optional_param('gapminutes', 30, PARAM_INT);
$download     = optional_param('download', 0, PARAM_INT);
$submitted    = optional_param('submitted', 0, PARAM_INT);  // 1 only when Analyse clicked

// Convert date strings to Unix timestamps (00:00:00 and 23:59:59 local time).
$starttime = 0;
$endtime   = PHP_INT_MAX;
$dateerror = '';

if ($startdatestr !== '') {
    $ts = strtotime($startdatestr);
    if ($ts === false) {
        $dateerror .= "Cannot parse start date '$startdatestr'. ";
    } else {
        $starttime = mktime(0, 0, 0, date('n', $ts), date('j', $ts), date('Y', $ts));
    }
}
if ($enddatestr !== '') {
    $ts = strtotime($enddatestr);
    if ($ts === false) {
        $dateerror .= "Cannot parse end date '$enddatestr'. ";
    } else {
        $endtime = mktime(23, 59, 59, date('n', $ts), date('j', $ts), date('Y', $ts));
    }
}

$gapseconds = max(1, $gapminutes) * 60;

// -------------------------------------------------------------------------
// Helper: courses the current user may report on.
/**
 * Returns an id => fullname map of courses the current user may analyse.
 * Site admins see all courses. Others see only courses where they hold one
 * of the permitted staff roles.
 */
function get_allowed_courses() {
    global $DB, $USER, $issiteadmin, $staffrolenames;

    if ($issiteadmin) {
        $courses = $DB->get_records_menu('course', null, 'fullname ASC', 'id,fullname');
        unset($courses[SITEID]);
        return $courses;
    }

    [$rolesql, $roleparams] = $DB->get_in_or_equal($staffrolenames, SQL_PARAMS_NAMED, 'role');
    $sql = "SELECT DISTINCT c.id, c.fullname
              FROM {course} c
              JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :ctxlevel
              JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = :userid
              JOIN {role} r ON r.id = ra.roleid AND r.shortname $rolesql
             WHERE c.id != :siteid
          ORDER BY c.fullname ASC";
    $params = array_merge(
        ['ctxlevel' => CONTEXT_COURSE, 'userid' => $USER->id, 'siteid' => SITEID],
        $roleparams
    );
    return $DB->get_records_sql_menu($sql, $params);
}

// -------------------------------------------------------------------------
// Helper: activities in a course for the filter dropdown.
/**
 * Returns a cmid => label map of all activities in the course, sorted by
 * section order then activity name, grouped by module type label.
 * The label is "Type: Name" so the dropdown is self-explanatory.
 *
 * @param int $courseid
 * @return array  cmid => "Type: Name"
 */
function get_course_activities($courseid) {
    global $DB;

    $sql = "SELECT cm.id AS cmid,
                   m.name AS modname,
                   cs.section AS sectionnum
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {course_sections} cs ON cs.id = cm.section
             WHERE cm.course   = :courseid
               AND cm.visible  = 1
               AND m.visible   = 1
               AND m.name      != 'label'
          ORDER BY cs.section, cm.id";

    // Fetch course modules with section ordering.
    $cms = $DB->get_records_sql($sql, ['courseid' => $courseid]);

    if (empty($cms)) {
        return [];
    }

    // Group cmids by module type so we can fetch instance names in bulk.
    $bymod = [];  // Maps modname => [cmid => record].
    foreach ($cms as $cm) {
        $bymod[$cm->modname][$cm->cmid] = $cm;
    }

    // Fetch instance names for each module type.
    $names = [];  // Maps cmid => activity name.
    foreach ($bymod as $modname => $modcms) {
        $cmids = array_keys($modcms);
        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        try {
            // Most activity tables have a 'name' column; a few (label) do not.
            $rows = $DB->get_records_sql(
                "SELECT cm.id AS cmid, act.name
                   FROM {{$modname}} act
                   JOIN {course_modules} cm ON cm.instance = act.id AND cm.module = (
                             SELECT id FROM {modules} WHERE name = :modname
                        )
                  WHERE cm.id $insql",
                array_merge(['modname' => $modname], $inparams)
            );
            foreach ($rows as $row) {
                $names[$row->cmid] = $row->name;
            }
        } catch (Exception $e) {
            // Module table has no 'name' column (e.g. label) — use module type name.
            foreach ($cmids as $id) {
                $names[$id] = ucfirst($modname);
            }
        }
    }

    // Build the list then sort alphabetically by label, excluding non-interactive types.
    $excludedprefixes = ['Folder:', 'Groupselect:', 'Url:', 'Glossary:', 'Page:', 'Resource:'];
    $options = [];
    foreach ($cms as $cm) {
        $label = ucfirst($cm->modname) . ': ' . ($names[$cm->cmid] ?? ucfirst($cm->modname));
        foreach ($excludedprefixes as $prefix) {
            if (strncmp($label, $prefix, strlen($prefix)) === 0) {
                continue 2;
            }
        }
        $options[$cm->cmid] = $label;
    }
    asort($options);

    return $options;
}

// -------------------------------------------------------------------------
// Helper: groups in a course for the filter dropdown.
/**
 * Returns a groupid => name map of all groups in the course.
 *
 * @param int $courseid
 * @return array  groupid => name
 */
function get_course_groups($courseid) {
    $groups = groups_get_all_groups($courseid);
    $options = [];
    foreach ($groups as $g) {
        $options[$g->id] = $g->name;
    }
    return $options;
}

// -------------------------------------------------------------------------
// Helper: students enrolled in a course, optionally scoped to a group.
/**
 * Returns a userid => "Lastname, Firstname" map of students in the course,
 * filtered to the given group if groupid is non-zero.
 *
 * @param int $courseid
 * @param int $groupid  0 = all students
 * @return array  userid => "Lastname, Firstname"
 */
function get_course_students($courseid, $groupid = 0) {
    global $DB;

    $groupsql    = '';
    $groupparams = [];
    if ($groupid) {
        $groupsql = 'AND ra.userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :groupid)';
        $groupparams['groupid'] = $groupid;
    }

    $sql = "SELECT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {role_assignments} ra  ON ra.userid    = u.id
              JOIN {role}             r   ON r.id         = ra.roleid AND r.shortname = 'student'
              JOIN {context}          ctx ON ctx.id       = ra.contextid
                                        AND ctx.contextlevel = :ctxlevel
                                        AND ctx.instanceid   = :courseid
             WHERE u.deleted = 0
               $groupsql
          ORDER BY u.lastname, u.firstname";

    $params = array_merge(
        ['ctxlevel' => CONTEXT_COURSE, 'courseid' => $courseid],
        $groupparams
    );

    $options = [];
    foreach ($DB->get_records_sql($sql, $params) as $u) {
        $options[$u->id] = $u->lastname . ', ' . $u->firstname;
    }
    return $options;
}

// -------------------------------------------------------------------------
// Core analysis function.
/**
 * Returns per-student stats and per-day cohort totals in a single pass.
 *
 * The recordset is iterated once, simultaneously accumulating:
 *   - per-student session totals (hits and total seconds)
 *   - per-day cohort totals (sum of intra-session gaps attributed to the day
 *     of the later event; these sum to exactly the cohort's total seconds)
 *
 * Return value is an associative array:
 *   'students'    => array of stdClass (userid, firstname, lastname, hits,
 *                    totalseconds), sorted by lastname then firstname
 *   'dailytotals' => array of date_string => total_cohort_seconds_that_day
 *
 * @param int   $courseid    Moodle course id
 * @param int   $cmid        Course-module id to filter on, or 0 for all
 * @param int   $groupid     Group id to filter on, or 0 for all groups
 * @param int   $starttime   Unix timestamp (inclusive lower bound)
 * @param int   $endtime     Unix timestamp (inclusive upper bound)
 * @param int   $gapseconds  Idle-gap threshold in seconds
 * @return array  ['students' => [...], 'dailytotals' => [...]]
 */
function analyse_course($courseid, $cmid, $groupid, $starttime, $endtime, $gapseconds, $studentid = 0) {
    global $DB;

    // Exclude autosave events.
    $excludedactions = ['autosaved', 'autosave'];
    [$notinsql, $notinparams] = $DB->get_in_or_equal(
        $excludedactions,
        SQL_PARAMS_NAMED,
        'excact',
        false
    );

    // Optional activity filter: restrict to the context of the chosen cm.
    $activitysql    = '';
    $activityparams = [];
    if ($cmid) {
        $activitysql = 'AND l.contextid = :activityctx';
        $activityparams['activityctx'] = context_module::instance($cmid)->id;
    }

    // Student filter takes priority over group filter.
    $groupsql    = '';
    $groupparams = [];
    if ($studentid) {
        $groupsql = 'AND l.userid = :filteruserid';
        $groupparams['filteruserid'] = $studentid;
    } else if ($groupid) {
        $groupsql = 'AND l.userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :groupid)';
        $groupparams['groupid'] = $groupid;
    }

    // Include only users enrolled with the student role.
    $sql = "SELECT l.id, l.userid, l.timecreated
              FROM {logstore_standard_log} l
             WHERE l.courseid    = :courseid
               AND l.timecreated >= :starttime
               AND l.timecreated <= :endtime
               AND l.action      $notinsql
               $activitysql
               $groupsql
               AND l.userid      != 0
               AND l.userid      IN (
                       SELECT ra.userid
                         FROM {role_assignments} ra
                         JOIN {role} r ON r.id = ra.roleid
                        WHERE ra.contextid IN (
                                  SELECT id FROM {context}
                                   WHERE contextlevel = :ctxlevel
                                     AND instanceid   = :ctxcourse
                              )
                          AND r.shortname = 'student'
                   )
          ORDER BY l.userid, l.timecreated";

    $params = array_merge(
        [
            'courseid'  => $courseid,
            'starttime' => $starttime,
            'endtime'   => $endtime,
            'ctxlevel'  => CONTEXT_COURSE,
            'ctxcourse' => $courseid,
        ],
        $notinparams,
        $activityparams,
        $groupparams
    );

    // Use a recordset to keep memory usage low for large courses.
    $rs = $DB->get_recordset_sql($sql, $params);

    $userstats   = [];
    $dailytotals = [];  // Date string => total cohort seconds that day.

    foreach ($rs as $row) {
        $uid = $row->userid;
        $t   = (int)$row->timecreated;

        if (!isset($userstats[$uid])) {
            $userstats[$uid] = ['hits' => 0, 'seconds' => 0, 'lastt' => $t, 'sessionstart' => $t];
        }

        $stat = &$userstats[$uid];
        $stat['hits']++;

        $gap = $t - $stat['lastt'];
        if ($gap > $gapseconds) {
            // Session boundary: close the previous session.
            $stat['seconds'] += $stat['lastt'] - $stat['sessionstart'];
            $stat['sessionstart'] = $t;
        } else if ($gap > 0) {
            // Active gap within a session: attribute to the day of the later event.
            // These per-gap contributions sum to exactly the total session seconds.
            $day = date('Y-m-d', $t);
            $dailytotals[$day] = ($dailytotals[$day] ?? 0) + $gap;
        }
        $stat['lastt'] = $t;
        unset($stat);
    }
    $rs->close();

    // Close the final open session for each user.
    foreach ($userstats as $uid => &$stat) {
        $stat['seconds'] += $stat['lastt'] - $stat['sessionstart'];
    }
    unset($stat);

    if (empty($userstats)) {
        return ['students' => [], 'dailytotals' => []];
    }

    // Fetch names for all relevant users in one query.
    [$uidsql, $uidparams] = $DB->get_in_or_equal(array_keys($userstats), SQL_PARAMS_NAMED, 'uid');
    $users = $DB->get_records_sql(
        "SELECT id, firstname, lastname FROM {user} WHERE id $uidsql",
        $uidparams
    );

    $results = [];
    foreach ($userstats as $uid => $stat) {
        if ($uid == -1) {
            continue;
        }
        $u = isset($users[$uid]) ? $users[$uid] : null;
        if ($u && trim($u->firstname) === 'Guest user') {
            continue;
        }
        $obj = new stdClass();
        $obj->userid       = $uid;
        $obj->firstname    = $u ? $u->firstname : "[$uid]";
        $obj->lastname     = $u ? $u->lastname : '';
        $obj->hits         = $stat['hits'];
        $obj->totalseconds = $stat['seconds'];
        $results[] = $obj;
    }

    usort($results, function ($a, $b) {
        $cmp = strcasecmp($a->lastname, $b->lastname);
        return $cmp !== 0 ? $cmp : strcasecmp($a->firstname, $b->firstname);
    });

    return ['students' => $results, 'dailytotals' => $dailytotals];
}

// -------------------------------------------------------------------------
// Format helpers.
function format_duration($seconds) {
    $seconds = max(0, (int)$seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    return sprintf('%d:%02d', $h, $m);
}

function duration_minutes($seconds) {
    return (int) round(max(0, $seconds) / 60);
}

// -------------------------------------------------------------------------
// Gate: user must have access to at least one course (or be site admin).
$allowedcourses = get_allowed_courses();
if (empty($allowedcourses)) {
    require_capability('moodle/site:config', $systemcontext); // Throws an appropriate access error.
}

// If a course was submitted, verify the user is actually allowed to report on it.
if ($courseid && !isset($allowedcourses[$courseid])) {
    throw new \moodle_exception('accessdenied', 'admin');
}

// Build activity, group, and student lists for the selected course (empty if no course chosen yet).
$activities = $courseid ? get_course_activities($courseid) : [];
$groups     = $courseid ? get_course_groups($courseid) : [];
$students   = $courseid ? get_course_students($courseid, $groupid) : [];

// -------------------------------------------------------------------------
// CSV download.
if ($courseid && !$dateerror && $download) {
    $analysisresult = analyse_course($courseid, $cmid, $groupid, $starttime, $endtime, $gapseconds, $studentid);
    $results        = $analysisresult['students'];
    $course         = $DB->get_record('course', ['id' => $courseid], 'shortname', MUST_EXIST);

    $activitylabel = '';
    if ($cmid && isset($activities[$cmid])) {
        $activitylabel = '_' . clean_filename($activities[$cmid]);
    }
    $grouplabel = '';
    if ($studentid && isset($students[$studentid])) {
        $grouplabel = '_' . clean_filename($students[$studentid]);
    } else if ($groupid && isset($groups[$groupid])) {
        $grouplabel = '_' . clean_filename($groups[$groupid]);
    }

    $filename = 'student_time_' . clean_filename($course->shortname) . $activitylabel . $grouplabel . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Last name', 'First name', 'User ID', 'Hits', 'Total time (H:MM)', 'Total minutes']);
    foreach ($results as $r) {
        fputcsv($out, [
            $r->lastname,
            $r->firstname,
            $r->userid,
            $r->hits,
            format_duration($r->totalseconds),
            duration_minutes($r->totalseconds),
        ]);
    }
    fclose($out);
    exit;
}

// -------------------------------------------------------------------------
// HTML output.
echo $OUTPUT->header();

if ($startdatestr !== '') {
    $defaultstart = $startdatestr;
} else if ($courseid) {
    $coursestartdate = $DB->get_field('course', 'startdate', ['id' => $courseid]);
    $defaultstart = $coursestartdate ? date('Y-m-d', $coursestartdate) : date('Y-m-d', strtotime('-4 weeks'));
} else {
    $defaultstart = date('Y-m-d', strtotime('-4 weeks'));
}
$defaultend = $enddatestr ?: date('Y-m-d');

// If the Analyse button was clicked, render the form non-interactive immediately so
// the user sees a locked form and "Computing data..." before the server starts work.
// The AMD JS below restores the form once the page has fully loaded (analysis done).
// NOTE: showComputing() must NOT use `disabled` on fields — disabled fields are
// excluded from GET form submissions, which would drop courseid/groupid from the URL.
// pointer-events and opacity are used instead.
$iscomputing = $courseid && !$dateerror && !$download && $submitted;
$formstyle   = $iscomputing ? 'pointer-events:none; opacity:0.5' : '';

$reloadjs = "
var indicator = document.getElementById('computing-indicator');
var resultsEl = document.getElementById('analysis-results');
var form      = document.getElementById('cr-timeanalysis-form');

function showComputing() {
    if (indicator) indicator.style.display = 'inline-block';
    if (resultsEl) resultsEl.style.display = 'none';
    if (form) { form.style.pointerEvents = 'none'; form.style.opacity = '0.5'; }
}

// Page has fully loaded — analysis is done.  Restore the form to interactive state.
if (indicator) indicator.style.display = 'none';
if (form)      { form.style.pointerEvents = ''; form.style.opacity = ''; }

// Reload the page when the course changes so activity/group/student dropdowns update.
// Drop 'submitted' so the reload does not trigger computation.
document.getElementById('id_courseid').addEventListener('change', function() {
    var url = new URL(window.location.href);
    url.searchParams.set('courseid', this.value);
    url.searchParams.delete('cmid');
    url.searchParams.delete('groupid');
    url.searchParams.delete('studentid');
    url.searchParams.delete('submitted');
    window.location.href = url.toString();
});

// Reload when the group changes so the student dropdown is re-scoped.
var groupEl = document.getElementById('id_groupid');
if (groupEl) {
    groupEl.addEventListener('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('groupid', this.value);
        url.searchParams.delete('studentid');
        url.searchParams.delete('submitted');
        window.location.href = url.toString();
    });
}

// Show computing state when the Analyse button is clicked.
if (form) {
    form.addEventListener('submit', function() { showComputing(); });
}

";
$PAGE->requires->js_amd_inline($reloadjs);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url,
    'id'     => 'cr-timeanalysis-form',
    'style'  => $formstyle,
]);
echo html_writer::start_tag('table', ['class' => 'generaltable']);

// Course selector.
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('label', 'Course:', ['for' => 'id_courseid']));
echo html_writer::tag('td', html_writer::select(
    $allowedcourses,
    'courseid',
    $courseid,
    ['0' => '-- select --'],
    ['id' => 'id_courseid']
));
echo html_writer::end_tag('tr');

// Activity selector (only shown once a course is selected).
if ($courseid) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::tag('label', 'Activity:', ['for' => 'id_cmid']));
    echo html_writer::tag('td', html_writer::select(
        $activities,
        'cmid',
        $cmid,
        ['0' => '-- All activities --'],
        ['id' => 'id_cmid']
    ));
    echo html_writer::end_tag('tr');
}

// Group selector (only shown once a course is selected and it has groups).
if ($courseid && !empty($groups)) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::tag('label', 'Group:', ['for' => 'id_groupid']));
    echo html_writer::tag('td', html_writer::select(
        $groups,
        'groupid',
        $groupid,
        ['0' => '-- All students --'],
        ['id' => 'id_groupid']
    ));
    echo html_writer::end_tag('tr');
}

// Student selector (shown once a course is selected; scoped by group if one is chosen).
if ($courseid && !empty($students)) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::tag('label', 'Student:', ['for' => 'id_studentid']));
    echo html_writer::tag('td', html_writer::select(
        $students,
        'studentid',
        $studentid,
        ['0' => '-- All students --'],
        ['id' => 'id_studentid']
    ));
    echo html_writer::end_tag('tr');
}

// Start date.
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('label', 'Start date:', ['for' => 'startdate']));
echo html_writer::tag(
    'td',
    html_writer::empty_tag('input', [
        'type'  => 'date',
        'id'    => 'startdate',
        'name'  => 'startdate',
        'value' => $defaultstart,
    ])
);
echo html_writer::end_tag('tr');

// End date.
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('label', 'End date:', ['for' => 'enddate']));
echo html_writer::tag(
    'td',
    html_writer::empty_tag('input', [
        'type'  => 'date',
        'id'    => 'enddate',
        'name'  => 'enddate',
        'value' => $defaultend,
    ])
);
echo html_writer::end_tag('tr');

// Gap threshold.
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('label', 'Idle-gap threshold (minutes):', ['for' => 'gapminutes']));
echo html_writer::tag(
    'td',
    html_writer::empty_tag('input', [
        'type'  => 'number',
        'id'    => 'gapminutes',
        'name'  => 'gapminutes',
        'value' => $gapminutes,
        'min'   => '1',
        'max'   => '480',
        'style' => 'width:5em',
    ])
);
echo html_writer::end_tag('tr');

// Hidden field so the server knows the Analyse button was clicked.
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submitted', 'value' => '1']);

// Analyse button.
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '');
echo html_writer::tag(
    'td',
    html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Analyse', 'class' => 'btn btn-primary'])
);
echo html_writer::end_tag('tr');

echo html_writer::end_tag('table');
echo html_writer::end_tag('form');

$indicatordisplay = $iscomputing ? 'inline-block' : 'none';
echo html_writer::tag('p', "\u{23F3} Analysing ... please wait\u{2026}", [
    'id'    => 'computing-indicator',
    'style' => "display:$indicatordisplay; margin-top:1.5em; font-weight:bold; " .
               "background:#fff3cd; color:#856404; " .
               "border:1px solid #ffc107; border-radius:4px; padding:.5em 1em;",
]);

// Error/results section.
if ($dateerror) {
    echo $OUTPUT->notification($dateerror, 'notifyproblem');
}

echo html_writer::start_tag('div', ['id' => 'analysis-results', 'style' => 'margin-top:2em']);

if ($courseid && !$dateerror && $submitted) {
    $startlabel    = $startdatestr ?: '(beginning of logs)';
    $endlabel      = $enddatestr ?: '(end of logs)';
    $activitylabel = ($cmid && isset($activities[$cmid])) ? $activities[$cmid] : 'All activities';
    $grouplabel    = ($groupid && isset($groups[$groupid])) ? $groups[$groupid] : 'All students';
    $studentlabel  = ($studentid && isset($students[$studentid])) ? $students[$studentid] : null;

    echo html_writer::tag(
        'p',
        "Analysing course ID <strong>$courseid</strong> &mdash; " .
        "activity: <strong>" . s($activitylabel) . "</strong> &mdash; " .
        ($studentlabel
            ? "student: <strong>" . s($studentlabel) . "</strong>"
            : "group: <strong>" . s($grouplabel) . "</strong>") .
        " &mdash; $startlabel to $endlabel &mdash; idle gap: <strong>{$gapminutes} min</strong>"
    );

    $analysisresult = analyse_course($courseid, $cmid, $groupid, $starttime, $endtime, $gapseconds, $studentid);
    $results        = $analysisresult['students'];
    $dailytotals    = $analysisresult['dailytotals'];

    if (empty($results)) {
        echo $OUTPUT->notification('No log entries found for the selected criteria.', 'notifymessage');
    } else {
        $totalstudents = count($results);
        $totalhits     = array_sum(array_column($results, 'hits'));
        $totalsecs     = array_sum(array_column($results, 'totalseconds'));
        $avghours      = $totalstudents > 0 ? number_format($totalsecs / $totalstudents / 3600, 2) : '0.00';

        echo html_writer::tag(
            'p',
            "$totalstudents students &mdash; $totalhits total hits &mdash; total time: " .
            format_duration($totalsecs) . " (H:MM) &mdash; average: <strong>{$avghours} hrs</strong>"
        );

        // -------------------------------------------------------------------------
        // Time-series chart: average hours per week across the cohort.
        if (!empty($dailytotals)) {
            // Determine chart date range.  Use explicit date parameters when given;
            // fall back to the actual span of the data.
            $chartstart = $startdatestr ? date('Y-m-d', $starttime) : min(array_keys($dailytotals));
            $chartend = $enddatestr ? date('Y-m-d', $endtime) : max(array_keys($dailytotals));

            // One data point per day; weekly ticks aligned to Mondays.
            $chartlabels = [];
            $chartvalues = [];
            $day = strtotime($chartstart);
            $end = strtotime($chartend);
            $dow = (int)date('N', $day);  // 1=Mon … 7=Sun
            if ($dow !== 1) {
                $day = strtotime('-' . ($dow - 1) . ' days', $day);
            }
            while ($day <= $end) {
                $key           = date('Y-m-d', $day);
                $chartlabels[] = (date('N', $day) === '1') ? date('D j/n/y', $day) : '';
                $chartvalues[] = round(($dailytotals[$key] ?? 0) / $totalstudents / 3600, 4);
                $day           = strtotime('+1 day', $day);
            }

            $canvasid  = html_writer::random_id('crta_');
            $labeljson = json_encode($chartlabels);
            $valuejson = json_encode($chartvalues);
            $charttitle = $studentlabel
                ? "Daily activity \u{2014} $studentlabel"
                : "Daily activity \u{2014} cohort: $totalstudents students";
            $titlejson = json_encode($charttitle);
            $yaxislabel = $studentlabel
                ? 'Estimated hours on server/day'
                : 'Estimated hours on server/student/day';

            $canvastag = html_writer::empty_tag('canvas', ['id' => $canvasid]);
            echo html_writer::tag(
                'div',
                $canvastag,
                ['style' => 'position:relative; height:350px; margin-bottom:1em']
            );

            $PAGE->requires->js_amd_inline("
require(['core/chartjs-lazy'], function(Chart) {
    var ctx = document.getElementById('$canvasid');
    if (!ctx) { return; }
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: $labeljson,
            datasets: [{
                data: $valuejson,
                borderColor: 'rgb(54,162,235)',
                backgroundColor: 'rgba(54,162,235,0.1)',
                tension: 0,
                pointRadius: 2,
                fill: true
            }]
        },
        options: {
            animation: false,
            plugins: {
                title: { display: true, text: $titlejson },
                legend: { display: false }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 45,
                        callback: function(val, idx) {
                            var lbl = this.getLabelForValue(val);
                            return lbl ? lbl : null;
                        }
                    }
                },
                y: {
                    title: { display: true, text: '$yaxislabel' },
                    min: 0
                }
            }
        }
    });
});
");
        }

        echo html_writer::tag('hr', '');
        echo html_writer::tag('h3', 'Per-student detail');
        echo html_writer::tag('p', 'Rough estimates of active time on server for each student. Indicative only.');

        $dlurl = new moodle_url($PAGE->url, [
            'courseid'   => $courseid,
            'cmid'       => $cmid,
            'groupid'    => $groupid,
            'studentid'  => $studentid,
            'startdate'  => $startdatestr,
            'enddate'    => $enddatestr,
            'gapminutes' => $gapminutes,
            'download'   => 1,
        ]);
        echo html_writer::tag(
            'p',
            html_writer::link($dlurl, 'Download as CSV', ['class' => 'btn btn-sm btn-secondary'])
        );

        $table = new html_table();
        $table->head       = ['Last name', 'First name', 'Hits', 'Total time (H:MM)'];
        $table->attributes = ['class' => 'generaltable', 'style' => 'width:auto'];
        $table->data       = [];
        foreach ($results as $r) {
            $table->data[] = [
                s($r->lastname),
                s($r->firstname),
                $r->hits,
                format_duration($r->totalseconds),
            ];
        }
        echo html_writer::table($table);
    }
}

echo html_writer::end_tag('div');  // End #analysis-results.

echo $OUTPUT->footer();
