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
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');;

require_login();
require_sesskey();

$qtype  = required_param('qtype', PARAM_ALPHANUMEXT);
$courseId = required_param('courseid', PARAM_INT);

header('Content-type: application/json; charset=utf-8');
try {
    $courseContext = context_course::instance($courseId);
    $questionType = qtype_coderunner::getPrototype($qtype, $courseContext);
    $questionType->success = true;
    $questionType->error = '';
} catch (moodle_exception $e) {
    $questionType = new stdClass();
    $questionType->success = false;
    $questionType->error = "Prototype not found or not unique. " . $e->getMessage();
}
echo json_encode($questionType);
die();
?>
