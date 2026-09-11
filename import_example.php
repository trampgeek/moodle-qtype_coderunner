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
 * Imports one of the documentation's example questions into a course
 * chosen by the user. This script does exactly one thing: given the slug
 * of a file in docs/editor/example_questions/, it asks which course to
 * import into, then runs the standard Moodle XML import into a
 * "CodeRunner examples" category in that course's question bank.
 *
 * @author     Michal Skraburski
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

use core\exception\moodle_exception;

global $PAGE, $OUTPUT, $CFG, $DB;

require_login();

$slug = required_param('slug', PARAM_FILE);      // Example filename without .xml, e.g. "05-fizzbuzz".
$courseid = optional_param('qtype_coderunner_import_courseid', 0, PARAM_INT);

// Resolve the slug to a file, refusing anything outside the examples dir.
// PARAM_FILE already strips path separators; the realpath check is the backstop.
$examplesdir = __DIR__ . '/docs/editor/example_questions/';
$file = realpath($examplesdir . '/' . $slug . '.xml');
if (!$file || strpos($file, realpath($examplesdir)) !== 0) {
    throw new moodle_exception('invalidparameter', 'error');
}

// Course-context question banks only (Moodle <= 4.5). On a mod_qbank site
// (4.6+) the target would be a qbank module instance instead.
// TODO: add a mod_qbank path when the site is upgraded.
if (qtype_coderunner_util::using_mod_qbank()) {
    throw new moodle_exception('invalidparameter', 'error');
}

$PAGE->set_url('/question/type/coderunner/import_example.php', ['slug' => $slug]);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Import example question');

$examplename = ucfirst(str_replace(['-', '_'], ' ', $slug));

if (!$courseid) {
    // Stage 1: ask which course to import into. Only offer courses where
    // the user may add questions (the capability the import pipeline needs).
    $courses = get_user_capability_course('moodle/question:add', null, false, 'fullname') ?: [];
    $options = [];
    foreach ($courses as $course) {
        if ($course->id != SITEID) {
            $options[$course->id] = format_string($course->fullname);
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading("Import example: $examplename");
    if (!$options) {
        echo $OUTPUT->notification(
            'You are not able to add questions to any course, so this example cannot be imported. '
            . 'You can still download it from the examples page.',
            \core\output\notification::NOTIFY_WARNING,
        );
    } else {
        echo html_writer::tag('p', 'Choose the course whose question bank the example should be imported into. '
            . 'The questions are placed in a "CodeRunner examples" category. '
            . 'Importing the same example twice creates duplicates.');
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::label('Course', 'id_import_courseid');
        echo ' ';
        echo html_writer::select(
            $options,
            'qtype_coderunner_import_courseid',
            '',
            ['' => 'choosedots'],
            ['id' => 'id_import_courseid'],
        );
        echo ' ';
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => 'Import',
            'class' => 'btn btn-primary',
        ]);
        echo html_writer::end_tag('form');
    }
    echo html_writer::tag('p', html_writer::link(
        new moodle_url('/question/type/coderunner/docs.php', ['page' => 'example_questions.md']),
        'Back to the examples page',
    ));
    echo $OUTPUT->footer();
    // Top-level return ends the script just like exit, but also lets the
    // PHPUnit tests include this file without terminating the test run.
    return;
}

// Stage 2: a course was chosen — do the import.
require_sesskey();
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);
require_capability('moodle/question:add', $coursecontext);

// Find or create the target category under the course's top category.
$topcategory = question_get_top_category($coursecontext->id, true);
$category = $DB->get_record(
    'question_categories',
    ['contextid' => $coursecontext->id, 'name' => 'CodeRunner examples'],
);
if (!$category) {
    $categoryid = $DB->insert_record('question_categories', [
        'name'      => 'CodeRunner examples',
        'contextid' => $coursecontext->id,
        'info'      => 'Example questions imported from the CodeRunner documentation.',
        'parent'    => $topcategory->id,
        'sortorder' => 999,
        'stamp'     => make_unique_id_code(),
    ]);
    $category = $DB->get_record('question_categories', ['id' => $categoryid]);
}

// Run the standard Moodle XML import pipeline (cf. load_questions() in
// db/upgradelib.php, which does the same for the built-in prototypes).
$qformat = new qformat_xml();
$qformat->setCategory($category);
$contexts = new core_question\local\bank\question_edit_contexts($coursecontext);
$qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
$qformat->setCourse($course);
$qformat->setFilename($file);
$qformat->setRealfilename(basename($file));
$qformat->setMatchgrades('error');
$qformat->setCatfromfile(false);
$qformat->setContextfromfile(false);
$qformat->setStoponerror(true);

ob_start();
$success = $qformat->importpreprocess()
        && $qformat->importprocess($category)
        && $qformat->importpostprocess();
$importoutput = ob_get_clean();

if (!$success) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading("Import example: $examplename");
    echo $OUTPUT->notification('Import failed.', \core\output\notification::NOTIFY_ERROR);
    // The importer explains its failures in its printed output.
    echo html_writer::div($importoutput);
    echo $OUTPUT->footer();
    return;
}

$numimported = is_array($qformat->questionids ?? null) ? count($qformat->questionids) : 0;
redirect(
    new moodle_url('/question/edit.php', [
        'courseid' => $course->id,
        'cat' => $category->id . ',' . $coursecontext->id,
    ]),
    "Imported $numimported question(s) into the \"CodeRunner examples\" category.",
    null,
    \core\output\notification::NOTIFY_SUCCESS,
);
