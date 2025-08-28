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
 * This version generates question data directly when the page loads,
 * eliminating the need for separate files or web services.
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

// Get course name for display
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

// Generate questions data directly
$generator = new questions_json_generator($context);
$questions = $generator->generate_questions_data();
$questionsjson = json_encode($questions, JSON_UNESCAPED_UNICODE);

// Get the correct Moodle base URL for JavaScript
global $CFG;
$moodlebaseurl = $CFG->wwwroot;

$urlparams = ['contextid' => $context->id];
$PAGE->set_url('/question/type/coderunner/questionbrowser.php', $urlparams);
$PAGE->set_context($context);
$PAGE->set_title("Question browser");

if ($context->contextlevel == CONTEXT_MODULE) {
    // Calling $PAGE->set_context should be enough, but it seems that it is not.
    // Therefore, we get the right $cm and $course, and set things up ourselves.
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $PAGE->set_cm($cm, $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST));
}

/**
 * Class to generate questions.json from database.
 */
class questions_json_generator {
    private $context;

    private static $stageorder = [
        'fundamentals', 'if', 'while', 'lists', 'for', 'files', 
        'numpy/matplotlib', 'dicts', 'oop'
    ];

    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * Generate the complete questions data array.
     */
    public function generate_questions_data() {
        // Use the same query pattern as bulk_tester
        $questions = bulk_tester::get_all_coderunner_questions_in_context($this->context->id, false);

        $enhancedquestions = [];

        foreach ($questions as $question) {
            $enhanced = $this->enhance_question_metadata($question);
            $enhancedquestions[] = $enhanced;
        }

        return $enhancedquestions;
    }

    /**
     * Enhance a single question with metadata analysis.
     */
    private function enhance_question_metadata($question) {
        // Determine course ID from context
        $courseid = $this->get_course_id_from_context();
        
        // Check if this is a COSC131 course for specialized analysis
        $iscosc131 = $this->is_cosc131_course();

        // Extract the correct answer, handling JSON format if needed
        $answer = $this->extract_answer($question->answer ?? '');

        // Base question data matching the JSON structure.
        $enhanced = [
            'type' => 'coderunner',
            'id' => (string)$question->id,
            'name' => $question->name,
            'questiontext' => $question->questiontext,
            'answer' => $answer,
            'coderunnertype' => $question->coderunnertype,
            'category' => bulk_tester::get_category_path($question->category),
            'categoryid' => (string)$question->category, // Keep the original category ID for URLs
            'version' => (int)$question->version,
            'courseid' => (string)$courseid
        ];

        // Analyze code to add enhanced metadata,
        $enhanced['lines_of_code'] = $this->count_lines_of_code($answer);
        $enhanced['constructs_used'] = $this->detect_constructs($answer);
        
        // COSC131-specific fields
        if ($iscosc131) {
            $enhanced['highest_stage'] = $this->classify_stage($enhanced['constructs_used'], $answer);
            $enhanced['topic_area'] = $this->determine_topic_area(
                $answer,
                $question->name, 
                $enhanced['constructs_used']
            );
        }
        
        $enhanced['spec_difficulty'] = $this->assess_difficulty(
            $enhanced['lines_of_code'],
            $enhanced['constructs_used']
        );
        $enhanced['question_type'] = $this->determine_question_type($question->coderunnertype);

        return $enhanced;
    }

