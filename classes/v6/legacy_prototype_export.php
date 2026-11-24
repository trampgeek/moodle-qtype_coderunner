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
 * Writing a prototype as a CodeRunner 5 prototype question.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype;
use local_coderunner_manager\prototype_repository;

/**
 * A prototype export a CodeRunner 5 site can read.
 *
 * A 6.0 *question* file already imports into 5.x, because
 * `question_type::import_from_xml()` reads only the names in the importing
 * site's own `extra_question_fields()`. What a 5.x site cannot do is build the
 * question type: `get_prototype()` looks a single name up among the prototype
 * questions in the bank and does not recurse, so 5.x inheritance is one level
 * deep, and `jobrunner.php` refuses a question whose prototype object is
 * missing before it looks at a single field.
 *
 * So the chain is resolved into **one prototype question** (`prototypetype` 2,
 * `coderunnertype` the leaf's name, every inherited field set), which the
 * recipient imports beside the questions file. The questions find it by name.
 * This is the only place flattening happens: it is a property of a prototype
 * export and never of a question export (decision 33).
 *
 * **One-way.** CodeRunner 5 XML has nowhere to carry a uuid or a chain, so
 * such a file re-imported here arrives as a legacy prototype question and is
 * converted as one, and the resolved prototype has no chain of its own, so the
 * recipient's copy diverges as soon as a parent is edited here.
 *
 * Deletable in one piece when phase 4 drops the legacy columns.
 */
class legacy_prototype_export {
    /** @var int What CodeRunner 5 stored for a user-defined prototype. */
    private const USER_PROTOTYPE = 2;

    /**
     * The whole Moodle XML file for one prototype.
     *
     * @param prototype $prototype the prototype to write.
     * @param prototype_repository|null $repo the repository to read through.
     * @return string the file's contents.
     */
    public static function file(prototype $prototype, ?prototype_repository $repo = null): string {
        $repo ??= new prototype_repository();
        $format = new \qformat_xml();

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n";
        $xml .= self::question($prototype, $repo, $format);
        $xml .= "</quiz>\n";

        return $xml;
    }

    /**
     * The one `<question>` element, support files included.
     *
     * Written through `qformat_xml::writequestion()` rather than by hand, so
     * that the element set is whatever the importer at the far end expects and
     * cannot drift from it. The question type's own `export_to_xml()` sees
     * `prototypetype` 2 and leaves the columns exactly as they are built here,
     * which is what the legacy branch of that method is for.
     *
     * The support files are the one thing the reused writer cannot produce:
     * it looks in `qtype_coderunner/datafile` under the question's own id, and
     * a prototype's files live in the manager's area under its version's. They
     * are spliced into the `<testcases>` element the writer always emits,
     * which is where CodeRunner 5 reads a question's files from.
     *
     * @param prototype $prototype the prototype to write.
     * @param prototype_repository $repo the repository to read through.
     * @param \qformat_xml $format the format doing the writing.
     * @return string the question element.
     */
    private static function question(
        prototype $prototype,
        prototype_repository $repo,
        \qformat_xml $format
    ): string {
        $questiondata = self::questiondata($prototype, $repo);
        $xml = $format->writequestion($questiondata);

        $files = $repo->support_files_for($prototype->id);
        if ($files) {
            $closing = "    </testcases>\n";
            $at = strpos($xml, $closing);
            if ($at !== false) {
                $xml = substr($xml, 0, $at) . $format->write_files($files) . substr($xml, $at);
            }
        }

        return $xml;
    }

