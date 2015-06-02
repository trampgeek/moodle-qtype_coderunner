<?php

/**
 * Set-up config variables for running tests.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

set_config('runguardsandbox_enabled', 1, 'qtype_coderunner');
set_config('liusandbox_enabled', 1, 'qtype_coderunner');
set_config('ideonesandbox_enabled', 0, 'qtype_coderunner');
set_config('jobesandbox_enabled', 1, 'qtype_coderunner');
set_config('jobe_host', 'localhost', 'qtype_coderunner');
set_config('jobe_apikey', 'test-api-key', 'qtype_coderunner');
set_config('ideone_user', 'coderunner', 'qtype_coderunner');
set_config('ideone_password', 'moodlequizzes', 'qtype_coderunner');

