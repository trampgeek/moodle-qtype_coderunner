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
 * Carrying a prototype chain in a question's Moodle XML.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype;
use local_coderunner_manager\prototype_repository;

/**
 * The prototype chain that travels with a question in Moodle XML.
 *
 * There is one Moodle XML format and it is Moodle's own (decision 32). A
 * question carries its prototype's uuid in `<prototypeuuid>`, and the
 * definition of each prototype in its chain is written **once per file**, in
 * the first question that needs it, inside `<prototypechain>`. Questions later
 * in the file that share the chain carry the uuid alone, which is what keeps a
 * large export from repeating python3_scratchpad's 150 KB of support files once
 * per question.
 *
 * Both elements are invisible to CodeRunner 5, which reads only the names in
 * its own `extra_question_fields()`, so the same file still imports there.
 *
 * **Why the state is keyed by the format object.** An export writes one file
 * through one `qformat_xml` instance, calling `export_to_xml()` once per
 * question, and the question type instance behind those calls is the single one
 * `question_bank::get_qtype()` hands out for the whole request. So the format
 * object is the only thing that identifies "this file", and an entry's life is
 * the life of the export that made it.
 */
class chain_transport {
    /** @var \SplObjectStorage per-file state, keyed by the qformat object. */
    private static ?\SplObjectStorage $files = null;

    /**
     * The elements a question adds to its Moodle XML for its prototype chain.
     *
     * Empty for a question with no prototype: a legacy prototype question, or
     * one whose prototype could not be resolved, both of which travel on the
     * name in `coderunnertype` as they always did.
     *
     * @param object $question the question being exported, with its options.
     * @param \qformat_xml $format the format writing the file.
     * @return string the XML, or '' if there is nothing to write.
     */
    public static function write_for(object $question, \qformat_xml $format): string {
        $prototypeid = (int) ($question->options->prototypeid ?? 0);
        if ($prototypeid === 0) {
            return '';
        }

        $repo = new prototype_repository();
        $leaf = $repo->get($prototypeid);
        if ($leaf === null) {
            return '';
        }

        $expout = "    <prototypeuuid>{$leaf->uuid}</prototypeuuid>\n";

        $definitions = self::definitions_for($prototypeid, $repo);
        $state = self::state_for($format);
        $towrite = [];
        foreach ($definitions as $definition) {
            if (isset($state->emitted[$definition->uuid])) {
                continue;
            }
            $state->emitted[$definition->uuid] = true;
            $towrite[] = $definition;
        }
        if ($towrite) {
            $expout .= self::write_definitions($towrite, $format);
        }

        return $expout;
    }

    /**
     * The definitions a chain needs to travel, root first.
     *
     * Root first because an import creates parents before children, and the
     * file's order is the only ordering it has.
     *
     * Built-ins and *Site defaults* are excluded (decision 34). A built-in is
     * found on the far side by uuid, which is derived from its canonical name
     * and so is the same on every site; *Site defaults* is found by its flag,
     * since the installer gives each site's copy a random uuid. Carrying either
     * would mean a receiving site acquiring a second copy of a prototype it
     * already has.
     *
     * @param int $prototypeid the leaf of the chain.
     * @param prototype_repository|null $repo the repository to read through.
     * @return array of definition objects, root first.
     */
    public static function definitions_for(int $prototypeid, ?prototype_repository $repo = null): array {
        $repo ??= new prototype_repository();
        $definitions = [];

        foreach ($repo->chain_prototypes($prototypeid) as $prototype) {
            if ($prototype->builtin || $prototype->issitedefaults) {
                continue;
            }
            $version = $prototype->currentversion;
            if ($version === null) {
                // A prototype with no current version resolves to nothing and
                // cannot be described. The questions below it are already
                // broken here and travel equally broken, which is honest.
                continue;
            }
            $parent = $version->parentid === null ? null : $repo->get($version->parentid);
            $definitions[] = (object) [
                'uuid' => $prototype->uuid,
                'name' => $prototype->name,
                // The exporting site's version number, which is that site's
                // own counting and no authority here. It travels so that a
                // warning about a local copy holding different settings can
                // name both, which decision 34 asks for: version order says
                // nothing about whether a question will run, so the number is
                // there to be read by a person, not compared by the code.
                'version' => $version->version,
                'parentuuid' => $parent?->uuid ?? '',
                'parentissitedefaults' => $parent?->issitedefaults ? 1 : 0,
                'overrides' => json_encode($version->overrides->to_array()),
                'summary' => $version->summary,
                'documentation' => $version->documentation,
                'documentationformat' => $version->documentationformat,
                'files' => $repo->version_files($prototype, $version->id),
            ];
        }

        return $definitions;
    }

