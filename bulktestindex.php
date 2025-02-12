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

$PAGE->set_url('/question/type/coderunner/bulktestindex.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('bulktestindextitle', 'qtype_coderunner'));

$nruns = 1;
$nrunsfromsettings = get_config('qtype_coderunner', 'bulktestdefaultnruns');
if (abs($nrunsfromsettings) > 1) {
    $nruns = abs($nrunsfromsettings);
}

// Find in which contexts the user can edit questions.
$questionsbycontext = bulk_tester::get_num_coderunner_questions_by_context();
$availablequestionsbycontext = [];
foreach ($questionsbycontext as $contextid => $numcoderunnerquestions) {
    $context = context::instance_by_id($contextid);
    if (has_capability('moodle/question:editall', $context)) {
        $coursecontext = $context->get_course_context(false);
        $coursename = $coursecontext->get_context_name(true, true);
        $contextname = $context->get_context_name(true, true);
        $name = "$coursename: $contextname";
        $availablequestionsbycontext[$name] = [
            'contextid' => $contextid,
            'numquestions' => $numcoderunnerquestions,
        ];
    }
}
ksort($availablequestionsbycontext);

// Display.
echo $OUTPUT->header();

// Add the configuration form
echo <<<HTML
<div class="bulk-test-config" style="margin-bottom: 20px; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">
    <h3>Test Configuration</h3>
    <div style="margin-bottom: 10px; display: grid; grid-template-columns: auto 80px; gap: 10px; align-items: center; max-width:400px;">
        <label for="nruns">Number of runs:</label>
        <input type="number" id="nruns" value="{$nruns}" min="1" style="width: 80px;">

        <label for="randomseed">Random seed:</label>
        <input type="number" id="randomseed" value="" min="0" style="width: 80px;">

        <label for="repeatrandomonly">Repeat random only:</label>
        <div>
            <input type="checkbox" id="repeatrandomonly" checked>
        </div>
        <label for="clearcachefirst">Clear course grading cache first (be careful):</label>
        <div>
            <input type="checkbox" id="clearcachefirst" onchange="confirmCheckboxChange(this)">
        </div>
    </div>
</div>
HTML;

// List all contexts available to the user.
if (count($availablequestionsbycontext) == 0) {
    echo html_writer::tag('p', get_string('unauthorisedbulktest', 'qtype_coderunner'));
} else {
    echo get_string('bulktestinfo', 'qtype_coderunner');
    echo $OUTPUT->heading(get_string('coderunnercontexts', 'qtype_coderunner'));
    $jobehost = get_config('qtype_coderunner', 'jobe_host');
    echo html_writer::tag('p', '<b>jobe_host:</b> ' . $jobehost);
    echo html_writer::start_tag('ul');
    $buttonstyle = 'background-color: #FFFFD0; padding: 2px 2px 0px 2px;border: 4px solid white';
    foreach ($availablequestionsbycontext as $name => $info) {
        $contextid = $info['contextid'];
        $numcoderunnerquestions = $info['numquestions'];

        $testallstr = get_string('bulktestallincontext', 'qtype_coderunner');
        $testalltitledetails = ['title' => get_string('testalltitle', 'qtype_coderunner'), 'style' => $buttonstyle];
        $testallspan = html_writer::tag(
            'span',
            $testallstr,
            ['class' => 'test-link',
             'data-contextid' => $contextid,
             'style' => $buttonstyle . ';cursor:pointer;']
        );
        $expandlink = html_writer::link(
            '#expand',
            get_string('expand', 'qtype_coderunner'),
            ['class' => 'expander', 'title' => get_string('expandtitle', 'qtype_coderunner'), 'style' => $buttonstyle]
        );
        $litext = $name . ' (' . $numcoderunnerquestions . ') ' . $testallspan . ' ' . $expandlink;
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
                     'style' => $buttonstyle . ';cursor:pointer;']
                );
                echo html_writer::tag('li', $span, $titledetails);
            }
        }
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
    }

    echo html_writer::end_tag('ul');
    echo html_writer::empty_tag('br');
    echo html_writer::tag('hr', '');
    echo html_writer::empty_tag('br');
    if (has_capability('moodle/site:config', context_system::instance())) {
        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/question/type/coderunner/bulktestall.php'),
            get_string('bulktestrun', 'qtype_coderunner')
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

            // Construct and navigate to URL
            var url = M.cfg.wwwroot + '/question/type/coderunner/bulktest.php?' + params.toString();
            window.location.href = url;
        });
    });
});
</script>
SCRIPT_END;

echo $OUTPUT->footer();
