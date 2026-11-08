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
 * Unit tests for the identity hash by which a restore matches questions.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_candidates;
use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/helper/restore_questions_parser_processor.class.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_qtype_plugin.class.php');
require_once($CFG->dirroot . '/question/type/coderunner/backup/moodle2/restore_qtype_coderunner_plugin.class.php');

/**
 * Tests that two descriptions of the same question hash alike, and that two
 * descriptions of different questions do not.
 *
 * This is the check the duplication bug never had. Moodle matches a backed-up
 * question against an existing one by a hash the question type supplies, and a
 * question that fails to match is **copied**. When CodeRunner supplied no hash
 * of its own, duplicating one quiz copied a whole question bank, and sites'
 * banks grew exponentially before it was fixed — one of them to 560,000
 * questions.
 *
 * So a hash that stops matching is silent: the restore succeeds and the bank
 * quietly doubles. Nothing here asserts that a restore completes; every test
 * asserts on the hash itself.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_qtype_coderunner_plugin::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class identity_hash_test extends \advanced_testcase {
    /** @var \stdClass the question under test, with its options. */
    private \stdClass $questiondata;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $repo = new prototype_repository();
        $repo->create(
            'python3',
            (int) SITEID,
            new override_set(['template' => "line one\nline two\n"], 'python3')
        );

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([
            'contextid' => \context_course::instance($course->id)->id,
        ]);
        $question = $generator->create_question('coderunner', 'sqr', [
            'category' => $category->id,
            'coderunnertype' => 'python3',
        ]);
        prototype_candidates::reset_caches();

        $this->questiondata = \question_bank::load_question_data($question->id);
    }

    /**
     * The question as the database side of a restore sees it.
     *
     * @return \stdClass the question, with its options loaded for export.
     */
    private function database_side(): \stdClass {
        $q = \question_bank::load_question_data($this->questiondata->id);
        $q->export_process = true;
        get_question_options($q);
        unset($q->export_process, $q->categoryobject, $q->questioncategoryid);
        return $q;
    }

    /**
     * The identity hash of a question, as restore_dbops computes it.
     *
     * @param \stdClass $questiondata the question.
     * @return string the hash.
     */
    private function hash(\stdClass $questiondata): string {
        return \restore_questions_parser_processor::generate_question_identity_hash($questiondata);
    }

    /**
     * Both CodeRunner 6 columns are excluded from the hash.
     *
     * `prototypeid` because a local id differs between sites; `overrides`
     * because a pre-6.0 backup has no such element, and the CodeRunner 5
     * columns beside it already carry the same content.
     */
    public function test_both_new_columns_are_excluded(): void {
        $method = new \ReflectionMethod(
            \restore_qtype_coderunner_plugin::class,
            'define_excluded_identity_hash_fields'
        );
        $excluded = $method->invoke(
            (new \ReflectionClass(\restore_qtype_coderunner_plugin::class))->newInstanceWithoutConstructor()
        );

        $this->assertContains('/options/prototypeid', $excluded);
        $this->assertContains('/options/overrides', $excluded);
    }

    /**
     * A pre-6.0 backup matches the question it describes, rather than being
     * restored as a copy of it.
     *
     * This is the case that matters: an administrator restoring last year's
     * course into an upgraded site. The backup has neither new column, because
     * neither existed when it was taken, while the question in the bank has
     * both.
     */
    public function test_a_pre_6_0_backup_matches_the_question_it_describes(): void {
        $indatabase = $this->database_side();

        $inbackup = $this->database_side();
        unset($inbackup->options->overrides, $inbackup->options->prototypeid);

        $this->assertSame(
            $this->hash($indatabase),
            $this->hash($inbackup),
            'A pre-6.0 backup does not match, so a restore would copy every question in it.'
        );
    }

    /**
     * The order of the keys in the overrides document does not change the hash.
     *
     * It would if the document were hashed: core reduces a question to a sorted
     * multiset of its *values*, which protects it from key order everywhere
     * except inside a value that is itself a document.
     */
    public function test_key_order_in_the_overrides_does_not_change_the_hash(): void {
        $overrides = json_decode($this->database_side()->options->overrides, true);
        $this->assertGreaterThan(1, count($overrides), 'Need several overrides for this to mean anything.');

        $reordered = $this->database_side();
        $reordered->options->overrides = json_encode(array_reverse($overrides, true));

        $this->assertSame($this->hash($this->database_side()), $this->hash($reordered));
    }

    /**
     * Line endings inside the overrides document do not change the hash.
     *
     * Core normalises CRLF, but only in a value that is text containing it. The
     * newlines inside a JSON document are escaped two-character sequences, so
     * the normalisation cannot reach them.
     */
    public function test_line_endings_in_the_overrides_do_not_change_the_hash(): void {
        $crlf = $this->database_side();
        $crlf->options->overrides = str_replace('\n', '\r\n', $crlf->options->overrides);
        $this->assertNotSame(
            $this->database_side()->options->overrides,
            $crlf->options->overrides,
            'The fixture needs newlines inside its overrides for this to mean anything.'
        );

        $this->assertSame($this->hash($this->database_side()), $this->hash($crlf));
    }

    /**
     * A question that really is different still hashes differently.
     *
     * The failure this guards against is the opposite one: excluding too much
     * would make every question match, and a restore would then quietly reuse
     * one question in place of another. The template is excluded from neither
     * side.
     */
    public function test_a_real_difference_still_changes_the_hash(): void {
        $changed = $this->database_side();
        $changed->options->template = 'A DIFFERENT TEMPLATE';

        $this->assertNotSame($this->hash($this->database_side()), $this->hash($changed));
    }

    /**
     * And a difference in the question's own text still changes it, so the
     * exclusions have not reached outside the options at all.
     */
    public function test_a_different_question_text_still_changes_the_hash(): void {
        $changed = $this->database_side();
        $changed->questiontext = 'Write a function that does something else entirely.';

        $this->assertNotSame($this->hash($this->database_side()), $this->hash($changed));
    }
}
