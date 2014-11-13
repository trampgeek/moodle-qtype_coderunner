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

define("TEMPLATE_LANGUAGE", 0);
define("USER_LANGUAGE", 1);

/*
 * Configure the ace editor for use with the given textarea (specified by its
 * id) if question is set to use Ace. Language is specified either as
 * TEMPLATE_LANGUAGE (the language used in the sandbox) or USER_LANGUAGE.
 * They are the same unless a different language has been explicitly specified
 * by the ace_lang field in the question authoring form,in which case the
 * ace_lang field is used for the USER and the $question->language for the
 * template.
 */
function load_ace_if_required($question, $textareaid, $which_lang) {
    global $CFG, $PAGE;
    if ($question->use_ace) {
        if ($which_lang === TEMPLATE_LANGUAGE ||
               empty($question->ace_lang)) {
            $lang = $question->language;
        } else {
            $lang = $question->ace_lang;
        }
        $lang = ucwords($lang);
        if ($lang == 'OCTAVE') {
            $lang = 'MATLAB';
        }
        
        load_ace_scripts();
        $PAGE->requires->js_init_call('M.qtype_coderunner.init_ace', array($textareaid, $lang));
    }
}


// Load the ace scripts
function load_ace_scripts() {
    global $PAGE;
    $plugindirrel = '/question/type/coderunner';
    $PAGE->requires->js($plugindirrel . '/ace/ace.js');
    $PAGE->requires->js($plugindirrel . '/ace/ext-language_tools.js');
    $PAGE->requires->js($plugindirrel . '/ace/ext-modelist.js');
}
