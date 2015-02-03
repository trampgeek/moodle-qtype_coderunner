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

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the editing form for the coderunner question type.
 *
 * @package 	questionbank
 * @subpackage 	questiontypes
 * @copyright 	&copy; 2013 Richard Lobb
 * @author 		Richard Lobb richard.lobb@canterbury.ac.nz
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once($CFG->dirroot . '/question/type/coderunner/sandbox/sandboxbase.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/type/coderunner/locallib.php');

/**
 * coderunner editing form definition.
 */
class qtype_coderunner_edit_form extends question_edit_form {
    
    const NUM_TESTCASES_START = 5;  // Num empty test cases with new questions
    const NUM_TESTCASES_ADD = 3;    // Extra empty test cases to add
    const DEFAULT_NUM_ROWS = 18;    // Answer box rows
    const DEFAULT_NUM_COLS = 100;   // Answer box rows
    const TEMPLATE_PARAM_SIZE = 80; // The size of the template parameter field
    const RESULT_COLUMNS_SIZE = 80; // The size of the resultcolumns field

    /**testcode
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    var $_textarea_or_htmleditor_generalfb;   //addElement type for general feedback
    var $_editor_options_generalfb;           //in dependence of editor type set a different array for its options

    function qtype() {
        return 'coderunner';
    }


    // Define the CodeRunner question edit form
    protected function definition() {
        global $PAGE;
        $jsmodule = array(
            'name'      => 'qtype_coderunner',
            'fullpath'  => '/question/type/coderunner/module.js',
            'requires'  => array('base', 'widget', 'io', 'node-menunav')
        );

        $mform = $this->_form;
        $this->make_questiontype_panel($mform);
        $this->make_customisation_panel($mform);
        $this->make_advanced_customisation_panel($mform);

        $PAGE->requires->js_init_call('M.qtype_coderunner.setupAllTAs',  array(), false, $jsmodule);
        $PAGE->requires->js_init_call('M.qtype_coderunner.initEditForm', array(), false, $jsmodule);
        load_ace_scripts();  // May be needed e.g. for template editing
        
        parent::definition($mform);  // The supercalss adds the "General" stuff
    }


    // Defines the bit of the CodeRunner question edit form after the "General"
    // section and before the footer stuff.
    public function definition_inner($mform) {

        // Note to self: what was the purpose of the next 2 lines in the superclass?
        // $mform->addElement('static', 'answersinstruct');
        // $mform->closeHeaderBefore('answersinstruct');

        $this->add_sample_answer_field($mform);
        
        if (isset($this->question->options->testcases)) {
            $numtestcases = count($this->question->options->testcases) + self::NUM_TESTCASES_ADD;
        }
        else {
            $numtestcases = self::NUM_TESTCASES_START;
        }

        // Confusion alert! A call to $mform->setDefault("mark[$i]", '1.0') looks
        // plausible and works to set the empty-form default, but it then
        // overrides (rather than is overridden by) the actual value. The same
        // thing happens with $repeatedoptions['mark']['default'] = 1.000 in
        // get_per_testcase_fields (q.v.).
        // I don't understand this (but see 'Evil hack alert' in the baseclass).
        // MY EVIL HACK ALERT -- setting just $numTestcases default values
        // fails when more test cases are added on the fly. So I've set up
        // enough defaults to handle 5 successive adding of more test cases.
        // I believe this is a bug in the underlying Moodle question type, not
        // mine, but ... how to be sure?
        $mform->setDefault('mark', array_fill(0, $numtestcases + 5 * self::NUM_TESTCASES_ADD, 1.0));
        $ordering = array();
        for ($i = 0; $i < 5 * self::NUM_TESTCASES_ADD; $i++) {
            $ordering[] = 10 * $i;
        }
        $mform->setDefault('ordering', $ordering);

        $this->add_per_testcase_fields($mform, get_string('testcase', 'qtype_coderunner', "{no}"),
                $numtestcases);

        // Add the option to attach runtime support files, all of which are
        // copied into the working directory when the expanded template is
        // executed.The file context is that of the current course.
        $options = $this->fileoptions;
        $options['subdirs'] = false;
        $mform->addElement('header', 'fileheader',
                get_string('fileheader', 'qtype_coderunner'));
        $mform->addElement('filemanager', 'datafiles',
                get_string('datafiles', 'qtype_coderunner'), null,
                $options);
        $mform->addHelpButton('datafiles', 'datafiles', 'qtype_coderunner');

        // Lastly add the standard moodle question stuff
        $this->add_interactive_settings();
    }

    /**
     * Add a field for a sample answer to this problem (optional)
     * @param object $mform the form being built
     */
    protected function add_sample_answer_field(&$mform) {
        $mform->addElement('header', 'answerhdr',
                    get_string('sampleanswer', 'qtype_coderunner'), '');
        $mform->setExpanded('answerhdr', 1);
        $mform->addElement('textarea', 'answer',
                get_string('answer', 'qtype_coderunner'),
                array('rows' => 15, 'class' => 'sampleanswer edit_code'));
    }

