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
 * This admin script displays a lit of all quizzes in the course in which the
 * user is currently browsing and provides buttons for downloading each script
 * as either a csv (flakey at handling program code), ods or Excel spreadsheet.
 *
 * Although this script has been around for some years, it should be regarded
 * as somewhat experimental as the output format may
 * change in the future.
 *
 * @package   qtype_coderunner
 * @copyright 2017 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);
if (!defined('ANONYMISE')) {
    define('ANONYMISE', 0);
}

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Login and check permissions.
$context = context_system::instance();
require_login();

$PAGE->set_url('/question/type/coderunner/downloadquizattempts.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('downloadquizattempts', 'qtype_coderunner'));
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();
$courses = $bulktester->get_all_courses();

// Start display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('downloadquizattempts', 'qtype_coderunner'));
echo html_writer::tag('p', get_string('downloadquizattemptshelp', 'qtype_coderunner'));

$coursequizzes = [];

$i = 0;
if (count($courses) > 2) {
    $initialcontentstate = 'display:none; margin:20px';
} else {
    $initialcontentstate = 'margin:20px';
}

foreach ($courses as $course) {
    $courseid = $course->id;
    $coursecontext = context_course::instance($courseid);
    if (!has_capability('moodle/grade:viewall', $coursecontext)) {
        continue;
    }
    $quizzes = $DB->get_records_sql("
        SELECT
            id,
            name,
            numattempts
        FROM {quiz}
        JOIN (
            SELECT count(*) as numattempts, quiz as quizid
            FROM {quiz_attempts}
            WHERE state='finished'
            GROUP BY quizid
        ) sub
        ON sub.quizid = {quiz}.id
        WHERE course=:courseid
        AND sub.numattempts > 0
        ORDER BY name", ['courseid' => $courseid]);

    if (!empty($quizzes)) {
        $numquizzes = count($quizzes);

        echo html_writer::tag(
            'h6',
            html_writer::tag(
                'a',
                "{$course->name} ($numquizzes)",
                ['class' => 'expander sectionname',
                      'id'    => 'expander-' . $i,
                      'href'  => '#']
            )
        );
        echo html_writer::start_tag(
            'div',
            ['class' => 'content' . $i . ' container-fluid',
            'style' => $initialcontentstate]
        );

        $rows = [];
        foreach ($quizzes as $quiz) {
            $quizname = "{$quiz->name} ({$quiz->numattempts})";
            $csvurl = new moodle_url(
                '/question/type/coderunner/getallattempts.php',
                ['quizid' => $quiz->id, 'format' => 'csv', 'anonymise' => ANONYMISE]
            );
            $excelurl = new moodle_url(
                '/question/type/coderunner/getallattempts.php',
                ['quizid' => $quiz->id, 'format' => 'excel', 'anonymise' => ANONYMISE]
            );
            $odsurl = new moodle_url(
                '/question/type/coderunner/getallattempts.php',
                ['quizid' => $quiz->id, 'format' => 'ods', 'anonymise' => ANONYMISE]
            );
            $rows[] = [$quizname,
                    html_writer::link($csvurl, 'csv', ['class' => 'btn-sm']),
                    html_writer::link($odsurl, 'ods', ['class' => 'btn-sm']),
                    html_writer::link($excelurl, 'excel', ['class' => 'btn-sm']),
            ];
        }

        $table = new html_table();
        $table->data = $rows;
        $table->attributes['class'] = 'table-bordered';
        echo html_writer::table($table);
        echo html_writer::end_tag('div');
        $i += 1;
    }
}

$script = '$(".expander").click(function (e) { $(".content" + e.target.id.split("-")[1]).slideToggle("fast");});';
echo html_writer::tag('script', $script);

echo $OUTPUT->footer();
