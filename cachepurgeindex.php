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
use qtype_coderunner;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
const GREENY = 'border: 1px solid #F0F0F0; background-color:rgb(232, 249, 213); padding: 2px 2px 0px 2px;';
const ORANGY = 'border: 1px solid #F0F0F0; background-color:rgb(249, 242, 213); padding: 2px 2px 0px 2px;';
define('OLDBUTTONTEXT', get_string('purgeoldcachekeysbutton', 'qtype_coderunner')); // For button to purge old entries.
define('ALLBUTTONTEXT', get_string('purgeallcachekeysbutton', 'qtype_coderunner')); // For button to purge old entries.

// Login and check permissions.
$context = context_system::instance();
require_login();
cache_config_writer::update_definitions();
$PAGE->set_url('/question/type/coderunner/cachepurgeindex.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('cachepurgeindextitle', 'qtype_coderunner'));



function echo_cache_purge_header() {
    echo html_writer::tag('p', get_string('cachepurgeindexinfo', 'qtype_coderunner'));
    $ttl = abs(get_config('qtype_coderunner', 'gradecachettl'));
    $ttldays = round($ttl / 60 / 60 / 24, 4);
    echo html_writer::tag('p', get_String('currentttlinfo', 'qtype_coderunner', ['seconds' => $ttl, 'days' => $ttldays]));
}


function link_url_button(int $contextid, int $usettl): string {
    $buttonstyle = $usettl ? GREENY : ORANGY;
    $buttontext = $usettl ? OLDBUTTONTEXT : ALLBUTTONTEXT;
    $url = new moodle_url('/question/type/coderunner/cachepurge.php', ['contextid' => $contextid, 'usettl' => $usettl]);
    $link = html_writer::link(
        $url,
        $buttontext,
        ['title' => $buttontext,
        'style' => $buttonstyle]
    );
    return $link;
}


/**  Echos a list item for context, if it has any cache keys.
 * The item has the context name, the key count and buttons/links
 * for purge with ttl and purge all.
 */
function echo_line_for_context(int $contextid, string $contextname, int $keycount): void {
    if ($keycount > 0) {
        $purgeusingttllink = link_url_button($contextid, usettl: 1);
        $purgealllink = link_url_button($contextid, usettl : 0);
        $litext = "[{$contextid}] " .
            $contextname .
            ' &nbsp;&nbsp; (cache size=<b>' .
            $keycount .
            '</b>)&nbsp;&nbsp;&nbsp;' .
            $purgeusingttllink .
            '&nbsp;&nbsp;&nbsp;' .
            $purgealllink;
        $class = 'cachepurge coderunner context normal';
        echo html_writer::start_tag('li', ['class' => $class]);
        echo $litext;
        echo html_writer::end_tag('li');
    }
}

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coderunnercontexts', 'qtype_coderunner'));
echo '<hr>';

// Gets key counts for contexts the user has suitable rights to.
// NOTE: Should probably echo out the 'Uncategorized' and 'Unknown' category totals.
$keycountsbycontextid = cache_purger::key_counts_for_available_contextids();

// List all contexts available to the user.
if (count($keycountsbycontextid) == 0) {
    echo html_writer::tag('p', get_string('noquestionstopurge', 'qtype_coderunner'));
} else {
    echo_cache_purge_header();
    $oldskool = !(\qtype_coderunner_util::using_mod_qbank()); // No qbanks in Moodle versions less than 5.0.
    if (!$oldskool) {
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', "Moodle >= 5.0 detected. Listing by course then qbank contexts.");
        $allcourses = bulk_tester::get_all_courses();
        echo html_writer::tag('li', "Displaying all courses you have access to.");
        echo html_writer::tag('li', "Courses are displayed as: <em>[context_id] Course Name (course_id)</em>");
        echo html_writer::tag('li', "Qbanks and other question containing contexts are displayed as: <em>[context_id] Context prefix:Context name </em>");
        echo '<hr>';
        echo html_writer::end_tag('ul');
        foreach ($allcourses as $courseid => $course) {
            $coursecontext = \context_course::instance($courseid);
            // Only list for courses that are visible to user.
            if (has_capability('moodle/question:editall', $coursecontext)) {
                echo $OUTPUT->heading("[{$coursecontext->id}] {$course->name} ({$courseid})", 4);
                $allbanks = bulk_tester::get_all_qbanks_for_course($courseid);
                if (count($allbanks) > 0) {
                    echo html_writer::start_tag('ul');
                    $totalkeysforcourse = 0;
                    foreach ($allbanks as $qbank) {
                        $contextid = $qbank->contextid;
                        $context = \context::instance_by_id($contextid);
                        $name = $context->get_context_name(true, true);
                        $keycount = $keycountsbycontextid[$contextid] ?? 0;
                        $totalkeysforcourse += $keycount;
                        echo_line_for_context($contextid, $name, $keycount);
                    } // For each qbank
                    if ($totalkeysforcourse == 0) {
                        echo html_writer::tag('li', '<em>No grading cache entries for course.</em>');
                    }
                    echo html_writer::end_tag('ul');
                }
            }
        }
    } else {  // We're going old skool.
        // Old skool method does this for all contexts with questions.
        // These contexts will typically be courses or course contexts.
        echo html_writer::tag('p', 'NOTE: Legacy mode (Moodle ver < 5.0) so only courses with grade cache entries will be listed.');
        echo html_writer::start_tag('ul');
        foreach ($keycountsbycontextid as $contextid => $keycount) {
            $context = context::instance_by_id($contextid);
            $name = $context->get_context_name(true, true);
            echo_line_for_context($contextid, $name, $keycount);
        }
        echo html_writer::end_tag('ul');
    }
    // Display link to admin->cache settings in case someone wants to fully purge grading cache.
    echo html_writer::tag('p', "Use link below to open Moodle cache admin page so you can purge the whole coderunner_grading_cache.");
    if (has_capability('moodle/site:config', context_system::instance())) {
        $link = html_writer::link(
            new moodle_url('/cache/admin.php'),
            "Open admin-cache page - for purging whole grading cache.",
            ['class' => 'link-to-cache-admin',
            'data-contextid' => 0,
            'style' => ORANGY . ";cursor:pointer;"]
        );
        echo html_writer::tag('p', $link);
    }
}
echo $OUTPUT->footer();
