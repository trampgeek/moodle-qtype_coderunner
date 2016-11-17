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
 * This script provides an index for running the question tests in bulk.
 * [A modified version of the script in qtype_stack with the same name.]
 *
 * @package   qtype_coderunner
 * @copyright 2016 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../config.php');

require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/coderunner/bulktester.class.php');


// Login and check permissions.
$context = context_system::instance();
require_login();
//require_capability('qtype/stack:usediagnostictools', $context);
$PAGE->set_url('/question/type/coderunner/bulktestindex.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('bulktestindextitle', 'qtype_coderunner'));

// Create the helper class.
$bulktester = new coderunner_bulk_tester();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coderunnercontexts', 'qtype_coderunner'));

echo html_writer::start_tag('ul');
foreach ($bulktester->get_coderunner_questions_by_context() as $contextid => $numcoderunnerquestions) {
    echo html_writer::tag('li', html_writer::link(
            new moodle_url('/question/type/coderunner/bulktest.php', array('contextid' => $contextid)),
            context::instance_by_id($contextid)->get_context_name(true, true) . ' (' . $numcoderunnerquestions . ')'));
}
echo html_writer::end_tag('ul');

/*
if (has_capability('moodle/site:config', context_system::instance())) {
    echo html_writer::tag('p', html_writer::link(
            new moodle_url('/question/type/coderunner/bulktestall.php'), stack_string('bulktestrun')));
}
 */

echo $OUTPUT->footer();


