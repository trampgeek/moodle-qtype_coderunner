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
 * @package    qtype_coderunner
 * @copyright  2012 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_coderunner\constants;

/**
 * Subclass for generating the bits of output specific to coderunner questions.
 *
 * @copyright  Richard Lobb, University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qtype_coderunner_renderer extends qtype_renderer {
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
        global $USER;

        $question = $qa->get_question();
        $qid = $question->id;
        if (empty($USER->coderunnerquestionids)) {
            $USER->coderunnerquestionids = [$qid];  // Record in case of AJAX request.
        } else {
            array_push($USER->coderunnerquestionids, $qid); // Array of active qids.
        }
        $divid = "qtype_coderunner_problemspec$qid";
        $params = $question->parameters;
        $qtext = '';
        if (isset($question->initialisationerrormessage) && $question->initialisationerrormessage) {
            $qtext .= "<div class='initialisationerror'>{$question->initialisationerrormessage}</div>";
        }
        $qtext .= $question->format_questiontext($qa);
        if (isset($params->programming_contest_problem) && $params->programming_contest_problem) {
            // Special case hack for programming contest problems.
            $qtext .= "<div id='$divid'></div>";
            $probspecfilename = isset($params->problem_spec_filename) ? $params->problem_spec_filename : '';
            $this->page->requires->js_call_amd(
                'qtype_coderunner/ajaxquestionloader',
                'loadQuestionText',
                [$qid, $divid, $probspecfilename]
            );
        }
        $examples = $question->example_testcases();
        if (count($examples) > 0) {
            $forexample = get_string('forexample', 'qtype_coderunner');
            $qtext .= html_writer::tag('p', $forexample . ':', ['class' => 'for-example-para']);
            $qtext .= html_writer::start_tag('div', ['class' => 'coderunner-examples']);
            $resultcolumns = $question->result_columns();
            $qtext .= $this->format_examples($examples, $resultcolumns);
            $qtext .= html_writer::end_tag('div');
        }

        $qtext .= html_writer::start_tag('div', ['class' => 'prompt']);

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $answerprompt = html_writer::tag(
            'label',
            get_string('answerprompt', 'qtype_coderunner'),
            ['class' => 'answerprompt', 'for' => $responsefieldid]
        );
        $qtext .= $answerprompt;
        $behaviour = $qa->get_behaviour(true);
        if ($behaviour->penaltiesenabled && $qa->has_marks()) {
            $penaltystring = $this->penalty_regime_string($qa);
            $htmlpenalties = html_writer::tag(
                'span',
                $penaltystring,
                ['class' => 'penaltyregime']
            );
            $qtext .= $htmlpenalties;
        }

        if (empty($question->acelang)) {
            $currentlanguage = $question->language;
        } else {
            $currentlanguage = $question->acelang;
            if (strpos($currentlanguage, ',') !== false) {
                $qtext .= $this->language_dropdown($qa);
                [$langs, $default] = qtype_coderunner_util::extract_languages($currentlanguage);
                $currentlanguage = $default ? $default : $langs[0];
            }
        }

        $qtext .= html_writer::end_tag('div');

        $preload = isset($question->answerpreload) ? $question->answerpreload : '';
        if ($preload) {  // Add a reset button if preloaded text is non-empty.
            $qtext .= $this->reset_button($qa, $responsefieldid, $preload);
        }

        $currentanswer = $qa->get_last_qt_var('answer');
        if ($currentanswer === null || $currentanswer === '') {
            $currentanswer = $preload;
        } else {
            // Horrible horrible hack for horrible horrible browser feature
            // of ignoring a leading newline in a textarea. So we inject an
            // extra one to ensure that if the answer begins with a newline it
            // is preserved.
            $currentanswer = "\n" . $currentanswer;
        }

        $rows = isset($question->answerboxlines) ? $question->answerboxlines : constants::DEFAULT_NUM_ROWS;
        $taattributes = $this->answerbox_attributes(
            $responsefieldname,
            $rows,
            $question,
            $currentlanguage,
            $options->readonly
        );

        $qtext .= html_writer::tag('textarea', s($currentanswer), $taattributes);

        if ($qa->get_state() == question_state::$invalid) {
            $qtext .= html_writer::nonempty_tag(
                'div',
                $question->get_validation_error($qa->get_last_qt_data()),
                ['class' => 'validationerror']
            );
        }

        // Add file upload controls if attachments are allowed.
        $files = '';
        if ($question->attachments) {
            if (empty($options->readonly)) {
                $files = $this->files_input($qa, $question->attachments, $options);
            } else {
                $files = $this->files_read_only($qa, $options);
            }
            $qtext .= html_writer::tag(
                'div',
                $files,
                ['class' => 'form-filemanager', 'data-fieldtype' => 'filemanager']
            );
            // Class and data-fieldtype are so behat can find the filemanager in both boost and clean themes.
        }

        // Initialise any JavaScript UI. Default is Ace unless uiplugin is explicitly
        // set and is neither the empty string nor the value 'none'.
        // Thanks to Ulrich Dangel for the original implementation of the Ace code editor.
        $uiplugin = $question->uiplugin === null ? 'ace' : strtolower($question->uiplugin);
        if ($uiplugin !== '' && $uiplugin !== 'none') {
            qtype_coderunner_util::load_uiplugin_js($question, $responsefieldid);
            if (!empty($question->acelang) && strpos($question->acelang, ',') != false) {
                // For multilanguage questions, add javascript to switch the
                // Ace language when the user changes the selected language.
                $this->page->requires->js_call_amd(
                    'qtype_coderunner/multilanguagequestion',
                    'initLangSelector',
                    [$responsefieldid]
                );
            }
        } else {
            $this->page->requires->js_call_amd(
                'qtype_coderunner/textareas',
                'initQuestionTA',
                [$responsefieldid]
            );
        }

        return $qtext;
    }


    /**
     * Override the base class method to allow CodeRunner questions to force
     * specific feedback to be displayed or hidden regardless of the quiz
     * review options.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function feedback(question_attempt $qa, question_display_options $options) {
        $optionsclone = clone($options);
        /** @var qtype_coderunner_question $q */
        $q = $qa->get_question();
        $feedbackdisplay = $q->display_feedback();

        // Update options for displaying specific feedback.
        if ($feedbackdisplay !== constants::FEEDBACK_USE_QUIZ && !empty($qa->get_last_qt_var('_testoutcome'))) {
            if ($feedbackdisplay === CONSTANTS::FEEDBACK_SHOW) {
                $optionsclone->feedback = 1;
            } else if ($feedbackdisplay === CONSTANTS::FEEDBACK_HIDE) {
                $optionsclone->feedback = 0;
            } else {
                throw new coding_exception("Invalid value of feedbackdisplay: $feedbackdisplay");
            }
        }

        // Update options for displaying general feedback.
        if ($feedbackdisplay === CONSTANTS::FEEDBACK_SHOW) {
            if ($qa->get_state()->is_finished() && $q->giveupallowed) {
                $optionsclone->generalfeedback = 1;
            }
        }

        return parent::feedback($qa, $optionsclone);
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

        $q = $qa->get_question();
        $outcome = unserialize($toserialised);
        if ($outcome === false) {
            $outcome = new qtype_coderunner_testing_outcome(0, 0, false);
            $outcome->set_status(qtype_coderunner_testing_outcome::STATUS_UNSERIALIZE_FAILED);
        }
        $resultsclass = $this->results_class($outcome, $q->allornothing);

        $isoutputonly = $outcome->is_output_only();
        if ($isoutputonly) {
            $resultsclass .= ' outputonly';
        }
        $isprecheck = $outcome->is_precheck($qa);
        if ($isprecheck) {
            $resultsclass .= ' precheck';
        }

        $fb = '';

        if ($q->showsource) {
            $fb .= $this->make_source_code_div($outcome);
        }

        $fb .= html_writer::start_tag('div', ['class' => $resultsclass]);
        if ($outcome->invalid()) {
            $fb .= html_writer::tag(
                'h5',
                get_string('unserializefailed', 'qtype_coderunner'),
                ['class' => 'run_failed_error']
            );
        } else if ($outcome->run_failed()) {
            $fb .= html_writer::tag('h5', get_string('run_failed', 'qtype_coderunner'));
            ;
            $fb .= html_writer::tag(
                'p',
                s($outcome->errormessage),
                ['class' => 'run_failed_error']
            );
        } else if ($outcome->has_syntax_error()) {
            $fb .= html_writer::tag('h5', get_string('syntax_errors', 'qtype_coderunner'));
            $fb .= html_writer::tag(
                'pre',
                s($outcome->errormessage),
                ['class' => 'pre_syntax_error']
            );
        } else if ($outcome->combinator_error()) {
            $fb .= html_writer::tag('h5', get_string('badquestion', 'qtype_coderunner'));
            $fb .= html_writer::tag(
                'pre',
                s($outcome->errormessage),
                ['class' => 'pre_question_error']
            );
        } else {
            // The run was successful (i.e didn't crash, but may be wrong answer). Display results.
            if ($isprecheck) {
                $fb .= html_writer::tag('h3', get_string('precheck_only', 'qtype_coderunner'));
            }

            if ($isprecheck && $q->precheck == constants::PRECHECK_EMPTY && !$outcome->iscombinatorgrader()) {
                $fb .= $this->empty_precheck_status($outcome);
            } else {
                $fb .= $this->build_results_table($outcome, $q);
            }
        }

        // Summarise the status of the response in a paragraph at the end.
        // Suppress when previous errors have already said enough or it's
        // an output only question.
        if (
            !$outcome->has_syntax_error() &&
             !$isprecheck &&
             !$isoutputonly &&
             !$outcome->is_ungradable() &&
             !$outcome->run_failed()
        ) {
            $fb .= $this->build_feedback_summary($qa, $outcome);
        }
        $fb .= html_writer::end_tag('div');

        // Need to pass the feedback through format_text to activate filters.
        // Try to ensure it makes minimal changes.
        $formatoptions = new stdClass();
        $formatoptions->trusted = true;
        $formatoptions->noclean = true;
        $formatoptions->para = false;
        $formatoptions->newlines = false;
        $formatoptions->allowid = true;
        return format_text($fb, FORMAT_HTML, $formatoptions);
    }

    /**
     * Return html to display the status of an empty precheck run.
     * @param qtype_coderunner_testing_outcome $outcome the results from the test
     * Must be a standard testing outcome, not a combinator grader outcome.
     * @return html string describing the outcome
     */
    protected function empty_precheck_status($outcome) {
        $output = $outcome->get_raw_output();
        if (!empty($output)) {
            $fb = html_writer::tag('p', get_string('bademptyprecheck', 'qtype_coderunner'));
            $fb .= html_writer::tag(
                'pre',
                qtype_coderunner_util::format_cell($output),
                ['class' => 'bad_empty_precheck']
            );
        } else {
            $fb = html_writer::tag(
                'p',
                get_string('goodemptyprecheck', 'qtype_coderunner'),
                ['class' => 'good_empty_precheck']
            );
        }
        return $fb;
    }

    // Generate the main feedback, consisting of (in order) any prologuehtml,
    // a table of results and any epiloguehtml. Finally append a warning if
    // question is being tested using the University of Canterbury's testing
    // Jobe server.
    protected function build_results_table($outcome, qtype_coderunner_question $question) {
        global $CFG;
        $fb = $outcome->get_prologue();
        $testresults = $outcome->get_test_results($question);
        if (is_array($testresults) && count($testresults) > 1) {
            $table = new html_table();
            $table->attributes['class'] = 'coderunner-test-results';
            $headers = $testresults[0];
            foreach ($headers as $header) {
                if (strtolower($header) != 'ishidden') {
                    $table->head[] = strtolower($header) === 'iscorrect' ? '' : $header;
                }
            }

            $rowclasses = [];
            $tablerows = [];

            $n = count($testresults);
            for ($i = 1; $i < $n; $i++) {
                $cells = $testresults[$i];
                $rowclass = $i % 2 == 0 ? 'r0' : 'r1';
                $tablerow = [];
                $j = 0;
                foreach ($cells as $cell) {
                    if (strtolower($headers[$j]) === 'iscorrect') {
                        $markfrac = (float) $cell;
                        $tablerow[] = $this->feedback_image($markfrac);
                    } else if (strtolower($headers[$j]) === 'ishidden') { // Control column.
                        if ($cell) { // Anything other than zero or false means hidden.
                            $rowclass .= ' hidden-test';
                        }
                    } else if ($cell instanceof qtype_coderunner_html_wrapper) {
                        $tablerow[] = $cell->value();  // It's already HTML.
                    } else {
                        $tablerow[] = qtype_coderunner_util::format_cell($cell);
                    }
                    $j++;
                }
                $tablerows[] = $tablerow;
                $rowclasses[] = $rowclass;
            }
            $table->data = $tablerows;
            $table->rowclasses = $rowclasses;
            $fb .= html_writer::table($table);
        }
        $fb .= $outcome->get_epilogue();

        // Issue a bright yellow warning if using jobe2, except when running behat.
        $sandboxinfo = $outcome->get_sandbox_info();
        if (isset($sandboxinfo['jobeserver'])) {
            $jobeserver = $sandboxinfo['jobeserver'];
            $apikey = $sandboxinfo['jobeapikey'];
            if (qtype_coderunner_sandbox::is_canterbury_server($jobeserver)
                    && (!qtype_coderunner_sandbox::is_using_test_sandbox())) {
                if ($apikey == constants::JOBE_HOST_DEFAULT_API_KEY) {
                    $fb .= get_string('jobe_warning_html', 'qtype_coderunner');
                } else {
                    $fb .= get_string('jobe_canterbury_html', 'qtype_coderunner');
                }
            }
        }

        return $fb;
    }


    // Compute the HTML feedback summary for this test outcome.
    // Should not be called if there were any syntax or sandbox errors.
    protected function build_feedback_summary(question_attempt $qa, qtype_coderunner_testing_outcome $outcome) {
        if ($outcome->iscombinatorgrader()) {
            // Simplified special case.
            return $this->build_combinator_grader_feedback_summary($qa, $outcome);
        }
        $question = $qa->get_question();
        $isprecheck = $outcome->is_precheck($qa);
        $lines = [];  // List of lines of output.

        $onlyhiddenfailed = false;
        if ($outcome->was_aborted()) {
            $lines[] = get_string('aborted', 'qtype_coderunner');
        } else {
            $hiddenerrors = $outcome->count_hidden_errors();
            if ($outcome->get_error_count() > 0) {
                if ($outcome->get_error_count() == $hiddenerrors) {
                    $onlyhiddenfailed = true;
                    $lines[] = get_string('failedhidden', 'qtype_coderunner');
                } else if ($hiddenerrors > 0) {
                    $lines[] = get_string('morehidden', 'qtype_coderunner');
                }
            }
        }

        if ($outcome->all_correct()) {
            if (!$isprecheck) {
                $lines[] = get_string('allok', 'qtype_coderunner') .
                        "&nbsp;" . $this->feedback_image(1.0);
            }
        } else {
            if ($question->allornothing && !$isprecheck) {
                $lines[] = get_string('noerrorsallowed', 'qtype_coderunner');
            }

            // Provide a show differences button if answer wrong and equality grader used.
            if (
                (empty($question->grader) ||
                 $question->grader == 'EqualityGrader' ||
                 $question->grader == 'NearEqualityGrader') &&
                    !$onlyhiddenfailed
            ) {
                $lines[] = $this->diff_button($qa);
            }
        }

        return qtype_coderunner_util::make_html_para($lines);
    }


    // A special case of the above method for use with combinator template graders
    // only.
    protected function build_combinator_grader_feedback_summary($qa, qtype_coderunner_combinator_grader_outcome $outcome) {
        $isprecheck = $outcome->is_precheck($qa);
        $lines = [];  // List of lines of output.

        if ($outcome->all_correct() && !$isprecheck) {
            $lines[] = get_string('allok', 'qtype_coderunner') .
                    "&nbsp;" . $this->feedback_image(1.0);
        }

        if ($outcome->show_differences()) {
             $lines[] = $this->diff_button($qa);
        }

        return qtype_coderunner_util::make_html_para($lines);
    }


    // Build and return an HTML div section containing a list of template
    // outputs used as source code (which are recorded in the given $outcome).
    protected function make_source_code_div($outcome) {
        $html = '';
        $sourcecodelist = $outcome->get_sourcecode_list();
        if ($sourcecodelist && count($sourcecodelist) > 0) {
            $heading = get_string('sourcecodeallruns', 'qtype_coderunner');
            $html = html_writer::start_tag('div', ['class' => 'debugging']);
            $html .= html_writer::tag('h3', $heading);
            $i = 1;
            foreach ($sourcecodelist as $run) {
                $html .= html_writer::tag('h4', "Run $i");
                $i++;
                $html .= html_writer::tag('pre', s($run));
                $html .= html_writer::tag('hr', '');
            }
            $html .= html_writer::end_tag('div');
        }
        return $html;
    }


    /**
     * Return a string describing the penalties in place for this question.
     * @param type $qa
     * @return type
     */
    protected function penalty_regime_string(question_attempt $qa) {
        $question = $qa->get_question();
        if (empty($question->penaltyregime) && $question->penaltyregime !== '0') {
            if (intval(100 * $question->penalty) == 100 * $question->penalty) {
                $decdigits = 0;
            } else {
                $decdigits = 1;
            }
            $penaltypercent = number_format($question->penalty * 100, $decdigits);
            $penaltypercent2 = number_format($question->penalty * 200, $decdigits);
            $penalties = $penaltypercent . ', ' . $penaltypercent2 . ', ...';
        } else {
            $penalties = $question->penaltyregime;
        }
        return get_string('penaltyregime', 'qtype_coderunner', $penalties);
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
        } else {
            $answer = "\n" . $answer; // Hack to ensure leading new line not lost.
        }
        $fieldname = $qa->get_qt_field_name('sampleanswer');
        $currentlanguage = $question->acelang ? $question->acelang : $question->language;
        if (strpos($question->acelang ?? '', ',') !== false) {
            // Case of a multilanguage question sample answer. Find the language,
            // which is specified by the template parameter answer_language if
            // given, or the default (starred) language in the language list
            // if given or the first language listed, whichever comes first.
            [$languages, $default] = qtype_coderunner_util::extract_languages($question->acelang);
            $params = $question->parameters;
            if (property_exists($params, 'answer_language')) {
                $currentlanguage = $params->answer_language;
            } else if (!empty($default)) {
                $currentlanguage = $default;
            } else {
                $currentlanguage = $languages[0];
            }
        }

        $uclang = ucwords($currentlanguage);
        $heading = get_string('asolutionis', 'qtype_coderunner');
        $heading = substr($heading, 0, strlen($heading) - 1) . ' (' . $uclang . ')';
        $divid = 'id_div_' . $fieldname;
        $answerlinkid = 'id_link_' . $fieldname;
        $html = html_writer::start_tag('div', ['class' => 'sample-code-wrapper']);
        $html .= html_writer::start_tag('a', [
            'id' => $answerlinkid]);
        $html .= html_writer::tag('h6', "\u{25B8} " . $heading);
        $html .= html_writer::end_tag('a');
        $html .= html_writer::start_tag('div', [
            'id' => $divid,
            'class' => 'sample code',
            'style' => 'display:none']);
        $answerboxlines = isset($question->answerboxlines) ? $question->answerboxlines : constants::DEFAULT_NUM_ROWS;
        if ($question->uiplugin == 'ace') {
            $rows = min($answerboxlines, substr_count($answer, "\n"));
        } else {
            $rows = $answerboxlines;
        }
        $taattributes = $this->answerbox_attributes(
            $fieldname,
            $rows,
            $question,
            $currentlanguage,
            true
        );

        $html .= html_writer::tag('textarea', s($answer), $taattributes);
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        $uiplugin = $question->uiplugin === null ? 'ace' : strtolower($question->uiplugin);
        $fieldid = 'id_' . $fieldname;
        if ($uiplugin !== '' && $uiplugin !== 'none') {
            qtype_coderunner_util::load_uiplugin_js($question, $fieldid);
        } else {
            $this->page->requires->js_call_amd(
                'qtype_coderunner/textareas',
                'initQuestionTA',
                [$fieldid]
            );
        }
        $this->page->requires->js_call_amd(
            'qtype_coderunner/textareas',
            'setupShowHideAnswer',
            [$answerlinkid, $divid]
        );
        return $html;
    }


    /**
     * Displays any attached files when the question is in read-only mode.
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should
     *      not be displayed. Used to get the context.
     */
    public function files_read_only(question_attempt $qa, question_display_options $options) {
        $files = $qa->get_last_qt_files('attachments', $options->context->id);
        $output = [];

        foreach ($files as $file) {
            $output[] = html_writer::tag('p', html_writer::link(
                $qa->get_response_file_url($file),
                $this->output->pix_icon(
                    file_file_icon($file),
                    get_mimetype_description($file),
                    'moodle',
                    ['class' => 'icon']
                ) . ' ' . s($file->get_filename())
            ));
        }
        return implode($output);
    }

    /**
     * Displays the input control for when the student is allowed to upload files.
     * @param question_attempt $qa the question attempt to display.
     * @param int $numallowed the maximum number of attachments allowed. -1 = unlimited.
     * @param question_display_options $options controls what should and should
     *      not be displayed. Used to get the context.
     */
    public function files_input(
        question_attempt $qa,
        $numallowed,
        question_display_options $options
    ) {
        global $CFG;
        require_once($CFG->dirroot . '/lib/form/filemanager.php');

        $question = $qa->get_question();
        $pickeroptions = new stdClass();
        $pickeroptions->mainfile = null;
        $pickeroptions->maxfiles = $numallowed;
        $pickeroptions->maxbytes = intval($question->maxfilesize);
        $pickeroptions->context = $options->context;
        $pickeroptions->return_types = FILE_INTERNAL | FILE_CONTROLLED_LINK;
        $pickeroptions->accepted_types = '*';  // Accept anything - names checked on upload.
        $pickeroptions->itemid = $qa->prepare_response_files_draft_itemid(
            'attachments',
            $options->context->id
        );

        $fm = new form_filemanager($pickeroptions);
        $filesrenderer = $this->page->get_renderer('core', 'files');

        $text = '';
        if (!empty($question->filenamesexplain)) {
                $text = $question->filenamesexplain;
        } else if (!empty($question->filenamesregex)) {
            $text = html_writer::tag('p', get_string('allowedfilenamesregex', 'qtype_coderunner')
                    . ': ' . $question->filenamesregex);
        }

        return $filesrenderer->render($fm) . html_writer::empty_tag(
            'input',
            ['type' => 'hidden', 'name' => $qa->get_qt_field_name('attachments'),
            'value' => $pickeroptions->itemid]
        ) . $text;
    }


    /**
     *
     * @param array $examples The array of testcases tagged "use as example"
     * @param array $resultcolumns the array of 2-element arrays specifying what
     * columns should appear in the result table, and their formats.
     * @return string An HTML table element displaying all the testcases.
     */
    private function format_examples($examples, $resultcolumns) {
        $table = new html_table();
        $table->attributes['class'] = 'coderunnerexamples';

        // Record counts of non-empty cells in each column so empty columns are suppressed.
        // But always show the 'expected' column (renaming it to 'result').
        [$numtests, $numstds, $numextras] = $this->count_bits($examples);
        $counts = ['testcode' => $numtests, 'stdin' => $numstds, 'extra' => $numextras, 'expected' => 1];

        $table->head = [];
        $table->data = [];
        $table->rowclasses = [];
        $i = 0;
        foreach ($examples as $example) {
            $row = [];
            foreach (['testcode', 'stdin', 'extra', 'expected'] as $col) {
                if ($counts[$col] && $this->show_column($col, $resultcolumns)) {
                    if ($i == 0) {
                        $table->head[] = $this->column_header($col, $resultcolumns);
                    }
                    if ($this->column_format($col, $resultcolumns) == '%h') {
                        $row[] = $example->$col; // For html (%h) column, use raw value.
                    } else { // Otherwise wrap value in <pre> element.
                        $row[] = qtype_coderunner_util::format_cell($example->$col);
                    }
                }
            }
            $table->data[] = $row;
            $table->rowclasses[] = $i % 2 == 0 ? 'r0' : 'r1';
            $i++;
        }
        return html_writer::table($table);
    }


    // Return a count of the number of non-empty stdins, tests and extras
    // in the given list of test result objects.
    private function count_bits($tests) {
        $numstds = 0;
        $numtests = 0;
        $numextras = 0;
        foreach ($tests as $test) {
            if (trim($test->stdin ?? '') !== '') {
                $numstds++;
            }
            if (trim($test->testcode ?? '') !== '') {
                $numtests++;
            }
            if (trim($test->extra ?? '') !== '') {
                $numextras++;
            }
        }
        return [$numtests, $numstds, $numextras];
    }

    // True iff the given testcase field is specified by the given question
    // resultcolumns field to be displayed.
    private function show_column($field, $resultcolumns) {
        foreach ($resultcolumns as $columnspecifier) {
            if ($columnspecifier[1] === $field) {
                return true;
            }
        }
        return false;
    }


    // Return the column header to be used for the given testcase field,
    // as specified by the question's resultcolumns field.
    // But HACK ALERT - the 'expected' column is renamed to 'Result' in
    // the example table.
    private function column_header($field, $resultcolumns) {
        if ($field === 'expected') {
            return get_string('resultcolumnheader', 'qtype_coderunner');
        }
        foreach ($resultcolumns as $columnspecifier) {
            if ($columnspecifier[1] === $field) {
                return $columnspecifier[0];
            }
        }
        return 'ERROR';
    }

    // Return the format to be used for the given field. If no specific
    // format given, return %s.
    private function column_format($field, $resultcolumns) {
        foreach ($resultcolumns as $columnspecifier) {
            if (count($columnspecifier) > 2 && $columnspecifier[1] === $field) {
                return trim($columnspecifier[2] ?? '');
            }
        }
        return '%s';
    }

    // Return the text area attributes for an answer box.
    private function answerbox_attributes(
        $fieldname,
        $rows,
        $question,
        $currentlanguage,
        $readonly = false
    ) {
        if ($question->mergeduiparameters) {
            $uiparamsjson = json_encode($question->mergeduiparameters);
        } else {
            $uiparamsjson = '{}';
        }
        $attributes = [
                'class' => 'coderunner-answer edit_code',
                'name' => $fieldname,
                'id' => 'id_' . $fieldname,
                'spellcheck' => 'false',
                'rows' => $rows,
                'data-params' => $uiparamsjson,
                'data-globalextra' => $question->globalextra,
                'data-prototypeextra' => $question->prototypeextra,
                'data-lang' => ucwords($currentlanguage),
                'data-test0' => $question->testcases ? $question->testcases[0]->testcode : '',
        ];

        if ($readonly) {
            $attributes['readonly'] = '';
        }
        return $attributes;
    }


    // Return the HTML for a language dropdown list for the given question attempt.
    private function language_dropdown($qa) {
        $question = $qa->get_question();
        [$languages, $default] = qtype_coderunner_util::extract_languages($question->acelang);
        $currentlanguage = $qa->get_last_qt_var('language');
        if (empty($currentlanguage) && $default !== '') {
            $currentlanguage = $default;
        }
        $selectname = $qa->get_qt_field_name('language');
        $selectid = 'id_' . $selectname;
        $html = html_writer::start_tag('div', ['class' => 'coderunner-lang-select-div']);
        $html .= html_writer::tag(
            'label',
            get_string('languageselectlabel', 'qtype_coderunner'),
            ['for' => $selectid]
        );
        $html .= html_writer::start_tag(
            'select',
            ['id' => $selectid, 'name' => $selectname,
            'class' => 'coderunner-lang-select']
        );
        if (empty($currentlanguage)) {
            $html .= html_writer::tag('option', '', ['value' => '']);
        }
        foreach ($languages as $lang) {
            $attributes = ['value' => $lang];
            if ($lang === $currentlanguage) {
                $attributes['selected'] = 'selected';
            }
            $html .= html_writer::tag('option', $lang, $attributes);
        }
        $html .= html_writer::end_tag('select');
        $html .= html_writer::end_tag('div');
        return $html;
    }


    /**
     *
     * @param qtype_coderunner_testing_outcome $outcome
     * @return string the CSS class for the given testing outcome
     */
    protected function results_class($outcome, $isallornothing) {
        if ($outcome->all_correct()) {
            $resultsclass = "coderunner-test-results good";
        } else if ($isallornothing || $outcome->mark_as_fraction() == 0) {
            $resultsclass = "coderunner-test-results bad";
        } else {
            $resultsclass = 'coderunner-test-results partial';
        }
        return $resultsclass;
    }


    // Support method to generate the "Show differences" button.
    // Returns the HTML for the button, and sets up the JavaScript handler
    // for it.
    protected function diff_button($qa) {
        $buttonid = $qa->get_behaviour_field_name('diffbutton');
        $attributes = [
            'type' => 'button',
            'id' => $buttonid,
            'name' => $buttonid,
            'value' => get_string('showdifferences', 'qtype_coderunner'),
            'class' => 'btn btn-secondary',
        ];
        $html = html_writer::empty_tag('input', $attributes);

        $this->page->requires->js_call_amd(
            'qtype_coderunner/showdiff',
            'initDiffButton',
            [$attributes['id'],
                get_string('showdifferences', 'qtype_coderunner'),
                get_string('hidedifferences', 'qtype_coderunner'),
                get_string('expectedcolhdr', 'qtype_coderunner'),
                get_string('gotcolhdr', 'qtype_coderunner'),
            ]
        );

        return $html;
    }


    /**
     * Support method to generate the "Reset" button, which resets the student
     * answer to the preloaded value.
     *
     * Returns the HTML for the button, and sets up the JavaScript handler
     * for it.
     * @param question_attempt $qa The current question attempt object
     * @param string $responsefieldid The id of the student answer field
     * @param string $preload The text to be plugged into the answer if reset
     * @return string html string for the button
     */
    protected function reset_button($qa, $responsefieldid, $preload) {
        $buttonid = $qa->get_behaviour_field_name('resetbutton');
        $attributes = [
            'type' => 'button',
            'id' => $buttonid,
            'name' => $buttonid,
            'value' => get_string('reset', 'qtype_coderunner'),
            'class' => 'answer_reset_btn btn btn-secondary',
            'data-reload-text' => $preload];
        $html = html_writer::empty_tag('input', $attributes);

        $this->page->requires->js_call_amd(
            'qtype_coderunner/resetbutton',
            'initResetButton',
            [$buttonid,
                  $responsefieldid,
                  get_string('confirmreset', 'qtype_coderunner'),
            ]
        );

        return $html;
    }
}
