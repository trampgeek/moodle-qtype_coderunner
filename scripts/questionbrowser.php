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
 * CodeRunner Question Browser.
 *
 * With no contextid parameter, displays an index of available contexts.
 * With a contextid parameter, displays the full question browser for that context.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use html_writer;
use moodle_url;
use qtype_coderunner_util;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/../classes/bulk_tester.php');

$contextid = optional_param('contextid', 0, PARAM_INT);

require_login();

if ($contextid === 0) {
    render_index();
} else {
    render_browser($contextid);
}

// Index view: list all available contexts.

/**
 * Render the context-selection index page.
 */
function render_index() {
    global $PAGE, $OUTPUT;

    $oldskool = !(qtype_coderunner_util::using_mod_qbank());

    $context = \context_system::instance();
    $PAGE->set_url('/question/type/coderunner/scripts/questionbrowser.php');
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('qb_index_title', 'qtype_coderunner'));
    $PAGE->set_heading(get_string('qb_index_title', 'qtype_coderunner'));
    $PAGE->requires->css(new \moodle_url('/question/type/coderunner/styles/questionbrowser.css'));

    echo $OUTPUT->header();

    echo html_writer::tag('p', get_string('qb_index_intro', 'qtype_coderunner'));

    $availablequestionsbycontext = bulk_tester::get_num_available_coderunner_questions_by_context();

    if (count($availablequestionsbycontext) == 0) {
        echo html_writer::tag('p', get_string('qb_no_permission', 'qtype_coderunner'));
    } else {
        echo html_writer::tag('p', get_string('qb_index_instructions', 'qtype_coderunner'));

        if ($oldskool) {
            $availablecount = count($availablequestionsbycontext);
            echo html_writer::tag('h3', get_string('qb_available_contexts', 'qtype_coderunner', $availablecount));
            qtype_coderunner_util::display_course_contexts(
                $availablequestionsbycontext,
                'qtype_coderunner\display_context_entry'
            );
        } else {
            echo html_writer::tag('p', get_string('qb_moodle5_notice', 'qtype_coderunner'));
            qtype_coderunner_util::display_course_grouped_contexts(
                $availablequestionsbycontext,
                'qtype_coderunner\display_course_header',
                'qtype_coderunner\display_context_entry'
            );
        }
    }

    echo $OUTPUT->footer();
}

/**
 * Display a course heading in the grouped context list (Moodle 5+).
 *
 * @param int $coursecontextid
 * @param string $coursename
 */
function display_course_header($coursecontextid, $coursename) {
    echo html_writer::tag('h5', $coursecontextid . ' - ' . $coursename);
}

/**
 * Display one context entry with a "Browse questions" link.
 *
 * @param int $contextid
 * @param string $name
 * @param int $numcoderunnerquestions
 */
function display_context_entry($contextid, $name, $numcoderunnerquestions) {
    $browseallstr = get_string('browsequestions', 'qtype_coderunner', $name);
    if (!$browseallstr) {
        $browseallstr = 'Browse questions';
    }

    $browseurl = new \moodle_url('/question/type/coderunner/scripts/questionbrowser.php', ['contextid' => $contextid]);
    $browselink = html_writer::link($browseurl, $browseallstr, ['class' => 'qbrowser-browse-link']);

    $litext = $contextid . ' - ' . $name . ' (' . $numcoderunnerquestions . ') ' . $browselink;
    $class = strpos($name, ': Quiz: ') === false
        ? 'questionbrowser coderunner context normal'
        : 'questionbrowser coderunner context quiz';

    echo html_writer::tag('li', $litext, ['class' => $class]);
}

// Browser view: full question browser for a specific context.

/**
 * Render the question browser for the given context.
 *
 * @param int $contextid
 */
