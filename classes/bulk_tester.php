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
 * Modified to provide services for the prototype usage script and the
 * autotagger script.
 *
 * @package   qtype_coderunner
 * @copyright 2016, 2018 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_bulk_tester {
    const PASS = 0;
    const MISSINGANSWER = 1;
    const FAIL = 2;
    const EXCEPTION = 3;

    /**
     * Get all the courses and their contexts from the database
     *
     * @return array of course objects with id, contextid and name (short),
     * indexed by id
     */
    public function get_all_courses() {
        global $DB;

        return $DB->get_records_sql("
            SELECT crs.id, ctx.id as contextid, crs.shortname as name
              FROM {course} crs
              JOIN {context} ctx ON ctx.instanceid = crs.id
            WHERE ctx.contextlevel = 50
            ORDER BY name");
    }


    /**
     * Get all the contexts that contain at least one CodeRunner question, with a
     * count of the number of those questions. Only the latest version of each
     * question is counted.
     *
     * @return array context id => number of CodeRunner questions.
     */
    public function get_num_coderunner_questions_by_context() {
        global $DB;

        return $DB->get_records_sql_menu("
            SELECT ctx.id, COUNT(q.id) AS numcoderunnerquestions
            FROM {context} ctx
            JOIN {question_categories} qc ON qc.contextid = ctx.id
            JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON qv.questionid = q.id
            WHERE q.qtype = 'coderunner'
            AND (qv.version = (SELECT MAX(v.version)
                                FROM {question_versions} v
                                JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                WHERE be.id = qbe.id)
                              )
            GROUP BY ctx.id, ctx.path
            ORDER BY ctx.path
        ");
    }


    /**
     * Find all coderunner questions in a given category, returning only
     * the latest version of each question.
     * @param type $categoryid the id of a question category of interest
     * @return all coderunner question ids in any state and any version in the given
     * category. Each row in the returned list of rows has an id, name and version number.
     */
    public function coderunner_questions_in_category($categoryid) {
        global $DB;
        $rec = $DB->get_records_sql(
            "
            SELECT q.id, q.name, qv.version
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
            WHERE q.qtype = 'coderunner'
            AND (qv.version = (SELECT MAX(v.version)
                                FROM {question_versions} v
                                JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                WHERE be.id = qbe.id)
                              )
            AND qbe.questioncategoryid=:categoryid",
            ['categoryid' => $categoryid]
        );
        return $rec;
    }


    /**
     * Get a list of all the categories within the supplied contextid that
     * contain CodeRunner questions in any state and any version.
     * @return an associative array mapping from category id to an object
     * with name and count fields for all question categories in the given context
     * that contain one or more CodeRunner questions.
     * The 'count' field is the number of coderunner questions in the given
     * category.
     */
    public function get_categories_for_context($contextid) {
        global $DB;

        return $DB->get_records_sql(
            "
                SELECT qc.id, qc.parent, qc.name as name,
                       (SELECT count(1)
                        FROM {question} q
                        JOIN {question_versions} qv ON qv.questionid = q.id
                        JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
                        WHERE qc.id = qbe.questioncategoryid and q.qtype='coderunner') AS count
                FROM {question_categories} qc
                WHERE qc.contextid = :contextid
                ORDER BY qc.name",
            ['contextid' => $contextid]
        );
    }


    /**
     * Get all the coderunner questions in the given context.
     *
     * @param courseid The id of the course of interest.
     * @param includeprototypes true to include prototypes in the returned list.
     * @return array qid => question
     */
    public function get_all_coderunner_questions_in_context($contextid, $includeprototypes = 0) {
        global $DB;

        if ($includeprototypes) {
            $exclprototypes = '';
        } else {
            $exclprototypes = 'AND prototypetype=0';
        }

        return $DB->get_records_sql("
            SELECT q.id, ctx.id as contextid, qc.id as category, qc.name as categoryname, q.*, opts.*
              FROM {context} ctx
              JOIN {question_categories} qc ON qc.contextid = ctx.id
              JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              JOIN {question} q ON q.id = qv.questionid
              JOIN {question_coderunner_options} opts ON opts.questionid = q.id
              WHERE (qv.version = (SELECT MAX(v.version)
                                FROM {question_versions} v
                                JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                WHERE be.id = qbe.id)
                              )
              $exclprototypes
              AND ctx.id = :contextid
              ORDER BY name", ['contextid' => $contextid]);
    }

    /**
     * Run the sample answer for all questions belonging to
     * a given context that have a sample answer. Optionally restrict to a
     * specified question category.
     *
     * Do output as we go along.
     *
     * @param context $context the context to run the tests for.
     * @param int $categoryid test only questions in this category. Default to all.
     * @return array with three elements:
     *              int a count of how many tests passed
     *              array of messages relating to the questions with failures
     *              array of messages relating to the questions without sample answers
     */
    public function run_all_tests_for_context(context $context, $categoryid = null) {
        global $OUTPUT;

        // Load the necessary data.
        $coursename = $context->get_context_name(true, true);
        $categories = $this->get_categories_for_context($context->id);
        $questiontestsurl = new moodle_url('/question/type/coderunner/questiontestrun.php');
        if ($context->contextlevel == CONTEXT_COURSE) {
            $qparams['courseid'] = $context->instanceid;
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $qparams['cmid'] = $context->instanceid;
        } else {
            $qparams['courseid'] = SITEID;
        }
        $questiontestsurl->params($qparams);
        $numpasses = 0;
        $failingtests = [];
        $missinganswers = [];

        foreach ($categories as $currentcategoryid => $nameandcount) {
            if ($categoryid !== null && $currentcategoryid != $categoryid) {
                continue;
            }
            $questions = $this->coderunner_questions_in_category($currentcategoryid);
            if (!$questions) {
                continue;
            }

            echo $OUTPUT->heading("{$nameandcount->name} ($nameandcount->count)", 4);
            echo "<ul>\n";
            foreach ($questions as $question) {
                // Output question name before testing, so if something goes wrong, it is clear which question was the problem.
                $previewurl = new moodle_url(
                    $questiontestsurl,
                    ['questionid' => $question->id]
                );
                $enhancedname = "{$question->name} (V{$question->version})";
                $questionnamelink = html_writer::link($previewurl, $enhancedname, ['target' => '_blank']);
                echo "<li>$questionnamelink:";
                flush(); // Force output to prevent timeouts and show progress.

                // Now run the test.
                try {
                    [$outcome, $message] = $this->load_and_test_question($question->id);
                } catch (Exception $e) {
                    $message = $e->getMessage();
                    $outcome = self::FAIL;
                }

                // Report the result, and record failures for the summary.
                echo " $message</li>";
                flush(); // Force output to prevent timeouts and show progress.
                $qparams['category'] = $currentcategoryid . ',' . $context->id;
                $qparams['lastchanged'] = $question->id;
                $qparams['qperpage'] = 1000;
                $questionbankurl = new moodle_url('/question/edit.php', $qparams);
                $questionbanklink = html_writer::link($questionbankurl, $nameandcount->name, ['target' => '_blank']);
                if ($outcome === self::PASS) {
                    $numpasses += 1;
                } else if ($outcome === self::MISSINGANSWER) {
                    $missinganswers[] = "$coursename / $questionbanklink / $questionnamelink";
                } else {
                    $failingtests[] = "$coursename / $questionbanklink / $questionnamelink: $message";
                }
            }
            echo "</ul>\n";
        }

        return [$numpasses, $failingtests, $missinganswers];
    }


    /**
     * Load and test a specified question.
     * @param int $questionid the id of the question to be tested
     * @return array with 2 elements: the status (one of pass, fail, missinganswer
     *  or exception) and a string message describing the outcome.
     * TODO: extend to handle questions that have sample answer attachments.
     */
    private function load_and_test_question($questionid) {
        try {
            $question = question_bank::load_question($questionid);
            if (empty(trim($question->answer ?? ''))) {
                $message = get_string('nosampleanswer', 'qtype_coderunner');
                $status = self::MISSINGANSWER;
            } else {
                $ok = $this->test_question($question);
                if ($ok) {
                    $message = "<b style='color:green'>" . get_string('pass', 'qtype_coderunner') . "</b>";
                    $status = self::PASS;
                } else {
                    $message = "<b style='color:red'>" . get_string('fail', 'qtype_coderunner') . "</b>";
                    $status = self::FAIL;
                }
            }
        } catch (qtype_coderunner_exception $e) {
            if (isset($question)) {
                $questionname = ' ' . format_string($question->name);
            } else {
                $questionname = '';
            }
            $message = '**** ' . get_string('questionloaderror', 'qtype_coderunner') .
                    $questionname . '. ' . $e->getMessage() . ' ****';
            $status = self::EXCEPTION;
        }
        return [$status, $message];
    }

    /**
     * Run the sample answer for the given question (if there is one).
     *
     * @param qtype_coderunner_question $question the question to test.
     * @return bool true if the sample answer passed, else false.
     */
    private function test_question($question) {
        core_php_time_limit::raise(60); // Prevent PHP timeouts.
        gc_collect_cycles(); // Because PHP's default memory management is rubbish.
        $question->start_attempt(null);
        $response = $question->get_correct_response();
        // Check if it's a multilanguage question; if so need to determine
        // what language (either specified by answer_language template param, or
        // the AceLang default or the first).
        $params = empty($question->templateparams) ? [] : json_decode($question->templateparams, true);
        if (!empty($params['answer_language'])) {
            $response['language'] = $params['answer_language'];
        } else if (!empty($question->acelang) && strpos($question->acelang, ',') !== false) {
            [$languages, $defaultlang] = qtype_coderunner_util::extract_languages($question->acelang);
            if ($defaultlang === '') {
                $defaultlang = $languages[0];
            }
            $response['language'] = $defaultlang;
        }
        try {
            [$fraction, $state] = $question->grade_response($response, false);
            $ok = $state == question_state::$gradedright;
        } catch (qtype_coderunner_exception $e) {
            $ok = false; // If user clicks link to see why, they'll get the same exception.
        }
        return $ok;
    }

    /**
     * Print an overall summary, with a link back to the bulk test index.
     *
     * @param int $numpasses count of tests passed.
     * @param array $failingtests list of the ones that failed.
     * @param array $missinganswers list of all the ones without sample answers.
     */
    public function print_overall_result($numpasses, $failingtests, $missinganswers) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('overallresult', 'qtype_coderunner'), 2);
        echo html_writer::tag('p', $numpasses . ' ' . get_string('passes', 'qtype_coderunner'));
        echo html_writer::tag('p', count($failingtests) . ' ' . get_string('fails', 'qtype_coderunner'));
        echo html_writer::tag('p', count($missinganswers) . ' ' . get_string('missinganswers', 'qtype_coderunner'));

        if (count($failingtests) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_failures', 'qtype_coderunner'), 3);
            echo html_writer::start_tag('ul');
            foreach ($failingtests as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }

        if (count($missinganswers) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_noanswer', 'qtype_coderunner'), 3);
            echo html_writer::start_tag('ul');
            foreach ($missinganswers as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }

        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/question/type/coderunner/bulktestindex.php'),
            get_string('back')
        ));
    }


    /**
     *  Display the results of scanning all the CodeRunner questions to
     *  find all prototype usages in a particular course
     * @param $course an array of stdObj course objects
     * @param $prototypes an associative array of coderunnertype => question
     * @param $missingprototypes an array of questions for which no prototype
     * could be found.
     */
    public static function display_prototypes($courseid, $prototypes, $missingprototypes) {
        global $OUTPUT;
        ksort($prototypes, SORT_STRING | SORT_FLAG_CASE);
        foreach ($prototypes as $prototypename => $prototype) {
            if (isset($prototype->usages)) {
                $name = isset($prototype->name) ? " ({$prototype->category}/{$prototype->name})" : ' (global)';
                echo $OUTPUT->heading($prototypename, 4);
                echo $OUTPUT->heading($name, 6);
                echo html_writer::start_tag('ul');
                foreach ($prototype->usages as $question) {
                    echo html_writer::tag('li', self::make_question_link($courseid, $question));
                }
                echo html_writer::end_tag('ul');
            }
        }

        if ($missingprototypes) {
            echo $OUTPUT->heading(get_string('missingprototypes', 'qtype_coderunner'), 3);
            echo html_writer::start_tag('ul');
            foreach ($missingprototypes as $name => $questions) {
                $links = [];
                foreach ($questions as $question) {
                    $links[] = self::make_question_link($courseid, $question);
                }
                $itemlist = html_writer::tag('em', $name) . ': ' . implode(', ', $links);
                echo html_writer::tag('li', $itemlist);
            }
            echo html_writer::end_tag('ul');
        }
    }


    /**
     * Return a link to the given question in the question bank.
     * @param int $courseid the id of the course containing the question
     * @param stdObj $question the question
     * @return html link to the question in the question bank
     */
    private static function make_question_link($courseid, $question) {
        $qbankparams = ['qperpage' => 1000]; // Can't easily get the true value.
        $qbankparams['category'] = $question->category . ',' . $question->contextid;
        $qbankparams['lastchanged'] = $question->questionid;
        $qbankparams['courseid'] = $courseid;
        $qbankparams['showhidden'] = 1;
        $questionbanklink = new moodle_url('/question/edit.php', $qbankparams);
        return html_writer::link($questionbanklink, $question->name, ['target' => '_blank']);
    }
}
