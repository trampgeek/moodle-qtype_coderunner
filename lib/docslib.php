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
 * Shared helpers for the CodeRunner in-plugin documentation, used by both
 * docs.php (the page renderer) and docs_searchindex.php (the search endpoint).
 *
 * @package    qtype_coderunner
 * @author     Michal Skraburski
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/markdown/MarkdownInterface.php');
require_once($CFG->libdir . '/markdown/Markdown.php');
require_once($CFG->libdir . '/markdown/MarkdownExtra.php');

/**
 * Absolute path to the documentation markdown directory (with trailing slash).
 * @return string
 */
function qtype_coderunner_docs_dir() {
    // This library lives in the plugin's lib/ directory, so the docs are one
    // level up from here.
    return dirname(__DIR__) . '/docs/editor/';
}

/**
 * Slugify a heading into an anchor id (used as MarkdownExtra's header_id_func).
 * @param string $headervalue the heading text.
 * @return string
 */
function qtype_coderunner_docs_slug($headervalue) {
    $slug = strtolower(trim($headervalue));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * A MarkdownExtra parser configured to emit heading anchor ids. A fresh
 * instance per page keeps heading-id numbering independent between pages.
 * @return \Michelf\MarkdownExtra
 */
function qtype_coderunner_docs_markdown() {
    $md = new \Michelf\MarkdownExtra();
    $md->header_id_func = 'qtype_coderunner_docs_slug';
    return $md;
}

/**
 * List every documentation page slug (recursively), without the .md extension.
 * @param string $docsdir
 * @return string[]
 */
function qtype_coderunner_docs_pages($docsdir) {
    $pages = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsdir)) as $f) {
        if ($f->getExtension() === 'md') {
            $slug = ltrim(str_replace($docsdir, '', $f->getPathname()), '/');
            $pages[] = substr($slug, 0, -3);
        }
    }
    sort($pages);
    return $pages;
}

/**
 * Transform Markdown to HTML, silencing the PHP 8.1 deprecation notice that
 * MarkdownExtra emits for headings without an explicit {#id}/{.class} block.
 * @param \Michelf\MarkdownExtra $md
 * @param string $contents
 * @return string
 */
function qtype_coderunner_docs_transform($md, $contents) {
    $previous = error_reporting(E_ALL & ~E_DEPRECATED);
    $html = $md->transform($contents);
    error_reporting($previous);
    return $html;
}

/**
 * Replace the example-questions list marker in a page's Markdown with an
 * auto-generated list of the XML exports, each with an import and a download
 * link. Dropping a new .xml into the directory is all that is needed.
 * @param string $contents raw Markdown.
 * @param string $docsdir
 * @return string
 */
function qtype_coderunner_docs_expand_example_list($contents, $docsdir) {
    $marker = '<!-- EXAMPLE_QUESTIONS_LIST -->';
    if (strpos($contents, $marker) === false) {
        return $contents;
    }
    $examplefiles = glob($docsdir . '/example_questions/*.xml') ?: [];
    sort($examplefiles);
    if ($examplefiles) {
        $list = '';
        foreach ($examplefiles as $examplefile) {
            $filename = basename($examplefile);
            $slug = substr($filename, 0, -4);
            $title = ucfirst(str_replace(['-', '_'], ' ', $slug));
            $list .= "- [$title](import_example.php?slug=" . rawurlencode($slug) . ")"
                . "   -   [download](docs.php?page=example_questions/" . rawurlencode($filename) . ")\n";
        }
    } else {
        $list = '*No example questions have been added yet.*';
    }
    return str_replace($marker, $list, $contents);
}

/**
 * Wrap each heading and the content beneath it (up to the next heading of any
 * level) in a section div; content before the first heading is left unwrapped.
 * @param string $html rendered HTML.
 * @return string
 */
function qtype_coderunner_docs_wrap_sections($html) {
    $out = '';
    foreach (preg_split('/(?=<h[1-6][\s>])/i', $html) as $section) {
        if ($section === '') {
            continue;
        }
        if (preg_match('/^<h[1-6][^>]*\bid="([^"]*)"/i', $section, $m)) {
            $out .= '<div class="doc-section" id="'. s($m[1]) . '-div" data-anchor="' . s($m[1]) . '">' . $section . '</div>';
        } else if (preg_match('/^<h[1-6][\s>]/i', $section)) {
            $out .= '<div class="doc-section">' . $section . '</div>';
        } else {
            $out .= $section;
        }
    }
    return $out;
}

/**
 * Build the client-side search index: one entry per documentation section, as
 * {page, anchor, heading, context, text}. Each entry keeps its own heading and
 * anchor (so results jump to the exact subsection), plus `context`: the chain
 * of enclosing shallower headings, outermost first, so a sub-subheading can be
 * shown grouped under its parent heading(s) and page. Uses the same transform +
 * split as the rendered pages so the anchors match exactly.
 * @param string $docsdir
 * @param string[] $pages page slugs (without .md).
 * @return array
 */
function qtype_coderunner_docs_search_index($docsdir, $pages) {
    $index = [];
    foreach ($pages as $pageslug) {
        $pagefile = $docsdir . $pageslug . '.md';
        if (!is_file($pagefile)) {
            continue;
        }
        $html = qtype_coderunner_docs_transform(qtype_coderunner_docs_markdown(), file_get_contents($pagefile));
        // Track the currently-open headings by level so each entry can carry
        // the breadcrumb of its shallower ancestors.
        $stack = [];
        foreach (preg_split('/(?=<h[1-6][\s>])/i', $html) as $section) {
            if ($section === '' || !preg_match('/^<h([1-6])[^>]*\bid="([^"]*)"/i', $section, $m)) {
                continue;
            }
            $level = (int) $m[1];
            preg_match('/^<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $section, $hm);
            $heading = trim(html_entity_decode(strip_tags($hm[1] ?? '')));
            // Ancestors are the open headings shallower than this one (the
            // stack is kept sorted by level, so this is outermost first).
            $context = [];
            foreach ($stack as $openlevel => $openheading) {
                if ($openlevel < $level) {
                    $context[] = $openheading;
                }
            }
            // This heading closes any open heading at its own level or deeper.
            foreach (array_keys($stack) as $openlevel) {
                if ($openlevel >= $level) {
                    unset($stack[$openlevel]);
                }
            }
            $stack[$level] = $heading;
            ksort($stack);
            $index[] = [
                'page' => $pageslug . '.md',
                'anchor' => $m[2],
                'heading' => $heading,
                'context' => $context,
                'text' => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($section)))),
            ];
        }
    }
    return $index;
}
