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

defined('MOODLE_INTERNAL') || die();

/*
 * Defines the editing form for the coderunner question type.
 *
 * @package    qtype_coderunner
 * @copyright 	&copy; 2013 Richard Lobb
 * @author 		Richard Lobb richard.lobb@canterbury.ac.nz
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');

use qtype_coderunner\constants;

/**
 * CodeRunner editing form definition.
 */
class qtype_coderunner_edit_form extends question_edit_form {
    const NUM_TESTCASES_START = 5;  // Num empty test cases with new questions.
    const NUM_TESTCASES_ADD = 3;    // Extra empty test cases to add.
    const DEFAULT_NUM_ROWS = 18;    // Answer box rows.
    const DEFAULT_NUM_COLS = 100;   // Answer box columns.
    const TEMPLATE_PARAM_ROWS = 5;  // The number of rows of the template parameter field.
    const UI_PARAM_ROWS = 5;  // The number of rows of the template parameter field.
    const RESULT_COLUMNS_SIZE = 80; // The size of the resultcolumns field.

    /** @var string The ace language. */
    private $acelang;

    /** @var string The language of the question. */
    private $lang;

    /** @var qtype_coderunner_question */
    private $formquestion;

    /** @var string */
    private $cacheduiparamsjson;

    public function qtype() {
        return 'coderunner';
    }

    // Define the CodeRunner question edit form.
    protected function definition() {
        global $PAGE;
        $mform = $this->_form;

        if (!empty($this->question->options->language)) {
            $this->lang = $this->acelang = $this->question->options->language;
        } else {
            $this->lang = $this->acelang = '';
        }
        if (!empty($this->question->options->acelang)) {
            $this->acelang = $this->question->options->acelang;
        }
        $this->make_error_div($mform);
        $this->make_questiontype_panel($mform);
        $this->make_questiontype_help_panel($mform);
        $this->make_customisation_panel($mform);
        $this->make_advanced_customisation_panel($mform);
        qtype_coderunner_util::load_ace();

        $PAGE->requires->js_call_amd('qtype_coderunner/textareas', 'setupAllTAs');
        $PAGE->requires->js_call_amd('qtype_coderunner/authorform', 'initEditForm');

        parent::definition($mform);  // The superclass adds the "General" stuff.
    }


    // Defines the bit of the CodeRunner question edit form after the "General"
    // section and before the footer stuff.
    public function definition_inner($mform) {
        $this->add_sample_answer_field($mform);
        $this->add_preload_answer_field($mform);
        $this->add_globalextra_field($mform);

        if (isset($this->question->options->testcases)) {
            $numtestcases = count($this->question->options->testcases);
        } else {
            $numtestcases = self::NUM_TESTCASES_START;
        }

        // Confusion alert! A call to $mform->setDefault("mark[$i]", '1.0') looks
        // plausible and works to set the empty-form default, but it then
        // overrides (rather than is overridden by) the actual value. The same
        // thing happens with $repeatedoptions['mark']['default'] = 1.000 in
        // get_per_testcase_fields (q.v.).
        // I don't understand this (but see 'Evil hack alert' in the baseclass).
        // MY EVIL HACK ALERT (OLD: probably out of date ) -- setting just $numTestcases default values
        // fails when more test cases are added on the fly. So I've set up
        // enough defaults to handle 5 successive adding of more test cases.
        // I believe this is a bug in the underlying Moodle question type, not
        // mine, but ... how to be sure?
        $mform->setDefault('mark', array_fill(0, $numtestcases + 5 * self::NUM_TESTCASES_ADD, 1.0));
        $ordering = [];
        for ($i = 0; $i < $numtestcases + 5 * self::NUM_TESTCASES_ADD; $i++) {
            $ordering[] = 10 * ($i + 1);
        }
        $mform->setDefault('ordering', $ordering);

        $this->add_per_testcase_fields(
            $mform,
            get_string('testcase', 'qtype_coderunner', "{no}"),
            $numtestcases
        );

        // Add the option to attach runtime support files, all of which are
        // copied into the working directory when the expanded template is
        // executed. The file context is that of the current course.
        $options = $this->fileoptions;
        $options['subdirs'] = false;
        $mform->addElement(
            'header',
            'fileheader',
            get_string('fileheader', 'qtype_coderunner')
        );
        $mform->addElement(
            'filemanager',
            'datafiles',
            get_string('datafiles', 'qtype_coderunner'),
            null,
            $options
        );
        $mform->addHelpButton('datafiles', 'datafiles', 'qtype_coderunner');

        // Insert the attachment section to allow file uploads.
        $qtype = question_bank::get_qtype('coderunner');
        $mform->addElement('header', 'attachmentoptions', get_string('attachmentoptions', 'qtype_coderunner'));
        $mform->setExpanded('attachmentoptions', 0);

        $mform->addElement(
            'select',
            'attachments',
            get_string('allowattachments', 'qtype_coderunner'),
            $qtype->attachment_options()
        );
        $mform->setDefault('attachments', 0);
        $mform->addHelpButton('attachments', 'allowattachments', 'qtype_coderunner');

        $mform->addElement(
            'select',
            'attachmentsrequired',
            get_string('attachmentsrequired', 'qtype_coderunner'),
            $qtype->attachments_required_options()
        );
        $mform->setDefault('attachmentsrequired', 0);
        $mform->addHelpButton('attachmentsrequired', 'attachmentsrequired', 'qtype_coderunner');
        $mform->disabledIf('attachmentsrequired', 'attachments', 'eq', 0);

        $filenamecontrols = [];
        $filenamecontrols[] = $mform->createElement(
            'text',
            'filenamesregex',
            get_string('filenamesregex', 'qtype_coderunner')
        );
        $mform->disabledIf('filenamesregex', 'attachments', 'eq', 0);
        $mform->setType('filenamesregex', PARAM_RAW);
        $mform->setDefault('filenamesregex', '');
        $filenamecontrols[] = $mform->createElement(
            'text',
            'filenamesexplain',
            get_string('filenamesexplain', 'qtype_coderunner')
        );
        $mform->disabledIf('filenamesexplain', 'attachments', 'eq', 0);
        $mform->setType('filenamesexplain', PARAM_RAW);
        $mform->setDefault('filenamesexplain', '');
        $mform->addElement(
            'group',
            'filenamesgroup',
            get_string('allowedfilenames', 'qtype_coderunner'),
            $filenamecontrols,
            null,
            false
        );
        $mform->addHelpButton('filenamesgroup', 'allowedfilenames', 'qtype_coderunner');

        $mform->addElement(
            'select',
            'maxfilesize',
            get_string('maxfilesize', 'qtype_coderunner'),
            $qtype->attachment_filesize_max()
        );
        $mform->addHelpButton('maxfilesize', 'maxfilesize', 'qtype_coderunner');
                $mform->setDefault('maxfilesize', '10240');
        $mform->disabledIf('maxfilesize', 'attachments', 'eq', 0);
    }


    public function get_data() {
        $fields = parent::get_data();
        if ($fields) {
            $fields->templateparamsevald = $this->formquestion->templateparamsevald;
        }
        return $fields;
    }

