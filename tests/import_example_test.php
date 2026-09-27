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
 * Unit tests for the example-question import page (import_example.php).
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use core\exception\moodle_exception;

defined("MOODLE_INTERNAL") || die();

class import_example_test extends \advanced_testcase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * import_example.php is a plain script, like docs.php, so we fake the
     * request via the superglobals (read by required_param/optional_param)
     * and capture whatever it echoes with output buffering.
     * @param array $get contents for $_GET.
     * @param array $post contents for $_POST.
     * @return string the captured page output.
     */
    private function run_import_script(array $get, array $post = []): string
    {
        global $CFG;
        $_GET = $get;
        $_POST = $post;
        ob_start();
        try {
            include $CFG->dirroot . "/question/type/coderunner/import_example.php";
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Make a course plus a user enrolled in it with the given role.
     * @return array [course, user]
     */
    private function make_course_and_user(string $role): array
    {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(["fullname" => "Import Test Course"]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, $role);
        return [$course, $user];
    }

    public function test_course_chooser_lists_editable_courses()
    {
        [$course, $teacher] = $this->make_course_and_user("editingteacher");
        $this->setUser($teacher);
        $output = $this->run_import_script(["slug" => "01-hello-world"]);
        $this->assertStringContainsString("Import example: 01 hello world", $output);
        $this->assertStringContainsString($course->fullname, $output);
    }

    public function test_course_chooser_warns_without_editable_courses()
    {
        // Enrolled as student only: no course offers moodle/question:add.
        [, $student] = $this->make_course_and_user("student");
        $this->setUser($student);
        $output = $this->run_import_script(["slug" => "01-hello-world"]);
        $this->assertStringContainsString("not able to add questions to any course", $output);
    }

    public function test_import_creates_questions_in_course_category()
    {
        global $DB;
        [$course, $teacher] = $this->make_course_and_user("editingteacher");
        $this->setUser($teacher);
        try {
            $this->run_import_script(["slug" => "01-hello-world"], [
                "qtype_coderunner_import_courseid" => $course->id,
                "sesskey" => sesskey(),
            ]);
            $this->fail("Expected the success redirect to the question bank");
        } catch (moodle_exception $e) {
            // redirect() throws in CLI scripts rather than redirecting.
            $this->assertSame("redirecterrordetected", $e->errorcode);
        }
        $coursecontext = \context_course::instance($course->id);
        $category = $DB->get_record(
            "question_categories",
            ["contextid" => $coursecontext->id, "name" => "CodeRunner examples"]
        );
        $this->assertNotFalse($category, "Expected a 'CodeRunner examples' category");
        $this->assertTrue(
            $DB->record_exists("question", ["name" => "Hello, World!"]),
            "Expected the example question to have been imported"
        );
    }

    public function test_import_denied_without_capability()
    {
        // A student in the course must not be able to import even with a
        // valid sesskey and course id (e.g. a hand-crafted POST).
        [$course, $student] = $this->make_course_and_user("student");
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        $this->run_import_script(["slug" => "01-hello-world"], [
            "qtype_coderunner_import_courseid" => $course->id,
            "sesskey" => sesskey(),
        ]);
    }

    public function test_import_denied_without_sesskey()
    {
        [$course, $teacher] = $this->make_course_and_user("editingteacher");
        $this->setUser($teacher);
        try {
            $this->run_import_script(["slug" => "01-hello-world"], [
                "qtype_coderunner_import_courseid" => $course->id,
            ]);
            $this->fail("Expected a sesskey failure");
        } catch (moodle_exception $e) {
            // A missing sesskey is refused either as 'invalidsesskey' or, on
            // newer Moodle, 'missingparam'; both mean the request was rejected.
            $this->assertContains($e->errorcode, ["invalidsesskey", "missingparam"], $e->getMessage());
        }
    }

    public function test_bad_slug_rejected()
    {
        [, $teacher] = $this->make_course_and_user("editingteacher");
        $this->setUser($teacher);
        $this->expectException(moodle_exception::class);
        $this->run_import_script(["slug" => "does-not-exist"]);
    }

    public function test_import_reuses_existing_examples_category()
    {
        global $DB;
        [$course, $teacher] = $this->make_course_and_user("editingteacher");
        $this->setUser($teacher);
        // Import the same example twice. The docs warn this makes duplicates,
        // but the "CodeRunner examples" category must be created once and then
        // reused (exercising the !$category false branch on the second run).
        foreach ([1, 2] as $unused) {
            try {
                $this->run_import_script(["slug" => "01-hello-world"], [
                    "qtype_coderunner_import_courseid" => $course->id,
                    "sesskey" => sesskey(),
                ]);
                $this->fail("Expected the success redirect to the question bank");
            } catch (moodle_exception $e) {
                $this->assertSame("redirecterrordetected", $e->errorcode);
            }
        }
        $coursecontext = \context_course::instance($course->id);
        $categories = $DB->get_records(
            "question_categories",
            ["contextid" => $coursecontext->id, "name" => "CodeRunner examples"]
        );
        $this->assertCount(1, $categories, "Expected exactly one examples category after two imports");
        // Two imports of a one-question file must leave two question copies.
        $this->assertSame(
            2,
            $DB->count_records("question", ["name" => "Hello, World!"]),
            "Expected the example question to have been imported twice"
        );
    }

    public function test_import_failure_reports_and_imports_nothing()
    {
        global $CFG, $DB;
        [$course, $teacher] = $this->make_course_and_user("editingteacher");
        $this->setUser($teacher);

        // Drop a temporary example file into the examples dir so the realpath
        // guard accepts the slug, but give it a question of a non-existent type
        // so the XML importer rejects it. This is well-formed XML (it parses)
        // yet fails to import, exercising the "Import failed" branch without
        // depending on how a parse error would surface across Moodle versions.
        $slug = "zz-behat-broken";
        $brokenfile = $CFG->dirroot .
            "/question/type/coderunner/docs/editor/example_questions/{$slug}.xml";
        $brokenxml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<quiz>
  <question type="qtype_that_does_not_exist">
    <name><text>Broken example</text></name>
    <questiontext format="html"><text>nothing</text></questiontext>
  </question>
</quiz>
XML;
        if (@file_put_contents($brokenfile, $brokenxml) === false) {
            $this->markTestSkipped("Examples directory is not writable for the failure-path test");
        }
        try {
            // A failed import must not throw the success redirect. Tolerate a
            // thrown error too, in case a future Moodle surfaces the failure
            // that way, but never accept the redirecterrordetected of success.
            $threw = false;
            try {
                $output = $this->run_import_script(["slug" => $slug], [
                    "qtype_coderunner_import_courseid" => $course->id,
                    "sesskey" => sesskey(),
                ]);
            } catch (\Throwable $e) {
                $threw = true;
                // Only the success path throws redirect with this errorcode.
                if ($e instanceof moodle_exception) {
                    $this->assertNotSame("redirecterrordetected", $e->errorcode, $e->getMessage());
                }
            }
            if (!$threw) {
                $this->assertStringContainsString("Import failed", $output);
            }
            // The category is created before the import runs, so it may exist;
            // what matters is that the broken question was not created.
            $this->assertFalse(
                $DB->record_exists("question", ["name" => "Broken example"]),
                "A broken import must not create any question"
            );
        } finally {
            @unlink($brokenfile);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_import_rejected_on_mod_qbank_site()
    {
        // On a mod_qbank site (Moodle 4.6+) the course-context import path no
        // longer applies, so the script must refuse rather than import into the
        // wrong place. Fake mod_qbank's presence by defining the class the
        // detector checks for. Runs in a separate process because a class
        // definition cannot be undone and would leak into other tests.
        if (!class_exists("mod_qbank\\task\\transfer_question_categories")) {
            eval("namespace mod_qbank\\task; class transfer_question_categories {}");
        }
        [, $teacher] = $this->make_course_and_user("editingteacher");
        $this->setUser($teacher);
        $this->expectException(moodle_exception::class);
        $this->run_import_script(["slug" => "01-hello-world"]);
    }
}
