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
 * CodeRunner renderer class.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

use qtype_coderunner\constants;

/**
 * Subclass for generating the bits of output specific to coderunner questions.
 *
 * @copyright  Richard Lobb, University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qtype_coderunner_renderer extends qtype_renderer {

    const FORCE_TABULAR_EXAMPLES = true;

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the question text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        global $CFG, $PAGE;

        $question = $qa->get_question();
        $qtext = $question->format_questiontext($qa);
        $examples = $question->example_testcases();
        if (count($examples) > 0) {
            $qtext .= html_writer::tag('p', 'For example:', array('class' => 'for-example-para'));
            $qtext .= html_writer::start_tag('div', array('class' => 'coderunner-examples'));
            $qtext .= $this->format_examples($examples);
            $qtext .= html_writer::end_tag('div');
        }

        $qtext .= html_writer::start_tag('div', array('class' => 'prompt'));
        $answerprompt = get_string("answer", "quiz") . ': ';
        $qtext .= $answerprompt;
        $qtext .= html_writer::end_tag('div');

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $rows = isset($question->answerboxlines) ? $question->answerboxlines : 18;
        $cols = isset($question->answerboxcolumns) ? $question->answerboxcolumns : 100;
        $preload = isset($question->answerpreload) ? $question->answerpreload : '';
        $taattributes = array(
            'class' => 'coderunner-answer edit_code',
            'name'  => $responsefieldname,
            'id'    => $responsefieldid,
            'cols'      => $cols,
            'spellcheck' => 'false',
            'rows'      => $rows
        );

        if ($options->readonly) {
            $taattributes['readonly'] = 'readonly';
        }

        $currentanswer = $qa->get_last_qt_var('answer');
        if (empty($currentanswer)) {
            $currentanswer = $preload;
        }
        $qtext .= html_writer::tag('textarea', s($currentanswer), $taattributes);

        if ($qa->get_state() == question_state::$invalid) {
            $qtext .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)),
                    array('class' => 'validationerror'));
        }

        $penalties = $question->penaltyregime ? $question->penaltyregime :
            number_format($question->penalty * 100, 1);
        $penaltypara =  html_writer::tag('p',
            get_string('penaltyregime', 'qtype_coderunner') . ': ' . s($penalties) . ' %',
            array('class' => 'penaltyregime'));
        $qtext .= $penaltypara;

        // Initialise any program-editing JavaScript.
        // Thanks to Ulrich Dangel for the original implementation of the Ace code editor.
        qtype_coderunner_util::load_ace_if_required($question, $responsefieldid, constants::USER_LANGUAGE);
        $PAGE->requires->js_call_amd('qtype_coderunner/textareas', 'initQuestionTA', array($responsefieldid));

        return $qtext;
    }


    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function specific_feedback(question_attempt $qa) {
        $toserialised = $qa->get_last_qt_var('_testoutcome');
        if (!$toserialised) { // Something broke?
            return '';
        }

        $testoutcome = unserialize($toserialised);
        $testoutcome->set_renderer($this);
        return $testoutcome->html_feedback($qa);
    }


    /**
     * Return the HTML to display the sample answer, if given.
     * @param question_attempt $qa
     * @return string The html for displaying the sample answer.
     */
    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        $answer = $question->answer;
        if (!$answer) {
            return '';
        }

        $heading = get_string('asolutionis', 'qtype_coderunner');
        $html = html_writer::start_tag('div', array('class' => 'sample code'));
        $html .= html_writer::tag('h4', $heading);
        $html .= html_writer::tag('pre', s($answer));
        $html .= html_writer::end_tag('div');
        return $html;
    }


    // Format one or more examples.
    protected function format_examples($examples) {
        if ($this->all_single_line($examples) && ! self::FORCE_TABULAR_EXAMPLES) {
            return $this->format_examples_one_per_line($examples);
        } else {
            return $this->format_examples_as_table($examples);
        }
    }


    // Return true iff there is no standard input and all expectedoutput and shell
    // input cases are single line only.
    private function all_single_line($examples) {
        foreach ($examples as $example) {
            if (!empty($example->stdin) ||
                strpos($example->testcode, "\n") !== false ||
                strpos($example->expected, "\n") !== false) {
                return false;
            }
        }
        return true;
    }


    // Return a '<br>' separated list of expression -> result examples.
    // For use only where there is no stdin and shell input is one line only.
    private function format_examples_one_per_line($examples) {
        $text = '';
        foreach ($examples as $example) {
            $text .= $example->testcode . ' &rarr; ' . $example->expected;
            $text .= html_writer::empty_tag('br');
        }
        return $text;
    }


    private function format_examples_as_table($examples) {
        $table = new html_table();
        $table->attributes['class'] = 'coderunnerexamples';
        list($numstd, $numshell) = $this->count_bits($examples);
        $table->head = array();
        if ($numshell) {
            $table->head[] = 'Test';
        }
        if ($numstd) {
            $table->head[] = 'Input';
        }
        $table->head[] = 'Result';

        $tablerows = array();
        $rowclasses = array();
        $i = 0;
        foreach ($examples as $example) {
            $row = array();
            $rowclasses[$i] = $i % 2 == 0 ? 'r0' : 'r1';
            if ($numshell) {
                $row[] = qtype_coderunner_util::format_cell($example->testcode);
            }
            if ($numstd) {
                $row[] = qtype_coderunner_util::format_cell($example->stdin);
            }
            $row[] = qtype_coderunner_util::format_cell($example->expected);
            $tablerows[] = $row;
            $i++;
        }
        $table->data = $tablerows;
        $table->rowclasses = $rowclasses;
        return html_writer::table($table);
    }


    // Return a count of the number of non-empty stdins and non-empty shell
    // inputs in the given list of test result objects.
    private function count_bits($tests) {
        $numstds = 0;
        $numshell = 0;
        foreach ($tests as $test) {
            if (trim($test->stdin) !== '') {
                $numstds++;
            }
            if (trim($test->testcode) !== '') {
                $numshell++;
            }
        }
        return array($numstds, $numshell);
    }


    /**
     * Hook for testing_outcome to call back to get feedback image from
     * base renderer class.
     */
    public function get_feedback_image($mark) {
        return $this->feedback_image($mark);
    }
}
