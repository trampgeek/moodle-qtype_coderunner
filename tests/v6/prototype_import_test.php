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
 * Unit tests for reading a prototype export file into a library.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use local_coderunner_manager\prototype_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * The manager's import door: what a file would do, and then doing it.
 *
 * Every fixture here is a real export file rather than hand-written XML, so
 * that the two halves of decision 36 are tested against each other: a file the
 * emitter would not produce proves nothing about an import.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prototype_import::class)]
#[\PHPUnit\Framework\Attributes\Group('qtype_coderunner')]
final class prototype_import_test extends \advanced_testcase {
    /** @var prototype_repository the manager's repository. */
    private prototype_repository $repo;

    /** @var int the course whose library is imported into. */
    private int $courseid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->repo = new prototype_repository();
        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
    }

    /**
     * A file this site has never seen creates its prototypes, parents first.
     */
    public function test_an_unknown_library_is_created(): void {
        $xml = $this->library_file();
        $this->empty_the_library();

        $plan = prototype_import::plan($xml, $this->courseid);
        $this->assertNull($plan['problem']);
        $this->assertSame(
            [prototype_import::CREATE, prototype_import::CREATE],
            array_column($plan['entries'], 'action')
        );
        $this->assertSame(['base', 'child'], array_column($plan['entries'], 'name'), 'Parents first.');

        $done = prototype_import::apply($xml, $this->courseid);
        $this->assertSame(['base', 'child'], $done['created']);

        $child = $this->by_name('child');
        $this->assertSame(
            'BASE TEMPLATE',
            $this->repo->chain_for($child->id)->resolve()->get('template'),
            'The chain must be rebuilt, not flattened: the template comes from the parent.'
        );
    }

    /**
     * A newer release of a library this site already has adds versions.
     *
     * Design section 9's rule, and the difference between this door and the
     * question import's, which adopts and leaves the local copy alone.
     */
    public function test_a_newer_release_adds_a_version(): void {
        $this->library_file();
        $child = $this->by_name('child');

        // The release: the child's own override changes.
        $this->repo->save_new_version(
            $child,
            new override_set(['penaltyregime' => '0, 0, 50, ...'], 'child'),
            $child->parentid(),
            'a newer release',
            ''
        );
        $xml = prototype_export::file_for([$this->by_name('base'), $this->by_name('child')], $this->repo);

        // Put the library back as it was, so the file is ahead of the site.
        $this->repo->save_new_version(
            $this->by_name('child'),
            new override_set(['penaltyregime' => '10, 20, ...'], 'child'),
            $child->parentid(),
            '',
            ''
        );
        $versionsbefore = count($this->repo->history($child->id));

        $plan = prototype_import::plan($xml, $this->courseid);
        $actions = array_combine(array_column($plan['entries'], 'name'), array_column($plan['entries'], 'action'));
        $this->assertSame(prototype_import::VERSION, $actions['child']);
        $this->assertSame(prototype_import::UNCHANGED, $actions['base'], 'The ancestor the file did not change.');

        $done = prototype_import::apply($xml, $this->courseid);
        $this->assertSame(['child'], $done['versioned']);
        $this->assertSame(['base'], $done['unchanged']);
        $this->assertCount(
            $versionsbefore + 1,
            $this->repo->history($child->id),
            'The incoming content should arrive as one new version.'
        );
        $this->assertSame(
            '0, 0, 50, ...',
            $this->repo->chain_for($this->by_name('child')->id)->resolve()->get('penaltyregime'),
            "The new version must hold the file's value."
        );
    }

    /**
     * Importing the same file twice changes nothing the second time.
     */
    public function test_re_importing_is_idempotent(): void {
        $xml = $this->library_file();
        $this->empty_the_library();
        prototype_import::apply($xml, $this->courseid);

        $child = $this->by_name('child');
        $versions = count($this->repo->history($child->id));

        $done = prototype_import::apply($xml, $this->courseid);

        $this->assertSame([], $done['created']);
        $this->assertSame([], $done['versioned']);
        $this->assertSame(['base', 'child'], $done['unchanged']);
        $this->assertCount($versions, $this->repo->history($child->id));
    }

    /**
     * A name already taken by an unrelated prototype is refused, and refusing
     * one entry imports nothing at all.
     *
     * Decision 34's rule applied to a library: the two are unrelated question
     * types that happen to share a name, and both versioning over it and
     * renaming it would change what some question elsewhere resolves to.
     */
    public function test_a_name_clash_refuses_the_whole_file(): void {
        $xml = $this->library_file();
        $this->empty_the_library();

        // Something unrelated, of the same name as the file's child.
        $this->repo->create('child', $this->courseid, new override_set(['template' => 'MINE'], 'child'));

        $plan = prototype_import::plan($xml, $this->courseid);
        $actions = array_combine(array_column($plan['entries'], 'name'), array_column($plan['entries'], 'action'));
        $this->assertSame(prototype_import::REFUSE, $actions['child']);
        $this->assertSame(prototype_import::CREATE, $actions['base']);

        $reasons = array_filter(array_column($plan['entries'], 'reason'));
        $this->assertStringContainsString('already has a different question type', reset($reasons));

        $done = prototype_import::apply($xml, $this->courseid);
        $this->assertSame(['child'], $done['refused']);
        $this->assertSame([], $done['created'], 'A refusal must import nothing: half a library is worse than none.');
        $this->assertNull($this->by_name('base'), 'The parent must not have been created either.');
    }

    /**
     * A question file is refused rather than quietly read as a library.
     *
     * A question carries its chain's definitions too, so without this a
     * question file would install the prototypes and drop every question.
     */
    public function test_a_question_file_is_not_a_library(): void {
        $prototype = $this->repo->create(
            'for_a_question',
            $this->courseid,
            new override_set(['template' => 'T'], 'for_a_question')
        );
        $questionxml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n"
            . "  <question type=\"coderunner\">\n"
            . "    <name><text>a question</text></name>\n"
            . "    <prototypetype>0</prototypetype>\n"
            . "    <coderunnertype>for_a_question</coderunnertype>\n"
            . "    <prototypeuuid>{$prototype->uuid}</prototypeuuid>\n"
            . "  </question>\n</quiz>\n";

        $plan = prototype_import::plan($questionxml, $this->courseid);

        $this->assertSame(prototype_import::QUESTIONS, $plan['problem']);
        $this->assertSame([], $plan['entries']);
    }

    /**
     * A file of built-ins says so, rather than claiming to hold nothing.
     *
     * The author exported three site prototypes, tried to import them and was
     * told the file contained no prototypes. It holds three, and decision 34
     * is why none of them is *defined* in it: a built-in is named by uuid and
     * found on the receiving site, because carrying it would give that site a
     * second copy of what it already has. So the file is perfectly good -- for
     * a CodeRunner 5 site that lacks one of them -- and there is simply
     * nothing for a CodeRunner 6 site to do with it.
     */
    public function test_a_file_of_builtins_says_so(): void {
        $builtin = $this->repo->create(
            'a_builtin',
            (int) SITEID,
            new override_set(['template' => 'T'], 'a_builtin'),
            null,
            '',
            '',
            true
        );

        $plan = prototype_import::plan(prototype_export::file($builtin, $this->repo), $this->courseid);

        $this->assertSame(prototype_import::BUILTINS, $plan['problem']);
        $this->assertSame(1, $plan['shipped'], 'The file names one prototype and defines none.');
        $this->assertSame([], $plan['entries']);
    }

    /**
     * A built-in beside a real prototype is counted, not silently dropped.
     *
     * The table would otherwise have fewer rows than the file has prototypes,
     * and an author who counted them would wonder which went missing.
     */
    public function test_a_builtin_beside_a_prototype_is_reported(): void {
        $builtin = $this->repo->create(
            'another_builtin',
            (int) SITEID,
            new override_set(['template' => 'T'], 'another_builtin'),
            null,
            '',
            '',
            true
        );
        $mine = $this->repo->create(
            'mine',
            $this->courseid,
            new override_set(['template' => 'M'], 'mine')
        );

        $plan = prototype_import::plan(
            prototype_export::file_for([$builtin, $mine], $this->repo),
            $this->courseid
        );

        $this->assertNull($plan['problem']);
        $this->assertSame(['mine'], array_column($plan['entries'], 'name'));
        $this->assertSame(1, $plan['shipped']);
    }

    /**
     * A file with no CodeRunner prototypes in it says so.
     */
    public function test_an_empty_file_says_so(): void {
        $plan = prototype_import::plan(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n</quiz>\n",
            $this->courseid
        );

        $this->assertSame(prototype_import::NOTHING, $plan['problem']);
    }

    /**
     * A copy in another course is not matched, and not written to.
     *
     * `find_one_by_uuid()` falls back to any course's copy, which is right for
     * a restore -- the alternative there is a duplicate -- and wrong for an
     * import: matching course B's copy from a page about course A would add a
     * version to B's prototype and change every question in B that resolves
     * against it. Found by rendering the preview for a course that had neither
     * prototype and seeing both reported as already here.
     */
    public function test_another_courses_copy_is_not_matched(): void {
        $xml = $this->library_file();
        $theirs = $this->by_name('child');
        $theirversions = count($this->repo->history($theirs->id));

        // A second course, which has never seen this library.
        $elsewhere = (int) $this->getDataGenerator()->create_course()->id;

        $plan = prototype_import::plan($xml, $elsewhere);
        $this->assertSame(
            [prototype_import::CREATE, prototype_import::CREATE],
            array_column($plan['entries'], 'action'),
            "Another course's copies must read as absent here."
        );

        $done = prototype_import::apply($xml, $elsewhere);
        $this->assertSame(['base', 'child'], $done['created']);
        $this->assertCount(
            $theirversions,
            $this->repo->history($theirs->id),
            "The other course's prototype must be untouched."
        );
        $this->assertCount(2, $this->repo->find_by_uuid($theirs->uuid), 'Each scope holds its own copy.');
    }

    /**
     * A summary that differs only in trailing space is unchanged.
     *
     * The file carries text through `writetext()` and it returns through
     * `getpath(..., true)`, which trims, so a stored summary ending in a space
     * cannot be represented in a file. Comparing bytes therefore reported an
     * unchanged prototype as changed for ever -- seen on the development site,
     * where `python3`'s summary ends in a space and a file exported from that
     * very site read back as a new version of it.
     */
    public function test_whitespace_alone_is_not_a_change(): void {
        $prototype = $this->repo->create(
            'trailing',
            $this->courseid,
            new override_set(['template' => 'T'], 'trailing'),
            null,
            'Runs stock Python3 '
        );
        $xml = prototype_export::file($prototype, $this->repo);

        $plan = prototype_import::plan($xml, $this->courseid);

        $this->assertSame(
            [prototype_import::UNCHANGED],
            array_column($plan['entries'], 'action'),
            'A round trip through the file must not read as a change.'
        );
    }

    /**
     * A real exported file, imported into a site that has never seen it, and
     * then re-planned.
     *
     * **The fixture is an unedited export from the development site**, which
     * is the point of it: a hand-written one carries only the oddities its
     * author thought of, and the two bugs this import has had were both
     * real-world text that no fixture would have contained -- a summary ending
     * in a space, and documentation with CRLF line endings. This one has the
     * CRLF. It is `c_function` because that is the smallest genuine export on
     * that site with those line endings in it and no support files: 6 KB,
     * where the library export the author first offered was 1.6 MB, ten times
     * everything else in this directory.
     *
     * Its parent is a built-in, which decision 34 keeps out of the file, so it
     * arrives here as a root -- the documented degradation for an ancestor the
     * receiving site does not have.
     */
    public function test_a_real_exported_file_imports_and_then_settles(): void {
        $xml = file_get_contents(__DIR__ . '/../fixtures/prototype_export_c_function.xml');

        $plan = prototype_import::plan($xml, $this->courseid);
        $this->assertNull($plan['problem']);
        $this->assertSame(['c_function'], array_column($plan['entries'], 'name'));
        $this->assertSame([prototype_import::CREATE], array_column($plan['entries'], 'action'));

        $done = prototype_import::apply($xml, $this->courseid);
        $this->assertSame(['c_function'], $done['created']);

        $landed = $this->by_name('c_function');
        $this->assertNotNull($landed);
        $this->assertNull($landed->parentid(), 'A built-in ancestor does not travel, so this arrives a root.');
        $this->assertStringContainsString(
            'write-a-function',
            $landed->currentversion->documentation,
            'The documentation must have survived the round trip.'
        );

        // And the file now says nothing new, which is the property that broke
        // twice: a prototype imported from a file must not read as changed by
        // that same file.
        $again = prototype_import::plan($xml, $this->courseid);
        $this->assertSame([prototype_import::UNCHANGED], array_column($again['entries'], 'action'));
    }

    /**
     * Documentation with CRLF line endings is not a change.
     *
     * The bug the author found by exporting six of his own prototypes and
     * importing them back into the same course: three read as *New version*
     * against identical version numbers. XML 1.0 requires a parser to
     * normalise `\r\n` to `\n` (section 2.11), so a `\r` cannot survive the
     * round trip and a stored CRLF value is not representable in a file.
     * Documentation typed into a browser arrives as CRLF, so this is the
     * ordinary case and not an edge one.
     *
     * The overrides are deliberately given CRLF too, and must *not* need the
     * same treatment: they travel as JSON inside the XML text, so the escaping
     * has already turned every `\r` into a two-character escape that the XML
     * parser never sees as a line ending.
     */
    public function test_crlf_line_endings_are_not_a_change(): void {
        $prototype = $this->repo->create(
            'crlf',
            $this->courseid,
            new override_set(['template' => "line one\r\nline two\r\n"], 'crlf'),
            null,
            'a summary',
            "<p>First.</p>\r\n<p>Second.</p>\r\n"
        );
        $xml = prototype_export::file($prototype, $this->repo);

        $plan = prototype_import::plan($xml, $this->courseid);

        $this->assertSame(
            [prototype_import::UNCHANGED],
            array_column($plan['entries'], 'action'),
            'A file exported from this very site read back as a new version of it.'
        );
    }

    /**
     * A two-prototype library, exported, with the fixture still in place.
     *
     * @return string the file.
     */
    private function library_file(): string {
        $parent = $this->repo->create(
            'base',
            $this->courseid,
            new override_set(['template' => 'BASE TEMPLATE'], 'base')
        );
        $this->repo->create(
            'child',
            $this->courseid,
            new override_set(['penaltyregime' => '10, 20, ...'], 'child'),
            $parent->id
        );

        return prototype_export::file_for([$this->by_name('base'), $this->by_name('child')], $this->repo);
    }

    /**
     * Delete the fixture, leaving a site that has never seen the file.
     *
     * Children first, since a prototype with dependants cannot go.
     */
    private function empty_the_library(): void {
        foreach (['child', 'base'] as $name) {
            $prototype = $this->by_name($name);
            if ($prototype !== null) {
                $this->repo->delete($prototype);
            }
        }
    }

    /**
     * One of this course's prototypes, by name.
     *
     * @param string $name the name.
     * @return \local_coderunner_manager\prototype|null the prototype.
     */
    private function by_name(string $name) {
        foreach ($this->repo->list_for_course($this->courseid) as $prototype) {
            if ($prototype->name === $name) {
                return $prototype;
            }
        }
        return null;
    }
}