    /**
     * The question data a resolved chain makes, as a V5 prototype question.
     *
     * @param prototype $prototype the prototype to write.
     * @param prototype_repository $repo the repository to read through.
     * @return \stdClass question data, as the export path expects it.
     */
    private static function questiondata(prototype $prototype, prototype_repository $repo): \stdClass {
        $resolved = $repo->chain_for($prototype->id)->resolve();
        $version = $prototype->currentversion;

        $options = (object) self::columns($resolved);
        $options->coderunnertype = $prototype->name;
        $options->prototypetype = self::USER_PROTOTYPE;
        $options->prototypeid = null;
        $options->overrides = null;
        $options->testcases = [];
        // Core's exporter walks the answers of whatever it is given, and a
        // question loaded from the database always has the list, empty or not.
        $options->answers = [];

        return (object) [
            'id' => 0,
            'contextid' => $repo->file_contextid($prototype),
            'qtype' => 'coderunner',
            'name' => get_string('legacyprototypename', 'qtype_coderunner', $prototype->name),
            // A prototype question's text is not the text of a question: it is
            // the documentation an author reads in the Details panel, which is
            // exactly what legacy_converter::documentation() read back out of
            // it when such a question became a prototype.
            'questiontext' => $version?->documentation ?? '',
            'questiontextformat' => $version?->documentationformat ?? FORMAT_HTML,
            'generalfeedback' => '',
            'generalfeedbackformat' => FORMAT_HTML,
            'defaultmark' => 1,
            'penalty' => 0,
            'idnumber' => '',
            'options' => $options,
        ];
    }

    /**
     * Every CodeRunner 5 column a prototype question carries, resolved.
     *
     * The registry's fields take their resolved value, since the point of a
     * flattened prototype is that it inherits nothing. Six of the exported
     * columns are not registry fields and are handled apart: `coderunnertype`
     * and `prototypetype` say what this question *is*, the two CodeRunner 6
     * columns mean nothing on a 5.x site, `templateparamsevald` is a cache,
     * and `answerboxcolumns` is defunct -- written at the value the built-in
     * prototypes carry, rather than omitted, because a missing element imports
     * as an empty string into an integer column.
     *
     * @param question_settings $resolved what the chain resolves to.
     * @return array column name => value.
     */
    private static function columns(question_settings $resolved): array {
        $columns = ['answerboxcolumns' => 100, 'templateparamsevald' => null];

        $fields = \qtype_coderunner::extra_question_fields_static();
        array_shift($fields);
        foreach ($fields as $name) {
            if (array_key_exists($name, $columns)
                    || in_array($name, ['coderunnertype', 'prototypetype', 'prototypeid', 'overrides'], true)) {
                continue;
            }
            $field = field_registry::get($name);
            if ($field->resolves_to_one_value()) {
                $columns[$name] = $resolved->get($name);
            }
        }

        return $columns + self::collected_columns($resolved);
    }

    /**
     * The four columns CodeRunner 5 took whole from a question's prototype.
     *
     * Template and UI parameters are collected from every layer of a chain
     * rather than resolved to one value, so flattening has to combine them:
     * each layer's document is merged over the ones above it, a child's keys
     * winning, which is the order `question.php` merges them in at run time.
     *
     * The two qualifiers -- the language the parameters are written in, and
     * whether they are evaluated per try -- belong to a layer rather than to
     * the merged document, and a single V5 prototype has only one of each. The
     * leaf-most layer that contributes parameters supplies them. Where two
     * contributing layers disagree about the language, nothing here can be
     * right, so the export refuses rather than quietly picking one.
     *
     * @param question_settings $resolved what the chain resolves to.
     * @return array column name => value.
     * @throws \qtype_coderunner_exception if two layers disagree on the language.
     */
    private static function collected_columns(question_settings $resolved): array {
        $merged = [];
        $lang = null;
        $pertry = 0;

        foreach (['templateparams', 'uiparameters'] as $name) {
            $document = [];
            foreach ($resolved->layers_of($name) as $layer) {
                $document = array_merge($document, json_decode((string) $layer['value'], true) ?: []);
            }
            $merged[$name] = $document ? json_encode($document) : '';
        }

        foreach ($resolved->layers_of('templateparams') as $layer) {
            if (trim((string) $layer['value']) === '') {
                continue;
            }
            $set = $layer['layer'];
            $thislang = $set->has('templateparamslang') ? (string) $set->get('templateparamslang') : 'twig';
            if ($lang !== null && $lang !== $thislang) {
                throw new \qtype_coderunner_exception(get_string(
                    'legacyprototypemixedlangs',
                    'qtype_coderunner',
                    (object) ['first' => $lang, 'second' => $thislang]
                ));
            }
            $lang = $thislang;
            $pertry = $set->has('templateparamsevalpertry')
                ? (int) $set->get('templateparamsevalpertry')
                : $pertry;
        }

        $merged['templateparamslang'] = $lang ?? 'twig';
        $merged['templateparamsevalpertry'] = $pertry;

        return $merged;
    }
}
