<?php
// This file is part of CodeRunner - http://coderunner.bham.ac.uk/
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
 * This script runs all the question tests for all deployed versions of all
 * questions in all contexts in the Moodle site. This is intended for regression
 * testing, before you release a new version of CodeRunner to your site.
 *
 * @package   qtype_coderunner
 * @copyright 2016 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/coderunner/bulktester.class.php');


// Get the parameters from the URL. This is an option to restart the process
// in the middle. Useful if it crashes.
$startfromcontextid = optional_param('startfromcontextid', 0, PARAM_INT);

// Login and check permissions.
$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);
$PAGE->set_url('/question/type/coderunner/bulktestall.php',
        array('startfromcontextid' => $startfromcontextid));
$PAGE->set_context($context);
$title = coderunner_string('bulktesttitle', $context->get_context_name());
$PAGE->set_title($title);

// Create the helper class.
$bulktester = new coderunner_bulk_tester();
$allpassed = true;
$allfailingtests = array();
$skipping = $startfromcontextid != 0;

// Release the session, so the user can do other things while this runs.
\core\session\manager::write_close();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 1);

// Run the tests.
foreach ($bulktester->get_coderunner_questions_by_context() as $contextid => $numcoderunnerquestions) {
    if ($skipping && $contextid != $startfromcontextid) {
        continue;
    }
    $skipping = false;

    $testcontext = context::instance_by_id($contextid);
    echo $OUTPUT->heading(get_string('bulktesttitle', 'qtype_coderunner', $testcontext->get_context_name()));
    echo html_writer::tag('p', html_writer::link(
            new moodle_url('/question/type/coderunner/bulktestall.php',
                array('startfromcontextid' => $testcontext->id)),
            get_string('bulktestcontinuefromhere', 'qtype_coderunner')));

    list($passed, $failingtests) = $bulktester->run_all_tests_for_context($testcontext);
    $allpassed = $allpassed && $passed;
    $allfailingtests = array_merge($allfailingtests, $failingtests);
}

// Display the final summary.
$bulktester->print_overall_result($allpassed, $allfailingtests);
echo $OUTPUT->footer();