    /**
     * Write the `<prototypechain>` element.
     *
     * @param array $definitions the definitions to write, root first.
     * @param \qformat_xml $format the format writing the file.
     * @return string the XML.
     */
    private static function write_definitions(array $definitions, \qformat_xml $format): string {
        $expout = "    <prototypechain>\n";
        foreach ($definitions as $definition) {
            $expout .= "      <prototype>\n";
            $expout .= "        <uuid>{$definition->uuid}</uuid>\n";
            $expout .= "        <version>{$definition->version}</version>\n";
            $expout .= "        <name>\n" . $format->writetext($definition->name, 5) . "        </name>\n";
            $expout .= "        <parentuuid>{$definition->parentuuid}</parentuuid>\n";
            $expout .= "        <parentissitedefaults>{$definition->parentissitedefaults}"
                . "</parentissitedefaults>\n";
            $expout .= "        <overrides>\n" . $format->writetext($definition->overrides, 5)
                . "        </overrides>\n";
            $expout .= "        <summary>\n" . $format->writetext($definition->summary, 5)
                . "        </summary>\n";
            $expout .= '        <documentation format="' . $definition->documentationformat . "\">\n"
                . $format->writetext($definition->documentation, 5) . "        </documentation>\n";
            if ($definition->files) {
                $expout .= "        <files>\n";
                $expout .= $format->write_files($definition->files);
                $expout .= "        </files>\n";
            }
            $expout .= "      </prototype>\n";
        }
        $expout .= "    </prototypechain>\n";

        return $expout;
    }

    /**
     * Read what a question's XML says about its prototype chain.
     *
     * Any definitions the question carries are remembered for the rest of the
     * file, because the file carries each chain once, in the first question
     * that needs it; a later question sharing the chain has the uuid alone.
     * Moodle parses a file in document order in one pass, so by the time such a
     * question is read its definitions have been seen.
     *
     * What comes back is the question's own chain, root first, which is then
     * carried on the question object itself: `save_question_options()` is given
     * no format object, and the chain has to reach it.
     *
     * @param array $data the question's parsed XML.
     * @param \qformat_xml $format the format reading the file.
     * @return array{uuid: string, chain: array}|null null if the question
     *      names no prototype, which is how a CodeRunner 5 file arrives.
     */
    public static function read_for(array $data, \qformat_xml $format): ?array {
        $state = self::state_for($format);

        foreach ($format->getpath($data, ['#', 'prototypechain', 0, '#', 'prototype'], []) as $prototype) {
            $uuid = $format->getpath($prototype, ['#', 'uuid', 0, '#'], '');
            if ($uuid === '' || isset($state->definitions[$uuid])) {
                continue;
            }
            $state->definitions[$uuid] = (object) [
                'uuid' => $uuid,
                'version' => (int) $format->getpath($prototype, ['#', 'version', 0, '#'], 0),
                'name' => $format->getpath($prototype, ['#', 'name', 0, '#', 'text', 0, '#'], '', true),
                'parentuuid' => $format->getpath($prototype, ['#', 'parentuuid', 0, '#'], ''),
                'parentissitedefaults' => (int) $format->getpath(
                    $prototype,
                    ['#', 'parentissitedefaults', 0, '#'],
                    0
                ),
                'overrides' => $format->getpath($prototype, ['#', 'overrides', 0, '#', 'text', 0, '#'], '{}', true),
                'summary' => $format->getpath($prototype, ['#', 'summary', 0, '#', 'text', 0, '#'], '', true),
                'documentation' => $format->getpath(
                    $prototype,
                    ['#', 'documentation', 0, '#', 'text', 0, '#'],
                    '',
                    true
                ),
                'documentationformat' => (int) $format->getpath(
                    $prototype,
                    ['#', 'documentation', 0, '@', 'format'],
                    FORMAT_HTML
                ),
                'files' => $format->getpath($prototype, ['#', 'files', 0, '#', 'file'], []),
            ];
        }

        $uuid = $format->getpath($data, ['#', 'prototypeuuid', 0, '#'], '');
        if ($uuid === '') {
            return null;
        }

        return ['uuid' => $uuid, 'chain' => self::chain_from($uuid, $state->definitions)];
    }

