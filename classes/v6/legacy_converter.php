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
 * Conversion of CodeRunner 5 option values into CodeRunner 6 override sets.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * Turns a CodeRunner 5 `question_coderunner_options` row, or the equivalent set
 * of values from a Moodle XML export, into an override set.
 *
 * This is migration and import scaffolding. It is never on the run-time path,
 * and it goes away with the legacy columns.
 */
class legacy_converter {
    /**
     * The inheritable fields of CodeRunner 5, being those a prototype question
     * actually supplied to its questions.
     *
     * Everything else a prototype question stored was ignored at run time:
     * `extra_question_fields_static()` less `noninherited_fields()`. Under
     * CodeRunner 6 many more fields become inheritable, which is why this list
     * is written out rather than derived from the registry.
     *
     * @return string[] the field names.
     */
    public static function fields_inherited_in_v5(): array {
        return [
            'template',
            'iscombinatortemplate',
            'grader',
            'language',
            'acelang',
            'useace',
            'sandbox',
            'cputimelimitsecs',
            'memlimitmb',
            'sandboxparams',
            'resultcolumns',
            'uiplugin',
            'testsplitterre',
            'allowmultiplestdins',
            'prototypeextra',
        ];
    }

    /**
     * The collected fields a CodeRunner 5 prototype question supplied to its
     * questions, which `fields_inherited_in_v5()` does not cover.
     *
     * That list is `extra_question_fields_static()` less
     * `noninherited_fields()`, and `noninherited_fields()` describes which
     * controls the authoring form hides behind its *Customise* checkbox — not
     * what actually flowed from a prototype to a question. Template parameters,
     * UI parameters and support files each flowed by their own dedicated
     * mechanism — `evaluate_merged_parameters()`,
     * `evaluate_merged_ui_parameters()` and `get_files()` — so they are absent
     * from that list while being genuinely inherited at run time.
     *
     * Leaving them out of a migrated prototype silently discarded them. On the
     * development site that was 12 prototypes' template parameters and 25
     * prototypes' UI parameters, affecting 11,883 of 12,346 questions: the
     * `wrapper_src` that tells the scratchpad UI where to find its wrapper, the
     * `isdirected` and `isfsm` flags of the graph UI, and so on.
     *
     * The language and per-try settings come with the parameters rather than on
     * their own, because they describe how to evaluate them and mean nothing
     * without them.
     *
     * @return string[] the field names.
     */
    public static function fields_supplied_by_v5_prototypes(): array {
        return [
            'templateparams',
            'templateparamslang',
            'templateparamsevalpertry',
            'uiparameters',
        ];
    }

    /**
     * The override set for a prototype converted from a CodeRunner 5 prototype
     * question.
     *
     * Only the fields a prototype supplied in CodeRunner 5 are carried over.
     * The fields that become inheritable in CodeRunner 6 — the penalty regime,
     * all-or-nothing, the answer box size, the attachment settings and so on —
     * were ignored on a prototype question, so whatever happened to be stored
     * in them is not a decision anybody made. Carrying those values over would
     * silently turn form defaults nobody chose into inherited settings for every
     * question later created under the prototype. They are left unset instead,
     * so they fall through to the Site defaults, while each existing question
     * keeps its own value because the question migration pins it.
     *
     * Instance fields are dropped: a prototype has no answer, answer preload or
     * test cases, and its global extra never had any effect.
     *
     * A null column is left out rather than stored as an explicit null, so the
     * field falls through to the parent or the registry default exactly as it
     * did when CodeRunner 5 treated null as "inherit".
     *
     * @param array $columns column name => value, from a row or an XML export.
     * @param string $source a label for error messages.
     * @return override_set the prototype's overrides.
     */
    public static function prototype_overrides(array $columns, string $source = ''): override_set {
        $values = [];
        foreach (self::fields_inherited_in_v5() as $name) {
            if (!array_key_exists($name, $columns)) {
                continue;
            }
            $value = $columns[$name];
            if ($value === null || $value === '') {
                // CodeRunner 5 treated both as "not set" for an inheritable
                // field, so neither is an override.
                continue;
            }
            $values[$name] = self::cast($name, $value);
        }

        // The collected fields, which CodeRunner 5 merged from the prototype by
        // a route of their own; see fields_supplied_by_v5_prototypes(). The
        // language and per-try settings are carried only alongside actual
        // parameters, since on their own they describe nothing.
        if (trim((string) ($columns['templateparams'] ?? '')) !== '') {
            foreach (['templateparams', 'templateparamslang', 'templateparamsevalpertry'] as $name) {
                if (array_key_exists($name, $columns) && $columns[$name] !== null && $columns[$name] !== '') {
                    $values[$name] = self::cast($name, $columns[$name]);
                }
            }
        }
        if (trim((string) ($columns['uiparameters'] ?? '')) !== '') {
            $values['uiparameters'] = self::cast('uiparameters', $columns['uiparameters']);
        }

        return new override_set($values, $source);
    }

