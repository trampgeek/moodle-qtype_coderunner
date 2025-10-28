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
 * This script provides an index of contexts for the CodeRunner question browser.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context_system;
use context;
use context_course;
use html_writer;
use moodle_url;
use qtype_coderunner_util;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/classes/bulk_tester.php');

// We are Moodle 4 or less if don't have mod_qbank.
$oldskool = !(qtype_coderunner_util::using_mod_qbank());

// Login and check permissions.
$context = context_system::instance();
require_login();

const BUTTONSTYLE = 'background-color: #FFFFD0; padding: 2px 2px 0px 2px;border: 4px solid white';

function display_course_header($coursecontextid, $coursename) {
    $litext = $coursecontextid . ' - ' . $coursename;
    echo html_writer::tag('h5', $litext);
}

function display_questions_for_context($contextid, $name, $numcoderunnerquestions) {
    $browseallstr = get_string('browsequestions', 'qtype_coderunner', $name);
    if (!$browseallstr) {
        $browseallstr = "Browse questions";
    }

    $browseurl = new moodle_url('/question/type/coderunner/questionbrowser.php', ['contextid' => $contextid]);

    $browselink = html_writer::link(
        $browseurl,
        $browseallstr,
        ['style' => BUTTONSTYLE . ';cursor:pointer;text-decoration:none;']
    );

    $litext = $contextid . ' - ' . $name . ' (' . $numcoderunnerquestions . ') ' . $browselink;

    if (strpos($name, ": Quiz: ") === false) {
        $class = 'questionbrowser coderunner context normal';
    } else {
        $class = 'questionbrowser coderunner context quiz';
    }

    echo html_writer::start_tag('li', ['class' => $class]);
    echo $litext;
    echo html_writer::end_tag('li');
}


// Set up page.
$PAGE->set_url('/question/type/coderunner/questionbrowserindex.php');
$PAGE->set_context($context);
$PAGE->set_title('CodeRunner Question Browser');
$PAGE->set_heading('CodeRunner Question Browser');

// Display.
echo $OUTPUT->header();

echo html_writer::tag('p', 'Select a context to browse CodeRunner questions with enhanced metadata and filtering capabilities.');

// Find questions from contexts which the user can edit questions in.
$availablequestionsbycontext = bulk_tester::get_num_available_coderunner_questions_by_context();

if (count($availablequestionsbycontext) == 0) {
    echo html_writer::tag('p', 'You do not have permission to browse questions in any contexts.');
} else {
    echo html_writer::tag(
        'p',
        '<strong>Instructions:</strong> Click "Browse questions" to open the question browser for that context.'
    );

    if ($oldskool) {
        // Moodle 4 style.
        echo html_writer::tag('h3', 'Available Contexts (' . count($availablequestionsbycontext) . ')');
        qtype_coderunner_util::display_course_contexts(
            $availablequestionsbycontext,
            'qtype_coderunner\display_questions_for_context'
        );
    } else {
        // Deal with funky question bank madness in Moodle 5.0.
        echo html_writer::tag('p', "Moodle >= 5.0 detected. Listing by course then question bank.");
        qtype_coderunner_util::display_course_grouped_contexts(
            $availablequestionsbycontext,
            'qtype_coderunner\display_course_header',
            'qtype_coderunner\display_questions_for_context'
        );
    }
}

// Add some basic styling.
echo <<<HTML
<style>
.questionbrowser.coderunner.context {
    margin-bottom: 8px;
    padding: 8px;
    border-left: 3px solid #007cba;
    background-color: #f9f9f9;
}
.questionbrowser.coderunner.context.quiz {
    border-left-color: #28a745;
}
.questionbrowser.coderunner.context:hover {
    background-color: #e9f4ff;
}
ul {
    list-style-type: none;
    padding-left: 0;
}
</style>
HTML;

echo $OUTPUT->footer();
