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
 * Check for question database integrity issues.
 * Finds orphaned records and inconsistencies that may have resulted from interrupted deletions.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context;
use context_system;
use html_writer;
use moodle_url;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Get the parameter from the URL.
$contextid = optional_param('contextid', 0, PARAM_INT);
$fix = optional_param('fix', 0, PARAM_BOOL);
$includesystem = optional_param('includesystem', 0, PARAM_BOOL);

// Login and check permissions.
require_login();
if ($contextid) {
    $context = context::instance_by_id($contextid);
    require_capability('moodle/question:editall', $context);
} else {
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
}

// SAFETY: Exclude system context by default to protect prototypes.
$systemcontext = context_system::instance();
if (!$includesystem && !$contextid) {
    // If no specific context and not including system, we can't proceed.
    echo $OUTPUT->header();
    echo html_writer::tag('h3', 'Question Database Integrity Check');
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('strong', '⚠ Safety Check: Context Required');
    echo html_writer::tag(
        'p',
        'For safety, you must specify a context ID. This prevents accidental modification of ' .
        'system-level questions like CodeRunner prototypes.'
    );
    echo html_writer::end_tag('div');
    echo html_writer::tag('p', 'Please access this tool from the deletion index page, which provides context-specific links.');
    $backurl = new moodle_url('/question/type/coderunner/deleteoldquestionversionsindex.php');
    echo html_writer::link($backurl, '← Back to deletion tool', ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_url('/question/type/coderunner/checkquestionintegrity.php', ['contextid' => $contextid]);
$PAGE->set_context($context);
$PAGE->set_title('Question Database Integrity Check');

echo $OUTPUT->header();

echo html_writer::tag('h3', 'Question Database Integrity Check');
echo html_writer::tag(
    'p',
    'This tool checks for orphaned or inconsistent question records that may have resulted from ' .
    'interrupted deletion operations.'
);

/**
 * Find question versions that reference non-existent questions.
 *
 * @param int $contextid Optional context ID to limit search
 * @return array Array of orphaned version records
 */
function find_orphaned_question_versions($contextid = 0) {
    global $DB;

    $contextwhere = '';
    $params = [];
    if ($contextid) {
        $contextwhere = 'AND ctx.id = :contextid';
        $params['contextid'] = $contextid;
    }

    $sql = "SELECT qv.id, qv.questionid, qv.version, qv.questionbankentryid,
                   qbe.id as entryid, qc.name as categoryname
              FROM {question_versions} qv
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              JOIN {context} ctx ON ctx.id = qc.contextid
         LEFT JOIN {question} q ON q.id = qv.questionid
             WHERE q.id IS NULL
                   $contextwhere
          ORDER BY qv.questionbankentryid, qv.version";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Find question bank entries with no versions.
 *
 * @param int $contextid Optional context ID to limit search
 * @return array Array of entries with no versions
 */
function find_entries_with_no_versions($contextid = 0) {
    global $DB;

    $contextwhere = '';
    $params = [];
    if ($contextid) {
        $contextwhere = 'AND ctx.id = :contextid';
        $params['contextid'] = $contextid;
    }

    $sql = "SELECT qbe.id, qbe.questioncategoryid, qc.name as categoryname
              FROM {question_bank_entries} qbe
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              JOIN {context} ctx ON ctx.id = qc.contextid
         LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
             WHERE qv.id IS NULL
                   $contextwhere
          ORDER BY qc.name, qbe.id";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Delete orphaned question version records.
 *
 * @param array $versionids Array of version IDs to delete
 * @return int Number of records deleted
 */
function delete_orphaned_versions($versionids) {
    global $DB;

    if (empty($versionids)) {
        return 0;
    }

    list($insql, $params) = $DB->get_in_or_equal($versionids, SQL_PARAMS_NAMED);
    $DB->delete_records_select('question_versions', "id $insql", $params);

    return count($versionids);
}

/**
 * Delete empty question bank entries.
 *
 * @param array $entryids Array of entry IDs to delete
 * @return int Number of records deleted
 */
function delete_empty_entries($entryids) {
    global $DB;

    if (empty($entryids)) {
        return 0;
    }

    list($insql, $params) = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
    $DB->delete_records_select('question_bank_entries', "id $insql", $params);

    return count($entryids);
}

// Check for issues.
echo html_writer::tag('h4', 'Checking for issues...');

$orphanedversions = find_orphaned_question_versions($contextid);
$emptyentries = find_entries_with_no_versions($contextid);

$issuesfound = count($orphanedversions) + count($emptyentries);

if ($issuesfound === 0) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
    echo html_writer::tag('strong', '✓ No issues found!');
    echo html_writer::tag('p', 'The question database appears to be in a consistent state.');
    echo html_writer::end_tag('div');
} else {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
    echo html_writer::tag('strong', "⚠ Found $issuesfound issue(s)");
    echo html_writer::end_tag('div');

    // Display orphaned versions.
    if (!empty($orphanedversions)) {
        $count = count($orphanedversions);
        echo html_writer::tag('h5', "Orphaned Question Versions: $count");
        echo html_writer::tag(
            'p',
            'These version records reference questions that no longer exist in the question table. ' .
            'They can be safely deleted.'
        );

        echo html_writer::start_tag('table', ['class' => 'table table-striped table-sm']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'Version ID');
        echo html_writer::tag('th', 'Question ID (missing)');
        echo html_writer::tag('th', 'Version');
        echo html_writer::tag('th', 'Entry ID');
        echo html_writer::tag('th', 'Category');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        $displaycount = 0;
        foreach ($orphanedversions as $v) {
            if ($displaycount++ < 20) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', $v->id);
                echo html_writer::tag('td', $v->questionid);
                echo html_writer::tag('td', $v->version);
                echo html_writer::tag('td', $v->questionbankentryid);
                echo html_writer::tag('td', $v->categoryname);
                echo html_writer::end_tag('tr');
            }
        }

        if ($count > 20) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', '... and ' . ($count - 20) . ' more', ['colspan' => 5, 'class' => 'text-muted']);
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }

    // Display empty entries.
    if (!empty($emptyentries)) {
        $count = count($emptyentries);
        echo html_writer::tag('h5', "Question Bank Entries with No Versions: $count");
        echo html_writer::tag(
            'p',
            'These question bank entries have no associated version records. They can be safely deleted.'
        );

        echo html_writer::start_tag('table', ['class' => 'table table-striped table-sm']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'Entry ID');
        echo html_writer::tag('th', 'Category ID');
        echo html_writer::tag('th', 'Category');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        $displaycount = 0;
        foreach ($emptyentries as $e) {
            if ($displaycount++ < 20) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', $e->id);
                echo html_writer::tag('td', $e->questioncategoryid);
                echo html_writer::tag('td', $e->categoryname);
                echo html_writer::end_tag('tr');
            }
        }

        if ($count > 20) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag(
                'td',
                '... and ' . ($count - 20) . ' more',
                ['colspan' => 3, 'class' => 'text-muted']
            );
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }

    // Fix button.
    if ($fix) {
        require_sesskey();

        echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
        echo html_writer::tag('h5', 'Cleaning up...');

        $deleted = 0;
        if (!empty($orphanedversions)) {
            $versionids = array_keys($orphanedversions);
            $deleted += delete_orphaned_versions($versionids);
            echo html_writer::tag('p', "✓ Deleted $deleted orphaned version records");
        }

        if (!empty($emptyentries)) {
            $entryids = array_keys($emptyentries);
            $entriesdeleted = delete_empty_entries($entryids);
            echo html_writer::tag('p', "✓ Deleted $entriesdeleted empty entry records");
            $deleted += $entriesdeleted;
        }

        echo html_writer::tag('p', html_writer::tag('strong', "Total records cleaned up: $deleted"));
        echo html_writer::end_tag('div');

        $rerunurl = new moodle_url(
            '/question/type/coderunner/checkquestionintegrity.php',
            ['contextid' => $contextid]
        );
        echo html_writer::link($rerunurl, 'Re-run Check', ['class' => 'btn btn-primary']);
    } else {
        $fixurl = new moodle_url(
            '/question/type/coderunner/checkquestionintegrity.php',
            ['contextid' => $contextid, 'fix' => 1, 'sesskey' => sesskey()]
        );
        echo html_writer::link($fixurl, 'Fix Issues', ['class' => 'btn btn-warning']);
        echo html_writer::tag(
            'p',
            html_writer::tag('small', 'This will delete the orphaned records listed above.', ['class' => 'text-muted'])
        );
    }
}

$backurl = new moodle_url('/question/type/coderunner/deleteoldquestionversionsindex.php');
echo html_writer::link($backurl, '← Back to deletion tool', ['class' => 'btn btn-secondary', 'style' => 'margin-left: 0.5rem;']);

echo $OUTPUT->footer();