    /**
     * Extract the correct answer from either JSON or plain text format.
     * If the answer is JSON and contains an 'answer_code' key, use that value.
     * Otherwise, return the original answer.
     */
    private function extract_answer($answer) {
        if (empty(trim($answer))) {
            return '';
        }

        // Attempt to decode JSON
        $decoded = json_decode($answer, true);
        
        // If JSON decoding succeeded and contains 'answer_code' key, use that
        if (json_last_error() === JSON_ERROR_NONE && 
            is_array($decoded) && 
            array_key_exists('answer_code', $decoded)) {
            // answer_code is typically an array, so join it if needed
            $answerCode = $decoded['answer_code'];
            if (is_array($answerCode)) {
                return implode("\n", $answerCode);
            }
            return $answerCode;
        }
        
        // Otherwise, return the original answer
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
    
    private function is_cosc131_course() {
        global $DB;
        
        $courseid = $this->get_course_id_from_context();
        if ($courseid == '0') {
            return false;
        }
        
        $course = $DB->get_record('course', ['id' => $courseid], 'shortname');
        if (!$course) {
            return false;
        }
        
        return stripos($course->shortname, 'cosc131') === 0;
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

    private function detect_constructs($code) {
        if (empty(trim($code))) {
            return [];
        }

        $constructs = [];
        
        // Remove Twig variables for analysis
        $codeforanalysis = preg_replace('/\{\{([^}]+)\}\}/', '$1', $code);
        $lines = explode("\n", $codeforanalysis);

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Simple keyword detection at line start or after whitespace
            if (preg_match('/^\s*if\b/', $line)) {
                $constructs[] = 'if';
            }
            if (preg_match('/^\s*for\s+\w+\s+in\b/', $line)) {
                $constructs[] = 'for';
            }
            if (preg_match('/^\s*while\b/', $line)) {
                $constructs[] = 'while';
            }
            if (preg_match('/^\s*def\s+\w+\s*\(/', $line)) {
                $constructs[] = 'def';
            }
            if (preg_match('/^\s*class\s+\w+/', $line)) {
                $constructs[] = 'class';
            }
            if (preg_match('/^\s*try\s*:/', $line)) {
                $constructs[] = 'try-except';
            }

            // Content-based detection (anywhere in line)
            if (strpos($line, 'open(') !== false) {
                $constructs[] = 'file-io';
            }
            if (preg_match('/\bimport\b|\bfrom\s+\w+\s+import/', $line)) {
                $constructs[] = 'import';
            }
            if (preg_match('/\b(numpy|np|matplotlib|plt)\b/', $line)) {
                $constructs[] = 'numpy/matplotlib';
            }
            // Detect dictionary usage patterns.
            $isdictline = false;

            // Dictionary literal with key:value pairs, avoiding f-strings and format strings.
            if (preg_match('/\{[^}]*:[^}]*\}/', $line) &&
                !preg_match('/f["\']/', $line) &&
                !preg_match('/\.format\s*\(/', $line)
            ) {
                $isdictline = true;
            }

            // Empty dictionary initialization.
            if (preg_match('/=\s*\{\s*\}/', $line)) {
                $isdictline = true;
            }

            // Dictionary indexing/assignment.
            if (preg_match('/\w+\[.+\]\s*=/', $line) || preg_match('/=.*\w+\[.+\]/', $line)) {
                $isdictline = true;
            }

            // Dictionary constructor or methods.
            if (preg_match('/\bdict\s*\(/', $line) ||
                preg_match('/\.(keys|values|items|get|update)\s*\(/', $line)
            ) {
                $isdictline = true;
            }

            if ($isdictline) {
                $constructs[] = 'dict';
            }
            if (preg_match('/\[.*for\s+\w+\s+in\s+.*\]/', $line)) {
                $constructs[] = 'list-comp';
            }
        }

        return array_unique($constructs);
    }

    private function classify_stage($constructs, $code) {
        if (empty($constructs)) {
            return 'fundamentals';
        }

        // Check from highest to lowest stage
        if (in_array('class', $constructs)) return 'oop';
        if (in_array('numpy/matplotlib', $constructs)) return 'numpy/matplotlib';
        if (in_array('dict', $constructs)) return 'dicts';
        if (in_array('file-io', $constructs)) return 'files';
        if (in_array('for', $constructs) || in_array('list-comp', $constructs)) return 'for';
        if (preg_match('/\[[^\]]*\]/', $code)) return 'lists'; // Simple list detection
        if (in_array('while', $constructs)) return 'while';
        if (in_array('if', $constructs)) return 'if';

        return 'fundamentals';
    }

    private function assess_difficulty($linesofcode, $constructs) {
        $constructcount = count($constructs);

        if ($linesofcode <= 3 && $constructcount <= 1) {
            return 'very_easy';
        } else if ($linesofcode <= 8 && $constructcount <= 2) {
            return 'easy';
        } else if ($linesofcode <= 15 && $constructcount <= 4) {
            return 'medium';
        } else if ($linesofcode <= 25 && $constructcount <= 6) {
            return 'hard';
        } else {
            return 'very_hard';
        }
    }

    private function determine_topic_area($code, $name, $constructs) {
        $text = strtolower($code . ' ' . $name);

        if (preg_match('/numpy|matplotlib|plot|graph|array/', $text)) {
            return 'data_science';
        } else if (preg_match('/file|read|write|open/', $text)) {
            return 'file_io';
        } else if (preg_match('/class|object|inherit/', $text)) {
            return 'oop';
        } else if (preg_match('/list|array|sort/', $text)) {
            return 'data_structures';
        } else if (preg_match('/function|def|return/', $text)) {
            return 'functions';
        } else {
            return 'general';
        }
    }

    private function determine_question_type($coderunnertype) {
        if (strpos($coderunnertype, 'scratchpad') !== false) {
            return 'scratchpad';
        } else if (strpos($coderunnertype, 'function') !== false) {
            return 'function';
        } else {
            return 'code';
        }
    }
}

// Set up page using Moodle's layout system.
$PAGE->set_title('Question Browser - ' . $coursename);
$PAGE->set_heading('Question Browser - ' . $coursename);
$PAGE->set_pagelayout('incourse'); // Use incourse layout for proper course context.

// Set up proper navigation context for the course
if ($context->contextlevel == CONTEXT_COURSE) {
    $PAGE->set_course($DB->get_record('course', ['id' => $context->instanceid]));
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
}

// Add the question browser to the navigation
$PAGE->navbar->add('Question Browser');

echo $OUTPUT->header();

?>

<style>
/* Reduce excessive white space */
#page-header, .page-header-headings {
    margin-top: 0;
    padding-top: 0.5rem;
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

@media (min-width: 992px) {
    .qbrowser-main {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 1.5rem;
    }
}
</style>

<div class="container-fluid qbrowser-main">
    <!-- LEFT: FILTERS -->
    <div class="card">
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
                <h6>Numeric filters</h6>
                <div id="numericFilters"></div>
            </div>

