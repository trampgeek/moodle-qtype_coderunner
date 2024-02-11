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
 * AJAX script to return a JSON-encoded row of the options for the specified
 * question type by looking up the prototype in the question_coderunner_options
 * table. Fields 'success' and 'error' are added for validation checking by
 * the caller.
 *
 * Alternatively, if called with a parameter uiplugin rather than qtype, returns
 * a list describing the UI plugin parameters and their descriptions.
 *
 * @group qtype_coderunner
 * Assumed to be run after python questions have been tested, so focuses
 * only on C-specific aspects.
 *
 * @package    qtype_coderunner
 * @copyright  2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

require_login();
require_sesskey();

$courseid = required_param('courseid', PARAM_INT);
$qtype = optional_param('qtype', '', PARAM_RAW_TRIMMED);
$uiplugin = strtolower(optional_param('uiplugin', '', PARAM_RAW_TRIMMED));

header('Content-type: application/json; charset=utf-8');

$coursecontext = context_course::instance($courseid);
if ($qtype) {
    $questiontype = qtype_coderunner::get_prototype($qtype, $coursecontext);
    if ($questiontype === null || is_array($questiontype)) {
        $questionprototype = $questiontype;
        $questiontype = new stdClass();
        $questiontype->success = false;
        if ($questiontype === null) {
            $questiontype->error = json_encode(["error" => "missingprototype",
                "alert" => "prototype_missing_alert", "extras" => ""]);
        } else {
            $extras = "";
            foreach ($questionprototype as $component) {
                $extras .= get_string(
                    'listprototypeduplicates',
                    'qtype_coderunner',
                    ['id' => $component->id, 'name' => $component->name,
                    'category' => $component->category]
                );
            }
            $questiontype->error = json_encode(["error" => "duplicateprototype",
                "alert" => "prototype_duplicate_alert", "extras" => $extras]);
        }
    } else {
        $questiontype->success = true;
        $questiontype->error = '';
    }
    echo json_encode($questiontype);
} else if ($uiplugin) {
    $uiplugins = qtype_coderunner_ui_plugins::get_instance();
    $allnames = $uiplugins->all_names();
    $uiparamstable = [];
    $columnheaders = [];
    if (!in_array($uiplugin, $allnames)) {
        $uiheader = get_string('unknownuiplugin', 'qtype_coderunner', ['pluginname' => $uiplugin]);
    } else {
        $uiparams = $uiplugins->parameters($uiplugin);
        if ($uiparams->length() === 0) {
            $uiheader = get_string('nouiparameters', 'qtype_coderunner', ['uiname' => $uiplugin]);
        } else {
            $csv = implode(', ', $uiparams->all_names_starred());
            $uiheader = get_string(
                'uiparametertablehead',
                'qtype_coderunner',
                ['uiname' => $uiplugin]
            ) . $csv . '.';
            $uiparamstable = $uiparams->table();
            $namehdr = get_string('uiparamname', 'qtype_coderunner');
            $descrhdr = get_string('uiparamdesc', 'qtype_coderunner');
            $defaulthdr = get_string('uiparamdefault', 'qtype_coderunner');
            $columnheaders = [$namehdr, $descrhdr, $defaulthdr];
        }
    }
    echo json_encode(['header' => $uiheader,
        'uiparamstable' => $uiparamstable,
        'columnheaders' => $columnheaders,
        'showdetails' => get_string('showdetails', 'qtype_coderunner'),
        'hidedetails' => get_string('hidedetails', 'qtype_coderunner')]);
}
die();
