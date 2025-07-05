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
 * @copyright 2016, 2018, 2024 Richard Lobb and Paul McKeown, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use moodle_url;
use html_writer;
use question_bank;
use core_php_time_limit;
use question_state;
use qtype_coderunner_util;
use context_course;
use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_edit_contexts;



class bulk_tester {
    /** @var context Context to run bulktester for. */
    public $context;

    /** @var int The optional category ID to run bulktester for. */
    public $categoryid;

    /** @var int The optional seed to set before running tests (default 0 means seed not set). */
    public $randomseed;

    /** @var bool Whether questions with random in name are the only ones to be repeated */
    public $repeatrandomonly;

    /** @var int How many runs to do for each question. */
    public $nruns;

    /** @var int Whether or not to clear the grading cache for this context first Default: 0 . */
    public $clearcachefirst;

    /** @var int Whether or not to use the grading cache (assuming it's turned on in Coderunner settings) Default: 1 . */
    public $usecache;

    /** @var int The number of questions that passed tests. */
    public $numpasses;

    /** @var int The number of questions that failed tests. */
    public $numfails;

    /** @var contextname The name of the base context for the instance */
    public $contextname;

    /** @var list IDs for questions that failed in last run. */
    public $failedquestionids;

    /** @var list A list of strings containing lines to be output for each failed question. */
    public $failedtestdetails;

    /** @var list A list of strings containing lines to be output for each question without an answer. */
    public $missinganswerdetails;

    /** @var url Base URL for question testers php module. */
    public $questiontestsurl;

    const PASS = 0;
    const MISSINGANSWER = 1;
    const FAIL = 2;
    const EXCEPTION = 3;

    /**
     * @param context $context the context to run the tests for.
     * @param int $categoryid test only questions in this category. Default to all.
     * @param int $randomseed used to set random seed before runs for each question. Default = -1   ---  meaning seed is not set.
     *             Use this to have more chance of the series of questions being generated for testing is the same for a new run
     *             of the tests. This works well with grader caching as you won't keep getting new random variations.
     *             Also allows you to mix up the space that is being tested.
     * @param int $repeatrandomonly when true(or 1), only repeats tests for questions with random in the name.
     *              Default = true (or really 1).
     * @param int $nruns the number times to test each question. Default to 1.
     * @param int $clearcachefirst If 1 then clears the grading cache (ignoring ttl) for the given context before running the tests. Default is 0.
     * @param int $usecache Set to 0 to not use the grading cache when testing. Helpful for multiple runs of fixed, non-randomised, questions. Default is 1.
     */
    public function __construct(
        $context = null,
        $categoryid = null,
        $randomseed = -1,
        $repeatrandomonly = 1,
        $nruns = 1,
        $clearcachefirst = 0,
        $usecache = 0
    ) {
        if ($context === null) {
            $site = get_site(); // Get front page course.
            $context = \context_course::instance($site->id);
        }
        $this->context = $context;
        $this->categoryid = $categoryid;
        $this->randomseed = $randomseed;
        $this->repeatrandomonly = $repeatrandomonly;
        $this->nruns = $nruns;
        $this->clearcachefirst = $clearcachefirst;
        $this->usecache = (bool) $usecache;
        $this->numpasses = 0;
        $this->numfails = 0;
        $this->failedquestionids = [];
        $this->failedtestdetails = [];
        $this->missinganswerdetails = [];
        $this->contextname = $this->context->get_context_name(true, true);
    }



