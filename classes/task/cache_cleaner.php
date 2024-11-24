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
 * @package    qtype_coderunner
 * @copyright  2024 Paul McKeown, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



namespace qtype_coderunner\task;

defined('MOODLE_INTERNAL') || die();

use cache;
use cache_helper;
use cache_store_file;
use cache_definition;

global $CFG;

/**
 * Task for purging old Coderunner cach entries
 */
class cache_cleaner extends \core\task\scheduled_task {
    const MAX_AGE = 10; // 7 * 24 * 60 * 6; // Seven days.

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return ('Purge Old Coderunner Cache Entries'); // A get_string('purgeoldcacheentries', 'qtype_coderunner');.
    }

    /**
     * Execute the task.
     */
    public function execute() {

        $configerer = \cache_config::instance();
        $defs = $configerer->get_definitions();
        foreach ($defs as $id => $def) {
            if ($def['component'] == 'qtype_coderunner' && $def['area'] == 'coderunner_grading_cache') {
                $definition = cache_definition::load($id, $def);
                mtrace('ID for created definition '.$definition->get_id());
                $stores = cache_helper::get_cache_stores($definition);
                $ttl = $definition->get_ttl();
                $days = $ttl / 60 / 60 / 24;
                mtrace("Time to live (TTL) is $ttl seconds (= $days days)");
                foreach ($stores as $store) {
                    mtrace("Store is searchable: " . $store->is_searchable());
                    if ($store->is_searchable()) {
                        $keys = $store->find_all();
                        $originalcount = count($keys);
                        // Do a get on every key.
                        // The file cache get method should delete keys that are older than ttl
                        foreach ($keys as $key) {
                            $value = $store->get($key);
                        }
                        $remainingkeys = $store->find_all();
                        $newcount = count($remainingkeys);
                        mtrace("Did a get on all $originalcount keys.");
                        mtrace("There are $newcount keys remaining.");
                    } else {
                        mtrace("Cache isn't searchable so can find all the keys...");
                    }
                }
                break;  // Should be only one definition for Coderunner.
            }
        }
        mtrace("Done for now.");

        // Really needed ??? global $DB;.
        // $cache = cache::make('qtype_coderunner', 'coderunner_grading_cache');
        // $keys = $cache->find_all();
        // foreach ($keys as $key) {
        //     mtrace($key);
        // }



        //if (! $cache instanceof cachestore_file) {
        //    echo($cache);
        //    mtrace('Cache store is not a file cache store - cleanup not needed');
        //} else {
        // $reflection = new \ReflectionClass($cache);
        // $property = $reflection->getProperty('store');
        // $property->setAccessible(true);




        // // Use reflection to access the private method
        // $reflection = new \ReflectionClass($cache);
        // $method = $reflection->getMethod('get_store');
        // $method->setAccessible(true);
        // // Call the private method
        // $store = $method->invoke($cache);

        // $reflection = new \ReflectionClass($store);
        // $property = $reflection->getProperty('path');
        // $property->setAccessible(true);
        // $path = $property->getValue($store);

        // mtrace("qtype_coderunner coderunner_grading_cache cleanup initiated.");
        // mtrace("Cache files are stored in: $path");
        // if ($path) {
        //     // Scans cache directory.
        //     $files = new \RecursiveDirectoryIterator($path);
        //     $iterator = new \RecursiveIteratorIterator($files);
        //     $deleted = 0;
        //     $found = 0;
        //     $now = time();
        //     foreach ($iterator as $file) {
        //         if ($file->isFile()) {
        //             $found++;
        //             $mtime = $file->getMTime();
        //             if (($now - $mtime) > self::MAX_AGE) {
        //                 if (unlink($file->getPathname())) {
        //                     $deleted++;
        //                 }
        //             }
        //         }
        //     }
        //     mtrace("Found $found cache files in total.");
        //     mtrace("Deleted $deleted old cache files.");

        //}
    }
}