    /**
     * The definitions for one chain, root first, from those the file carried.
     *
     * Stops at the first uuid the file does not define, which is a built-in or
     * *Site defaults* ancestor: neither travels, and both are found on the
     * receiving site instead.
     *
     * @param string $uuid the leaf's uuid.
     * @param array $definitions every definition the file has carried so far.
     * @return array the chain's definitions, root first.
     */
    private static function chain_from(string $uuid, array $definitions): array {
        $chain = [];
        $seen = [];
        while ($uuid !== '' && isset($definitions[$uuid]) && !isset($seen[$uuid])) {
            $seen[$uuid] = true;
            $chain[] = $definitions[$uuid];
            $uuid = $definitions[$uuid]->parentuuid;
        }

        return array_reverse($chain);
    }

    /**
     * What the receiving site has to say about a question's chain.
     *
     * Everything decision 34 asks an import to check, all of it while the file
     * is parsed and before anything is written: Moodle's stop-on-error gate
     * sits between the parse and the first save, so a refusal costs nothing.
     *
     * **Refusals.** No route to the question's own prototype -- no local uuid
     * match, no definition in the file, no prototype of that name either -- so
     * the question would be saved broken. A definition whose *name* is already
     * taken here by a prototype of a different uuid, which is two unrelated
     * question types of one name: renaming the incoming one would quietly
     * duplicate the type, so both are named and the author decides. And a
     * definition whose parent is neither in the file nor here, which is the
     * missing built-in ancestor: rooting it instead would change what every
     * question beneath it resolves to.
     *
     * **A warning**, not a refusal, where a local prototype of the same uuid
     * holds different settings from the file's. The local one is used, and both
     * versions are named, in both directions: version order says nothing about
     * whether a question will run, because that depends on the sandbox, so the
     * importer is told and left to decide. The bulk tester is the only thing
     * that can answer the question it raises.
     *
     * Everything is empty for a file carrying no uuid at all -- a CodeRunner 5
     * file, or a course archive full of legacy prototype questions. Those
     * resolve by name after the prototype sweep, exactly as they always have,
     * and refusing them here would break every such import.
     *
     * Each message is raised once per file, however many questions share the
     * chain that provoked it.
     *
     * @param object $qo the question as the import has built it so far.
     * @param \qformat_xml $format the format reading the file.
     * @return array{refusals: string[], warnings: string[]} what to tell the user.
     */
    public static function import_report(object $qo, \qformat_xml $format): array {
        $report = ['refusals' => [], 'warnings' => []];
        $uuid = (string) ($qo->prototypeuuid ?? '');
        if ($uuid === '') {
            return $report;
        }

        $courseid = self::target_courseid($format);
        $repo = new prototype_repository();
        $chain = $qo->prototypechain ?? [];
        $carried = [];
        foreach ($chain as $definition) {
            $carried[$definition->uuid] = true;
        }

        if (!isset($carried[$uuid])
                && $repo->find_one_by_uuid($uuid, $courseid) === null
                && $repo->find_by_name_for_course((string) $qo->coderunnertype, $courseid) === null) {
            $report['refusals'][] = get_string(
                'importmissingprototype',
                'qtype_coderunner',
                $qo->coderunnertype
            );
        }

        foreach ($chain as $definition) {
            $here = $repo->find_one_by_uuid($definition->uuid, $courseid);
            if ($here !== null) {
                $differs = self::differing_settings($definition, $here);
                if ($differs !== null) {
                    $report['warnings'][] = $differs;
                }
                continue;
            }

            $samename = $repo->find_by_name_for_course($definition->name, $courseid);
            if ($samename !== null) {
                $report['refusals'][] = get_string('importprototypenameclash', 'qtype_coderunner', (object) [
                    'name' => $definition->name,
                    'incoming' => $definition->uuid,
                    'local' => $samename->uuid,
                ]);
                continue;
            }

            if (!self::parent_is_available($definition, $carried, $courseid, $repo)) {
                $report['refusals'][] = get_string('importprototypenoancestor', 'qtype_coderunner', (object) [
                    'name' => $definition->name,
                    'parent' => $definition->parentuuid,
                ]);
            }
        }

        return self::only_new($report, self::state_for($format));
    }