    /**
     * Add a field for a sample answer to this problem (optional)
     * @param object $mform the form being built
     */
    protected function add_sample_answer_field($mform) {
        global $CFG;
        $mform->addElement(
            'header',
            'answerhdr',
            get_string('answer', 'qtype_coderunner'),
            ''
        );
        $mform->setExpanded('answerhdr', 1);

        $attributes = [
            'rows' => 9,
            'class' => 'answer edit_code',
            'data-params' => $this->get_merged_ui_params(),
            'data-lang' => $this->acelang];
        $mform->addElement(
            'textarea',
            'answer',
            get_string('answer', 'qtype_coderunner'),
            $attributes
        );
        // Add a file attachment upload panel (disabled if attachments not allowed).
        $options = $this->fileoptions;
        $options['subdirs'] = false;
        $mform->addElement(
            'filemanager',
            'sampleanswerattachments',
            get_string('sampleanswerattachments', 'qtype_coderunner'),
            null,
            $options
        );
        $mform->addHelpButton('sampleanswerattachments', 'sampleanswerattachments', 'qtype_coderunner');
        // Unless behat is running, hide the attachments file picker.
        // behat barfs if it's hidden.
        if (!qtype_coderunner_sandbox::is_using_test_sandbox()) {
            $method = method_exists($mform, 'hideIf') ? 'hideIf' : 'disabledIf';
            $mform->$method('sampleanswerattachments', 'attachments', 'eq', 0);
        }
        $mform->addElement(
            'advcheckbox',
            'validateonsave',
            null,
            get_string('validateonsave', 'qtype_coderunner')
        );
        $mform->setDefault('validateonsave', true);
        $mform->addHelpButton('answer', 'answer', 'qtype_coderunner');
    }

    /**
     * Add a field for a text to be preloaded into the answer box.
     * @param object $mform the form being built
     */
    protected function add_preload_answer_field($mform) {
        $mform->addElement(
            'header',
            'answerpreloadhdr',
            get_string('answerpreload', 'qtype_coderunner'),
            ''
        );
        $expanded = !empty($this->formquestion->options->answerpreload);
        $mform->setExpanded('answerpreloadhdr', $expanded);
        $attributes = [
            'rows' => 5,
            'class' => 'preloadanswer edit_code',
            'data-params' => $this->get_merged_ui_params(),
            'data-lang' => $this->acelang];
        $mform->addElement(
            'textarea',
            'answerpreload',
            get_string('answerpreload', 'qtype_coderunner'),
            $attributes
        );
        $mform->addHelpButton('answerpreload', 'answerpreload', 'qtype_coderunner');
    }

    /**
     * Add a field to contain extra text for use by template authors, global
     * to all tests.
     * @param object $mform the form being built
     */
    protected function add_globalextra_field($mform) {
        $mform->addElement(
            'header',
            'globalextrahdr',
            get_string('globalextra', 'qtype_coderunner'),
            ''
        );
        $expanded = !empty($this->question->options->globalextra);
        $mform->setExpanded('globalextrahdr', $expanded);
        $attributes = [
            'rows' => 5,
            'class' => 'globalextra edit_code'];
        $mform->addElement(
            'textarea',
            'globalextra',
            get_string('globalextra', 'qtype_coderunner'),
            $attributes
        );
        $mform->addHelpButton('globalextra', 'globalextra', 'qtype_coderunner');
    }

    /*
     * Add a set of form fields, obtained from get_per_test_fields, to the form,
     * one for each existing testcase, with some blanks for some new ones
     * This overrides the base-case version because we're dealing with test
     * cases, not answers.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $gradeoptions the possible grades for each answer.
     * @param $minoptions the minimum number of testcase blanks to display.
     *      Default QUESTION_NUMANS_START.
     * @param $addoptions the number of testcase blanks to add. Default QUESTION_NUMANS_ADD.
     */
    protected function add_per_testcase_fields($mform, $label, $numtestcases) {
        $mform->addElement(
            'header',
            'testcasehdr',
            get_string('testcases', 'qtype_coderunner'),
            ''
        );
        $mform->setExpanded('testcasehdr', 1);
        $repeatedoptions = [];
        $repeated = $this->get_per_testcase_fields($mform, $label, $repeatedoptions);
        $this->repeat_elements(
            $repeated,
            $numtestcases,
            $repeatedoptions,
            'numtestcases',
            'addanswers',
            QUESTION_NUMANS_ADD,
            $this->get_more_choices_string(),
            true
        );
        $n = $numtestcases + QUESTION_NUMANS_ADD;
        for ($i = 0; $i < $n; $i++) {
            $mform->disabledIf("mark[$i]", 'allornothing', 'checked');
        }
    }


    /*
     *  A rewritten version of get_per_answer_fields specific to test cases.
     */
    public function get_per_testcase_fields($mform, $label, &$repeatedoptions) {
        $repeated = [];
        $repeated[] = $mform->createElement(
            'textarea',
            'testcode',
            $label,
            ['rows' => 3, 'class' => 'testcaseexpression edit_code']
        );
        $repeated[] = $mform->createElement(
            'textarea',
            'stdin',
            get_string('stdin', 'qtype_coderunner'),
            ['rows' => 3, 'class' => 'testcasestdin edit_code']
        );
        $repeated[] = $mform->createElement(
            'textarea',
            'expected',
            get_string('expected', 'qtype_coderunner'),
            ['rows' => 3, 'class' => 'testcaseresult edit_code']
        );

        $repeated[] = $mform->createElement(
            'textarea',
            'extra',
            get_string('extra', 'qtype_coderunner'),
            ['rows' => 3, 'class' => 'testcaseresult edit_code']
        );
        $group[] = $mform->createElement(
            'checkbox',
            'useasexample',
            null,
            get_string('useasexample', 'qtype_coderunner')
        );

        $options = [];
        foreach ($this->displayoptions() as $opt) {
            $options[$opt] = get_string($opt, 'qtype_coderunner');
        }

        $group[] = $mform->createElement(
            'select',
            'display',
            get_string('display', 'qtype_coderunner'),
            $options
        );
        $group[] = $mform->createElement(
            'checkbox',
            'hiderestiffail',
            null,
            get_string('hiderestiffail', 'qtype_coderunner')
        );
        $group[] = $mform->createElement(
            'text',
            'mark',
            get_string('mark', 'qtype_coderunner'),
            ['size' => 5, 'class' => 'testcasemark']
        );
        $group[] = $mform->createElement(
            'text',
            'ordering',
            get_string('ordering', 'qtype_coderunner'),
            ['size' => 3, 'class' => 'testcaseordering']
        );

        $repeated[] = $mform->createElement(
            'group',
            'testcasecontrols',
            get_string('testcasecontrols', 'qtype_coderunner'),
            $group,
            null,
            false
        );

        $typevalues = [
            constants::TESTTYPE_NORMAL   => get_string('testtype_normal', 'qtype_coderunner'),
            constants::TESTTYPE_PRECHECK => get_string('testtype_precheck', 'qtype_coderunner'),
            constants::TESTTYPE_BOTH     => get_string('testtype_both', 'qtype_coderunner'),
        ];

        $repeated[] = $mform->createElement(
            'select',
            'testtype',
            get_string('testtype', 'qtype_coderunner'),
            $typevalues,
            ['class' => 'testtype']
        );

        $repeatedoptions['expected']['type'] = PARAM_RAW;
        $repeatedoptions['testcode']['type'] = PARAM_RAW;
        $repeatedoptions['stdin']['type'] = PARAM_RAW;
        $repeatedoptions['extra']['type'] = PARAM_RAW;
        $repeatedoptions['mark']['type'] = PARAM_FLOAT;
        $repeatedoptions['ordering']['type'] = PARAM_INT;
        $repeatedoptions['testtype']['type'] = PARAM_RAW;

        foreach (['testcode', 'stdin', 'expected', 'extra', 'testcasecontrols', 'testtype'] as $field) {
            $repeatedoptions[$field]['helpbutton'] = [$field, 'qtype_coderunner'];
        }

        // Here I expected to be able to use: $repeatedoptions['mark']['default'] = 1.000
        // but it doesn't work. See "Confusion alert" in definition_inner.

        return $repeated;
    }


    // A list of the allowed values of the DB 'display' field for each testcase.
    protected function displayoptions() {
        return ['SHOW', 'HIDE', 'HIDE_IF_FAIL', 'HIDE_IF_SUCCEED'];
    }


