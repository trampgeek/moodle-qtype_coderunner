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
 * Quiz mark trajectory and per-question time analysis.
 *
 * Two modes:
 *  - 'table'  (default): spreadsheet of per-student, per-question times in
 *             minutes, with column averages and a link to the trajectory view
 *             for each student.
 *  - 'trajectory': Chart.js mark-vs-cumulative-active-time plot for one student.
 *
 * All times are cumulative active times, with idle gaps (> configurable
 * threshold) removed.  Session-gap boundaries are shown as vertical dotted
 * lines on the trajectory chart.
 *
 * Time on question i is defined as:
 *   T_i = b_i - max(b_j : j != i, b_j < b_i)
 * where b_i is the cumulative active time at which the student first achieved
 * their best mark on question i.  If no other question was completed before
 * b_i, T_start (first log event in the quiz context) is used instead.
 *
 * Place this file inside question/type/coderunner/scripts/ and access it as:
 *   https://<your-moodle>/question/type/coderunner/scripts/quiztrajectory.php
 *
 * @package   qtype_coderunner
 * @copyright  2024 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Access control.
require_login();

$systemcontext = context_system::instance();
$issiteadmin   = has_capability('moodle/site:config', $systemcontext);
$staffrolenames = ['manager', 'coursecreator', 'editingteacher', 'teacher'];

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/question/type/coderunner/scripts/quiztrajectory.php'));
$PAGE->set_title('CodeRunner: Quiz mark trajectories');
$PAGE->set_heading('CodeRunner: Quiz mark trajectories');

// Parameters.
$mode         = optional_param('mode', 'table', PARAM_ALPHA);   // Either 'table' or 'trajectory'.
$courseid     = optional_param('courseid', 0, PARAM_INT);
$quizid       = optional_param('quizid', 0, PARAM_INT);     // Quiz instance id.
$studentid    = optional_param('studentid', 0, PARAM_INT);     // For trajectory mode.
$gapminutes   = optional_param('gapminutes', 30, PARAM_INT);
$attemptnum   = optional_param('attemptnum', 1, PARAM_INT);  // 1=first, -1=last, 0=best, n=nth attempt.
$debug        = optional_param('debug', 0, PARAM_INT);     // 1 = dump raw step data.

$gapseconds = max(1, $gapminutes) * 60;

// Helper: courses the current user may report on.
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

// Helper: quizzes in a course.
function get_course_quizzes($courseid) {
    global $DB;
    $sql = "SELECT q.id, q.name
              FROM {quiz} q
              JOIN {course_modules} cm ON cm.instance = q.id
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             WHERE q.course  = :courseid
               AND cm.visible = 1
          ORDER BY q.name ASC";
    return $DB->get_records_sql_menu($sql, ['courseid' => $courseid]);
}

// Helper: get the course-module id for a quiz instance.
function get_cmid_for_quiz($quizid) {
    global $DB;
    return (int)$DB->get_field_sql(
        "SELECT cm.id
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
          WHERE cm.instance = :quizid",
        ['quizid' => $quizid]
    );
}

// Helper: students enrolled in a course (student role only).
function get_enrolled_students($courseid) {
    global $DB;
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {role_assignments} ra ON ra.userid = u.id
              JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
              JOIN {context} ctx ON ctx.id = ra.contextid
                AND ctx.contextlevel = :ctxlevel AND ctx.instanceid = :courseid
             WHERE u.deleted = 0
          ORDER BY u.lastname ASC, u.firstname ASC";
    return $DB->get_records_sql($sql, ['ctxlevel' => CONTEXT_COURSE, 'courseid' => $courseid]);
}

// Helper: return the highest attempt number taken by any student in a quiz.
function get_quiz_max_attempts($quizid) {
    global $DB;
    return (int)$DB->get_field_sql(
        "SELECT COALESCE(MAX(attempt), 1) FROM {quiz_attempts} WHERE quiz = :quizid",
        ['quizid' => $quizid]
    );
}

// Helper: return one quiz_attempts row for a student according to $attemptnum.
// n > 0 = attempt number n exactly; -1 = last attempt; 0 = best (highest sumgrades).
// Returns null if no matching attempt exists.
function get_student_attempt($userid, $quizid, $attemptnum) {
    global $DB;
    if ($attemptnum > 0) {
        $row = $DB->get_record(
            'quiz_attempts',
            ['quiz' => $quizid, 'userid' => $userid, 'attempt' => $attemptnum]
        );
        return $row ?: null;
    }
    if ($attemptnum === -1) {
        $rows = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts} WHERE quiz = :quizid AND userid = :userid
             ORDER BY attempt DESC",
            ['quizid' => $quizid, 'userid' => $userid],
            0,
            1
        );
    } else {
        // Best attempt: highest sumgrades, ties broken by earliest attempt.
        $rows = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts} WHERE quiz = :quizid AND userid = :userid
             ORDER BY sumgrades DESC, attempt ASC",
            ['quizid' => $quizid, 'userid' => $userid],
            0,
            1
        );
    }
    return $rows ? reset($rows) : null;
}

