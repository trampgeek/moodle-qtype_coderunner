<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/question/behaviour/adaptive/renderer.php');

// Why do I have to provide this?

class qbehaviour_adaptive_adapted_for_coderunner_renderer extends qbehaviour_adaptive_renderer
{
}
