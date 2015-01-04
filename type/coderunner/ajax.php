<?php

/*
 * AJAX script to return a JSON-encoded row of the options for the specified
 * question type by looking up the prototype in the question_coderunner_options
 * table. Fields 'success' and 'error' are added for validation checking by
 * the caller.
 */

define('AJAX_SCRIPT', true);

$dir = dirname(__FILE__);

if (strpos($dir, 'local/CodeRunner/type/coderunner') !== false) {
    require_once('../../../../config.php'); // Symbolically linked rather than copied
} else {
    require_once('../../../config.php'); // "Normal" case of a copy of the code
}
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
    $questionType->error = "Prototype not found or not unique. " . $e->getMessage();
}
echo json_encode($questiontype);
die();
?>