// Helper: human-readable label for an attempt number parameter.
function attempt_label($attemptnum) {
    if ($attemptnum === 0) {
        return 'Best attempt';
    }
    if ($attemptnum === -1) {
        return 'Last attempt';
    }
    $suffixes = [1 => 'st', 2 => 'nd', 3 => 'rd'];
    $suffix = $suffixes[$attemptnum] ?? 'th';
    return $attemptnum . $suffix . ' attempt';
}

// Helper: questions in a quiz, in slot order
// Returns array of objects with: slot, maxmark
// Random question slots are included (they have no entry in question_references).
// Description slots are excluded.
// qnum is assigned sequentially to match the numbers Moodle shows students.
function get_quiz_questions($quizid) {
    global $DB;
    // Random question slots have no entry in question_references (they use
    // question_set_references instead), so we use LEFT JOINs to avoid silently
    // dropping those slots.  We also honour qr.version (the pinned version) when
    // set, rather than blindly joining to MAX(version), which would be wrong if
    // the question has been updated since the quiz was created.
    // qtype is only joined to filter out description slots; it is not returned.
    $sql = "SELECT qs.slot,
                   qs.maxmark
              FROM {quiz_slots} qs
         LEFT JOIN {question_references} qr
                ON qr.component    = 'mod_quiz'
               AND qr.questionarea = 'slot'
               AND qr.itemid       = qs.id
         LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
         LEFT JOIN {question_versions} qv
                ON qv.questionbankentryid = qbe.id
               AND qv.version = COALESCE(
                       qr.version,
                       (SELECT MAX(qv2.version)
                          FROM {question_versions} qv2
                         WHERE qv2.questionbankentryid = qbe.id)
                   )
         LEFT JOIN {question} q ON q.id = qv.questionid
             WHERE qs.quizid = :quizid
               AND (q.qtype IS NULL OR q.qtype != 'description')
          ORDER BY qs.slot ASC";
    $rows = $DB->get_records_sql($sql, ['quizid' => $quizid]);

    if (empty($rows)) {
        // Fall back to pre-4.x schema.
        $sql = "SELECT qs.slot, qs.maxmark
                  FROM {quiz_slots} qs
                  JOIN {question} q ON q.id = qs.questionid
                 WHERE qs.quizid     = :quizid
                   AND qs.questionid > 0
                   AND q.qtype      != 'description'
              ORDER BY qs.slot ASC";
        $rows = $DB->get_records_sql($sql, ['quizid' => $quizid]);
    }

    // Assign sequential display numbers matching Moodle's own numbering.
    $qnum = 1;
    foreach ($rows as &$row) {
        $row->qnum = $qnum++;
    }
    unset($row);

    return array_values($rows);
}

// Core: scan log events for one student in one quiz context and identify
// idle gaps (consecutive events more than $gapseconds apart).
//
// Fills $gaps with one entry per gap:
// ['rawstart' => int, 'rawend' => int, 'cumstart' => float]
// where rawstart/rawend are the raw Unix timestamps bracketing the gap and
// cumstart is the cumulative active seconds at which the gap begins.
//
// Returns the raw timestamp of the first log event, or null if there are
// none (in which case $gaps is set to []).
function build_gap_intervals($userid, $contextid, $starttime, $endtime, $gapseconds, &$gaps) {
    global $DB;

    $sql = "SELECT timecreated
              FROM {logstore_standard_log}
             WHERE userid    = :userid
               AND contextid = :contextid
               AND timecreated >= :starttime
               AND timecreated <= :endtime
               AND action NOT IN ('autosave', 'autosaved')
          ORDER BY timecreated ASC";

    $params = ['userid' => $userid, 'contextid' => $contextid,
               'starttime' => $starttime, 'endtime' => $endtime];

    $events = $DB->get_fieldset_sql($sql, $params);

    if (empty($events)) {
        $gaps = [];
        return null;
    }

    $gaps     = [];
    $cum      = 0.0;
    $firstraw = (int)$events[0];
    $prev     = $firstraw;

    foreach (array_slice($events, 1) as $rawt) {
        $rawt  = (int)$rawt;
        $delta = $rawt - $prev;
        if ($delta > $gapseconds) {
            $gaps[] = ['rawstart' => $prev, 'rawend' => $rawt, 'cumstart' => $cum];
        } else {
            $cum += $delta;
        }
        $prev = $rawt;
    }

    return $firstraw;
}

