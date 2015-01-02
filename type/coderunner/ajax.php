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
$courseid = required_param('courseid', PARAM_INT);

header('Content-type: application/json; charset=utf-8');
try {
    $coursecontext = context_course::instance($courseid);
    $questiontype = qtype_coderunner::get_prototype($qtype, $coursecontext);
    $questiontype->success = true;
    $questiontype->error = '';
} catch (moodle_exception $e) {
    $questiontype = new stdClass();
    $questiontype->success = false;
    $questiontype->error = "Prototype not found. " . $e->getMessage();
}
echo json_encode($questiontype);
die();
?>
