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


/* Function to limit the size of a string for browser display.
 * Restricts line length to MAX_LINE_LENGTH and number of lines to
 * MAX_NUM_LINES.
 */
function restrict_qty($s) {
    if (!is_string($s)) {  // It's a no-op for non-strings.
        return $s;
    }
    $result = '';
    $n = strlen($s);
    $line = '';
    $linelen = 0;
    $numlines = 0;
    for ($i = 0; $i < $n && $numlines < constants::MAX_NUM_LINES; $i++) {
        if ($s[$i] != "\n") {
            if ($linelen < constants::MAX_LINE_LENGTH) {
                $line .= $s[$i];
            } else if ($linelen == constants::MAX_LINE_LENGTH) {
                for ($j = 1; $j <= 3; $j++) {
                    $line[constants::MAX_LINE_LENGTH - $j] = '.'; // Insert '...'.
                }
            } // else { ...  ignore remainder of line ... }
            $linelen++;
        } else { // Newline.
            $result .= $line . "\n";
            $line = '';
            $linelen = 0;
            $numlines += 1;
            if ($numlines == constants::MAX_NUM_LINES) {
                $result .= "[... snip ...]\n";
            }
        }
    }
    return $result . $line;
}


// Return a copy of $s with trailing blank lines removed and trailing white
// space from each line removed. Also sanitised by replacing all control
// chars except newlines with hex equivalents.
// A newline terminator is added at the end unless the string to be
// returned is otherwise empty.
// Used e.g. by the equality grader subclass.
// This implementation is a bit algorithmically complex because the
// original implemention, breaking the string into lines using explode,
// was a hideous memory hog.
function clean(&$s) {
    $nls = '';     // Unused line breaks.
    $output = '';  // Output string.
    $spaces = '';  // Unused space characters.
    $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        $c = $s[$i];
        if ($c === ' ') {
            $spaces .= $c;
        } else if ($c === "\n") {
            $spaces = ''; // Discard spaces before a newline.
            $nls .= $c;
        } else {
            if ($c === "\r") {
                $c = '\\r';
            } else if ($c === "\t") {
                $c = '\\t';
            } else if ($c < " " || $c > "\x7E") {
                $c = '\\x' . sprintf("%02x", ord($c));
            }
            $output .= $nls . $spaces . $c;
            $spaces = '';
            $nls = '';
        }
    }
    if ($output !== '') {
        $output .= "\n";
    }
    return $output;
}


// Limit the length of the given string to MAX_STRING_LENGTH by
// removing the centre of the string, inserting the substring
// [... snip ... ] in its place.
function snip(&$s) {
    $snipinsert = ' ...snip... ';
    $len = strlen($s);
    if ($len > constants::MAX_STRING_LENGTH) {
        $lentoremove = $len - constants::MAX_STRING_LENGTH + strlen($snipinsert);
        $partlength = ($len - $lentoremove) / 2;
        $firstbit = substr($s, 0, $partlength);
        $lastbit = substr($s, $len - $partlength, $partlength);
        $s = $firstbit . $snipinsert . $lastbit;
    }
    return $s;
}


// Return a cleaned and snipped version of the string s (or null if s is null).
function tidy($s) {
    if ($s === null) {
        return null;
    } else {
        $cleaneds = clean($s);
        return snip($cleaneds);
    }
}