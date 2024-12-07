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
 * This script provides a class with support methods for running question tests in bulk.
 * It is taken from the qtype_stack plugin with slight modifications.
 *
 * Modified to provide services for the prototype usage script and the
 * autotagger script.
 *
 * @package   qtype_coderunner
 * @copyright 2024 Paul McKeown, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use cache;
use cache_helper;
use cachestore_file;
use cache_definition;
use context;
use moodle_url;
use core\chart_bar;
use core\chart_series;

defined('MOODLE_INTERNAL') || die();


class cache_purger {
    /**
     * Get all the visible course contexts.
     *
     * @return array context ids
     */
    public function get_all_visible_course_contextids() {
        global $DB;

        $allcontexts = $DB->get_records_sql("
            SELECT ctx.id as contextid
              FROM {context} ctx
              ORDER BY contextid"); //, ['contextid' => $contextid]);
        $result = [];
        foreach ($allcontexts as $record) {
            $contextid = $record->contextid;
            $context = context::instance_by_id($contextid);
            if (has_capability('moodle/question:editall', $context)) {
                // Only add in courses for now.
                if ($context->contextlevel == CONTEXT_COURSE) {
                    $result[] = $contextid;
                }
            }
        } // endfor each contextid
        return $result;
    }



    // Returns a map from contextid to count of keys
    public function key_count_by_course(array $contextids) {
        $contextcounts = [];
        $coursetocontext = [];  // Maps courseid to contextid.
        foreach ($contextids as $contextid) {
            $contextcounts[$contextid] = 0;
            $context = context::instance_by_id($contextid);
            $coursename = $context->get_context_name(true, true);
            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = $context->instanceid;
                $coursetocontext[$courseid] = $contextid;
            } else {
                // should be an error here
            }
        }
        $definition = $this->get_coderunner_cache_definition();
        $store = $this->get_first_file_store($definition);
        $keys = $store->find_all();
        //print_r($keys);
        $pattern = '/_courseid_(\d+)_/';
        foreach ($keys as $key) {
            preg_match($pattern, $key, $match);
            $courseid = $match[1];
            if (array_key_exists($courseid, $coursetocontext)) {
                $contextid = $coursetocontext[$courseid];
                $contextcounts[$contextid] += 1;
            }
        }
        // go through all keys and count by context...
        return $contextcounts;
    }


    public function get_coderunner_cache_definition() {
        $configerer = \cache_config::instance();
        $defs = $configerer->get_definitions();
        foreach ($defs as $id => $def) {
            if ($def['component'] == 'qtype_coderunner' && $def['area'] == 'coderunner_grading_cache') {
                $definition = cache_definition::load($id, $def);
                return $definition;
            }
        }
        return null;  // Probably should raise an exception here...
    }


    public function get_first_file_store(cache_definition $definition) {
        $stores = cache_helper::get_cache_stores($definition);
        // Should really only be one file store but go through them if needed...
        foreach ($stores as $store) {
            if ($store instanceof cachestore_file) {
                return $store;
            }
        }
        return null;  // whoops!
    }




    public function purge_cache_for_context(int $contextid, int $usettl) {
        global $OUTPUT;
        global $CFG;
        // Load the necessary data.
        $context = context::instance_by_id($contextid);
        $coursename = $context->get_context_name(true, true);
        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = $context->instanceid;
        } else {
            echo 'Nothing to do as context_id $contextid is not a course.';
            return;
        }
        //echo $OUTPUT->heading("Purging cache for course " . $courseid, 4);
        $definition = self::get_coderunner_cache_definition();
        $ttl = $definition->get_ttl();
        $days = round($ttl / 60 / 60 / 24, 4);

        if ($usettl) {
            echo "<p>Purging only old keys for course, based on Time to Live. TTL=";
            echo "$ttl seconds (= $days days)</p>";
        } else {
            echo "<p>Purging all keys for course, regardless of Time to Live (TTL).</p>";
        }
        $store = $this->get_first_file_store($definition);
        // $store->purge_old_entries();

        // Use reflection to access the private cachestore_file method file_path_for_key
        $reflection = new \ReflectionClass($store);
        $filepathmethod = $reflection->getMethod('file_path_for_key');
        $filepathmethod->setAccessible(true);

        $keys = $store->find_all();
        $originalcount = count($keys);

        // Delete all keys for course if usettl is false otherwise only old ones.
        $maxtime = cache::now() - $ttl;
        $onepercent = round($originalcount / 100, 0);
        $numdeleted = 0;
        $tooyoungtodie = 0;
        $keysforcourse = 0;
        $numprocessed = 0;
        if ($originalcount > 0){
            $progressbar = new \progress_bar('cache_purge_progress_bar', width:800, autostart:true);
        }
        foreach ($keys as $key) {
            $numprocessed += 1;
            // Call the private file_path_for_key method on the cache store.
            $path = $filepathmethod->invoke($store, $key);
            $pattern = '/_courseid_' . $courseid . '_/';
            $file = basename($path);
            if (preg_match($pattern, $file)) {
                $keysforcourse += 1;
                if (!$usettl) {
                    $store->delete($key);
                    $numdeleted += 1;
                } else {
                    $filetime = filemtime($path);
                    if ($ttl && $filetime < $maxtime) {
                            $store->delete($key);
                            $numdeleted += 1;
                    } else {
                        $tooyoungtodie += 1;
                    }
                }
            // $value = $store->get($key);  // Would delete old key if fixed in file store.
            }
            if ($originalcount > 0 && ($originalcount < 100  || $numprocessed % $onepercent == 0)){
                $progressbar->update($numprocessed,
                                    $originalcount,
                                    "$numprocessed / $originalcount"
                                    //get_string('regradingattemptxofywithdetails', //'quiz_overview', [$numprocessed, $originalcount])
                                    );
            }
        }
        // Make sure it gets to 100%
        if ($originalcount > 0) {
            $progressbar->update($numprocessed,
                                 $originalcount,
                                 "$numprocessed / $originalcount",
                                //get_string('regradingattemptxofywithdetails', //'quiz_overview', [$numprocessed, $originalcount])
                                );
        }
        echo "$originalcount keys scanned, in total. <br>";
        echo "$keysforcourse keys found for course. ";
        echo "$numdeleted keys purged for course.<br>";
        echo "$tooyoungtodie keys were too young to die.<br>";
    }


}
