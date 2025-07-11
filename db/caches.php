<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
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
 * Defines cache used to store results of quiz attempt steps.
 * If a jobe submission is cached we don't need to call jobe again
 * as the result will be known :)
 *
 * IMPORTANT:
 * You probably shouldn't use anything other than a filestore for this cache.
 * For example, a Redis cache might fill up and cause all kinds of issues.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2023 Paul McKeown, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'coderunner_grading_cache' => [
        'mode' => cache_store::MODE_APPLICATION,
        // The maxsize will be ignored by the standard file cache but
        // other caches might respect it...
        'maxsize' => 50000000, // Seems like a good start...
        'simplekeys' => true,
        'simpledata' => false,
        'canuselocalstore' => true,
        'requiredataguarantee' => true, // The cached data needs to hang around to be useful.
        'ttl' => 1209600, // 1209600 seconds = 14 days by default.
        // When using a file store for the cache this ttl setting
        // will be used by the cache purger and scheduled the file store cache purge task.
        // The scheduled coderunner file store cache purging will only run for file store caches.
        // The file cache will respect this ttl and not serve up anything older,
        // but it doesn't remove old entries automatically.
        // Important:
        // If you must use another type of store then should make sure that
        // you've set the ttl and cache settings appropriately, eg,
        // Redis will schedule a clean-up based on ttl but you will also
        // need to set maxmemory to something sensible and maxmemory-policy
        // so that you don't run out of memory!
        //
        // Helpful note:
        // If you change this file you can get Moodle to update its settings
        // by using the Rescan definitions link on the Admin Settings,
        // plugins cache configuration page.
    ],
];
