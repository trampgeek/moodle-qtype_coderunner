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

use context_system;
use html_writer;
use moodle_url;

// Login and check permissions.
$context = context_system::instance();
require_login();

// Set up page.
$PAGE->set_url('/question/type/coderunner/management.php');
$PAGE->set_context($context);
$PAGE->set_title('CodeRunner Management');
$PAGE->set_heading('CodeRunner Management Dashboard');

// Display.
echo $OUTPUT->header();

echo html_writer::tag(
    'p',
    'Central access point for CodeRunner administration and management tools. ' .
    'Select a category below to access the tools you need.'
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

    echo html_writer::tag('h5', $title, ['class' => 'card-title']);
    echo html_writer::tag('p', $description, ['class' => 'card-text']);

    $toolurl = new moodle_url($url);
    echo html_writer::link(
        $toolurl,
        'Open Tool →',
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
    'Testing & Validation',
    'Tools for testing questions and browsing question banks.'
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    'Bulk Tester',
    'Test multiple CodeRunner questions in bulk to verify they work correctly. ' .
    'Run all tests for selected subsets of questions and see detailed results.',
    '/question/type/coderunner/bulktestindex.php',
    'fa fa-flask',
    'primary'
);

display_tool_card(
    'Question Browser',
    'Browse and search through CodeRunner questions. View question text, ' .
    'answer, tags and quiz usage. Preview questions or edit them in the question ' .
    'bank. Useful for finding and reviewing questions.',
    '/question/type/coderunner/questionbrowserindex.php',
    'fa fa-search',
    'info'
);

echo html_writer::end_tag('div'); // Row.

// Maintenance.
display_category(
    'Maintenance',
    'Tools for maintaining question banks and database integrity.'
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    'Delete Old Question Versions',
    'Clean up bloated question banks by deleting old question versions. ' .
    'Works for all question types. Keeps only the most recent version of each question.',
    '/question/type/coderunner/deleteoldquestionversionsindex.php',
    'fa fa-trash',
    'warning'
);

display_tool_card(
    'Cache Purge',
    'Clear the CodeRunner job runs cache for selected courses or contexts. ' .
    'Reduces disk space usage. Required after updates to Jobe servers that might ' .
    'alter run results.',
    '/question/type/coderunner/cachepurgeindex.php',
    'fa fa-refresh',
    'default'
);

echo html_writer::end_tag('div'); // Row.

// Data Export.
display_category(
    'Data Export',
    'Tools for exporting quiz attempt data for analysis and research.'
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    'Download Quiz Attempts',
    'Export quiz attempt data for analysis. Download student submissions, ' .
    'code responses, and results in various formats for grading review and analysis.',
    '/question/type/coderunner/downloadquizattempts.php',
    'fa fa-download',
    'success'
);

display_tool_card(
    'Download Quiz Attempts (Anonymized)',
    'Export anonymized quiz attempt data for research purposes. ' .
    'Removes personally identifiable information for use in studies subject to ethics approval.',
    '/question/type/coderunner/downloadquizattemptsanon.php',
    'fa fa-user-secret',
    'success'
);

echo html_writer::end_tag('div'); // Row.

// Prototype Management.
display_category(
    'Prototype Management',
    'Tools for managing and analyzing CodeRunner question prototypes.'
);
echo html_writer::start_tag('div', ['class' => 'row']);

display_tool_card(
    'Prototype Usage',
    'Analyze which prototypes are being used across your question banks. ' .
    'See usage statistics and identify unused prototypes.',
    '/question/type/coderunner/prototypeusageindex.php',
    'fa fa-pie-chart',
    'primary'
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
