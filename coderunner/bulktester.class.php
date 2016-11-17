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
 * This script provides a class with support methods for running question tests in bulk.
 * It is taken from the qtype_stack plugin with slight modifications.
 *
 * @package   qtype_coderunner
 * @copyright 2016 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class coderunner_bulk_tester  {

    /**
     * Get all the contexts that contain at least one CodeRunner question, with a
     * count of the number of those questions.
     *
     * @return array context id => number of CodeRunner questions.
     */
    public function get_coderunner_questions_by_context() {
        global $DB;

        return $DB->get_records_sql_menu("
            SELECT ctx.id, COUNT(q.id) AS numcoderunnerquestions
              FROM {context} ctx
              JOIN {question_categories} qc ON qc.contextid = ctx.id
              JOIN {question} q ON q.category = qc.id
             WHERE q.qtype = 'coderunner'
          GROUP BY ctx.id, ctx.path
          ORDER BY ctx.path
        ");
    }

    /**
     * Run the sample answer for all questions belonging to
     * a given context that have a sample answer.
     *
     * Do output as we go along.
     *
     * @param context $context the context to run the tests for.
     * @return array with two elements:
     *              bool true if the tests passed, else false.
     *              array of messages relating to the questions with failures.
     */
    public function run_all_tests_for_context(context $context) {
        global $DB, $OUTPUT;

        // Load the necessary data.
        $categories = question_category_options(array($context));
        $categories = reset($categories);
        $questiontestsurl = new moodle_url('/question/type/coderunner/questiontestrun.php');
        if ($context->contextlevel == CONTEXT_COURSE) {
            $questiontestsurl->param('courseid', $context->instanceid);
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $questiontestsurl->param('cmid', $context->instanceid);
        }
        $allpassed = true;
        $failingtests = array();

        foreach ($categories as $key => $category) {
            list($categoryid) = explode(',', $key);
            echo $OUTPUT->heading($category, 3);

            $questionids = $DB->get_records_menu('question',
                    array('category' => $categoryid, 'qtype' => 'coderunner'), 'name', 'id,name');
            if (!$questionids) {
                continue;
            }

            echo html_writer::tag('p', get_string('replacedollarscount', 'qtype_coderunner', count($questionids)));

            foreach ($questionids as $questionid => $name) {
                $questionname = format_string($name);
                $previewurl = new moodle_url($questiontestsurl, array('questionid' => $questionid));
                $questionnamelink = html_writer::link($previewurl, $questionname);
                echo $OUTPUT->heading($questionnamelink, 4);

                try {
                    $question = question_bank::load_question($questionid);
                } catch (coderunner_exception $e) {
                    echo "<p>\n**** " . get_string('questionloaderror', 'qtype_coderunner') . " $questionname: " . $e->getMessage() . " ****</p>";
                    continue;
                }

                if (empty(trim($question->answer))) {
                    echo "<p class='missinganswer'>No sample answer.</p>";
                } else {
                    $ok = $this->qtype_coderunner_test_question($question);
                    if (!$ok) {
                        $allpassed = false;
                        $failingtests[] = $questionnamelink . ': Failed';
                    }
                }

            }
        }

        return array($allpassed, $failingtests);
    }

    /**
     * Run the sample answer for the given question (if there is one).
     *
     * @param qtype_coderunner_question $question the question to test.
     * @return bool true if the sample answer passed, else false.
     */
    public function qtype_coderunner_test_question($question) {
        flush(); // Force output to prevent timeouts and to make progress clear.
        core_php_time_limit::raise(60); // Prevent PHP timeouts.
        gc_collect_cycles(); // Because PHP's default memory management is rubbish.

        // Grade the question
        $answer = $question->answer;
        try {
            list($fraction, $state) = $question->grade_response(array('answer' => $answer), false);
            $ok = $state == question_state::$gradedright;
        } catch (coderunner_exception $e) {
            $ok = false; // If user clicks link to see why, they'll get the same exception
        }

        echo html_writer::tag('p', $ok ? get_string('pass', 'qtype_coderunner') :
            get_string('fail', 'qtype_coderunner'));

        flush(); // Force output to prevent timeouts and to make progress clear.

        return $ok;
    }

    /**
     * Print an overall summary, with a link back to the bulk test index.
     *
     * @param bool $allpassed whether all the tests passed.
     * @param array $failingtests list of the ones that failed.
     */
    public function print_overall_result($allpassed, $failingtests) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('overallresult', 'qtype_coderunner'), 2);
        if ($allpassed) {
            echo html_writer::tag('p', get_string('coderunner_install_testsuite_pass', 'qtype_coderunner'),
                    array('class' => 'overallresult pass'));
        } else {
            echo html_writer::tag('p', get_string('coderunner_install_testsuite_fail', 'qtype_coderunner'),
                    array('class' => 'overallresult fail'));
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_failures', 'qtype_coderunner'), 3);
            echo html_writer::start_tag('ul');
            foreach ($failingtests as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }

        echo html_writer::tag('p', html_writer::link(new moodle_url('/question/type/coderunner/bulktestindex.php'),
                get_string('back')));
    }
}
