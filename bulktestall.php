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
namespace qtype_coderunner;

use context;
use context_system;
use context_course;
use html_writer;
use moodle_url;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Get the parameters from the URL. This is an option to restart the process
// in the middle. Useful if it crashes.
$startfromcontextid = optional_param('startfromcontextid', 0, PARAM_INT);
$usecache = optional_param('usecache', 1, PARAM_INT);
$randomseed = optional_param('randomseed', -1, PARAM_INT);
$repeatrandomonly = optional_param('repeatrandomonly', 1, PARAM_INT);
$nruns = optional_param('nruns', 1, PARAM_INT);
$clearcachefirst = optional_param('clearcachefirst', 0, PARAM_INT);

// Login.
$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);  // Administrators only.
$PAGE->set_url(
    '/question/type/coderunner/bulktestall.php',
    ['startfromcontextid' => $startfromcontextid]
);
$PAGE->set_context($context);
$title = get_string('bulktestalltitle', 'qtype_coderunner');
$PAGE->set_title($title);


$numpasses = 0;
$allfailingtests = [];
$allmissinganswers = [];
$skipping = $startfromcontextid != 0;

// Release the session, so the user can do other things while this runs.
\core\session\manager::write_close();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 1);

$jobehost = get_config('qtype_coderunner', 'jobe_host');
$usecachelabel = get_string('bulktestusecachelabel', 'qtype_coderunner');
$usecachevalue = $usecache ? "true" : "false";
echo html_writer::tag('p', '<b>jobe_host:</b> ' . $jobehost);
echo html_writer::tag('p', "<b>$usecachelabel</b> $usecachevalue");
echo html_writer::tag('p', get_string('bulktestallcachenotclearedmessage', 'qtype_coderunner'));

// Run the tests.
ini_set('memory_limit', '2048M');  // For big question banks - TODO: make this a setting?
$contextdata = bulk_tester::get_num_coderunner_questions_by_context();
foreach ($contextdata as $contextid => $numcoderunnerquestions) {
    if ($skipping && $contextid != $startfromcontextid) {
        continue;
    }
    $skipping = false;
    $testcontext = context::instance_by_id($contextid);
    if (has_capability('moodle/question:editall', $context)) {
        if ($context->contextlevel == CONTEXT_COURSE) {
            $PAGE->set_context($testcontext);  // Helps grading cache pickup right course id.
        }  // Otherwise don't change context as it could be higher level, eg, course category
        $bulktester = new bulk_tester(
            context: $testcontext,
            randomseed: $randomseed,
            repeatrandomonly: $repeatrandomonly,
            nruns: $nruns,
            clearcachefirst: $clearcachefirst,
            usecache: $usecache
        );
        echo $OUTPUT->heading(get_string('bulktesttitle', 'qtype_coderunner', $testcontext->get_context_name()));
        echo html_writer::tag('p', html_writer::link(
            new moodle_url(
                '/question/type/coderunner/bulktestall.php',
                ['startfromcontextid' => $testcontext->id]
            ),
            get_string('bulktestcontinuefromhere', 'qtype_coderunner')
        ));
        [$passes, $failingtests, $missinganswers] = $bulktester->run_tests();
        $numpasses += $passes;
        $allfailingtests = array_merge($allfailingtests, $failingtests);
        $allmissinganswers = array_merge($allmissinganswers, $missinganswers);
    }
}

// Display the final summary.
bulk_tester::print_summary_after_bulktestall($numpasses, $allfailingtests, $allmissinganswers);
echo $OUTPUT->footer();