function render_browser($contextid) {
    global $PAGE, $OUTPUT, $DB, $CFG;

    $context = \context::instance_by_id($contextid);
    require_capability('moodle/question:editall', $context);

    // Get course name for display.
    if ($context->contextlevel == CONTEXT_COURSE) {
        $course = $DB->get_record('course', ['id' => $context->instanceid], 'fullname');
        $coursename = $course ? $course->fullname : get_string('qb_unknown_course', 'qtype_coderunner', $contextid);
    } else if ($context->contextlevel == CONTEXT_MODULE) {
        $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], 'fullname');
        $coursename = $course ? $course->fullname : 'Unknown Course (' . $contextid . ')';
    } else {
        $coursename = $context->get_context_name() . ' (' . $contextid . ')';
    }

    // Generate question data.
    $generator = new questions_json_generator($context);
    $questions = $generator->generate_questions_data();
    $moodlebaseurl = $CFG->wwwroot;

    // Page setup.
    $PAGE->set_url('/question/type/coderunner/scripts/questionbrowser.php', ['contextid' => $contextid]);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('qb_browser_title', 'qtype_coderunner', $coursename));
    $PAGE->set_heading('Question Browser - ' . $coursename);
    $PAGE->set_pagelayout('incourse');

    if ($context->contextlevel == CONTEXT_COURSE) {
        $PAGE->set_course($DB->get_record('course', ['id' => $context->instanceid]));
    } else if ($context->contextlevel == CONTEXT_MODULE) {
        $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
        $PAGE->set_course($DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST));
        $PAGE->set_cm($cm);
    }

    $PAGE->navbar->add(get_string('qb_browser_heading', 'qtype_coderunner'));
    $PAGE->requires->css(new \moodle_url('/question/type/coderunner/styles/questionbrowser.css'));
    $PAGE->requires->js_call_amd('qtype_coderunner/questionbrowser', 'init');

    $jstrings = [
        'alert_bad_regex'      => get_string('qb_alert_bad_regex',      'qtype_coderunner'),
        'any'                  => get_string('qb_any',                  'qtype_coderunner'),
        'alert_max_rules'      => get_string('qb_alert_max_rules',      'qtype_coderunner'),
        'alert_no_bank_params' => get_string('qb_alert_no_bank_params', 'qtype_coderunner'),
        'alert_no_id'          => get_string('qb_alert_no_id',          'qtype_coderunner'),
        'btn_answer'           => get_string('qb_btn_answer',           'qtype_coderunner'),
        'btn_bank'             => get_string('qb_btn_bank',             'qtype_coderunner'),
        'btn_close'            => get_string('qb_btn_close',            'qtype_coderunner'),
        'btn_json'             => get_string('qb_btn_json',             'qtype_coderunner'),
        'btn_preview'          => get_string('qb_btn_preview',          'qtype_coderunner'),
        'btn_question'         => get_string('qb_btn_question',         'qtype_coderunner'),
        'col_actions'          => get_string('qb_col_actions',          'qtype_coderunner'),
        'col_category'         => get_string('qb_col_category',         'qtype_coderunner'),
        'col_name'             => get_string('qb_col_name',             'qtype_coderunner'),
        'col_tags'             => get_string('qb_col_tags',             'qtype_coderunner'),
        'col_usedin'           => get_string('qb_col_usedin',           'qtype_coderunner'),
        'no_answer'            => get_string('qb_no_answer',            'qtype_coderunner'),
        'no_matches'           => get_string('qb_no_matches',           'qtype_coderunner'),
        'no_questiontext'      => get_string('qb_no_questiontext',      'qtype_coderunner'),
        'shown'                => get_string('qb_shown',                'qtype_coderunner'),
        'tip_preview_question' => get_string('qb_tip_preview_question', 'qtype_coderunner'),
        'tip_regex_input'      => get_string('qb_tip_regex_input',      'qtype_coderunner'),
        'tip_remove_filter'    => get_string('qb_tip_remove_filter',    'qtype_coderunner'),
        'tip_remove_rule'      => get_string('qb_tip_remove_rule',      'qtype_coderunner'),
        'tip_view_answer'      => get_string('qb_tip_view_answer',      'qtype_coderunner'),
        'tip_view_in_bank'     => get_string('qb_tip_view_in_bank',     'qtype_coderunner'),
        'tip_view_json'        => get_string('qb_tip_view_json',        'qtype_coderunner'),
        'tip_view_question'    => get_string('qb_tip_view_question',    'qtype_coderunner'),
        'total'                => get_string('qb_total',                'qtype_coderunner'),
    ];
    $initjson = htmlspecialchars(
        json_encode(
            ['questions' => $questions, 'moodlebaseurl' => $moodlebaseurl, 'strings' => $jstrings],
            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE
        ),
        ENT_QUOTES,
        'UTF-8'
    );

    echo $OUTPUT->header();

    ?>
