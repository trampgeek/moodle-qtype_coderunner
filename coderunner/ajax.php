<?php

/*
 * AJAX script to return a JSON-encoded question type per_test_template.
 */

define('AJAX_SCRIPT', true);

require_once('../../../config.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

require_login();
require_sesskey();

$outcome = new stdClass();
$outcome->success = true;
$outcome->per_per_test_template = '';
$outcome->error='';

$qtype  = required_param('qtype', PARAM_ALPHANUMEXT);

header('Content-type: application/json; charset=utf-8');
try {
    $questionType = $DB->get_record('quest_coderunner_types', array('coderunner_type'=>$qtype), 'per_test_template', MUST_EXIST);
    $outcome->per_test_template = $questionType->per_test_template;
} catch (moodle_exception $e) {
    $outcome->success = false;
    $outcome->error = "Oops, we are dead, Fred"; // print_r($e);
}
echo json_encode($outcome);
die();
?>