    /**
     * Get all the courses and their contexts from the database
     *
     * @return array of course objects with id, contextid and name (short),
     * indexed by id
     */
    public static function get_all_courses() {
        global $DB;

        return $DB->get_records_sql("
            SELECT crs.id, ctx.id as contextid, crs.shortname as name
              FROM {course} crs
              JOIN {context} ctx ON ctx.instanceid = crs.id
            WHERE ctx.contextlevel = 50
            ORDER BY ctx.id");
    }



    /**
     * Get all the contexts that contain at least one CodeRunner question, with a
     * count of the number of those questions. Only the latest version of each
     * question is counted and prototypes are ignored.
     *
     * @return array context id => number of CodeRunner questions.
     */
    public static function get_num_coderunner_questions_by_context() {
        global $DB;

        return $DB->get_records_sql_menu("
            SELECT ctx.id, COUNT(q.id) AS numcoderunnerquestions
            FROM {context} ctx
            JOIN {question_categories} qc ON qc.contextid = ctx.id
            JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON qv.questionid = q.id
            JOIN {question_coderunner_options} opts ON opts.questionid = q.id
            WHERE q.qtype = 'coderunner'
            AND opts.prototypetype = 0
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
     * Returns an array mapping editable context ids to an array containing:
     *   - the context name, including prefix
     *   - the count of the number of questions in the context
     *   - the contextid, for legacy useage.
     *
     * By available we mean that the questions are in contexts the user can edit questions in.
     * Only the latest version of each question is counted and prototypes are ignored.
     *
     * @return array context id => [context_name, number of CodeRunner questions, contextid].
     */
    public static function get_num_available_coderunner_questions_by_context() {
        // Find in which contexts the user can edit questions.
        $questionsbycontext = self::get_num_coderunner_questions_by_context();
        $availablequestionsbycontext = [];
        foreach ($questionsbycontext as $contextid => $numcoderunnerquestions) {
            $context = \context::instance_by_id($contextid);
            if (has_capability('moodle/question:editall', $context)) {
                $contextname = $context->get_context_name(true, true); // With context prefix, short version.
                $name = $contextname;
                $availablequestionsbycontext[$contextid] = [
                    'name' => $name,
                    'numquestions' => $numcoderunnerquestions,
                    'contextid' => $contextid, // For legacy usage.
                ];
            }
        }
        ksort($availablequestionsbycontext);
        return $availablequestionsbycontext;
    }





    /**
     * Find all coderunner questions in a given category, returning only
     * the latest version of each question and ignoring prototypes.
     * @param type $categoryid the id of a question category of interest
     * @return all coderunner question ids in any state and any version in the given
     * category. Each row in the returned list of rows has an id, name and version number.
     */
    public static function coderunner_questions_in_category($categoryid, $questionids = []) {
        global $DB;
        $query = "
            SELECT q.id, q.name, qv.version
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qv.questionbankentryid = qbe.id
            JOIN {question_coderunner_options} opts ON opts.questionid = q.id
            WHERE q.qtype = 'coderunner'
            AND opts.prototypetype = 0
            AND (qv.version = (SELECT MAX(v.version)
                                FROM {question_versions} v
                                JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                WHERE be.id = qbe.id)
                              )
            AND qbe.questioncategoryid=:categoryid";
        $params = ['categoryid' => $categoryid];
        if (count($questionids) > 0) {
            // Only include listed question IDs.
            [$idincondition, $idparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $params += $idparams;
            $query = $query . " AND q.id $idincondition";
        }
        $rec = $DB->get_records_sql(
            $query,
            $params
        );
        return $rec;
    }



    /**
     * Find all coderunner questions in a given category, returning only
     * the latest version of each question.
     * @param questionids A list of question IDs.
     * @return questions A list of question records with id, name and version.
     */
    public static function get_coderunner_questions_from_ids($questionids) {
        global $DB;
        [$idincondition, $idparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $recs = $DB->get_records_sql(
            "
            SELECT q.id, q.name, qv.version
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            WHERE q.id $idincondition",
            $idparams
        );
        return $recs;
    }



    /**
     * Get a list of all the categories within the supplied contextid that
     * contain CodeRunner questions in any state and any version.
     * @return array an associative array mapping from category id to an object
     * with name and count fields for all question categories in the given context
     * that contain one or more CodeRunner questions.
     * The 'count' field is the number of coderunner questions in the given
     * category.
     */
    public static function get_categories_for_context($contextid) {
        global $DB;

        return $DB->get_records_sql(
            "SELECT qc.id, qc.parent, qc.name AS name, COUNT(DISTINCT q.id) AS count
            FROM  {question_categories} qc
            JOIN {question_bank_entries} qbe  ON qc.id = qbe.questioncategoryid
            JOIN {question_versions} qv ON qbe.id = qv.questionbankentryid
            JOIN {question} q  ON q.id = qv.questionid
            JOIN {question_coderunner_options} opts ON opts.questionid = q.id
            WHERE q.qtype = 'coderunner'
            AND opts.prototypetype = 0
            AND qc.contextid = :contextid
            AND qv.version = (
                SELECT MAX(v.version)
                FROM {question_versions} v
                WHERE v.questionbankentryid = qbe.id
            )
            GROUP BY qc.id, qc.parent, qc.name
            ORDER BY qc.name;",
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
    public static function get_all_coderunner_questions_in_context($contextid, $includeprototypes = 0) {
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
     * Gets all the shared and private question banks for the given course id.
     * @param int $courseid
     * @return array of question bank instances
     */
    public static function get_all_qbanks_for_course(int $courseid) {
        $allcaps = array_merge(question_edit_contexts::$caps['editq'], question_edit_contexts::$caps['categories']);
        // Shared banks will be activites of type qbank.
        $sharedbanks = question_bank_helper::get_activity_instances_with_shareable_questions([$courseid], [], $allcaps);
        // Private banks are actually just other modules that can contain questions, eg, quizzes.
        $privatebanks = question_bank_helper::get_activity_instances_with_private_questions([$courseid], [], $allcaps);
        $allbanks = array_merge($sharedbanks, $privatebanks);
        return $allbanks;
    }



    /**
     * Gets all the shared and private question banks for the given course id.
     * @param context $context
     * @return array of question bank instances
     */
    public static function run_all_tests_for_qbank($context) {
        $allcaps = array_merge(question_edit_contexts::$caps['editq'], question_edit_contexts::$caps['categories']);
        // Shared banks will be activites of type qbank.
        $sharedbanks = question_bank_helper::get_activity_instances_with_shareable_questions([$course->id], [], $allcaps);
        // Private banks are actually just other modules that can contain questions, eg, quizzes.
        $privatebanks = question_bank_helper::get_activity_instances_with_private_questions([$course->id], [], $allcaps);
        $allbanks = array_merge($sharedbanks, $privatebanks);
        return $allbanks;
    }



    /** Generates the base URL used for linking to a preview of the test result for each question.
     *
     * @return moodle_url base URL used for seeing the results of a test run.
     */
    private function get_question_base_test_url() {
        if ($this->context->contextlevel == CONTEXT_COURSE) {
            $qparams['courseid'] = $this->context->instanceid;
        } else if ($this->context->contextlevel == CONTEXT_MODULE) {
            $qparams['cmid'] = $this->context->instanceid;
        } else {
            $qparams['courseid'] = SITEID;
        }
        $questiontestsurl = new moodle_url('/question/type/coderunner/questiontestrun.php');
        $questiontestsurl->params($qparams);
        return $questiontestsurl;
    }



    /** Tests the question.
     *
     * Keeps track of numpasses, failedtestdetails and missinganswerdetails using the following:
     *     $this->numpasses,
     *     $this->failedtestdetails,
     *     $this->missinganswerdetails
     *
     * @param question $question The question to test
     * @param int
     *
     */
    private function process_question($question, $categoryid, $categoryname, $categorycount) {
        global $OUTPUT;
        global $PAGE;
        // Output question name before testing, so if something goes wrong, it is clear which question was the problem.
        $previewurl = new moodle_url(
            $this->get_question_base_test_url(),
            ['questionid' => $question->id]
        );
        $enhancedname = "{$question->name} (V{$question->version})";
        $questionnamelink = html_writer::link($previewurl, $enhancedname, ['target' => '_blank']);
        echo "<li><small>$questionnamelink: </small>";
        flush(); // Force output to prevent timeouts and show progress.
        $npassesforq = 0;
        $nfailsforq = 0;
        if ($this->repeatrandomonly && !preg_match('/random/', $question->name)) {
            $nrunsthistime = 1;
        } else {
            $nrunsthistime = $this->nruns;
        }
        if ($this->randomseed >= 0) {
            mt_srand($this->randomseed);
        }
        // Now run the test for the required number of times.
        $start = microtime(true);
        for ($i = 0; $i < $nrunsthistime; $i++) {
            // Only records last outcome and message.
            try {
                 [$outcome, $message] = $this->load_and_test_question($question->id);
            } catch (Exception $e) {
                $message = $e->getMessage();
                $outcome = self::FAIL;
                echo "<i style='color:red'>x</i>";
            }
            if ($outcome == self::MISSINGANSWER) {
                echo " $message ";
                break;  // No point trying again as there is no answer to check.
            } else {
                if ($outcome == self::PASS) {
                    $npassesforq += 1;
                    echo "<i style='color:green;'>.</i>";
                } else {
                    $nfailsforq += 1;
                    echo "<i style='color:red;'>.</i>";
                }
            }
            flush();
        }
        $end = microtime(true);
        $timetakenpertest = number_format(($end - $start) / $nrunsthistime, 4);
        // Report the result, and record failures for the summary.
        if ($outcome != self::MISSINGANSWER) {
            echo "&nbsp;&nbsp;&nbsp;<i style='color:green;'>" .
                  get_string('passes', 'qtype_coderunner') .
                  "=" .
                  $npassesforq .
                  "</i>";
            if ($nfailsforq > 0) {
                echo ", <b style='color:red;'>" . get_string('fails', 'qtype_coderunner') . '=' . $nfailsforq . "</b>";
            }
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
            echo "<span style='font-size:smaller'>$timetakenpertest</span>";
            echo "<span style='color:gray; font-size:smaller'> sec/test</span>";
        }
        echo "</li>";

        gc_collect_cycles(); // Because PHP's default memory management is rubbish.
        flush(); // Force output to prevent timeouts and show progress.

        $qparams['category'] = $categoryid . ',' . $this->context->id;
        $qparams['lastchanged'] = $question->id;
        $qparams['qperpage'] = 1000;
        $questionbankurl = new moodle_url('/question/edit.php', $qparams);
        $questionbanklink = html_writer::link($questionbankurl, $categoryname, ['target' => '_blank']);

        if ($outcome === self::MISSINGANSWER) {
            $this->missinganswerdetails[] = "$this->contextname / $questionbanklink / $questionnamelink";
        } else if ($nfailsforq == 0) {
                $this->numpasses += 1;
        } else {  // Had at least one fail.
            $this->failedquestionids[] = $question->id;
            $failmessage = " <b style='color:red'>" . get_string('fail', 'qtype_coderunner') . '=' . $nfailsforq . "</b>";
            $this->failedtestdetails[] = "$this->contextname / $questionbanklink / $questionnamelink: $failmessage";
        }
    }



    /**
     * Does what it says it does.
     *
     * @param int $categoryid The id of the category to test the questions from.
     * @param string $categoryname The name of the category.
     * @param int $categorycount The number of questions in the category.
     * @param
     */
    private function test_questions_in_category(
        int $categoryid,
        string $categoryname,
        array $questionidstoinclude = []
    ) {
        global $OUTPUT;
        global $PAGE;
        $questions = $this->coderunner_questions_in_category($categoryid, $questionidstoinclude);
        if ($questions) {
            $categorycount = count($questions);
            echo $OUTPUT->heading("{$categoryname} ($categorycount)", 5);
            echo "<ul>\n";
            foreach ($questions as $question) {
                if (count($questionidstoinclude) == 0 || in_array($question->id, $questionidstoinclude)) {
                    $this->process_question($question, $categoryid, $categoryname, $categorycount);
                }
            }
            echo "</ul>\n";
        }
    }



    /**
     * Run the sample answer for all questions that belong to
     * a given qbank and have a sample answer.
     *
     * Outputs as it goes.
     * Keeps track of numpasses, failedtestdetails and missinganswerdetails using the following:
     *     $this->numpasses,
     *     $this->failedtestdetails,
     *     $this->missinganswerdetails
     *
     * @return array with three elements:
     *              int a count of how many tests passed
     *              array of messages relating to the questions with failures
     *              array of messages relating to the questions without sample answers
     */
    private function run_tests_for_simple_context($context, $questionidstoinclude = []) {
        global $OUTPUT;
        $contextname = $context->get_context_name(true, true);
        echo $OUTPUT->heading("{$contextname}", 4);

        // Clear grading cache if requested. usettl is set to false here so that all keys for context are purged.
        if ($this->clearcachefirst) {
            $purger = new cache_purger(usettl: false);
            $purger->purge_cache_for_context($context->id, quiet : true);
        }
        $categories = $this->get_categories_for_context($context->id);
        foreach ($categories as $categoryid => $nameandcount) {
            $categoryname = $nameandcount->name;
            $categorycount = $nameandcount->count;
            // If a category id has been specified then only process that category.
            if ($this->categoryid !== null && $categoryid != $this->categoryid) {
                continue;
            }
            $this->test_questions_in_category($categoryid, $categoryname, $questionidstoinclude);
        }
    }



    /**
     * @param $context must be a context object and be for a course.
     * @param array $questionidstoinclude If included, only the given questions listed will be tested
     *               this is useful for retesting failed questions.
     */
    private function run_tests_for_all_qbanks_in_course(context_course $context, $questionidstoinclude = []) {
        global $OUTPUT;
        $coursename = $context->get_context_name();
        echo $OUTPUT->heading("{$coursename}", 3);
        $qbanks = self::get_all_qbanks_for_course($context->instanceid);
        if (count($qbanks) > 0) {
            foreach ($qbanks as $qbank) {
                $contextid = $qbank->contextid;
                $qbankcontext = \context::instance_by_id($contextid);
                $qbankname = $qbankcontext->get_context_name(true, true);
                $this->run_tests_for_simple_context($qbankcontext, $questionidstoinclude);
            }
        }
    }



    /**
     * Run the sample answer for all questions belonging to
     * a given context that have a sample answer. Optionally restrict to a
     * specified question category.
     *
     * The context is initially taken from the bulk_tester instance's context attribute.
     *
     * Outputs as it goes.
     * Keeps track of numpasses, failedtestdetails and missinganswerdetails using the following:
     *     $this->numpasses,
     *     $this->failedtestdetails,
     *     $this->missinganswerdetails
     */
    public function run_tests($questionidstoinclude = []) {
        global $OUTPUT;
        global $PAGE;
        $oldskool = !(qtype_coderunner_util::using_mod_qbank()); // no qbanks in Moodle < 5.0.
        if ($this->context->contextlevel == CONTEXT_COURSE) {
            if ($oldskool) {
                $this->run_tests_for_simple_context($this->context, questionidstoinclude:$questionidstoinclude);
            } else {
                // Run tests for all qbanks in course.
                $this->run_tests_for_all_qbanks_in_course($this->context, questionidstoinclude:$questionidstoinclude);
            }
        } else {
            $this->run_tests_for_simple_context($this->context, questionidstoinclude:$questionidstoinclude);
        }
        return [$this->numpasses, $this->failedtestdetails, $this->missinganswerdetails];
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
        } catch (exception $e) {
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
     * @param qtype_coderunner_question $question the questipon to test.
     * @return bool true if the sample answer passed, else false.
     */
    private function test_question($question) {
        core_php_time_limit::raise(60); // Prevent PHP timeouts.
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
            // Precheck: false, isvalidation: false for the run.
            [$fraction, $state] = $question->grade_response($response, false, false, $this->usecache);
            $ok = $state == question_state::$gradedright;
        } catch (exception $e) {
            $ok = false; // If user clicks link to see why, they'll get the same exception.
        }
        return $ok;
    }

    /**
     * Print an overall summary, with a link back to the bulk test index.
     */
    public function print_overall_result() {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('bulktestoverallresults', 'qtype_coderunner'), 5);
        $spacer = '&nbsp;&nbsp;|&nbsp;&nbsp;';
        $passstr = $this->numpasses . ' ' . get_string('passes', 'qtype_coderunner') . $spacer;
        $failstr = count($this->failedtestdetails) . ' ' . get_string('fails', 'qtype_coderunner') . $spacer;
        $missingstr = count($this->missinganswerdetails) . ' ' . get_string('missinganswers', 'qtype_coderunner');
        echo html_writer::tag('p', $passstr . $failstr . $missingstr);

        if (count($this->missinganswerdetails) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_noanswer', 'qtype_coderunner'), 5);
            echo html_writer::start_tag('ul');
            foreach ($this->missinganswerdetails as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }
        if (count($this->failedtestdetails) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_failures', 'qtype_coderunner'), 5);
            echo html_writer::start_tag('ul');
            foreach ($this->failedtestdetails as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');

            // Give a link for retesting if anything failed.
            $buttonstyle = 'font-size: large; border:2px solid rgb(230, 211, 195);';
            $buttonstyle .= 'background-color:rgb(240, 240, 233);padding: 2px 2px 0px 2px;';
            $retestallurl = new moodle_url(
                '/question/type/coderunner/bulktest.php',
                ['contextid' => $this->context->id,
                'randomseed' => $this->randomseed,
                'repeatrandomonly' => $this->repeatrandomonly,
                'nruns' => $this->nruns,
                'clearcachefirst' => $this->clearcachefirst,
                'usecache' => $this->usecache,
                'questionids' => implode(',', $this->failedquestionids)]
            );
            $retestalllink = html_writer::link(
                $retestallurl,
                get_string('retestfailedquestions', 'qtype_coderunner'),
                ['title' => get_string('retestfailedquestions', 'qtype_coderunner'),
                'style' => $buttonstyle]
            );

            echo html_writer::tag('p', '&nbsp;&nbsp;-------> ' . $retestalllink);
        }
        $url = new moodle_url('/question/type/coderunner/bulktestindex.php');
        $link = html_writer::link($url, get_string('backtobulktestindex', 'qtype_coderunner'));
        echo html_writer::tag('p', $link);
    }



    /**
     * Print an overall summary of the failed tests.
     */
    public static function print_summary_after_bulktestall($numpasses, $allfailingtests, $allmissinganswers) {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('bulktestoverallresults', 'qtype_coderunner'), 5);
        $spacer = '&nbsp;&nbsp;|&nbsp;&nbsp;';
        $passstr = $numpasses . ' ' . get_string('passes', 'qtype_coderunner') . $spacer;
        $failstr = count($allfailingtests) . ' ' . get_string('fails', 'qtype_coderunner') . $spacer;
        $missingstr = count($allmissinganswers) . ' ' . get_string('missinganswers', 'qtype_coderunner');
        echo html_writer::tag('p', $passstr . $failstr . $missingstr);

        if (count($allmissinganswers) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_noanswer', 'qtype_coderunner'), 5);
            echo html_writer::start_tag('ul');
            foreach ($allmissinganswers as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }
        if (count($allfailingtests) > 0) {
            echo $OUTPUT->heading(get_string('coderunner_install_testsuite_failures', 'qtype_coderunner'), 5);
            echo html_writer::start_tag('ul');
            foreach ($allfailingtests as $message) {
                echo html_writer::tag('li', $message);
            }
            echo html_writer::end_tag('ul');
        }
    }


    /**
     *  Display the results of scanning all the CodeRunner questions to
     *  find all prototype usages in a particular course
     * @param $courseid The id of the course.
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
                echo $OUTPUT->heading($prototypename, 5);
                echo $OUTPUT->heading($name, 6);
                echo html_writer::start_tag('ul');
                foreach ($prototype->usages as $question) {
                    echo html_writer::tag('li', self::make_question_link($courseid, $question));
                }
                echo html_writer::end_tag('ul');
            }
        }

        if ($missingprototypes) {
            echo $OUTPUT->heading(get_string('missingprototypes', 'qtype_coderunner'), 5);
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
        $qbankparams = ['qperpage' => 1000]; // Can't easily get the true vrequire_once($CFG->libdir . '/questionlib.php');alue.
        $qbankparams['category'] = $question->category . ',' . $question->contextid;
        $qbankparams['lastchanged'] = $question->questionid;
        $qbankparams['courseid'] = $courseid;
        $qbankparams['showhidden'] = 1;
        $questionbanklink = new moodle_url('/question/edit.php', $qbankparams);
        return html_writer::link($questionbanklink, $question->name, ['target' => '_blank']);
    }
}
