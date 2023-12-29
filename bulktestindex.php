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
 * @copyright 2016, 2017 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Login and check permissions.
$context = context_system::instance();
require_login();

$PAGE->set_url('/question/type/coderunner/bulktestindex.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('bulktestindextitle', 'qtype_coderunner'));

// Create the helper class.
$bulktester = new qtype_coderunner_bulk_tester();

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coderunnercontexts', 'qtype_coderunner'));

// Find in which contexts the user can edit questions.
$questionsbycontext = $bulktester->get_num_coderunner_questions_by_context();
$availablequestionsbycontext = [];
foreach ($questionsbycontext as $contextid => $numcoderunnerquestions) {
    $context = context::instance_by_id($contextid);
    if (has_capability('moodle/question:editall', $context)) {
        $name = $context->get_context_name(true, true);
        if (strpos($name, 'Quiz:') === 0) { // Quiz-specific question category.
            $course = $context->get_course_context(false);
            if ($course === false) {
                $name = 'UnknownCourse: ' . $name;
            } else {
                $name = $course->get_context_name(true, true) . ': ' . $name;
            }
        }
        $availablequestionsbycontext[$name] = [
            'contextid' => $contextid,
            'numquestions' => $numcoderunnerquestions];
    }
}

ksort($availablequestionsbycontext);

// List all contexts available to the user.
if (count($availablequestionsbycontext) == 0) {
    echo html_writer::tag('p', get_string('unauthorisedbulktest', 'qtype_coderunner'));
} else {
    echo html_writer::start_tag('ul');
    $buttonstyle = 'border: 1px solid gray; padding: 2px 2px 0px 2px;';
    $buttonstyle = 'border: 1px solid #F0F0F0; background-color: #FFFFC0; padding: 2px 2px 0px 2px;border: 4px solid white';
    foreach ($availablequestionsbycontext as $name => $info) {
        $contextid = $info['contextid'];
        $numcoderunnerquestions = $info['numquestions'];

        $testallurl = new moodle_url('/question/type/coderunner/bulktest.php', ['contextid' => $contextid]);
        $testalllink = html_writer::link(
            $testallurl,
            get_string('bulktestallincontext', 'qtype_coderunner'),
            ['title' => get_string('testalltitle', 'qtype_coderunner'),
            'style' => $buttonstyle]
        );
        $expandlink = html_writer::link(
            '#expand',
            get_string('expand', 'qtype_coderunner'),
            ['class' => 'expander',
                      'title' => get_string('expandtitle', 'qtype_coderunner'),
            'style' => $buttonstyle]
        );
        $litext = $name . ' (' . $numcoderunnerquestions . ') ' . $testalllink . ' ' . $expandlink;
        if (strpos($name, 'Quiz:') === 0) {
            $class = 'bulktest coderunner context quiz';
        } else {
            $class = 'bulktest coderunner context normal';
        }

        if (strpos($name, ": Quiz: ") === false) {
            $class = 'bulktest coderunner context normal';
        } else {
            $class = 'bulktest coderunner context quiz';
        }
        echo html_writer::start_tag('li', ['class' => $class]);
        echo $litext;

        $categories = $bulktester->get_categories_for_context($contextid);
        echo html_writer::start_tag('ul', ['class' => 'expandable']);
        foreach ($categories as $cat) {
            if ($cat->count > 0) {
                $url = new moodle_url(
                    '/question/type/coderunner/bulktest.php',
                    ['contextid' => $contextid, 'categoryid' => $cat->id]
                );
                $linktext = $cat->name . ' (' . $cat->count . ')';
                $link = html_writer::link($url, $linktext, ['style' => $buttonstyle]);
                echo html_writer::tag(
                    'li',
                    $link,
                    ['title' => get_string('testallincategory', 'qtype_coderunner')]
                );
            }
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
    }

    echo html_writer::end_tag('ul');

    if (has_capability('moodle/site:config', context_system::instance())) {
        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/question/type/coderunner/bulktestall.php'),
            get_string('bulktestrun', 'qtype_coderunner')
        ));
    }
}

echo <<<SCRIPT_END
<script>
document.addEventListener("DOMContentLoaded", function(event) {
    var expandables = document.getElementsByClassName('expandable');
    Array.from(expandables).forEach(function (expandable) {
        expandable.style.display = 'none';
    });
    var expanders = document.getElementsByClassName('expander');
    Array.from(expanders).forEach(function(expander) {
        expander.addEventListener('click', function(event) {
            event.preventDefault();
            if (expander.innerHTML == 'Expand') {
                expander.innerHTML = 'Collapse';
                expander.nextSibling.style.display = 'inline';
            } else {
                expander.innerTHML = 'Expand';
                expander.nextSibling.style.display = 'none';
            }
        });
    });
});
</script>
SCRIPT_END;

echo $OUTPUT->footer();
