<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Question behaviour for the old adaptive mode.
 *
 * @package    qbehaviour
 * @subpackage adaptive
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *  Need a special behaviour for coderunner questions (which are assumed to be
 *  run in some sort of adaptive mode), in order to avoid repeating
 *  the expensive test run whenever question::grade_response is called.
 *
 *  The solution adopted here is to override the process_submit method of
 *  the adaptive behaviour so that it calls the coderunner::grade_response_raw
 *  method, rather than coderunner::grade_response. The raw method takes the
 *  question_attempt_pending_step as a parameter rather than the response
 *  copied from that step. This allows the question to cache the test results
 *  within the step, which is stored in the database.
 *
 * TODO: keep in touch with developers regarding the fact that this interface
 * is broken (admitted by Tim Hunt in earlier communications). This file
 * (or the functionality it provides, anyway) belongs somewhere in
 * the question/type subtree.
 */

defined('MOODLE_INTERNAL') || die();

class qbehaviour_adaptive_adapted_for_coderunner extends qbehaviour_adaptive {
    const IS_ARCHETYPAL = false;

    public function required_question_definition_type() {
        // Restrict behaviour to programming questions
        return 'qtype_coderunner_question';
    }


    public function process_submit(question_attempt_pending_step $pendingstep) {
        $status = $this->process_save($pendingstep);

        $response = $pendingstep->get_qt_data();
        if (!$this->question->is_complete_response($response)) {
            $pendingstep->set_state(question_state::$invalid);
            if ($this->qa->get_state() != question_state::$invalid) {
                $status = question_attempt::KEEP;
            }
            return $status;
        }

        $prevstep = $this->qa->get_last_step_with_behaviour_var('_try');
        $prevresponse = $prevstep->get_qt_data();
        $prevtries = $this->qa->get_last_behaviour_var('_try', 0);
        $prevbest = $pendingstep->get_fraction();
        if (is_null($prevbest)) {
            $prevbest = 0;
        }

        if ($this->question->is_same_response($response, $prevresponse)) {
            return question_attempt::DISCARD;
        }

        // *** changed bit #1 begins ***
        $gradeData = $this->question->grade_response($response);
        list($fraction, $state) = $gradeData;
        if (count($gradeData) > 2) {
            foreach($gradeData[2] as $name => $value) {
                $pendingstep->set_qt_var($name, $value);
            }
        }
        // *** end of changed bit #1 ***

        $pendingstep->set_fraction(max($prevbest, $this->adjusted_fraction($fraction, $prevtries)));
        if ($prevstep->get_state() == question_state::$complete) {
            $pendingstep->set_state(question_state::$complete);
        } else if ($state == question_state::$gradedright) {
            $pendingstep->set_state(question_state::$complete);
        } else {
            $pendingstep->set_state(question_state::$todo);
        }
        $pendingstep->set_behaviour_var('_try', $prevtries + 1);
        $pendingstep->set_behaviour_var('_rawfraction', $fraction);
        $pendingstep->set_new_response_summary($this->question->summarise_response($response));

        return question_attempt::KEEP;
    }


    public function process_finish(question_attempt_pending_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        $prevtries = $this->qa->get_last_behaviour_var('_try', 0);
        $prevbest = $this->qa->get_fraction();
        if (is_null($prevbest)) {
            $prevbest = 0;
        }

        $laststep = $this->qa->get_last_step();
        $response = $laststep->get_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            $state = question_state::$gaveup;
            $fraction = 0;
        } else {

            if ($laststep->has_behaviour_var('_try')) {
                // Last answer was graded, we want to regrade it. Otherwise the answer
                // has changed, and we are grading a new try.

                // Changed bit #3. Fix bug resulting in regrading of
                // already-graded questions.
                // See https://tracker.moodle.org/browse/MDL-42399
                // $prevtries -= 1;  // I never did understand this line
                return question_attempt::DISCARD;  // This should do the trick ???
                // End changed bit #3.
            }

            // *** changed bit #2 begins ***
            // Cache extra data from grade response.
            $gradeData = $this->question->grade_response($response);
            list($fraction, $state) = $gradeData;
            if (count($gradeData) > 2) {
                foreach($gradeData[2] as $name => $value) {
                    $pendingstep->set_qt_var($name, $value);
                }
            }
            // *** end of changed bit #2 ***

            $pendingstep->set_behaviour_var('_try', $prevtries + 1);
            $pendingstep->set_behaviour_var('_rawfraction', $fraction);
            $pendingstep->set_new_response_summary($this->question->summarise_response($response));
        }

        $pendingstep->set_state($state);
        $pendingstep->set_fraction(max($prevbest, $this->adjusted_fraction($fraction, $prevtries)));
        return question_attempt::KEEP;
    }
}
