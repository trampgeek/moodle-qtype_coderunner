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
 * Moodle server-activity visualiser.
 *
 * Plots the number of distinct active IP addresses per hour for a selected
 * course, with optional filtering by group, activity, date range and an
 * IP-address regular expression.
 *
 * Access it at:
 *   https://yourserver/question/type/coderunner/scripts/activityviz.php
 *
 * Requirements:
 *   - User must be logged in to Moodle.
 *   - User must have moodle/course:viewhiddenuserfields capability in the
 *     selected course (i.e. be a teacher, editing teacher, or manager).
 *
 * @package    qtype_coderunner
 * @copyright  2026 Paul McKeown, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();   // Redirects to login page if not authenticated.

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/question/type/coderunner/scripts/activityviz.php'));
$PAGE->set_title('Course Activity Visualiser');
$PAGE->set_pagelayout('base');


/**
 * Get all courses in which the current user has teacher-level access,
 * defined as holding the moodle/course:viewhiddenuserfields capability.
 *
 * @return stdClass[] course records, sorted by fullname.
 */
function qtype_coderunner_activityviz_teacher_courses() {
    global $USER;
    $courses = enrol_get_all_users_courses($USER->id, true);
    $teachercourses = [];
    foreach ($courses as $course) {
        $context = context_course::instance($course->id);
        if (has_capability('moodle/course:viewhiddenuserfields', $context)) {
            $teachercourses[] = $course;
        }
    }
    usort($teachercourses, fn($a, $b) => strcmp($a->fullname, $b->fullname));
    return $teachercourses;
}


/**
 * Check whether the given string is a valid PCRE regular expression body.
 *
 * The pattern is tested inside '/.../' delimiters. Uses a temporary error
 * handler rather than the '@' operator, which Moodle style discourages.
 *
 * @param string $regex the regular expression body to check.
 * @return bool true if the regex is valid.
 */
function qtype_coderunner_activityviz_regex_valid(string $regex): bool {
    $isvalid = true;
    set_error_handler(function () use (&$isvalid) {
        $isvalid = false;
    });
    preg_match('/' . $regex . '/', '');
    restore_error_handler();
    return $isvalid;
}


/**
 * Build the JSON chart data for the given filters.
 *
 * The result is a JSON object {"timestamps": [...], "counts": [...]} where
 * timestamps are Unix hour-boundary timestamps and counts are the number of
 * distinct IP addresses seen in that hour. Returns the string 'null' if the
 * date range is invalid, so the page renders its "select a range" prompt.
 *
 * @param int $courseid the course to report on.
 * @param context_course $coursecontext the course context (used for group membership).
 * @param int $groupid group to filter by, or 0 for all groups.
 * @param int $cmid course module to filter by, or 0 for all activities.
 * @param string $datefrom start date (YYYY-MM-DD).
 * @param string $dateto end date (YYYY-MM-DD).
 * @param string $ipregex regular expression that IPs must match, or '' / '.*' for all.
 * @return string JSON chart data, or 'null' if the date range is invalid.
 */
