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
 * Simplified Question Browser that generates data inline.
 * Enhanced with advanced filter builder.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use html_writer;

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/classes/bulk_tester.php');

// Get the parameter from the URL.
$contextid = required_param('contextid', PARAM_INT);

// Login and check permissions.
require_login();
$context = \context::instance_by_id($contextid);
require_capability('moodle/question:editall', $context);

// Get course name for display.
$coursename = '';
if ($context->contextlevel == CONTEXT_COURSE) {
    $course = $DB->get_record('course', ['id' => $context->instanceid], 'fullname');
    $coursename = $course ? $course->fullname : 'Unknown Course (' . $contextid . ')';
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], 'fullname');
    $coursename = $course ? $course->fullname : 'Unknown Course (' . $contextid . ')';
} else {
    $coursename = $context->get_context_name() . ' (' . $contextid . ')';
}

// Generate questions data directly.
$generator = new questions_json_generator($context);
$questions = $generator->generate_questions_data();
$questionsjson = json_encode($questions, JSON_UNESCAPED_UNICODE);

// Get the correct Moodle base URL for JavaScript.
global $CFG;
$moodlebaseurl = $CFG->wwwroot;

$urlparams = ['contextid' => $context->id];
$PAGE->set_url('/question/type/coderunner/questionbrowser.php', $urlparams);
$PAGE->set_context($context);
$PAGE->set_title("Question browser");

if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $PAGE->set_cm($cm, $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST));
}

/**
 * Class to generate questions.json from database.
 */
class questions_json_generator {
    private $context;
    private $usagemap;

    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * Generate the complete questions data array.
     */
    public function generate_questions_data() {
        $questions = bulk_tester::get_all_coderunner_questions_in_context($this->context->id, false);

        // Fetch quiz usage for all questions in one bulk query.
        $this->usagemap = $this->fetch_quiz_usage_bulk($questions);

        $enhancedquestions = [];

        foreach ($questions as $question) {
            $enhanced = $this->enhance_question_metadata($question);
            $enhancedquestions[] = $enhanced;
        }

        return $enhancedquestions;
    }

    /**
     * Fetch quiz usage for all questions in a single query.
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

        // Build the query to get quiz usage for all questions.
        // This combines question_references (direct usage) and question_attempts (random question usage).
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

        // Build lookup map: questionid => array of quiz names.
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
     */
    private function enhance_question_metadata($question) {
        $courseid = $this->get_course_id_from_context();
        $answer = $this->extract_answer($question->answer ?? '');
        $tags = $this->get_question_tags($question->id);
        $usedin = $this->usagemap[$question->id] ?? [];

        $enhanced = [
            'type' => 'coderunner',
            'id' => (string)$question->id,
            'name' => $question->name,
            'questiontext' => $question->questiontext,
            'answer' => $answer,
            'coderunnertype' => $question->coderunnertype,
            'category' => bulk_tester::get_category_path($question->category),
            'categoryid' => (string)$question->category,
            'version' => (int)$question->version,
            'courseid' => (string)$courseid,
            'tags' => $tags,
            'usedin' => $usedin,
        ];

        $enhanced['lines_of_code'] = $this->count_lines_of_code($answer);

        return $enhanced;
    }

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

    private function get_course_id_from_context() {
        if ($this->context->contextlevel == CONTEXT_COURSE) {
            return $this->context->instanceid;
        } else if ($this->context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(false, $this->context->instanceid, 0, false, MUST_EXIST);
            return $cm->course;
        }
        return '0';
    }

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

    private function get_question_tags($questionid) {
        $tagobjects = \core_tag_tag::get_item_tags('core_question', 'question', $questionid);
        $tags = [];
        foreach ($tagobjects as $tag) {
            $tags[] = $tag->name;
        }
        return $tags;
    }
}

// Set up page using Moodle's layout system.
$PAGE->set_title('Question Browser - ' . $coursename);
$PAGE->set_heading('Question Browser - ' . $coursename);
$PAGE->set_pagelayout('incourse');

if ($context->contextlevel == CONTEXT_COURSE) {
    $PAGE->set_course($DB->get_record('course', ['id' => $context->instanceid]));
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
}

$PAGE->navbar->add('Question Browser');

echo $OUTPUT->header();

?>

<style>
/* Reduce excessive white space */
#page-header, .page-header-headings {
    margin-top: 0;
    padding-top: 0.5rem;
}

/* Highlight active buttons when panels are open */
.qbrowser-btn-active {
    background-color: #0066cc !important;
    border-color: #0066cc !important;
    color: white !important;
}

/* Minimal custom styles to work with Moodle theme */
.qbrowser-filters .form-group {
    margin-bottom: 1rem;
}

.qbrowser-grid2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.qbrowser-list {
    max-height: 75vh;
    overflow-y: auto;
}

.qbrowser-list table {
    margin-bottom: 0;
}

.qbrowser-detail {
    margin: 0.5rem 0;
    padding: 1rem;
    border-left: 4px solid #dee2e6;
    background-color: var(--bs-light, #f8f9fa);
}

.qbrowser-detail.code-content {
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 0.875rem;
}

.qbrowser-detail.html-content {
    font-family: inherit;
}

.qbrowser-controls .btn {
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
}

/* Advanced filter styles */
.advanced-section {
    margin-top: 1rem;
    border-top: 2px solid #dee2e6;
    padding-top: 1rem;
}

.advanced-toggle {
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #0066cc;
    font-weight: 500;
}

.advanced-toggle:hover {
    color: #0052a3;
}

.advanced-toggle-icon {
    transition: transform 0.2s;
    display: inline-block;
}

.advanced-toggle-icon.expanded {
    transform: rotate(90deg);
}

.advanced-content {
    display: none;
    margin-top: 1rem;
}

.advanced-content.show {
    display: block;
}

.filter-rule {
    display: grid;
    grid-template-columns: 2fr 1.5fr 2fr auto;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.5rem;
    padding: 0.5rem;
    background-color: #f8f9fa;
    border-radius: 0.25rem;
}

.filter-rule select,
.filter-rule input {
    font-size: 0.875rem;
}

.filter-connector {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.25rem 0;
    padding-left: 0.5rem;
}

.filter-connector-select {
    width: auto;
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    font-weight: 600;
}

.filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    background-color: #e3f2fd;
    border: 1px solid #90caf9;
    border-radius: 1rem;
    font-size: 0.875rem;
    color: #1976d2;
}