 /**
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
    protected function add_per_testcase_fields(&$mform, $label, $numtestcases) {
        $mform->addElement('header', 'testcasehdr',
                    get_string('testcases', 'qtype_coderunner'), '');
        $mform->setExpanded('testcasehdr', 1);
        $repeatedoptions = array();
        $repeated = $this->get_per_testcase_fields($mform, $label, $repeatedoptions);
        $this->repeat_elements($repeated, $numtestcases, $repeatedoptions,
                'numtestcases', 'addanswers', QUESTION_NUMANS_ADD,
                $this->get_more_choices_string(), true);
        $n = $numtestcases + QUESTION_NUMANS_ADD;
        for ($i = 0; $i < $n; $i++) {
            $mform->disabledIf("mark[$i]", 'allornothing', 'checked');
        }
    }


    /*
     *  A rewritten version of get_per_answer_fields specific to test cases.
     */
    public function get_per_testcase_fields($mform, $label, &$repeatedoptions) {
        $repeated = array();
        $repeated[] = & $mform->createElement('textarea', 'testcode',
                $label,
                array('rows' => 3, 'class' => 'testcaseexpression edit_code'));
        $repeated[] = & $mform->createElement('textarea', 'stdin',
                get_string('stdin', 'qtype_coderunner'),
                array('rows' => 3, 'class' => 'testcasestdin edit_code'));
        $repeated[] = & $mform->createElement('textarea', 'expected',
                get_string('expected', 'qtype_coderunner'),
                array('rows' => 3, 'class' => 'testcaseresult edit_code'));
        
       $repeated[] = & $mform->createElement('textarea', 'extra',
                get_string('extra', 'qtype_coderunner'),
                array('rows' => 3, 'class' => 'testcaseresult edit_code'));

        $group[] =& $mform->createElement('checkbox', 'useasexample', null,
                get_string('useasexample', 'qtype_coderunner'));

        $options = array();
        foreach ($this->displayoptions() as $opt) {
            $options[$opt] = get_string($opt, 'qtype_coderunner');
        }

        $group[] =& $mform->createElement('select', 'display',
                        get_string('display', 'qtype_coderunner'), $options);
        $group[] =& $mform->createElement('checkbox', 'hiderestiffail', null,
                        get_string('hiderestiffail', 'qtype_coderunner'));
        $group[] =& $mform->createElement('text', 'mark',
                get_string('mark', 'qtype_coderunner'),
                array('size' => 5, 'class' => 'testcasemark'));
        $group[] =& $mform->createElement('text', 'ordering',
                get_string('ordering', 'qtype_coderunner'),
                array('size' => 3, 'class' => 'testcaseordering'));

        $repeated[] =& $mform->createElement('group', 'testcasecontrols',
                        get_string('testcasecontrols', 'qtype_coderunner'),
                        $group, null, false);

        $repeatedoptions['expected']['type'] = PARAM_RAW;
        $repeatedoptions['testcode']['type'] = PARAM_RAW;
        $repeatedoptions['stdin']['type'] = PARAM_RAW;
        $repeatedoptions['extra']['type'] = PARAM_RAW;
        $repeatedoptions['mark']['type'] = PARAM_FLOAT;
        $repeatedoptions['ordering']['type'] = PARAM_INT;
        
        foreach (array('testcode', 'stdin', 'expected', 'extra', 'testcasecontrols') as $field) {
            $repeatedoptions[$field]['helpbutton'] = array($field, 'qtype_coderunner');
        }
        
        // Why does the following line not work? See "Confusion alert" in definition_inner.
        // $repeatedoptions['mark']['default'] = 1.000; 

        return $repeated;
    }