function qtype_coderunner_activityviz_chart_json(
    int $courseid,
    context_course $coursecontext,
    int $groupid,
    int $cmid,
    string $datefrom,
    string $dateto,
    string $ipregex
): string {
    global $DB;

    // Convert dates to Unix timestamps (start of day / end of day).
    $tsfrom = strtotime($datefrom . ' 00:00:00');
    $tsto = strtotime($dateto . ' 23:59:59');
    if ($tsfrom === false || $tsto === false || $tsfrom > $tsto) {
        return 'null';
    }

    // Build the SQL query. We want, for each hour bucket, the set of
    // distinct IPs. Strategy: aggregate (hour, ip) pairs in the DB, then
    // apply the IP regex and bucket in PHP (avoids DB-specific regex and
    // date functions).
    $params = [$courseid, $tsfrom, $tsto];
    $where = "l.courseid = ? AND l.timecreated >= ? AND l.timecreated <= ?";

    // Filter by group: get userids for the group, add to WHERE.
    if ($groupid > 0) {
        $memberids = array_keys(get_enrolled_users($coursecontext, '', $groupid, 'u.id'));
        if (empty($memberids)) {
            // Group has no members, so there is nothing to plot.
            return '{"timestamps":[],"counts":[]}';
        }
        [$insql, $inparams] = $DB->get_in_or_equal($memberids);
        $where .= " AND l.userid $insql";
        $params = array_merge($params, $inparams);
    }

    // Filter by activity (course module => contextinstanceid in log).
    if ($cmid > 0) {
        $where .= " AND l.contextinstanceid = ? AND l.contextlevel = ?";
        $params[] = $cmid;
        $params[] = CONTEXT_MODULE;
    }

    // Aggregate in the DB: one row per (hourts, ip) pair.
    // FLOOR(timecreated/3600)*3600 gives the Unix ts of the hour boundary.
    // This is vastly faster than streaming every log row to PHP.
    $sql = "SELECT FLOOR(l.timecreated/3600)*3600 AS hourts, l.ip
              FROM {logstore_standard_log} l
             WHERE $where
               AND l.ip IS NOT NULL AND l.ip <> ''
          GROUP BY hourts, l.ip
          ORDER BY hourts ASC";

    $rs = $DB->get_recordset_sql($sql, $params);

    // Bucket into hourly slots. Each row is already one distinct (hour, ip)
    // pair from the DB; the IP regex filter is applied here in PHP.
    $ipre = ($ipregex !== '' && $ipregex !== '.*') ? '/' . $ipregex . '/' : null;
    $buckets = [];   // Maps hourts => set of IPs (stored as assoc array keys).
    foreach ($rs as $row) {
        if ($ipre !== null && !preg_match($ipre, $row->ip)) {
            continue;
        }
        $hourts = (int) $row->hourts;
        if (!isset($buckets[$hourts])) {
            $buckets[$hourts] = [];
        }
        $buckets[$hourts][$row->ip] = true;
    }
    $rs->close();   // Always close recordsets to free the DB cursor.

    // Fill gaps so the chart is continuous, covering the full requested
    // range: start from midnight of $datefrom and end at the last hour of
    // $dateto, so trailing hours with no activity show as zero instead of
    // truncating the chart early.
    if (!empty($buckets)) {
        $minhour = strtotime($datefrom . ' 00:00:00');
        $maxhour = strtotime($dateto . ' 23:00:00');
        for ($h = $minhour; $h <= $maxhour; $h += 3600) {
            if (!isset($buckets[$h])) {
                $buckets[$h] = [];
            }
        }
        ksort($buckets);
    }

    // Build parallel timestamp and count arrays for Chart.js.
    $timestamps = [];
    $counts = [];
    foreach ($buckets as $hourts => $ips) {
        $timestamps[] = $hourts;
        $counts[] = count($ips);
    }

    return json_encode(['timestamps' => $timestamps, 'counts' => $counts]);
}


/**
 * Return the static CSS for the page.
 *
 * @return string a complete <style> element.
 */
