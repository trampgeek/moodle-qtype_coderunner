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
 * This script provides bulktests for Coderunner questions.
 *
 * @package   qtype_coderunner
 * @copyright 2016-2025 Richard Lobb and Paul McKeown, The University of Canterbury.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace qtype_coderunner;
use context_system;
use context;
use context_course;
use html_writer;
use moodle_url;
use qtype_coderunner_util;
use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_edit_contexts;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// We are Moodle 4 or less if don't have mod_qbank.
$oldskool = !(qtype_coderunner_util::using_mod_qbank());


// Login and check permissions.
$context = context_system::instance();
require_login();

const BUTTONSTYLE = 'background-color: #FFFFD0; padding: 2px 2px 0px 2px;border: 4px solid white';



function display_course_header_and_link($coursecontextid, $coursename) {
    $testalltitledetails = ['title' => get_string('testalltitle', 'qtype_coderunner')];
    $linktext = $coursename;
    $testallspan = html_writer::tag(
        'span',
        $linktext,
        ['class' => 'test-link',
        'data-contextid' => $coursecontextid,
        'style' => BUTTONSTYLE . ';cursor:pointer;']
    );
    $litext = $coursecontextid . ' - ' . $coursename . ' ' . $testallspan;
    echo html_writer::tag('h5', $litext, $testalltitledetails);
}


function display_questions_for_context($contextid, $name, $numcoderunnerquestions) {
    $testallstr = get_string('bulktestallincontext', 'qtype_coderunner');
    $testalltitledetails = ['title' => get_string('testalltitle', 'qtype_coderunner'), 'style' => BUTTONSTYLE];
    $testallspan = html_writer::tag(
        'span',
        $testallstr,
        ['class' => 'test-link',
        'data-contextid' => $contextid,
        'style' => BUTTONSTYLE . ';cursor:pointer;']
    );
    $expandlink = html_writer::link(
        '#expand',
        get_string('expand', 'qtype_coderunner'),
        ['class' => 'expander', 'title' => get_string('expandtitle', 'qtype_coderunner'), 'style' => BUTTONSTYLE]
    );
    $litext = $contextid . ' - ' . $name . ' (' . $numcoderunnerquestions . ') ' . $testallspan . ' ' . $expandlink;
    if (strpos($name, ": Quiz: ") === false) {
        $class = 'bulktest coderunner context normal';
    } else {
        $class = 'bulktest coderunner context quiz';
    }
    echo html_writer::start_tag('li', ['class' => $class]);
    echo $litext;

    $categories = bulk_tester::get_categories_for_context($contextid);
    echo html_writer::start_tag('ul', ['class' => 'expandable']);
    $titledetails = ['title' => get_string('testallincategory', 'qtype_coderunner')];
    foreach ($categories as $cat) {
        if ($cat->count > 0) {
            $linktext = $cat->name . ' (' . $cat->count . ')';
            $span = html_writer::tag(
                'span',
                $linktext,
                ['class' => 'test-link',
                'data-contextid' => $contextid,
                'data-categoryid' => $cat->id,
                'style' => BUTTONSTYLE . ';cursor:pointer;']
            );
            echo html_writer::tag('li', $span, $titledetails);
        }
    }
    echo html_writer::end_tag('ul');  // End category list.
    echo html_writer::end_tag('li');  // End context list item.
}


/**
 * Displays questions for all available contexts with questions.
 * Probably not much use now...
 * $availablequestionsbycontext maps
 *    from contextid to [name, numquestions] associative arrays.
 */
function display_questions_for_all_contexts($availablequestionsbycontext) {
    echo html_writer::start_tag('ul');
    foreach ($availablequestionsbycontext as $contextid => $info) {
        $name = $info['name'];
        $numcoderunnerquestions = $info['numquestions'];
        display_questions_for_context($contextid, $name, $numcoderunnerquestions);
    }
     echo html_writer::end_tag('ul');
}



/**
 * Used for displaying all the questions in the Oldskool Moodle 4 setup.
 * $availablequestionsbycontext maps from
 *    contextid to [name, numquestions] associative arrays.
 */
function display_questions_for_all_course_contexts($availablequestionsbycontext) {
    foreach ($availablequestionsbycontext as $contextid => $info) {
        $context = context::instance_by_id($contextid);
        if ($context->contextlevel === CONTEXT_COURSE || $context->contextlevel === CONTEXT_COURSECAT) {
            $name = $info['name'];
            $numcoderunnerquestions = $info['numquestions'];
            display_questions_for_context($contextid, $name, $numcoderunnerquestions);
        }
    }
}



