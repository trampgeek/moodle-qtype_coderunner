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
 * The record of what the CodeRunner 5 authoring form offered, and what
 * accounts for each of those controls in CodeRunner 6.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Enforces the implementation notes' rule that the field set comes from the
 * current code: every control of `edit_coderunner_form.php` must appear in the
 * CodeRunner 6 forms unless the design document says otherwise.
 *
 * The rule has so far been remembered rather than checked, and M7 replaces the
 * form that is its only written record. A control dropped in that rewrite would
 * be silent: the question would go on resolving the field from its prototype,
 * and only an author looking for the setting would find it gone. So the whole
 * inventory is recorded here first, each entry saying what accounts for it, and
 * three checks hold the record and the code to each other.
 *
 * The inventory is read from the form's **source** rather than from a built
 * form. Two reasons: `edit_coderunner_form` cannot be constructed without a
 * question, a context and a submit URL, and `MoodleQuickForm::getElementValue()`
 * and `toHtml()` both fail under PHP 8.3 through PEAR code that calls a
 * non-static method statically, so a form's elements cannot reliably be read in
 * a test at all. Reading the source also keeps working through the rewrite,
 * which is the point at which the check earns its place.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(field_registry::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class form_control_inventory_test extends \advanced_testcase {
    /** @var string The control carries a registry field, named by the entry's value. */
    const FIELD = 'field';

    /** @var string The control belongs to the question itself and is not a registry field. */
    const QUESTION = 'question';

    /** @var string A header, group or static element: CodeRunner 5's layout, replaced by the registry's sections. */
    const LAYOUT = 'layout';

    /** @var string The control goes in CodeRunner 6, for the reason the entry gives. */
    const REMOVED = 'removed';

    /**
     * Every control of the CodeRunner 5 authoring form, and what accounts for
     * it in CodeRunner 6.
     *
     * Keyed by the element name as the form defines it. A FIELD entry's value
     * is the registry field it carries, which is usually but not always the
     * same name: the support files are `datafiles` on the form and
     * `supportfiles` in the registry, a rename the registry already records in
     * that field's `labelstring`.
     *
     * @return array element name => [classification, detail].
     */
    public static function legacy_controls(): array {
        return [
            // Open at the top of the form.
            'answer' => [self::FIELD, 'answer'],
            'answerpreload' => [self::FIELD, 'answerpreload'],
            'validateonsave' => [self::FIELD, 'validateonsave'],
            'datafiles' => [self::FIELD, 'supportfiles'],

            // Grading and penalties.
            'allornothing' => [self::FIELD, 'allornothing'],
            'penaltyregime' => [self::FIELD, 'penaltyregime'],
            'precheck' => [self::FIELD, 'precheck'],
            'hidecheck' => [self::FIELD, 'hidecheck'],
            'displayfeedback' => [self::FIELD, 'displayfeedback'],
            'giveupallowed' => [self::FIELD, 'giveupallowed'],

            // User interface, including the answer box and attachments.
            'uiplugin' => [self::FIELD, 'uiplugin'],
            'uiparameters' => [self::FIELD, 'uiparameters'],
            'useace' => [self::FIELD, 'useace'],
            'answerboxlines' => [self::FIELD, 'answerboxlines'],
            'acelang' => [self::FIELD, 'acelang'],
            'attachments' => [self::FIELD, 'attachments'],
            'attachmentsrequired' => [self::FIELD, 'attachmentsrequired'],
            'maxfilesize' => [self::FIELD, 'maxfilesize'],
            'filenamesregex' => [self::FIELD, 'filenamesregex'],
            'filenamesexplain' => [self::FIELD, 'filenamesexplain'],

            // Template and grader.
            'template' => [self::FIELD, 'template'],
            'iscombinatortemplate' => [self::FIELD, 'iscombinatortemplate'],
            'grader' => [self::FIELD, 'grader'],
            'resultcolumns' => [self::FIELD, 'resultcolumns'],
            'testsplitterre' => [self::FIELD, 'testsplitterre'],
            'allowmultiplestdins' => [self::FIELD, 'allowmultiplestdins'],
            'extractcodefromjson' => [self::FIELD, 'extractcodefromjson'],
            'showsource' => [self::FIELD, 'showsource'],

            // Template parameters. Note that `showsource` above is the control
            // the notes call template debugging; it is a stored column and a
            // registry field, not a per-session option.
            'templateparams' => [self::FIELD, 'templateparams'],
            'templateparamslang' => [self::FIELD, 'templateparamslang'],
            'templateparamsevalpertry' => [self::FIELD, 'templateparamsevalpertry'],
            'hoisttemplateparams' => [self::FIELD, 'hoisttemplateparams'],
            'twigall' => [self::FIELD, 'twigall'],

            // Sandbox, languages and limits.
            'sandbox' => [self::FIELD, 'sandbox'],
            'sandboxparams' => [self::FIELD, 'sandboxparams'],
            'language' => [self::FIELD, 'language'],
            'cputimelimitsecs' => [self::FIELD, 'cputimelimitsecs'],
            'memlimitmb' => [self::FIELD, 'memlimitmb'],

            // Extras. Both are inheritable in CodeRunner 6, although the old
            // form puts the global extra beside the question's own fields.
            'globalextra' => [self::FIELD, 'globalextra'],
            'prototypeextra' => [self::FIELD, 'prototypeextra'],

            // The test cases, which are rows of question_coderunner_tests
            // rather than settings, so they are not registry fields and the
            // registry's TESTCASEFIELDS switches decide only which of them the
            // form offers.
            'testcode' => [self::QUESTION, 'test case column'],
            'stdin' => [self::QUESTION, 'test case column'],
            'expected' => [self::QUESTION, 'test case column'],
            'extra' => [self::QUESTION, 'test case column'],
            'testtype' => [self::QUESTION, 'test case column'],
            'useasexample' => [self::QUESTION, 'test case column'],
            'display' => [self::QUESTION, 'test case column'],
            'hiderestiffail' => [self::QUESTION, 'test case column'],
            'mark' => [self::QUESTION, 'test case column'],
            'ordering' => [self::QUESTION, 'test case column'],

            // The sample answer's attachments: a file area of the question,
            // not a setting, and not merged along the chain the way support
            // files are.
            'sampleanswerattachments' => [self::QUESTION, 'file area of the question'],

            // CodeRunner 5's arrangement of the above, which the registry's
            // form sections replace.
            'answerhdr' => [self::LAYOUT, 'header'],
            'answerpreloadhdr' => [self::LAYOUT, 'header'],
            'globalextrahdr' => [self::LAYOUT, 'header'],
            'testcasehdr' => [self::LAYOUT, 'header'],
            'fileheader' => [self::LAYOUT, 'header'],
            'attachmentoptions' => [self::LAYOUT, 'header'],
            'questiontypeheader' => [self::LAYOUT, 'header'],
            'questiontypehelpheader' => [self::LAYOUT, 'header'],
            'customisationheader' => [self::LAYOUT, 'header'],
            'advancedcustomisationheader' => [self::LAYOUT, 'header'],
            'answerbox_group' => [self::LAYOUT, 'group'],
            'coderunner_type_group' => [self::LAYOUT, 'group'],
            'coderunner_type_checkboxes' => [self::LAYOUT, 'group'],
            'coderunner_precheck_group' => [self::LAYOUT, 'group'],
            'coderunner_feedback_group' => [self::LAYOUT, 'group'],
            'coderunner_giveup_group' => [self::LAYOUT, 'group'],
            'markinggroup' => [self::LAYOUT, 'group'],
            'gradingcontrols' => [self::LAYOUT, 'group'],
            'languages' => [self::LAYOUT, 'group'],
            'filenamesgroup' => [self::LAYOUT, 'group'],
            'prototypecontrols' => [self::LAYOUT, 'group'],
            'sandboxcontrols' => [self::LAYOUT, 'group'],
            'templatecontrols' => [self::LAYOUT, 'group'],
            'twigcontrols' => [self::LAYOUT, 'group'],
            'uicontrols' => [self::LAYOUT, 'group'],
            'uiparametergroup' => [self::LAYOUT, 'group'],
            'testcasecontrols' => [self::LAYOUT, 'group'],

            // Gone in CodeRunner 6.
            'coderunnertype' => [
                self::REMOVED,
                'the question type menu becomes the prototype picker, which records prototypeid',
            ],
            'customise' => [
                self::REMOVED,
                'design section 7: every setting is visible, so there is nothing for the checkbox to reveal',
            ],
            'prototypetype' => [self::REMOVED, 'prototypes are no longer questions'],
            'typename' => [self::REMOVED, 'named the prototype a question was being turned into'],
            'saved_prototype_type' => [self::REMOVED, 'guarded a change to prototypetype'],
            'brokenquestionmessage' => [
                self::REMOVED,
                'carried the missing-prototype and duplicate-prototype errors that design section 7 removes',
            ],
            'badquestionload' => [
                self::REMOVED,
                'dead in CodeRunner 5: nothing ever sets it, and the JavaScript that reveals it only hides it',
            ],
        ];
    }

    /**
     * Registry fields that no CodeRunner 5 control accounts for, with the
     * reason each is new.
     *
     * The list is deliberately short and explicit. A field appearing here that
     * ought to have a CodeRunner 5 control is the mistake this guards against:
     * an invented setting that no existing question has a value for.
     *
     * @return array field name => why it is new.
     */
    public static function new_in_6(): array {
        return [
            'showtestcode' => 'which test case fields the form offers; CodeRunner 5 always offered all four',
            'showstdin' => 'which test case fields the form offers; CodeRunner 5 always offered all four',
            'showexpected' => 'which test case fields the form offers; CodeRunner 5 always offered all four',
            'showtestextra' => 'which test case fields the form offers; CodeRunner 5 always offered all four',
        ];
    }

    /**
     * Every inventory entry is classified, and a FIELD entry names a field the
     * registry actually has.
     */
    public function test_every_recorded_control_is_accounted_for(): void {
        $known = [self::FIELD, self::QUESTION, self::LAYOUT, self::REMOVED];
        $fields = field_registry::all();
        $problems = [];

        foreach (self::legacy_controls() as $element => [$classification, $detail]) {
            if (!in_array($classification, $known, true)) {
                $problems[] = "$element: unknown classification '$classification'";
            } else if ($detail === '') {
                $problems[] = "$element: classified $classification with no detail";
            } else if ($classification === self::FIELD && !array_key_exists($detail, $fields)) {
                $problems[] = "$element: mapped to '$detail', which is not a registry field";
            }
        }

        $this->assertSame([], $problems, 'Controls whose record of what accounts for them does not hold.');
    }

    /**
     * The authoring form defines nothing the inventory has missed.
     *
     * This is what stops the record drifting from the code it records. It is
     * satisfied either by the inventory or by the registry, so that it goes on
     * holding after M7 replaces the form: the new form's controls are registry
     * fields, and anything else it adds has to be recorded here deliberately.
     */
    public function test_the_form_defines_nothing_the_inventory_has_missed(): void {
        $found = self::elements_of_the_authoring_form();

        // A regex over source is brittle, and a pattern that stopped matching
        // would turn this check into one that always passes. The CodeRunner 5
        // form defines 85 named elements, so a scan returning a handful means
        // the pattern has stopped reading the file rather than that the form
        // has shrunk.
        $this->assertGreaterThan(
            60,
            count($found),
            'The element scan found almost nothing, so it has stopped reading the form rather than passing.'
        );

        $unrecorded = array_diff(
            $found,
            array_keys(self::legacy_controls()),
            array_keys(field_registry::all())
        );

        $this->assertSame(
            [],
            array_values($unrecorded),
            'Controls in edit_coderunner_form.php that the inventory does not record. '
                . 'Add each one, saying what accounts for it in CodeRunner 6.'
        );
    }

    /**
     * Every registry field either comes from a CodeRunner 5 control or is
     * declared new, so a field cannot be invented without saying so.
     */
    public function test_every_registry_field_comes_from_the_form_or_is_declared_new(): void {
        $accountedfor = [];
        foreach (self::legacy_controls() as [$classification, $detail]) {
            if ($classification === self::FIELD) {
                $accountedfor[] = $detail;
            }
        }

        $unaccounted = array_diff(
            array_keys(field_registry::all()),
            $accountedfor,
            array_keys(self::new_in_6())
        );

        $this->assertSame(
            [],
            array_values($unaccounted),
            'Registry fields with neither a CodeRunner 5 control nor an entry in new_in_6().'
        );
    }

    /**
     * A control mapped to a field stored in a column must reach a column the
     * question type actually loads, which closes the loop from control to field
     * to column.
     */
    public function test_column_backed_controls_reach_a_loaded_column(): void {
        $columns = \qtype_coderunner::extra_question_fields_static();
        array_shift($columns);  // Discard the table name.

        $missing = [];
        foreach (self::legacy_controls() as $element => [$classification, $detail]) {
            if ($classification !== self::FIELD) {
                continue;
            }
            $field = field_registry::get($detail);
            if ($field->incolumn && !in_array($field->name, $columns, true)) {
                $missing[] = "$element -> $detail";
            }
        }

        $this->assertSame([], $missing, 'Controls whose field claims a column that is not loaded.');
    }

    /**
     * The element names the authoring form defines, read from its source.
     *
     * Elements added without a quoted name — the two static `html` divs the
     * JavaScript writes its messages into — carry no value and have no name to
     * record, so they are not matched.
     *
     * @return string[] the element names, in no particular order.
     */
    private static function elements_of_the_authoring_form(): array {
        global $CFG;

        $source = file_get_contents($CFG->dirroot . '/question/type/coderunner/edit_coderunner_form.php');

        // Matches addElement('type', 'name', ...) and createElement likewise,
        // then addGroup('name', ...). An element's type is sometimes a
        // variable, and createElement is written with a capital C in one
        // place, hence the alternation and the case-insensitive match.
        $pattern = '/\b(?:addElement|createElement)\s*\(\s*(?:\'[a-z]+\'|\$\w+)\s*,\s*\'(\w+)\'/i';
        preg_match_all($pattern, $source, $elements);

        $pattern = '/\baddGroup\s*\(\s*(?:\[[^\]]*\]|\$\w+)\s*,\s*\'(\w+)\'/i';
        preg_match_all($pattern, $source, $groups);

        return array_values(array_unique(array_merge($elements[1], $groups[1])));
    }
}