function qtype_coderunner_activityviz_css(): string {
    return <<<'CSS'
<style>
  #av-wrap {
    max-width: 1020px;
    margin: 0 auto;
    padding: 24px 16px 48px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #212529;
  }

  #av-wrap h1 {
    font-size: clamp(20px, 3.5vw, 30px);
    font-weight: 700;
    color: #212529;
    margin: 0 0 4px;
  }

  #av-wrap .av-subtitle {
    color: #6c757d;
    font-size: 13px;
    margin-bottom: 28px;
  }

  /* Panel. */
  .av-panel {
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 18px 22px;
    margin-bottom: 18px;
  }

  .av-panel-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6c757d;
    margin-bottom: 12px;
  }

  /* Form grid. */
  .av-form-row {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
  }

  .av-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
    flex: 1;
    min-width: 160px;
  }

  .av-field label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6c757d;
  }

  .av-field .av-checkbox-label {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 2px;
    text-transform: none;
    letter-spacing: normal;
    font-weight: 400;
    font-size: 12px;
    cursor: pointer;
  }

  .av-field select {
    background: #ffffff;
    border: 1px solid #ced4da;
    border-radius: 6px;
    color: #212529;
    font-size: 13px;
    padding: 8px 10px;
    outline: none;
    width: 100%;
    cursor: pointer;
  }

  .av-field input[type=date] {
    background: #ffffff;
    border: 1px solid #ced4da;
    border-radius: 6px;
    color: #212529;
    font-size: 13px;
    padding: 8px 10px;
    outline: none;
    width: 160px;
    cursor: pointer;
  }

  .av-field select:focus,
  .av-field input[type=date]:focus {
    border-color: #0f6cbf;
    box-shadow: 0 0 0 0.2rem rgba(15, 108, 191, 0.25);
  }

  .av-btn {
    background: #0f6cbf;
    border: 1px solid #0f6cbf;
    border-radius: 6px;
    color: #ffffff;
    font-size: 13px;
    font-weight: 500;
    padding: 9px 22px;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
    align-self: flex-end;
  }
  .av-btn:hover { background: #0c5696; border-color: #0c5696; }

  /* Stats strip. */
  .av-stats {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }

  .av-stat {
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px 18px;
    flex: 1;
    min-width: 110px;
  }

  .av-stat-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6c757d;
  }

  .av-stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #0f6cbf;
    margin-top: 2px;
  }

  /* Chart area. */
  .av-chart-box {
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 24px 16px 16px;
    min-height: 320px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
  }

  .av-placeholder {
    color: #adb5bd;
    font-size: 14px;
    text-align: center;
  }

  .av-footer-note {
    margin-top: 12px;
    color: #adb5bd;
    font-size: 11px;
    text-align: center;
  }

  /* Notice. */
  .av-notice {
    background: #cff4fc;
    border: 1px solid #9eeaf9;
    border-radius: 6px;
    color: #055160;
    font-size: 13px;
    padding: 10px 16px;
    margin-bottom: 16px;
  }
</style>
CSS;
}


/**
 * Build the <option> elements for a select control.
 *
 * @param array $options map from value => display label.
 * @param mixed $selected the currently selected value.
 * @return string the HTML for the option list.
 */
function qtype_coderunner_activityviz_options(array $options, $selected): string {
    $html = '';
    foreach ($options as $value => $label) {
        $selectedattr = $value == $selected ? ' selected' : '';
        $html .= '<option value="' . (int) $value . '"' . $selectedattr . '>' .
                s($label) . "</option>\n";
    }
    return $html;
}


/**
 * Build the filter-form HTML (course/group/activity selectors, date range,
 * IP regex and update button).
 *
 * @param stdClass[] $allcourses courses available to the current user.
 * @param stdClass[] $groups groups in the selected course.
 * @param string[] $activities map from cmid => activity display name.
 * @param int $courseid currently selected course id (0 for none).
 * @param int $groupid currently selected group id (0 for all).
 * @param int $cmid currently selected course module id (0 for all).
 * @param string $datefrom start date (YYYY-MM-DD).
 * @param string $dateto end date (YYYY-MM-DD).
 * @param string $ipregex the current IP regex filter.
 * @param bool $ipregexvalid false if the user-supplied regex was invalid.
 * @param bool $quizonly whether the activity list is restricted to quizzes.
 * @return string the form HTML.
 */