    public function data_preprocessing($question) {
        // Preprocess the question data to be loaded into the form. Called by set_data after
        // standard stuff all loaded.
        // TODO - consider how much of this can be dispensed with just by
        // calling question_bank::loadquestion($question->id).
        global $COURSE;

        if (isset($question->options->testcases)) { // Reloading a saved question?
            $q = $this->make_question_from_form_data($question);
            // Loads the error messages into the brokenquestionmessage.
            $question->brokenquestionmessage = $this->load_error_messages($question, $q);

            // Record the prototype for subsequent use.
            $question->prototype = $q->prototype;

            // Next flatten all the question->options down into the question itself.
            $question->testcode = [];
            $question->expected = [];
            $question->useasexample = [];
            $question->display = [];
            $question->extra = [];
            $question->hiderestifail = [];

            foreach ($question->options->testcases as $tc) {
                $question->testcode[] = $this->newline_hack($tc->testcode);
                $question->testtype[] = $tc->testtype;
                $question->stdin[] = $this->newline_hack($tc->stdin);
                $question->expected[] = $this->newline_hack($tc->expected);
                $question->extra[] = $this->newline_hack($tc->extra);
                $question->useasexample[] = $tc->useasexample;
                $question->display[] = $tc->display;
                $question->hiderestiffail[] = $tc->hiderestiffail;
                $question->mark[] = sprintf("%.3f", $tc->mark);
            }

            // The customise field isn't listed as an extra-question-field so also
            // needs to be copied down from the options here.
            $question->customise = $question->options->customise;

            // Save the prototypetype and value so can see if it changed on post-back.
            $question->saved_prototype_type = $question->prototypetype;
            $question->courseid = $COURSE->id;

            // Load the type-name if this is a prototype, else make it blank.
            if ($question->prototypetype != 0) {
                $question->typename = $question->coderunnertype;
            } else {
                $question->typename = '';
            }

            // Convert raw newline chars in testsplitterre into 2-char form
            // so they can be edited in a one-line entry field.
            if (isset($question->testsplitterre)) {
                $question->testsplitterre = str_replace("\n", '\n', $question->testsplitterre);
            }

            // Legacy questions may have a question.penalty but no penalty regime.
            // Dummy up a penalty regime from the question.penalty in such cases.
            if (empty($question->penaltyregime)) {
                if (empty($question->penalty) || $question->penalty == 0) {
                    $question->penaltyregime = '0';
                } else {
                    if (intval(100 * $question->penalty) == 100 * $question->penalty) {
                        $decdigits = 0;
                    } else {
                        $decdigits = 1;  // For nasty fractions like 0.33333333.
                    }
                    $penaltypercent = number_format($question->penalty * 100, $decdigits);
                    $penaltypercent2 = number_format($question->penalty * 200, $decdigits);
                    $question->penaltyregime = $penaltypercent . ', ' . $penaltypercent2 . ', ...';
                }
            }
        } else {
            // This is a new question.
            $question->penaltyregime = get_config('qtype_coderunner', 'default_penalty_regime');
        }

        foreach (
            ['datafiles' => 'datafile',
                'sampleanswerattachments' => 'samplefile'] as $fileset => $filearea
        ) {
            $draftid = file_get_submitted_draft_itemid($fileset);
            $options = $this->fileoptions;
            $options['subdirs'] = false;

            file_prepare_draft_area(
                $draftid,
                $this->context->id,
                'qtype_coderunner',
                $filearea,
                empty($question->id) ? null : (int) $question->id,
                $options
            );
            $question->$fileset = $draftid; // File manager needs this (and we need it when saving).
        }
        return $question;
    }


    // A horrible hack for a horrible browser "feature".
    // Inserts a newline at the start of a text string that's going to be
    // displayed at the start of a <textarea> element, because all browsers
    // strip a leading newline. If there's one there, we need to keep it, so
    // the extra one ensures we do. If there isn't one there, this one gets
    // ignored anyway.
    private function newline_hack($s) {
        return "\n" . $s;
    }

    /**
     * Loads error messages to be put into brokenquestionmessage of the question if needed.
     * Returns a string of the message to be inserted.
     *
     * @param type $question Object with all the question data within.
     * @param type $q Object with the new question data within.
     */
    private function load_error_messages($question, $q) {
        $errorstring = "";
        // Firstly check if we're editing a question with a missing prototype or duplicates.
        // Set the broken_question message if so.
        if ($q->prototype === null) {
            $errorstring = get_string(
                'missingprototype',
                'qtype_coderunner',
                ['crtype' => $question->coderunnertype]
            );
        } else if (is_array($q->prototype)) {
            $outputstring = "</p>";
            // Output every duplicate Question id, name and category.
            foreach ($q->prototype as $component) {
                $outputstring .= get_string(
                    'listprototypeduplicates',
                    'qtype_coderunner',
                    ['id' => $component->id, 'name' => $component->name, 'category' => $component->category]
                );
            }
            $errorstring = get_string(
                'duplicateprototype',
                'qtype_coderunner',
                ['crtype' => $question->coderunnertype,
                'outputstring' => $outputstring]
            );
        }
        return $errorstring;
    }


    // FUNCTIONS TO BUILD PARTS OF THE MAIN FORM
    // =========================================.

    // Create 2 empty divs with id id__qtype_coderunner_warning_div, id_qtype_coderunner_error_div for use by
    // JavaScript error handling code.
    private function make_error_div($mform) {
        $mform->addElement('html', "<div id='id_qtype_coderunner_warning_div' class='qtype_coderunner_warning_message'></div>");
        $mform->addElement('html', "<div id='id_qtype_coderunner_error_div' class='qtype_coderunner_error_message'></div>");
    }