    // A list of the allowed values of the DB 'display' field for each testcase.
    protected function displayoptions() {
        return array('SHOW', 'HIDE', 'HIDE_IF_FAIL', 'HIDE_IF_SUCCEED');
    }

    
    public function data_preprocessing($question) {
        // Load question data into form ($this). Called by set_data after
        // standard stuff all loaded.
        global $COURSE;
       
        if (isset($question->options->testcases)) { // Reloading a saved question?
            $question->testcode = array();
            $question->expected = array();
            $question->useasexample = array();
            $question->display = array();
            $question->extra = array();
            $question->hiderestifail = array();

            foreach ($question->options->testcases as $tc) {
                $question->testcode[] = $this->newline_hack($tc->testcode);
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

            // Save the prototypetype so can see if it changed on post-back
            $question->saved_prototype_type = $question->prototypetype;
            $question->courseid = $COURSE->id;

            // Load the type-name if this is a prototype, else make it blank
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
        }

        $draftid = file_get_submitted_draft_itemid('datafiles');
        $options = $this->fileoptions;
        $options['subdirs'] = false;

        file_prepare_draft_area($draftid, $this->context->id,
                'qtype_coderunner', 'datafile',
                empty($question->id) ? null : (int) $question->id,
                $options);
        $question->datafiles = $draftid; // File manager needs this (and we need it when saving)
        return $question;
    }
    
    
    // A horrible horrible hack for a horrible horrible browser "feature".
    // Inserts a newline at the start of a text string that's going to be
    // displayed at the start of a <textarea> element, because all browsers
    // strip a leading newline. If there's one there, we need to keep it, so
    // the extra one ensures we do. If there isn't one there, this one gets
    // ignored anyway.
    private function newline_hack($s) {
        return "\n" . $s;
    }


    
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['coderunnertype'] == 'Undefined') {
            $errors['coderunner_type_group'] = get_string('questiontype_required', 'qtype_coderunner');
        }
        if ($data['cputimelimitsecs'] != '' &&
             (!ctype_digit($data['cputimelimitsecs']) || intval($data['cputimelimitsecs']) <= 0)) {
            $errors['sandboxcontrols'] = get_string('badcputime', 'qtype_coderunner');
        }
        if ($data['memlimitmb'] != '' &&
             (!ctype_digit($data['memlimitmb']) || intval($data['memlimitmb']) < 0)) {
            $errors['sandboxcontrols'] = get_string('badmemlimit', 'qtype_coderunner');
        }
        
        if ($data['sandboxparams'] != '' &&
                json_decode($data['sandboxparams']) === null) {
            $errors['sandboxcontrols'] = get_string('badsandboxparams', 'qtype_coderunner');
        }
        
        if ($data['templateparams'] != '' &&
                json_decode($data['templateparams']) === null) {
            $errors['templateparams'] = get_string('badtemplateparams', 'qtype_coderunner');
        }

        if ($data['prototypetype'] == 0 && $data['grader'] !== 'qtype_coderunner_combinator_template_grader') {
            // Unless it's a prototype or uses a combinator-template grader
            // it needs at least one testcase
            $testCaseErrors = $this->validate_test_cases($data);
            $errors = array_merge($errors, $testCaseErrors);
        }
        
        if ($data['grader'] === 'qtype_coderunner_combinator_template_grader' &&
                $data['enablecombinator'] == false) {
            $errors['combinatorcontrols'] = get_string('combinator_required', 'qtype_coderunner');
        }


        if ($data['prototypetype'] == 2 && ($data['saved_prototype_type'] != 2 ||
                   $data['typename'] != $data['coderunnertype'])){
            // User-defined prototype, either newly created or undergoing a name change
            $typeName = trim($data['typename']);
            if ($typeName === '') {
                $errors['prototypecontrols'] = get_string('empty_new_prototype_name', 'qtype_coderunner');
            } else if (!$this->is_valid_new_type($typeName)) {
                $errors['prototypecontrols'] = get_string('bad_new_prototype_name', 'qtype_coderunner');
            }
        }

        if (trim($data['penaltyregime']) != '') {
            $bits = explode(',', $data['penaltyregime']);
            $n = count($bits);
            for ($i = 0; $i < $n; $i++) {
                $bit = trim($bits[$i]);
                if ($bit === '...') {
                    if ($i != $n - 1 || $n < 3 || floatval($bits[$i - 1]) <= floatval($bits[$i - 2])) {
                        $errors['markinggroup'] = get_string('bad_dotdotdot', 'qtype_coderunner');
                    }
                }
            }
        }
        
