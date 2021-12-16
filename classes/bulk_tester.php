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

defined('MOODLE_INTERNAL') || die();

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
     * Get all available prototypes for the given course.
     * @param $courseid the id of the course whose context is searched for prototypes
     * @return stdClass[] question prototype rows from question joined to
     * coderunner_options, keyed by coderunnertype
     */
    public static function get_all_prototypes($courseid) {
        global $DB;
        $coursecontext = context_course::instance($courseid);
        list($contextcondition, $params) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids(true));

        $rows = $DB->get_records_sql("
                SELECT qco.coderunnertype, q.name, qco.*
                  FROM {question_coderunner_options} qco
                  JOIN {question} q ON q.id = qco.questionid
                  JOIN {question_categories} qc ON qc.id = q.category
                  WHERE prototypetype != 0 AND qc.contextid $contextcondition
                  ORDER BY coderunnertype", $params);
        return $rows;
    }

    /**
     * Get all the contexts that contain at least one CodeRunner question, with a
     * count of the number of those questions.
     *
     * @return array context id => number of CodeRunner questions.
     */
    public function get_num_coderunner_questions_by_context() {
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
     *
     * @param type $categoryid the id of a question category of interest
     * @return a count of the number of questions in the given category.
     */
    public function count_questions_in_category($categoryid) {
        global $DB;
        $rec = $DB->get_record_sql("
            SELECT count(q.id) as count
            FROM {question} q
            WHERE q.category=:categoryid",
                array('categoryid' => $categoryid));
        return $rec->count;
    }


    /**
     *
     * @param type $categoryid the id of a question category of interest
     * @return an array of the categoryid of all child categories
     */
    public function child_categories($categoryid) {
        global $DB;
        $rows = $DB->get_records_sql("
            SELECT id
            FROM {question_categories} qc
            WHERE qc.parent=:categoryid",
                array('categoryid' => $categoryid));
        $children = array();
        foreach ($rows as $row) {
            $children[] = $row->id;
        }
        return $children;
    }

    // Return the name of the given category id.
    public function category_name($categoryid) {
        global $DB;
        $row = $DB->get_record_sql("
            SELECT name
            FROM {question_categories} qc
            WHERE qc.id=:categoryid",
                array('categoryid' => $categoryid));
        return $row->name;
    }

    // Delete the given question category id.
    public function delete_category($categoryid) {
        global $DB;
        $DB->delete_records("question_categories", array("id" => $categoryid));
    }

    /**
     * Get a list of all the categories within the supplied contextid.
     * @return an associative array mapping from category id to an object
     * with name and count fields for all question categories in the given context
     * that contain one or more CodeRunner questions.
     * The 'count' field is the number of coderunner questions in the given
     * category.
     */
    public function get_categories_for_context($contextid) {
        global $DB;

        return $DB->get_records_sql("
                SELECT qc.id, qc.parent, qc.name as name,
                       (SELECT count(1)
                        FROM {question} q
                        WHERE qc.id = q.category AND q.qtype='coderunner') AS count
                FROM {question_categories} qc
                WHERE qc.contextid = :contextid
                ORDER BY qc.name",
            array('contextid' => $contextid));
    }


    /**
     * Get a list of all the categories within the supplied contextid.
     * @return an associative array mapping from category id to an object
     * with name and count fields for all question categories in the given context.
     * The 'count' field is the number of all questions in the given
     * category.
     */
    public function get_all_categories_for_context($contextid) {
        global $DB;

        return $DB->get_records_sql("
                SELECT qc.id, qc.parent, qc.name as name,
                       (SELECT count(1)
                        FROM {question} q
                        WHERE qc.id = q.category) AS count
                FROM {question_categories} qc
                WHERE qc.contextid = :contextid
                ORDER BY qc.name",
            array('contextid' => $contextid));
    }

    /**
     * Categories are tree structured, with each category containing a link
     * to its parent node. Thus any given category can be defined by a path-
     * like variable consisting of a '/'-separated list of all the names
     * on the path from the root to the nominated category node.
     * Given a category id, return that path.
     * @global type $DB
     * @param type $categoryid
     */
    public function category_path($categoryid) {
        global $DB;

        $path = '';
        $catid = $categoryid;
        while ($catid != 0) {
            $node = $DB->get_record_sql("
                SELECT id, name, parent FROM {question_categories}
                WHERE id=:catid
            ", array('catid' => $catid));
            $path = $node->name . '/' . $path;
            $catid = $node->parent;
        }
        return $path;
    }


    /**
     * Add the given tag to all questions in the given categoryid
     * @param int $contextid  The current context. Not sure why this is needed.
     * @param int $categoryid The category in which questions are to be tagged
     * @param string $tag The tag to add
     */
    public function tag_by_category($contextid, $categoryid, $tag) {
        global $DB;

        $context = context::instance_by_id($contextid);
        $questionids = $DB->get_records_sql(
                "SELECT q.id FROM {question} q WHERE q.category = :catid",
                array('catid' => $categoryid));
        foreach (array_keys($questionids) as $questionid) {
            $name = $DB->get_field_sql('SELECT name FROM {question} q where q.id = :questionid',
                    array('questionid' => $questionid));
            echo "Added tag <em>$tag</em> to question $questionid, '$name'<br>";
            core_tag_tag::add_item_tag('core_question', 'question', $questionid, $context, $tag);
            question_bank::notify_question_edited($questionid);
        }
    }


    /**
     * Add the given tag to all questions in the given quiz (specified by id).
     * @param int $contextid  The current context. Not sure why this is needed.
     * @param int $quizid The id of the quiz in which questions are to be tagged
     * @param string $tag The tag to add
     */
    public function tag_by_quiz($contextid, $quizid, $tag) {
        global $DB;

        $context = context::instance_by_id($contextid);
        $questionids = $DB->get_records_sql(
                "SELECT q.id
                 FROM {question} q JOIN {quiz_slots} slt
                 ON q.id = slt.questionid
                 WHERE slt.quizid = :quizid AND q.qtype='coderunner'",
                array('quizid' => $quizid));
        foreach (array_keys($questionids) as $questionid) {
            $name = $DB->get_field_sql('SELECT name FROM {question} q where q.id = :questionid',
                    array('questionid' => $questionid));
            echo "Added tag <em>$tag</em> to question $questionid, '$name'<br>";
            core_tag_tag::add_item_tag('core_question', 'question', $questionid, $context, $tag);
            question_bank::notify_question_edited($questionid);
        }
    }

    /**
     * Return all the quizzes in the given context
     * @param int $contextid the course context
     * @return associative array with keys being quizid and value being an
     * object with a name field (the quiz name) and a count field (the number
     * of CodeRunner questions in the quiz).
     */
    public function get_quizzes_for_context($contextid) {
        global $DB;

        return $DB->get_records_sql("
            SELECT qz.id as quizid, qz.name as name,
                (SELECT count(1)
                 FROM {question} q JOIN {quiz_slots} slt
                 ON q.id = slt.questionid
                 WHERE slt.quizid = qz.id AND q.qtype='coderunner'
                ) AS count
            FROM {quiz} qz
            JOIN {course} crs on qz.course = crs.id
            JOIN {context} ctx on ctx.instanceid = crs.id
            WHERE ctx.id = :contextid
            ORDER BY name",
                array('contextid' => $contextid)
        );
    }


    /**
     * Get all the non-prototype coderunner questions in the given context.
     *
     * @param courseid
     * @return array qid => question
     */
    public function get_all_coderunner_questions_in_context($contextid) {
        global $DB;

        return $DB->get_records_sql("
            SELECT q.id, ctx.id as contextid, qc.id as category, qc.name as categoryname, q.*, opts.*
              FROM {context} ctx
              JOIN {question_categories} qc ON qc.contextid = ctx.id
              JOIN {question} q ON q.category = qc.id
              JOIN {question_coderunner_options} opts ON opts.questionid = q.id
              WHERE prototypetype = 0
              AND ctx.id = :contextid
              ORDER BY name", array('contextid' => $contextid));
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
    public function run_all_tests_for_context(context $context, $categoryid=null) {
        global $DB, $OUTPUT;

        // Load the necessary data.
        $categories = get_categories_for_contexts($context->id);
        $questiontestsurl = new moodle_url('/question/type/coderunner/questiontestrun.php');
        if ($context->contextlevel == CONTEXT_COURSE) {
            $questiontestsurl->param('courseid', $context->instanceid);
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $questiontestsurl->param('cmid', $context->instanceid);
        } else {
            $questiontestsurl->param('courseid', SITEID);
        }
        $numpasses = 0;
        $failingtests = array();
        $missinganswers = array();

        foreach ($categories as $category) {
            if ($categoryid !== null && $category->id != $categoryid) {
                continue;
            }
            $questionids = $DB->get_records_menu('question',
                    array('category' => $category->id, 'qtype' => 'coderunner'), 'name', 'id,name');
            if (!$questionids) {
                continue;
            }

            $numquestions = count($questionids);
            echo $OUTPUT->heading("{$category->name} ($numquestions)", 4);
            echo "<ul>\n";
            foreach ($questionids as $questionid => $name) {
                // Output question name before testing, so if something goes wrong, it is clear which question was the problem.
                $questionname = format_string($name);
                $previewurl = new moodle_url($questiontestsurl, array('questionid' => $questionid));
                $questionnamelink = html_writer::link($previewurl, $questionname, array('target' => '_blank'));
                echo "<li>$questionnamelink:";
                flush(); // Force output to prevent timeouts and show progress.

                // Now run the test.
                list($outcome, $message) = $this->load_and_test_question($questionid);

                // Report the result, and record failures for the summary.
                echo " $message</li>";
                flush(); // Force output to prevent timeouts and show progress.
                if ($outcome === self::PASS) {
                    $numpasses += 1;
                } else if ($outcome === self::MISSINGANSWER) {
                    $missinganswers[] = $questionnamelink;
                } else {
                    $failingtests[] = "$questionnamelink: $message";
                }
            }
            echo "</ul>\n";
        }

        return array($numpasses, $failingtests, $missinganswers);
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
            if (empty(trim($question->answer))) {
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
        return array($status, $message);
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
        $answer = $question->answer;
        $response = array('answer' => $answer);
        // Check if it's a multilanguage question; if so need to determine
        // what language (either specified by answer_language template param, or
        // the AceLang default or the first).
        $params = empty($question->templateparams) ? array() : json_decode($question->templateparams, true);
        if (!empty($params['answer_language'])) {
            $response['language'] = $params['answer_language'];
        } else if (!empty($question->acelang) && strpos($question->acelang, ',') !== false) {
            list($languages, $defaultlang) = qtype_coderunner_util::extract_languages($question->acelang);
            if ($defaultlang === '') {
                $defaultlang = $languages[0];
            }
            $response['language'] = $defaultlang;
        }
        try {
            list($fraction, $state) = $question->grade_response($response, false);
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

        echo html_writer::tag('p', html_writer::link(new moodle_url('/question/type/coderunner/bulktestindex.php'),
                get_string('back')));
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

        foreach ($prototypes as $prototypename => $prototype) {
            if (isset($prototype->usages)) {
                echo $OUTPUT->heading($prototypename, 4);
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
                $links = array();
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
        $qbankparams = array('qperpage' => 1000); // Can't easily get the true value.
        $qbankparams['category'] = $question->category . ',' . $question->contextid;
        $qbankparams['lastchanged'] = $question->questionid;
        $qbankparams['courseid'] = $courseid;
        $qbankparams['showhidden'] = 1;
        $questionbanklink = new moodle_url('/question/edit.php', $qbankparams);
        return html_writer::link($questionbanklink, $question->name, array('target' => '_blank'));
    }
}