    // Add to the supplied $mform the panel "Coderunner question type".
    private function make_questiontype_panel($mform) {
        [, $types] = $this->get_languages_and_types();
        $hidemethod = method_exists($mform, 'hideIf') ? 'hideIf' : 'disabledIf';

        $mform->addElement('header', 'questiontypeheader', get_string('type_header', 'qtype_coderunner'));

        // Insert the (possible) bad question load message as a hidden field before broken question. JavaScript
        // will be used to show it if non-empty.
        $mform->addElement(
            'hidden',
            'badquestionload',
            '',
            ['id' => 'id_bad_question_load', 'class' => 'badquestionload']
        );
        $mform->setType('badquestionload', PARAM_RAW);

        // Insert the (possible) missing prototype message as a hidden field. JavaScript
        // will be used to show it if non-empty.
        $mform->addElement(
            'hidden',
            'brokenquestionmessage',
            '',
            ['id' => 'id_broken_question', 'class' => 'brokenquestionerror']
        );
        $mform->setType('brokenquestionmessage', PARAM_RAW);

        // The Question Type controls (a group with the question type and the warning, if it is one).
        $typeselectorelements = [];
        $expandedtypes = array_merge(['Undefined' => 'Undefined'], $types);
        $typeselectorelements[] = $mform->createElement(
            'select',
            'coderunnertype',
            null,
            $expandedtypes
        );
        $prototypelangstring = get_string('prototypeexists', 'qtype_coderunner');
        $typeselectorelements[] = $mform->createElement(
            'html',
            "<div id='id_isprototype' class='qtype_coderunner_prototype_message' hidden>"
            . "{$prototypelangstring}</div>"
        );
        $mform->addElement(
            'group',
            'coderunner_type_group',
            get_string('coderunnertype', 'qtype_coderunner'),
            $typeselectorelements,
            null,
            false
        );
        $mform->addHelpButton('coderunner_type_group', 'coderunnertype', 'qtype_coderunner');

        // Customisation checkboxes.
        $typeselectorcheckboxes = [];
        $typeselectorcheckboxes[] = $mform->createElement(
            'advcheckbox',
            'customise',
            null,
            get_string('customise', 'qtype_coderunner')
        );
        $typeselectorcheckboxes[] = $mform->createElement(
            'advcheckbox',
            'showsource',
            null,
            get_string('showsource', 'qtype_coderunner')
        );
        $mform->setDefault('showsource', false);
        $mform->addElement(
            'group',
            'coderunner_type_checkboxes',
            get_string('questioncheckboxes', 'qtype_coderunner'),
            $typeselectorcheckboxes,
            null,
            false
        );
        $mform->addHelpButton('coderunner_type_checkboxes', 'questioncheckboxes', 'qtype_coderunner');

        // Answerbox controls.
        $answerboxelements = [];
        $answerboxelements[] = $mform->createElement(
            'text',
            'answerboxlines',
            get_string('answerboxlines', 'qtype_coderunner'),
            ['size' => 3, 'class' => 'coderunner_answerbox_size']
        );
        $mform->setType('answerboxlines', PARAM_INT);
        $mform->setDefault('answerboxlines', self::DEFAULT_NUM_ROWS);
        $mform->addElement(
            'group',
            'answerbox_group',
            get_string('answerbox_group', 'qtype_coderunner'),
            $answerboxelements,
            null,
            false
        );
        $mform->addHelpButton('answerbox_group', 'answerbox_group', 'qtype_coderunner');

        // Precheck control group (precheck + hide check).
        $precheckelements = [];
        $precheckvalues = [
            constants::PRECHECK_DISABLED => get_string('precheck_disabled', 'qtype_coderunner'),
            constants::PRECHECK_EMPTY    => get_string('precheck_empty', 'qtype_coderunner'),
            constants::PRECHECK_EXAMPLES => get_string('precheck_examples', 'qtype_coderunner'),
            constants::PRECHECK_SELECTED => get_string('precheck_selected', 'qtype_coderunner'),
            constants::PRECHECK_ALL      => get_string('precheck_all', 'qtype_coderunner'),
        ];
        $precheckelements[] = $mform->createElement(
            'select',
            'precheck',
            get_string('precheck', 'qtype_coderunner'),
            $precheckvalues
        );
        $precheckelements[] = $mform->createElement(
            'advcheckbox',
            'hidecheck',
            null,
            get_string('hidecheck', 'qtype_coderunner')
        );
        $mform->addElement(
            'group',
            'coderunner_precheck_group',
            get_string('submitbuttons', 'qtype_coderunner'),
            $precheckelements,
            null,
            false
        );
        $mform->addHelpButton('coderunner_precheck_group', 'precheck', 'qtype_coderunner');

        // Whether to show the 'Stop and read feedback' button.
        $giveupelements = [];
        $giveupvalues = [
                constants::GIVEUP_NEVER => get_string('giveup_never', 'qtype_coderunner'),
                constants::GIVEUP_AFTER_MAX_MARKS => get_string('giveup_aftermaxmarks', 'qtype_coderunner'),
                constants::GIVEUP_ALWAYS => get_string('giveup_always', 'qtype_coderunner'),
        ];

        $giveupelements[] = $mform->createElement('select', 'giveupallowed', null, $giveupvalues);
        $mform->addElement(
            'group',
            'coderunner_giveup_group',
            get_string('giveup', 'qtype_coderunner'),
            $giveupelements,
            null,
            false
        );
        $mform->addHelpButton('coderunner_giveup_group', 'giveup', 'qtype_coderunner');
        $mform->setDefault('giveupallowed', constants::GIVEUP_NEVER);

        // Feedback control (a group with only one element).
        $feedbackelements = [];
        $feedbackvalues = [
            constants::FEEDBACK_USE_QUIZ => get_string('feedback_quiz', 'qtype_coderunner'),
            constants::FEEDBACK_SHOW    => get_string('feedback_show', 'qtype_coderunner'),
            constants::FEEDBACK_HIDE => get_string('feedback_hide', 'qtype_coderunner'),
        ];

        $feedbackelements[] = $mform->createElement('select', 'displayfeedback', null, $feedbackvalues);
        $mform->addElement(
            'group',
            'coderunner_feedback_group',
            get_string('feedback', 'qtype_coderunner'),
            $feedbackelements,
            null,
            false
        );
        $mform->addHelpButton('coderunner_feedback_group', 'feedback', 'qtype_coderunner');
        $mform->setDefault('displayfeedback', constants::FEEDBACK_SHOW);
        $mform->setType('displayfeedback', PARAM_INT);

        // Marking controls.
        $markingelements = [];
        $markingelements[] = $mform->createElement(
            'advcheckbox',
            'allornothing',
            null,
            get_string('allornothing', 'qtype_coderunner')
        );
        $markingelements[] = $mform->CreateElement(
            'text',
            'penaltyregime',
            get_string('penaltyregimelabel', 'qtype_coderunner'),
            ['size' => 20]
        );
        $mform->addElement(
            'group',
            'markinggroup',
            get_string('markinggroup', 'qtype_coderunner'),
            $markingelements,
            null,
            false
        );
        $mform->setDefault('allornothing', true);
        $mform->setType('penaltyregime', PARAM_RAW);
        $mform->addHelpButton('markinggroup', 'markinggroup', 'qtype_coderunner');

        // Template params.
        $mform->addElement(
            'textarea',
            'templateparams',
            get_string('templateparams', 'qtype_coderunner'),
            ['rows' => self::TEMPLATE_PARAM_ROWS,
                  'class' => 'edit_code',
                  'data-lang' => '', // Don't syntax colour template params.
            ]
        );
        $mform->setType('templateparams', PARAM_RAW);
        $mform->addHelpButton('templateparams', 'templateparams', 'qtype_coderunner');

        // Twig controls.
        $twigelements = [];
        $twigelements[] = $mform->createElement(
            'advcheckbox',
            'hoisttemplateparams',
            null,
            get_string('hoisttemplateparams', 'qtype_coderunner')
        );
        $twigelements[] = $mform->createElement(
            'advcheckbox',
            'extractcodefromjson',
            null,
            get_string('extractcodefromjson', 'qtype_coderunner')
        );
        $twigelements[] = $mform->createElement(
            'advcheckbox',
            'twigall',
            null,
            get_string('twigall', 'qtype_coderunner')
        );
        $templateparamlangs = [
            'None' => 'None',
            'twig' => 'Twig',
            'python3' => 'Python3',
            'c' => 'C',
            'cpp' => 'C++',
            'java' => 'Java',
            'php' => 'php',
            'octave' => 'Octave',
            'pascal' => 'Pascal',
            ];
        $twigelements[] = $mform->createElement(
            'select',
            'templateparamslang',
            get_string('templateparamslang', 'qtype_coderunner'),
            $templateparamlangs
        );
        $twigelements[] = $mform->createElement(
            'advcheckbox',
            'templateparamsevalpertry',
            null,
            get_string('templateparamsevalpertry', 'qtype_coderunner')
        );
        $mform->addElement(
            'group',
            'twigcontrols',
            get_string('twigcontrols', 'qtype_coderunner'),
            $twigelements,
            null,
            false
        );
        $mform->setDefault('templateparamslang', 'None');
        $mform->setDefault('templateparamsevalpertry', false);
        $mform->setDefault('twigall', false);
        $mform->$hidemethod('templateparamsevalpertry', 'templateparamslang', 'eq', 'None');
        $mform->$hidemethod('templateparamsevalpertry', 'templateparamslang', 'eq', 'twig');
        $mform->setDefault('hoisttemplateparams', true);
        $mform->setDefault('extractcodefromjson', true);
        $mform->addHelpButton('twigcontrols', 'twigcontrols', 'qtype_coderunner');

        // UI parameters.
        $plugins = qtype_coderunner_ui_plugins::get_instance();
        $uielements = [];
        $uiparamedescriptionhtml = '<div class="ui_parameters_descr"></div>'; // JavaScript fills this.
        $uielements[] = $mform->createElement('html', $uiparamedescriptionhtml);
        $uielements[] = $mform->createElement(
            'textarea',
            'uiparameters',
            get_string('uiparameters', 'qtype_coderunner'),
            ['rows' => self::UI_PARAM_ROWS,
                  'class' => 'edit_code',
                  'data-lang' => '', // Don't syntax colour ui params.
            ]
        );
        $mform->setType('uiparameters', PARAM_RAW);

        $mform->addElement(
            'group',
            'uiparametergroup',
            get_string('uiparametergroup', 'qtype_coderunner'),
            $uielements,
            null,
            false
        );
        $mform->addHelpButton('uiparametergroup', 'uiparametergroup', 'qtype_coderunner');
    }