        $result_columns_json = trim($data['resultcolumns']);
        if ($result_columns_json !== '') {
            $resultcolumns = json_decode($result_columns_json);
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

        return $errors;
    }
    
    // FUNCTIONS TO BUILD PARTS OF THE MAIN FORM
    // =========================================
    
    
    // Add to the supplied $mform the panel "Coderunner question type"
    private function make_questiontype_panel(&$mform) {
        list($languages, $types) = $this->get_languages_and_types();

        $mform->addElement('header', 'questiontypeheader', get_string('type_header','qtype_coderunner'));

        $typeselectorelements = array();
        $expandedtypes = array_merge(array('Undefined' => 'Undefined'), $types);
        $typeselectorelements[] = $mform->createElement('select', 'coderunnertype',
                null, $expandedtypes);

        $typeselectorelements[] = $mform->createElement('advcheckbox', 'customise', null,
                get_string('customise', 'qtype_coderunner'));
        
        $typeselectorelements[] =  $mform->createElement('advcheckbox', 'showsource', null,
                get_string('showsource', 'qtype_coderunner'));
        $mform->setDefault('showsource', False);

        $mform->addElement('group', 'coderunner_type_group',
                get_string('questiontype', 'qtype_coderunner'), $typeselectorelements, null, false);
        $mform->addHelpButton('coderunner_type_group', 'coderunnertype', 'qtype_coderunner');

        $answerboxelements = array();
        $answerboxelements[] = $mform->createElement('text', 'answerboxlines',
                get_string('answerboxlines', 'qtype_coderunner'),
                array('size'=>3, 'class'=>'coderunner_answerbox_size'));
        $mform->setType('answerboxlines', PARAM_INT);
        $mform->setDefault('answerboxlines', self::DEFAULT_NUM_ROWS);
        $answerboxelements[] = $mform->createElement('text', 'answerboxcolumns',
                get_string('answerboxcolumns', 'qtype_coderunner'),
                array('size'=>3, 'class'=>'coderunner_answerbox_size'));
        $mform->setType('answerboxcolumns', PARAM_INT);
        $mform->setDefault('answerboxcolumns', self::DEFAULT_NUM_COLS);
        $answerboxelements[] = $mform->createElement('advcheckbox', 'useace', null,
                get_string('useace', 'qtype_coderunner'));
        $mform->setDefault('useace', True);
        $mform->addElement('group', 'answerbox_group', get_string('answerbox_group', 'qtype_coderunner'),
                $answerboxelements, null, false);
        $mform->addHelpButton('answerbox_group', 'answerbox_group', 'qtype_coderunner');

        $markingelements = array();
        $markingelements[] = $mform->createElement('advcheckbox', 'allornothing',
                get_string('marking', 'qtype_coderunner'),
                get_string('allornothing', 'qtype_coderunner'));
        $markingelements[] = $mform->CreateElement('text', 'penaltyregime',
            get_string('penaltyregime', 'qtype_coderunner'),
            array('size' => 20));
        $mform->addElement('group', 'markinggroup', get_string('markinggroup', 'qtype_coderunner'),
                $markingelements, null, false);
        $mform->setDefault('allornothing', True);
        $mform->setType('penaltyregime', PARAM_RAW);
        $mform->addHelpButton('markinggroup', 'markinggroup', 'qtype_coderunner');

        $templateelements = array();
        $templateelements[] =  $mform->createElement('advcheckbox', 'showsource', null,
                get_string('showsource', 'qtype_coderunner'));
        $mform->setDefault('showsource', False);

        $mform->addElement('text', 'templateparams',
            get_string('templateparams', 'qtype_coderunner'),
            array('size' => self::TEMPLATE_PARAM_SIZE));
        $mform->setType('templateparams', PARAM_RAW);
        $mform->addHelpButton('templateparams', 'templateparams', 'qtype_coderunner');
        $mform->setAdvanced('templateparams');
    }
    
    
    // Add to the supplied $mform the Customisation Panel
    // The panel is hidden by default but exposed when the user clicks
    // the 'Customise' checkbox in the question-type panel.
    private function make_customisation_panel(&$mform) {
        
        // The following fields are used to customise a question by overriding
        // values from the base question type. All are hidden
        // unless the 'customise' checkbox is checked.

        $mform->addElement('header', 'customisationheader',
                get_string('customisation','qtype_coderunner'));

        $mform->addElement('textarea', 'pertesttemplate',
                get_string('template', 'qtype_coderunner'),
                array('rows'=>8, 'class'=>'template edit_code',
                      'name'=>'pertesttemplate'));


        $mform->addHelpButton('pertesttemplate', 'template', 'qtype_coderunner');
        $gradingcontrols = array();
        $graderTypes = array('EqualityGrader' => 'Exact match',
                'NearEqualityGrader' => 'Nearly exact match',
                'RegexGrader' => 'Regular expression',
                'TemplateGrader' => 'Per-test-template grader',
                'CombinatorTemplateGrader' => 'Combinator-template grader');
        $gradingcontrols[] = $mform->createElement('select', 'grader',
                null, $graderTypes);
        $mform->addElement('group', 'gradingcontrols',
                get_string('grading', 'qtype_coderunner'), $gradingcontrols,
                null, false);
        $mform->addHelpButton('gradingcontrols', 'gradingcontrols', 'qtype_coderunner');

        $mform->addElement('text', 'resultcolumns',
            get_string('resultcolumns', 'qtype_coderunner'),
            array('size' => self::RESULT_COLUMNS_SIZE));
        $mform->setType('resultcolumns', PARAM_RAW);
        $mform->addHelpButton('resultcolumns', 'resultcolumns', 'qtype_coderunner');
    }
    
    
    // Make the advanced customisation panel, also hidden until the user
    // customises the question. The fields in this part of the form are much more
   // advanced and not recommended for most users. 
    private function make_advanced_customisation_panel(&$mform) {
        $mform->addElement('header', 'advancedcustomisationheader',
                get_string('advanced_customisation','qtype_coderunner'));

        $prototypecontrols = array();

        $prototypeselect =& $mform->createElement('select', 'prototypetype',
                get_string('prototypeQ', 'qtype_coderunner'));
        $prototypeselect->addOption('No', '0');
        $prototypeselect->addOption('Yes (built-in)', '1', array('disabled'=>'disabled'));
        $prototypeselect->addOption('Yes (user defined)', '2');
        $prototypecontrols[] =& $prototypeselect;
        $prototypecontrols[] =& $mform->createElement('text', 'typename',
                get_string('typename', 'qtype_coderunner'), array('size' => 30));
        $mform->addElement('group', 'prototypecontrols',
                get_string('prototypecontrols', 'qtype_coderunner'),
                $prototypecontrols, null, false);
        $mform->setDefault('is_prototype', False);
        $mform->setType('typename', PARAM_RAW);
        $mform->addElement('hidden', 'saved_prototype_type');
        $mform->setType('saved_prototype_type', PARAM_RAW);
        $mform->addHelpButton('prototypecontrols', 'prototypecontrols', 'qtype_coderunner');

        $sandboxcontrols = array();

        $sandboxes = array('DEFAULT' => 'DEFAULT');
        foreach (qtype_coderunner_sandbox::available_sandboxes() as $ext => $class) {
            $sandboxes[$ext] = $ext;
        }

        $sandboxcontrols[] =  $mform->createElement('select', 'sandbox', null, $sandboxes);

        $sandboxcontrols[] =& $mform->createElement('text', 'cputimelimitsecs',
                get_string('cputime', 'qtype_coderunner'), array('size' => 3));
        $sandboxcontrols[] =& $mform->createElement('text', 'memlimitmb',
                get_string('memorylimit', 'qtype_coderunner'), array('size' => 5));
        $sandboxcontrols[] =& $mform->createElement('text', 'sandboxparams',
                get_string('sandboxparams', 'qtype_coderunner'), array('size' => 15));
        $mform->addElement('group', 'sandboxcontrols',
                get_string('sandboxcontrols', 'qtype_coderunner'),
                $sandboxcontrols, null, false);
        $mform->setType('cputimelimitsecs', PARAM_RAW);
        $mform->setType('memlimitmb', PARAM_RAW);
        $mform->setType('sandboxparams', PARAM_RAW);
        $mform->addHelpButton('sandboxcontrols', 'sandboxcontrols', 'qtype_coderunner');

        $languages = array();
        $languages[]  =& $mform->createElement('text', 'language',
            get_string('language', 'qtype_coderunner'),
            array('size' => 10));
        $mform->setType('language', PARAM_RAW);
        $languages[]  =& $mform->createElement('text', 'acelang',
            get_string('ace-language', 'qtype_coderunner'),
            array('size' => 10));
        $mform->setType('acelang', PARAM_RAW);
        $mform->addElement('group', 'languages',
            get_string('languages', 'qtype_coderunner'), 
            $languages, null, false);
        $mform->addHelpButton('languages', 'languages', 'qtype_coderunner');

        $combinatorcontrols = array();

        $combinatorcontrols[] =& $mform->createElement('advcheckbox', 'enablecombinator', null,
                get_string('enablecombinator', 'qtype_coderunner'));
        $combinatorcontrols[] =& $mform->createElement('text', 'testsplitterre',
                get_string('testsplitterre', 'qtype_coderunner'),
                array('size' => 45));
        $mform->setType('testsplitterre', PARAM_RAW);
        $mform->disabledIf('typename', 'prototypetype', 'neq', '2');

        $combinatorcontrols[] =& $mform->createElement('textarea', 'combinatortemplate',
                '',
                array('cols'=>60, 'rows'=>8, 'class'=>'template edit_code',
                       'name'=>'combinatortemplate'));

        $mform->addElement('group', 'combinatorcontrols',
                get_string('combinatorcontrols', 'qtype_coderunner'),
                $combinatorcontrols, null, false);

        $mform->addHelpButton('combinatorcontrols', 'combinatorcontrols', 'qtype_coderunner');

        $mform->setExpanded('customisationheader');  // Although expanded it's hidden until JavaScript unhides it        
    }
    
