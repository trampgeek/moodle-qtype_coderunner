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
 * qtype_coderunner external file. Allows webservice access by authenticated
 * users to the sandbox server (usually Jobe).
 *
 * @package    qtype_coderunner
 * @category   external
 * @copyright  2021 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");


class qtype_coderunner_external extends external_api {

    /**
     * Returns description of method parameters. Used for validation.
     * @return external_function_parameters
     */
    public static function run_in_sandbox_parameters() {
        return new external_function_parameters(
            array(
                'sourcecode' => new external_value(PARAM_RAW,
                        'The source code to be run', PARAM_REQUIRED),
                'language' => new external_value(PARAM_TEXT,
                        'The computer language of the sourcecode', PARAM_REQUIRED, 'python3'),
                'stdin' => new external_value(PARAM_RAW,
                        'The standard input to use for the run', PARAM_REQUIRED, ''),
                'files' => new external_value(PARAM_RAW,
                        'A JSON object in which attributes are filenames and values file contents',
                        PARAM_DEFAULT, ''),
                'params' => new external_value(PARAM_RAW,
                        'A JSON object defining any sandbox parameters',
                        PARAM_DEFAULT, '')
            )
        );
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function run_in_sandbox_returns() {
        return new external_value(PARAM_RAW, 'The JSON-encoded Jobe server run result');
    }

    /**
     * Run a job in the sandbox (Jobe).
     * @param string $sourcecode The source code to be run.
     * @param string $language The language of execution (default python3)
     * @param string $stdin The standard input for the run (default empty)
     * @param string $files A JSON object in which attributes are filenames and
     * attribute values are the corresponding file contents.
     * @param string $params A JSON object defining any required Jobe sandbox
     * parameters (cputime, memorylimit etc).
     * @return string JSON-encoded Jobe run-result object.
     * @throws qtype_coderunner_exception
     */
    public static function run_in_sandbox($sourcecode, $language='python3', $stdin='', $files='', $params='') {
        // First, see if the web service is enabled.
        if (!get_config('qtype_coderunner', 'wsenabled')) {
            throw new qtype_coderunner_exception(get_string('wsdisabled', 'qtype_coderunner'));
        }
        // Now check if the user is logged in, and not a guest.
        if (!isloggedin() || isguestuser()) {
            throw new qtype_coderunner_exception(get_string('wsnoaccess', 'qtype_coderunner'));
        }
        // Parameters validation.
        self::validate_parameters(self::run_in_sandbox_parameters(),
                array('sourcecode' => $sourcecode,
                      'language' => $language,
                      'stdin' => $stdin,
                      'files' => $files,
                      'params' => $params
                    ));
        $sandbox = qtype_coderunner_sandbox::get_best_sandbox($language);
        if ($sandbox === null) {
            throw new qtype_coderunner_exception("Language {$language} is not available on this system");
        }

        if (get_config('qtype_coderunner', 'wsloggingenabled')) {
            $context = context_system::instance();
            $event = \qtype_coderunner\event\sandbox_webservice_exec::create([
                'contextid' => $context->id]);
            $event->trigger();
        }

        try {
            $filesarray = $files ? json_decode($files, true) : null;
            $paramsarray = $params ? json_decode($params, true) : array();
            $jobehostws = get_config('qtype_coderunner', 'wsjobeserver').trim();
            if ($jobehostws !== '') {
                $paramsarray['jobeserver'] = $jobehostws;
            }
            $runresult = $sandbox->execute($sourcecode, $language, $stdin, $filesarray, $paramsarray);
        } catch (Exception $ex) {
            throw new qtype_coderunner_exception("Attempt to run job failed with error {$ex->message}");
        }
        return json_encode($runresult);
    }
}