            <hr>

            <div class="form-group">
                <h6>Categorical filters</h6>
                <div id="categoricalFilters"></div>
            </div>

            <hr>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-sm" id="apply">Apply Filters</button>
                <button class="btn btn-secondary btn-sm" id="clear">Clear</button>
            </div>
            <div class="mt-2">
                <small class="text-muted">Tip: filters apply to the loaded dataset; keyword search stacks with them.</small>
            </div>
        </div>
    </div>

    <!-- RIGHT: RESULTS -->
    <div class="card">
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
  // Questions data is embedded directly from PHP
  const rawData = <?php echo $questionsjson; ?>;
  let viewData = rawData.slice();
  
  // Get the correct Moodle base URL from PHP
  const moodleBaseUrl = '<?php echo $moodlebaseurl; ?>';
  
  // Common categorical keys you likely have; only rendered if present in data.
  const COMMON_CATS = ["highest_stage", "spec_difficulty", "question_type", "topic_area"];
  
  // Teaching order for highest_stage dropdown
  const STAGE_ORDER = ["fundamentals", "if", "while", "lists", "for", "files", "numpy/matplotlib", "dicts", "oop"];

  let currentSort = {field: null, direction: 'asc'};
  let hasStageData = false; // Whether the current dataset has stage data

  // Elements
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

