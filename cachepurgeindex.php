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


require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Login and check permissions.
$context = context_system::instance();
require_login();

$PAGE->set_url('/question/type/coderunner/cachepurgeindex.php');
$PAGE->set_context($context);
$PAGE->set_title('Coderunner Cache Purge Index'); //get_string('bulktestindextitle', 'qtype_coderunner'));


// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coderunnercontexts', 'qtype_coderunner'));

// Find in which contexts the user can edit questions.
//$questionsbycontext = $bulktester->get_num_coderunner_questions_by_context();


$cachepurger = new cache_purger();
$allvisiblecoursecontexts = $cachepurger->get_all_visible_course_contextids();
krsort($allvisiblecoursecontexts);  // Effectively newest first.
$keycounts = $cachepurger->key_count_by_course($allvisiblecoursecontexts);

// List all contexts available to the user.
if (count($allvisiblecoursecontexts) == 0) {
    echo html_writer::tag('p', get_string('unauthorisedbulktest', 'qtype_coderunner'));
} else {
    echo html_writer::start_tag('ul');
    //$buttonstyle = 'border: 1px solid gray; padding: 2px 2px 0px 2px;';
    $buttonstyle = 'border: 1px solid #F0F0F0; background-color: #FFFFC0; padding: 2px 2px 0px 2px;border: 4px solid white';
    foreach ($allvisiblecoursecontexts as $contextid) {
        $context = context::instance_by_id($contextid);
        $name = $context->get_context_name(true, true);
        $courseid = $context->instanceid;

        $purgeusingttlurl = new moodle_url('/question/type/coderunner/cachepurge.php', ['contextid' => $contextid, 'usettl' => 1]);
        $purgeusingttllink = html_writer::link(
            $purgeusingttlurl,
            'Purge all old cache entries (ie, using TTL)',
            // get_string('bulktestallincontext', 'qtype_coderunner'),
            ['title' => 'Purge all old',  //get_string('testalltitle', 'qtype_coderunner'),
            'style' => $buttonstyle]
        );

        $purgeallurl = new moodle_url('/question/type/coderunner/cachepurge.php', ['contextid' => $contextid, 'usettl' => 0]);
        $purgealllink = html_writer::link(
            $purgeallurl,
            'Purge all in context',
            // get_string('bulktestallincontext', 'qtype_coderunner'),
            ['title' => 'Purge all',  //get_string('testalltitle', 'qtype_coderunner'),
            'style' => $buttonstyle]
        );


        $litext = $name . ' [Course id= ' . $courseid . '] &nbsp;&nbsp; cache size=' . $keycounts[$contextid] . '&nbsp;&nbsp;&nbsp;' .  $purgeusingttllink . '&nbsp;&nbsp;&nbsp;' . $purgealllink;
        $class = 'cachepurge coderunner context normal';
        echo html_writer::start_tag('li', ['class' => $class]);
        echo $litext;
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');

    // Maybe do a purge all later...
    // if (has_capability('moodle/site:config', context_system::instance())) {
    //     echo html_writer::tag('p', html_writer::link(
    //         new moodle_url('/question/type/coderunner/bulktestall.php'),
    //         get_string('bulktestrun', 'qtype_coderunner')
    //     ));
    // }
}


echo $OUTPUT->footer();
