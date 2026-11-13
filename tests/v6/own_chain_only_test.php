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
 * Unit tests for the chain recorded for a question that resolves nothing.
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
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Tests that every question loaded for display carries a chain.
 *
 * A question with nothing to resolve — a legacy prototype question, a question
 * with no prototype id, a question whose stored chain is broken — gets a chain
 * of its own one layer rather than no chain at all. The attempt path falls back
 * on the CodeRunner 5 prototype lookup whenever it finds no chain, so what is
 * asserted here is that no loaded question sends it there.
 *
 * The exception is a question loaded with export_process set, which must have
 * nothing added to it: the identity hash is a multiset of the values found on
 * the options object, so a recorded chain would enter it and could stop a
 * pre-6.0 backup matching what is already in the bank. That is about the hash,
 * not about the export format.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_coderunner::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class own_chain_only_test extends \advanced_testcase {
    /** @var int the question under test. */
    private int $questionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        (new prototype_repository())->create(
            'python3',
            (int) SITEID,
            new override_set(['template' => 'PROTOTYPE TEMPLATE'], 'python3')
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

        $this->questionid = (int) $question->id;
    }

    /**
     * The question's options, loaded the way an attempt loads them.
     *
     * @return \stdClass the options.
     */
    private function loaded_options(): \stdClass {
        // The loaded question is cached in core/questiondata, and these tests
        // change its options row underneath it.
        \question_bank::notify_question_edited($this->questionid);
        return \question_bank::load_question_data($this->questionid)->options;
    }

    /**
     * Assert that the options carry a chain of the question's own one layer.
     *
     * @param \stdClass $options the loaded options.
     */
    private function assert_own_chain_only(\stdClass $options): void {
        $this->assertIsArray($options->chainlayers);
        $this->assertCount(1, $options->chainlayers);
        $layer = $options->chainlayers[0];
        $this->assertEquals(\qtype_coderunner::QUESTION_LAYER, $layer['source']);
        $this->assertEquals((string) $options->templateparams, $layer['templateparams']);
        $this->assertEquals($options->uiplugin, $layer['uiplugin']);
        $this->assertEquals([], $options->supportfileareas);
    }

    /**
     * A question that resolves has the chain its prototype gives it.
     */
    public function test_a_resolvable_question_resolves_its_chain(): void {
        $options = $this->loaded_options();
        $this->assertCount(2, $options->chainlayers);
        // Root first, so the prototype's layer comes before the question's.
        // A layer names the prototype version it came from, not just the
        // prototype, so the label is python3@<version>.
        $this->assertStringStartsWith('python3@', $options->chainlayers[0]['source']);
        $this->assertEquals(\qtype_coderunner::QUESTION_LAYER, $options->chainlayers[1]['source']);
    }

    /**
     * A question with no prototype id is broken, and inherits nothing.
     */
    public function test_a_question_with_no_prototype_id_gets_its_own_layer(): void {
        global $DB;
        $DB->set_field('question_coderunner_options', 'prototypeid', null, ['questionid' => $this->questionid]);

        $options = $this->loaded_options();
        $this->assert_own_chain_only($options);
        $this->assertNotEquals('PROTOTYPE TEMPLATE', $options->template);
    }

    /**
     * So is one whose prototype id names a prototype that is not there.
     */
    public function test_an_unresolvable_chain_gets_its_own_layer(): void {
        global $DB;
        $DB->set_field('question_coderunner_options', 'prototypeid', -1, ['questionid' => $this->questionid]);

        $this->assert_own_chain_only($this->loaded_options());
    }

    /**
     * A legacy prototype question inherits nothing by definition, so its chain
     * is itself. It has no prototype id either, which is what the save path
     * decided for it.
     */
    public function test_a_legacy_prototype_question_gets_its_own_layer(): void {
        global $DB;
        $DB->set_field('question_coderunner_options', 'prototypetype', 2, ['questionid' => $this->questionid]);
        $DB->set_field('question_coderunner_options', 'prototypeid', null, ['questionid' => $this->questionid]);

        $options = $this->loaded_options();
        $this->assert_own_chain_only($options);
        $this->assertTrue($options->customise);
    }

    /**
     * The export_process flag adds neither the chain nor the file areas,
     * whatever the question resolves to, because everything on the options
     * object goes into the identity hash. It says nothing about what an
     * exported question contains: a non-column property cannot reach the XML.
     */
    public function test_the_export_process_flag_records_no_chain(): void {
        global $DB;

        foreach ([null, -1, (int) $this->questionid] as $i => $ignored) {
            // Built from a raw question record and marked for export before
            // the options are loaded, which is what restore_dbops does when it
            // hashes the questions already in the bank.
            $question = $DB->get_record('question', ['id' => $this->questionid]);
            $question->export_process = true;
            get_question_options($question);
            $this->assertObjectNotHasProperty('chainlayers', $question->options);
            $this->assertObjectNotHasProperty('supportfileareas', $question->options);

            // Once as a question that resolves, once as a broken one, and once
            // as a legacy prototype question, which is the only case that sets
            // anything of its own during export.
            if ($i === 0) {
                $DB->set_field('question_coderunner_options', 'prototypeid', null, ['questionid' => $this->questionid]);
            } else if ($i === 1) {
                $DB->set_field('question_coderunner_options', 'prototypetype', 2, ['questionid' => $this->questionid]);
            }
        }
    }
}