    // Add to the supplied $mform the question-type help panel.
    // This displays the text of the currently-selected prototype.
    private function make_questiontype_help_panel($mform) {
        $mform->addElement(
            'header',
            'questiontypehelpheader',
            get_string('questiontypedetails', 'qtype_coderunner')
        );
        $nodetailsavailable = '<span id="qtype-help">' . get_string('nodetailsavailable', 'qtype_coderunner') . '</span>';
        $mform->addElement('html', $nodetailsavailable);
    }

    // Add to the supplied $mform the Customisation Panel
    // The panel is hidden by default but exposed when the user clicks
    // the 'Customise' checkbox in the question-type panel.
    private function make_customisation_panel($mform) {
        // The following fields are used to customise a question by overriding
        // values from the base question type. All are hidden
        // unless the 'customise' checkbox is checked.

        $mform->addElement(
            'header',
            'customisationheader',
            get_string('customisation', 'qtype_coderunner')
        );
        $attributes = ['rows'  => 8,
            'class' => 'template edit_code',
            'name'  => 'template',
            'data-lang' => $this->lang];
        $mform->addElement(
            'textarea',
            'template',
            get_string('template', 'qtype_coderunner'),
            $attributes
        );
        $mform->addHelpButton('template', 'template', 'qtype_coderunner');

        $templatecontrols = [];
        $templatecontrols[] = $mform->createElement(
            'advcheckbox',
            'iscombinatortemplate',
            null,
            get_string('iscombinatortemplate', 'qtype_coderunner')
        );
        $templatecontrols[] = $mform->createElement(
            'advcheckbox',
            'allowmultiplestdins',
            null,
            get_string('allowmultiplestdins', 'qtype_coderunner')
        );

        $templatecontrols[] = $mform->createElement(
            'text',
            'testsplitterre',
            get_string('testsplitterre', 'qtype_coderunner'),
            ['size' => 45]
        );
        $mform->setType('testsplitterre', PARAM_RAW);
        $mform->addElement(
            'group',
            'templatecontrols',
            get_string('templatecontrols', 'qtype_coderunner'),
            $templatecontrols,
            null,
            false
        );
        $mform->addHelpButton('templatecontrols', 'templatecontrols', 'qtype_coderunner');

        $gradingcontrols = [];
        $gradertypes = ['EqualityGrader' => get_string('equalitygrader', 'qtype_coderunner'),
                'NearEqualityGrader' => get_string('nearequalitygrader', 'qtype_coderunner'),
                'RegexGrader'    => get_string('regexgrader', 'qtype_coderunner'),
                'TemplateGrader' => get_string('templategrader', 'qtype_coderunner')];
        $gradingcontrols[] = $mform->createElement('select', 'grader', null, $gradertypes);
        $mform->addElement(
            'group',
            'gradingcontrols',
            get_string('grading', 'qtype_coderunner'),
            $gradingcontrols,
            null,
            false
        );
        $mform->addHelpButton('gradingcontrols', 'gradingcontrols', 'qtype_coderunner');

        $mform->addElement(
            'text',
            'resultcolumns',
            get_string('resultcolumns', 'qtype_coderunner'),
            ['size' => self::RESULT_COLUMNS_SIZE]
        );
        $mform->setType('resultcolumns', PARAM_RAW);
        $mform->addHelpButton('resultcolumns', 'resultcolumns', 'qtype_coderunner');

        $uicontrols = [];
        $plugins = qtype_coderunner_ui_plugins::get_instance();
        $uitypes = $plugins->dropdownlist();

        $uicontrols[] = $mform->createElement(
            'select',
            'uiplugin',
            get_string('student_answer', 'qtype_coderunner'),
            $uitypes
        );
        $mform->setDefault('uiplugin', 'ace');
        $uicontrols[] = $mform->createElement(
            'advcheckbox',
            'useace',
            null,
            get_string('useace', 'qtype_coderunner')
        );
        $mform->setDefault('useace', true);
        $mform->addElement(
            'group',
            'uicontrols',
            get_string('uicontrols', 'qtype_coderunner'),
            $uicontrols,
            null,
            false
        );
        $mform->addHelpButton('uicontrols', 'uicontrols', 'qtype_coderunner');

        $attributes = [
            'rows' => 5,
            'class' => 'prototypeextra edit_code'];
        $mform->addElement(
            'textarea',
            'prototypeextra',
            get_string('prototypeextra', 'qtype_coderunner'),
            $attributes
        );
        $mform->addHelpButton('prototypeextra', 'prototypeextra', 'qtype_coderunner');

        $mform->setExpanded('customisationheader');  // Although expanded it's hidden until JavaScript unhides it .
    }


    // Make the advanced customisation panel, also hidden until the user
    // customises the question. The fields in this part of the form are much more
    // advanced and not recommended for most users.
    private function make_advanced_customisation_panel($mform) {
        $mform->addElement(
            'header',
            'advancedcustomisationheader',
            get_string('advanced_customisation', 'qtype_coderunner')
        );

        $prototypecontrols = [];

        $prototypeselect = $mform->createElement(
            'select',
            'prototypetype',
            get_string('prototypeQ', 'qtype_coderunner')
        );
        $prototypeselect->addOption('No', '0');
        $prototypeselect->addOption('Yes (built-in)', '1', ['disabled' => 'disabled']);
        $prototypeselect->addOption('Yes (user defined)', '2');
        $prototypecontrols[] = $prototypeselect;
        $prototypecontrols[] = $mform->createElement(
            'text',
            'typename',
            get_string('typename', 'qtype_coderunner'),
            ['size' => 30]
        );
        $mform->addElement(
            'group',
            'prototypecontrols',
            get_string('prototypecontrols', 'qtype_coderunner'),
            $prototypecontrols,
            null,
            false
        );
        $mform->setDefault('is_prototype', false);
        $mform->setType('typename', PARAM_RAW_TRIMMED);
        $mform->addElement('hidden', 'saved_prototype_type');
        $mform->setType('saved_prototype_type', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('prototypecontrols', 'prototypecontrols', 'qtype_coderunner');

        $sandboxcontrols = [];

        $enabled = qtype_coderunner_sandbox::enabled_sandboxes();
        if (count($enabled) > 1) {
            $sandboxes = array_merge(['DEFAULT' => 'DEFAULT'], $enabled);
            foreach (array_keys($sandboxes) as $ext) {
                $sandboxes[$ext] = $ext;
            }

            $sandboxcontrols[] = $mform->createElement('select', 'sandbox', null, $sandboxes);
        } else {
            $sandboxcontrols[] = $mform->createElement('hidden', 'sandbox', 'DEFAULT');
            $mform->setType('sandbox', PARAM_RAW);
        }

        $sandboxcontrols[] = $mform->createElement(
            'text',
            'cputimelimitsecs',
            get_string('cputime', 'qtype_coderunner'),
            ['size' => 3]
        );
        $sandboxcontrols[] = $mform->createElement(
            'text',
            'memlimitmb',
            get_string('memorylimit', 'qtype_coderunner'),
            ['size' => 5]
        );
        $sandboxcontrols[] = $mform->createElement(
            'text',
            'sandboxparams',
            get_string('sandboxparams', 'qtype_coderunner'),
            ['size' => 15]
        );
        $mform->addElement(
            'group',
            'sandboxcontrols',
            get_string('sandboxcontrols', 'qtype_coderunner'),
            $sandboxcontrols,
            null,
            false
        );

        $mform->setType('cputimelimitsecs', PARAM_RAW);
        $mform->setType('memlimitmb', PARAM_RAW);
        $mform->setType('sandboxparams', PARAM_RAW);
        $mform->addHelpButton('sandboxcontrols', 'sandboxcontrols', 'qtype_coderunner');

        $languages = [];
        $languages[]  = $mform->createElement(
            'text',
            'language',
            get_string('language', 'qtype_coderunner'),
            ['size' => 10]
        );
        $mform->setType('language', PARAM_RAW_TRIMMED);
        $languages[]  = $mform->createElement(
            'text',
            'acelang',
            get_string('ace-language', 'qtype_coderunner'),
            ['size' => 20]
        );
        $mform->setType('acelang', PARAM_RAW_TRIMMED);
        $mform->addElement(
            'group',
            'languages',
            get_string('languages', 'qtype_coderunner'),
            $languages,
            null,
            false
        );
        $mform->addHelpButton('languages', 'languages', 'qtype_coderunner');

        // IMPORTANT: authorform.js has to set the initial enabled/disabled
        // status of the testsplitterre and allowmultiplestdins elements
        // after loading a new question type as the following code apparently
        // sets up event handlers only for clicks on the iscombinatortemplate
        // checkbox. Note, if disabled, the value doesn't exist, so check
        // properties exist!
        $mform->disabledIf('typename', 'prototypetype', 'neq', '2');
        $mform->disabledIf('testsplitterre', 'iscombinatortemplate', 'eq', 0);
        $mform->disabledIf('allowmultiplestdins', 'iscombinatortemplate', 'eq', 0);
        $mform->disabledIf('coderunnertype', 'prototypetype', 'eq', '2');
    }


    /***********************************************************
     *
     * VALIDATION.
     *
     **********************************************************/

    // Validate the given data and possible files.
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!isset($data['coderunnertype'])) {
            if ($data['prototypetype'] == 2) {
                // If the questiontype is Undefined or non-existent; still good for user prototype.
                $data['coderunnertype'] = $data['typename'];
            } else {
                $data['coderunnertype'] = 'Undefined';
            }
        }
        if ($data['coderunnertype'] == 'Undefined') {
            $errors['coderunner_type_group'] = get_string('questiontype_required', 'qtype_coderunner');
            /* Don't continue checking in these cases, including
               if there isn't a previous coderunnertype (duplicate, missings),
               else template param validation breaks. */
            return $errors;
        }
        $this->formquestion = $this->make_question_from_form_data($data);
        if (
            $data['cputimelimitsecs'] != '' &&
             (!ctype_digit($data['cputimelimitsecs']) || intval($data['cputimelimitsecs']) <= 0)
        ) {
            $errors['sandboxcontrols'] = get_string('badcputime', 'qtype_coderunner');
        }
        if (
            $data['memlimitmb'] != '' &&
             (!ctype_digit($data['memlimitmb']) || intval($data['memlimitmb']) < 0)
        ) {
            $errors['sandboxcontrols'] = get_string('badmemlimit', 'qtype_coderunner');
        }