function qtype_coderunner_activityviz_form(
    array $allcourses,
    array $groups,
    array $activities,
    int $courseid,
    int $groupid,
    int $cmid,
    string $datefrom,
    string $dateto,
    string $ipregex,
    bool $ipregexvalid,
    bool $quizonly
): string {
    $courselabels = [0 => '— select a course —'];
    foreach ($allcourses as $course) {
        $courselabels[$course->id] = $course->fullname;
    }
    $courseoptions = qtype_coderunner_activityviz_options($courselabels, $courseid);

    // The group/activity/date/regex controls appear only once a course is chosen.
    $filterfields = '';
    if ($courseid > 0) {
        $grouplabels = [0 => 'All groups'];
        foreach ($groups as $group) {
            $grouplabels[$group->id] = $group->name;
        }
        $groupoptions = qtype_coderunner_activityviz_options($grouplabels, $groupid);
        $activityoptions = qtype_coderunner_activityviz_options(
            [0 => 'All activities'] + $activities,
            $cmid
        );

        $datefromesc = s($datefrom);
        $datetoesc = s($dateto);
        $ipregexesc = s($ipregex);
        $quizonlychecked = $quizonly ? ' checked' : '';
        $regexwarning = '';
        if (!$ipregexvalid) {
            $regexwarning = <<<'HTML'
          <span style="color:#dc3545; font-size:11px">
            &#9888; Invalid regex &mdash; using .*
          </span>
HTML;
        }

        $filterfields = <<<HTML
        <!-- Group selector -->
        <div class="av-field">
          <label for="av-group">Group</label>
          <select name="groupid" id="av-group">
            {$groupoptions}
          </select>
        </div>

        <!-- Activity selector -->
        <div class="av-field" style="min-width:220px; flex:2">
          <label for="av-activity">Activity</label>
          <select name="cmid" id="av-activity">
            {$activityoptions}
          </select>
          <!-- quiz_only_shown tells a real uncheck (box was on the page,
               but quiz_only is missing from the submission) apart from the
               box simply not having existed yet on the previous page. -->
          <input type="hidden" name="quiz_only_shown" value="1">
          <label class="av-checkbox-label" for="av-quizonly">
            <input type="checkbox" name="quiz_only" id="av-quizonly" value="1"{$quizonlychecked}
                   onchange="this.form.submit()">
            Quizzes only
          </label>
        </div>

      </div><!-- .av-form-row (row 1) -->

      <!-- Row 2: date range + IP filter + submit -->
      <div class="av-form-row" style="margin-top:12px; flex-wrap:wrap; align-items:flex-end; gap:12px">

        <!-- Date range -->
        <div class="av-field" style="flex:0 0 auto">
          <label for="av-from">From</label>
          <input type="date" name="date_from" id="av-from" value="{$datefromesc}">
        </div>

        <div class="av-field" style="flex:0 0 auto">
          <label for="av-to">To</label>
          <input type="date" name="date_to" id="av-to" value="{$datetoesc}">
        </div>

        <!-- IP regex filter -->
        <div class="av-field" style="flex:0 0 auto">
          <label for="av-ipregex">IP Address Regex</label>
          <input type="text" name="ip_regex" id="av-ipregex"
                 value="{$ipregexesc}"
                 placeholder="e.g. 10\.67\.28\..*"
                 spellcheck="false"
                 style="font-family:SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:12px; width:200px">
{$regexwarning}
        </div>

        <div style="flex:0 0 auto; align-self:flex-end">
          <button type="submit" class="av-btn">Update</button>
        </div>
HTML;
    } else {
        // No course selected yet: close row 1 so the form markup stays balanced.
        $filterfields = "      </div><!-- .av-form-row (row 1) -->\n      <div class=\"av-form-row\">";
    }

    return <<<HTML
  <!-- Filter form -->
  <form method="get" action="">
    <div class="av-panel">
      <div class="av-panel-title">Course &amp; Filters</div>
      <div class="av-form-row">

        <!-- Course selector -->
        <div class="av-field" style="min-width:220px; flex:2">
          <label for="av-course">Course</label>
          <select name="courseid" id="av-course" onchange="this.form.submit()">
            {$courseoptions}
          </select>
        </div>

{$filterfields}

      </div><!-- .av-form-row (row 2) -->
    </div><!-- .av-panel -->
  </form>
HTML;
}


/**
 * Build the stats strip and chart box HTML.
 *
 * @param int $courseid the selected course id (0 for none).
 * @param string $chartjson the chart data JSON, or 'null' if none available.
 * @return string the HTML.
 */