// Core: convert a raw Unix timestamp to cumulative active seconds.
//
// Log events are used only to identify idle gaps; the step timestamp itself
// is the source of truth for when an event occurred.
//
// cumulative_active(T) = (T - firstraw) - total idle time before T
//
// Returns null if $firstraw is null (no log events) or $rawt < $firstraw.
function raw_to_cum($rawt, $firstraw, $gaps) {
    if ($firstraw === null || $rawt < $firstraw) {
        return null;
    }
    $idle = 0;
    foreach ($gaps as $g) {
        if ($g['rawstart'] >= $rawt) {
            break;
        }
        $idle += min($g['rawend'], $rawt) - $g['rawstart'];
    }
    return $rawt - $firstraw - $idle;
}

// Core: analyse one student's attempt on a quiz.
//
// $attempt is the quiz_attempts row to analyse (as returned by get_student_attempt).
//
// Returns an object with:
// ->questions  : array indexed by slot (1-based); each object has slot, qnum,
// maxmark, bestmark, best_cum (seconds), time_on_q (seconds, or null if unscored)
// ->tstart      : cumulative time of quiz attempt start (seconds)
// ->trajectory  : array of {cum_minutes, cummarks, qlabel} sorted by cum
// ->gaps        : array of gap intervals, each with rawstart, rawend, cumstart
// ->firstraw    : raw Unix timestamp of the first log event
//
// Returns null if the student has no log events in the quiz context.
function analyse_student($userid, $quizid, $cmid, $questions, $attempt, $gapseconds) {
    global $DB;

    $starttime = (int)$attempt->timestart;
    $endtime   = ($attempt->timefinish > 0) ? (int)$attempt->timefinish : PHP_INT_MAX;

    // Get the module context id for the quiz.
    $modcontext = context_module::instance($cmid);

    // Scan log events to find idle gaps; the returned $firstraw anchors all
    // cumulative-time calculations.
    $gaps     = [];
    $firstraw = build_gap_intervals(
        $userid,
        $modcontext->id,
        $starttime,
        $endtime,
        $gapseconds,
        $gaps
    );

    if ($firstraw === null) {
        return null;
    }

    // T_start: the attempt's recorded start time, converted to cumulative seconds.
    $tstart = raw_to_cum($starttime, $firstraw, $gaps) ?? 0.0;

    // Fetch all question_attempt_step rows for this specific quiz attempt.
    $sql = "SELECT qas.id,
                   qas.questionattemptid,
                   qas.timecreated,
                   qas.fraction,
                   qa.slot,
                   qa.maxmark
              FROM {question_attempt_steps} qas
              JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
             WHERE qa.questionusageid = :uniqueid
               AND qas.fraction IS NOT NULL
          ORDER BY qas.timecreated ASC";

    $steps = $DB->get_records_sql($sql, ['uniqueid' => $attempt->uniqueid]);
    $rawsteps = $steps; // Keep a copy for debug output.

    // Build a map from question_attempt.id -> slot using the canonical slot
    // numbers from quiz_slots, to avoid any off-by-one discrepancy.
    $qaidtoslot = [];
    $slotbest = [];
    foreach ($questions as $q) {
        $slotbest[(int)$q->slot] = (object)[
            'bestmark' => -1,
            'best_raw' => null,
            'best_cum' => null,
            'maxmark'  => (float)$q->maxmark,
        ];
    }
    // Fetch the mapping from question_attempt id -> quiz_slots.slot for this attempt.
    $qaidrows = $DB->get_records_sql(
        "SELECT qa.id AS qaid, qs.slot
           FROM {question_attempts} qa
           JOIN {quiz_slots} qs ON qs.quizid = :quizid AND qs.slot = qa.slot
          WHERE qa.questionusageid = :uniqueid",
        ['quizid' => $quizid, 'uniqueid' => $attempt->uniqueid]
    );
    foreach ($qaidrows as $row) {
        $qaidtoslot[(int)$row->qaid] = (int)$row->slot;
    }

    foreach ($steps as $step) {
        $slot = $qaidtoslot[(int)$step->questionattemptid] ?? null;
        if ($slot === null || !isset($slotbest[$slot])) {
            continue;
        }
        $mark = (float)$step->fraction * (float)$step->maxmark;
        $cum  = raw_to_cum((int)$step->timecreated, $firstraw, $gaps);
        if ($cum === null) {
            $cum = 0.0; // Step before first log event; treat as time zero.
        }
        if ($mark > $slotbest[$slot]->bestmark) {
            $slotbest[$slot]->bestmark = $mark;
            $slotbest[$slot]->best_raw = (int)$step->timecreated;
            $slotbest[$slot]->best_cum = $cum;
        }
    }

    // Compute T_i = b_i - max(b_j : j != i, b_j < b_i), falling back to tstart.
    // Collect all b values that are defined (bestmark > 0 or == 0 but attempted).
    $bvals = []; // Cumulative time => slot, for slots where best_cum is set and bestmark >= 0.
    foreach ($slotbest as $slot => $info) {
        if ($info->best_cum !== null && $info->bestmark >= 0) {
            $bvals[$slot] = $info->best_cum;
        }
    }

    $result = (object)[
        'questions'  => [],
        'tstart'     => $tstart,
        'trajectory' => [],
        'gaps'       => $gaps,
        'firstraw'   => $firstraw,
        'rawsteps'   => $rawsteps,
    ];

    foreach ($questions as $q) {
        $slot = (int)$q->slot;
        $info = $slotbest[$slot];
        $obj  = (object)[
            'slot'    => $slot,
            'qnum'    => $q->qnum,
            'maxmark' => $info->maxmark,
            'bestmark'   => $info->bestmark >= 0 ? $info->bestmark : null,
            'best_cum'   => $info->best_cum,
            'time_on_q'  => null,
        ];

        if ($info->best_cum !== null && $info->bestmark > 0) {
            // Find max(b_j : j != slot, b_j < b_i).
            $bi      = $info->best_cum;
            $prevb   = $tstart; // Fallback.
            foreach ($bvals as $j => $bj) {
                if ($j !== $slot && $bj < $bi && $bj > $prevb) {
                    $prevb = $bj;
                }
            }
            $obj->time_on_q = $bi - $prevb;
        }

        $result->questions[$slot] = $obj;
    }

    // Build trajectory: list of {cum_minutes, cummarks, qlabel} sorted by cum.
    // One entry per slot where the student achieved >0 marks.
    $trajpoints = [];
    foreach ($result->questions as $slot => $obj) {
        if ($obj->best_cum !== null && $obj->bestmark > 0) {
            $trajpoints[] = (object)[
                'cum'      => $obj->best_cum,
                'mark'     => $obj->bestmark,
                'qlabel'   => 'Q' . $obj->qnum,
            ];
        }
    }
    usort($trajpoints, fn($a, $b) => $a->cum <=> $b->cum);

    // Offset all trajectory x-values by T_start so that x=0 on the chart
    // corresponds to the moment the quiz attempt was started, matching the
    // origin used by the time-on-question calculations.
    $result->trajectory[] = (object)['cum_minutes' => 0.0, 'cummarks' => 0.0, 'qlabel' => ''];
    $cummarks = 0.0;
    foreach ($trajpoints as $pt) {
        $cummarks += $pt->mark;
        $result->trajectory[] = (object)[
            'cum_minutes' => round(($pt->cum - $tstart) / 60, 4),
            'cummarks'    => round($cummarks, 4),
            'qlabel'      => $pt->qlabel,
        ];
    }

    // Extend the trajectory to the quiz finish time so the graph doesn't cut
    // off at the last correct submission.  The final point has no label so no
    // marker is drawn; the stepped line simply continues horizontally to the end.
    if ($attempt->timefinish > 0) {
        $endcum = raw_to_cum((int)$attempt->timefinish, $firstraw, $gaps);
        if ($endcum !== null) {
            $endmins = round(($endcum - $tstart) / 60, 4);
            $lastpt  = end($result->trajectory);
            if ($endmins > $lastpt->cum_minutes) {
                $result->trajectory[] = (object)[
                    'cum_minutes' => $endmins,
                    'cummarks'    => round($cummarks, 4),
                    'qlabel'      => '',
                ];
            }
        }
    }

    return $result;
}