    // UTILITY FUNCTIONS
    // =================

    // True iff the given name is valid for a new type, i.e., it's not in use
    // in the current context (Currently only a single global context is
    // implemented).
    private function is_valid_new_type($typeName) {
        list($langs, $types) = $this->get_languages_and_types();
        return !array_key_exists($typeName, $types);
    }


    private function get_languages_and_types() {
        // Return two arrays (language => language_upper_case) and (type => subtype) of
        // all the coderunner question types available in the current course
        // context.
        // The subtype is the suffix of the type in the database,
        // e.g. for java_method it is 'method'. The language is the bit before
        // the underscore, and language_upper_case is a capitalised version,
        // e.g. Java for java. For question types without a
        // subtype the word 'Default' is used.

        $records = qtype_coderunner::get_all_prototypes();
        $types = array();
        foreach ($records as $row) {
            if (($pos = strpos($row->coderunnertype, '_')) !== false) {
                $subtype = substr($row->coderunnertype, $pos + 1);
                $language = substr($row->coderunnertype, 0, $pos);
            }
            else {
                $subtype = 'Default';
                $language = $row->coderunnertype;
            }
            $types[$row->coderunnertype] = $row->coderunnertype;
            $languages[$language] = ucwords($language);
        }
        asort($types);
        asort($languages);
        return array($languages, $types);
    }



