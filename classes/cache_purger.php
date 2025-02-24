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
 * This script provides a class with support methods for purging grading cache entries.
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

defined('MOODLE_INTERNAL') || die();


class cache_purger {
    /** @var int Jobe server host name. */
    public $contextid;

    /** @var bool Whether or not to purge based on Time To Live (TTL) */
    public $usettl;

    /** @var int Coderunner Time To Live (TTL) in seconds */
    public $ttl;

    /** @var cache_definition The Coderunner cache definition */
    private $definition;

    /** @var cache_store The actual file store used for the Coderunner cache */
    private $store;

    /** @var reflection Used to gain access to the private filepath method of the cache store */
    private $reflection;

    /** @var method Used to access the filepath method of the cache store */
    private $filepathmethod;

    /** @var list The list of all keys in the cache store */
    public $keys;

    /** @var int The total number of keys in the cache store */
    public $originalcount;

    /** @var int Baically now less the TTL, ie, if a file is before this time then it is too old */
    public $maxtime;

    /** @var int Roughly one percent of the total number of keys - so we can update the progress every one percent only */
    public $onepercent;

    /** @var int The number of keys that were deleted when purging */
    private $numdeleted;

    /** @var int The number of keys that were too young to die */
    private $tooyoungtodie;

    /** The number of keys in the course/context */
    private $keysforcourse;

    /** The number of keys that have been processed during a purge */
    private $numprocessed;


    public function __construct(int $contextid, bool $usettl) {
        global $CFG;
        $this->contextid = $contextid;
        $this->usettl = $usettl;
        $this->ttl = abs(get_config('qtype_coderunner', 'gradecachettl'));  // Correct for any crazy negative TTL's.
        $this->definition = self::get_coderunner_cache_definition();
        $this->store = self::get_first_file_store($this->definition);

        // Use reflection to access the private cachestore_file method file_path_for_key.
        $this->reflection = new \ReflectionClass($this->store);
        $this->filepathmethod = $this->reflection->getMethod('file_path_for_key');
        $this->filepathmethod->setAccessible(true);

        $this->keys = $this->store->find_all();
        $this->originalcount = count($this->keys);
        $this->maxtime = cache::now() - $this->ttl;
        $this->onepercent = round($this->originalcount / 100, 0);
        $this->numdeleted = 0;
        $this->tooyoungtodie = 0;
        $this->keysforcourse = 0;
        $this->numprocessed = 0;
    }

    /**
     * Get all the visible course contexts.
     *
     * @return array context ids
     */
    public static function get_all_visible_course_contextids() {
        global $DB;

        $allcontexts = $DB->get_records_sql("
            SELECT ctx.id as contextid
              FROM {context} ctx
              ORDER BY contextid");
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

    /**
     * Get all the visible course contexts.
     *
     * @return array context ids
     */
    public static function get_all_visible_course_and_coursecat_contextids() {
        global $DB;
        $query = "SELECT ctx.id as contextid
              FROM {context} ctx
              WHERE contextlevel IN (:course, :coursecat)
              ORDER BY contextid";
        $params = [
            'course' => CONTEXT_COURSE,
            'coursecat' => CONTEXT_COURSECAT,
        ];
        $allcontexts = $DB->get_records_sql($query, $params);
        $result = [];
        foreach ($allcontexts as $record) {
            $contextid = $record->contextid;
            $context = context::instance_by_id($contextid);
            if (has_capability('moodle/question:editall', $context)) {
                $result[] = $contextid;
            }
        } // endfor each contextid
        return $result;
    }


    /**
     * Get count of keys for each course/context.
     * @param array $contextids A list of the context ids.
     * @return array mapping contextids to counts of keys.
     */
    public static function key_count_by_context(array $contextids) {
        $contextcounts = [];
        foreach ($contextids as $contextid) {
            $contextcounts[$contextid] = 0;
        }
        $definition = self::get_coderunner_cache_definition();
        $store = self::get_first_file_store($definition);
        $keys = $store->find_all();
        $pattern = '/___contextid_(\d+)___/';
        // Go through all keys and count by context...
        foreach ($keys as $key) {
            $found = preg_match($pattern, $key, $match);
            if ($found) {
                $contextid = (int)$match[1];
                // Just in case a weird context id came through, ignore it.
                if (array_key_exists($contextid, $contextcounts)) {
                    $contextcounts[$contextid] += 1;
                }
            } // Not found so ignore.
        }
        return $contextcounts;
    }

    /**
     * Get count of keys for all cache categories.
     * Has to scan all cache keys!
     * @return array mapping cachecategories to counts of keys.
     */
    public static function key_counts_for_all_cachecategories() {
        $categorycounts = [];
        $categorycounts['unknown'] = 0;
        $definition = self::get_coderunner_cache_definition();
        $store = self::get_first_file_store($definition);
        $keys = $store->find_all();
        $pattern = '/___([a-zA-Z0-9_]+)___/';
        foreach ($keys as $key) {
            $found = preg_match($pattern, $key, $match);
            if ($found) {
                $categoryid = $match[1];
                if (array_key_exists($categoryid, $categorycounts)) {
                    $categorycounts[$categoryid] += 1;
                } else {
                    $categorycounts[$categoryid] = 1;
                }
            } else { // Strange key without category!
                $categorycounts['unknown'] += 1;
            }
        }
        return $categorycounts;
    }

    /**
     * Get count of keys for each course/context.
     * @param array $contextids A list of the context ids that should all be for courses.
     * @return array mapping contextids to counts of keys.
     */
    public static function key_count_by_course(array $contextids) {
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
                ; // Ignore non-course contexts, which we shouldn't get anyway...
            }
        }
        $definition = self::get_coderunner_cache_definition();
        $store = self::get_first_file_store($definition);
        $keys = $store->find_all();
        $pattern = '/___courseid_(\d+)___/';
        foreach ($keys as $key) {
            $found = preg_match($pattern, $key, $match);
            if ($found) {
                $courseid = $match[1];
                if (array_key_exists($courseid, $coursetocontext)) {
                    $contextid = $coursetocontext[$courseid];
                    $contextcounts[$contextid] += 1;
                }
            } // Not found so ignore.
        }
        // Go through all keys and count by context...
        return $contextcounts;
    }