function qtype_coderunner_activityviz_chart_box(int $courseid, string $chartjson): string {
    if ($courseid <= 0) {
        return <<<'HTML'
    <div class="av-chart-box">
      <div class="av-placeholder">Select a course above to begin.</div>
    </div>
HTML;
    }
    $placeholder = $chartjson === 'null'
            ? 'Select a date range and click Update.'
            : 'Loading chart&hellip;';
    return <<<HTML
    <div class="av-stats" id="av-stats" style="display:none">
      <div class="av-stat">
        <div class="av-stat-label">Hours shown</div>
        <div class="av-stat-value" id="stat-hours">&mdash;</div>
      </div>
      <div class="av-stat">
        <div class="av-stat-label">Peak IPs / hour</div>
        <div class="av-stat-value" id="stat-peak">&mdash;</div>
      </div>
      <div class="av-stat">
        <div class="av-stat-label">IP-hours total</div>
        <div class="av-stat-value" id="stat-total">&mdash;</div>
      </div>
    </div>

    <div class="av-chart-box">
      <div class="av-placeholder" id="av-placeholder">{$placeholder}</div>
      <canvas id="av-chart" style="display:none; width:100%; max-height:400px"></canvas>
    </div>
    <p class="av-footer-note" id="av-footer-note">
      Tick marks every 8 h &middot; date shown at midnight &middot; hover any bar for exact count
    </p>
HTML;
}


/**
 * Build the Chart.js loader plus the chart-rendering script.
 *
 * The chart data is injected as a JSON literal; everything else is static.
 *
 * @param string $chartjson the chart data JSON.
 * @return string the HTML script elements plus trailing style overrides.
 */