    // Validate the test cases
    private function validate_test_cases($data) {
        $errors = array(); // Return value
        $testcodes = $data['testcode'];
        $stdins = $data['stdin'];
        $expecteds = $data['expected'];
        $marks = $data['mark'];
        $count = 0;
        $numnonemptytests = 0;
        $num = max(count($testcodes), count($stdins), count($expecteds));
        for ($i = 0; $i < $num; $i++) {
            $testcode = trim($testcodes[$i]);
            if ($testcode != '') {
                $numnonemptytests++;
            }
            $stdin = trim($stdins[$i]);
            $expected = trim($expecteds[$i]);
            if ($testcode !== '' || $stdin != '' || $expected !== '') {
                $count++;
                $mark = trim($marks[$i]);
                if ($mark != '') {
                    if (!is_numeric($mark)) {
                        $errors["testcode[$i]"] = get_string('nonnumericmark', 'qtype_coderunner');
                    }
                    else if (floatval($mark) <= 0) {
                        $errors["testcode[$i]"] = get_string('negativeorzeromark', 'qtype_coderunner');
                    }
                }
            }
        }

        if ($count == 0) {
            $errors["testcode[0]"] = get_string('atleastonetest', 'qtype_coderunner');
        }
        else if ($numnonemptytests != 0 && $numnonemptytests != $count) {
            $errors["testcode[0]"] = get_string('allornothing', 'qtype_coderunner');
        }
        return $errors;
    }
}