    public static function get_coderunner_cache_definition() {
        $configerer = \cache_config::instance();
        $defs = $configerer->get_definitions();
        foreach ($defs as $id => $def) {
            if ($def['component'] == 'qtype_coderunner' && $def['area'] == 'coderunner_grading_cache') {
                $definition = cache_definition::load($id, $def);
                return $definition;
            }
        }
        $error = get_string(
            'gradingcachedefintionnotfound',
            'qtype_coderunner'
        );
        throw new Exception($error);
    }


    public static function get_first_file_store(cache_definition $definition) {
        $stores = cache_helper::get_cache_stores($definition);
        // Should really only be one file store but go through them if needed...
        foreach ($stores as $store) {
            if ($store instanceof cachestore_file) {
                return $store;
            }
        }
        $error = get_string(
            'gradingcachefilestorenotfound',
            'qtype_coderunner'
        );
        throw new Exception($error);
    }


    public function purge_cache_for_context() {
        global $OUTPUT;
        global $CFG;
        $context = context::instance_by_id($this->contextid);
        $coursename = $context->get_context_name(true, true);
        // if ($context->contextlevel == CONTEXT_COURSE) {
        //     $courseid = $context->instanceid;
        // } else {
        //     // Nothing to do - can only run for courses.
        //     echo get_string('contextidnotacourseincachepurgerequest', 'qtype_coderunner', $this->contextid);
        //     return;
        // }

        $this->display_ttl_info();


        // Delete all keys for course if usettl is false otherwise only old ones.
        if ($this->originalcount > 0) {
            $progressbar = new \progress_bar('cache_purge_progress_bar', width:800, autostart:true);
        }
        $pattern = '/___contextid_' . $this->contextid . '___/';
        foreach ($this->keys as $key) {
            $this->numprocessed += 1;
            // Call the private file_path_for_key method on the cache store.
            $path = $this->filepathmethod->invoke($this->store, $key);
            $file = basename($path);
            if (preg_match($pattern, $file)) {
                $this->keysforcourse += 1;
                if (!$this->usettl) {
                    $this->store->delete($key);
                    $this->numdeleted += 1;
                } else {
                    $filetime = filemtime($path);
                    if ($this->ttl && $filetime < $this->maxtime) {
                            $this->store->delete($key);
                            $this->numdeleted += 1;
                    } else {
                        $this->tooyoungtodie += 1;
                    }
                }
            // Could have used $value = $store->get($key) to delete to delete old key if TTL exceeded, if this worked in file store.
            }
            if (
                $this->originalcount > 0 && ($this->originalcount < 100  ||
                $this->numprocessed % $this->onepercent == 0)
            ) {
                $progressstring = get_string(
                    'cachepurgecheckingkeyxoftotalnum',
                    'qtype_coderunner',
                    ['x' => $this->numprocessed, 'totalnumkeys' => $this->originalcount]
                );
                $progressbar->update($this->numprocessed, $this->originalcount, $progressstring);
            }
        }

        // Make sure progress bar gets to 100%.
        if ($this->originalcount > 0) {
            $progressstring = get_string(
                'cachepurgecheckingkeyxoftotalnum',
                'qtype_coderunner',
                ['x' => $this->numprocessed, 'totalnumkeys' => $this->originalcount]
            );
            $progressbar->update($this->numprocessed, $this->originalcount, $progressstring);
        }
        echo "$this->originalcount keys scanned, in total. <br>";
        echo "$this->keysforcourse keys found for course.<br>";
        echo "<b>$this->numdeleted</b> keys purged for course.<br>";
        echo "$this->tooyoungtodie keys were too young to die.<br>";
    }


    private function display_ttl_info() {
        global $OUTPUT;
        $ttldays = round($this->ttl / 60 / 60 / 24, 4);
        if ($this->usettl) {
            $message = get_string('purgingoldkeysmessage', 'qtype_coderunner', ['seconds' => $this->ttl, 'days' => $ttldays]);
        } else {
            $message = get_string('purgingallkeysmessage', 'qtype_coderunner');
        }
        echo \html_writer::tag('p', $message);
    }
}
