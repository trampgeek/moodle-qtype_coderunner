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
 * Set the template parameter language to None for questions that do not use Twig.
 *
 * CodeRunner's `question_coderunner_options.templateparamslang` column defaults
 * to twig, so a question created by any route other than the authoring form —
 * which defaults to None — is marked as using Twig whether it does or not. Such
 * a question is run through the Twig engine on every page load for nothing.
 *
 * A question is converted only when Twig provably does nothing to it: rendering
 * its template parameters returns the same text, ignoring the final newline
 * that Twig strips from every template. If the rendered output is identical,
 * None produces the same parameters by definition, so the change cannot alter
 * behaviour. Anything that renders differently, or fails to render, is left
 * alone.
 *
 * Nothing is written without --execute. Run it without first and read the
 * report.
 *
 * Usage:
 *   php retire_unused_twig.php [--execute] [--verbose]
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
    ['execute' => false, 'verbose' => false, 'help' => false],
    ['e' => 'execute', 'v' => 'verbose', 'h' => 'help']
);

if ($options['help'] || $unrecognised) {
    cli_writeln("Set the template parameter language to None where Twig provably does nothing.

A question is converted only if rendering its template parameters through Twig
returns the same text, so the change cannot alter what the question does.

Options:
  -e, --execute   Apply the changes. Without this nothing is written.
  -v, --verbose   List every question considered, not just the totals.
  -h, --help      Print this help.
");
    exit(0);
}

/**
 * Whether Twig leaves a question's template parameters unchanged.
 *
 * Twig strips the final newline of every template, which is not the question
 * using Twig, so trailing whitespace is ignored in the comparison.
 *
 * @param string $raw the stored template parameters.
 * @return bool|null true if Twig does nothing, false if it does, null if
 *      rendering failed and the question should be left alone.
 */
function twig_is_a_no_op(string $raw): ?bool {
    try {
        mt_srand(1);
        $rendered = qtype_coderunner_twig::render($raw);
    } catch (Throwable $e) {
        return null;
    }

    $normalise = fn($text) => rtrim(str_replace("\r\n", "\n", $text));
    return $normalise($rendered) === $normalise($raw);
}

$rows = $DB->get_recordset_select(
    'question_coderunner_options',
    "templateparamslang = :lang AND " .
        $DB->sql_isnotempty('question_coderunner_options', 'templateparams', true, true),
    ['lang' => 'twig'],
    'id'
);

$counts = ['examined' => 0, 'convertible' => 0, 'usestwig' => 0, 'unrenderable' => 0, 'notjson' => 0];
$convert = [];

foreach ($rows as $row) {
    $counts['examined']++;
    $raw = (string) $row->templateparams;

    $noop = twig_is_a_no_op($raw);
    if ($noop === null) {
        $counts['unrenderable']++;
        continue;
    }
    if (!$noop) {
        $counts['usestwig']++;
        if ($options['verbose']) {
            cli_writeln("  question {$row->questionid}: uses Twig, left alone.");
        }
        continue;
    }

    // Belt and braces: with no preprocessor the field is used as it stands, so
    // it had better be the JSON that CodeRunner will try to parse.
    if (!is_array(json_decode($raw, true))) {
        $counts['notjson']++;
        cli_writeln("  question {$row->questionid}: Twig does nothing but the text is not valid JSON; left alone.");
        continue;
    }

    $counts['convertible']++;
    $convert[] = $row->id;
    if ($options['verbose']) {
        cli_writeln("  question {$row->questionid}: convertible.");
    }
}
$rows->close();

cli_writeln('');
cli_writeln(sprintf('Examined          %6d questions with templateparamslang = twig', $counts['examined']));
cli_writeln(sprintf('Genuinely Twig    %6d  left alone', $counts['usestwig']));
cli_writeln(sprintf('Would not render  %6d  left alone', $counts['unrenderable']));
cli_writeln(sprintf('Not valid JSON    %6d  left alone', $counts['notjson']));
cli_writeln(sprintf('Convertible       %6d', $counts['convertible']));

if (!$convert) {
    cli_writeln('');
    cli_writeln('Nothing to do.');
    exit(0);
}

if (!$options['execute']) {
    cli_writeln('');
    cli_writeln('Nothing written. Re-run with --execute to apply.');
    exit(0);
}

$transaction = $DB->start_delegated_transaction();
foreach (array_chunk($convert, 500) as $chunk) {
    [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'id');
    $params['lang'] = 'None';
    $DB->execute(
        "UPDATE {question_coderunner_options} SET templateparamslang = :lang WHERE id $insql",
        $params
    );
}
$transaction->allow_commit();

// The resolved settings of every affected question are cached, and the
// language is one of them.
purge_caches(['muc' => true]);

cli_writeln('');
cli_writeln(sprintf('Converted %d questions to None, and purged the caches.', count($convert)));
