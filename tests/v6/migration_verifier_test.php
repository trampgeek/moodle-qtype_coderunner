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
 * Unit tests for how the migration verifier classifies what it cannot compare.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * The verifier compares two resolutions of the same question, and a question it
 * can only resolve once is not a disagreement.
 *
 * A question created through the CodeRunner 6 form can name a prototype that
 * has no CodeRunner 5 prototype question behind it — every prototype made in
 * the prototype editor is one — so CodeRunner 5 cannot resolve it at all. The
 * migration never ran on such a question, so there is nothing to verify, and
 * reporting it as a changed setting would say the migration had altered a
 * question it never saw.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(migration_verifier::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class migration_verifier_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A question on a prototype CodeRunner 5 never knew about is counted as
     * unverifiable, by name, and is not reported as a difference.
     */
    public function test_a_question_crn5_cannot_resolve_is_unverifiable_not_differing(): void {
        global $DB;

        // CodeRunner 5's prototype search starts at the site's own question
        // bank, and prototype_contexts() refuses to run without one.
        \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type(
            get_site(),
            true
        );

        $course = $this->getDataGenerator()->create_course();
        $repo = new prototype_repository();
        $prototype = $repo->create(
            'python3_function',
            (int) $course->id,
            new override_set(['template' => '{{ STUDENT_ANSWER }}'], 'python3_function')
        );

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([
            'contextid' => \context_course::instance($course->id)->id,
        ]);
        $question = $generator->create_question('coderunner', 'sqr', [
            'category' => $category->id,
        ]);

        // What the CodeRunner 6 form stores: a prototype id and an overrides
        // document, under a type name no prototype question carries.
        $DB->set_field(
            'question_coderunner_options',
            'coderunnertype',
            'python3_function',
            ['questionid' => $question->id]
        );
        $DB->set_field(
            'question_coderunner_options',
            'prototypeid',
            $prototype->id,
            ['questionid' => $question->id]
        );
        $DB->set_field(
            'question_coderunner_options',
            'overrides',
            '{"template":"{{ STUDENT_ANSWER }}"}',
            ['questionid' => $question->id]
        );

        $this->assertNull(
            \qtype_coderunner::get_prototype('python3_function', \context_course::instance($course->id)),
            'The fixture is only meaningful while CodeRunner 5 cannot resolve the name.'
        );

        $result = (new migration_verifier())->verify();

        $this->assertSame(1, $result->unverifiable);
        $this->assertSame(['python3_function' => 1], $result->unverifiablebyname);
        $this->assertSame(0, $result->differing);
        $this->assertSame([], $result->differences);
        $this->assertSame(0, $result->checked, 'Nothing was compared, so nothing was checked.');
    }
}