        if ($data['precheck'] == constants::PRECHECK_EXAMPLES && $this->num_examples($data) === 0) {
            $errors['coderunner_precheck_group'] = get_string('precheckingemptyset', 'qtype_coderunner');
        }

        if (
            $data['sandboxparams'] != '' &&
                json_decode($data['sandboxparams']) === null
        ) {
            $errors['sandboxcontrols'] = get_string('badsandboxparams', 'qtype_coderunner');
        }

        [$templateerrors, $json] = $this->validate_template_params();
        if (!$templateerrors) {
            $this->formquestion->templateparamsevald = $json;
            $this->formquestion->parameters = json_decode($json, true);
        } else {
            $errors['templateparams'] = $templateerrors;
            $this->formquestion->templateparamsevald = '{}';
        }

        if (!$templateerrors && isset($data['uiparameters']) && $data['uiparameters']) {
            $uiparametererrors = $this->validate_ui_parameters($data['uiparameters']);
            if ($uiparametererrors) {
                $errors['uiparametergroup'] = $uiparametererrors;
            }
        }

        if (
            $data['prototypetype'] == 0 && ($data['grader'] !== 'TemplateGrader'
                || $data['iscombinatortemplate'] === false)
        ) {
            // Unless it's a prototype or uses a combinator-template grader,
            // it needs at least one testcase.
            $testcaseerrors = $this->validate_test_cases($data);
            $errors = array_merge($errors, $testcaseerrors);
        }

        if ($data['prototypetype'] == 2 && empty($data['language'])) {
            // Language cannot be empty when it is a prototype template.
            $errors['languages'] = get_string('emptysandboxlanguage', 'qtype_coderunner');
        }

        if ($data['iscombinatortemplate'] && empty($data['testsplitterre'])) {
            $errors['templatecontrols'] = get_string('bad_empty_splitter', 'qtype_coderunner');
        }

        if (
            $data['prototypetype'] == 2 && ($data['saved_prototype_type'] != 2 ||
                   $data['typename'] != $data['coderunnertype'])
        ) {
            // User-defined prototype, either newly created or undergoing a name change.
            $typename = trim($data['typename'] ?? '');
            if ($typename === '') {
                $errors['prototypecontrols'] = get_string('empty_new_prototype_name', 'qtype_coderunner');
            } else if (!$this->is_valid_new_type($typename)) {
                $errors['prototypecontrols'] = get_string('bad_new_prototype_name', 'qtype_coderunner');
            }
        }

        $penaltyregimeerror = $this->validate_penalty_regime($data);
        if ($penaltyregimeerror) {
             $errors['markinggroup'] = $penaltyregimeerror;
        }

        $resultcolumnsjson = trim($data['resultcolumns'] ?? '');
        if ($resultcolumnsjson !== '') {
            $resultcolumns = json_decode($resultcolumnsjson);
            if ($resultcolumns === null) {
                $errors['resultcolumns'] = get_string('resultcolumnsnotjson', 'qtype_coderunner');
            } else if (!is_array($resultcolumns)) {
                $errors['resultcolumns'] = get_string('resultcolumnsnotlist', 'qtype_coderunner');
            } else {
                foreach ($resultcolumns as $col) {
                    if (!is_array($col) || count($col) < 2) {
                        $errors['resultcolumns'] = get_string('resultcolumnspecbad', 'qtype_coderunner');
                        break;
                    }
                    foreach ($col as $el) {
                        if (!is_string($el)) {
                            $errors['resultcolumns'] = get_string('resultcolumnspecbad', 'qtype_coderunner');
                            break;
                        }
                    }
                }
            }
        }

        if ($data['attachments']) {
            // Check a valid regular expression was given.
            // Use '=' as the PCRE delimiter.
            if (@preg_match('=^' . $data['filenamesregex'] . '$=', null) === false) {
                $errors['filenamesgroup'] = get_string('badfilenamesregex', 'qtype_coderunner');
            }
        }

        if (count($errors) == 0 && $data['twigall']) {
            $errors = $this->validate_twigables();
        }

        if (count($errors) == 0 && !empty($data['validateonsave'])) {
            $testresult = $this->validate_sample_answer($data);
            if ($testresult) {
                $errors['answer'] = $testresult;
            }
        }

        $acelangs = trim($data['acelang'] ?? '');
        if ($acelangs !== '' && strpos($acelangs, ',') !== false) {
            $parsedlangs = qtype_coderunner_util::extract_languages($acelangs);
            if ($parsedlangs === false) {
                $errors['languages'] = get_string('multipledefaults', 'qtype_coderunner');
            } else if (count($parsedlangs[0]) === 0) {
                $errors['languages'] = get_string('badacelangstring', 'qtype_coderunner');
            }
        }

        // Don't allow the teacher to require more attachments than they allow; as this would
        // create a condition that it's impossible for the student to meet.
        if ($data['attachments'] != -1 && $data['attachments'] < $data['attachmentsrequired']) {
            $errors['attachmentsrequired']  = get_string('mustrequirefewer', 'qtype_coderunner');
        }