$PAGE->set_url('/question/type/coderunner/bulktestindex.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('bulktestindextitle', 'qtype_coderunner'));

$nruns = 1;
$nrunsfromsettings = get_config('qtype_coderunner', 'bulktestdefaultnruns');
if (abs($nrunsfromsettings) > 1) {
    $nruns = abs($nrunsfromsettings);
}

$numrunslabel = get_string('bulktestnumrunslabel', 'qtype_coderunner');
$numrunsexplanation = get_string('bulktestnumrunsexplanation', 'qtype_coderunner');

$randomseedlabel = get_string('bulktestrandomseedlabel', 'qtype_coderunner');
$randomseedexplanation = get_string('bulktestrandomseedexplanation', 'qtype_coderunner');

$repeatrandomonlylabel = get_string('bulktestrepeatrandomonlylabel', 'qtype_coderunner');
$repeatrandomonlyexplanation = get_string('bulktestrepeatrandomonlyexplanation', 'qtype_coderunner');

$clearcachefirstlabel = get_string('bulktestclearcachefirstlabel', 'qtype_coderunner');
$clearcachefirstexplanation = get_string('bulktestclearcachefirstexplanation', 'qtype_coderunner');

$usecachelabel = get_string('bulktestusecachelabel', 'qtype_coderunner');
$usecacheexplanation = get_string('bulktestusecacheexplanation', 'qtype_coderunner');

// Display.
echo $OUTPUT->header();

// Add the configuration form.

echo <<<HTML
<div class="bulk-test-config" style="margin-bottom: 20px; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">
    <h3>Test Configuration</h3>
    <div style="margin-bottom: 10px; display: grid; grid-template-columns: 180pt 80pt auto; gap: 10px; align-items: center; max-width:1000;">
        <div style="grid-column: span 3; border-top: 1px solid rgb(10, 16, 74);"> </div>
        <label for="nruns">$numrunslabel</label>
        <input type="number" id="nruns" class="form-control" value="{$nruns}" min="1" style="width: 80px;">
        <span>$numrunsexplanation</span>
        <div style="grid-column: span 3; border-bottom: 1px solid rgb(10, 16, 74);"> </div>

        <label for="randomseed">$randomseedlabel</label>
        <input type="number" id="randomseed" class="form-control" value="" min="0" style="width: 80px;">
        <span>$randomseedexplanation</span>
        <div style="grid-column: span 3; border-bottom: 1px solid rgb(10, 16, 74);"> </div>
        <label for="repeatrandomonly">$repeatrandomonlylabel</label>
        <div>
            <input type="checkbox" id="repeatrandomonly" checked>
        </div>
        <span>$repeatrandomonlyexplanation</span>
        <div style="grid-column: span 3; border-bottom: 1px solid rgb(10, 16, 74);"> </div>
        <label for="clearcachefirst">$clearcachefirstlabel</label>
        <div>
            <input type="checkbox" id="clearcachefirst"  onchange="confirmCheckboxChange(this)">
        </div>
        <span>$clearcachefirstexplanation</span>
        <div style="grid-column: span 3; border-bottom: 1px solid rgb(10, 16, 74);"> </div>
        <label for="usecache">$usecachelabel</label>
        <div>
            <input type="checkbox" id="usecache" checked>
        </div>
        <span>$usecacheexplanation</span>
        <div style="grid-column: span 3; border-bottom: 1px solid rgb(10, 16, 74);"> </div>
    </div>
</div>
HTML;


// Find questions from contexts which the user can edit questions in.
$availablequestionsbycontext = bulk_tester::get_num_available_coderunner_questions_by_context();

$jobehost = get_config('qtype_coderunner', 'jobe_host');
if (count($availablequestionsbycontext) == 0) {
    echo html_writer::tag('p', get_string('unauthorisedbulktest', 'qtype_coderunner'));
} else {
    echo html_writer::tag('p', '<b>jobe_host:</b> ' . $jobehost);
    // Something to do
    if ($oldskool) {
        // Moodle 4 style.
        echo $OUTPUT->heading(get_string('coderunnercontexts', 'qtype_coderunner'));
        display_questions_for_all_course_contexts($availablequestionsbycontext);
    } else {
        // Deal with funky question bank madness in Moodle 5.0.
        echo html_writer::tag('p', "Moodle >= 5.0 detected. Listing by course then qbank.");
        $allcourses = bulk_tester::get_all_courses();
        foreach ($allcourses as $courseid => $course) {
            $coursecontext = context_course::instance($courseid);
            display_course_header_and_link($coursecontext->id, $course->name);
            $allbanks = bulk_tester::get_all_qbanks_for_course($courseid);
            if (count($allbanks) > 0) {
                echo html_writer::start_tag('ul');
                foreach ($allbanks as $bank) {
                    $contextid = $bank->contextid;
                    if (array_key_exists($contextid, $availablequestionsbycontext)) {
                        $contextdata = $availablequestionsbycontext[$contextid];
                        $name = $contextdata['name'];
                        $numquestions = $contextdata['numquestions'];
                        $coursenamebankname = $bank->coursenamebankname;
                        display_questions_for_context($contextid, $name, $numquestions);
                    }
                }
                echo html_writer::end_tag('ul');
            }
        }
    }
    // Output final stuff, including link to bulktestall.
    echo html_writer::empty_tag('br');
    echo html_writer::tag('hr', '');
    echo html_writer::empty_tag('br');
    if (has_capability('moodle/site:config', context_system::instance())) {
        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/question/type/coderunner/bulktestall.php'),
            get_string('bulktestrun', 'qtype_coderunner'),
            ['class' => 'test-all-link',
            'data-contextid' => 0,
            'style' => BUTTONSTYLE . ';cursor:pointer;']
        ));
    }
}