.filter-chip-remove {
    cursor: pointer;
    font-weight: bold;
    color: #1976d2;
    border: none;
    background: none;
    padding: 0;
    font-size: 1rem;
    line-height: 1;
}

.filter-chip-remove:hover {
    color: #d32f2f;
}

@media (min-width: 992px) {
    .qbrowser-main {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 1.5rem;
        position: relative;
    }
    
    .qbrowser-main-resizer {
        position: absolute;
        left: calc(350px + 0.75rem); /* Center in the gap */
        top: 0;
        bottom: 0;
        width: 8px;
        margin-left: -4px; /* Center the handle */
        cursor: col-resize;
        z-index: 10;
        background-color: #dee2e6;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    
    .qbrowser-main-resizer:hover {
        background-color: #0066cc;
    }
    
    .qbrowser-main-resizer::before {
        content: '⋮';
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 16px;
        line-height: 1;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }
}

/* Resizable table columns */
.qbrowser-list table th {
    position: relative;
    overflow: visible;
}

.column-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 10px;
    cursor: col-resize;
    user-select: none;
    z-index: 10;
}

.column-resizer:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

.column-resizer::after {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 2px;
    height: 60%;
    background-color: rgba(255, 255, 255, 0.5);
}

.resizing {
    cursor: col-resize !important;
    user-select: none !important;
}

.resizing * {
    cursor: col-resize !important;
    user-select: none !important;
}
</style>

<div class="container-fluid qbrowser-main" id="qbrowserMain">
    <!-- LEFT: FILTERS -->
    <div class="card" id="filterPanel">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body qbrowser-filters">
            <div class="form-group">
                <h6>Data</h6>
                <div id="loadStatus" class="text-muted small">Loaded <?php echo count($questions); ?> questions</div>
            </div>

            <hr>

            <div class="form-group">
                <h6>Text filter</h6>
                <div class="row">
                    <div class="col-md-6">
                        <label for="kw" class="form-label small">Search</label>
                        <input type="text" id="kw" class="form-control form-control-sm" placeholder="substring or regex" />
                    </div>
                    <div class="col-md-6">
                        <label for="kwMode" class="form-label small">Mode</label>
                        <select id="kwMode" class="form-control form-control-sm">
                            <option>Include</option>
                            <option>Exclude</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <label for="kwField" class="form-label small">Field</label>
                        <select id="kwField" class="form-control form-control-sm">
                            <option>Any</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="kwType" class="form-label small">Type</label>
                        <select id="kwType" class="form-control form-control-sm">
                            <option>Text</option>
                            <option>Regex</option>
                        </select>
                    </div>
                </div>
                <div class="mt-1">
                    <small class="text-muted">Regex uses JavaScript syntax</small>
                </div>
            </div>

            <hr>

            <div class="form-group">
                <h6>Lines of code</h6>
                <div id="numericFilters"></div>
            </div>

            <hr>

            <div class="form-group">
                <h6>CodeRunner type</h6>
                <div id="categoricalFilters"></div>
            </div>

            <!-- Advanced Filters Section -->
            <div class="advanced-section">
                <div class="advanced-toggle" id="advancedToggle">
                    <span class="advanced-toggle-icon">▶</span>
                    <h6 class="mb-0">Advanced Filters</h6>
                </div>
                <div class="advanced-content" id="advancedContent">
                    <div class="mb-2">
                        <small class="text-muted">Build complex filter rules with AND/OR logic</small>
                    </div>
                    <div id="filterRules"></div>
                    <button class="btn btn-sm btn-outline-primary mt-2" id="addRule">+ Add Rule</button>
                    <div id="activeFiltersChips" class="filter-chips"></div>
                </div>
            </div>

            <hr>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-sm" id="apply">Apply Filters</button>
                <button class="btn btn-secondary btn-sm" id="clear">Clear</button>
            </div>
            <div class="mt-2">
                <small class="text-muted">Tip: All filters combine with AND logic. Advanced filters provide OR options.</small>
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
                    <h5 class="mb-0">Results</h5>
                    <span class="text-muted small ml-3" id="count">No data</span>
                </div>
                <div class="d-flex">
                    <button id="exportJson" class="btn btn-success btn-sm mr-2" disabled>Export JSON</button>
                    <button id="exportCsv" class="btn btn-success btn-sm" disabled>Export CSV</button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="list" class="qbrowser-list">
                <!-- Content will be built dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
