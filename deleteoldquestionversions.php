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
 * Delete old question versions for a specific context.
 * Keeps only the most recent version of each question.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context;
use core_php_time_limit;
use html_writer;
use moodle_url;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Get the parameters from the URL.
$contextid = required_param('contextid', PARAM_INT);
$dryrun = optional_param('dryrun', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$skipinuse = optional_param('skipinuse', 1, PARAM_BOOL); // Default to skipping in-use versions.

// Login and check permissions.
require_login();
$context = context::instance_by_id($contextid);
require_capability('moodle/question:editall', $context);

// Raise time and memory limits to handle large numbers of questions.
core_php_time_limit::raise(300); // Allow up to 5 minutes for the entire script.
raise_memory_limit(MEMORY_EXTRA);

// Get course name for display.
$coursename = '';
if ($context->contextlevel == CONTEXT_COURSE) {
    $course = $DB->get_record('course', ['id' => $context->instanceid], 'fullname');
    $coursename = $course ? $course->fullname : 'Unknown Course (' . $contextid . ')';
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], 'fullname');
    $coursename = $course ? $course->fullname : 'Unknown Course (' . $contextid . ')';
} else {
    $coursename = $context->get_context_name() . ' (' . $contextid . ')';
}

$urlparams = ['contextid' => $context->id];
if ($dryrun) {
    $urlparams['dryrun'] = 1;
}
if (!$skipinuse) {
    $urlparams['skipinuse'] = 0;
}
$PAGE->set_url('/question/type/coderunner/deleteoldquestionversions.php', $urlparams);
$PAGE->set_context($context);
$PAGE->set_title($dryrun ? "Dry Run - Delete Old Question Versions" : "Delete Old Question Versions");

if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $PAGE->set_cm($cm, $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST));
}

/**
 * Get all question bank entries with multiple versions in a context.
 *
 * @param int $contextid Context ID
 * @return array Array of question bank entries with version information
 */
function get_question_bank_entries_with_multiple_versions($contextid) {
    global $DB;

    // Find all question bank entries that have more than one version.
    $sql = "SELECT qbe.id AS entryid,
                   COUNT(qv.id) AS versioncount,
                   MAX(qv.version) AS latestversion,
                   qbe.questioncategoryid,
                   qc.name AS categoryname,
                   ctx.id AS contextid
              FROM {question_bank_entries} qbe
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              JOIN {context} ctx ON ctx.id = qc.contextid
             WHERE ctx.id = :contextid
          GROUP BY qbe.id, qbe.questioncategoryid, qc.name, ctx.id
            HAVING COUNT(qv.id) > 1
          ORDER BY qc.name, qbe.id";

    return $DB->get_records_sql($sql, ['contextid' => $contextid]);
}

/**
 * Get all versions for a question bank entry.
 *
 * @param int $entryid Question bank entry ID
 * @return array Array of version records
 */
function get_all_versions_for_entry($entryid) {
    global $DB;

    $sql = "SELECT qv.id AS versionid,
                   qv.questionid,
                   qv.version,
                   qv.questionbankentryid,
                   q.name AS questionname,
                   q.qtype
              FROM {question_versions} qv
              JOIN {question} q ON q.id = qv.questionid
             WHERE qv.questionbankentryid = :entryid
          ORDER BY qv.version DESC";

    return $DB->get_records_sql($sql, ['entryid' => $entryid]);
}

/**
 * Check if any versions are in use.
 *
 * @param array $questionids Array of question IDs to check
 * @return array Array mapping question ID to usage status
 */
function check_versions_in_use($questionids) {
    $usagestatus = [];
    foreach ($questionids as $qid) {
        $usagestatus[$qid] = questions_in_use([$qid]);
    }
    return $usagestatus;
}

/**
 * Delete a question version.
 *
 * @param int $questionid Question ID to delete
 * @return array ['success' => bool, 'error' => string|null]
 */
