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
 * Clearing cached question data when something a question inherits changes.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

use qtype_coderunner\constants;

/**
 * The `core/questiondata` cache, as it concerns CodeRunner questions.
 *
 * A CodeRunner question's prototype chain is resolved inside
 * qtype_coderunner::get_question_options(), and core holds that method's result
 * in the `core/questiondata` application cache. Decision 15 defers a
 * resolved-settings cache precisely because this one already covers it: a
 * question is resolved once per cache entry rather than once per attempt.
 *
 * The price is that everything resolution produced -- every inherited field,
 * every layer of template and UI parameters, and the support-file areas, which
 * are recorded as *version* ids -- is frozen into that entry at the version the
 * prototype had when it was written. Nothing a prototype does afterwards
 * reaches the question until the entry goes. So every operation that changes
 * what a question resolves to has to come through here.
 *
 * Whoever does that faces the same two questions each time -- which questions,
 * and is that too many to do one at a time -- so the answers live here once
 * rather than at each of the three call sites.
 */
class question_cache {
    /** @var string The question type's options table. */
    const OPTIONS = 'question_coderunner_options';

    /**
     * Whether this many questions is more than is worth uncaching one by one.
     *
     * Fetching and iterating the dependants of a prototype used site-wide can
     * exhaust memory or time out: python3_scratchpad has 8,304 children on the
     * development site, and one site has reported 410,000 questions in total.
     * Past the threshold the whole definition is purged instead. Purging more
     * than is strictly necessary is safe -- it is a cache, and it reloads on
     * demand -- where leaving a stale entry is not.
     *
     * @param int $count how many questions would be uncached individually.
     * @return bool true to purge the lot instead.
     */
    public static function too_many(int $count): bool {
        return $count > constants::BULK_CACHE_PURGE_THRESHOLD;
    }

    /**
     * Throw away every cached question, of every question type.
     *
     * @return void
     */
    public static function purge(): void {
        if (self::disabled()) {
            return;
        }
        \core_cache\helper::purge_by_definition('core', 'questiondata');
    }

    /**
     * Uncache the given questions, or purge the lot if there are too many.
     *
     * @param int[] $questionids the questions to uncache.
     * @return void
     */
    public static function uncache(array $questionids): void {
        global $CFG;

        if (self::disabled() || !$questionids) {
            return;
        }
        if (self::too_many(count($questionids))) {
            self::purge();
            return;
        }

        // question_bank is not autoloaded, and the callers include an adhoc
        // task that need never have touched a question page.
        require_once($CFG->dirroot . '/question/engine/bank.php');

        foreach ($questionids as $questionid) {
            \question_bank::notify_question_edited((int) $questionid);
        }
    }

    /**
     * Uncache every question whose options row matches a select.
     *
     * Counted before the ids are fetched, so that a selection too large to
     * uncache one at a time is also too large to load.
     *
     * The count is over the options table rather than over question bank
     * entries: there is a cache entry per stored question, so every version of
     * every question has to be uncached, not one per question as an author
     * counts them.
     *
     * @param string $select a WHERE clause over {question_coderunner_options}.
     * @param array $params its parameters.
     * @return void
     */
    public static function uncache_matching(string $select, array $params): void {
        global $DB;

        if (self::disabled()) {
            return;
        }

        $count = $DB->count_records_select(self::OPTIONS, $select, $params);
        if ($count === 0) {
            return;
        }
        if (self::too_many($count)) {
            self::purge();
            return;
        }

        self::uncache($DB->get_fieldset_select(self::OPTIONS, 'questionid', $select, $params));
    }

    /**
     * Whether caching is off altogether, as it is under some test harnesses.
     *
     * @return bool true if there is nothing to clear.
     */
    protected static function disabled(): bool {
        return defined('CACHE_DISABLE_ALL') && CACHE_DISABLE_ALL !== false;
    }
}
