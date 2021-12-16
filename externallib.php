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
                'sourcecode' => new external_value(PARAM_RAW, 'The source code to be run', VALUE_REQUIRED),
                'language' => new external_value(PARAM_TEXT, 'The computer language of the sourcecode', VALUE_REQUIRED),
                'stdin' => new external_value(PARAM_RAW, 'The standard input to use for the run', VALUE_REQUIRED)
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
     * @return string welcome message
     */
    public static function run_in_sandbox($sourcecode, $language, $stdin) {
        // Parameters validation.
        $params = self::validate_parameters(self::run_in_sandbox_parameters(),
                array('sourcecode' => $sourcecode,
                      'language' => $language,
                      'stdin' => $stdin));
        $sandbox = qtype_coderunner_sandbox::get_best_sandbox($language);
        if ($sandbox === null) {
            throw new qtype_coderunner_exception("Language {$language} is not available on this system");
        }
        try {
            $runresult = $sandbox->execute($sourcecode, $language, $stdin);
        } catch (Exception $ex) {
            throw new qtype_coderunner_exception("Attempt to run job failed with error {$ex->message}");
        }
        return json_encode($runresult);
    }
}
