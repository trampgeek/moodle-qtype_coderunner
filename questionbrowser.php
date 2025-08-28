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
            if (preg_match('/\{.*:.*\}/', $line)) {
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

?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Moodle CodeRunner Question Browser</title>
<style>
  :root {
    --bg: #0b1020;
    --panel: #121a33;
    --muted: #7081b9;
    --text: #e8ecff;
    --accent: #7aa2ff;
    --accent-2: #65d6ad;
    --danger: #ff7a7a;
    --border: #223058;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial;
    background: radial-gradient(1200px 800px at 70% -10%, #1e2a5a 0%, var(--bg) 45%, #070b18 100%);
    color: var(--text);
  }
  header {
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.0));
    position: sticky; top: 0; z-index: 10;
  }
  header h1 { font-size: 18px; margin: 0; letter-spacing: 0.3px; font-weight: 650; }
  .pill {
    padding: 6px 10px; border: 1px solid var(--border); border-radius: 10px; color: var(--muted);
    background: #0e1630; display: inline-flex; gap: 8px; align-items: center;
  }
  main {
    display: grid; grid-template-columns: 340px 1fr; gap: 16px; padding: 16px;
  }
  @media (max-width: 1000px) { main { grid-template-columns: 1fr; } }

  .card {
    background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.0));
    border: 1px solid var(--border); border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);
  }
  .card h2 { font-size: 14px; font-weight: 650; margin: 0 0 8px 0; color: var(--muted); letter-spacing: 0.3px; }
  .panel { padding: 14px; }
  .grid { display: grid; gap: 10px; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  label { font-size: 12px; color: var(--muted); }
  input[type="text"], input[type="number"], select {
    width: 100%; padding: 8px 9px; border-radius: 10px; border: 1px solid var(--border);
    background: #0f1732; color: var(--text); outline: none;
  }
  input[type="file"] { color: var(--muted); }
  button {
    padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border);
    background: #11204a; color: var(--text); cursor: pointer; letter-spacing: 0.2px;
  }
  button.primary { background: linear-gradient(180deg, #3758ff, #2b48d8); border-color: #3f59d4; }
  button.ghost { background: #0f1732; }
  button.success { background: linear-gradient(180deg, #31c590, #27a77a); border-color: #279a72; }
  button:disabled { opacity: 0.6; cursor: not-allowed; }

  .drop {
    padding: 14px; border: 1px dashed var(--border); border-radius: 12px; text-align: center; color: var(--muted);
    background: rgba(255,255,255,0.02);
  }
  .drop.dragover { border-color: var(--accent); color: var(--accent); }

  .resultsHeader { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
  .count { color: var(--muted); font-size: 12px; }
  .list {
    max-height: calc(100vh - 230px); overflow: auto; border-top-left-radius: 0; border-top-right-radius: 0;
  }

  /* Parent grid defines all columns for every row */
  .list {
    max-height: calc(100vh - 230px);
    overflow: auto;
    display: grid;
    grid-template-columns: 1fr max-content max-content max-content; /* Name flexes, Category/Stage/Buttons hug */
    column-gap: 10px;
    align-items: center;
    }

    /* Each "row" is only a grouping hook; its children become grid items */
    .row { display: contents; }
    

    /* Cells */
    .list .row > .name,
    .list .row > .category,
    .list .row > .mono,
    .list .row > .controls {
    padding: 10px 12px;
    }

    /* Keep previous look-and-feel */
    .name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .category { color: var(--muted); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size: 12px; color: #c7d2ff; }

    /* Zebra striping for data rows */
    .list .row:not(.header):nth-of-type(odd) > .name,
    .list .row:not(.header):nth-of-type(odd) > .category,
    .list .row:not(.header):nth-of-type(odd) > .mono,
    .list .row:not(.header):nth-of-type(odd) > .controls {
    background: rgba(255,255,255,0.02);
    }

    /* Sticky header inside the scrollable list */
    .list .row.header > .name,
    .list .row.header > .category,
    .list .row.header > .mono,
    .list .row.header > .controls {
    font-weight: 650;
    border-bottom: 2px solid #26376f;
    background: #0e1630;
    position: sticky;
    top: 0;
    z-index: 2;
    color: var(--text);
    font-size: inherit;
    }

    /* Full-width rows for details */
    .detail {
    grid-column: 1 / -1;   /* span all columns */
    }

    .controls {
    display: flex;
    gap: 4px;
    }
    
    .controls button {
    padding: 4px 6px;
    font-size: 10px;
    border-radius: 6px;
    }

    .detail {
    background: #0c1329;
    border: 1px solid #1b2952;
    border-radius: 10px;
    padding: 10px;
    margin: 8px 12px;
    font-size: 13px;
    color: #cfe3ff;
    line-height: 1.4;
    }
    
    /* Code/JSON content should be monospace with pre-wrap */
    .detail.code-content {
    white-space: pre-wrap;
    font-family: ui-monospace, monospace;
    font-size: 12px;
    }
    
    /* Question HTML content should render normally */
    .detail.html-content {
    font-family: inherit;
    }
    
    /* Style common HTML elements in question text */
    .detail.html-content code {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px;
    padding: 2px 6px;
    font-family: ui-monospace, monospace;
    font-size: 11px;
    color: #e8f4ff;
    }
    
    .detail.html-content p {
    margin: 0 0 10px 0;
    }
    
    .detail.html-content ul, .detail.html-content ol {
    margin: 0 0 10px 20px;
    }
    
    .detail.html-content li {
    margin: 0 0 4px 0;
    }
    
    .detail.html-content pre {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    padding: 8px;
    margin: 8px 0;
    overflow-x: auto;
    font-size: 12px;
    }
    
    .detail.html-content strong, .detail.html-content b {
    color: #ffffff;
    font-weight: 600;
    }
  .rowItem .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size: 12px; color: #c7d2ff; }
  .rowItem .name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .rowItem .category { color: var(--muted); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .rowItem button { padding: 6px 8px; font-size: 12px; }
  .toolbar { display: flex; gap: 8px; flex-wrap: wrap; }
  .sep { height: 1px; background: var(--border); margin: 10px 0; }
  .hint { font-size: 12px; color: var(--muted); }
</style>
</head>
<body>
  <header>
    <h1>Question Browser - <?php echo htmlspecialchars($coursename); ?></h1>
  </header>

  <main>
    <!-- LEFT: FILTERS -->
    <section class="card">
      <div class="panel grid">
        <h2>Data</h2>
        <div id="loadStatus">Loaded <?php echo count($questions); ?> questions</div>

        <div class="sep"></div>

        <h2>Text filter</h2>
        <div class="grid">
          <div class="row">
            <label style="min-width:70px">Search</label>
            <input type="text" id="kw" placeholder="substring or regex" />
          </div>
          <div class="row">
            <label style="min-width:70px">Mode</label>
            <select id="kwMode">
              <option>Include</option>
              <option>Exclude</option>
            </select>
            <label style="min-width:70px">Field</label>
            <select id="kwField">
              <option>Any</option>
            </select>
          </div>
          <div class="row">
            <label style="min-width:70px">Type</label>
            <select id="kwType">
              <option>Text</option>
              <option>Regex</option>
            </select>
            <label style="min-width:70px"></label>
            <label style="font-size:11px; color: var(--muted);">Regex uses JavaScript syntax</label>
          </div>
        </div>

        <div class="sep"></div>

        <h2>Numeric filters</h2>
        <div id="numericFilters" class="grid"></div>

        <div class="sep"></div>

        <h2>Categorical filters</h2>
        <div id="categoricalFilters" class="grid"></div>

        <div class="sep"></div>

        <div class="toolbar">
          <button class="primary" id="apply">Apply Filters</button>
          <button class="ghost" id="clear">Clear</button>
          <span class="hint">Tip: filters apply to the loaded dataset; keyword search stacks with them.</span>
        </div>
      </div>
    </section>

    <!-- RIGHT: RESULTS -->
    <section class="card">
      <div class="panel">
        <div class="resultsHeader">
          <div class="row" style="gap: 12px;">
            <h2 style="margin:0">Results</h2>
            <span class="count" id="count">No data</span>
          </div>
          <div class="toolbar">
            <button id="exportJson" class="success" disabled>Export JSON</button>
            <button id="exportCsv" class="success" disabled>Export CSV</button>
          </div>
        </div>

        <div id="list" class="list">
        <!-- Header will be built dynamically -->
        </div>
      </div>
    </section>
  </main>

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
    const header = document.createElement('div');
    header.className = 'row header';
    
    const nameCol = document.createElement('div');
    nameCol.className = 'name';
    nameCol.id = 'sortName';
    nameCol.style.cursor = 'pointer';
    nameCol.textContent = 'Name ↕';
    
    const controlsCol = document.createElement('div');
    controlsCol.className = 'controls';
    controlsCol.textContent = 'Actions';
    
    const categoryCol = document.createElement('div');
    categoryCol.className = 'category';
    categoryCol.id = 'sortCategory';
    categoryCol.style.cursor = 'pointer';
    categoryCol.textContent = 'Category ↕';
    
    header.appendChild(nameCol);
    header.appendChild(controlsCol);
    header.appendChild(categoryCol);
    
    if (hasStageData) {
      const stageCol = document.createElement('div');
      stageCol.className = 'mono';
      stageCol.id = 'sortStage';
      stageCol.style.cursor = 'pointer';
      stageCol.textContent = 'Stage ↕';
      header.appendChild(stageCol);
    }
    
    return header;
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

  function summarizeRow(q, idx){
    const row = document.createElement('div');
    row.className = 'row';

    // cells
    const c1 = document.createElement('div'); c1.className = 'name';     c1.textContent = q.name ?? '';
    const c2 = document.createElement('div'); c2.className = 'category'; c2.textContent = q.category ?? '';
    const c3 = hasStageData ? document.createElement('div') : null;
    if (c3) {
      c3.className = 'mono';
      c3.textContent = (q.highest_stage ?? '');
    }

    // buttons
    const expBtn = document.createElement('button');     expBtn.className = 'ghost';   expBtn.textContent = 'JSON';
    const questionBtn = document.createElement('button');questionBtn.className = 'ghost'; questionBtn.textContent = 'Question';
    const answerBtn = document.createElement('button');  answerBtn.className = 'ghost'; answerBtn.textContent = 'Answer';
    const previewBtn = document.createElement('button'); previewBtn.className = 'ghost'; previewBtn.textContent = 'Preview';
    const bankBtn = document.createElement('button');    bankBtn.className = 'ghost';   bankBtn.textContent = 'View in Bank';

    const controls = document.createElement('div');
    controls.className = 'controls';
    controls.append(questionBtn, answerBtn, previewBtn, bankBtn, expBtn);

    // details wrapper: children become grid items (so .detail can span 1 / -1)
    const expWrap = document.createElement('div');
    expWrap.style.display = 'contents';

    let openType = null; // 'json' | 'question' | 'answer'

  function toggleDisplay(type, content, isHTML = false) {
    if (openType === type) {
    openType = null;
    expBtn.textContent = 'JSON';
    questionBtn.textContent = 'Question';
    answerBtn.textContent = 'Answer';
    // remove any open detail panels
    while (expWrap.lastChild && expWrap.lastChild.classList?.contains('detail')) {
        expWrap.removeChild(expWrap.lastChild);
    }
    } else {
    // close existing
    while (expWrap.lastChild && expWrap.lastChild.classList?.contains('detail')) {
        expWrap.removeChild(expWrap.lastChild);
    }
    openType = type;
    expBtn.textContent = type === 'json' ? 'Close' : 'JSON';
    questionBtn.textContent = type === 'question' ? 'Close' : 'Question';
    answerBtn.textContent = type === 'answer' ? 'Close' : 'Answer';

    const detail = document.createElement('div');
    detail.className = isHTML ? 'detail html-content' : 'detail code-content';
    if (isHTML) detail.innerHTML = content;
    else        detail.textContent = content;
    expWrap.appendChild(detail);
    }
  }

  expBtn.addEventListener('click', () => toggleDisplay('json', JSON.stringify(q, null, 2)));
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
      // Create question bank URL like bulk tester does
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

  // order matters: cells in grid order, then details (if opened)
  row.append(c1, controls, c2);
  if (c3) row.append(c3);
  row.append(expWrap);
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
    
    // Adjust grid layout based on available data
    if (hasStageData) {
      listEl.style.gridTemplateColumns = '1fr max-content max-content max-content'; // Name, Controls, Category, Stage
    } else {
      listEl.style.gridTemplateColumns = '1fr max-content max-content'; // Name, Controls, Category
    }
    
    // Build and add header
    const header = buildHeader();
    listEl.appendChild(header);

    const frag = document.createDocumentFragment();
    data.forEach((q, i) => frag.appendChild(summarizeRow(q, i)));
    listEl.appendChild(frag);

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
      const wrap = document.createElement('div'); wrap.className = 'grid2';
      const min = document.createElement('input'); min.type = 'number'; min.placeholder = `${k} min`; min.dataset.key = k; min.dataset.kind = 'min';
      const max = document.createElement('input'); max.type = 'number'; max.placeholder = `${k} max`; max.dataset.key = k; max.dataset.kind = 'max';
      const lab = document.createElement('label'); lab.textContent = k; lab.style.gridColumn = '1 / -1';
      const box = document.createElement('div'); box.className='grid'; box.append(lab, min, max);
      wrap.appendChild(box);
      numericFilters.appendChild(wrap);
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
      const wrap = document.createElement('div'); wrap.className = 'grid';
      const lab = document.createElement('label'); lab.textContent = k;
      const sel = document.createElement('select'); sel.dataset.key = k;
      
      let values;
      if (k === 'highest_stage') {
        // Use predefined teaching order for highest_stage, only include stages present in data
        const dataStages = new Set(data.map(o => o[k]).filter(v => typeof v === 'string' && v.trim() !== ''));
        values = STAGE_ORDER.filter(stage => dataStages.has(stage));
      } else {
        values = Array.from(new Set(data.map(o => o[k]).filter(v => typeof v === 'string' && v.trim() !== ''))).sort();
      }
      
      const empty = document.createElement('option'); empty.value = ''; empty.textContent = '(any)';
      sel.appendChild(empty);
      values.forEach(v => { const o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); });
      wrap.append(lab, sel);
      categoricalFilters.appendChild(wrap);
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
</body>
</html>