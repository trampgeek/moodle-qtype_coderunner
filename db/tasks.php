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
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2024 Paul McKeown, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'qtype_coderunner\task\cache_cleaner',
        'blocking' => 0,
        'minute' => 'R', // Random minute.
        'hour' => '1',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '6', // 6=Saturday.
    ],
];
