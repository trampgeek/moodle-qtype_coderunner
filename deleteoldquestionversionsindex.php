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
 * Index page for deleting old question versions.
 * Lists contexts where the user can edit questions and provides links to delete old versions.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context_system;
use html_writer;
use moodle_url;
use qtype_coderunner_util;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/classes/bulk_tester.php');
require_once(__DIR__ . '/db/upgradelib.php');

// We are Moodle 4 or less if don't have mod_qbank.
$oldskool = !(qtype_coderunner_util::using_mod_qbank());

// Login and check permissions.
$context = context_system::instance();
require_login();

const BUTTONSTYLE = 'background-color: #FFFFD0; padding: 2px 2px 0px 2px; border: 4px solid white; margin-right: 4px;';
const DRYRUNSTYLE = 'background-color: #D0F0FF; padding: 2px 2px 0px 2px; border: 4px solid white;';

/**
 * Get count of questions with multiple versions in a context.
 *
 * @param int $contextid The context ID
 * @return int Number of question bank entries with multiple versions
 */
function get_multi_version_count($contextid) {
    global $DB;

    $sql = "SELECT COUNT(DISTINCT qbe.id)
              FROM {question_bank_entries} qbe
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              JOIN {context} ctx ON ctx.id = qc.contextid
             WHERE ctx.id = :contextid
          GROUP BY qbe.id
            HAVING COUNT(qv.id) > 1";

    // Count entries with multiple versions.
    $sql = "SELECT COUNT(*) as count
              FROM (
                  SELECT qbe.id
                    FROM {question_bank_entries} qbe
                    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                    JOIN {context} ctx ON ctx.id = qc.contextid
                   WHERE ctx.id = :contextid
                GROUP BY qbe.id
                  HAVING COUNT(qv.id) > 1
              ) subquery";

    $result = $DB->get_record_sql($sql, ['contextid' => $contextid]);
    return $result ? (int)$result->count : 0;
}

/**
 * Display header for a course context grouping.
 *
 * @param int $coursecontextid Course context ID
 * @param string $coursename Course name
 */
function display_course_header($coursecontextid, $coursename) {
    $litext = $coursecontextid . ' - ' . $coursename;
    echo html_writer::tag('h5', $litext);
}

/**
 * Display buttons for a context.
 *
 * @param int $contextid Context ID
 * @param string $name Context name
 * @param int $numcoderunnerquestions Number of CodeRunner questions (not used here)
 */
function display_context_with_buttons($contextid, $name, $numcoderunnerquestions) {
    // Get count of questions with multiple versions.
    $multiversioncount = get_multi_version_count($contextid);

    $dryrunurl = new moodle_url(
        '/question/type/coderunner/deleteoldquestionversions.php',
        ['contextid' => $contextid, 'dryrun' => 1]
    );
    $deleteurl = new moodle_url(
        '/question/type/coderunner/deleteoldquestionversions.php',
        ['contextid' => $contextid]
    );

    $dryrunlink = html_writer::link(
        $dryrunurl,
        'Dry Run',
        ['style' => DRYRUNSTYLE . 'cursor:pointer;text-decoration:none;', 'class' => 'btn btn-sm']
    );

    $deletelink = html_writer::link(
        $deleteurl,
        'Delete Old Versions',
        [
            'style' => BUTTONSTYLE . 'cursor:pointer;text-decoration:none;',
            'class' => 'btn btn-sm',
            'onclick' => 'return confirm("Are you sure you want to delete old question versions? ' .
                        'This action cannot be undone!");',
        ]
    );

    $integrityurl = new moodle_url(
        '/question/type/coderunner/checkquestionintegrity.php',
        ['contextid' => $contextid]
    );
    $integritylink = html_writer::link(
        $integrityurl,
        'Check Integrity',
        [
            'style' => 'background-color: #E0E0E0; padding: 2px 2px 0px 2px; ' .
                      'border: 4px solid white; margin-right: 4px;',
            'class' => 'btn btn-sm',
        ]
    );

    $statustext = $multiversioncount > 0
        ? html_writer::tag('strong', "$multiversioncount questions with old versions", ['style' => 'color: #856404;'])
        : html_writer::tag('span', 'No old versions to delete', ['style' => 'color: #155724;']);

    $litext = $contextid . ' - ' . $name . ' (' . $statustext . ') ' .
              $dryrunlink . ' ' . $deletelink . ' ' . $integritylink;

    if (strpos($name, ": Quiz: ") === false) {
        $class = 'deleteversions context normal';
    } else {
        $class = 'deleteversions context quiz';
    }

    echo html_writer::start_tag('li', ['class' => $class]);
    echo $litext;
    echo html_writer::end_tag('li');
}

