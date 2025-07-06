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
 * This script purges the Coderunner grading cache entries for all the
 * questions in a given course. If useTTL is set then it will only
 * purge cache entries that are older than the TTL (Time To Live) as
 * set in the Coderunner admin settings.
 *
 * @package   qtype_coderunner
 * @copyright 2024 Paul McKeown, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qtype_coderunner;

use context;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
// require_once($CFG->libdir . '/questionlib.php');


// Get the parameters from the URL.
$contextid = required_param('contextid', PARAM_INT);
$usettl = required_param('usettl', PARAM_INT);
$usettl = $usettl === 1; // 1 for use, 0 for don't use.

// Login and check permissions.
$context = context::instance_by_id($contextid);
require_login();
require_capability('moodle/question:editall', $context);
$PAGE->set_url('/question/type/coderunner/cachepurge.php', ['contextid' => $context->id, 'useTTL' => $usettl]);
$PAGE->set_context($context);
$title = get_string('cachepurgepagetitle', 'qtype_coderunner', $context->get_context_name()); // 'Purging cache for $a' . $context->get_context_name(); //
$PAGE->set_title($title);

if ($context->contextlevel == CONTEXT_MODULE) {
    // Calling $PAGE->set_context should be enough, but it seems that it is not.
    // Therefore, we get the right $cm and $course, and set things up ourselves.
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $PAGE->set_cm($cm, $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST));
}


// Release the session, so the user can do other things while this runs.
\core\session\manager::write_close();

$purger = new cache_purger($usettl);
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 4);
$purger->purge_cache_for_context($context->id);
echo $OUTPUT->footer();