    /**
     * Whether a definition's parent can be found, or it has none.
     *
     * The three routes a parent can arrive by, and no fourth: another
     * definition in the same file, *Site defaults* by its flag -- the only
     * thing about it that crosses a site boundary, since each site's copy has
     * its own random uuid -- and any local prototype by uuid, which is how a
     * built-in is found.
     *
     * @param object $definition the definition whose parent is wanted.
     * @param array $carried uuid to true, for every definition in the file.
     * @param int $courseid the course being imported into.
     * @param prototype_repository $repo the repository to read through.
     * @return bool true if the parent is available, or there is no parent.
     */
    private static function parent_is_available(
        object $definition,
        array $carried,
        int $courseid,
        prototype_repository $repo
    ): bool {
        if ($definition->parentuuid === '') {
            return true;
        }
        if (isset($carried[$definition->parentuuid])) {
            return true;
        }
        if ($definition->parentissitedefaults) {
            return $repo->site_defaults() !== null;
        }

        return $repo->find_one_by_uuid($definition->parentuuid, $courseid) !== null;
    }

    /**
     * The warning for a local prototype whose settings differ from the file's.
     *
     * Compared on the overrides alone, which are the settings a question
     * actually resolves from; a difference of summary or documentation changes
     * nothing a student or an author would run into. Decoded first, because two
     * equal documents can be written in any key order.
     *
     * @param object $definition the definition the file carried.
     * @param prototype $here the local prototype of the same uuid.
     * @return string|null the warning, or null if the two agree.
     */
    private static function differing_settings(object $definition, prototype $here): ?string {
        $version = $here->currentversion;
        if ($version === null) {
            return null;
        }

        $incoming = json_decode($definition->overrides, true) ?: [];
        $local = $version->overrides->to_array();
        ksort($incoming);
        ksort($local);
        if ($incoming == $local) {
            return null;
        }

        return get_string('importprototypediffers', 'qtype_coderunner', (object) [
            'name' => $here->name,
            'fileversion' => $definition->version,
            'localversion' => $version->version,
        ]);
    }

    /**
     * The messages this file has not already given, recording them as given.
     *
     * A chain shared by two hundred questions would otherwise raise the same
     * complaint two hundred times.
     *
     * @param array $report the refusals and warnings just worked out.
     * @param object $state the per-file state.
     * @return array{refusals: string[], warnings: string[]} the new ones only.
     */
    private static function only_new(array $report, object $state): array {
        foreach ($report as $kind => $messages) {
            $report[$kind] = [];
            foreach ($messages as $message) {
                if (isset($state->reported[$message])) {
                    continue;
                }
                $state->reported[$message] = true;
                $report[$kind][] = $message;
            }
        }

        return $report;
    }

    /**
     * The course an import is writing into.
     *
     * The same derivation the save path uses, from the context rather than
     * from `$format->course`, so that a question bank in a module or at site
     * level is scoped as the manager scopes it.
     *
     * @param \qformat_xml $format the format reading the file.
     * @return int the course id, or SITEID for a bank outside any course.
     */
    private static function target_courseid(\qformat_xml $format): int {
        $contextid = (int) ($format->category->contextid ?? 0);
        if ($contextid === 0) {
            return SITEID;
        }
        $coursecontext = \context::instance_by_id($contextid)->get_course_context(false);

        return $coursecontext ? (int) $coursecontext->instanceid : SITEID;
    }

