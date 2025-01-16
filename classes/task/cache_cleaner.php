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

/* A sandbox that uses the Jobe server (http://github.com/trampgeek/jobe) to run
 * student submissions.
 *
 * This version doesn't do any authentication; it's assumed the server is
 * firewalled to accept connections only from Moodle.
 *
 * This class is used by the scheduler to cleanup the cache
 * Admins can change the schedule in Site Adminstration -> Server -> Scheduled Tasks -> Purge Old Coderunner Cache Entries
 *
 * @package    qtype_coderunner
 * @copyright  2024 Paul McKeown, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



namespace qtype_coderunner\task;

defined('MOODLE_INTERNAL') || die();

use cache;
use cache_helper;
use cachestore_file;
use cache_definition;
use qtype_coderunner\cache_purger;

global $CFG;

/**
 * Task for purging old Coderunner cache entries.
 */
class cache_cleaner extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return ('Purge Old Coderunner Cache Entries - task'); // A get_string('purgeoldcacheentries', 'qtype_coderunner');.
    }

    /**
     * Execute the task.
     */
    public function execute() {
        // Use system context as purging everything
        // ... and set use TTL to true so that only old
        // ... entries are purged.
        $purger = new cache_purger(1, true);
        $definition = $purger->get_coderunner_cache_definition();
        $store = $purger->get_first_file_store($definition);
        $ttl = $purger->ttl;
        if ($ttl) {
            $days = round($ttl / 60 / 60 / 24, 4);
            mtrace("Time to live (TTL) is $ttl seconds (= $days days)");
        }
        // $store->purge_old_entries();
        // Use reflection to access the private cachestore_file method file_path_for_key
        $reflection = new \ReflectionClass($store);
        $filepathmethod = $reflection->getMethod('file_path_for_key');
        $filepathmethod->setAccessible(true);

        $keys = $store->find_all();
        $originalcount = count($keys);
        // Do a get on every key.
        // The file cache get method should delete keys that are older than ttl but it doesn't...
        $maxtime = cache::now() - $ttl;
        foreach ($keys as $key) {
            // Call the private method
            $path = $filepathmethod->invoke($store, $key);
            $filetime = filemtime($path);
            if ($ttl && $filetime < $maxtime) {
                $store->delete($key);
            }
        }
            // $value = $store->get($key);  // Would delete old key if fixed in file store.
        $remainingkeys = $store->find_all();
        $newcount = count($remainingkeys);
        $purgedcount = $originalcount - $newcount;
        mtrace("Originally found $originalcount keys.");
        mtrace("$purgedcount keys pruged.");
        mtrace("$newcount keys were too young to die.");
    }
}
