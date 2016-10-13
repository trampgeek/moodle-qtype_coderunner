<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library routines for qtype_coderunner
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/question/type/coderunner/constants.php');

use qtype_coderunner\constants;

/* The class for exceptions thrown in the coderunner plugin */
class coderunner_exception extends moodle_exception {
    /**
     * @param string $errorcode exception description identifier
     * @param mixed $debuginfo debugging data to display
     */
    public function __construct($errorcode, $a=null, $debuginfo=null) {
        parent::__construct($errorcode, 'qtype_coderunner', '', $a, $debuginfo);
    }
}


/*
 * Configure the ace editor for use with the given textarea (specified by its
 * id) if question is set to use Ace. Language is specified either as
 * TEMPLATE_LANGUAGE (the language used in the sandbox) or USER_LANGUAGE.
 * They are the same unless a different language has been explicitly specified
 * by the acelang field in the question authoring form, in which case the
 * acelang field is used for the USER and the $question->language for the
 * template.
 */
function load_ace_if_required($question, $textareaid, $whichlang) {
    global $CFG, $PAGE;
    if ($question->useace) {
        if ($whichlang === constants::TEMPLATE_LANGUAGE ||
               empty($question->acelang)) {
            $lang = $question->language;
        } else {
            $lang = $question->acelang;
        }
        $lang = ucwords($lang);
        load_ace();
        $PAGE->requires->js_call_amd('qtype_coderunner/aceinterface', 'initAce', array($textareaid, $lang));
    }
}


// Load the ace scripts.
function load_ace() {
    global $PAGE;
    $plugindirrel = '/question/type/coderunner';
    $PAGE->requires->js($plugindirrel . '/ace/ace.js');
    $PAGE->requires->js($plugindirrel . '/ace/ext-language_tools.js');
    $PAGE->requires->js($plugindirrel . '/ace/ext-modelist.js');
}
