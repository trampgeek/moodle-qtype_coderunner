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
 * Reading a prototype export file into this site's prototype library.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_repository;

/**
 * What a prototype export file would do to this library, and then doing it.
 *
 * **The other door, and the one with an author to talk to.** A prototype file
 * fed to Moodle's question import is adopted silently and never changes a
 * prototype already here ({@see chain_transport::adopt_or_create()}), because
 * there is nobody to ask and no way to report. This door is the manager's own
 * import page: it works out what the file would change, shows that, and acts
 * only when the author says so. So this is where design section 9's rule lives
 * -- *importing a newer release of a library updates each prototype with a
 * matching identifier by adding the incoming content as a new version, after
 * showing the author what will change*.
 *
 * **Planning and applying are separate calls** over the same file, not one
 * call that remembers. A preview and its confirmation are two requests, and
 * the file is the only thing that travels between them -- it stays in the
 * author's draft area and is read again. Nothing is cached: a plan built from
 * the file and a plan built from the file a minute later disagree only if the
 * library changed underneath, which is exactly the case where the first plan
 * should not be trusted.
 *
 * It lives in the question type rather than in the manager for the same reason
 * {@see prototype_export} does, and symmetrically with it: the file is Moodle
 * XML for *questions*, which is the question type's format, and reading a
 * chain out of it is {@see chain_transport}'s. The manager's page calls in, as
 * its export page already does.
 */
class prototype_import {
    /** @var string The file holds no prototypes at all. */
    const NOTHING = 'nothing';

    /** @var string The file holds questions rather than prototypes. */
    const QUESTIONS = 'questions';

    /** @var string The file holds only prototypes every site already has. */
    const BUILTINS = 'builtins';

    /** @var string This prototype is not here and would be created. */
    const CREATE = 'create';

    /** @var string This prototype is here and would gain a version. */
    const VERSION = 'version';

    /** @var string This prototype is here and the file says nothing new. */
    const UNCHANGED = 'unchanged';

    /** @var string This prototype cannot be imported, and nor can the file. */
    const REFUSE = 'refuse';

    /**
     * What this file would do, prototype by prototype, parents first.
     *
     * Parents first because that is the order the file carries and the order
     * an apply has to follow: a child's parent must exist before it does. The
     * emitter writes each chain root-first and the selection parents-first, so
     * document order already is dependency order and this preserves it rather
     * than re-deriving it.
     *
     * @param string $xml the file's contents.
     * @param int $courseid the course whose library is being imported into.
     * @return array{entries: array, problem: string|null} the plan: one entry
     *      per prototype in the file, and a problem naming why there is no
     *      plan at all.
     */
    public static function plan(string $xml, int $courseid): array {
        $read = self::definitions_in($xml);
        if ($read === null) {
            return ['entries' => [], 'shipped' => 0, 'problem' => self::QUESTIONS];
        }

        // **A file of built-ins carries no definitions and that is correct.**
        // Decision 34 keeps built-ins and *Site defaults* out of a chain,
        // because they are found on the far side by uuid and carrying them
        // would give a site a second copy of what it already has. So such a
        // file has prototypes in it that a CodeRunner 5 site needs and a
        // CodeRunner 6 site does not, and saying "no prototypes" -- which is
        // what the author was told when he exported three site prototypes --
        // describes the file wrongly. The distinction is whether any question
        // named a prototype at all.
        $shipped = $read['prototypes'] - count($read['definitions']);
        if (!$read['definitions']) {
            return [
                'entries' => [],
                'shipped' => $shipped,
                'problem' => $read['prototypes'] > 0 ? self::BUILTINS : self::NOTHING,
            ];
        }

        $repo = new prototype_repository();
        $entries = [];
        foreach ($read['definitions'] as $definition) {
            $entries[] = self::classify($definition, $courseid, $repo);
        }

        return ['entries' => $entries, 'shipped' => $shipped, 'problem' => null];
    }