echo <<<SCRIPT_END
<script>
function confirmCheckboxChange(checkbox) {
    if (checkbox.checked) {
        var prompt = "Are you sure you want to clear the cache for the selected course?";
        prompt = prompt + " This will clear the cache for all attempts on all questions!";
        const confirmed = confirm(prompt);
        if (!confirmed) {
            checkbox.checked = false;
        }
    }
}

document.addEventListener("DOMContentLoaded", function(event) {
    // Handle expandable sections
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
                expander.innerHTML = 'Expand';
                expander.nextSibling.style.display = 'none';
            }
        });
    });

    // Handle test links
    var testLinks = document.getElementsByClassName('test-link');
    Array.from(testLinks).forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();

            // Get configuration values
            var nruns = document.getElementById('nruns').value;
            var randomseed = document.getElementById('randomseed').value;
            var repeatrandomonly = document.getElementById('repeatrandomonly').checked ? 1 : 0;
            var clearcachefirst = document.getElementById('clearcachefirst').checked ? 1 : 0;
            var usecache = document.getElementById('usecache').checked ? 1 : 0;

            // Build URL parameters
            var params = new URLSearchParams();
            params.append('contextid', link.dataset.contextid);

            // Add category ID if present
            if (link.dataset.categoryid) {
                params.append('categoryid', link.dataset.categoryid);
            }
            params.append('nruns', nruns);
            params.append('randomseed', randomseed);
            params.append('repeatrandomonly', repeatrandomonly);
            params.append('clearcachefirst', clearcachefirst);
            params.append('usecache', usecache);

            // Construct and navigate to URL
            var url = M.cfg.wwwroot + '/question/type/coderunner/bulktest.php?' + params.toString();
            window.location.href = url;
        });
    });


    // Handle test all link
    var testLinks = document.getElementsByClassName('test-all-link');
    Array.from(testLinks).forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();

            // Get configuration values
            var nruns = document.getElementById('nruns').value;
            var randomseed = document.getElementById('randomseed').value;
            var repeatrandomonly = document.getElementById('repeatrandomonly').checked ? 1 : 0;
            var clearcachefirst = document.getElementById('clearcachefirst').checked ? 1 : 0;
            var usecache = document.getElementById('usecache').checked ? 1 : 0;

            // Build URL parameters
            var params = new URLSearchParams();
            params.append('contextid', link.dataset.contextid);

            // Add category ID if present
            if (link.dataset.categoryid) {
                params.append('categoryid', link.dataset.categoryid);
            }
            params.append('nruns', nruns);
            params.append('randomseed', randomseed);
            params.append('repeatrandomonly', repeatrandomonly);
            params.append('clearcachefirst', clearcachefirst);
            params.append('usecache', usecache);

            // Construct and navigate to URL
            var url = M.cfg.wwwroot + '/question/type/coderunner/bulktestall.php?' + params.toString();
            window.location.href = url;
        });
    });
});
</script>
SCRIPT_END;

echo $OUTPUT->footer();
