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

    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * Generate the complete questions data array.
     */
    public function generate_questions_data() {
        // Use the same query pattern as bulk_tester.
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
        // Determine course ID from context.
        $courseid = $this->get_course_id_from_context();

        // Extract the correct answer, handling JSON format if needed.
        $answer = $this->extract_answer($question->answer ?? '');

        // Get question tags.
        $tags = $this->get_question_tags($question->id);

        // Base question data matching the JSON structure.
        $enhanced = [
            'type' => 'coderunner',
            'id' => (string)$question->id,
            'name' => $question->name,
            'questiontext' => $question->questiontext,
            'answer' => $answer,
            'coderunnertype' => $question->coderunnertype,
            'category' => bulk_tester::get_category_path($question->category),
            'categoryid' => (string)$question->category, // Keep the original category ID for URLs.
            'version' => (int)$question->version,
            'courseid' => (string)$courseid,
            'tags' => $tags,
        ];

        // Analyze code to add enhanced metadata.
        $enhanced['lines_of_code'] = $this->count_lines_of_code($answer);

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

        // Attempt to decode JSON.
        $decoded = json_decode($answer, true);

        // If JSON decoding succeeded and contains 'answer_code' key, use that.
        if (
            json_last_error() === JSON_ERROR_NONE &&
            is_array($decoded) &&
            array_key_exists('answer_code', $decoded)
        ) {
            // Answer_code is typically an array, so join it if needed.
            $answercode = $decoded['answer_code'];
            if (is_array($answercode)) {
                return implode("\n", $answercode);
            }
            return $answercode;
        }

        // Otherwise, return the original answer.
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

    /**
     * Get tags for a question.
     *
     * @param int $questionid The question ID
     * @return array Array of tag names
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

// Set up page using Moodle's layout system.
$PAGE->set_title('Question Browser - ' . $coursename);
$PAGE->set_heading('Question Browser - ' . $coursename);
$PAGE->set_pagelayout('incourse'); // Use incourse layout for proper course context.

// Set up proper navigation context for the course.
if ($context->contextlevel == CONTEXT_COURSE) {
    $PAGE->set_course($DB->get_record('course', ['id' => $context->instanceid]));
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
}

// Add the question browser to the navigation.
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
  // Questions data is embedded directly from PHP.
  const rawData = <?php echo $questionsjson; ?>;
  let viewData = rawData.slice();
  
  // Get the correct Moodle base URL from PHP.
  const moodleBaseUrl = '<?php echo $moodlebaseurl; ?>';
  
  // Common categorical keys you likely have; only rendered if present in data.
  const COMMON_CATS = [];

  let currentSort = {field: null, direction: 'asc'};
  let currentlyOpenDetails = null; // Track currently open details to close them when opening new ones

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

  // Helpers.
  function isNumber(x){ return typeof x === 'number' && Number.isFinite(x); }
  
  function buildHeader() {
    const table = document.createElement('table');
    table.className = 'table table-striped table-hover table-sm';
    
    const thead = document.createElement('thead');
    thead.className = 'table-dark sticky-top';
    const headerRow = document.createElement('tr');
    
    // Name column.
    const nameCol = document.createElement('th');
    nameCol.id = 'sortName';
    nameCol.style.cursor = 'pointer';
    nameCol.textContent = 'Name ↕';
    nameCol.className = 'user-select-none';
    
    // Actions column (moved between Name and Category).
    const actionsCol = document.createElement('th');
    actionsCol.textContent = 'Actions';
    actionsCol.style.width = '280px';
    
    // Category column.
    const categoryCol = document.createElement('th');
    categoryCol.id = 'sortCategory';
    categoryCol.style.cursor = 'pointer';
    categoryCol.textContent = 'Category ↕';
    categoryCol.className = 'user-select-none';

    // Tags column.
    const tagsCol = document.createElement('th');
    tagsCol.id = 'sortTags';
    tagsCol.style.cursor = 'pointer';
    tagsCol.textContent = 'Tags ↕';
    tagsCol.className = 'user-select-none';
    tagsCol.style.width = '200px';

    headerRow.appendChild(nameCol);
    headerRow.appendChild(actionsCol);
    headerRow.appendChild(categoryCol);
    headerRow.appendChild(tagsCol);
    
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

    // Name cell.
    const nameCell = document.createElement('td');
    nameCell.textContent = q.name ?? '';
    nameCell.className = 'text-truncate';
    nameCell.style.maxWidth = '300px';

    // Actions cell with smaller buttons.
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

    // Tags cell
    const tagsCell = document.createElement('td');
    const tagText = Array.isArray(q.tags) && q.tags.length > 0 ? q.tags.join(', ') : '';
    tagsCell.textContent = tagText;
    tagsCell.className = 'text-muted small text-truncate';
    tagsCell.style.maxWidth = '200px';
    tagsCell.title = tagText; // Show full tags on hover

    // Assemble the row in the new order: Name, Actions, Category, Tags
    row.appendChild(nameCell);
    row.appendChild(actionsCell);
    row.appendChild(categoryCell);
    row.appendChild(tagsCell);

    let openType = null; // 'json' | 'question' | 'answer'
    let detailRow = null;

    function closeDetails() {
      // Helper function to close this row's details
      openType = null;
      questionBtn.textContent = 'Question';
      answerBtn.textContent = 'Answer';
      jsonBtn.textContent = 'JSON';
      // Remove highlighting from all buttons
      questionBtn.classList.remove('qbrowser-btn-active');
      answerBtn.classList.remove('qbrowser-btn-active');
      jsonBtn.classList.remove('qbrowser-btn-active');
      if (detailRow) {
        detailRow.remove();
        detailRow = null;
      }
      // Clear global tracking if this was the currently open detail
      if (currentlyOpenDetails === closeDetails) {
        currentlyOpenDetails = null;
      }
    }

    function toggleDisplay(type, content, isHTML = false) {
      if (openType === type) {
        // Close current detail
        closeDetails();
      } else {
        // Close any other currently open details first
        if (currentlyOpenDetails && currentlyOpenDetails !== closeDetails) {
          currentlyOpenDetails();
        }

        // Close existing detail if any (in same row)
        if (detailRow) {
          detailRow.remove();
        }

        // Update button states and highlighting
        openType = type;
        questionBtn.textContent = type === 'question' ? 'Close' : 'Question';
        answerBtn.textContent = type === 'answer' ? 'Close' : 'Answer';
        jsonBtn.textContent = type === 'json' ? 'Close' : 'JSON';

        // Remove highlighting from all buttons, then add to active one
        questionBtn.classList.remove('qbrowser-btn-active');
        answerBtn.classList.remove('qbrowser-btn-active');
        jsonBtn.classList.remove('qbrowser-btn-active');

        if (type === 'question') questionBtn.classList.add('qbrowser-btn-active');
        else if (type === 'answer') answerBtn.classList.add('qbrowser-btn-active');
        else if (type === 'json') jsonBtn.classList.add('qbrowser-btn-active');

        // Create new detail row
        detailRow = document.createElement('tr');
        const detailCell = document.createElement('td');
        detailCell.colSpan = 4; // Name, Actions, Category, Tags

        const detail = document.createElement('div');
        detail.className = isHTML ? 'qbrowser-detail html-content' : 'qbrowser-detail code-content';
        if (isHTML) detail.innerHTML = content;
        else        detail.textContent = content;

        detailCell.appendChild(detail);
        detailRow.appendChild(detailCell);

        // Insert detail row after current row
        row.insertAdjacentElement('afterend', detailRow);

        // Track this as the currently open detail
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
      // Toggle direction if same field
      currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
      // New field, start with ascending
      currentSort.field = field;
      currentSort.direction = 'asc';
    }

    // Sorting with special handling for tags array
    viewData.sort((a, b) => {
      let aVal, bVal;
      if (field === 'tags') {
        // For tags, join array and sort
        aVal = (Array.isArray(a[field]) ? a[field].join(', ') : '').toLowerCase();
        bVal = (Array.isArray(b[field]) ? b[field].join(', ') : '').toLowerCase();
      } else {
        // Standard field sorting
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
    // Update header to show sort direction
    const sortName = document.getElementById('sortName');
    const sortCategory = document.getElementById('sortCategory');
    const sortTags = document.getElementById('sortTags');

    [sortName, sortCategory, sortTags].forEach(header => {
      if (!header) return;
      let field;
      if (header.id === 'sortName') field = 'name';
      else if (header.id === 'sortCategory') field = 'category';
      else if (header.id === 'sortTags') field = 'tags';

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
    document.getElementById('sortTags')?.addEventListener('click', () => sortBy('tags'));
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
      
      // Get unique values from data and sort alphabetically
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
        // Standard exact match for categorical filters
        out = out.filter(o => (o[key] ?? '') === val);
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
