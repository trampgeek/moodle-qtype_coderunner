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
 * The definitive description of every field a CodeRunner question has.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use qtype_coderunner\constants;

/**
 * The schema of a CodeRunner question: which fields exist, which of them a
 * prototype can supply, how a layer's value combines with an inherited one,
 * and where each appears in the authoring form.
 *
 * Resolution, both authoring forms, export, backup and the migration all work
 * from this one table, so that adding or changing a field is a change here
 * rather than in every one of those places.
 *
 * It is a collection of fields and nothing more: what a field is, and the
 * vocabulary of kinds, merge rules, types and form sections, all belong to
 * {@see field}.
 */
class field_registry {
    /** @var array|null Lazily built field table, name => field. */
    private static $fields = null;

    /**
     * Columns of question_coderunner_options that are not registry fields.
     *
     * They are read by the migration and by legacy import and restore, and are
     * dropped once legacy support is retired. Listing them explicitly means the
     * converter can neither silently carry nor silently drop a column.
     *
     * @return array column name => why it is not a field.
     */
    public static function legacy_columns() {
        return [
            // Superseded by the question's prototype id.
            'coderunnertype' => 'prototype is referred to by id; the name lives on the prototype',
            'prototypetype' => 'prototypes are no longer questions',
            // Dropped outright.
            'answerboxcolumns' => 'defunct; dropped in CodeRunner 6',
            // Defunct long before CodeRunner 6, and absent from extra_question_fields_static().
            'enablecombinator' => 'superseded by iscombinatortemplate before CodeRunner 3.1',
            'combinatortemplate' => 'superseded by template before CodeRunner 3.1',
            'pertesttemplate' => 'superseded by template before CodeRunner 3.1',
            // Storage, not a field: a cache of one layer's evaluated parameters.
            'templateparamsevald' => 'cache of one layer\'s evaluation, not an authored setting',
        ];
    }

    /**
     * Columns of question_coderunner_options that carry the resolution itself,
     * rather than a field the resolution produces.
     *
     * They are neither registry fields nor legacy: `prototypeid` says which
     * chain a question resolves against and `overrides` is what the question
     * contributes to it, so they are the inputs resolution reads and not values
     * it resolves. Unlike the legacy columns they are not dropped in phase 4.
     *
     * Listed here so that the completeness check over
     * extra_question_fields_static() can account for every column, and so that
     * neither can be mistaken for a field and given a merge rule.
     *
     * @return array column name => what it holds.
     */
    public static function resolution_columns() {
        return [
            'prototypeid' => 'the prototype whose chain the question resolves against',
            'overrides' => 'what the question overrides of that chain, as a JSON document',
        ];
    }