(() => {
  const rawData = <?php echo $questionsjson; ?>;
  let viewData = rawData.slice();
  
  const moodleBaseUrl = '<?php echo $moodlebaseurl; ?>';
  
  const COMMON_CATS = [];

  let currentSort = {field: null, direction: 'asc'};
  let currentlyOpenDetails = null;
  let advancedRules = [];
  let ruleIdCounter = 0;
  let columnWidths = {
    name: null,
    actions: 280,
    category: null,
    tags: 200,
    usedin: 200
  };

  // Elements.
  const kw = document.getElementById('kw');
  const kwMode = document.getElementById('kwMode');
  const kwField = document.getElementById('kwField');
  const kwType = document.getElementById('kwType');
  const numericFilters = document.getElementById('numericFilters');
  const categoricalFilters = document.getElementById('categoricalFilters');
  const applyBtn = document.getElementById('apply');
  const clearBtn = document.getElementById('clear');
  const listEl = document.getElementById('list');
  const countEl = document.getElementById('count');
  const exportJsonBtn = document.getElementById('exportJson');
  const exportCsvBtn = document.getElementById('exportCsv');
  const advancedToggle = document.getElementById('advancedToggle');
  const advancedContent = document.getElementById('advancedContent');
  const filterRulesEl = document.getElementById('filterRules');
  const addRuleBtn = document.getElementById('addRule');
  const activeFiltersChips = document.getElementById('activeFiltersChips');
  const qbrowserMain = document.getElementById('qbrowserMain');
  const filterPanel = document.getElementById('filterPanel');
  const mainResizer = document.getElementById('mainResizer');

  // Helpers.
  function isNumber(x){ return typeof x === 'number' && Number.isFinite(x); }
  
  function buildHeader() {
    const table = document.createElement('table');
    table.className = 'table table-striped table-hover table-sm';
    table.style.tableLayout = 'fixed';
    table.style.width = '100%';
    
    const colgroup = document.createElement('colgroup');
    const nameColDef = document.createElement('col');
    const actionsColDef = document.createElement('col');
    const categoryColDef = document.createElement('col');
    const tagsColDef = document.createElement('col');
    const usedinColDef = document.createElement('col');

    if (columnWidths.name) nameColDef.style.width = columnWidths.name + 'px';
    actionsColDef.style.width = columnWidths.actions + 'px';
    if (columnWidths.category) categoryColDef.style.width = columnWidths.category + 'px';
    if (columnWidths.tags) tagsColDef.style.width = columnWidths.tags + 'px';
    if (columnWidths.usedin) usedinColDef.style.width = columnWidths.usedin + 'px';

    colgroup.append(nameColDef, actionsColDef, categoryColDef, tagsColDef, usedinColDef);
    table.appendChild(colgroup);
    
    const thead = document.createElement('thead');
    thead.className = 'table-dark sticky-top';
    const headerRow = document.createElement('tr');
    
    const nameCol = document.createElement('th');
    nameCol.id = 'sortName';
    nameCol.style.cursor = 'pointer';
    nameCol.className = 'user-select-none';
    nameCol.style.position = 'relative';
    const nameText = document.createElement('span');
    nameText.textContent = 'Name ↕';
    nameCol.appendChild(nameText);
    
    const actionsCol = document.createElement('th');
    actionsCol.style.position = 'relative';
    const actionsText = document.createElement('span');
    actionsText.textContent = 'Actions';
    actionsCol.appendChild(actionsText);
    
    const categoryCol = document.createElement('th');
    categoryCol.id = 'sortCategory';
    categoryCol.style.cursor = 'pointer';
    categoryCol.className = 'user-select-none';
    categoryCol.style.position = 'relative';
    const categoryText = document.createElement('span');
    categoryText.textContent = 'Category ↕';
    categoryCol.appendChild(categoryText);

    const tagsCol = document.createElement('th');
    tagsCol.id = 'sortTags';
    tagsCol.style.cursor = 'pointer';
    tagsCol.className = 'user-select-none';
    tagsCol.style.position = 'relative';
    const tagsText = document.createElement('span');
    tagsText.textContent = 'Tags ↕';
    tagsCol.appendChild(tagsText);

    const usedinCol = document.createElement('th');
    usedinCol.id = 'sortUsedIn';
    usedinCol.style.cursor = 'pointer';
    usedinCol.className = 'user-select-none';
    usedinCol.style.position = 'relative';
    const usedinText = document.createElement('span');
    usedinText.textContent = 'Used In ↕';
    usedinCol.appendChild(usedinText);

    // Add resizers to all columns except the last
    [nameCol, actionsCol, categoryCol, tagsCol].forEach((col, idx) => {
      const resizer = document.createElement('div');
      resizer.className = 'column-resizer';
      resizer.dataset.columnIndex = idx;

      // Stop propagation to prevent triggering sort on parent <th>
      resizer.addEventListener('click', (e) => {
        e.stopPropagation();
      });

      col.appendChild(resizer);
    });

    headerRow.appendChild(nameCol);
    headerRow.appendChild(actionsCol);
    headerRow.appendChild(categoryCol);
    headerRow.appendChild(tagsCol);
    headerRow.appendChild(usedinCol);
    
    thead.appendChild(headerRow);
    table.appendChild(thead);
    
    const tbody = document.createElement('tbody');
    table.appendChild(tbody);
    
    return table;
  }
  
  function toCSV(arr) {
    if (!arr.length) return "";
    const headers = Object.keys(arr[0]);
    const esc = v => {
      if (v === null || v === undefined) return "";
      const s = typeof v === 'object' ? JSON.stringify(v) : String(v);
      return /[",\n]/.test(s) ? `"${s.replace(/"/g,'""')}"` : s;
    };
    return [headers.join(',')].concat(arr.map(o => headers.map(h => esc(o[h])).join(','))).join('\n');
  }
  
  function download(filename, content, mime) {
    const blob = new Blob([content], {type: mime});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  function summarizeRow(q, idx, tbody){
    const row = document.createElement('tr');

    const nameCell = document.createElement('td');
    nameCell.textContent = q.name ?? '';
    nameCell.className = 'text-truncate';
    nameCell.style.maxWidth = '300px';

    const actionsCell = document.createElement('td');
    actionsCell.className = 'qbrowser-controls';
    
    const questionBtn = document.createElement('button');
    questionBtn.className = 'btn btn-outline-secondary';
    questionBtn.style.cssText = 'padding: 2px 5px; font-size: 10px; margin-right: 1px; line-height: 1.2;';
    questionBtn.textContent = 'Question';
    questionBtn.title = 'View Question';
    
    const answerBtn = document.createElement('button');
    answerBtn.className = 'btn btn-outline-secondary';
    answerBtn.style.cssText = 'padding: 2px 5px; font-size: 10px; margin-right: 1px; line-height: 1.2;';
    answerBtn.textContent = 'Answer';
    answerBtn.title = 'View Answer';
    
    const previewBtn = document.createElement('button');
    previewBtn.className = 'btn btn-outline-secondary';
    previewBtn.style.cssText = 'padding: 2px 5px; font-size: 10px; margin-right: 1px; line-height: 1.2;';
    previewBtn.textContent = 'Preview';
    previewBtn.title = 'Preview Question';
    
    const bankBtn = document.createElement('button');
    bankBtn.className = 'btn btn-outline-secondary';
    bankBtn.style.cssText = 'padding: 2px 5px; font-size: 10px; margin-right: 1px; line-height: 1.2;';
    bankBtn.textContent = 'Bank';
    bankBtn.title = 'View in Question Bank';

    const jsonBtn = document.createElement('button');
    jsonBtn.className = 'btn btn-outline-secondary';
    jsonBtn.style.cssText = 'padding: 2px 5px; font-size: 10px; line-height: 1.2;';
    jsonBtn.textContent = 'JSON';
    jsonBtn.title = 'View JSON';

    actionsCell.append(questionBtn, answerBtn, previewBtn, bankBtn, jsonBtn);

    const categoryCell = document.createElement('td');
    categoryCell.textContent = q.category ?? '';
    categoryCell.className = 'text-muted small text-truncate';
    categoryCell.style.maxWidth = '200px';

    const tagsCell = document.createElement('td');
    const tagText = Array.isArray(q.tags) && q.tags.length > 0 ? q.tags.join(', ') : '';
    tagsCell.textContent = tagText;
    tagsCell.className = 'text-muted small text-truncate';
    tagsCell.style.maxWidth = '200px';
    tagsCell.title = tagText;

    const usedinCell = document.createElement('td');
    const usedinArray = Array.isArray(q.usedin) ? q.usedin : [];
    usedinCell.className = 'text-muted small';
    usedinCell.style.maxWidth = '200px';

    // Create one div per quiz name, each with its own truncation
    if (usedinArray.length > 0) {
      usedinArray.forEach(quizname => {
        const quizDiv = document.createElement('div');
        quizDiv.textContent = quizname;
        quizDiv.className = 'text-truncate';
        quizDiv.title = quizname;
        usedinCell.appendChild(quizDiv);
      });
    }

    // Tooltip shows full list
    const usedinText = usedinArray.join('\n');
    usedinCell.title = usedinText;

    row.appendChild(nameCell);
    row.appendChild(actionsCell);
    row.appendChild(categoryCell);
    row.appendChild(tagsCell);
    row.appendChild(usedinCell);

    let openType = null;
    let detailRow = null;

    function closeDetails() {
      openType = null;
      questionBtn.textContent = 'Question';
      answerBtn.textContent = 'Answer';
      jsonBtn.textContent = 'JSON';
      questionBtn.classList.remove('qbrowser-btn-active');
      answerBtn.classList.remove('qbrowser-btn-active');
      jsonBtn.classList.remove('qbrowser-btn-active');
      if (detailRow) {
        detailRow.remove();
        detailRow = null;
      }
      if (currentlyOpenDetails === closeDetails) {
        currentlyOpenDetails = null;
      }
    }

    function toggleDisplay(type, content, isHTML = false) {
      if (openType === type) {
        closeDetails();
      } else {
        if (currentlyOpenDetails && currentlyOpenDetails !== closeDetails) {
          currentlyOpenDetails();
        }

        if (detailRow) {
          detailRow.remove();
        }

        openType = type;
        questionBtn.textContent = type === 'question' ? 'Close' : 'Question';
        answerBtn.textContent = type === 'answer' ? 'Close' : 'Answer';
        jsonBtn.textContent = type === 'json' ? 'Close' : 'JSON';

        questionBtn.classList.remove('qbrowser-btn-active');
        answerBtn.classList.remove('qbrowser-btn-active');
        jsonBtn.classList.remove('qbrowser-btn-active');

        if (type === 'question') questionBtn.classList.add('qbrowser-btn-active');
        else if (type === 'answer') answerBtn.classList.add('qbrowser-btn-active');
        else if (type === 'json') jsonBtn.classList.add('qbrowser-btn-active');

        detailRow = document.createElement('tr');
        const detailCell = document.createElement('td');
        detailCell.colSpan = 5;

        const detail = document.createElement('div');
        detail.className = isHTML ? 'qbrowser-detail html-content' : 'qbrowser-detail code-content';
        if (isHTML) detail.innerHTML = content;
        else        detail.textContent = content;

        detailCell.appendChild(detail);
        detailRow.appendChild(detailCell);

        row.insertAdjacentElement('afterend', detailRow);

        currentlyOpenDetails = closeDetails;
      }
    }

    jsonBtn.addEventListener('click', () => toggleDisplay('json', JSON.stringify(q, null, 2)));
    questionBtn.addEventListener('click', () => toggleDisplay('question', q.questiontext || 'No question text available', true));
    answerBtn.addEventListener('click', () => toggleDisplay('answer', q.answer || 'No answer available'));
    
    previewBtn.addEventListener('click', () => {
      if (q.id) {
        const previewUrl = `${moodleBaseUrl}/question/bank/previewquestion/preview.php?id=${q.id}`;
        window.open(previewUrl, '_blank');
      } else {
        alert('No question ID available for preview');
      }
    });

    bankBtn.addEventListener('click', () => {
      if (q.id && q.courseid && q.categoryid) {
        const params = new URLSearchParams({
          'qperpage': '1000',
          'category': q.categoryid + ',' + <?php echo $contextid; ?>,
          'lastchanged': q.id,
          'courseid': q.courseid,
          'showhidden': '1'
        });
        const bankUrl = `${moodleBaseUrl}/question/edit.php?${params.toString()}`;
        window.open(bankUrl, '_blank');
      } else {
        alert('Missing question data for question bank link');
      }
    });

    return row;
  }

  function sortBy(field) {
    if (currentSort.field === field) {
      currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
      currentSort.field = field;
      currentSort.direction = 'asc';
    }

    viewData.sort((a, b) => {
      let aVal, bVal;
      if (field === 'tags' || field === 'usedin') {
        aVal = (Array.isArray(a[field]) ? a[field].join(', ') : '').toLowerCase();
        bVal = (Array.isArray(b[field]) ? b[field].join(', ') : '').toLowerCase();
      } else {
        aVal = (a[field] || '').toString().toLowerCase();
        bVal = (b[field] || '').toString().toLowerCase();
      }

      if (currentSort.direction === 'asc') {
        return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
      } else {
        return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
      }
    });

    renderList(viewData);
    updateHeaderSortIndicators();
  }
  
  function updateHeaderSortIndicators() {
    const sortName = document.getElementById('sortName');
    const sortCategory = document.getElementById('sortCategory');
    const sortTags = document.getElementById('sortTags');
    const sortUsedIn = document.getElementById('sortUsedIn');

    [sortName, sortCategory, sortTags, sortUsedIn].forEach(header => {
      if (!header) return;
      let field;
      if (header.id === 'sortName') field = 'name';
      else if (header.id === 'sortCategory') field = 'category';
      else if (header.id === 'sortTags') field = 'tags';
      else if (header.id === 'sortUsedIn') field = 'usedin';

      // Find the span element that contains the text
      const span = header.querySelector('span');
      if (!span) return;

      if (field === currentSort.field) {
        const arrow = currentSort.direction === 'asc' ? '↑' : '↓';
        span.textContent = span.textContent.replace(/[↕↑↓]/, arrow);
      } else {
        span.textContent = span.textContent.replace(/[↕↑↓]/, '↕');
      }
    });
  }

  function renderList(data){
    listEl.innerHTML = '';
    
    if (data.length === 0) {
      listEl.innerHTML = '<div class="text-center p-4 text-muted">No questions match the current filters.</div>';
    } else {
      const table = buildHeader();
      const tbody = table.querySelector('tbody');
      
      data.forEach((q, i) => {
        const row = summarizeRow(q, i, tbody);
        tbody.appendChild(row);
      });
      
      listEl.appendChild(table);
    }

    countEl.textContent = `${data.length.toLocaleString()} shown / ${rawData.length.toLocaleString()} total`;
    const hasData = data.length > 0;
    exportJsonBtn.disabled = !hasData;
    exportCsvBtn.disabled = !hasData;
    
    document.getElementById('sortName')?.addEventListener('click', () => sortBy('name'));
    document.getElementById('sortCategory')?.addEventListener('click', () => sortBy('category'));
    document.getElementById('sortTags')?.addEventListener('click', () => sortBy('tags'));
    document.getElementById('sortUsedIn')?.addEventListener('click', () => sortBy('usedin'));
  }

  function buildFilters(data){
    kwField.innerHTML = '';
    const keys = Array.from(new Set(data.flatMap(obj => Object.keys(obj))));
    const optAny = document.createElement('option'); optAny.textContent = 'Any'; kwField.appendChild(optAny);
    keys.filter(k => k !== 'version' && k !== 'timemodified' && k !== 'type' && k !== 'courseid' && k !== 'lines_of_code').forEach(k => {
      const o = document.createElement('option'); o.textContent = k; kwField.appendChild(o);
    });

    const numFields = keys.filter(k => {
      if (k === 'version' || k === 'timemodified') return false;
      let n=0, t=0;
      for (const o of data) { if (k in o) { t++; if (isNumber(o[k])) n++; } }
      return t > 0 && n / t >= 0.8;
    });

    numericFilters.innerHTML = '';
    numFields.forEach(k => {
      const inputGroup = document.createElement('div');
      inputGroup.className = 'qbrowser-grid2';
      
      const min = document.createElement('input');
      min.type = 'number';
      min.placeholder = 'min';
      min.className = 'form-control form-control-sm';
      min.dataset.key = k;
      min.dataset.kind = 'min';
      
      const max = document.createElement('input');
      max.type = 'number';
      max.placeholder = 'max';
      max.className = 'form-control form-control-sm';
      max.dataset.key = k;
      max.dataset.kind = 'max';
      
      inputGroup.append(min, max);
      numericFilters.appendChild(inputGroup);
    });

    const catFields = new Set(COMMON_CATS.filter(k => keys.includes(k)));
    keys.forEach(k => {
      const vals = new Set();
      let seen = 0;
      for (const o of data) {
        if (k in o) { seen++; const v = o[k]; if (typeof v === 'string') vals.add(v); if (vals.size > 30) break; }
      }
      if (seen > 0 && vals.size > 1 && vals.size <= 30) catFields.add(k);
    });

    categoricalFilters.innerHTML = '';
    [...catFields].forEach(k => {
      const select = document.createElement('select');
      select.className = 'form-control form-control-sm';
      select.dataset.key = k;
      
      const dataValues = new Set(data.map(o => o[k]).filter(v => typeof v === 'string' && v.trim() !== ''));
      const values = Array.from(dataValues).sort();
      
      const emptyOption = document.createElement('option');
      emptyOption.value = '';
      emptyOption.textContent = '(any)';
      select.appendChild(emptyOption);
      
      values.forEach(v => {
        const option = document.createElement('option');
        option.value = v;
        option.textContent = v;
        select.appendChild(option);
      });
      
      categoricalFilters.appendChild(select);
    });
  }

  function getNumericFilterRanges(){
    const inputs = numericFilters.querySelectorAll('input[type="number"]');
    const ranges = {};
    inputs.forEach(inp => {
      const key = inp.dataset.key;
      const kind = inp.dataset.kind;
      const val = inp.value === '' ? null : Number(inp.value);
      ranges[key] = ranges[key] || {min: null, max: null};
      ranges[key][kind] = val;
    });
    return ranges;
  }

  // Advanced filter functions
  function getAvailableFields() {
    const keys = Array.from(new Set(rawData.flatMap(obj => Object.keys(obj))));
    return keys.filter(k => k !== 'version' && k !== 'timemodified' && k !== 'type' && k !== 'courseid' && k !== 'lines_of_code').sort();
  }

  function getOperatorsForField(field) {
    // Determine field type from data
    const sampleValues = rawData.slice(0, 10).map(obj => obj[field]).filter(v => v !== null && v !== undefined);
    
    if (sampleValues.length === 0) {
      return ['contains', 'does not contain', 'equals', 'does not equal', 'matches regex', 'does not match regex'];
    }

    const firstValue = sampleValues[0];
    
    if (Array.isArray(firstValue)) {
      return ['includes', 'does not include', 'is empty', 'is not empty', 'matches regex', 'does not match regex'];
    } else if (typeof firstValue === 'number') {
      return ['equals', 'does not equal', 'greater than', 'less than', 'greater or equal', 'less or equal'];
    } else {
      return ['contains', 'does not contain', 'equals', 'does not equal', 'starts with', 'ends with', 'is empty', 'is not empty', 'matches regex', 'does not match regex'];
    }
  }

  function evaluateRule(obj, rule) {
    const fieldValue = obj[rule.field];
    const compareValue = rule.value;

    switch (rule.operator) {
      case 'contains':
        return String(fieldValue || '').toLowerCase().includes(String(compareValue).toLowerCase());
      case 'does not contain':
        return !String(fieldValue || '').toLowerCase().includes(String(compareValue).toLowerCase());
      case 'equals':
        return String(fieldValue || '').toLowerCase() === String(compareValue).toLowerCase();
      case 'does not equal':
        return String(fieldValue || '').toLowerCase() !== String(compareValue).toLowerCase();
      case 'starts with':
        return String(fieldValue || '').toLowerCase().startsWith(String(compareValue).toLowerCase());
      case 'ends with':
        return String(fieldValue || '').toLowerCase().endsWith(String(compareValue).toLowerCase());
      case 'is empty':
        return !fieldValue || (Array.isArray(fieldValue) && fieldValue.length === 0) || String(fieldValue).trim() === '';
      case 'is not empty':
        return fieldValue && (!Array.isArray(fieldValue) || fieldValue.length > 0) && String(fieldValue).trim() !== '';
      case 'includes':
        if (Array.isArray(fieldValue)) {
          return fieldValue.some(v => String(v).toLowerCase().includes(String(compareValue).toLowerCase()));
        }
        return String(fieldValue || '').toLowerCase().includes(String(compareValue).toLowerCase());
      case 'does not include':
        if (Array.isArray(fieldValue)) {
          return !fieldValue.some(v => String(v).toLowerCase().includes(String(compareValue).toLowerCase()));
        }
        return !String(fieldValue || '').toLowerCase().includes(String(compareValue).toLowerCase());
      case 'matches regex':
        try {
          const regex = new RegExp(compareValue, 'i');
          if (Array.isArray(fieldValue)) {
            return fieldValue.some(v => regex.test(String(v)));
          }
          return regex.test(String(fieldValue || ''));
        } catch (e) {
          console.error('Invalid regex pattern:', compareValue, e);
          return false;
        }
      case 'does not match regex':
        try {
          const regex = new RegExp(compareValue, 'i');
          if (Array.isArray(fieldValue)) {
            return !fieldValue.some(v => regex.test(String(v)));
          }
          return !regex.test(String(fieldValue || ''));
        } catch (e) {
          console.error('Invalid regex pattern:', compareValue, e);
          return true; // If regex is invalid, consider it as "not matching"
        }
      case 'greater than':
        return Number(fieldValue) > Number(compareValue);
      case 'less than':
        return Number(fieldValue) < Number(compareValue);
      case 'greater or equal':
        return Number(fieldValue) >= Number(compareValue);
      case 'less or equal':
        return Number(fieldValue) <= Number(compareValue);
      default:
        return true;
    }
  }

  function createFilterRule(rule = null) {
    const ruleId = rule?.id || ruleIdCounter++;
    const ruleDiv = document.createElement('div');
    ruleDiv.dataset.ruleId = ruleId;

    const ruleContainer = document.createElement('div');
    ruleContainer.className = 'filter-rule';
    ruleContainer.style.gridTemplateColumns = '1.8fr 2fr 1.8fr auto';

    // Field select
    const fieldSelect = document.createElement('select');
    fieldSelect.className = 'form-control form-control-sm';
    const fields = getAvailableFields();
    fields.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f;
      opt.textContent = f;
      if (rule && rule.field === f) opt.selected = true;
      fieldSelect.appendChild(opt);
    });

    // Operator select
    const operatorSelect = document.createElement('select');
    operatorSelect.className = 'form-control form-control-sm';
    const updateOperators = () => {
      const selectedField = fieldSelect.value;
      operatorSelect.innerHTML = '';
      const operators = getOperatorsForField(selectedField);
      operators.forEach(op => {
        const opt = document.createElement('option');
        opt.value = op;
        opt.textContent = op;
        if (rule && rule.operator === op) opt.selected = true;
        operatorSelect.appendChild(opt);
      });
      updateValueInput();
    };

    // Value input
    const valueInput = document.createElement('input');
    valueInput.type = 'text';
    valueInput.className = 'form-control form-control-sm';
    valueInput.placeholder = 'value';
    if (rule) valueInput.value = rule.value || '';

    const updateValueInput = () => {
      const operator = operatorSelect.value;
      if (operator === 'is empty' || operator === 'is not empty') {
        valueInput.disabled = true;
        valueInput.value = '';
        valueInput.placeholder = '';
        valueInput.title = '';
      } else if (operator === 'matches regex' || operator === 'does not match regex') {
        valueInput.disabled = false;
        valueInput.placeholder = 'regex pattern';
        valueInput.title = 'JavaScript regex pattern (case-insensitive)';
      } else {
        valueInput.disabled = false;
        valueInput.placeholder = 'value';
        valueInput.title = '';
      }
    };

    fieldSelect.addEventListener('change', updateOperators);
    operatorSelect.addEventListener('change', updateValueInput);

    // Remove button
    const removeBtn = document.createElement('button');
    removeBtn.className = 'btn btn-sm btn-outline-danger';
    removeBtn.textContent = '×';
    removeBtn.title = 'Remove rule';
    removeBtn.addEventListener('click', () => {
      advancedRules = advancedRules.filter(r => r.id !== ruleId);
      ruleDiv.remove();
      updateActiveFiltersChips();
    });

    ruleContainer.append(fieldSelect, operatorSelect, valueInput, removeBtn);
    ruleDiv.appendChild(ruleContainer);

    // Add connector if not the first rule
    if (filterRulesEl.children.length > 0) {
      const connector = document.createElement('div');
      connector.className = 'filter-connector';
      
      const connectorSelect = document.createElement('select');
      connectorSelect.className = 'form-control filter-connector-select';
      ['AND', 'OR'].forEach(op => {
        const opt = document.createElement('option');
        opt.value = op;
        opt.textContent = op;
        if (rule && rule.connector === op) opt.selected = true;
        connectorSelect.appendChild(opt);
      });
      
      connector.appendChild(connectorSelect);
      ruleDiv.insertBefore(connector, ruleContainer);
      
      connectorSelect.addEventListener('change', () => {
        const existingRule = advancedRules.find(r => r.id === ruleId);
        if (existingRule) {
          existingRule.connector = connectorSelect.value;
        }
      });
    }

    updateOperators();

    // Store rule data
    const ruleData = {
      id: ruleId,
      connector: rule?.connector || 'AND',
      get field() { return fieldSelect.value; },
      get operator() { return operatorSelect.value; },
      get value() { return valueInput.value; }
    };

    if (!rule) {
      advancedRules.push(ruleData);
    }

    return ruleDiv;
  }

  function updateActiveFiltersChips() {
    activeFiltersChips.innerHTML = '';
    
    advancedRules.forEach((rule, idx) => {
      const chip = document.createElement('div');
      chip.className = 'filter-chip';
      
      const text = document.createElement('span');
      const connector = idx > 0 ? `${rule.connector} ` : '';
      const valueText = rule.operator === 'is empty' || rule.operator === 'is not empty' ? '' : `: "${rule.value}"`;
      text.textContent = `${connector}${rule.field} ${rule.operator}${valueText}`;
      
      const removeBtn = document.createElement('button');
      removeBtn.className = 'filter-chip-remove';
      removeBtn.textContent = '×';
      removeBtn.title = 'Remove filter';
      removeBtn.addEventListener('click', () => {
        advancedRules = advancedRules.filter(r => r.id !== rule.id);
        const ruleEl = filterRulesEl.querySelector(`[data-rule-id="${rule.id}"]`);
        if (ruleEl) ruleEl.remove();
        updateActiveFiltersChips();
      });
      
      chip.append(text, removeBtn);
      activeFiltersChips.appendChild(chip);
    });
  }

  function applyAdvancedFilters(data) {
    if (advancedRules.length === 0) return data;

    return data.filter(obj => {
      let result = evaluateRule(obj, advancedRules[0]);
      
      for (let i = 1; i < advancedRules.length; i++) {
        const rule = advancedRules[i];
        const ruleResult = evaluateRule(obj, rule);
        
        if (rule.connector === 'AND') {
          result = result && ruleResult;
        } else {
          result = result || ruleResult;
        }
      }
      
      return result;
    });
  }

  function applyFilters(){
    let out = rawData.slice();

    // Numeric
    const ranges = getNumericFilterRanges();
    for (const [k, {min, max}] of Object.entries(ranges)) {
      if (min !== null) out = out.filter(o => isNumber(o[k]) ? o[k] >= min : true);
      if (max !== null) out = out.filter(o => isNumber(o[k]) ? o[k] <= max : true);
    }

    // Categorical
    categoricalFilters.querySelectorAll('select').forEach(sel => {
      const key = sel.dataset.key;
      const val = sel.value;
      if (val !== '') {
        out = out.filter(o => (o[key] ?? '') === val);
      }
    });

    // Keyword
    const needle = kw.value.trim();
    if (needle) {
      const mode = kwMode.value;
      const fieldChoice = kwField.value;
      const searchType = kwType.value;
      
      let regex = null;
      if (searchType === 'Regex') {
        try {
          regex = new RegExp(needle, 'i');
        } catch (e) {
          alert('Invalid regex pattern: ' + e.message);
          return;
        }
      }
      
      const matches = (obj) => {
        if (fieldChoice === 'Any') {
          return Object.values(obj).some(v => {
            let s;
            if (Array.isArray(v)) s = v.join(', ');
            else if (v && typeof v === 'object') s = JSON.stringify(v);
            else s = String(v ?? '');
            
            if (searchType === 'Regex') {
              return regex.test(s);
            } else {
              return s.toLowerCase().includes(needle.toLowerCase());
            }
          });
        } else {
          const v = obj[fieldChoice];
          let s;
          if (Array.isArray(v)) s = v.join(', ');
          else if (v && typeof v === 'object') s = JSON.stringify(v);
          else s = String(v ?? '');
          
          if (searchType === 'Regex') {
            return regex.test(s);
          } else {
            return s.toLowerCase().includes(needle.toLowerCase());
          }
        }
      };
      out = out.filter(o => (mode === 'Include') ? matches(o) : !matches(o));
    }

    // Advanced filters
    out = applyAdvancedFilters(out);

    viewData = out;
    renderList(viewData);
    updateActiveFiltersChips();
  }

  function clearFiltersUI(){
    numericFilters.querySelectorAll('input').forEach(i => i.value = '');
    categoricalFilters.querySelectorAll('select').forEach(s => s.value = '');
    kw.value = '';
    kwMode.value = 'Include';
    kwField.value = 'Any';
    kwType.value = 'Text';
    
    // Clear advanced filters
    advancedRules = [];
    filterRulesEl.innerHTML = '';
    updateActiveFiltersChips();
  }

  // Advanced filter toggle
  advancedToggle.addEventListener('click', () => {
    const isExpanded = advancedContent.classList.contains('show');
    if (isExpanded) {
      advancedContent.classList.remove('show');
      advancedToggle.querySelector('.advanced-toggle-icon').classList.remove('expanded');
    } else {
      advancedContent.classList.add('show');
      advancedToggle.querySelector('.advanced-toggle-icon').classList.add('expanded');
    }
  });

  // Add rule button
  addRuleBtn.addEventListener('click', () => {
    if (advancedRules.length >= 6) {
      alert('Maximum of 6 advanced filter rules allowed');
      return;
    }
    const ruleEl = createFilterRule();
    filterRulesEl.appendChild(ruleEl);
  });

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', () => {
    buildFilters(rawData);
    renderList(viewData);
    initializeResizers();
  });

  // Enter key on search field applies filters
  kw.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      applyFilters();
    }
  });

  // Resizer functionality
  function initializeResizers() {
    // Main panel resizer
    let isResizingMain = false;
    let startX = 0;
    let startWidth = 0;

    mainResizer.addEventListener('mousedown', (e) => {
      isResizingMain = true;
      startX = e.clientX;
      startWidth = filterPanel.offsetWidth;
      document.body.classList.add('resizing');
      e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
      if (isResizingMain) {
        const delta = e.clientX - startX;
        const newWidth = Math.max(250, Math.min(600, startWidth + delta));
        qbrowserMain.style.gridTemplateColumns = `${newWidth}px 1fr`;
        // Update resizer position to stay centered in gap
        mainResizer.style.left = `calc(${newWidth}px + 0.75rem)`;
      }
    });

    document.addEventListener('mouseup', () => {
      if (isResizingMain) {
        isResizingMain = false;
        document.body.classList.remove('resizing');
      }
    });

    // Column resizers (delegated event listener)
    document.addEventListener('mousedown', (e) => {
      if (e.target.classList.contains('column-resizer')) {
        const resizer = e.target;
        const columnIndex = parseInt(resizer.dataset.columnIndex);
        const table = resizer.closest('table');
        const colgroup = table.querySelector('colgroup');
        const cols = Array.from(colgroup.children);
        
        let startX = e.clientX;
        let startWidth = cols[columnIndex].offsetWidth || parseInt(cols[columnIndex].style.width);
        let nextStartWidth = cols[columnIndex + 1].offsetWidth || parseInt(cols[columnIndex + 1].style.width);

        document.body.classList.add('resizing');
        e.preventDefault();

        const onMouseMove = (e) => {
          const delta = e.clientX - startX;
          const newWidth = Math.max(80, startWidth + delta);
          const nextNewWidth = Math.max(80, nextStartWidth - delta);
          
          cols[columnIndex].style.width = newWidth + 'px';
          cols[columnIndex + 1].style.width = nextNewWidth + 'px';

          // Store widths
          const columnNames = ['name', 'actions', 'category', 'tags', 'usedin'];
          columnWidths[columnNames[columnIndex]] = newWidth;
          columnWidths[columnNames[columnIndex + 1]] = nextNewWidth;
        };

        const onMouseUp = () => {
          document.body.classList.remove('resizing');
          document.removeEventListener('mousemove', onMouseMove);
          document.removeEventListener('mouseup', onMouseUp);
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
      }
    });
  }

  // Apply/Clear
  applyBtn.addEventListener('click', applyFilters);
  clearBtn.addEventListener('click', () => { clearFiltersUI(); viewData = rawData.slice(); renderList(viewData); });

  // Export
  exportJsonBtn.addEventListener('click', () => {
    download('filtered_questions.json', JSON.stringify(viewData, null, 2), 'application/json');
  });
  exportCsvBtn.addEventListener('click', () => {
    download('filtered_questions.csv', toCSV(viewData), 'text/csv');
  });

})();
</script>

<?php
echo $OUTPUT->footer();
