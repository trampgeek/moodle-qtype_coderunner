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
use qtype_coderunner\v6\field_registry;
use qtype_coderunner\v6\form_section;
use qtype_coderunner\v6\override_set;
use qtype_coderunner\v6\prototype_chain;
use qtype_coderunner\v6\prototype_picker;
use qtype_coderunner\v6\prototype_promotion;
use qtype_coderunner\v6\settings_form;

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

    /** @var bool */
    private $iscombinatorgrader; // True if the question uses a combinator template grader.

    /** @var \qtype_coderunner\v6\question_settings|null What the question inherits. */
    private $inheritedsettings = null;

    /** @var \qtype_coderunner\v6\question_settings|null What the question resolves to. */
    private $resolvedsettings = null;

    /** @var override_set|null What the question overrides of its chain. */
    private $ownoverrides = null;

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
        $this->make_prototype_panel($mform);

        // Ace on every code field. The settings sections get the plain editor;
        // the answer and the answer preload get the question's own UI plugin,
        // which may be the scratchpad, a graph editor or anything else a
        // prototype chooses, and which is what a student will see.
        settings_form::require_ace(self::sections(), (string) $this->resolved_settings()->get('language'));
        $this->require_answer_box_ui($mform);
        $PAGE->requires->js_call_amd('qtype_coderunner/textareas', 'setupAllTAs');
        // The '<<' buttons in the table of failed test cases, which a failed
        // *Validate on save* puts on the page.
        $PAGE->requires->js_call_amd('qtype_coderunner/replaceexpected', 'init');
        prototype_picker::require_reload_on_change();

        parent::definition($mform);  // The superclass adds the "General" stuff.
    }

    /**
     * Attach the question's user interface plugin to the answer and the answer
     * preload.
     *
     * CodeRunner 5 did this from `authorform.js`, which also drove the
     * *Customise* checkbox, the question type menu's AJAX reload and the rest
     * of a form that no longer exists. The plugin is attached here instead,
     * from the settings the question resolves to, so that an author writing a
     * sample answer sees the editor their students will see.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function require_answer_box_ui($mform) {
        global $PAGE;

        $uiplugin = (string) $this->resolved_settings()->get('uiplugin');
        if ($uiplugin === '' || strtolower($uiplugin) === 'none') {
            return;
        }

        foreach (['answer', 'answerpreload'] as $field) {
            $PAGE->requires->js_call_amd(
                'qtype_coderunner/userinterfacewrapper',
                'newUiWrapper',
                [$uiplugin, 'id_' . $field]
            );
        }
    }

    /**
     * The prototype picker, and what the chosen prototype documents about
     * itself.
     *
     * This replaces CodeRunner 5's *Question type* menu, its *Customise*
     * checkbox and the Details panel beside them. The picker is built by
     * {@see prototype_picker}; what is added here is the surrounding section
     * and the documentation, which is collapsed because an author who knows
     * the prototype does not want to scroll past it.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function make_prototype_panel($mform) {
        $mform->addElement('header', 'questiontypeheader', get_string('type_header', 'qtype_coderunner'));
        $mform->setExpanded('questiontypeheader', 1);

        prototype_picker::add_to_form($mform, $this->picker_courseid(), $this->chosen_prototypeid());
        prototype_promotion::add_to_form($mform, $this->picker_courseid(), $this->chosen_prototypeid());

        $documentation = $this->prototype_documentation();
        if ($documentation !== '') {
            $mform->addElement(
                'header',
                'questiontypehelpheader',
                get_string('questiontypedetails', 'qtype_coderunner')
            );
            $mform->setExpanded('questiontypehelpheader', 0);
            $mform->addElement('html', $documentation);
        }
    }

    /**
     * Every setting of the question, in the collapsible sections of design
     * section 7, each built from the field registry.
     *
     * The question's own fields — its sample answer, answer preload and
     * validate-on-save — are the first section and are open, because they are
     * what an author is here for. Everything else is closed, and a section
     * header says how many of its fields the question overrides, so that a
     * closed section never hides a change.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function make_settings_sections($mform) {
        $inherited = $this->inherited_settings();
        $own = $this->own_overrides();
        $language = (string) $this->resolved_settings()->get('language');

        foreach (self::sections() as $section) {
            if ($section === form_section::QUESTION) {
                // Added by definition_inner(), beside the test cases, because
                // the sample answer needs its attachment manager and the
                // design puts them at the top of the form.
                continue;
            }
            if ($section === form_section::FILES) {
                // A set of files rather than a value; definition_inner() gives
                // it a file manager of its own.
                continue;
            }

            $fields = settings_form::fields_of($section);
            if (!$fields) {
                continue;
            }

            $mform->addElement(
                'header',
                self::section_header($section),
                $this->section_label($section, $own)
            );
            $mform->setExpanded(self::section_header($section), $this->section_is_open($section, $own));
            // Instance fields included: the question form is the only form
            // that may show them, and `showsource` — Template debugging —
            // sits in the Template section.
            // The legend saying what the marks beside the fields mean comes
            // with them, from the component.
            settings_form::add_section($mform, $section, $inherited, $own, $language, true);
        }
    }

    /**
     * The sections this form shows, in the order design section 7 sets out.
     *
     * @return form_section[] the sections.
     */
    public static function sections(): array {
        return [
            form_section::QUESTION,
            form_section::PARAMETERS,
            form_section::TESTCASEFIELDS,
            form_section::GRADING,
            form_section::UI,
            form_section::TEMPLATE,
            form_section::SANDBOX,
            form_section::EXTRAS,
            form_section::FILES,
        ];
    }

    /**
     * The form element name of a section's header.
     *
     * @param form_section $section the section.
     * @return string the element name.
     */
    protected static function section_header(form_section $section): string {
        return settings_form::section_header($section);
    }

    /**
     * A section's heading, with the number of fields the question overrides in
     * it when there are any.
     *
     * @param form_section $section the section.
     * @param override_set $own what the question overrides.
     * @return string the heading.
     */
    protected function section_label(form_section $section, override_set $own): string {
        return settings_form::section_label($section, $own, $this->inherited_settings(), true);
    }

    /**
     * How many of a section's fields the question overrides **to a different
     * value** from the one it would otherwise inherit.
     *
     * Counted by the component, which the prototype editor's headers need in
     * the same words; see {@see settings_form::changed_count()} for why the
     * count is of effective differences and not of overrides.
     *
     * @param form_section $section the section.
     * @param override_set $own what the question overrides.
     * @return int the count.
     */
    protected function section_override_count(form_section $section, override_set $own): int {
        return settings_form::changed_count($section, $own, $this->inherited_settings(), true);
    }

    /**
     * Whether a section starts open.
     *
     * None of them does. An earlier draft opened any section holding an
     * override, on the grounds that a change should be visible without
     * hunting. On real data that opens most of the form: the migration pins
     * twenty fields on every existing question (M3), so a migrated question
     * overrides something in five of the eight sections and the form arrives
     * almost entirely expanded. The count on the header is what stops a closed
     * section hiding a change, which is what the design relies on.
     *
     * @param form_section $section the section.
     * @param override_set $own what the question overrides.
     * @return bool true to start expanded.
     */
    protected function section_is_open(form_section $section, override_set $own): bool {
        return false;
    }

    /**
     * The course whose prototypes the picker offers.
     *
     * The question's own course, which is the course of the bank it is being
     * written in. A question at site level has no course, and is offered the
     * site's prototypes alone.
     *
     * @return int the course id.
     */
    protected function picker_courseid(): int {
        global $COURSE;

        $coursecontext = $this->context ? $this->context->get_course_context(false) : false;
        return $coursecontext ? (int) $coursecontext->instanceid : (int) $COURSE->id;
    }

    /**
     * The prototype the question is built on.
     *
     * Read from the submission when there is one, so that a no-submit reload —
     * asking for another course's prototypes — keeps the author's choice,
     * rather than reverting to what is stored.
     *
     * @return int|null the prototype id, or null for a question with none.
     */
    protected function chosen_prototypeid(): ?int {
        $submitted = optional_param(prototype_picker::FIELD, 0, PARAM_INT);
        if ($submitted) {
            return $submitted;
        }
        $stored = $this->question->options->prototypeid ?? null;
        return $stored ? (int) $stored : null;
    }

    /**
     * What the question's chain resolves to above the question itself: the
     * value each field takes unless the question overrides it.
     *
     * @return \qtype_coderunner\v6\question_settings the inherited settings.
     */
    protected function inherited_settings() {
        if ($this->inheritedsettings === null) {
            $this->inheritedsettings = $this->resolve_chain(null);
        }
        return $this->inheritedsettings;
    }

    /**
     * What the question's settings actually are, its own overrides included.
     *
     * @return \qtype_coderunner\v6\question_settings the resolved settings.
     */
    protected function resolved_settings() {
        if ($this->resolvedsettings === null) {
            $this->resolvedsettings = $this->resolve_chain($this->own_overrides());
        }
        return $this->resolvedsettings;
    }

    /**
     * Resolve the chosen prototype's chain, optionally with the question's own
     * overrides on the end of it.
     *
     * A question with no prototype, or one whose prototype has gone or whose
     * parent links form a cycle, resolves against nothing: the form still has
     * to build, so that the author can choose a prototype that works rather
     * than meeting a stack trace.
     *
     * @param override_set|null $own the question's overrides, or null for the
     *      inherited settings alone.
     * @return \qtype_coderunner\v6\question_settings the resolved settings.
     */
    protected function resolve_chain(?override_set $own) {
        $prototypeid = $this->chosen_prototypeid();
        if ($prototypeid !== null) {
            try {
                $repository = new \local_coderunner_manager\prototype_repository();
                return $repository->chain_for($prototypeid, $own)->resolve();
            } catch (Exception $e) {
                // Falls through to the empty chain below.
                $prototypeid = null;
            }
        }
        return (new prototype_chain($own === null ? [] : [$own]))->resolve();
    }

    /**
     * What the question overrides of its chain.
     *
     * @return override_set the question's own overrides.
     */
    protected function own_overrides(): override_set {
        if ($this->ownoverrides === null) {
            $stored = $this->question->options->overrides ?? null;
            $values = $stored ? json_decode($stored, true) : [];
            $this->ownoverrides = new override_set(
                is_array($values) ? $values : [],
                qtype_coderunner::QUESTION_LAYER
            );
        }
        return $this->ownoverrides;
    }

    /**
     * The chosen prototype's documentation, rendered.
     *
     * @return string the documentation, or an empty string if there is none.
     */
    protected function prototype_documentation(): string {
        $prototypeid = $this->chosen_prototypeid();
        if ($prototypeid === null) {
            return '';
        }

        $repository = new \local_coderunner_manager\prototype_repository();
        $prototype = $repository->get($prototypeid);
        $version = $prototype ? $prototype->currentversion : null;
        if ($version === null || trim($version->documentation) === '') {
            return '';
        }

        return format_text(
            $version->documentation,
            $version->documentationformat,
            ['context' => $this->context, 'noclean' => true]
        );
    }


    // Defines the bit of the CodeRunner question edit form after the "General"
    // section and before the footer stuff.
    public function definition_inner($mform) {
        $this->add_sample_answer_field($mform);
        $this->add_preload_answer_field($mform);

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

        // Everything else the question can set, in the collapsible sections of
        // design section 7. Added here rather than in definition(), which runs
        // before the parent adds the General section, so that the question
        // text, the sample answer and the test cases come first as the design
        // asks.
        $this->make_settings_sections($mform);

        // Support files, which are copied into the working directory when the
        // expanded template is executed. A set of files rather than a value, so
        // the registry-driven component leaves them out and they get a file
        // manager of their own; the question's own files are merged with each
        // layer's by filename when it runs. Last of the sections, as the design
        // numbers them.
        $options = $this->fileoptions;
        $options['subdirs'] = false;
        $mform->addElement('header', self::section_header(form_section::FILES), form_section::FILES->label());
        $mform->setExpanded(self::section_header(form_section::FILES), 0);
        $mform->addElement(
            'filemanager',
            'datafiles',
            get_string('datafiles', 'qtype_coderunner'),
            null,
            $options
        );
        $mform->addHelpButton('datafiles', 'datafiles', 'qtype_coderunner');
    }

    /**
     * Which of the four test case fields this question's prototype asks the
     * form to offer.
     *
     * The switches are settings like any other, resolved from the chain, so a
     * prototype whose questions never use standard input can stop offering the
     * column to every question built on it. They govern the **form** and not
     * the data: a column that is not shown keeps whatever it holds, which
     * get_data() puts back.
     *
     * @return array field name => true if the column is shown.
     */
    protected function testcase_columns(): array {
        $resolved = $this->resolved_settings();
        return [
            'testcode' => (bool) $resolved->get('showtestcode'),
            'stdin' => (bool) $resolved->get('showstdin'),
            'expected' => (bool) $resolved->get('showexpected'),
            'extra' => (bool) $resolved->get('showtestextra'),
            // Which tests a precheck runs is a per-test choice only when the
            // question's precheck is *Selected*. Under every other setting the
            // answer is the same for every test, so the column says the same
            // thing on every row — *Check only*, on all of them — and asks the
            // author to read past it. CodeRunner 5 hid it with JavaScript
            // driven by the precheck menu; the setting is resolved here before
            // the form is built, so it is simply not offered.
            'testtype' => (int) $resolved->get('precheck') === constants::PRECHECK_SELECTED,
        ];
    }

    public function get_data() {
        $fields = parent::get_data();
        if (!$fields) {
            return $fields;
        }

        $fields->templateparamsevald = $this->formquestion->templateparamsevald;
        $this->restore_hidden_testcase_columns($fields);
        $this->record_prototype_and_overrides($fields);

        return $fields;
    }

    /**
     * Put back the test case columns the form did not show.
     *
     * The switches govern the form, not the data. An author whose prototype
     * hides standard input must not lose the standard input their test cases
     * already have, and `copy_testcases_from_form()` reads all four columns of
     * every row, so the arrays have to be complete whether or not they were
     * on screen.
     *
     * @param object $fields the submitted data, modified in place.
     */
    protected function restore_hidden_testcase_columns($fields) {
        foreach ($this->hidden_testcase_columns((array) $fields) as $column => $values) {
            $fields->$column = $values;
        }
    }

    /**
     * The values of the test case columns the form did not show, as the
     * submitted data would have carried them had it shown them.
     *
     * Used twice: by get_data() so that what is saved keeps them, and by
     * validation(), which reads all four columns of every row and runs before
     * get_data() does.
     *
     * @param array $submitted the submitted data.
     * @return array column name => list of values, for the hidden columns only.
     */
    protected function hidden_testcase_columns(array $submitted): array {
        $shown = $this->testcase_columns();
        $stored = array_values($this->question->options->testcases ?? []);

        // How many rows the form had. Taken from a column it showed, since a
        // hidden one submitted nothing; a form showing none of the four has
        // only the rows the question already had.
        $rows = count($stored);
        foreach (array_keys(array_filter($shown)) as $column) {
            if (isset($submitted[$column])) {
                $rows = count($submitted[$column]);
                break;
            }
        }

        $restored = [];
        foreach ($shown as $column => $isshown) {
            if ($isshown) {
                continue;
            }
            // A row the form did not have at all gets the empty value for the
            // column: nothing for the text columns, and the normal test type
            // rather than an empty string, since that one is an integer.
            $empty = $column === 'testtype' ? constants::TESTTYPE_NORMAL : '';
            $values = [];
            for ($i = 0; $i < $rows; $i++) {
                $values[] = isset($stored[$i]) ? $stored[$i]->$column : $empty;
            }
            $restored[$column] = $values;
        }
        return $restored;
    }

    /**
     * Submitted values tidied into the form CodeRunner stores them in.
     *
     * The penalty regime is the one field an author may type loosely — `10%,
     * 20%, ...` means `10, 20, ...` — and CodeRunner 5 tidied it in
     * `clean_question_form()`, on its way to the column. Under CodeRunner 6
     * the column is written from the overrides document, so tidying the
     * question object after the fact no longer reaches what is stored: the
     * value has to be tidied before it becomes an override. Both paths that
     * build an override set from submitted data come through here.
     *
     * @param array $data the submitted data.
     * @return array the same data, tidied.
     */
    protected static function tidy_submitted(array $data): array {
        if (isset($data['penaltyregime']) && !preg_match('/{{.*}}/', trim($data['penaltyregime']))) {
            // Not a Twig expression, so it can be tidied; if it is one, hope
            // for the best, exactly as CodeRunner 5 does.
            $regime = str_replace(['%', ','], ['', ', '], $data['penaltyregime']);
            $data['penaltyregime'] = preg_replace('/ *,? +/', ', ', $regime);
        }
        return $data;
    }

    /**
     * Record the prototype the author picked and what the question overrides
     * of it.
     *
     * The form is the authority for both: it submits the prototype id and the
     * overrides, and `save_question_options()` writes the CodeRunner 5 columns
     * from them rather than the other way round (decision 26). The marker is
     * what tells it which kind of save this is.
     *
     * @param object $fields the submitted data, modified in place.
     */
    protected function record_prototype_and_overrides($fields) {
        $overrides = settings_form::overrides_from(
            self::tidy_submitted((array) $fields),
            self::sections(),
            $this->own_overrides(),
            qtype_coderunner::QUESTION_LAYER
        );

        $prototypeid = $this->chosen_prototypeid();
        [$prototypeid, $overrides] = $this->apply_promotion($fields, $prototypeid, $overrides);

        $fields->prototypeid = $prototypeid;
        $fields->overrides = json_encode($overrides->to_array());
        $fields->{qtype_coderunner::OVERRIDES_SUPPLIED} = 1;

        // A question is never a prototype in CodeRunner 6, and the column
        // retires with the rest of the legacy ones in phase 4. Until then it
        // has to carry a value, because save_question_options() reads it.
        $fields->prototypetype = 0;
    }

    /**
     * Make a prototype out of this question's overrides, if the author asked
     * for one, and say what the question should then refer to.
     *
     * Re-pointing clears the question's overrides, and has to: the new
     * prototype holds them, so keeping them as well would leave every field
     * overridden with exactly the value it now inherits. That is the redundant
     * duplicate the sixth round of review removed from the merging fields —
     * harmless at run time and wrong in intent, because a question holding a
     * copy stops following the prototype it was copied from.
     *
     * Declining to re-point leaves the question exactly as it was, which is
     * not the same question answered twice: the question resolves identically
     * either way today, and differs in what happens when the new prototype is
     * next edited. A re-pointed question follows it; one left alone does not.
     *
     * @param object $fields the submitted data.
     * @param int|null $prototypeid the prototype the author chose.
     * @param override_set $overrides what the question overrides.
     * @return array the prototype id and overrides the question is saved with.
     */
    protected function apply_promotion($fields, ?int $prototypeid, override_set $overrides): array {
        $name = prototype_promotion::requested_name((array) $fields);
        if ($name === '' || $prototypeid === null) {
            return [$prototypeid, $overrides];
        }

        $prototype = prototype_promotion::promote(
            $name,
            $this->picker_courseid(),
            $prototypeid,
            $overrides
        );

        if (!prototype_promotion::repoint_wanted((array) $fields)) {
            return [$prototypeid, $overrides];
        }

        return [
            (int) $prototype->id,
            new override_set([], qtype_coderunner::QUESTION_LAYER),
        ];
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
            // All-or-nothing is a Yes/No menu in the registry-driven section,
            // not the checkbox the CodeRunner 5 form had, so the condition is
            // its value rather than its checked state.
            $mform->disabledIf("mark[$i]", 'allornothing', 'eq', 1);
        }
    }


    /*
     *  A rewritten version of get_per_answer_fields specific to test cases.
     */
    public function get_per_testcase_fields($mform, $label, &$repeatedoptions) {
        $shown = $this->testcase_columns();
        $repeated = [];
        if ($shown['testcode']) {
            $repeated[] = $mform->createElement(
                'textarea',
                'testcode',
                $label,
                ['rows' => 3, 'class' => 'testcaseexpression edit_code']
            );
        }
        if ($shown['stdin']) {
            $repeated[] = $mform->createElement(
                'textarea',
                'stdin',
                get_string('stdin', 'qtype_coderunner'),
                ['rows' => 3, 'class' => 'testcasestdin edit_code']
            );
        }
        if ($shown['expected']) {
            $repeated[] = $mform->createElement(
                'textarea',
                'expected',
                get_string('expected', 'qtype_coderunner'),
                ['rows' => 3, 'class' => 'testcaseresult edit_code']
            );
        }
        if ($shown['extra']) {
            $repeated[] = $mform->createElement(
                'textarea',
                'extra',
                get_string('extra', 'qtype_coderunner'),
                ['rows' => 3, 'class' => 'testcaseresult edit_code']
            );
        }
        $group[] = $mform->createElement(
            'advcheckbox',
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
            'advcheckbox',
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

        // The test type comes before the controls, so that the controls are the
        // last element of a test case whether or not it is offered. The box
        // drawn round a test case in styles.css closes on the controls, and a
        // row after them would fall outside it.
        if ($shown['testtype']) {
            $repeated[] = $mform->createElement(
                'select',
                'testtype',
                get_string('testtype', 'qtype_coderunner'),
                [
                    constants::TESTTYPE_NORMAL   => get_string('testtype_normal', 'qtype_coderunner'),
                    constants::TESTTYPE_PRECHECK => get_string('testtype_precheck', 'qtype_coderunner'),
                    constants::TESTTYPE_BOTH     => get_string('testtype_both', 'qtype_coderunner'),
                ],
                ['class' => 'testtype']
            );
        }

        $repeated[] = $mform->createElement(
            'group',
            'testcasecontrols',
            get_string('testcasecontrols', 'qtype_coderunner'),
            $group,
            null,
            false
        );

        foreach (array_keys(array_filter($shown)) as $field) {
            $repeatedoptions[$field]['type'] = PARAM_RAW;
        }
        $repeatedoptions['mark']['type'] = PARAM_FLOAT;
        $repeatedoptions['ordering']['type'] = PARAM_INT;
        if ($shown['testtype']) {
            $repeatedoptions['testtype']['type'] = PARAM_RAW;
        }

        foreach (array_merge(array_keys(array_filter($shown)), ['testcasecontrols']) as $field) {
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
            // Flatten all the question->options down into the question itself.
            $question->testcode = [];
            $question->expected = [];
            $question->useasexample = [];
            $question->display = [];
            $question->extra = [];
            $question->hiderestiffail = [];

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

            $question->courseid = $COURSE->id;

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
            // This is a new question. Its penalty regime default comes from the
            // Site defaults prototype, which is where every question-field
            // default now lives, rather than from an admin setting that would
            // be a second place for the same value to be configured.
            $question->penaltyregime = qtype_coderunner_util::default_penalty_regime();
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
    // Create 2 empty divs with id id_qtype_coderunner_warning_div,
    // id_qtype_coderunner_error_div for use by JavaScript error handling code.
    private function make_error_div($mform) {
        $mform->addElement('html', "<div id='id_qtype_coderunner_warning_div' class='qtype_coderunner_warning_message'></div>");
        $mform->addElement('html', "<div id='id_qtype_coderunner_error_div' class='qtype_coderunner_error_message'></div>");
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // The prototype first, and nothing else if it is wrong. Every check
        // below reaches the question's settings — the template parameters are
        // evaluated, the penalty regime is Twig expanded, the sample answer is
        // run — and all of those resolve through the chain the prototype names.
        $prototypeerror = prototype_picker::validation_error(
            empty($data[prototype_picker::FIELD]) ? null : (int) $data[prototype_picker::FIELD],
            isset($this->question->options->prototypeid) ? (int) $this->question->options->prototypeid : null
        );
        if ($prototypeerror !== null) {
            $errors[prototype_picker::GROUP] = $prototypeerror;
            return $errors;
        }

        // The name of a prototype being promoted out of this question. Checked
        // beside the prototype rather than with the settings below, because it
        // is a question about the question's type and not about its values.
        $errors += prototype_promotion::validate(
            $data,
            $this->picker_courseid(),
            $this->chosen_prototypeid()
        );

        // The test case columns the form did not show, so that the checks
        // below — and the sample answer run — see every row in full.
        $data = array_merge($data, $this->hidden_testcase_columns($data));

        $this->formquestion = $this->make_question_from_form_data($data);
        $q = $this->formquestion;

        // Every setting below is read from the resolved question rather than
        // from the submitted data. A field the question does not override is
        // greyed by disabledIf, and a disabled control is not submitted, so
        // the submitted data has no key for it; and what wants validating is
        // the value the question will use, whichever layer supplies it.
        if (
            $q->cputimelimitsecs != '' &&
             (!ctype_digit((string) $q->cputimelimitsecs) || intval($q->cputimelimitsecs) <= 0)
        ) {
            $errors['cputimelimitsecs_group'] = get_string('badcputime', 'qtype_coderunner');
        }
        if (
            $q->memlimitmb != '' &&
             (!ctype_digit((string) $q->memlimitmb) || intval($q->memlimitmb) < 0)
        ) {
            $errors['memlimitmb_group'] = get_string('badmemlimit', 'qtype_coderunner');
        }

        if ($q->precheck == constants::PRECHECK_EXAMPLES && $this->num_examples($data) === 0) {
            $errors['precheck_group'] = get_string('precheckingemptyset', 'qtype_coderunner');
        }

        if ($q->sandboxparams != '' && json_decode($q->sandboxparams) === null) {
            $errors['sandboxparams'] = get_string('badsandboxparams', 'qtype_coderunner');
        }

        [$templateerrors, $json] = $this->validate_template_params();
        if (!$templateerrors) {
            $this->formquestion->templateparamsevald = $json;
            $this->formquestion->parameters = json_decode($json, true);
        } else {
            $errors['templateparams'] = $templateerrors;
            $this->formquestion->templateparamsevald = '{}';
        }

        $uiparameters = $this->get_and_twigify_maybe('uiparameters');
        if (!$templateerrors && $uiparameters) {
            $uiparametererrors = $this->validate_ui_parameters($uiparameters);
            if ($uiparametererrors) {
                $errors['uiparameters'] = $uiparametererrors;
            }
        }

        $this->iscombinatorgrader = $q->grader === 'TemplateGrader' && $q->iscombinatortemplate;
        if (!$this->iscombinatorgrader) {
            // Unless it uses a combinator-template grader, a question needs at
            // least one test case.
            $testcaseerrors = $this->validate_test_cases($data);
            $errors = array_merge($errors, $testcaseerrors);
        }

        if ($q->iscombinatortemplate && empty($q->testsplitterre)) {
            $errors['testsplitterre_group'] = get_string('bad_empty_splitter', 'qtype_coderunner');
        }

        $resultcolumnsjson = trim((string) $q->resultcolumns);
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

        if ($q->attachments) {
            // Check a valid regular expression was given.
            // Use '=' as the PCRE delimiter.
            if (@preg_match('=^' . $q->filenamesregex . '$=', null) === false) {
                $errors['filenamesregex_group'] = get_string('badfilenamesregex', 'qtype_coderunner');
            }
        }

        if (count($errors) == 0 && $q->twigall) {
            $errors = $this->validate_twigables();
        }

        if (count($errors) == 0) {
            // Penalty regime is Twiggable, so we have postponed validating
            // until after validate_twigables.
            $penaltyregimeerror = $this->validate_penalty_regime();
            if ($penaltyregimeerror) {
                $errors['penaltyregime_group'] = $penaltyregimeerror;
            }
        }

        if (count($errors) == 0 && !empty($data['validateonsave'])) {
            $testresult = $this->validate_sample_answer($data);
            if ($testresult) {
                $errors['answer'] = $testresult;
            }
        }

        $acelangs = trim((string) $q->acelang);
        if ($acelangs !== '' && strpos($acelangs, ',') !== false) {
            $parsedlangs = qtype_coderunner_util::extract_languages($acelangs);
            if ($parsedlangs === false) {
                $errors['acelang_group'] = get_string('multipledefaults', 'qtype_coderunner');
            } else if (count($parsedlangs[0]) === 0) {
                $errors['acelang_group'] = get_string('badacelangstring', 'qtype_coderunner');
            }
        }

        // Don't allow the teacher to require more attachments than they allow; as this would
        // create a condition that it's impossible for the student to meet.
        if ($q->attachments != -1 && $q->attachments < $q->attachmentsrequired) {
            $errors['attachmentsrequired_group'] = get_string('mustrequirefewer', 'qtype_coderunner');
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
            $templateparamsnoprototype = $templateparamsnoprototype ?? [];  // In case it's empty or null.
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


    // Extract the specified field from the data, and if twigall is set,
    // pass it through twig. Must only be called after the template parameters
    // have been evaluated, as it uses $question->templatparamsevald.
    private function get_and_twigify_maybe($field) {
        $value = trim((string) $this->formquestion->$field);
        if ($value && $this->formquestion->twigall) {
            $question = $this->formquestion;
            $jsonparams = $question->templateparamsevald;
            $parameters = json_decode($jsonparams, true);
            $value = $this->twig_render($value, $parameters, true);
        }
        return $value;
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


    private function validate_penalty_regime() {
        // Check the penalty regime and return an error string or an empty string if OK.
        $errorstring = '';
        $expectedpr = '/[0-9]+(\.[0-9]*)?%?([, ] *[0-9]+(\.[0-9]*)?%?)*([, ] *...)?/';
        $penaltyregime = $this->get_and_twigify_maybe('penaltyregime');
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
        $parameters['QUESTION'] = $question; // Shouldn't really include this but removing it might break existing questions.

        // Try twig expanding everything (see question::twig_all), with strict_variables true.
        $twigablefields = qtype_coderunner::twigablefields();
        foreach ($twigablefields as $field) {
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
    private function validate_sample_answer($data) {
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
            [$mark, $state, $cachedata] = $this->formquestion->grade_response(
                $response,
                false, // Not a precheck.
                true   // Is a validation run.
            );
        } catch (Exception $e) {
            return $e->getMessage();
        }

        // Return either an empty string if run was good or an error message.
        if ($mark == 1.0) {
            return '';
        } else {
            $outcome = $this->formquestion->unserialize_outcome($cachedata['_testoutcome']);
            $error = $outcome->validation_error_message();
            if ($this->iscombinatorgrader) {
                $error = $this->fix_template_grader_error($error);
            }
            return $error;
        }
    }
    private function num_examples($data) {
        return isset($data['useasexample']) ? count(array_filter($data['useasexample'])) : 0;
    }


    // Render the given Twig text with the given params, using the global
    // $USER variable (the question author) as a dummy student.
    // @return Rendered text.
    private function twig_render($text, $params = [], $isstrict = false) {
        global $USER;
        $params['STUDENT'] = new qtype_coderunner_student($USER);
        $params['QUIZ'] = new qtype_coderunner_quiz();
        return qtype_coderunner_twig::render($text, $params, $isstrict);
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

        [$category] = explode(',', $question->category);
        $contextid = $DB->get_field('question_categories', 'contextid', ['id' => $category]);
        $question->contextid = $contextid;

        // CodeRunner 5's cleaning still has three jobs here, and the first of
        // them is load-bearing: it is what turns the submitted test case arrays
        // into $question->testcases, which the sample answer run needs. It also
        // nulls a sandbox of DEFAULT and tidies the penalty regime. It runs
        // before the chain is resolved, so resolution has the last word on
        // every inherited field.
        $question->prototypetype = 0;
        $qtype = new qtype_coderunner();
        $qtype->clean_question_form($question, true);

        // Resolve the chain, so that every setting on this object is the one
        // the question will actually use. Two reasons it has to happen here
        // rather than being read from the submitted data:
        //
        // First, a field the question does not override is greyed by
        // disabledIf, and a disabled control is not submitted at all, so the
        // submitted data has no key for it: validation reading $data would
        // check nothing, or would check null. Second, the collected fields are
        // merged along the chain, which needs every layer's contribution and
        // not just the question's.
        //
        // The resolver is the question type's own, the same one the run-time
        // path uses, so the form cannot drift from it.
        $question->prototypeid = empty($question->prototypeid) ? null : (int) $question->prototypeid;
        $question->overrides = json_encode(
            settings_form::overrides_from(
                self::tidy_submitted($data),
                self::sections(),
                $this->own_overrides(),
                qtype_coderunner::QUESTION_LAYER
            )->to_array()
        );
        $qtype->resolve_settings_in_place($question);

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

                // Tricky bug fix follows. Using a fixed seed for the random
                // number generator, and subsequently setting it with a call to
                // mt_srand, could result in Moodle allocating the same draftitemid
                // to two different file pickers in two simultaneously
                // open questions. It's a Moodle core bug, but circumvented
                // by never using a fixed seed in CodeRunner.
                $seed = mt_rand(); // Don't care about randomisation here.
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

    // If we're using a combinator template grader, any links back to the failing
    // test case, needed by the "copy got to expected" button, will be incorrect
    // if the ordering of the test cases is not the same as the ordering of
    // the rows in the (as yet unsaved) testcases table. This function fixes
    // the error message to include the correct test case number. It's a bit
    // of a hack, but it's the best I can do, since the testresults table built
    // by the question author does not know about the original test ordering.
    private function fix_template_grader_error($error) {
        $tests = $this->formquestion->testcases;

        // First make a map from rownum at test time to rownum in the edit form.
        $pairs = [];
        foreach ($tests as $test) {
            $pairs[] = [$test->ordering, $test->rownum];  // List of (ordering, rownum) pairs.
        }

        // Sort the list by ordering.
        $orderings = array_column($pairs, 0);
        array_multisort($orderings, SORT_ASC, $pairs);

        // Build the map from rownum at test time to rownum in the edit form.
        $map = [];
        $i = 0;
        foreach (array_column($pairs, 1) as $oldrownum) {
            $map[strval($i++)] = strval($oldrownum);
        }

        // We're now ready to do all the remapping of row numbers.
        // First convert all the test numbers to 0 origin.
        $error = preg_replace_callback('/>Test case (\d+)</', function ($matches) {
            return '>Test case ' . strval(intval($matches[1] - 1) . '<');
        }, $error);

        // Need a 2-pass process to do the substitutions.
        // Pass 1.
        $leadinstrings = ['#id_testcode_', '#id_expected_', 'id_fail_expected_', 'id_got_',
            '>Test case ', 'coderunner-failed-test failrow_'];
        foreach ($leadinstrings as $leadinstring) {
            $pattern = '/' . $leadinstring . '([0-9]+)/';
            $error = preg_replace_callback(
                $pattern,
                function ($matches) use ($map, $leadinstring) {
                    return $leadinstring . 'XXX' . $map[intval($matches[1])];
                },
                $error
            );
        }

        // Pass 2. Delete all the 'XXX's.
        foreach ($leadinstrings as $leadinstring) {
            $error = str_replace($leadinstring . 'XXX', $leadinstring, $error);
        }

        // Lastly convert the test numbers back to 1 origin.
        $error = preg_replace_callback('/>Test case (\d+)</', function ($matches) {
            return '>Test case ' . strval(intval($matches[1]) + 1 . '<');
        }, $error);

        return $error;
    }
}