// Set up page.
$PAGE->set_url('/question/type/coderunner/deleteoldquestionversionsindex.php');
$PAGE->set_context($context);
$PAGE->set_title('Delete Old Question Versions');
$PAGE->set_heading('Delete Old Question Versions');

// Display.
echo $OUTPUT->header();

echo html_writer::tag(
    'p',
    'This tool deletes all old versions of questions, keeping only the most recent version of each question. ' .
    'This works for ALL question types, not just CodeRunner.'
);

echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
echo html_writer::tag('strong', 'Warning: ');
echo 'This action permanently deletes old question versions. Use "Dry Run" first to see what would be deleted. ';
echo 'It is recommended to run this on a clone of your course before using on production data.';
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
echo html_writer::tag('strong', 'Tip: ');
echo 'If a deletion was interrupted (e.g., due to timeout), you may have orphaned database records. ';
echo 'Use the integrity checker links below (next to each context) to find and fix any issues.';
echo html_writer::end_tag('div');

echo html_writer::tag(
    'p',
    'Select a context below. Use <strong>Dry Run</strong> to preview what would be deleted, ' .
    'or <strong>Delete Old Versions</strong> to perform the actual deletion.'
);

// Check if system prototypes context exists and user has permission.
$prototypecontextid = get_prototype_contextid();
$showprototypes = false;
if ($prototypecontextid && has_capability('moodle/question:editall', \context::instance_by_id($prototypecontextid))) {
    $showprototypes = true;
}

// Display system prototypes context first if it exists.
if ($showprototypes) {
    echo html_writer::tag('h4', 'System Prototypes');
    echo html_writer::start_tag('ul');
    $prototypelabel = $oldskool
        ? 'System Context - CR_PROTOTYPES (Built-in CodeRunner Prototypes)'
        : 'Front Page Question Bank - CR_PROTOTYPES (Built-in CodeRunner Prototypes)';
    display_context_with_buttons($prototypecontextid, $prototypelabel, 0);
    echo html_writer::end_tag('ul');
    echo html_writer::tag('br', '');
}

// Find questions from contexts which the user can edit questions in.
$availablequestionsbycontext = bulk_tester::get_num_available_coderunner_questions_by_context();

if (count($availablequestionsbycontext) == 0 && !$showprototypes) {
    echo html_writer::tag('p', 'You do not have permission to edit questions in any contexts.');
} else if (count($availablequestionsbycontext) > 0) {
    if ($oldskool) {
        // Moodle 4 style.
        echo html_writer::tag('h4', 'Available Contexts (' . count($availablequestionsbycontext) . ')');
        qtype_coderunner_util::display_course_contexts(
            $availablequestionsbycontext,
            'qtype_coderunner\display_context_with_buttons'
        );
    } else {
        // Deal with funky question bank madness in Moodle 5.0.
        echo html_writer::tag('h4', 'Course Contexts');
        echo html_writer::tag('p', "Moodle >= 5.0 detected. Listing by course then question bank.");
        qtype_coderunner_util::display_course_grouped_contexts(
            $availablequestionsbycontext,
            'qtype_coderunner\display_course_header',
            'qtype_coderunner\display_context_with_buttons'
        );
    }
}

// Add some basic styling.
echo <<<HTML
<style>
.deleteversions.context {
    margin-bottom: 8px;
    padding: 8px;
    border-left: 3px solid #856404;
    background-color: #fff3cd;
}
.deleteversions.context.quiz {
    border-left-color: #004085;
    background-color: #cce5ff;
}
.deleteversions.context.normal {
    border-left-color: #856404;
    background-color: #fff3cd;
}
.deleteversions.context:hover {
    background-color: #ffeaa7;
}
ul {
    list-style-type: none;
    padding-left: 0;
}
.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.2rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}
</style>
HTML;

echo $OUTPUT->footer();