        return $errors;
    }


    // Check the templateparameters value, if given. Return an array containing
    // the error message string, which will be empty if there are no errors,
    // and the JSON evaluated template parameters, which will be empty if there
    // are errors.
    private function validate_template_params() {
        $errormessage = '';
        $json = '';
        $seed = mt_rand();  // TODO use a fixed seed if !evaluate_per_try.
        try {
            $json = $this->formquestion->evaluate_merged_parameters($seed);
            $decoded = json_decode($json, true);
            if ($decoded === null) {
                $errormessage = get_string('badtemplateparams', 'qtype_coderunner', $json);
            }
        } catch (qtype_coderunner_bad_json_exception $e) {
            $errormessage = get_string('badtemplateparams', 'qtype_coderunner', $e->getMessage());
        } catch (Exception $e) {
            $errormessage = get_string('badtemplateparams', 'qtype_coderunner', '** Unknown error **');
        }

        if ($errormessage === '') {
            // Check for legacy case of ui parameters defined within the template params.
            $uiplugin = $this->formquestion->uiplugin ? $this->formquestion->uiplugin : 'ace';
            $uiparams = new qtype_coderunner_ui_parameters($uiplugin);
            $templateparamsnoprototype = json_decode($this->formquestion->template_params_json($seed), true);
            $alluiparamnames = $uiparams->all_names();
            $badparams = [];
            foreach (array_keys($templateparamsnoprototype) as $paramname) {
                if (in_array($paramname, $alluiparamnames)) {
                    $badparams[] = $paramname;
                }
            }
            if ($badparams) {
                $errormessage = get_string('legacyuiparams', 'qtype_coderunner') . implode(', ', $badparams);
            } else {
                foreach (array_keys($templateparamsnoprototype) as $paramname) {
                    // Also check if  template parameter starts with UI plugin name and
                    // an underscore followed by a valid ui parameter name.
                    $bits = explode('_', $paramname, 2);
                    if (count($bits) > 1 && $bits[0] === $uiplugin && in_array($bits[1], $alluiparamnames)) {
                        $badparams[] = $paramname;
                    }
                    if ($badparams) {
                        $extra = ['uiname' => $uiplugin];
                        $errormessage = get_string('legacyuiparams2', 'qtype_coderunner', $extra) . implode(', ', $badparams);
                    }
                }
            }
        }

        return [$errormessage, $json];
    }

    // Check that the uiparameters field, if present and non-empty, is valid.
    // Return an error message string if not valid, else an empty string.
    private function validate_ui_parameters($uiparameters) {
        $checkmissing = false; // True to check for missing parameters. Currently not doing this.
        $errormessage = '';
        if (empty($uiparameters)) {
            return $errormessage;
        }
        try {
            $decoded = json_decode($uiparameters, true);
        } catch (Exception $e) {
            $decoded = null;
        }
        if ($decoded === null) {
               $errormessage = get_string('baduiparams', 'qtype_coderunner');
        } else {
            // Check only valid uiparameters are defined.
            $uipluginname = $this->formquestion->uiplugin;
            $uiplugins = qtype_coderunner_ui_plugins::get_instance();
            $uiparams = $uiplugins->parameters($uipluginname);
            $alluiparamnames = $uiparams->all_names();
            $badparams = [];
            foreach (array_keys($decoded) as $paramname) {
                if (!in_array($paramname, $alluiparamnames)) {
                    $badparams[] = $paramname;
                }
            }
            if ($badparams) {
                $errormessage = get_string(
                    'illegaluiparamname',
                    'qtype_coderunner',
                    ['uiname' => $uipluginname]
                ) . implode(', ', $badparams);
            } else if ($checkmissing) {
                // Make sure any required ui parameters are defined.
                $missingparams = [];
                foreach ($alluiparamnames as $uiname) {
                    if ($uiparams->is_required($uiname) && !in_array($uiname, array_keys($decoded))) {
                        $missingparams[] = $uiname;
                    }
                }
                if ($missingparams) {
                    $errormessage = get_string('missinguiparams', 'qtype_coderunner') . implode(', ', $missingparams);
                }
            }
        }

        return $errormessage;
    }


    private function validate_penalty_regime($data) {
        // Check the penalty regime and return an error string or an empty string if OK.
        $errorstring = '';
        $expectedpr = '/[0-9]+(\.[0-9]*)?%?([, ] *[0-9]+(\.[0-9]*)?%?)*([, ] *...)?/';
        $penaltyregime = trim($data['penaltyregime'] ?? '');
        if ($penaltyregime == '') {
            $errorstring = get_string('emptypenaltyregime', 'qtype_coderunner');
        } else if (!preg_match($expectedpr, $penaltyregime)) {
            $errorstring = get_string('badpenalties', 'qtype_coderunner');
        } else {
            $penaltyregime = str_replace('%', '', $penaltyregime);
            $penaltyregime = str_replace(',', ', ', $penaltyregime);
            $penaltyregime = preg_replace('/ *,? +/', ', ', $penaltyregime);
            $bits = explode(', ', $penaltyregime);
            $n = count($bits);
            if ($bits[$n - 1] === '...') {
                if ($n < 3 || floatval($bits[$n - 2]) <= floatval($bits[$n - 3])) {
                    // If it ends with '...', ensure the last two numbers are in increasing order.
                    $errorstring = get_string('bad_dotdotdot', 'qtype_coderunner');
                }
                $n--;
            }
            if ($errorstring === '') {
                // Check all elements are valid numbers.
                for ($i = 0; $i < $n; $i++) {
                    if (!is_numeric($bits[$i])) {
                        $errorstring = get_string('badpenalties', 'qtype_coderunner');
                        break;
                    }
                }
            }
        }
        return $errorstring;
    }

    // Check for twig errors in all fields except the template itself, which
    // is checked when the answer is validated. Checking it here would require
    // setting up a runtime context with STUDENT_ANSWER and TEST or TESTCASES etc.
    // Return value is an associative array mapping from
    // form fields to error messages.
    // Should only be called if twig all is set.
    private function validate_twigables() {
        $errors = [];
        $question = $this->formquestion;
        $jsonparams = $question->templateparamsevald;
        $parameters = json_decode($jsonparams, true);
        $parameters['QUESTION'] = $question;

        // Try twig expanding everything (see question::twig_all), with strict_variables true.
        foreach (['questiontext', 'answer', 'answerpreload', 'globalextra', 'prototypeextra'] as $field) {
            $text = $question->$field;
            if (is_array($text)) {
                $text = $text['text'];
            }
            try {
                $this->twig_render($text, $parameters, true);
            } catch (Exception $ex) {
                $errors[$field] = get_string(
                    'twigerror',
                    'qtype_coderunner',
                    $ex->getMessage()
                );
            }
        }

        // Now all test cases.
        if (!empty($question->testcode)) {
            $num = max(
                count($question->testcode),
                count($question->stdin),
                count($question->expected),
                count($question->extra)
            );

            foreach (['testcode', 'stdin', 'expected', 'extra'] as $fieldname) {
                $fields = $question->$fieldname;
                for ($i = 0; $i < $num; $i++) {
                    $text = $fields[$i];
                    try {
                        $this->twig_render($text, $parameters, true);
                    } catch (Exception $ex) {
                        $errors["testcode[$i]"] = get_string(
                            'twigerrorintest',
                            'qtype_coderunner',
                            $ex->getMessage()
                        );
                    }
                }
            }
        }
        return $errors;
    }


    // Validate the test cases.
    private function validate_test_cases($data) {
        $errors = []; // Return value.
        $testcodes = $data['testcode'];
        $stdins = $data['stdin'];
        $expecteds = $data['expected'];
        $marks = $data['mark'];
        $count = 0;
        $numnonemptytests = 0;
        $num = max(count($testcodes), count($stdins), count($expecteds));
        for ($i = 0; $i < $num; $i++) {
            $testcode = trim($testcodes[$i] ?? '');
            if ($testcode != '') {
                $numnonemptytests++;
            }
            $stdin = trim($stdins[$i] ?? '');
            $expected = trim($expecteds[$i] ?? '');
            if ($testcode !== '' || $stdin != '' || $expected !== '') {
                $count++;
                $mark = trim($marks[$i] ?? '');
                if ($mark != '') {
                    if (!is_numeric($mark)) {
                        $errors["testcode[$i]"] = get_string('nonnumericmark', 'qtype_coderunner');
                    } else if (floatval($mark) < 0) {
                        $errors["testcode[$i]"] = get_string('negativeorzeromark', 'qtype_coderunner');
                    }
                }
            }
        }

        if ($count == 0) {
            $errors["testcode[0]"] = get_string('atleastonetest', 'qtype_coderunner');
        } else if ($numnonemptytests != 0 && $numnonemptytests != $count) {
            $errors["testcode[0]"] = get_string('allornone', 'qtype_coderunner');
        }
        return $errors;
    }


    // Check the sample answer (if there is one).
    // Return an empty string if there is no sample answer and no attachments,
    // or if the sample answer passes all the tests.
    // Otherwise return a suitable error message for display in the form.
    private function validate_sample_answer() {
        $attachmentssaver = $this->get_sample_answer_file_saver();
        $files = $attachmentssaver ? $attachmentssaver->get_files() : [];
        $answer = $this->formquestion->answer;
        if (trim($answer ?? '') === '' && count($files) == 0) {
            return ''; // Empty answer and no attachments.
        }
        // Check if it's a multilanguage question; if so need to determine
        // what language to use. If there is a specific answer_language template
        // parameter, that is used. Otherwise the default language (if specified)
        // or the first in the list is used.
        $acelang = trim($this->formquestion->acelang ?? '');
        if ($acelang !== '' && strpos($acelang, ',') !== false) {
            if (empty($this->formquestion->parameters['answer_language'])) {
                [$languages, $answerlang] = qtype_coderunner_util::extract_languages($acelang);
                if ($answerlang === '') {
                    $answerlang = $languages[0];
                }
            } else {
                $answerlang = $this->formquestion->parameters['answer_language'];
            }
        }

        try {
            $savedevalpertry = $this->formquestion->templateparamsevalpertry;
            if (!isset($this->formquestion->uiparameters)) {
                $this->formquestion->uiparameters = null; // If hidden, value isn't recorded in formquestion.
            }
            $this->formquestion->templateparamsevalpertry = 0; // Save an extra evaluation.
            $this->formquestion->start_attempt();
            $this->formquestion->templateparamsevalpertry = $savedevalpertry;
            $response = ['answer' => $this->formquestion->answer];
            if (!empty($answerlang)) {
                $response['language'] = $answerlang;
            }
            if ($attachmentssaver) {
                $response['attachments'] = $attachmentssaver;
            }
            $error = $this->formquestion->validate_response($response);
            if ($error) {
                return $error;
            }
            [$mark, , $cachedata] = $this->formquestion->grade_response($response, false, true);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        // Return either an empty string if run was good or an error message.
        if ($mark == 1.0) {
            return '';
        } else {
            $outcome = unserialize($cachedata['_testoutcome']);
            $error = $outcome->validation_error_message();
            return $error;
        }
    }

    // UTILITY FUNCTIONS.
    // =================.

    // True iff the given name is valid for a new type, i.e., it's not in use
    // in the current context (Currently only a single global context is
    // implemented).
    private function is_valid_new_type($typename) {
        [, $types] = $this->get_languages_and_types();
        return !array_key_exists($typename, $types);
    }


    /**
     * Return a count of the number of test cases set as examples.
     * @param array $data data from the form
     */
    private function num_examples($data) {
        return isset($data['useasexample']) ? count($data['useasexample']) : 0;
    }

    /**
     * Return two arrays (language => language_upper_case) and (type => subtype) of
     * all the coderunner question types available in the current course
     * context. [If needing to filter duplicates out in future, see here! (row->count)]
     * The subtype is the suffix of the type in the database,
     * e.g. for java_method it is 'method'. The language is the bit before
     * the underscore, and language_upper_case is a capitalised version,
     * e.g. Java for java. For question types without a
     * subtype the word 'Default' is used.
     *
     * @global type $COURSE The Course in which this query contex will lie.
     * @return array Language and type arrays as specified.
     */
    private function get_languages_and_types() {
        global $COURSE;
        $courseid = $COURSE->id;
        $records = qtype_coderunner::get_all_prototypes($courseid);
        $types = [];
        $languages = [];
        foreach ($records as $row) {
            if (($pos = strpos($row->coderunnertype, '_')) !== false) {
                $language = substr($row->coderunnertype, 0, $pos);
            } else {
                $language = $row->coderunnertype;
            }
            $types[$row->coderunnertype] = $row->coderunnertype;
            $languages[$language] = ucwords($language);
        }
        asort($types);
        asort($languages);
        return [$languages, $types];
    }

    // Render the given Twig text with the given params, using the global
    // $USER variable (the question author) as a dummy student.
    // @return Rendered text.
    private function twig_render($text, $params = [], $isstrict = false) {
        global $USER;
        $student = new qtype_coderunner_student($USER);
        return qtype_coderunner_twig::render($text, $student, (array) $params, $isstrict);
    }


    private function make_question_from_form_data($data) {
        // Construct a question object containing all the fields from $data.
        // Used in data pre-processing and when validating a question.
        global $DB, $USER;
        $question = new qtype_coderunner_question();
        foreach ($data as $key => $value) {
            if ($key === 'questiontext' || $key === 'generalfeedback') {
                // Question text and general feedback are associative arrays.
                $question->$key = $value['text'];
            } else {
                $question->$key = $value;
            }
        }
        $question->isnew = true;
        $question->supportfilemanagerdraftid = $this->get_file_manager('datafiles');
        $question->student = new qtype_coderunner_student($USER);

        // Clean the question object, get inherited fields.
        $qtype = new qtype_coderunner();
        $qtype->clean_question_form($question, true);
        $questiontype = $question->coderunnertype;
        [$category] = explode(',', $question->category);
        $contextid = $DB->get_field('question_categories', 'contextid', ['id' => $category]);
        $question->contextid = $contextid;
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        $question->prototype = $qtype->get_prototype($questiontype, $context);
        $qtype->set_inherited_fields($question, $question->prototype);
        return $question;
    }


    // Returns the Json for the merged template parameters.
    // It is assumed that this function is called only when a question is
    // initially loaded from the DB or a new question is being created,
    // so that it can use the question bank's load_question method to get
    // a valid question from the DB rather than the stdClass 'question'
    // provided to the form at initialisation.
    private function get_merged_ui_params() {
        global $USER;
        if (isset($this->cacheduiparamsjson)) {
            return $this->cacheduiparamsjson;
        }
        $q = $this->question;
        if (isset($q->options)) {
            // Editing an existing question.
            try {
                $qfromdb = question_bank::load_question($q->id);
                $qfromdb->student = new qtype_coderunner_student($USER);
                $seed = 1;
                $qfromdb->evaluate_question_for_display($seed, null);
                if ($qfromdb->mergeduiparameters) {
                    $json = json_encode($qfromdb->mergeduiparameters);
                } else {
                    $json = '{}';
                }
            } catch (Throwable $e) {
                $json = '{}';  // This shouldn't happen, but has been known to.
                $q->brokenquestionmessage = get_string('corruptuiparams', 'qtype_coderunner');
            };
            $this->cacheduiparamsjson = $json;
            return $json;
        } else {
            return '{}';
        }
    }


    // Return a file saver for the sample answer filemanager, if present.
    private function get_sample_answer_file_saver() {
        $sampleanswerdraftid = $this->get_file_manager('sampleanswerattachments');
        $saver = null;
        if ($sampleanswerdraftid) {
            $saver = new question_file_saver($sampleanswerdraftid, 'qtype_coderunner', 'draft');
        }
        return $saver;
    }


    // Find the id of the filemanager element draftid with the given name.
    private function get_file_manager($filemanagername) {
        $mform = $this->_form;
        $draftid = null;
        foreach ($mform->_elements as $element) {
            if (
                $element->_type == 'filemanager'
                    && $element->_attributes['name'] === $filemanagername
            ) {
                $draftid = (int)$element->getValue();
            }
        }
        return $draftid;
    }
}
