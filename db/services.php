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

// This file defines the sandbox service, which is available only to
// logged in users, i.e. where there is an active valid session. It allows
// AJAX requests to run code on the sandbox (Jobe) server. This is intended for
// use with a mini-IDE feature inside a CodeRunner question, where students can
// try out their code on the Jobe server prior to submitting it for grading.

// Based on https://docs.moodle.org/dev/Adding_a_web_service_to_a_plugin.

/**
 * List of Web Services for the qtype_coderunner plugin.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2021 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'qtype_coderunner_run_in_sandbox' => [
        'classname'   => 'qtype_coderunner\external\run_in_sandbox',
        'classpath'   => '',
        'methodname'  => 'execute', // Redundant but legacy Moodle versions need it.
        'description' => 'Runs a job on the Jobe sandbox server',
        'type'        => '', // No DB access allowed.
        'ajax'        => true, // The service is available to 'internal' ajax calls.
        'capabilities' => 'qtype/coderunner:sandboxwsaccess',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