    /**
     * The values a CHOICE field may take, as value => label.
     *
     * These live here rather than in the forms because the registry is meant to
     * describe a field completely: a form that has to know, on its own, what
     * `precheck` may be set to is a second place the field is defined. The
     * question form built each of these inline, which is why the same list had
     * to be written again for the prototype editor.
     *
     * Built on demand rather than in the field table, because the labels need
     * get_string() and the UI plugin list is discovered from the plugins
     * present, neither of which belongs in a static definition.
     *
     * @param string $name the field name.
     * @return array|null value => label, or null if the field is not a choice.
     */
    public static function choices($name) {
        $field = self::get($name);
        if ($field->type !== field_type::CHOICE) {
            return null;
        }

        switch ($name) {
            case 'precheck':
                return [
                    constants::PRECHECK_DISABLED => get_string('precheck_disabled', 'qtype_coderunner'),
                    constants::PRECHECK_EMPTY => get_string('precheck_empty', 'qtype_coderunner'),
                    constants::PRECHECK_EXAMPLES => get_string('precheck_examples', 'qtype_coderunner'),
                    constants::PRECHECK_SELECTED => get_string('precheck_selected', 'qtype_coderunner'),
                    constants::PRECHECK_ALL => get_string('precheck_all', 'qtype_coderunner'),
                ];

            case 'displayfeedback':
                return [
                    constants::FEEDBACK_USE_QUIZ => get_string('feedback_quiz', 'qtype_coderunner'),
                    constants::FEEDBACK_SHOW => get_string('feedback_show', 'qtype_coderunner'),
                    constants::FEEDBACK_HIDE => get_string('feedback_hide', 'qtype_coderunner'),
                ];

            case 'giveupallowed':
                return [
                    constants::GIVEUP_NEVER => get_string('giveup_never', 'qtype_coderunner'),
                    constants::GIVEUP_AFTER_MAX_MARKS => get_string('giveup_aftermaxmarks', 'qtype_coderunner'),
                    constants::GIVEUP_ALWAYS => get_string('giveup_always', 'qtype_coderunner'),
                ];

            case 'templateparamslang':
                // The value None means the parameters are already JSON and
                // are used as they stand. The rest are evaluated, Twig in process and the
                // others on the sandbox server.
                return [
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

            case 'uiplugin':
                return \qtype_coderunner_ui_plugins::get_instance()->dropdownlist();

            case 'sandbox':
                // DEFAULT means "whichever sandbox claims the language", which
                // is what almost every question wants.
                $sandboxes = ['DEFAULT' => 'DEFAULT'];
                foreach (array_keys(\qtype_coderunner_sandbox::enabled_sandboxes()) as $name) {
                    $sandboxes[$name] = $name;
                }
                return $sandboxes;

            default:
                throw new \coding_exception("Field '$name' is a choice but the registry lists no values for it.");
        }
    }

    /**
     * Every field, keyed by name.
     *
     * @return field[] name => field.
     */
    public static function all() {
        if (self::$fields === null) {
            self::$fields = [];
            foreach (self::definitions() as $field) {
                self::$fields[$field->name] = $field;
            }
        }
        return self::$fields;
    }

    /**
     * One field by name.
     *
     * @param string $name the field name.
     * @return field the field.
     * @throws \coding_exception if there is no such field.
     */
    public static function get($name) {
        $fields = self::all();
        if (!isset($fields[$name])) {
            throw new \coding_exception("Unknown CodeRunner question field '$name'.");
        }
        return $fields[$name];
    }

    /**
     * Whether a field of the given name exists.
     *
     * @param string $name the field name.
     * @return bool true if it is a registry field.
     */
    public static function exists($name) {
        return isset(self::all()[$name]);
    }

    /**
     * All fields of one kind.
     *
     * @param field_kind $kind the kind to select.
     * @return field[] name => field.
     */
    public static function of_kind(field_kind $kind) {
        return array_filter(self::all(), function ($field) use ($kind) {
            return $field->kind === $kind;
        });
    }

    /**
     * The fields a layer may supply, being everything a prototype can set.
     *
     * @return field[] name => field.
     */
    public static function inheritable() {
        return array_filter(self::all(), function ($field) {
            return $field->kind->is_inheritable();
        });
    }

    /**
     * The fields that are Twig expanded when twigall is set, in the stage that
     * prepares a question for display.
     *
     * The template is deliberately absent: it is expanded later, during
     * grading, because it needs the student's answer.
     *
     * @return field[] name => field.
     */
    public static function twigable() {
        return array_filter(self::all(), function ($field) {
            return $field->twigable;
        });
    }

    /**
     * The field table.
     *
     * Keyed by nothing: all() keys each field by its own name, so a key and a
     * name cannot disagree. Positional arguments are name, kind, type and
     * section, which every field has; everything else is named at the point of
     * use, so that a mistyped argument is a fatal error rather than a silently
     * ignored array key.
     *
     * Defaults are those a question gets when no layer in its chain sets the
     * field, and are the values that move into the Site defaults prototype.
     * They match CodeRunner 5's install.xml defaults, except penaltyregime,
     * whose default came from the qtype_coderunner/default_penalty_regime admin
     * setting. That setting is retired once Site defaults holds the value, so
     * that a question-field default has one place it is configured.
     *
     * @return field[] every field, in form-section order.
     */
    private static function definitions() {
        return [
            // Instance fields: the question's own, never inherited.
            new field('answer', field_kind::INSTANCE, field_type::TEXT, form_section::QUESTION, twigable: true, iscode: true),
            new field(
                'answerpreload',
                field_kind::INSTANCE,
                field_type::TEXT,
                form_section::QUESTION,
                twigable: true,
                iscode: true,
            ),
            new field('validateonsave', field_kind::INSTANCE, field_type::BOOLEAN, form_section::QUESTION, default: 1),
            new field('showsource', field_kind::INSTANCE, field_type::BOOLEAN, form_section::TEMPLATE, default: 0),

            // Collected fields: every layer contributes.
            new field(
                'templateparams',
                field_kind::COLLECTED,
                field_type::JSON,
                form_section::PARAMETERS,
                default: '',
                merge: merge_rule::AFTER_EVALUATION,
                perlayerwith: ['templateparamslang', 'templateparamsevalpertry'],
                iscode: true,
            ),
            new field(
                'templateparamslang',
                field_kind::COLLECTED,
                field_type::CHOICE,
                form_section::PARAMETERS,
                default: 'twig',
                merge: merge_rule::AFTER_EVALUATION,
            ),
            new field(
                'templateparamsevalpertry',
                field_kind::COLLECTED,
                field_type::BOOLEAN,
                form_section::PARAMETERS,
                default: 0,
                merge: merge_rule::AFTER_EVALUATION,
            ),
            new field(
                'uiparameters',
                field_kind::COLLECTED,
                field_type::JSON,
                form_section::UI,
                default: '',
                merge: merge_rule::IF_SAME_UI,
                twigable: true,
                iscode: true,
            ),
            new field(
                'supportfiles',
                field_kind::COLLECTED,
                field_type::FILES,
                form_section::FILES,
                merge: merge_rule::BY_FILENAME,
                incolumn: false,
                labelstring: 'datafiles',
            ),

            // Grading and penalties.
            new field('allornothing', field_kind::INHERITED, field_type::BOOLEAN, form_section::GRADING, default: 1),
            new field(
                'penaltyregime',
                field_kind::INHERITED,
                field_type::CHAR,
                form_section::GRADING,
                default: '10, 20, ...',
                twigable: true,
            ),
            new field('precheck', field_kind::INHERITED, field_type::CHOICE, form_section::GRADING, default: 0),
            new field('hidecheck', field_kind::INHERITED, field_type::BOOLEAN, form_section::GRADING, default: 0),
            new field(
                'displayfeedback',
                field_kind::INHERITED,
                field_type::CHOICE,
                form_section::GRADING,
                default: 1,
                labelstring: 'feedback',
            ),
            new field(
                'giveupallowed',
                field_kind::INHERITED,
                field_type::CHOICE,
                form_section::GRADING,
                default: 0,
                labelstring: 'giveup',
            ),

            // User interface.
            new field(
                'uiplugin',
                field_kind::INHERITED,
                field_type::CHOICE,
                form_section::UI,
                default: 'ace',
                labelstring: 'student_answer',
            ),
            new field('useace', field_kind::INHERITED, field_type::BOOLEAN, form_section::UI, default: 1),
            new field('answerboxlines', field_kind::INHERITED, field_type::INTEGER, form_section::UI, default: 18),
            new field(
                'attachments',
                field_kind::INHERITED,
                field_type::INTEGER,
                form_section::UI,
                default: 0,
                labelstring: 'allowattachments',
            ),
            new field('attachmentsrequired', field_kind::INHERITED, field_type::INTEGER, form_section::UI, default: 0),
            new field('maxfilesize', field_kind::INHERITED, field_type::INTEGER, form_section::UI, default: 0),
            new field('filenamesregex', field_kind::INHERITED, field_type::TEXT, form_section::UI, default: ''),
            new field('filenamesexplain', field_kind::INHERITED, field_type::TEXT, form_section::UI, default: ''),

            // Template and grader.
            new field('template', field_kind::INHERITED, field_type::TEXT, form_section::TEMPLATE, default: '', iscode: true),
            new field('iscombinatortemplate', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 0),
            new field('grader', field_kind::INHERITED, field_type::CHAR, form_section::TEMPLATE, default: 'EqualityGrader'),
            new field('resultcolumns', field_kind::INHERITED, field_type::JSON, form_section::TEMPLATE, default: '', iscode: true),
            new field('testsplitterre', field_kind::INHERITED, field_type::CHAR, form_section::TEMPLATE, default: ''),
            new field('allowmultiplestdins', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 0),
            new field('extractcodefromjson', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 1),
            new field('twigall', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 0),
            new field('hoisttemplateparams', field_kind::INHERITED, field_type::BOOLEAN, form_section::PARAMETERS, default: 1),

            // Sandbox, languages and limits.
            new field('language', field_kind::INHERITED, field_type::CHAR, form_section::SANDBOX, default: ''),
            new field(
                'acelang',
                field_kind::INHERITED,
                field_type::CHAR,
                form_section::SANDBOX,
                default: '',
                labelstring: 'ace-language',
            ),
            new field('sandbox', field_kind::INHERITED, field_type::CHOICE, form_section::SANDBOX),
            new field('sandboxparams', field_kind::INHERITED, field_type::JSON, form_section::SANDBOX, iscode: true),
            new field(
                'cputimelimitsecs',
                field_kind::INHERITED,
                field_type::INTEGER,
                form_section::SANDBOX,
                labelstring: 'cputime',
            ),
            new field('memlimitmb', field_kind::INHERITED, field_type::INTEGER, form_section::SANDBOX, labelstring: 'memorylimit'),

            // Extras.
            new field(
                'globalextra',
                field_kind::INHERITED,
                field_type::TEXT,
                form_section::EXTRAS,
                default: '',
                twigable: true,
                iscode: true,
            ),
            new field(
                'prototypeextra',
                field_kind::INHERITED,
                field_type::TEXT,
                form_section::EXTRAS,
                default: '',
                twigable: true,
                iscode: true,
            ),

            // New in CodeRunner 6: which test case fields the form offers.
            new field('showtestcode', field_kind::INHERITED, field_type::BOOLEAN, form_section::TESTCASEFIELDS,
                default: 1, incolumn: false),
            new field('showstdin', field_kind::INHERITED, field_type::BOOLEAN, form_section::TESTCASEFIELDS,
                default: 1, incolumn: false),
            new field('showexpected', field_kind::INHERITED, field_type::BOOLEAN, form_section::TESTCASEFIELDS,
                default: 1, incolumn: false),
            new field('showtestextra', field_kind::INHERITED, field_type::BOOLEAN, form_section::TESTCASEFIELDS,
                default: 1, incolumn: false),
        ];
    }
}