function qtype_coderunner_activityviz_script(string $chartjson): string {
    $prologue = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js">' .
            "</script>\n<script>\n(function () {\n  const raw = " . $chartjson . ";";
    return $prologue . <<<'JS'

  if (!raw || !raw.timestamps || raw.timestamps.length === 0) {
    document.getElementById('av-placeholder').textContent =
      'No log data found for the selected filters.';
    return;
  }

  // Build labels and expose hour/isNewDay metadata.

  const timestamps = raw.timestamps;   // Unix timestamps (seconds), one per hour
  const counts     = raw.counts;

  const labels = timestamps.map(ts => {
    const d  = new Date(ts * 1000);
    const hh = d.getHours();
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return { ts, hh, isNewDay: hh === 0, dateLabel: `${dd}/${mm}`,
             fullLabel: `${dd}/${mm} ${String(hh).padStart(2,'0')}:00` };
  });

  // Stats.

  const peak  = Math.max(...counts);
  const total = counts.reduce((a, b) => a + b, 0);
  document.getElementById('stat-hours').textContent = counts.length;
  document.getElementById('stat-peak').textContent  = peak;
  document.getElementById('stat-total').textContent = total.toLocaleString();
  document.getElementById('av-stats').style.display = 'flex';

  // Show canvas, hide placeholder.

  document.getElementById('av-placeholder').style.display = 'none';
  const canvas = document.getElementById('av-chart');
  canvas.style.display = 'block';

  // Custom X-axis tick plugin.
  // Draws our own tick marks + labels at midnight (date+00) and every 8 h.
  // Chart.js built-in ticks are hidden via ticks.display:false.
  // The 8-hourly sub-ticks are dropped once the range spans too many days
  // for them to stay legible, leaving just the day-boundary ticks.

  const numDays = timestamps.length / 24;
  const showHourTicks = numDays <= 15;
  document.getElementById('av-footer-note').textContent = showHourTicks
    ? 'Tick marks every 8 h · date shown at midnight · hover any bar for exact count'
    : 'Tick marks at midnight (range too long to also show hourly ticks) · hover any bar for exact count';

  const customXTicks = {
    id: 'customXTicks',
    afterDraw(chart) {
      const { ctx, scales: { x, y } } = chart;
      const axisY = y.bottom;          // pixel Y of the x-axis line
      const FONT_SANS = "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";

      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';

      labels.forEach((lbl, i) => {
        if (!lbl.isNewDay && !showHourTicks) return;   // sub-ticks hidden on long ranges
        if (lbl.hh % 8 !== 0) return;   // only every 8 hours

        const xPx = x.getPixelForValue(i);

        if (lbl.isNewDay) {
          // Tall accent tick line
          ctx.beginPath();
          ctx.moveTo(xPx, axisY);
          ctx.lineTo(xPx, axisY + 7);
          ctx.strokeStyle = '#0f6cbf';
          ctx.lineWidth = 2;
          ctx.stroke();
          // Hour "00"
          ctx.font = `500 11px ${FONT_SANS}`;
          ctx.fillStyle = '#495057';
          ctx.fillText('00', xPx, axisY + 10);
          // Date below
          ctx.font = `700 12px ${FONT_SANS}`;
          ctx.fillStyle = '#212529';
          ctx.fillText(lbl.dateLabel, xPx, axisY + 24);
        } else {
          // Shorter dim tick
          ctx.beginPath();
          ctx.moveTo(xPx, axisY);
          ctx.lineTo(xPx, axisY + 4);
          ctx.strokeStyle = '#ced4da';
          ctx.lineWidth = 1;
          ctx.stroke();
          // Hour number
          ctx.font = `400 11px ${FONT_SANS}`;
          ctx.fillStyle = '#6c757d';
          ctx.fillText(String(lbl.hh).padStart(2, '0'), xPx, axisY + 7);
        }
      });

      ctx.restore();
    }
  };

  // Chart.

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: labels.map(l => l.fullLabel),   // used in tooltip
      datasets: [{
        data: counts,
        backgroundColor: '#0f6cbf',
        hoverBackgroundColor: '#0c5696',
        borderRadius: 3,
        borderSkipped: 'bottom',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 400 },
      layout: { padding: { left: 8, right: 8, top: 8, bottom: 48 } },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#212529',
          borderWidth: 0,
          titleColor: '#ffffff',
          bodyColor: '#e9ecef',
          titleFont: { weight: '600', size: 13 },
          bodyFont:  { size: 13 },
          callbacks: {
            title: items => items[0].label,
            label: item => ` ${item.raw} distinct IP${item.raw !== 1 ? 's' : ''}`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          border: { color: '#dee2e6' },
          ticks: { display: false },   // we draw our own via the plugin
        },
        y: {
          beginAtZero: true,
          grid: { color: '#e9ecef', drawBorder: false },
          border: { display: false },
          ticks: {
            color: '#6c757d',
            font: { size: 11 },
            precision: 0,
          },
          title: {
            display: true,
            text: 'Distinct IPs',
            color: '#6c757d',
            font: { size: 11 }
          }
        }
      }
    },
    plugins: [customXTicks]
  });

})();
</script>

<!-- Give the canvas a fixed height (Chart.js needs a sized container) -->
<style>
  #av-chart { height: 400px !important; }
  /* Extra bottom padding so the custom x-axis tick labels (drawn below y.bottom)
     are not clipped by the canvas boundary. */
  .av-chart-box { min-height: 460px; align-items: stretch; padding-bottom: 8px; }
</style>
JS;
}


// Read form inputs.
$courseid = optional_param('courseid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);   // 0 = all groups.
$cmid = optional_param('cmid', 0, PARAM_INT);         // 0 = all activities.
$datefrom = optional_param('date_from', '', PARAM_ALPHANUMEXT);
$dateto = optional_param('date_to', '', PARAM_ALPHANUMEXT);
$ipregex = optional_param('ip_regex', '10\.67\.28\..*', PARAM_RAW);

// Quizzes-only defaults to checked, but browsers omit an unchecked checkbox
// from the submitted query string, so we can't just default-to-1 on absence
// (that would make an explicit uncheck impossible). The 'quiz_only_shown'
// hidden field (rendered next to the checkbox once it exists on the page)
// tells a real uncheck apart from the checkbox simply not having been on
// the previously rendered page yet (e.g. the course was just selected).
$quizonlyshown = optional_param('quiz_only_shown', 0, PARAM_INT) === 1;
$quizonly = optional_param('quiz_only', $quizonlyshown ? 0 : 1, PARAM_INT) === 1;

// Validate dates (expect YYYY-MM-DD).
$dateregex = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($dateregex, $datefrom)) {
    $datefrom = '';
}
if (!preg_match($dateregex, $dateto)) {
    $dateto = '';
}

