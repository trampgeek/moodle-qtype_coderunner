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
 * This class is used by the scheduler to cleanup the grading cache.
 * Currently only cleans up the cache if it's in a file store.
 * Admins can change the schedule in Site Adminstration -> Server -> Scheduled Tasks -> Purge Old Coderunner Cache Entries
 *
 * @package    qtype_coderunner
 * @copyright  2024-5 Paul McKeown, University of Canterbury
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
        $taskname = get_string('purgeoldcacheentriestaskname', 'qtype_coderunner');
        return $taskname;
    }

    /**
     * This task will purge old cache entries in file caches used
     * by the Coderunner grading cache definition.
     * This task will only cleanup if a file store is being used.
     * Task does nothing if the grading cache isn't enabled
     * in the Coderunner admin settings. You might want
     * to purge the Coderunner grading cache manually
     * if you aren't going to turn it on again.
     */
    public function execute() {
        if (get_config('qtype_coderunner', 'enablegradecache')) {
            $purger = new cache_purger(usettl:true);
            $definition = $purger->get_coderunner_cache_definition();
            $filestores = $purger->get_file_stores($definition);
            if (count($filestores) > 0) {
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
                $maxtime = cache::now() - $ttl;
                foreach ($keys as $key) {
                    // Call the private method.
                    $path = $filepathmethod->invoke($store, $key);
                    $filetime = filemtime($path);
                    if ($ttl && $filetime < $maxtime) {
                        $store->delete($key);
                    }
                }
                // Using $value = $store->get($key);  // Would delete old key if fixed in file store.
                $remainingkeys = $store->find_all();
                $newcount = count($remainingkeys);
                $purgedcount = $originalcount - $newcount;
                mtrace("Originally found $originalcount keys.");
                mtrace("$purgedcount keys purged.");
                mtrace("$newcount keys were too young to die.");
            } else {
                mtrace("Grading cache not using a file store so nothing to do. You should probably unschedule this task.");
                mtrace("If you're using a Redis store use the Redis purging task to manage cache size.");
            }
        } else {
            mtrace("Grading cache not enabled so not purging.");
        }
    }
}