// Gate: user must have access to at least one course.
$allowedcourses = get_allowed_courses();
if (empty($allowedcourses)) {
    require_capability('moodle/site:config', $systemcontext);
}
if ($courseid && !isset($allowedcourses[$courseid])) {
    throw new \moodle_exception('accessdenied', 'admin');
}

$quizzes    = $courseid ? get_course_quizzes($courseid) : [];
$questions  = ($quizid) ? get_quiz_questions($quizid) : [];
$students   = $courseid ? get_enrolled_students($courseid) : [];
$quizcmid   = $quizid ? get_cmid_for_quiz($quizid) : 0;

// Trajectory mode.
if ($mode === 'trajectory' && $quizid && $studentid && $quizcmid) {
    $student = $DB->get_record('user', ['id' => $studentid], 'id,firstname,lastname', MUST_EXIST);
    $quiz    = $DB->get_record('quiz', ['id' => $quizid], 'id,name', MUST_EXIST);

    $attempt = get_student_attempt($studentid, $quizid, $attemptnum);
    $data    = $attempt
        ? analyse_student($studentid, $quizid, $quizcmid, $questions, $attempt, $gapseconds)
        : null;

    echo $OUTPUT->header();

    // Back link.
    $backurl = new moodle_url('/question/type/coderunner/scripts/quiztrajectory.php', [
        'courseid'   => $courseid,
        'quizid'     => $quizid,
        'gapminutes' => $gapminutes,
        'attemptnum' => $attemptnum,
    ]);
    echo html_writer::tag('p', html_writer::link($backurl, '&larr; Back to summary table'));
    $heading = 'Mark trajectory: ' . s(fullname($student)) . ' &mdash; ' . s($quiz->name) .
        ' (' . attempt_label($attemptnum) . ')';
    echo html_writer::tag('h2', $heading);

    if (!$data || count($data->trajectory) <= 1) {
        $msg = $attempt
            ? 'No mark data found for this student for the selected attempt.'
            : 'No ' . attempt_label($attemptnum) . ' found for this student.';
        echo $OUTPUT->notification($msg, 'notifymessage');
        echo $OUTPUT->footer();
        exit;
    }

    // Compute quiz maximum for Y axis.
    $quizmax = array_sum(array_column($questions, 'maxmark'));

    // Build chart datasets.
    // Trajectory points: piecewise linear, label at each step.
    $chartpoints = [];
    foreach ($data->trajectory as $pt) {
        $chartpoints[] = ['x' => $pt->cum_minutes, 'y' => $pt->cummarks, 'label' => $pt->qlabel];
    }

    // Gap lines: vertical dotted annotations (Chart.js annotation plugin not
    // available in Moodle core, so we draw gaps as thin datasets instead).
    // Gaps are also offset by T_start to align with the chart origin.
    $tstartmins = $data->tstart / 60;
    $gaplines = [];
    foreach ($data->gaps as $gap) {
        $gx = $gap['cumstart'] / 60 - $tstartmins;
        if ($gx > 0) {  // Only show gaps that fall after the attempt started.
            $gaplines[] = $gx;
        }
    }

    $chartpointsjson = json_encode($chartpoints);
    $gaplinesjson    = json_encode($gaplines);
    $quizmaxjson     = json_encode((float)$quizmax);
    $studentname     = json_encode(fullname($student));
    $quizname        = json_encode($quiz->name);

    echo <<<HTML
<div style="max-width:900px; margin:1em 0;">
  <canvas id="trajectoryChart"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    var points    = {$chartpointsjson};
    var gaplines  = {$gaplinesjson};
    var quizmax   = {$quizmaxjson};

    // Piecewise-constant (stepped) trajectory.
    // For each mark-change point we emit two chart points:
    //   (x_prev, y_prev) -> (x_new, y_prev)  [horizontal run at old value]
    //   (x_new,  y_new)                       [vertical rise, implicit in line]
    // Chart.js 'stepped' mode handles this automatically via stepped:'before'.
    var trajData = points.map(function(p) { return {x: p.x, y: p.y}; });

    // One vertical line dataset per gap.
    var gapDatasets = gaplines.map(function(gx) {
        return {
            label: '',
            data: [{x: gx, y: 0}, {x: gx, y: quizmax}],
            borderColor: 'rgba(160,160,160,0.5)',
            borderDash: [5, 5],
            borderWidth: 1,
            pointRadius: 0,
            showLine: true,
            fill: false,
            stepped: false,
        };
    });

    var ctx = document.getElementById('trajectoryChart').getContext('2d');
    new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Marks',
                data: trajData,
                borderColor: 'rgba(54, 120, 200, 1)',
                backgroundColor: 'rgba(54, 120, 200, 0.1)',
                showLine: true,
                fill: true,
                stepped: 'before',
                pointRadius: points.map(function(p) { return p.label ? 4 : 0; }),
                pointHoverRadius: 6,
                pointBackgroundColor: 'rgba(54,120,200,1)',
            }].concat(gapDatasets),
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: {
                    display: true,
                    text: 'Mark trajectory — ' + {$studentname} + ' — ' + {$quizname},
                },
                tooltip: {
                    filter: function(item) { return item.datasetIndex === 0; },
                    callbacks: {
                        label: function(ctx) {
                            var pt = points[ctx.dataIndex];
                            var lbl = pt.label ? ' (' + pt.label + ')' : '';
                            return 'Time: ' + ctx.parsed.x.toFixed(1) + ' min, Marks: ' + ctx.parsed.y.toFixed(2) + lbl;
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'linear',
                    title: { display: true, text: 'Cumulative active time (minutes)' },
                    min: 0,
                },
                y: {
                    title: { display: true, text: 'Cumulative marks' },
                    min: 0,
                    max: quizmax,
                }
            }
        },
        plugins: [{
            // Draw Q-labels above and to the left of each step point.
            afterDraw: function(chart) {
                var ctx2 = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                ctx2.save();
                ctx2.font = 'bold 11px sans-serif';
                ctx2.fillStyle = 'rgba(40,80,160,0.9)';
                points.forEach(function(pt, i) {
                    if (!pt.label) return;
                    var el = meta.data[i];
                    if (!el) return;
                    var tw = ctx2.measureText(pt.label).width;
                    // Place label above and to the left of the step point.
                    ctx2.fillText(pt.label, el.x - tw - 4, el.y - 5);
                });
                ctx2.restore();
            }
        }]
    });
})();
</script>
HTML;

    // Also show a small table of per-question stats below the chart.
    echo html_writer::tag('h3', 'Per-question detail');
    $table = new html_table();
    $table->head = ['Question', 'Max mark', 'Best mark', 'Time (min)'];
    $table->attributes = ['class' => 'generaltable', 'style' => 'width:auto'];
    foreach ($data->questions as $slot => $obj) {
        $timestr = ($obj->time_on_q !== null)
            ? number_format($obj->time_on_q / 60, 1)
            : '&mdash;';
        $markstr = ($obj->bestmark !== null)
            ? number_format($obj->bestmark, 2)
            : '&mdash;';
        $table->data[] = [
            'Q' . $obj->qnum,
            number_format($obj->maxmark, 2),
            $markstr,
            $timestr,
        ];
    }
    echo html_writer::table($table);

    // Debug mode: dump every question_attempt_step with fraction IS NOT NULL.
    if ($debug && $issiteadmin) {
        echo html_writer::tag('h3', 'Debug: raw question_attempt_steps (fraction IS NOT NULL)');
        $dtable = new html_table();
        $dtable->head = ['Step ID', 'QA ID', 'Slot', 'Qnum', 'timecreated', 'fraction', 'cum (s)', 'cum (min)'];
        $dtable->attributes = ['class' => 'generaltable', 'style' => 'width:auto; font-size:0.85em'];
        // Build slot->qnum map for display.
        $slotnums = [];
        foreach ($questions as $q) {
            $slotnums[(int)$q->slot] = $q->qnum;
        }
        foreach ($data->rawsteps as $step) {
            $slot = (int)$step->slot;
            $qnum = isset($slotnums[$slot]) ? ('Q' . $slotnums[$slot]) : "slot $slot";
            $cum  = raw_to_cum((int)$step->timecreated, $data->firstraw, $data->gaps);
            $cummin = ($cum !== null) ? number_format($cum / 60, 3) : 'before map';
            $dtable->data[] = [
                $step->id,
                $step->questionattemptid,
                $slot,
                $qnum,
                userdate($step->timecreated, '%H:%M:%S'),
                number_format((float)$step->fraction, 4),
                ($cum !== null) ? number_format($cum, 1) : 'n/a',
                $cummin,
            ];
        }
        echo html_writer::table($dtable);
    }

    echo $OUTPUT->footer();
    exit;
}