    /**
     * Cast a stored or exported value to the type the registry declares.
     *
     * Values arrive as strings from the database and from XML. Comparing a
     * resolved `'5'` against a resolved `5` would report a spurious difference
     * during verification, so each is cast once, here.
     *
     * @param string $name the field name.
     * @param mixed $value the stored value.
     * @return mixed the value as the registry's type.
     */
    public static function cast(string $name, mixed $value): mixed {
        if ($value === null) {
            return null;
        }
        return match (field_registry::get($name)->type) {
            field_type::BOOLEAN => (int) (bool) $value,
            field_type::INTEGER => (int) $value,
            default => (string) $value,
        };
    }

    /**
     * The fields that become inheritable in CodeRunner 6 but were a question's
     * own in CodeRunner 5, together with the collected fields, which were also
     * the question's own.
     *
     * A question's value for each of these was effective, so each is pinned on
     * migration whether it is null or not. That keeps every question exactly as
     * it is now, unaffected by its prototype or by the Site defaults, which is
     * the opposite of the rule for prototypes: a *prototype's* value for most of
     * these fields was ignored by CodeRunner 5, so carrying it over would invent
     * a preference nobody expressed.
     *
     * The exception is the four collected fields, which a prototype did supply
     * through a mechanism of their own, and which a prototype therefore carries;
     * see `fields_supplied_by_v5_prototypes()`. A question still pins its own
     * values for them, because each layer's parameters are evaluated in that
     * layer's own language before being merged, so both layers contribute.
     *
     * The consequence is that the new inheritance does nothing for existing
     * questions until an author deliberately flattens them. That is the
     * author's decision to make, not the migration's.
     *
     * @return string[] the field names.
     */
    public static function fields_pinned_on_questions(): array {
        $pinned = [];
        foreach (field_registry::all() as $name => $field) {
            if ($field->kind === field_kind::INSTANCE || !$field->incolumn) {
                // Instance fields keep their own columns, and support files are
                // not a column at all.
                continue;
            }
            if (in_array($name, self::fields_inherited_in_v5(), true)) {
                // Already inherited in CodeRunner 5, so the "null means
                // inherit" rule applies to it instead.
                continue;
            }
            $pinned[] = $name;
        }
        return $pinned;
    }

    /**
     * The override set for a question converted from its CodeRunner 5 options
     * row.
     *
     * Two rules apply, according to whether CodeRunner 5 inherited the field:
     *
     * - A field CodeRunner 5 inherited is an override only when the column
     *   holds something, because a null or empty column meant "inherit" and
     *   must keep meaning that.
     * - A field CodeRunner 5 did not inherit is pinned whether the column is
     *   null or not, because its value was effective and the question must not
     *   start following its prototype or the Site defaults instead.
     *
     * Instance fields are not included: they keep their own columns.
     *
     * @param array $columns column name => value from the options row.
     * @param string $source a label for the layer, used in provenance.
     * @return override_set the question's overrides.
     */
    public static function question_overrides(array $columns, string $source = ''): override_set {
        $values = [];

        foreach (self::fields_inherited_in_v5() as $name) {
            if (!array_key_exists($name, $columns)) {
                continue;
            }
            $value = $columns[$name];
            if ($value === null || $value === '') {
                continue;
            }
            $values[$name] = self::cast($name, $value);
        }

        foreach (self::fields_pinned_on_questions() as $name) {
            $values[$name] = self::cast($name, $columns[$name] ?? null);
        }

        return new override_set($values, $source);
    }

    /**
     * The documentation for a prototype converted from a prototype question.
     *
     * A prototype question's question text is not the text of a question: it is
     * the documentation shown to a question author in the Details panel. It
     * therefore becomes the prototype's documentation.
     *
     * @param array $columns column name => value, including `questiontext`.
     * @return string the documentation.
     */
    public static function documentation(array $columns): string {
        return (string) ($columns['questiontext'] ?? '');
    }

    /**
     * The format a converted prototype's documentation is written in.
     *
     * A prototype question's text carried a format like any other Moodle text,
     * and the documentation that replaces it is edited through the same editor,
     * so the format travels with it. Defaults to HTML, which is what every
     * prototype question on the development site used.
     *
     * @param array $columns column name => value, including 'questiontextformat'.
     * @return int the FORMAT_ constant.
     */
    public static function documentation_format(array $columns): int {
        return (int) ($columns['questiontextformat'] ?? FORMAT_HTML);
    }
}
