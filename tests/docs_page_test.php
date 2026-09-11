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
 * Unit tests for coderunner docs page.
 * @group qtype_coderunner
 * Assumed to be run after python questions have been tested, so focuses
 * only on C-specific aspects.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use core\exception\moodle_exception;
use PHPUnit\Framework\Testcase;

defined("MOODLE_INTERNAL") || die();

global $CFG;

class docs_page_test extends Testcase
{
    /**
     * docs.php is a plain script, not a function, so there's nothing to call
     * directly. Instead we fake the request (via $_GET, read by optional_param)
     * and capture whatever it echoes with output buffering.
     * @param string $page value of the 'page' request parameter.
     * @return string the captured page output.
     */
    private function render_docs_page(string $page): string
    {
        global $CFG;
        // Fresh page/output globals per render: $PAGE's state machine only
        // advances, so a second $OUTPUT->header() on the same page object
        // would throw once more than one page is rendered per test run.
        $GLOBALS["PAGE"] = new \moodle_page();
        $GLOBALS["OUTPUT"] = new \bootstrap_renderer();
        $_GET["page"] = $page;
        ob_start();
        try {
            include $CFG->dirroot . "/question/type/coderunner/docs.php";
            return ob_get_clean();
        } catch (\Throwable $e) {
            // docs.php throws before reaching the ob_get_clean() above, so the
            // buffer would otherwise stay open when this propagates out.
            ob_end_clean();
            throw $e;
        }
    }

    public function test_valid_page()
    {
        $output = $this->render_docs_page("index.md");
        $this->assertStringContainsString("Coderunner Question Editor", $output);
    }

    public function test_default_page_is_index()
    {
        global $CFG;
        // No 'page' parameter at all: docs.php must fall back to index.md,
        // since that's what the docs links on the author form produce.
        $GLOBALS["PAGE"] = new \moodle_page();
        $GLOBALS["OUTPUT"] = new \bootstrap_renderer();
        unset($_GET["page"]);
        ob_start();
        try {
            include $CFG->dirroot . "/question/type/coderunner/docs.php";
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $this->assertStringContainsString("Coderunner Question Editor", $output);
    }

    public function test_xml_page_is_served_as_download()
    {
        global $CFG;
        // .xml files under the docs dir are streamed verbatim (as a download)
        // rather than being rendered as markdown.
        $file = $CFG->dirroot .
            "/question/type/coderunner/docs/editor/example_questions/01-hello-world.xml";
        $output = $this->render_docs_page("example_questions/01-hello-world.xml");
        $this->assertSame(file_get_contents($file), $output);
    }

    public function test_examples_page_lists_examples()
    {
        $output = $this->render_docs_page("example_questions.md");
        // The <!-- EXAMPLE_QUESTIONS_LIST --> marker must be replaced by a
        // generated entry per xml file: an import link and a download link.
        $this->assertStringNotContainsString("EXAMPLE_QUESTIONS_LIST", $output);
        $this->assertStringContainsString("01 hello world", $output);
        $this->assertStringContainsString("import_example.php?slug=01-hello-world", $output);
        $this->assertStringContainsString(
            "docs.php?page=example_questions/01-hello-world.xml",
            $output
        );
    }

    public function test_walkthroughs_page()
    {
        $output = $this->render_docs_page("example_walkthroughs.md");
        $this->assertStringContainsString("Example Walkthroughs", $output);
        $this->assertStringContainsString("import_example.php?slug=01-hello-world", $output);
    }

    public function test_outofbounds_page()
    {
        $this->expectException(moodle_exception::class);
        // Two directories above docs/editor/ is the plugin root: escapes docsdir
        // but still points at a real file, so this also can't be caught by a
        // simple file_exists() check alone.
        $output = $this->render_docs_page("../../version.php");
        ob_end_flush();
    }

    public function test_nonexistent_page()
    {
        $this->expectException(moodle_exception::class);
        $output = $this->render_docs_page("does-not-exist");
        ob_end_flush();
    }
}
