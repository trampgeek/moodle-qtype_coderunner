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
 * This script provides an index for purging grading cache entries by course.
 *
 * @package   qtype_coderunner
 * @copyright 2024 Paul McKeown, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use context_system;
use context;
use html_writer;
use moodle_url;
use cache_config_writer;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

const GREENY = 'border: 1px solid #F0F0F0; background-color:rgb(232, 249, 213); padding: 2px 2px 0px 2px;';
const ORANGY = 'border: 1px solid #F0F0F0; background-color:rgb(249, 242, 213); padding: 2px 2px 0px 2px;';

// Login and check permissions.
$context = context_system::instance();
require_login();

cache_config_writer::update_definitions();

$PAGE->set_url('/question/type/coderunner/cachepurgeindex.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('cachepurgeindextitle', 'qtype_coderunner'));


// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coderunnercontexts', 'qtype_coderunner'));

// Find in which contexts the user can edit questions.



// TRIAL reading counts for full cache
$categorycounts = cache_purger::key_counts_for_all_cachecategories();
// NOTE: Should probably echo out the 'Uncategorized' and 'Unknown' category totals.
$keycountsbycontextid = cache_purger::key_counts_for_available_contextids($categorycounts);

// $contexttocoursecontextmap = cache_purger::get_context_to_course_context_map($keycountsbycontextid);
// echo '<br>';
// foreach ($contexttocoursecontextmap as $context => $coursecontext) {
//     echo "{$context} -> {$coursecontext}<br>";
// }
// $coursetocontextsmap = cache_purger::invert_array($contexttocoursecontextmap);
// echo '<br>';
// foreach ($coursetocontextsmap as $coursecontext => $contexts) {
//     echo "{$coursecontext} -> ";
//     print_r($contexts);
//     echo "<br>";
// }




//$allvisiblecoursecontexts = cache_purger::get_all_visible_course_and_coursecat_contextids();
//krsort($allvisiblecoursecontexts);  // Effectively newest first.
//$keycounts = cache_purger::key_count_by_context($allvisiblecoursecontexts);

// List all contexts available to the user.
if (count($keycountsbycontextid) == 0) {
    echo html_writer::tag('p', get_string('unauthorisedcachepurging', 'qtype_coderunner'));
} else {
    echo html_writer::tag('p', get_string('cachepurgeindexinfo', 'qtype_coderunner'));
    $ttl = abs(get_config('qtype_coderunner', 'gradecachettl'));
    $ttldays = round($ttl / 60 / 60 / 24, 4);
    echo html_writer::tag('p', get_String('currentttlinfo', 'qtype_coderunner', ['seconds' => $ttl, 'days' => $ttldays]));
    echo html_writer::start_tag('ul');
    $oldbuttongtext = get_string('purgeoldcachekeysbutton', 'qtype_coderunner');
    $allbuttongtext = get_string('purgeallcachekeysbutton', 'qtype_coderunner');
    foreach ($keycountsbycontextid as $contextid => $keycount) {
        $context = context::instance_by_id($contextid);
        $name = $context->get_context_name(true, true);
        // $courseid = $context->instanceid;
        $purgeusingttlurl = new moodle_url('/question/type/coderunner/cachepurge.php', ['contextid' => $contextid, 'usettl' => 1]);
        $buttonstyle = GREENY;
        $purgeusingttllink = html_writer::link(
            $purgeusingttlurl,
            $oldbuttongtext,
            ['title' => $oldbuttongtext,
            'style' => $buttonstyle]
        );
        $purgeallurl = new moodle_url('/question/type/coderunner/cachepurge.php', ['contextid' => $contextid, 'usettl' => 0]);
        $buttonstyle = ORANGY;
        $purgealllink = html_writer::link(
            $purgeallurl,
            $allbuttongtext,
            ['title' => $allbuttongtext,
            'style' => $buttonstyle]
        );
        $litext = $name .
            ' [Context id= ' .
            $contextid .
            '] &nbsp;&nbsp; cache size=' .
            $keycount .
            '&nbsp;&nbsp;&nbsp;' .
            $purgeusingttllink .
            '&nbsp;&nbsp;&nbsp;' .
            $purgealllink;
        $class = 'cachepurge coderunner context normal';
        echo html_writer::start_tag('li', ['class' => $class]);
        echo $litext;
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
    // Maybe do a purge all later or simply link to the admin cache purging page
    // and say to purge the coderunner grading cache ...
}

echo $OUTPUT->footer();
