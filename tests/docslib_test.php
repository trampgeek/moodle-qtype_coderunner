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
 * Unit tests for the documentation helper library (lib/docslib.php).
 * @group qtype_coderunner
 *
 * @package    qtype_coderunner
 * @copyright  2026 Michal Skraburski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/lib/docslib.php');

class docslib_test extends \advanced_testcase {

    public function test_slug(): void {
        $this->assertSame('twig-escapers', qtype_coderunner_docs_slug('Twig Escapers'));
        $this->assertSame('hello-world', qtype_coderunner_docs_slug('Hello, World!'));
        $this->assertSame('grading', qtype_coderunner_docs_slug('  Grading!  '));
        $this->assertSame('the-e-c-escaper', qtype_coderunner_docs_slug("The e('c') escaper"));
        // A heading with no alphanumerics collapses to the empty string.
        $this->assertSame('', qtype_coderunner_docs_slug('!!!'));
    }

    public function test_markdown_returns_configured_parser(): void {
        $md = qtype_coderunner_docs_markdown();
        $this->assertInstanceOf(\Michelf\MarkdownExtra::class, $md);
        // The configured header_id_func must produce the slug anchor.
        $html = qtype_coderunner_docs_transform($md, "# Hello World\n");
        $this->assertStringContainsString('id="hello-world"', $html);
    }

    public function test_dir_points_at_docs(): void {
        $dir = qtype_coderunner_docs_dir();
        $this->assertStringEndsWith('/docs/editor/', $dir);
        $this->assertDirectoryExists($dir);
        $this->assertFileExists($dir . 'index.md');
    }

    public function test_pages_lists_markdown_slugs(): void {
        $pages = qtype_coderunner_docs_pages(qtype_coderunner_docs_dir());
        $this->assertContains('index', $pages);
        $this->assertContains('templating', $pages);
        $this->assertContains('example_questions', $pages);
        // Slugs carry no extension.
        foreach ($pages as $slug) {
            $this->assertStringEndsNotWith('.md', $slug);
        }
        // The list is sorted.
        $sorted = $pages;
        sort($sorted);
        $this->assertSame($sorted, $pages);
    }

    public function test_transform_produces_clean_html(): void {
        // A plain heading would trigger a PHP 8.1 deprecation inside
        // MarkdownExtra; the helper suppresses it, so under PHPUnit (which
        // promotes notices to failures) this returning cleanly is the check.
        $html = qtype_coderunner_docs_transform(qtype_coderunner_docs_markdown(), "## A Heading\n\nBody.\n");
        $this->assertStringContainsString('<h2 id="a-heading">A Heading</h2>', $html);
    }

    public function test_wrap_sections_wraps_each_heading(): void {
        $html = '<h2 id="a">A</h2><p>alpha</p><h3 id="b">B</h3><p>beta</p>';
        $wrapped = qtype_coderunner_docs_wrap_sections($html);
        $this->assertSame(2, substr_count($wrapped, 'class="doc-section"'));
        $this->assertStringContainsString('<div class="doc-section" data-anchor="a"><h2 id="a">A</h2>', $wrapped);
        $this->assertStringContainsString('<div class="doc-section" data-anchor="b"><h3 id="b">B</h3>', $wrapped);
        // Content stays with its heading, up to the next heading.
        $this->assertStringContainsString('<p>alpha</p></div>', $wrapped);
    }

    public function test_wrap_sections_leaves_preamble_unwrapped(): void {
        $html = '<p>intro</p><h2 id="a">A</h2><p>body</p>';
        $wrapped = qtype_coderunner_docs_wrap_sections($html);
        $this->assertStringStartsWith('<p>intro</p>', $wrapped);
        $this->assertSame(1, substr_count($wrapped, 'class="doc-section"'));
    }

    public function test_expand_example_list(): void {
        $dir = qtype_coderunner_docs_dir();
        $expanded = qtype_coderunner_docs_expand_example_list(
            "# Examples\n\n<!-- EXAMPLE_QUESTIONS_LIST -->\n",
            $dir
        );
        $this->assertStringNotContainsString('EXAMPLE_QUESTIONS_LIST', $expanded);
        $this->assertStringContainsString('import_example.php?slug=', $expanded);
        // Content without the marker is returned unchanged.
        $this->assertSame('# no marker', qtype_coderunner_docs_expand_example_list('# no marker', $dir));
    }

    public function test_search_index_structure(): void {
        $dir = qtype_coderunner_docs_dir();
        $index = qtype_coderunner_docs_search_index($dir, qtype_coderunner_docs_pages($dir));
        $this->assertNotEmpty($index);
        foreach ($index as $entry) {
            $this->assertArrayHasKey('page', $entry);
            $this->assertArrayHasKey('anchor', $entry);
            $this->assertArrayHasKey('heading', $entry);
            $this->assertArrayHasKey('text', $entry);
            $this->assertStringEndsWith('.md', $entry['page']);
            $this->assertNotSame('', $entry['anchor']);
            $this->assertNotSame('', $entry['heading']);
        }
    }
}
