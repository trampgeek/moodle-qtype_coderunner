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
 * @copyright 2024-5 Paul McKeown, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

use cache;
use cache_helper;
use cachestore_file;
use cache_definition;
use context;
use context_module;
use context_course;
use moodle_url;
use exception;
use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_edit_contexts;


class cache_purger {
    /** @var bool Whether or not to purge based on Time To Live (TTL) */
    public $usettl;

    /** @var int Coderunner Time To Live (TTL) in seconds */
    public $ttl;

    /** @var cache_definition The Coderunner cache definition */
    private $definition;


    public function __construct(bool $usettl) {
        global $CFG;
        $this->usettl = $usettl;
        // OLD method $this->ttl = abs(get_config('qtype_coderunner', 'gradecachettl'));  // Correct for any crazy negative TTL's.
        $this->definition = self::get_coderunner_cache_definition();
        $this->ttl = abs($this->definition->get_ttl()); // Correct for any crazy negative TTL's.
    }

    /**
     * Get all the visible course contexts.
     * Visible means courses that current user can editall.
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
            $context = context::instance_by_id($contextid, IGNORE_MISSING);
            if ($context) {
                if (has_capability('moodle/question:editall', $context)) {
                    // Only add in courses for now.
                    if ($context->contextlevel == CONTEXT_COURSE) {
                        $result[] = $contextid;
                    }
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
        $query = "SELECT ctx.id as contextid, contextlevel
              FROM {context} ctx
              WHERE contextlevel IN (:course, :coursecat, :module)
              ORDER BY contextid";
        $params = [
            'course' => CONTEXT_COURSE,
            'coursecat' => CONTEXT_COURSECAT,
            'module' => CONTEXT_MODULE,
        ];
        $allcontexts = $DB->get_records_sql($query, $params);
        $result = [];
        foreach ($allcontexts as $record) {
            $contextid = $record->contextid;
            $contextlevel = $record->contextlevel;
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
        $stores = self::get_all_stores($definition);
        if (count($stores < 0)) {
            $store = $stores[0];  // Use first store as all should have same contents.
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
        }
        return $contextcounts;
    }

    /**
     * Get count of keys for all cache categories.
     * Usually categories are set to indicate the context id for the question result being cached.
     * The categories aren't question bank categories.
     * -----------> This works for file stores and maybe for Redis stores??? to be tested <-----------
     * Has to scan all cache keys!
     * @return array mapping cachecategories to counts of keys.
     */
    public static function key_counts_for_all_cachecategories() {
        $categorycounts = [];
        $categorycounts['unknown'] = 0;
        $definition = self::get_coderunner_cache_definition();
        $stores = self::get_all_stores($definition);
        if (count($stores) > 0) {
            $store = $stores[0];  // Keys should be same in all stores...
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
                } else { // Strange key without category! This shouldn't happen...
                    $categorycounts['unknown'] += 1;
                }
            }
        }
        return $categorycounts;
    }



    /**
     * Get count of keys for each editable context.
     *
     * @param array $categorycounts An array containing category, count pairs.
     * where the category is the string that is used in the cache key suffix.
     * @return array mapping contextids to counts of keys. Sorted in reverse order by contextid.
     */
    public static function key_counts_for_available_contextids() {
        $categorycounts = self::key_counts_for_all_cachecategories();
        $keycounts = [];
        foreach ($categorycounts as $category => $count) {
            if (preg_match('/contextid_(\d+)/', $category, $match)) {
                $contextid = (int)$match[1];
                // Deleted courses/contexts will get a null.
                // Could make it so anyone can remove the cache for such contexts?
                // That is report them with (deleted) suffix or such.
                $context = context::instance_by_id($contextid, IGNORE_MISSING);
                if ($context) {
                    if (has_capability('moodle/question:editall', $context)) {
                        $keycounts[$contextid] = $count;
                    }
                }
            }
        }
        krsort($keycounts);  // Effectively in order from newest to oldest.
        return $keycounts;
    }


    /**
     * Get mapping from key contextid to the contextid of containing course.
     * This allows us to quickly check if the context in a cache key
     * corresponds to a course - used when clearing cache for all contexts
     * in a course
     *
     * @return array mapping contextids to coursecontextids.
     */
    public static function get_qbank_context_to_course_context_map() {
        $contextidtocoursecontextid = [];
        $allcaps = array_merge(question_edit_contexts::$caps['editq'], question_edit_contexts::$caps['categories']);
        $allcourses = bulk_tester::get_all_courses();
        $coursebanks = [];
        foreach ($allcourses as $courseid => $course) {
            $coursecontext = context_course::instance($courseid);
            echo $coursecontext->id . '; ';
            // Shared banks will be activites of type qbank.
            $sharedbanks = question_bank_helper::get_activity_instances_with_shareable_questions([$course->id], [], $allcaps);
            // Private banks are actually just other modules that can contain questions, eg, quizzes.
            $privatebanks = question_bank_helper::get_activity_instances_with_private_questions([$course->id], [], $allcaps);
            $allbanks = array_merge($sharedbanks, $privatebanks);
            if (count($allbanks) > 0) {
                foreach ($allbanks as $bank) {
                    $contextid = $bank->contextid;
                    $contextidtocoursecontextid[$contextid] = $coursecontext->id;
                }
            }
        }
        return $contextidtocoursecontextid;
    }

    /**
     * Used for inverting and array.
     * Used to invert the contextid -> coursecontextid array
     * to give coursecontextid -> list of contextids array.
     * @param array Associative array.
     * @return array Maps from original values to lists of keys associated with them.
     */
    public static function invert_array($array) {
        $result = [];
        foreach ($array as $key => $value) {
            if (!isset($result[$value])) {
                $result[$value] = [$key];
            } else {
                $result[$value][] = $key;
            }
        }
        return $result;
    }



    /** NOTE: THIS IS OLD - FROM WHEN QUESTIONS WERE IN COURSE CONTEXTS
     * Get count of keys for each course context.
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
        throw new \Exception($error);
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
        throw new \Exception($error);
    }


    /**
     * Returns a list of cache file stores (ie, cachestore_file objects)
     * for the given cache definition.
     */
    public static function get_file_stores(cache_definition $definition) {
        $stores = cache_helper::get_cache_stores($definition);
        $filestores = [];
        // Should really only be one file store but go through them if needed...
        foreach ($stores as $store) {
            if ($store instanceof cachestore_file) {
                $filestores[] = $store;
            }
        }
        return $filestores;
    }


    /**
     * Returns a list of all cache stores
     * for the given cache definition.
     */
    public static function get_all_stores(cache_definition $definition) {
        $stores = cache_helper::get_cache_stores($definition);
        return $stores;
    }


    /**
     * Delete all keys for context if this->usettl is false otherwise only old ones.
     * NOTE: If the cache isn't a file store then purging will only work if this->usettl is false.
     * @param int $contextid int must be a valid context id.
     * @param bool $quiet If quiet then no messages are echoed.  This is helpful for the bulktester.
     */
    public function purge_cache_for_context(int $contextid, $quiet = false) {
        global $OUTPUT;
        global $CFG;
        $definition = self::get_coderunner_cache_definition();
        $stores = self::get_all_stores($definition);
        $context = context::instance_by_id($contextid);
        $contextname = $context->get_context_name(true, true);
        if (!$quiet) {
            $this->display_ttl_info();
        }

        // Connect to cache as a whole so that deletes.
        // get done to all stores at once.
        $cache = cache::make('qtype_coderunner', 'coderunner_grading_cache');

        $originalcount = 0;
        $maxtime = cache::now() - $this->ttl;
        $numdeleted = 0;
        $tooyoungtodie = 0;
        $keysforcontext = 0;
        $numprocessed = 0;

        $pattern = '/___contextid_' . $contextid . '___/';

        foreach ($stores as $store) {
            if ($store instanceof cachestore_file) {
                // Use reflection to access the private cachestore_file method file_path_for_key.
                $reflection = new \ReflectionClass($store);
                $filepathmethod = $reflection->getMethod('file_path_for_key');
                $filepathmethod->setAccessible(true);
            }
            $isfilestore = $store instanceof cachestore_file;
            $fullcachekeys = $store->find_all();
            $originalcount += count($fullcachekeys);
            $onepercent = round($originalcount / 100, 0);
            if (!$isfilestore && $this->usettl) {
                echo "<p>Sorry. Will not do TTL purging for non file stores.</p>";
                echo "<p>If you're using a Redis store use the Redis scheduled task for cleanup. Or don't use TTL setting.</p>";
            } else {
                if ($originalcount > 0 && !$quiet) {
                    $progressbar = new \progress_bar('cache_purge_progress_bar', width:800, autostart:true);
                }
                foreach ($fullcachekeys as $fullcachekey) {
                    // Find_all doesn't give real keys, it includes Moodles extra hash after a - at the end
                    // so just keep the real key.
                    $bits = explode("-", $fullcachekey);
                    $key = $bits[0];  // The actual key.
                    $numprocessed += 1;
                    if (preg_match($pattern, $key)) {   // NOTE: used to use $file????
                        $keysforcontext += 1;
                        if (!$this->usettl) {
                            $cache->delete($key);
                            $numdeleted += 1;
                        } else {
                            // Currently do TTL stuff for file stores only.
                            if ($isfilestore) {
                                // Could have used $value = $store->get($key) to delete to delete old
                                // key if TTL exceeded, if this worked in file store. It doesn't
                                // work there as filestores only delete keys that are past TTL
                                // if prescan is true but prescan is set to false for file caches
                                // if they are not being used for requests. See the initialise method
                                // in the cache/stores/file/lib.php module.

                                // Call the private file_path_for_key method on the cache store.
                                $path = $filepathmethod->invoke($store, $fullcachekey);
                                $file = basename($path);
                                $filetime = filemtime($path);
                                if ($filetime < $maxtime) {
                                        $cache->delete($key);
                                        $numdeleted += 1;
                                } else {
                                    $tooyoungtodie += 1;
                                }
                            }
                        }
                    }
                    if (
                          !$quiet &&
                        ($originalcount > 0 && ($originalcount < 100  ||
                        $numprocessed % $onepercent == 0))
                    ) {
                        $progressstring = get_string(
                            'cachepurgecheckingkeyxoftotalnum',
                            'qtype_coderunner',
                            ['x' => $numprocessed, 'totalnumkeys' => $originalcount]
                        );
                        $progressbar->update($numprocessed, $originalcount, $progressstring);
                    }
                }
            }
        }

        if (!$quiet) {
             // Make sure progress bar gets to 100%.
            if ($originalcount > 0) {
                $progressstring = get_string(
                    'cachepurgecheckingkeyxoftotalnum',
                    'qtype_coderunner',
                    ['x' => $numprocessed, 'totalnumkeys' => $originalcount]
                );
                $progressbar->update($numprocessed, $originalcount, $progressstring);
            }
            echo "$originalcount keys scanned, in total. <br>";
            echo "$keysforcontext keys found for context id $contextid.<br>";
            echo "<b>$numdeleted</b> keys purged for course.<br>";
            echo "$tooyoungtodie keys were too young to die.<br>";
        }
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