// Validate IP regex, silently falling back to match-all if invalid.
$ipregexvalid = $ipregex === '' || qtype_coderunner_activityviz_regex_valid($ipregex);
if (!$ipregexvalid) {
    $ipregex = '.*';
}

// Security: verify teacher access to the selected course.
$coursecontext = null;
if ($courseid > 0) {
    $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
    if (
        !$coursecontext ||
            !has_capability('moodle/course:viewhiddenuserfields', $coursecontext)
    ) {
        $courseid = 0;   // Reset: user not authorised for this course.
        $coursecontext = null;
    }
}

// Fetch supporting data for dropdowns (only when a valid course is chosen).
$groups = [];
$activities = [];
if ($courseid > 0) {
    // Groups in this course.
    $groups = groups_get_all_groups($courseid);

    // Course modules with human-readable names. CodeRunner questions only
    // ever appear in quizzes, so "Quizzes only" is checked by default to
    // keep the list short; the module-type suffix is redundant once every
    // entry is a quiz, so it's only shown when other activity types are mixed in.
    $modinfo = get_fast_modinfo($courseid);
    foreach ($modinfo->get_cms() as $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        if ($quizonly && $cm->modname !== 'quiz') {
            continue;
        }
        $activities[$cm->id] = $quizonly
                ? $cm->get_formatted_name()
                : $cm->get_formatted_name() . ' (' . $cm->modname . ')';
    }
    asort($activities);

    // The previously selected activity may have been filtered out above.
    if ($cmid > 0 && !array_key_exists($cmid, $activities)) {
        $cmid = 0;
    }

    // Set default date range: last 7 days.
    if ($datefrom === '') {
        $datefrom = date('Y-m-d', strtotime('-7 days'));
    }
    if ($dateto === '') {
        $dateto = date('Y-m-d');
    }
}

// Build chart data (JSON).
$chartjson = 'null';
if ($courseid > 0 && $datefrom !== '' && $dateto !== '') {
    $chartjson = qtype_coderunner_activityviz_chart_json(
        $courseid,
        $coursecontext,
        $groupid,
        $cmid,
        $datefrom,
        $dateto,
        $ipregex
    );
}

// Fetch course list for the selector dropdown.
$allcourses = qtype_coderunner_activityviz_teacher_courses();

// Assemble the page body.
if (empty($allcourses)) {
    $body = <<<'HTML'
  <div class="av-notice">
    You do not appear to have teacher-level access to any courses.
  </div>
HTML;
} else {
    $body = qtype_coderunner_activityviz_form(
        $allcourses,
        $groups,
        $activities,
        $courseid,
        $groupid,
        $cmid,
        $datefrom,
        $dateto,
        $ipregex,
        $ipregexvalid,
        $quizonly
    );
    $body .= "\n" . qtype_coderunner_activityviz_chart_box($courseid, $chartjson);
}

// Output. The page uses Moodle's header/footer so that the Moodle nav bar
// and session remain intact.
echo $OUTPUT->header();
echo qtype_coderunner_activityviz_css();
echo <<<HTML
<div id="av-wrap">

  <h1>Course Activity <span style="color:#0f6cbf">Visualiser</span></h1>
  <p class="av-subtitle">Distinct active IPs per hour &middot; teacher access required</p>

{$body}

</div><!-- #av-wrap -->
HTML;
if ($courseid > 0 && $chartjson !== 'null') {
    echo qtype_coderunner_activityviz_script($chartjson);
}
echo $OUTPUT->footer();