  // Helpers
  function isNumber(x){ return typeof x === 'number' && Number.isFinite(x); }
  
  function buildHeader() {
    const table = document.createElement('table');
    table.className = 'table table-striped table-hover table-sm';
    
    const thead = document.createElement('thead');
    thead.className = 'table-dark sticky-top';
    const headerRow = document.createElement('tr');
    
    // Name column
    const nameCol = document.createElement('th');
    nameCol.id = 'sortName';
    nameCol.style.cursor = 'pointer';
    nameCol.textContent = 'Name ↕';
    nameCol.className = 'user-select-none';
    
    // Actions column (moved between Name and Category)
    const actionsCol = document.createElement('th');
    actionsCol.textContent = 'Actions';
    actionsCol.style.width = '280px';
    
    // Category column  
    const categoryCol = document.createElement('th');
    categoryCol.id = 'sortCategory';
    categoryCol.style.cursor = 'pointer';
    categoryCol.textContent = 'Category ↕';
    categoryCol.className = 'user-select-none';
    
    // Stage column (if applicable)
    let stageCol = null;
    if (hasStageData) {
      stageCol = document.createElement('th');
      stageCol.id = 'sortStage';
      stageCol.style.cursor = 'pointer';
      stageCol.textContent = 'Stage ↕';
      stageCol.className = 'user-select-none';
    }
    
    headerRow.appendChild(nameCol);
    headerRow.appendChild(actionsCol);
    headerRow.appendChild(categoryCol);
    if (stageCol) headerRow.appendChild(stageCol);
    
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

    // Name cell
    const nameCell = document.createElement('td');
    nameCell.textContent = q.name ?? '';
    nameCell.className = 'text-truncate';
    nameCell.style.maxWidth = '300px';

    // Actions cell with smaller buttons
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

    // Category cell
    const categoryCell = document.createElement('td');
    categoryCell.textContent = q.category ?? '';
    categoryCell.className = 'text-muted small text-truncate';
    categoryCell.style.maxWidth = '200px';

    // Stage cell (if applicable)
    let stageCell = null;
    if (hasStageData) {
      stageCell = document.createElement('td');
      stageCell.textContent = q.highest_stage ?? '';
      stageCell.className = 'font-monospace small';
    }

    // Assemble the row in the new order: Name, Actions, Category, Stage
    row.appendChild(nameCell);
    row.appendChild(actionsCell);
    row.appendChild(categoryCell);
    if (stageCell) row.appendChild(stageCell);

    let openType = null; // 'json' | 'question' | 'answer'
    let detailRow = null;

    function toggleDisplay(type, content, isHTML = false) {
      if (openType === type) {
        // Close current detail
        openType = null;
        questionBtn.textContent = 'Question';
        answerBtn.textContent = 'Answer';
        jsonBtn.textContent = 'JSON';
        if (detailRow) {
          detailRow.remove();
          detailRow = null;
        }
      } else {
        // Close existing detail if any
        if (detailRow) {
          detailRow.remove();
        }
        
        // Update button states
        openType = type;
        questionBtn.textContent = type === 'question' ? 'Close' : 'Question';
        answerBtn.textContent = type === 'answer' ? 'Close' : 'Answer';
        jsonBtn.textContent = type === 'json' ? 'Close' : 'JSON';

        // Create new detail row
        detailRow = document.createElement('tr');
        const detailCell = document.createElement('td');
        const colSpan = hasStageData ? 4 : 3; // Name, Actions, Category, [Stage]
        detailCell.colSpan = colSpan;
        
        const detail = document.createElement('div');
        detail.className = isHTML ? 'qbrowser-detail html-content' : 'qbrowser-detail code-content';
        if (isHTML) detail.innerHTML = content;
        else        detail.textContent = content;
        
        detailCell.appendChild(detail);
        detailRow.appendChild(detailCell);
        
        // Insert detail row after current row
        row.insertAdjacentElement('afterend', detailRow);
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
      // Toggle direction if same field
      currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
      // New field, start with ascending
      currentSort.field = field;
      currentSort.direction = 'asc';
    }
    
    // Special sorting for highest_stage to follow teaching order
    if (field === 'highest_stage') {
      viewData.sort((a, b) => {
        const aIndex = STAGE_ORDER.indexOf(a[field] || '');
        const bIndex = STAGE_ORDER.indexOf(b[field] || '');
        const aVal = aIndex === -1 ? 999 : aIndex;
        const bVal = bIndex === -1 ? 999 : bIndex;
        return currentSort.direction === 'asc' ? aVal - bVal : bVal - aVal;
      });
    } else {
      // Standard string sorting
      viewData.sort((a, b) => {
        const aVal = (a[field] || '').toString().toLowerCase();
        const bVal = (b[field] || '').toString().toLowerCase();
        if (currentSort.direction === 'asc') {
          return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
        } else {
          return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
        }
      });
    }
    
    renderList(viewData);
    updateHeaderSortIndicators();
  }
  
  function updateHeaderSortIndicators() {
    // Update header to show sort direction
    const sortName = document.getElementById('sortName');
    const sortCategory = document.getElementById('sortCategory');
    const sortStage = document.getElementById('sortStage');
    
    [sortName, sortCategory, sortStage].forEach(header => {
      if (!header) return;
      let field;
      if (header.id === 'sortName') field = 'name';
      else if (header.id === 'sortCategory') field = 'category';
      else if (header.id === 'sortStage') field = 'highest_stage';
      
      if (field === currentSort.field) {
        const arrow = currentSort.direction === 'asc' ? '↑' : '↓';
        header.textContent = header.textContent.replace(/[↕↑↓]/, arrow);
      } else {
        header.textContent = header.textContent.replace(/[↕↑↓]/, '↕');
      }
    });
  }

  function renderList(data){
    listEl.innerHTML = '';
    
    if (data.length === 0) {
      listEl.innerHTML = '<div class="text-center p-4 text-muted">No questions match the current filters.</div>';
    } else {
      // Build and add table
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
    
    // Attach sort event listeners
    document.getElementById('sortName')?.addEventListener('click', () => sortBy('name'));
    document.getElementById('sortCategory')?.addEventListener('click', () => sortBy('category'));
    if (hasStageData) {
      document.getElementById('sortStage')?.addEventListener('click', () => sortBy('highest_stage'));
    }
  }

  function buildFilters(data){
    // Keyword field choices
    kwField.innerHTML = '';
    const keys = Array.from(new Set(data.flatMap(obj => Object.keys(obj))));
    const optAny = document.createElement('option'); optAny.textContent = 'Any'; kwField.appendChild(optAny);
    keys.filter(k => k !== 'version' && k !== 'timemodified').forEach(k => {
      const o = document.createElement('option'); o.textContent = k; kwField.appendChild(o);
    });

    // Detect numeric fields (appear as numbers in majority of records)
    // Exclude version and timemodified from numeric filters
    const numFields = keys.filter(k => {
      if (k === 'version' || k === 'timemodified') return false;
      let n=0, t=0;
      for (const o of data) { if (k in o) { t++; if (isNumber(o[k])) n++; } }
      return t > 0 && n / t >= 0.8;
    });

    numericFilters.innerHTML = '';
    numFields.forEach(k => {
      const formGroup = document.createElement('div');
      formGroup.className = 'form-group mb-2';
      
      const label = document.createElement('label');
      label.textContent = k;
      label.className = 'form-label small';
      
      const inputGroup = document.createElement('div');
      inputGroup.className = 'qbrowser-grid2';
      
      const min = document.createElement('input');
      min.type = 'number';
      min.placeholder = `${k} min`;
      min.className = 'form-control form-control-sm';
      min.dataset.key = k;
      min.dataset.kind = 'min';
      
      const max = document.createElement('input');
      max.type = 'number';
      max.placeholder = `${k} max`;
      max.className = 'form-control form-control-sm';
      max.dataset.key = k;
      max.dataset.kind = 'max';
      
      inputGroup.append(min, max);
      formGroup.append(label, inputGroup);
      numericFilters.appendChild(formGroup);
    });

    // Categorical filters: use known common + any string field with ≤ 30 unique vals
    const catFields = new Set(COMMON_CATS.filter(k => keys.includes(k)));
    keys.forEach(k => {
      // consider only string-like fields with limited unique values
      const vals = new Set();
      let seen = 0;
      for (const o of data) {
        if (k in o) { seen++; const v = o[k]; if (typeof v === 'string') vals.add(v); if (vals.size > 30) break; }
      }
      if (seen > 0 && vals.size > 1 && vals.size <= 30) catFields.add(k);
    });

    categoricalFilters.innerHTML = '';
    [...catFields].forEach(k => {
      const formGroup = document.createElement('div');
      formGroup.className = 'form-group mb-2';
      
      const label = document.createElement('label');
      label.textContent = k;
      label.className = 'form-label small';
      
      const select = document.createElement('select');
      select.className = 'form-control form-control-sm';
      select.dataset.key = k;
      
      let values;
      if (k === 'highest_stage') {
        // Use predefined teaching order for highest_stage, only include stages present in data
        const dataStages = new Set(data.map(o => o[k]).filter(v => typeof v === 'string' && v.trim() !== ''));
        values = STAGE_ORDER.filter(stage => dataStages.has(stage));
      } else {
        values = Array.from(new Set(data.map(o => o[k]).filter(v => typeof v === 'string' && v.trim() !== ''))).sort();
      }
      
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
      
      formGroup.append(label, select);
      categoricalFilters.appendChild(formGroup);
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
        if (key === 'highest_stage') {
          // For highest_stage, include selected stage AND all lower stages
          const selectedIndex = STAGE_ORDER.indexOf(val);
          if (selectedIndex !== -1) {
            out = out.filter(o => {
              const questionStage = o[key] ?? '';
              const questionIndex = STAGE_ORDER.indexOf(questionStage);
              return questionIndex !== -1 && questionIndex <= selectedIndex;
            });
          } else {
            // Fallback to exact match if stage not in order
            out = out.filter(o => (o[key] ?? '') === val);
          }
        } else {
          // Standard exact match for other categorical filters
          out = out.filter(o => (o[key] ?? '') === val);
        }
      }
    });

    // Keyword
    const needle = kw.value.trim();
    if (needle) {
      const mode = kwMode.value;          // Include/Exclude
      const fieldChoice = kwField.value;  // Any or specific key
      const searchType = kwType.value;    // Text or Regex
      
      let regex = null;
      if (searchType === 'Regex') {
        try {
          regex = new RegExp(needle, 'i'); // case-insensitive regex
        } catch (e) {
          alert('Invalid regex pattern: ' + e.message);
          return; // Don't apply filter if regex is invalid
        }
      }
      
      const matches = (obj) => {
        if (fieldChoice === 'Any') {
          return Object.values(obj).some(v => {
            let s;
            if (Array.isArray(v)) s = v.join(' ');
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
          if (Array.isArray(v)) s = v.join(' ');
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

    viewData = out;
    renderList(viewData);
  }

  function clearFiltersUI(){
    numericFilters.querySelectorAll('input').forEach(i => i.value = '');
    categoricalFilters.querySelectorAll('select').forEach(s => s.value = '');
    kw.value = '';
    kwMode.value = 'Include';
    kwField.value = 'Any';
    kwType.value = 'Text';
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', () => {
    // Detect if we have stage data
    hasStageData = rawData.length > 0 && rawData.some(q => q.hasOwnProperty('highest_stage'));
    
    buildFilters(rawData);
    renderList(viewData);
  });

  // Enter key on search field applies filters
  kw.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      applyFilters();
    }
  });

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
