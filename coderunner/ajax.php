<?php

/*
 * AJAX script to return a JSON-encoded row from the coderunner question type
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
    $questionType = $DB->get_record('quest_coderunner_options',
            array('coderunner_type'=>$qtype, 'prototype_type' => 1),
            '*',
            MUST_EXIST);
    $questionType->success = true;
    $questionType->error = '';
} catch (moodle_exception $e) {
    $questionType->success = false;
    $questionType->error = "Database error or question type not found";
}
echo json_encode($questionType);
die();
?>
