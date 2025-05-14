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
 * This is a walkthrough test for the CodeRunner plugin
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2019 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/helper.php');

/**
 * More extensive testing of penalty regime.
 *
 * @coversNothing
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class penaltyregime_test extends \qbehaviour_walkthrough_test_base {
    protected function setUp(): void {
        parent::setUp();
        \qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    // Support function to run the sqr question with the given penalty regime,
    // making $numbadattempts wrong submissions followed by a correct submission.
    // Check the resulting mark = $expected.
    public function run_with_regime($regime, $numbadattempts, $expected) {
        $helper = new \qtype_coderunner_test_helper();
        $q = $helper->make_coderunner_question_sqr(['penaltyregime' => $regime]);
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);
        for ($i = 1; $i <= $numbadattempts; $i++) {
            // Submit a totally wrong answer $numbadattempts times.
            $badanswer = 'def sqr(n): return ' . (-100 * $i);
            $this->process_submission(['-submit' => 1, 'answer' => $badanswer]);
            $this->check_current_mark(0.0);
        }
        // Now get it right.
        $this->process_submission(['-submit' => 1, 'answer' => 'def sqr(n): return n * n']);
        $this->check_current_mark($expected);
    }

    public function test_with_good_regime() {
        $this->run_with_regime("15, 30, 50, ...", 0, 1.0);
        $this->run_with_regime("15, 30, 50, ...", 1, 0.85);
        $this->run_with_regime("15, 30, 50, ...", 2, 0.70);
        $this->run_with_regime("15, 30, 50, ...", 3, 0.50);
        $this->run_with_regime("15, 30, 50, ...", 4, 0.30);
        $this->run_with_regime("15, 30, 50, ...", 5, 0.10);
        $this->run_with_regime("15, 30, 50, ...", 6, 0.0);
    }

    public function test_with_missing_comma_at_end() {
        $this->run_with_regime("15, 30, 50 ...", 0, 1.0);
        $this->run_with_regime("15, 30, 50 ...", 1, 0.85);
        $this->run_with_regime("15, 30, 50 ...", 2, 0.70);
        $this->run_with_regime("15, 30, 50 ...", 3, 0.50);
        $this->run_with_regime("15, 30, 50 ...", 4, 0.30);
        $this->run_with_regime("15, 30, 50 ...", 5, 0.10);
        $this->run_with_regime("15, 30, 50 ...", 6, 0.0);
    }

    public function test_with_space_separators_and_percents() {
        $this->run_with_regime("15%  30  50% ...", 0, 1.0);
        $this->run_with_regime("15   30%   50 ...", 1, 0.85);
        $this->run_with_regime("15 30   50   ...", 2, 0.70);
        $this->run_with_regime("15%    30 50   ...", 3, 0.50);
        $this->run_with_regime("15% 30% 50% ...", 4, 0.30);
        $this->run_with_regime("15 30 50 ...", 5, 0.10);
        $this->run_with_regime("15 30 50 ...", 6, 0.0);
    }
}