// Table mode.
echo $OUTPUT->header();
echo html_writer::tag('h2', 'Quiz mark trajectories &amp; per-question times');

// Reload page when course changes to refresh quiz list.
// Inline script; js_amd_inline is unreliable in standalone scripts.

// Form section.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url]);
echo html_writer::start_tag('table', ['class' => 'generaltable']);

// Course.
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

// Quiz (only once course chosen).
if ($courseid) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::tag('label', 'Quiz:', ['for' => 'id_quizid']));
    echo html_writer::tag('td', html_writer::select(
        $quizzes,
        'quizid',
        $quizid,
        ['0' => '-- select --'],
        ['id' => 'id_quizid']
    ));
    echo html_writer::end_tag('tr');
}

// Attempt selector (only once quiz chosen).
if ($quizid) {
    $maxattempts = get_quiz_max_attempts($quizid);
    $attemptopts = [];
    for ($n = 1; $n <= $maxattempts; $n++) {
        $attemptopts[$n] = attempt_label($n);
    }
    $attemptopts[-1] = 'Last attempt';
    $attemptopts[0]  = 'Best attempt';
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::tag('label', 'Attempt:', ['for' => 'id_attemptnum']));
    echo html_writer::tag('td', html_writer::select(
        $attemptopts,
        'attemptnum',
        $attemptnum,
        false,
        ['id' => 'id_attemptnum']
    ));
    echo html_writer::end_tag('tr');
}

