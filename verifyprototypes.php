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
 * Verify CodeRunner prototypes and check database state.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context_system;
use html_writer;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Login and check permissions.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/question/type/coderunner/verifyprototypes.php');
$PAGE->set_context($context);
$PAGE->set_title('Verify CodeRunner Prototypes');

echo $OUTPUT->header();

echo html_writer::tag('h3', 'CodeRunner Prototype Verification');

// Find CR_PROTOTYPES category.
$prototypecategory = $DB->get_record('question_categories', ['name' => 'CR_PROTOTYPES']);

if (!$prototypecategory) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
    echo 'CR_PROTOTYPES category not found. This is unusual.';
    echo html_writer::end_tag('div');
} else {
    echo html_writer::tag('h4', 'CR_PROTOTYPES Category Info');
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Category ID: ' . $prototypecategory->id);
    echo html_writer::tag('li', 'Context ID: ' . $prototypecategory->contextid);
    echo html_writer::tag('li', 'Name: ' . $prototypecategory->name);
    echo html_writer::end_tag('ul');

    // Check questions in this category.
    echo html_writer::tag('h4', 'Questions in CR_PROTOTYPES');

    $sql = "SELECT DISTINCT q.id, q.name, q.qtype
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             WHERE qbe.questioncategoryid = :categoryid
          ORDER BY q.id";
    $questions = $DB->get_records_sql($sql, ['categoryid' => $prototypecategory->id]);

    if (empty($questions)) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
        echo html_writer::tag('strong', '⚠ NO QUESTIONS FOUND in question table for CR_PROTOTYPES!');
        echo html_writer::tag('p', 'This confirms the problem - prototype questions are missing from the question table.');
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::tag('p', 'Found ' . count($questions) . ' questions in the question table:');
        echo html_writer::start_tag('table', ['class' => 'table table-striped']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', 'Question ID');
        echo html_writer::tag('th', 'Name');
        echo html_writer::tag('th', 'Type');
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');
        foreach ($questions as $q) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', $q->id);
            echo html_writer::tag('td', htmlspecialchars($q->name));
            echo html_writer::tag('td', $q->qtype);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }

    // Check question_versions for this category.
    echo html_writer::tag('h4', 'Question Versions in CR_PROTOTYPES');

    // Use get_recordset_sql to avoid keying issues.
    $sql = "SELECT qv.id AS versionrecordid, qv.questionid, qv.version, qv.questionbankentryid
              FROM {question_versions} qv
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             WHERE qbe.questioncategoryid = :categoryid
          ORDER BY qv.questionid, qv.version";

    $recordset = $DB->get_recordset_sql($sql, ['categoryid' => $prototypecategory->id]);
    $versions = [];
    foreach ($recordset as $record) {
        $versions[] = $record;
    }
    $recordset->close();

    echo html_writer::tag('p', 'Found ' . count($versions) . ' version records:');

    // Debug: Show first record structure.
    if (!empty($versions)) {
        echo html_writer::start_tag('details', ['style' => 'margin-bottom: 1rem;']);
        echo html_writer::tag('summary', 'Debug: First record structure (click to expand)');
        $debuginfo = 'versionrecordid: ' . $versions[0]->versionrecordid . "\n" .
                     'questionid: ' . $versions[0]->questionid . "\n" .
                     'version: ' . $versions[0]->version . "\n" .
                     'questionbankentryid: ' . $versions[0]->questionbankentryid;
        echo html_writer::tag('pre', $debuginfo);
        echo html_writer::end_tag('details');
    }

    echo html_writer::start_tag('table', ['class' => 'table table-striped table-sm']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Version Record ID (qv.id)');
    echo html_writer::tag('th', 'Question ID (qv.questionid)');
    echo html_writer::tag('th', 'Version #');
    echo html_writer::tag('th', 'Bank Entry ID');
    echo html_writer::tag('th', 'Question Exists?');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $displaycount = 0;
    foreach ($versions as $v) {
        if ($displaycount >= 50 && $displaycount < count($versions) - 10) {
            if ($displaycount === 50) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag(
                    'td',
                    '... ' . (count($versions) - 60) . ' more records ...',
                    ['colspan' => 5, 'class' => 'text-muted text-center']
                );
                echo html_writer::end_tag('tr');
            }
            $displaycount++;
            continue;
        }

        $questionexists = $DB->record_exists('question', ['id' => $v->questionid]);
        echo html_writer::start_tag('tr', ['class' => $questionexists ? '' : 'table-danger']);
        echo html_writer::tag('td', isset($v->versionrecordid) ? $v->versionrecordid : 'N/A');
        echo html_writer::tag('td', $v->questionid);
        echo html_writer::tag('td', $v->version);
        echo html_writer::tag('td', $v->questionbankentryid);
        echo html_writer::tag('td', $questionexists ? '✓ Yes' : '✗ NO - ORPHANED');
        echo html_writer::end_tag('tr');
        $displaycount++;
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    // Check for the specific issue.
    $orphanedcount = 0;
    foreach ($versions as $v) {
        if (!$DB->record_exists('question', ['id' => $v->questionid])) {
            $orphanedcount++;
        }
    }

    if ($orphanedcount > 0) {
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
        echo html_writer::tag('strong', "ℹ️ Found $orphanedcount orphaned prototype version records");
        echo html_writer::tag(
            'p',
            'These version records reference questions that no longer exist in the question table. ' .
            'This is a known issue with CodeRunner prototype upgrades.'
        );
        echo html_writer::tag(
            'p',
            'When CodeRunner is upgraded, new prototype questions are created but old version records ' .
            'are not always cleaned up, leading to accumulation over time.'
        );
        echo html_writer::tag(
            'p',
            html_writer::tag('strong', 'Good news: ') .
            'Your current prototypes (the ' . count($questions) . ' questions shown above) are intact. ' .
            'The orphaned records can be safely cleaned up using the integrity checker.'
        );
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', ['class' => 'alert alert-info']);
        echo html_writer::tag('h6', 'How to clean up:');
        echo html_writer::start_tag('ol');
        echo html_writer::tag(
            'li',
            'The orphaned records are harmless but waste database space'
        );
        echo html_writer::tag(
            'li',
            'They cannot be deleted through normal question deletion (which is why you saw failures)'
        );
        echo html_writer::tag(
            'li',
            'Use the integrity checker with a specific course context to clean up course-level orphans first'
        );
        echo html_writer::tag(
            'li',
            'For system-level cleanup (CR_PROTOTYPES), you may need to manually delete these records or ' .
            'contact your system administrator'
        );
        echo html_writer::end_tag('ol');
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
        echo html_writer::tag('strong', '✓ All version records are valid!');
        echo html_writer::tag('p', 'No orphaned version records found in CR_PROTOTYPES.');
        echo html_writer::end_tag('div');
    }
}

echo $OUTPUT->footer();