    /**
     * Carry out a plan, and say what it did.
     *
     * Re-planned from the file rather than taking the preview's word for it,
     * so that a library changed between the two requests is seen. A plan with
     * anything refused in it does nothing at all, which is Moodle's own
     * stop-on-error rule and for the same reason: half an imported library is
     * worse than none.
     *
     * @param string $xml the file's contents.
     * @param int $courseid the course whose library is being imported into.
     * @return array{created: string[], versioned: string[], unchanged: string[],
     *      refused: string[], problem: string|null} what happened.
     */
    public static function apply(string $xml, int $courseid): array {
        $plan = self::plan($xml, $courseid);
        $done = [
            'created' => [],
            'versioned' => [],
            'unchanged' => [],
            'refused' => [],
            'shipped' => $plan['shipped'],
            'problem' => $plan['problem'],
        ];
        if ($plan['problem'] !== null) {
            return $done;
        }

        foreach ($plan['entries'] as $entry) {
            if ($entry['action'] === self::REFUSE) {
                $done['refused'][] = $entry['name'];
            }
        }
        if ($done['refused']) {
            return $done;
        }

        $repo = new prototype_repository();

        // Uuid to local id for everything this run has settled, which is what
        // lets a child find the parent created moments earlier.
        $created = [];
        foreach ($plan['entries'] as $entry) {
            $definition = $entry['definition'];
            switch ($entry['action']) {
                case self::CREATE:
                    $created[$definition->uuid] = chain_transport::create_from_definition(
                        $definition,
                        $created,
                        $courseid,
                        $repo,
                        get_string('importedprototypeversion', 'qtype_coderunner')
                    );
                    $done['created'][] = $entry['name'];
                    break;
                case self::VERSION:
                    $created[$definition->uuid] = $entry['localid'];
                    self::add_version($entry, $definition, $repo);
                    $done['versioned'][] = $entry['name'];
                    break;
                default:
                    $created[$definition->uuid] = $entry['localid'];
                    $done['unchanged'][] = $entry['name'];
            }
        }

        return $done;
    }

    /**
     * Add the incoming content to a prototype already here, as a new version.
     *
     * **The parent is left alone.** The file says what the prototype's parent
     * was on the exporting site, and this site's answer to that may be a
     * different prototype or none; re-pointing a local prototype's parent from
     * a file is a change to the shape of this library rather than to the
     * prototype's content, and design section 9 asks only for the content.
     * Passing the version's own parent keeps it.
     *
     * **The files are replaced, not merged.** `save_new_version()` carries the
     * previous version's files forward when given null, which would leave a
     * file the new release deleted in place for ever. An empty array means
     * this version has none, and the incoming ones are written into it after.
     *
     * @param array $entry the plan entry.
     * @param object $definition the definition the file carried.
     * @param prototype_repository $repo the repository to write through.
     */
    private static function add_version(array $entry, object $definition, prototype_repository $repo): void {
        $prototype = $repo->get_or_fail($entry['localid']);
        $editing = $prototype->currentversion;

        $saved = $repo->save_new_version(
            $prototype,
            new override_set(json_decode($definition->overrides, true) ?: [], $definition->name),
            $editing?->parentid,
            $definition->summary,
            $definition->documentation,
            get_string('importedprototypeversion', 'qtype_coderunner'),
            [],
            $definition->documentationformat
        );

        chain_transport::write_files($saved, $definition->files, $repo);
    }

    /**
     * What this file would do to one prototype.
     *
     * Matched by **uuid**, which is the only identifier that means the same
     * thing on two sites. A name match with a different uuid is refused rather
     * than guessed at, which is decision 34's rule applied to a library: the
     * two are unrelated question types that happen to share a name, and either
     * renaming one or versioning over it would quietly change what a question
     * elsewhere resolves to.
     *
     * @param object $definition the definition the file carried.
     * @param int $courseid the course being imported into.
     * @param prototype_repository $repo the repository to read through.
     * @return array the plan entry.
     */
    private static function classify(object $definition, int $courseid, prototype_repository $repo): array {
        $entry = [
            'uuid' => $definition->uuid,
            'name' => $definition->name,
            'fileversion' => $definition->version,
            'localid' => null,
            'localversion' => null,
            'definition' => $definition,
            'action' => self::CREATE,
            'reason' => null,
        ];

        // In this scope only. `find_one_by_uuid()` would fall back to another
        // course's copy, which is right for a restore and wrong here: it would
        // add a version to that course's prototype from a page about this one.
        $here = $repo->find_in_scope_by_uuid($definition->uuid, $courseid);
        if ($here !== null) {
            $entry['localid'] = $here->id;
            $entry['localversion'] = $here->currentversion?->version;
            $entry['name'] = $here->name;
            $entry['action'] = self::unchanged_from($definition, $here) ? self::UNCHANGED : self::VERSION;

            return $entry;
        }

        $clash = $repo->find_by_name_for_course($definition->name, $courseid);
        if ($clash !== null) {
            $entry['action'] = self::REFUSE;
            $entry['reason'] = get_string('importprototypenameclash', 'qtype_coderunner', (object) [
                'name' => $definition->name,
                'incoming' => $definition->uuid,
                'local' => $clash->uuid,
            ]);
        }

        return $entry;
    }

