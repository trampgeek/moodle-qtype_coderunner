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
 * CodeRunner Management Dashboard
 * Central access point for all CodeRunner management and administration tools.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

// Login and check permissions.
// When reached via the course navigation link, courseid is provided and the
// capability is checked in that course context (so editing teachers pass).
// When accessed directly without a course, site-admin access is required.
// Teachers who arrive without courseid (e.g. via a bookmarked direct URL) get
// a friendly explanation rather than a raw access-denied exception.
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    require_capability('qtype/coderunner:management', $context);
} else {
    require_login();
    $context = context_system::instance();
    if (!has_capability('qtype/coderunner:management', $context)) {
        $PAGE->set_url('/question/type/coderunner/management.php');
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('mgmt_page_title', 'qtype_coderunner'));
        $PAGE->set_heading(get_string('mgmt_page_heading', 'qtype_coderunner'));
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('mgmt_needs_courseid', 'qtype_coderunner'),
            'notifymessage'
        );
        echo html_writer::link(
            new moodle_url('/course/index.php'),
            get_string('courses')
        );
        echo $OUTPUT->footer();
        exit;
    }
}

// Set up page.
$PAGE->set_url('/question/type/coderunner/management.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('mgmt_page_title', 'qtype_coderunner'));
$PAGE->set_heading(get_string('mgmt_page_heading', 'qtype_coderunner'));

// Display.
echo $OUTPUT->header();

echo html_writer::tag(
    'p',
    get_string('mgmt_intro', 'qtype_coderunner')
);

/**
 * Display a tool card.
 *
 * @param string $title Tool title
 * @param string $description Tool description
 * @param string $url URL to the tool
 * @param string $icon Icon class (optional)
 * @param string $color Card color theme (default, primary, success, info, warning, danger)
 */
function display_tool_card($title, $description, $url, $icon = '', $color = 'default') {
    $colorclasses = [
        'default' => 'border-secondary',
        'primary' => 'border-primary',
        'success' => 'border-success',
        'info' => 'border-info',
        'warning' => 'border-warning',
        'danger' => 'border-danger',
    ];
    $borderclass = $colorclasses[$color] ?? $colorclasses['default'];

    echo html_writer::start_tag('div', ['class' => 'col-md-6 col-lg-4 mb-3']);
    echo html_writer::start_tag('div', ['class' => "card h-100 $borderclass", 'style' => 'border-width: 2px;']);
    echo html_writer::start_tag('div', ['class' => 'card-body']);

    if ($icon) {
        echo html_writer::tag('i', '', ['class' => "$icon fa-2x mb-2", 'style' => 'color: #0073aa;']);
    }

    $toolurl = new moodle_url($url);
    echo html_writer::tag('h5', html_writer::link($toolurl, $title), ['class' => 'card-title']);
    echo html_writer::tag('p', $description, ['class' => 'card-text']);
    echo html_writer::link(
        $toolurl,
        get_string('mgmt_open_tool', 'qtype_coderunner'),
        ['class' => 'btn btn-primary btn-sm']
    );

    echo html_writer::end_tag('div'); // Card-body.
    echo html_writer::end_tag('div'); // Card.
    echo html_writer::end_tag('div'); // Col.
}

/**
 * Display a category header.
 *
 * @param string $title Category title
 * @param string $description Category description
 */
function display_category($title, $description) {
    echo html_writer::tag('h3', $title, ['class' => 'mt-4 mb-2']);
    echo html_writer::tag('p', $description, ['class' => 'text-muted mb-3']);
}

// Testing & Validation.
display_category(
    get_string('mgmt_cat_testing_title', 'qtype_coderunner'),
    get_string('mgmt_cat_testing_desc', 'qtype_coderunner')
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    get_string('mgmt_tool_bulktester_title', 'qtype_coderunner'),
    get_string('mgmt_tool_bulktester_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/bulktest.php',
    'fa fa-flask',
    'primary'
);

display_tool_card(
    get_string('mgmt_tool_questionbrowser_title', 'qtype_coderunner'),
    get_string('mgmt_tool_questionbrowser_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/questionbrowser.php',
    'fa fa-search',
    'info'
);

echo html_writer::end_tag('div'); // Row.

// Maintenance.
display_category(
    get_string('mgmt_cat_maintenance_title', 'qtype_coderunner'),
    get_string('mgmt_cat_maintenance_desc', 'qtype_coderunner')
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    get_string('mgmt_tool_deleteold_title', 'qtype_coderunner'),
    get_string('mgmt_tool_deleteold_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/deleteoldquestionversions.php',
    'fa fa-trash',
    'warning'
);

display_tool_card(
    get_string('mgmt_tool_cachepurge_title', 'qtype_coderunner'),
    get_string('mgmt_tool_cachepurge_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/cachepurge.php',
    'fa fa-refresh',
    'default'
);

echo html_writer::end_tag('div'); // Row.

// Data Export.
display_category(
    get_string('mgmt_cat_dataexport_title', 'qtype_coderunner'),
    get_string('mgmt_cat_dataexport_desc', 'qtype_coderunner')
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    get_string('mgmt_tool_downloadattempts_title', 'qtype_coderunner'),
    get_string('mgmt_tool_downloadattempts_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/downloadquizattempts.php',
    'fa fa-download',
    'success'
);

display_tool_card(
    get_string('mgmt_tool_downloadattempts_anon_title', 'qtype_coderunner'),
    get_string('mgmt_tool_downloadattempts_anon_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/downloadquizattemptsanon.php',
    'fa fa-user-secret',
    'success'
);

echo html_writer::end_tag('div'); // Row.

// Prototype Management.
display_category(
    get_string('mgmt_cat_prototype_title', 'qtype_coderunner'),
    get_string('mgmt_cat_prototype_desc', 'qtype_coderunner')
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    get_string('mgmt_tool_prototypeusage_title', 'qtype_coderunner'),
    get_string('mgmt_tool_prototypeusage_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/prototypeusage.php',
    'fa fa-pie-chart',
    'primary'
);

echo html_writer::end_tag('div'); // Row.

// Analytics.
display_category(
    get_string('mgmt_cat_analytics_title', 'qtype_coderunner'),
    get_string('mgmt_cat_analytics_desc', 'qtype_coderunner')
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    get_string('mgmt_tool_analytics_studenttime_title', 'qtype_coderunner'),
    get_string('mgmt_tool_analytics_studenttime_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/studenttimeanalysis.php',
    'fa fa-clock-o',
    'info'
);

display_tool_card(
    get_string('mgmt_tool_analytics_quiztrajectory_title', 'qtype_coderunner'),
    get_string('mgmt_tool_analytics_quiztrajectory_desc', 'qtype_coderunner'),
    '/question/type/coderunner/scripts/quiztrajectory.php',
    'fa fa-line-chart',
    'info'
);

echo html_writer::end_tag('div'); // Row.

// Add custom styling for cards.
echo <<<HTML
<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.card-title {
    color: #0073aa;
    font-weight: bold;
}
.fa {
    display: block;
    margin-bottom: 10px;
}
h3 {
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 8px;
}
</style>
HTML;

echo $OUTPUT->footer();
