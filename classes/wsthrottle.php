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

/*
 * A utility class for use by the external web service to throttle users
 * to a configuration-dependent rate of runs per hour.
 *
 * @package    qtype_coderunner
 * @category   qtype_coderunner
 * @copyright  2023 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Class to manage the throttling of an individual webservice user to the rate given
 * in the wsmaxhourlyrate config parameter.
 */
class qtype_coderunner_wsthrottle {
    private $timestamps;
    private $maxhourlyrate;
    private $head;
    private $tail;

    public function __construct() {
        $this->init();
    }

    private function init() {
        $this->maxhourlyrate = intval(get_config('qtype_coderunner', 'wsmaxhourlyrate'));
        $this->timestamps = array_fill(0, $this->maxhourlyrate, 0);
        $this->head = $this->tail = 0; // Head and tail indices for circular list.
    }
    /**
     * Add a log entry to the circular list of timestamps, clearing any
     * expired entries (i.e. entries more than 1 hour ago).
     * Return true if logging succeeds, false if user has reached their limit.
     */
    public function logrunok() {
        if (intval(get_config('qtype_coderunner', 'wsmaxhourlyrate')) != $this->maxhourlyrate) {
            // Rate has been changed. Restart throttle.
            $this->init();
        }
        $now = strtotime('now');

        // Purge any non-zero entries older than 1 hour.
        while ($this->expired($this->timestamps[$this->tail], $now)) {
            $this->timestamps[$this->tail] = 0;
            $this->tail = ($this->tail + 1) % $this->maxhourlyrate;
        }
        if ($this->timestamps[$this->head] == 0) { // Empty entry available?
            $this->timestamps[$this->head] = $now;
            $this->head = ($this->head + 1) % $this->maxhourlyrate;
            return true;
        } else {
            // List of timestamps is full. Need to throttle user.
            return false;
        }
    }

    /**
     *
     * @param int $timestamp the timestamp of interest
     * @param int $now current timestamp
     * @return bool true if the timestamp is non-zero and older than 1 hour
     */
    private function expired($timestamp, $now) {
        return ($timestamp !== 0) && ($now - $timestamp) > 3600;
    }
}