    /**
     * Whether the file says anything this prototype does not already hold.
     *
     * The content a version carries, all of it: the overrides, the summary and
     * the documentation. Not the files, which would mean reading every one of
     * them to compare -- a library's files run to megabytes, and a release that
     * changes only a support file still changes its overrides' version number
     * in practice. A false *unchanged* costs a version nobody needed; the
     * author sees it in the preview and in the history either way.
     *
     * **The text is compared in the form a file can carry**, which is what
     * {@see as_carried()} settles and is the whole of what went wrong twice.
     *
     * @param object $definition the definition the file carried.
     * @param \local_coderunner_manager\prototype $here the local prototype.
     * @return bool true if a new version would record nothing new.
     */
    private static function unchanged_from(object $definition, $here): bool {
        $version = $here->currentversion;
        if ($version === null) {
            return false;
        }

        $incoming = json_decode($definition->overrides, true) ?: [];
        $local = $version->overrides->to_array();
        ksort($incoming);
        ksort($local);

        return $incoming == $local
            && self::as_carried($definition->summary) === self::as_carried($version->summary)
            && self::as_carried($definition->documentation) === self::as_carried($version->documentation);
    }

    /**
     * A text field as a file is able to carry it.
     *
     * **Compare what the transport preserves, not what the database holds.**
     * Twice now a prototype exported from a site has read back as a changed
     * version of itself, both times because the comparison asked for a byte
     * match on a value the file is incapable of carrying:
     *
     * - **Line endings.** XML 1.0 requires a parser to normalise `\r\n` and a
     *   lone `\r` to `\n` (section 2.11), so a `\r` cannot survive the round
     *   trip at all. Documentation typed into a browser arrives as CRLF, so
     *   this is the ordinary case rather than an edge one: on the development
     *   site three of six prototypes in one export differed by nothing else.
     * - **Leading and trailing space**, because the file is read back through
     *   `qformat_xml::getpath(..., true)`, which trims.
     *
     * Only the free text needs it. The overrides travel as a JSON document
     * inside the XML text, so `json_encode()` has already escaped every
     * control character into a `\r` or `\n` escape sequence that the XML
     * parser never sees as a line ending -- which is why a template with CRLF
     * in it compares equal and these two did not.
     *
     * @param string|null $text the stored or carried value.
     * @return string the value as it survives a round trip.
     */
    private static function as_carried(?string $text): string {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
    }

    /**
     * Every prototype definition the file carries, parents first, once each.
     *
     * Null rather than an empty list where the file holds **questions**: those
     * carry their chain's definitions too, so a question file would otherwise
     * read here as a perfectly good library import, silently installing the
     * prototypes and dropping every question on the floor. `prototypetype` is
     * what tells the two apart, and it is the exporting site's answer to *what
     * is this file for*.
     *
     * **How many prototypes the file names is counted as well as how many it
     * defines**, and the two differ by design: a built-in or *Site defaults*
     * is named by uuid and never defined, since decision 34 leaves it to be
     * found on the receiving site. A file with names but no definitions is
     * therefore a file of built-ins rather than an empty one.
     *
     * @param string $xml the file's contents.
     * @return array{definitions: array, prototypes: int}|null what the file
     *      holds, or null if it is a question file.
     */
    private static function definitions_in(string $xml): ?array {
        global $CFG;

        require_once($CFG->dirroot . '/question/format/xml/format.php');

        $format = new \qformat_xml();
        $parsed = class_exists('\\core\\xml_parser')
            ? (new \core\xml_parser())->parse($xml, 0, 'UTF-8', true)
            : xmlize($xml, 0, 'UTF-8', true);

        $definitions = [];
        $prototypes = 0;
        foreach ($format->getpath($parsed, ['quiz', '#', 'question'], []) as $questionxml) {
            if ($format->getpath($questionxml, ['@', 'type'], '') !== 'coderunner') {
                // A category element, or another question type's question.
                continue;
            }
            if ((int) $format->getpath($questionxml, ['#', 'prototypetype', 0, '#'], 0) === 0) {
                return null;
            }

            $chain = chain_transport::read_for($questionxml, $format);
            if ($chain === null) {
                // A CodeRunner 5 file, or a 6.0 one from before the uuid.
                continue;
            }
            $prototypes++;
            foreach ($chain['chain'] as $definition) {
                $definitions[$definition->uuid] = $definition;
            }
        }

        return ['definitions' => array_values($definitions), 'prototypes' => $prototypes];
    }
}