// Gap threshold.
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('label', 'Idle-gap threshold (minutes):', ['for' => 'gapminutes']));
echo html_writer::tag('td', html_writer::empty_tag('input', [
    'type' => 'number', 'id' => 'gapminutes', 'name' => 'gapminutes',
    'value' => $gapminutes, 'min' => '1', 'max' => '480', 'style' => 'width:5em']));
echo html_writer::end_tag('tr');

// Submit.
echo html_writer::start_tag('tr');
echo html_writer::tag('td', '');
echo html_writer::tag('td', html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'value' => 'Analyse', 'class' => 'btn btn-primary']
));
echo html_writer::end_tag('tr');

echo html_writer::end_tag('table');
echo html_writer::end_tag('form');
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    var sel = document.getElementById("id_courseid");
    if (sel) sel.addEventListener("change", function() {
        var url = new URL(window.location.href);
        url.searchParams.set("courseid", this.value);
        url.searchParams.delete("quizid");
        window.location.href = url.toString();
    });
});
</script>';

// Results section.
if ($courseid && $quizid && $quizcmid) {
    $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id,name', MUST_EXIST);
    echo html_writer::tag(
        'h3',
        s($quiz->name) . ' &mdash; ' . attempt_label($attemptnum) .
        ' &mdash; idle gap: ' . $gapminutes . ' min',
        ['style' => 'margin-top: 2em']
    );

    if (empty($questions)) {
        echo $OUTPUT->notification('No questions found in this quiz.', 'notifymessage');
        echo $OUTPUT->footer();
        exit;
    }

    $nquestions = count($questions);
    $slotlabels = [];
    foreach ($questions as $q) {
        $slotlabels[] = 'Q' . $q->qnum;
    }

    // Analyse each student.
    $alldata   = [];  // User ID => result object (or null).
    $colsums   = array_fill(0, $nquestions, 0.0);
    $colcounts = array_fill(0, $nquestions, 0);

    foreach ($students as $uid => $student) {
        $attempt = get_student_attempt($uid, $quizid, $attemptnum);
        $data = $attempt
            ? analyse_student($uid, $quizid, $quizcmid, $questions, $attempt, $gapseconds)
            : null;
        $alldata[$uid] = $data;
        if ($data) {
            $i = 0;
            foreach ($questions as $q) {
                $obj = $data->questions[$q->slot];
                if ($obj->time_on_q !== null) {
                    $colsums[$i]   += $obj->time_on_q / 60;
                    $colcounts[$i] += 1;
                }
                $i++;
            }
        }
    }

    // Attempt counts per student (for display in the name column).
    $attemptcounts = $DB->get_records_sql_menu(
        "SELECT userid, COUNT(*) FROM {quiz_attempts} WHERE quiz = :quizid GROUP BY userid",
        ['quizid' => $quizid]
    );

    // Base URL for trajectory links.
    $trajbase = new moodle_url('/question/type/coderunner/scripts/quiztrajectory.php', [
        'mode'       => 'trajectory',
        'courseid'   => $courseid,
        'quizid'     => $quizid,
        'gapminutes' => $gapminutes,
        'attemptnum' => $attemptnum,
    ]);

    // Sticky header + sticky first column.
    // position:sticky on table cells only works when no ancestor has
    // overflow:auto/hidden, so we let the page itself scroll and apply
    // sticky directly against the viewport.
    echo '<style>
#quiztrajectorytable {
    border-collapse: separate;
    border-spacing: 0;
    width: auto;
}
#quiztrajectorytable thead tr th {
    position: sticky;
    top: 0;
    background: #f0f0f0;
    z-index: 20;
    box-shadow: 0 2px 3px rgba(0,0,0,0.12);
    white-space: nowrap;
    padding: 4px 8px;
}
#quiztrajectorytable thead tr th:first-child {
    left: 0;
    z-index: 30;
}
#quiztrajectorytable tbody tr td:first-child,
#quiztrajectorytable tfoot tr td:first-child {
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 10;
    box-shadow: 2px 0 3px rgba(0,0,0,0.08);
    white-space: nowrap;
    padding: 4px 8px;
}
#quiztrajectorytable tbody tr:nth-child(even) td:first-child {
    background: #f9f9f9;
}
</style>';

    // Build table.
    $table = new html_table();
    $table->attributes = ['class' => 'generaltable', 'id' => 'quiztrajectorytable'];
    $table->head = array_merge(['Student'], $slotlabels, ['Total (min)']);

    foreach ($students as $uid => $student) {
        $data = $alldata[$uid];
        $n = (int)($attemptcounts[$uid] ?? 0);
        $attemptstr = $n > 0 ? ' (' . $n . ' ' . ($n === 1 ? 'attempt' : 'attempts') . ')' : '';
        $name = s($student->lastname . ', ' . $student->firstname);
        $trajurl = new moodle_url($trajbase, ['studentid' => $uid]);
        $namelink = html_writer::link($trajurl, $name) . html_writer::tag('small', $attemptstr);

        $row   = [$namelink];
        $total = 0.0;
        $hastotal = false;

        foreach ($questions as $q) {
            if (!$data) {
                $row[] = '&mdash;';
                continue;
            }
            $obj = $data->questions[$q->slot];
            if ($obj->time_on_q !== null) {
                $mins = $obj->time_on_q / 60;
                $row[] = number_format($mins, 1);
                $total    += $mins;
                $hastotal  = true;
            } else {
                $row[] = '&mdash;';
            }
        }
        $row[] = $hastotal ? number_format($total, 1) : '&mdash;';
        $table->data[] = $row;
    }

    // Average row.
    $avgrow = [html_writer::tag('strong', 'Average')];
    $grandtotal = 0.0;
    $grandcount = 0;
    foreach ($colsums as $i => $sum) {
        if ($colcounts[$i] > 0) {
            $avg = $sum / $colcounts[$i];
            $avgrow[] = html_writer::tag('strong', number_format($avg, 1));
            $grandtotal += $avg;
            $grandcount++;
        } else {
            $avgrow[] = '&mdash;';
        }
    }
    $avgrow[] = html_writer::tag(
        'strong',
        $grandcount > 0 ? number_format($grandtotal, 1) : '&mdash;'
    );
    $table->data[] = $avgrow;

    // CSV export — build and offer download if requested.
    $csvdownload = optional_param('csvdownload', 0, PARAM_INT);
    if ($csvdownload) {
        $quizobj = $DB->get_record('quiz', ['id' => $quizid], 'name', MUST_EXIST);
        $filename = 'quiz_times_' . clean_filename($quizobj->name) . '_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_merge(['Student'], $slotlabels, ['Total (min)']));
        foreach ($students as $uid => $student) {
            $data = $alldata[$uid];
            $row  = [$student->lastname . ', ' . $student->firstname];
            $total = 0.0;
            $hastotal = false;
            foreach ($questions as $q) {
                if (!$data) {
                    $row[] = '';
                    continue;
                }
                $obj = $data->questions[$q->slot];
                if ($obj->time_on_q !== null) {
                    $mins = $obj->time_on_q / 60;
                    $row[] = number_format($mins, 1);
                    $total += $mins;
                    $hastotal = true;
                } else {
                    $row[] = '';
                }
            }
            $row[] = $hastotal ? number_format($total, 1) : '';
            fputcsv($out, $row);
        }
        // Average row.
        $avgrow = ['Average'];
        $grandtotal = 0.0;
        $grandcount = 0;
        foreach ($colsums as $i => $sum) {
            if ($colcounts[$i] > 0) {
                $avg = $sum / $colcounts[$i];
                $avgrow[] = number_format($avg, 1);
                $grandtotal += $avg;
                $grandcount++;
            } else {
                $avgrow[] = '';
            }
        }
        $avgrow[] = $grandcount > 0 ? number_format($grandtotal, 1) : '';
        fputcsv($out, $avgrow);
        fclose($out);
        exit;
    }

    // CSV download link.
    $csvurl = new moodle_url('/question/type/coderunner/scripts/quiztrajectory.php', [
        'courseid'    => $courseid,
        'quizid'      => $quizid,
        'gapminutes'  => $gapminutes,
        'attemptnum'  => $attemptnum,
        'csvdownload' => 1,
    ]);
    echo html_writer::tag(
        'p',
        html_writer::link($csvurl, 'Download as CSV', ['class' => 'btn btn-sm btn-secondary'])
    );

    echo html_writer::tag(
        'p',
        'Times are cumulative active minutes spent on each question. ' .
        'Click a student\'s name to view their mark trajectory.'
    );
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