function delete_question_version($questionid) {
    try {
        question_require_capability_on($questionid, 'edit');
        question_delete_question($questionid);
        return ['success' => true, 'error' => null];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Output header.
echo $OUTPUT->header();

echo html_writer::start_tag('div', ['class' => 'container-fluid']);

// Heading.
if ($dryrun) {
    echo html_writer::tag('h3', 'Dry Run: Preview Question Version Deletion');
    echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
    echo html_writer::tag('strong', 'Dry Run Mode: ');
    echo 'No questions will be deleted. This preview shows what would be deleted.';
    echo html_writer::end_tag('div');
} else {
    echo html_writer::tag('h3', 'Delete Old Question Versions');
}

echo html_writer::tag('p', html_writer::tag('strong', 'Context: ') . $coursename);

// Get all entries with multiple versions.
$entries = get_question_bank_entries_with_multiple_versions($contextid);

if (empty($entries)) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
    echo 'No question bank entries with multiple versions found in this context.';
    echo html_writer::end_tag('div');

    $backurl = new moodle_url('/question/type/coderunner/deleteoldquestionversionsindex.php');
    echo html_writer::link($backurl, '← Back to context list', ['class' => 'btn btn-secondary']);

    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

$totalentries = count($entries);
$totalversions = 0;
$totaltokeep = $totalentries;
$totaltodelete = 0;
$totalinuse = 0;
$totalskipped = 0;

// Collect all information first.
$deletionplan = [];
foreach ($entries as $entry) {
    $versions = get_all_versions_for_entry($entry->entryid);
    $versioncount = count($versions);
    $totalversions += $versioncount;

    $latestversion = array_shift($versions); // Keep this one.
    $oldversions = $versions; // Delete these.

    $totaltodelete += count($oldversions);

    // Check if any old versions are in use.
    $oldversionids = array_column($oldversions, 'questionid');
    $usagestatus = check_versions_in_use($oldversionids);
    $anyinuse = array_sum($usagestatus) > 0;
    if ($anyinuse) {
        $totalinuse += array_sum($usagestatus);
    }

    $deletionplan[] = [
        'entry' => $entry,
        'latest' => $latestversion,
        'old' => $oldversions,
        'usage' => $usagestatus,
        'anyinuse' => $anyinuse,
    ];
}

// Display summary.
echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
echo html_writer::tag('h5', 'Summary');
echo html_writer::tag('p', "Question bank entries with multiple versions: $totalentries");
echo html_writer::tag('p', "Total versions found: $totalversions");
echo html_writer::tag('p', "Versions to keep (latest): $totaltokeep");
echo html_writer::tag('p', "Versions to delete: $totaltodelete");
if ($totalinuse > 0) {
    if ($skipinuse) {
        echo html_writer::tag(
            'p',
            "ℹ️ Note: $totalinuse old version(s) are currently in use and will be SKIPPED",
            ['style' => 'color: #004085; font-weight: bold;']
        );
    } else {
        echo html_writer::tag(
            'p',
            "⚠️ DANGER: $totalinuse old version(s) are currently in use and WILL BE DELETED!",
            ['style' => 'color: #721c24; font-weight: bold; font-size: 1.1em;']
        );
    }
}
echo html_writer::end_tag('div');

// If not confirmed and not dry run, show confirmation.
if (!$dryrun && !$confirm) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
    echo html_writer::tag('h5', '⚠️ Confirmation Required');
    echo html_writer::tag('p', "You are about to permanently delete question versions. This action cannot be undone!");
    echo html_writer::end_tag('div');

    if ($totalinuse > 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
        echo html_writer::tag('h5', '⚠️ Versions In Use Detected');
        echo html_writer::tag(
            'p',
            "$totalinuse old version(s) are currently in use in quizzes or other activities."
        );
        echo html_writer::tag(
            'p',
            'Deleting in-use versions may cause problems with:'
        );
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', 'Existing student quiz attempts');
        echo html_writer::tag('li', 'Quiz reports and grading');
        echo html_writer::tag('li', 'Historical question data');
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => new moodle_url('/question/type/coderunner/deleteoldquestionversions.php'),
            'style' => 'margin: 1rem 0;',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'contextid', 'value' => $contextid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        echo html_writer::start_tag('div', ['style' => 'margin-bottom: 1rem;']);
        echo html_writer::tag('h6', 'Choose how to handle in-use versions:');

        echo html_writer::start_tag('div', ['class' => 'form-check']);
        echo html_writer::empty_tag('input', [
            'type' => 'radio',
            'class' => 'form-check-input',
            'name' => 'skipinuse',
            'id' => 'skipinuse1',
            'value' => '1',
            'checked' => 'checked',
        ]);
        echo html_writer::start_tag('label', ['class' => 'form-check-label', 'for' => 'skipinuse1']);
        echo html_writer::tag('strong', 'Skip in-use versions (RECOMMENDED)');
        echo '<br>';
        echo html_writer::tag('small', 'Only delete versions that are not currently in use', ['class' => 'text-muted']);
        echo html_writer::end_tag('label');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', ['class' => 'form-check', 'style' => 'margin-top: 0.5rem;']);
        echo html_writer::empty_tag('input', [
            'type' => 'radio',
            'class' => 'form-check-input',
            'name' => 'skipinuse',
            'id' => 'skipinuse0',
            'value' => '0',
        ]);
        echo html_writer::start_tag('label', ['class' => 'form-check-label', 'for' => 'skipinuse0']);
        echo html_writer::tag('strong', 'Delete ALL old versions including in-use ones', ['style' => 'color: #721c24;']);
        echo '<br>';
        echo html_writer::tag('small', 'DANGER: This may break existing quizzes and student attempts!', [
            'class' => 'text-danger',
            'style' => 'font-weight: bold;',
        ]);
        echo html_writer::end_tag('label');
        echo html_writer::end_tag('div');

        echo html_writer::end_tag('div');

        echo html_writer::tag('button', 'Proceed with Deletion', [
            'type' => 'submit',
            'class' => 'btn btn-danger mr-2',
        ]);
        echo html_writer::end_tag('form');
    } else {
        // No in-use versions, simpler confirmation.
        $confirmurl = new moodle_url(
            '/question/type/coderunner/deleteoldquestionversions.php',
            ['contextid' => $contextid, 'confirm' => 1, 'skipinuse' => 1, 'sesskey' => sesskey()]
        );

        echo html_writer::tag('p', "You are about to delete $totaltodelete old question versions.");
        echo html_writer::tag('p', 'Are you sure you want to continue?');
        echo html_writer::link($confirmurl, 'Yes, Delete Old Versions', ['class' => 'btn btn-danger mr-2']);
    }

    $backurl = new moodle_url('/question/type/coderunner/deleteoldquestionversionsindex.php');
    echo html_writer::link($backurl, 'Cancel', ['class' => 'btn btn-secondary']);

    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

// Verify sesskey for actual deletion.
if (!$dryrun && $confirm) {
    require_sesskey();
}

// Display detailed plan.
echo html_writer::tag('h4', 'Detailed Plan');

$progress = 0;
$successcount = 0;
$failcount = 0;
$errors = []; // Track error messages.

foreach ($deletionplan as $plan) {
    $progress++;
    core_php_time_limit::raise(60); // Extend time limit by 1 minute for each question entry.
    $entry = $plan['entry'];
    $latest = $plan['latest'];
    $oldversions = $plan['old'];
    $usagestatus = $plan['usage'];
    $anyinuse = $plan['anyinuse'];

    echo html_writer::start_tag('div', [
        'class' => 'card mb-3',
        'style' => 'border-left: 4px solid ' . ($anyinuse ? '#721c24' : '#004085'),
    ]);
    echo html_writer::start_tag('div', ['class' => 'card-body']);

    echo html_writer::tag('h5', "[$progress/$totalentries] " . htmlspecialchars($latest->questionname));
    echo html_writer::tag(
        'p',
        html_writer::tag(
            'small',
            'Category: ' . htmlspecialchars($entry->categoryname) . ', Entry ID: ' . $entry->entryid,
            ['class' => 'text-muted']
        )
    );

    echo html_writer::tag(
        'p',
        html_writer::tag('strong', 'Keeping: ') .
        "Version $latest->version (Question ID: $latest->questionid, Type: $latest->qtype)"
    );

    echo html_writer::tag('p', html_writer::tag('strong', 'Deleting:'));
    echo html_writer::start_tag('ul');

    foreach ($oldversions as $version) {
        $inuse = $usagestatus[$version->questionid];
        $usagetext = $inuse ? ' <span style="color: #721c24;">⚠️ IN USE</span>' : '';

        echo html_writer::start_tag('li');
        echo "Version $version->version (Question ID: $version->questionid, Type: $version->qtype)$usagetext";

        if (!$dryrun && $confirm) {
            // Actually delete (or skip if in use and skipinuse is true).
            if ($inuse && $skipinuse) {
                echo ' ... <span style="color: #856404;">↷ Skipped (in use)</span>';
                $totalskipped++;
            } else {
                echo ' ... ';
                flush();
                $result = delete_question_version($version->questionid);
                if ($result['success']) {
                    echo '<span style="color: #155724;">✓ Deleted</span>';
                    $successcount++;
                } else {
                    $errormsg = $result['error'];
                    echo '<span style="color: #721c24;">✗ Failed: ' . htmlspecialchars($errormsg) . '</span>';
                    $failcount++;
                    // Collect error messages for summary.
                    if (!isset($errors[$errormsg])) {
                        $errors[$errormsg] = 0;
                    }
                    $errors[$errormsg]++;
                }
            }
        } else if ($dryrun && $inuse && $skipinuse) {
            echo ' <span style="color: #856404;">(would be skipped)</span>';
        }

        echo html_writer::end_tag('li');
    }

    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');

    flush();
}

// Final summary.
echo html_writer::start_tag('div', ['class' => 'alert alert-' . ($dryrun ? 'info' : 'success')]);
echo html_writer::tag('h5', 'Final Summary');
echo html_writer::tag('p', "Question bank entries processed: $totalentries");
echo html_writer::tag('p', "Total versions found: $totalversions");
echo html_writer::tag('p', "Versions kept (latest): $totaltokeep");

if ($dryrun) {
    $woulddelete = $totaltodelete;
    if ($skipinuse && $totalinuse > 0) {
        $woulddelete = $totaltodelete - $totalinuse;
        echo html_writer::tag('p', "Versions that would be deleted: $woulddelete");
        echo html_writer::tag('p', "Versions that would be skipped (in use): $totalinuse", ['style' => 'color: #856404;']);
    } else {
        echo html_writer::tag('p', "Versions that would be deleted: $woulddelete");
    }
    echo html_writer::tag('p', html_writer::tag('em', 'This was a dry run. No questions were actually deleted.'));
} else {
    echo html_writer::tag('p', "Versions deleted: $successcount");
    if ($totalskipped > 0) {
        echo html_writer::tag('p', "Versions skipped (in use): $totalskipped", ['style' => 'color: #856404;']);
    }
    if ($failcount > 0) {
        echo html_writer::tag('p', "Versions failed to delete: $failcount", ['style' => 'color: #721c24;']);

        // Display error breakdown.
        if (!empty($errors)) {
            echo html_writer::start_tag(
                'div',
                [
                    'style' => 'margin-top: 1rem; padding: 1rem; ' .
                              'background-color: #f8d7da; border-left: 4px solid #721c24;',
                ]
            );
            echo html_writer::tag('h6', 'Error Breakdown:', ['style' => 'color: #721c24;']);
            echo html_writer::start_tag('ul');
            arsort($errors); // Sort by count, highest first.
            foreach ($errors as $error => $count) {
                echo html_writer::tag(
                    'li',
                    html_writer::tag('strong', "$count occurrences: ") . htmlspecialchars($error)
                );
            }
            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('div');
        }
    }
    echo html_writer::tag('p', html_writer::tag('strong', 'Cleanup complete!'));
}
echo html_writer::end_tag('div');

$backurl = new moodle_url('/question/type/coderunner/deleteoldquestionversionsindex.php');
echo html_writer::link($backurl, '← Back to context list', ['class' => 'btn btn-secondary']);

echo html_writer::end_tag('div');

echo <<<HTML
<style>
.mr-2 {
    margin-right: 0.5rem;
}
.mb-3 {
    margin-bottom: 1rem;
}
.card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    word-wrap: break-word;
    background-color: #fff;
    background-clip: border-box;
    border: 1px solid rgba(0,0,0,.125);
    border-radius: 0.25rem;
}
.card-body {
    flex: 1 1 auto;
    padding: 1.25rem;
}
</style>
HTML;

echo $OUTPUT->footer();