    /**
     * The local id of the prototype a question imported by uuid should use.
     *
     * Adopt a prototype already here with that uuid; otherwise create what the
     * file defined, parents before children. Null means the file cannot be
     * honoured: the caller falls back to the name in `coderunnertype`, which is
     * how a CodeRunner 5 file and a pre-uuid CodeRunner 6 file both resolve.
     *
     * @param string $uuid the prototype the question names.
     * @param array $chain the definitions it carried, root first.
     * @param int $courseid the course being imported into.
     * @return int|null the prototype's local id, or null if there is none.
     */
    public static function adopt_or_create(string $uuid, array $chain, int $courseid): ?int {
        $repo = new prototype_repository();

        $existing = $repo->find_one_by_uuid($uuid, $courseid);
        if ($existing !== null) {
            return $existing->id;
        }

        $created = [];
        foreach ($chain as $definition) {
            $here = $repo->find_one_by_uuid($definition->uuid, $courseid);
            $created[$definition->uuid] = $here !== null
                ? $here->id
                : self::create_from_definition($definition, $created, $courseid, $repo);
        }

        return $created[$uuid] ?? null;
    }

    /**
     * Create one prototype from the definition a file carried.
     *
     * The same four routes to a parent as a restore uses, narrowest first: one
     * created earlier in this chain, *Site defaults* by its flag, a built-in or
     * other local prototype by uuid, and failing all three a root -- which
     * leaves the chain shorter than it was rather than failing, the same
     * degradation a missing prototype has always produced.
     *
     * @param object $definition the definition to create from.
     * @param array $created uuid to local id, for prototypes made just now.
     * @param int $courseid the course being imported into.
     * @param prototype_repository $repo the repository to write through.
     * @return int the new prototype's id.
     */
    private static function create_from_definition(
        object $definition,
        array $created,
        int $courseid,
        prototype_repository $repo
    ): int {
        $parentid = $created[$definition->parentuuid] ?? null;
        if ($parentid === null && $definition->parentissitedefaults) {
            $parentid = $repo->site_defaults()?->id;
        }
        if ($parentid === null && $definition->parentuuid !== '') {
            $parentid = $repo->find_one_by_uuid($definition->parentuuid, $courseid)?->id;
        }

        $prototype = $repo->restore_prototype(
            (object) [
                'name' => $definition->name,
                'uuid' => $definition->uuid,
            ],
            [[
                'version' => 1,
                'parentid' => $parentid,
                'overrides' => $definition->overrides,
                'summary' => $definition->summary,
                'documentation' => $definition->documentation,
                'documentationformat' => $definition->documentationformat,
                'commitmessage' => get_string('importedprototypecommit', 'qtype_coderunner'),
            ]],
            $courseid,
            1
        );

        self::write_files($prototype, $definition->files, $repo);

        return $prototype->id;
    }

    /**
     * Store a created prototype's support files.
     *
     * @param prototype $prototype the prototype just created.
     * @param array $files the `<file>` elements the definition carried.
     * @param prototype_repository $repo the repository owning the file area.
     */
    private static function write_files(prototype $prototype, array $files, prototype_repository $repo): void {
        if (!$files || $prototype->currentversion === null) {
            return;
        }

        $fs = get_file_storage();
        $contextid = $repo->file_contextid($prototype);
        foreach ($files as $file) {
            $filename = $file['@']['name'] ?? '';
            if ($filename === '') {
                continue;
            }
            $filepath = $file['@']['path'] ?? '/';
            if ($fs->file_exists(
                $contextid,
                prototype_repository::COMPONENT,
                prototype_repository::FILEAREA,
                $prototype->currentversion->id,
                $filepath,
                $filename
            )) {
                continue;
            }
            $fs->create_file_from_string([
                'contextid' => $contextid,
                'component' => prototype_repository::COMPONENT,
                'filearea' => prototype_repository::FILEAREA,
                'itemid' => $prototype->currentversion->id,
                'filepath' => $filepath,
                'filename' => $filename,
            ], base64_decode($file['#'] ?? ''));
        }
    }

    /**
     * The per-file state for one export or import.
     *
     * @param \qformat_xml $format the format running the file.
     * @return object the state, with an `emitted` and a `definitions` map.
     */
    private static function state_for(\qformat_xml $format): object {
        self::$files ??= new \SplObjectStorage();
        if (!isset(self::$files[$format])) {
            self::$files[$format] = (object) ['emitted' => [], 'definitions' => [], 'reported' => []];
        }

        return self::$files[$format];
    }

    /**
     * Forget every file's state.
     *
     * For tests, which reuse one process for many exports. Production has no
     * need of it: an entry dies with the format object that made it.
     */
    public static function reset(): void {
        self::$files = null;
    }
}
