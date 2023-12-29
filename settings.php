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
 * Configuration settings declaration information for the CodeRunner question type.
 *
 * @package    qtype_coderunner
 * @copyright  2014 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
use qtype_coderunner\constants;

$links = [
    get_string(
        'bulkquestiontester',
        'qtype_coderunner',
        ['link' => (string) new moodle_url('/question/type/coderunner/bulktestindex.php')]
    ),
];

$settings->add(new admin_setting_heading(
    'supportscripts',
    get_string('supportscripts', 'qtype_coderunner'),
    '* ' . implode("\n* ", $links)
));

$settings->add(new admin_setting_heading(
    'codeRunnersettings',
    get_string('coderunnersettings', 'qtype_coderunner'),
    ''
));

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/default_penalty_regime",
    get_string('default_penalty_regime', 'qtype_coderunner'),
    get_string('default_penalty_regime_desc', 'qtype_coderunner'),
    '10, 20, ...'
));

$sandboxes = qtype_coderunner_sandbox::available_sandboxes();
foreach ($sandboxes as $sandbox => $classname) {
    $settings->add(new admin_setting_configcheckbox(
        "qtype_coderunner/{$sandbox}_enabled",
        get_string('enable', 'qtype_coderunner') . ' ' . $sandbox,
        get_string('enable_sandbox_desc', 'qtype_coderunner'),
        $sandbox === 'jobesandbox'
    ));  // Only jobesandbox is enabled by default.
}

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/jobe_host",
    get_string('jobe_host', 'qtype_coderunner'),
    get_string('jobe_host_desc', 'qtype_coderunner'),
    constants::JOBE_HOST_DEFAULT,
    PARAM_RAW,
    60
));

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/jobe_apikey",
    get_string('jobe_apikey', 'qtype_coderunner'),
    get_string('jobe_apikey_desc', 'qtype_coderunner'),
    constants::JOBE_HOST_DEFAULT_API_KEY
));

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/ideone_user",
    get_string('ideone_user', 'qtype_coderunner'),
    get_string('ideone_user_desc', 'qtype_coderunner'),
    ''
));

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/ideone_password",
    get_string('ideone_pass', 'qtype_coderunner'),
    get_string('ideone_pass_desc', 'qtype_coderunner'),
    ''
));

$settings->add(new admin_setting_heading(
    'codeRunnerwssettings',
    get_string('coderunnerwssettings', 'qtype_coderunner'),
    ''
));

$settings->add(new admin_setting_configcheckbox(
    "qtype_coderunner/wsenabled",
    get_string('enable_sandbox_ws', 'qtype_coderunner'),
    get_string('enable_sandbox_ws_desc', 'qtype_coderunner'),
    false
));

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/wsjobeserver",
    get_string('jobe_host_ws', 'qtype_coderunner'),
    get_string('jobe_host_ws_desc', 'qtype_coderunner'),
    '',
    PARAM_RAW,
    60
));

$settings->add(new admin_setting_configcheckbox(
    "qtype_coderunner/wsloggingenabled",
    get_string('wsloggingenable', 'qtype_coderunner'),
    get_string('wsloggingenable_desc', 'qtype_coderunner'),
    true
));

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/wsmaxhourlyrate",
    get_string('wsmaxhourlyrate', 'qtype_coderunner'),
    get_string('wsmaxhourlyrate_desc', 'qtype_coderunner'),
    '200'
));

$settings->add(new admin_setting_configtext(
    "qtype_coderunner/wsmaxcputime",
    get_string('wsmaxcputime', 'qtype_coderunner'),
    get_string('wsmaxcputime_desc', 'qtype_coderunner'),
    '5'
));
