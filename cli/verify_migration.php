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
 * Check every migrated question against the columns it was migrated from.
 *
 * The CodeRunner 6 migration is additive: the CodeRunner 5 option columns are
 * retained, so what each question resolves to now can be compared against what
 * it resolved to before. This script does that comparison and reports any
 * question whose settings have changed.
 *
 * It can be run as often as wanted and changes nothing. If it reports
 * differences, the conversion can be corrected and re-run from the retained
 * columns, without restoring a backup.
 *
 * Usage:
 *   php verify_migration.php [--limit=N] [--verbose]
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

[$options, $unrecognised] = cli_get_params(
    ['limit' => 0, 'verbose' => false, 'help' => false],
    ['l' => 'limit', 'v' => 'verbose', 'h' => 'help']
);

if ($options['help'] || $unrecognised) {
    cli_writeln("Check every migrated CodeRunner question against the columns it was migrated from.

Options:
  -l, --limit=N   Stop after N questions (default: all).
  -v, --verbose   List every differing question rather than a sample.
  -h, --help      Print this help.
");
    exit(0);
}

$verifier = new \qtype_coderunner\v6\migration_verifier();

$started = microtime(true);
$result = $verifier->verify((int) $options['limit'], function ($done, $total) {
    if ($done % 1000 === 0) {
        cli_writeln(sprintf('  %d / %d', $done, $total));
    }
});
$elapsed = microtime(true) - $started;

cli_separator();
cli_writeln(sprintf(
    'Checked %d questions in %.0f seconds: %d unchanged, %d changed.',
    $result->checked,
    $elapsed,
    $result->matched,
    $result->differing
));

if ($result->unmigrated) {
    cli_writeln(sprintf(
        '%d questions have not been migrated yet. The prototype manager\'s install task runs on cron.',
        $result->unmigrated
    ));
}

if ($result->broken) {
    cli_writeln(sprintf(
        "\n%d question versions have no resolvable prototype and were left broken, which is the state they",
        $result->broken
    ));
    cli_writeln('were already in. The prototype health report lists them with the reason.');
    arsort($result->brokenbyname);
    foreach ($result->brokenbyname as $name => $count) {
        cli_writeln(sprintf('  %-34s %d question version(s)', $name, $count));
    }
}

if (!$result->differing) {
    cli_separator();
    cli_writeln('No question changed. The migration preserved every question\'s settings.');
    exit(0);
}

cli_separator();
cli_writeln('Questions whose settings changed, grouped by the fields that differ:');
foreach ($result->differences as $fields => $group) {
    cli_writeln(sprintf("\n  %d question(s) differ in: %s", $group['count'], $fields));
    cli_writeln(sprintf(
        '    for example question %d, of type %s',
        $group['example']['questionid'],
        $group['example']['type']
    ));
    foreach ($group['fields'] as $field => $values) {
        cli_writeln(sprintf(
            '    %-24s before: %-24s after: %s',
            $field,
            var_export($values['before'], true),
            var_export($values['after'], true)
        ));
    }
}
exit(1);
