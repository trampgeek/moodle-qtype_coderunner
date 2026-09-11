<?php

// This file is part of CodeRunner - http://coderunner.org.nz/
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
 * Renders the CodeRunner in-plugin documentation pages from Markdown.
 *
 * @author     Michal Skraburski
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib/docslib.php');

use core\exception\moodle_exception;

// Explicit globals so this still works when included from inside a function
// scope (e.g. a PHPUnit test), not just when run as the top-level script.
global $PAGE, $OUTPUT, $CFG;

$docsdir = qtype_coderunner_docs_dir();
$page = optional_param('page', 'index.md', PARAM_PATH);
$file = realpath($docsdir . '/' . $page);

// realpath() returns false for a missing file; the strpos check refuses
// anything that resolves outside the documentation directory (traversal).
if (!$file || strpos($file, realpath($docsdir)) !== 0) {
    throw new moodle_exception('invalidparameter', 'error');
}

// jpg/xml are streamed verbatim. The headers_sent() guard keeps these paths
// includable from the PHPUnit tests, which have already emitted output.
$ext = pathinfo($file, PATHINFO_EXTENSION);
if ($ext == 'jpg') {
    if (!headers_sent()) {
        header('Content-Type: image/jpeg');
    }
    readfile($file);
    return;
}
if ($ext == 'xml') {
    if (!headers_sent()) {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    }
    readfile($file);
    return;
}

$PAGE->set_url('/question/type/coderunner/docs.php', ['page' => $page]);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('CodeRunner Documentation');
// Inject CSS; \@imports do not work on ./styles.css.
$PAGE->requires->css(new moodle_url('/question/type/coderunner/docs/editor/styles/docs.css'));
$PAGE->requires->js_call_amd('qtype_coderunner/docssearch', 'init', [
    (new moodle_url('/question/type/coderunner/docs_searchindex.php'))->out(false),
    (new moodle_url('/question/type/coderunner/docs.php'))->out(false),
]);

echo $OUTPUT->header();

// Documentation search: a bar docked at the top of the page that opens into a
// centered modal palette (click it or press Ctrl+K); see amd/src/docssearch.js.
echo '<button type="button" id="coderunner-docs-search-trigger" class="docs-search-trigger"'
    . ' aria-label="Search the documentation">'
    . '<span class="docs-search-trigger-label">Search the docs…</span>'
    . '<kbd class="docs-search-kbd">Ctrl K</kbd>'
    . '</button>';
echo '<div id="coderunner-docs-search" class="docs-search" role="dialog" aria-modal="true"'
    . ' aria-label="Search the documentation" aria-hidden="true">'
    . '<div class="docs-search-panel">'
    . '<input type="search" class="docs-search-input" autocomplete="off" spellcheck="false"'
    . ' placeholder="Search the docs…" aria-label="Search the documentation" role="combobox"'
    . ' aria-expanded="false" aria-controls="docs-search-results" aria-autocomplete="list">'
    . '<div id="docs-search-results" class="docs-search-results" role="listbox" aria-label="Search results"></div>'
    . '</div>'
    . '<div class="docs-search-status" aria-live="polite"></div>'
    . '</div>';

$contents = qtype_coderunner_docs_expand_example_list(file_get_contents($file), $docsdir);
$html = qtype_coderunner_docs_transform(qtype_coderunner_docs_markdown(), $contents);
echo qtype_coderunner_docs_wrap_sections($html);
echo $OUTPUT->footer();
