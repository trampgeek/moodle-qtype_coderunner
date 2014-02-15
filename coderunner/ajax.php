<?php

/*
 * AJAX script to return a JSON-encoded row of the options for the specified
 * question type by looking up the prototype in the quest_coderunner_options
 * table. Fields 'success' and 'error' are added for validation checking by
 * the caller.
 */

define('AJAX_SCRIPT', true);

require_once('../../../config.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

require_login();
require_sesskey();

$qtype  = required_param('qtype', PARAM_ALPHANUMEXT);

header('Content-type: application/json; charset=utf-8');
try {
    $questionType = $DB->get_record_select(
            'quest_coderunner_options',
            "prototype_type != 0 and coderunner_type = '$qtype'",
            NULL,
            '*',
            MUST_EXIST);
    $questionType->success = true;
    $questionType->error = '';
} catch (moodle_exception $e) {
    $questionType = new stdClass();
    $questionType->success = false;
    $questionType->error = "Database error or question type not found";
}
echo json_encode($questionType);
die();
?>
