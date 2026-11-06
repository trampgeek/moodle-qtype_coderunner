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
            new field('answer', field_kind::INSTANCE, field_type::TEXT, form_section::QUESTION, twigable: true),
            new field('answerpreload', field_kind::INSTANCE, field_type::TEXT, form_section::QUESTION, twigable: true),
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
            ),
            new field(
                'supportfiles',
                field_kind::COLLECTED,
                field_type::FILES,
                form_section::FILES,
                merge: merge_rule::BY_FILENAME,
                incolumn: false,
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
            new field('displayfeedback', field_kind::INHERITED, field_type::CHOICE, form_section::GRADING, default: 1),
            new field('giveupallowed', field_kind::INHERITED, field_type::CHOICE, form_section::GRADING, default: 0),

            // User interface.
            new field('uiplugin', field_kind::INHERITED, field_type::CHAR, form_section::UI, default: 'ace'),
            new field('useace', field_kind::INHERITED, field_type::BOOLEAN, form_section::UI, default: 1),
            new field('answerboxlines', field_kind::INHERITED, field_type::INTEGER, form_section::UI, default: 18),
            new field('attachments', field_kind::INHERITED, field_type::INTEGER, form_section::UI, default: 0),
            new field('attachmentsrequired', field_kind::INHERITED, field_type::INTEGER, form_section::UI, default: 0),
            new field('maxfilesize', field_kind::INHERITED, field_type::INTEGER, form_section::UI, default: 0),
            new field('filenamesregex', field_kind::INHERITED, field_type::TEXT, form_section::UI, default: ''),
            new field('filenamesexplain', field_kind::INHERITED, field_type::TEXT, form_section::UI, default: ''),

            // Template and grader.
            new field('template', field_kind::INHERITED, field_type::TEXT, form_section::TEMPLATE, default: ''),
            new field('iscombinatortemplate', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 0),
            new field('grader', field_kind::INHERITED, field_type::CHAR, form_section::TEMPLATE, default: 'EqualityGrader'),
            new field('resultcolumns', field_kind::INHERITED, field_type::JSON, form_section::TEMPLATE, default: ''),
            new field('testsplitterre', field_kind::INHERITED, field_type::CHAR, form_section::TEMPLATE, default: ''),
            new field('allowmultiplestdins', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 0),
            new field('extractcodefromjson', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 1),
            new field('twigall', field_kind::INHERITED, field_type::BOOLEAN, form_section::TEMPLATE, default: 0),
            new field('hoisttemplateparams', field_kind::INHERITED, field_type::BOOLEAN, form_section::PARAMETERS, default: 1),

            // Sandbox, languages and limits.
            new field('language', field_kind::INHERITED, field_type::CHAR, form_section::SANDBOX, default: ''),
            new field('acelang', field_kind::INHERITED, field_type::CHAR, form_section::SANDBOX, default: ''),
            new field('sandbox', field_kind::INHERITED, field_type::CHAR, form_section::SANDBOX),
            new field('sandboxparams', field_kind::INHERITED, field_type::JSON, form_section::SANDBOX),
            new field('cputimelimitsecs', field_kind::INHERITED, field_type::INTEGER, form_section::SANDBOX),
            new field('memlimitmb', field_kind::INHERITED, field_type::INTEGER, form_section::SANDBOX),

            // Extras.
            new field('globalextra', field_kind::INHERITED, field_type::TEXT, form_section::EXTRAS, default: '', twigable: true),
            new field('prototypeextra', field_kind::INHERITED, field_type::TEXT, form_section::EXTRAS, default: '', twigable: true),

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