<div class="container-fluid qbrowser-main" id="qbrowserMain" data-init="<?php echo $initjson; ?>">
    <!-- LEFT: FILTERS -->
    <div class="card" id="filterPanel">
        <div class="card-header">
            <h5 class="mb-0"><?php echo get_string('qb_filters', 'qtype_coderunner'); ?></h5>
        </div>
        <div class="card-body qbrowser-filters">
            <div class="mb-3">
                <h6><?php echo get_string('qb_data', 'qtype_coderunner'); ?></h6>
                <div id="loadStatus" class="text-muted small">
                    <?php echo get_string('qb_loaded_questions', 'qtype_coderunner', count($questions)); ?>
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <h6><?php echo get_string('qb_text_filter', 'qtype_coderunner'); ?></h6>
                <div class="row">
                    <div class="col-md-6">
                        <label for="kw" class="form-label small"><?php echo get_string('qb_search', 'qtype_coderunner'); ?></label>
                        <input type="text" id="kw" class="form-control form-control-sm"
                            placeholder="<?php echo get_string('qb_search_placeholder', 'qtype_coderunner'); ?>" />
                    </div>
                    <div class="col-md-6">
                        <label for="kwMode" class="form-label small"
                            ><?php echo get_string('qb_mode', 'qtype_coderunner'); ?></label>
                        <select id="kwMode" class="form-control form-control-sm">
                            <option><?php echo get_string('qb_include', 'qtype_coderunner'); ?></option>
                            <option><?php echo get_string('qb_exclude', 'qtype_coderunner'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <label for="kwField" class="form-label small"
                            ><?php echo get_string('qb_field', 'qtype_coderunner'); ?></label>
                        <select id="kwField" class="form-control form-control-sm">
                            <option><?php echo get_string('qb_any', 'qtype_coderunner'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="kwType" class="form-label small"
                            ><?php echo get_string('qb_type', 'qtype_coderunner'); ?></label>
                        <select id="kwType" class="form-control form-control-sm">
                            <option><?php echo get_string('qb_text', 'qtype_coderunner'); ?></option>
                            <option><?php echo get_string('qb_regex', 'qtype_coderunner'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="mt-1">
                    <small class="text-muted"><?php echo get_string('qb_regex_hint', 'qtype_coderunner'); ?></small>
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <h6><?php echo get_string('qb_lines_of_code', 'qtype_coderunner'); ?></h6>
                <div id="numericFilters"></div>
            </div>

            <hr>

            <div class="mb-3">
                <h6><?php echo get_string('qb_coderunner_type', 'qtype_coderunner'); ?></h6>
                <div id="categoricalFilters"></div>
            </div>

            <!-- Advanced Filters Section -->
            <div class="advanced-section">
                <div class="advanced-toggle" id="advancedToggle">
                    <span class="advanced-toggle-icon">&#9658;</span>
                    <h6 class="mb-0"><?php echo get_string('qb_advanced_filters', 'qtype_coderunner'); ?></h6>
                </div>
                <div class="advanced-content" id="advancedContent">
                    <div class="mb-2">
                        <small class="text-muted"><?php echo get_string('qb_advanced_filters_hint', 'qtype_coderunner'); ?></small>
                    </div>
                    <div id="filterRules"></div>
                    <button class="btn btn-sm btn-outline-primary mt-2" id="addRule"
                        ><?php echo get_string('qb_add_rule', 'qtype_coderunner'); ?></button>
                    <div id="activeFiltersChips" class="filter-chips"></div>
                </div>
            </div>

            <hr>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-sm" id="apply"
                    ><?php echo get_string('qb_apply_filters', 'qtype_coderunner'); ?></button>
                <button class="btn btn-secondary btn-sm" id="clear"
                    ><?php echo get_string('qb_clear', 'qtype_coderunner'); ?></button>
            </div>
            <div class="mt-2">
                <small class="text-muted"><?php echo get_string('qb_filter_tip', 'qtype_coderunner'); ?></small>
            </div>
        </div>
    </div>

    <!-- Resizer for main columns -->
    <div class="qbrowser-main-resizer" id="mainResizer"></div>

    <!-- RIGHT: RESULTS -->
    <div class="card" id="resultsPanel">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0"><?php echo get_string('qb_results', 'qtype_coderunner'); ?></h5>
                    <span class="text-muted small ms-3" id="count"
                        ><?php echo get_string('qb_loading', 'qtype_coderunner'); ?></span>
                </div>
                <div class="d-flex">
                    <button id="exportJson" class="btn btn-success btn-sm me-2" disabled
                        ><?php echo get_string('qb_export_json', 'qtype_coderunner'); ?></button>
                    <button id="exportCsv" class="btn btn-success btn-sm" disabled
                        ><?php echo get_string('qb_export_csv', 'qtype_coderunner'); ?></button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="list" class="qbrowser-list">
                <div class="text-center p-4">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden"><?php echo get_string('qb_loading', 'qtype_coderunner'); ?></span>
                    </div>
                    <p class="mt-2 text-muted"><?php echo get_string('qb_loading_questions', 'qtype_coderunner'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

    <?php
    echo $OUTPUT->footer();
}

// Class definition.

/**
 * Class to generate questions data array from the database.
 */
class questions_json_generator {
    /** @var \context */
    private $context;
    /** @var array */
    private $usagemap;

    /**
     * Constructor.
     *
     * @param \context $context
     */
    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * Generate the complete questions data array.
     *
     * @return array
     */
    public function generate_questions_data() {
        $questions = bulk_tester::get_all_coderunner_questions_in_context($this->context->id, false);
        $this->usagemap = $this->fetch_quiz_usage_bulk($questions);
        $enhancedquestions = [];
        foreach ($questions as $question) {
            $enhancedquestions[] = $this->enhance_question_metadata($question);
        }
        return $enhancedquestions;
    }

    /**
     * Fetch quiz usage for all questions in a single query.
     *
     * @param array $questions
     * @return array
     */
    private function fetch_quiz_usage_bulk($questions) {
        global $DB;

        if (empty($questions)) {
            return [];
        }

        $questionids = array_column($questions, 'id');

        if (empty($questionids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);

        $sql = "SELECT CONCAT(qv.questionid, '-', qz.id) as uniqueid,
                       qv.questionid, qz.id as quizid, qz.name as quizname
                  FROM {question_versions} qv
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
                  JOIN {quiz_slots} slot ON slot.id = qr.itemid
                  JOIN {quiz} qz ON qz.id = slot.quizid
                 WHERE qv.questionid $insql
                   AND qr.component = 'mod_quiz'
                   AND qr.questionarea = 'slot'
              GROUP BY qv.questionid, qz.id, qz.name
              ORDER BY qv.questionid, qz.name";

        $usages = $DB->get_records_sql($sql, $params);

        $usagemap = [];
        foreach ($usages as $usage) {
            if (!isset($usagemap[$usage->questionid])) {
                $usagemap[$usage->questionid] = [];
            }
            $usagemap[$usage->questionid][] = $usage->quizname;
        }

        return $usagemap;
    }

    /**
     * Enhance a single question with metadata analysis.
     *
     * @param object $question
     * @return array
     */
    private function enhance_question_metadata($question) {
        $courseid = $this->get_course_id_from_context();
        $answer = $this->extract_answer($question->answer ?? '');
        $tags = $this->get_question_tags($question->id);
        $usedin = $this->usagemap[$question->id] ?? [];
        $qbankparams = \qtype_coderunner_util::make_question_bank_url_params($question);

        $enhanced = [
            'type' => 'coderunner',
            'id' => (string)$question->id,
            'name' => $question->name,
            'questiontext' => $question->questiontext,
            'answer' => $answer,
            'coderunnertype' => $question->coderunnertype,
            'category' => bulk_tester::get_category_path($question->category),
            'categoryid' => (string)$question->category,
            'contextid' => (string)$question->contextid,
            'version' => (int)$question->version,
            'courseid' => (string)$courseid,
            'tags' => $tags,
            'usedin' => $usedin,
            'qbankparams' => $qbankparams,
        ];

        $enhanced['lines_of_code'] = $this->count_lines_of_code($answer);

        return $enhanced;
    }

    /**
     * @param string $answer
     * @return string
     */
    private function extract_answer($answer) {
        if (empty(trim($answer))) {
            return '';
        }

        $decoded = json_decode($answer, true);

        if (
            json_last_error() === JSON_ERROR_NONE &&
            is_array($decoded) &&
            array_key_exists('answer_code', $decoded)
        ) {
            $answercode = $decoded['answer_code'];
            if (is_array($answercode)) {
                return implode("\n", $answercode);
            }
            return $answercode;
        }

        return $answer;
    }

    /**
     * @return string
     */
    private function get_course_id_from_context() {
        if ($this->context->contextlevel == CONTEXT_COURSE) {
            return $this->context->instanceid;
        } else if ($this->context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(false, $this->context->instanceid, 0, false, MUST_EXIST);
            return $cm->course;
        }
        return '0';
    }

    /**
     * @param string $code
     * @return int
     */
    private function count_lines_of_code($code) {
        if (empty(trim($code))) {
            return 0;
        }

        $lines = explode("\n", $code);
        $count = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && !preg_match('/^\s*#/', $trimmed)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param int $questionid
     * @return array
     */
    private function get_question_tags($questionid) {
        $tagobjects = \core_tag_tag::get_item_tags('core_question', 'question', $questionid);
        $tags = [];
        foreach ($tagobjects as $tag) {
            $tags[] = $tag->name;
        }
        return $tags;
    }
}
