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
 * Uses the "leaky bucket" algorithm (https://en.wikipedia.org/wiki/Leaky_bucket). This allows
 * a surge of run requests equal to the maxhourlyrate but thereafter the rate is limited
 * to maxhourlyrate / 3600 runs per second.
 * This class must be instantiated within the SESSION variable.
 */
class qtype_coderunner_wsthrottle {
    private $timestamps;
    private $maxhourlyrate;
    private $bucketlevel;
    private $timestamp;

    public function __construct() {
        $this->init();
    }

    private function init() {
        $this->maxhourlyrate = intval(get_config('qtype_coderunner', 'wsmaxhourlyrate'));
        $this->bucketlevel = 0; // Current level of virtual fluid in the bucket (runs in last hour).
        $this->timestamp = time(); // When the bucket level was last updated.
    }

    /**
     * Allow any drainage to occur from the bucket. Then, if it's still full,
     * disallow the run, otherwise allow it.
     * Return true if logging succeeds, false if user has reached their limit.
     */
    public function logrunok() {
        if (intval(get_config('qtype_coderunner', 'wsmaxhourlyrate')) != $this->maxhourlyrate) {
            // Rate has been changed. Restart throttle.
            $this->init();
        }

        // Allow any fluid to drain since the last time the level was computed.
        $now = time();
        $elapsedseconds = $now - $this->timestamp;
        $drainage = $this->maxhourlyrate * $elapsedseconds / (60 * 60); // Change in bucket level.
        $this->bucketlevel = max(0.0, $this->bucketlevel - $drainage);
        $this->timestamp = $now;

        // Now if there's enough space for 1 more run, allow it.
        if ($this->bucketlevel + 1 <= $this->maxhourlyrate) { // Enough space in bucket?
            $this->bucketlevel += 1;  // Yes, another quantum of fluid gets added.
            return true;
        } else {  // Not enough space.
            return false;
        }
    }
}