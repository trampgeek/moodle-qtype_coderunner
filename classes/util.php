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
 * Utility routines for qtype_coderunner
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use qtype_coderunner\constants;

class qtype_coderunner_util {
    /*
     * Configure the ace editor for use with the given textarea (specified by its
     * id) if question is set to use Ace. Language is specified either as
     * TEMPLATE_LANGUAGE (the language used in the sandbox) or USER_LANGUAGE.
     * They are the same unless a different language has been explicitly specified
     * by the acelang field in the question authoring form, in which case the
     * acelang field is used for the USER and the $question->language for the
     * template.
     */
    public static function load_ace_if_required($question, $textareaid, $whichlang) {
        global $CFG, $PAGE;
        if ($question->useace) {
            if ($whichlang === constants::TEMPLATE_LANGUAGE ||
                   empty($question->acelang)) {
                $lang = $question->language;
            } else {
                $lang = $question->acelang;
            }
            $lang = ucwords($lang);
            self::load_ace();
            $PAGE->requires->js_call_amd('qtype_coderunner/aceinterface', 'initAce', array($textareaid, $lang));
        }
    }


    // Load the ace scripts.
    public static function load_ace() {
        global $PAGE;
        $plugindirrel = '/question/type/coderunner';
        $PAGE->requires->js($plugindirrel . '/ace/ace.js');
        $PAGE->requires->js($plugindirrel . '/ace/ext-language_tools.js');
        $PAGE->requires->js($plugindirrel . '/ace/ext-modelist.js');
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
    public static function clean(&$s) {
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
    public static function snip(&$s) {
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
    public static function tidy($s) {
        if ($s === null) {
            return null;
        } else {
            $cleaneds = self::clean($s);
            return self::snip($cleaneds);
        }
    }


    // Sanitise with 's()' and add line breaks to a given string.
    // TODO: expand tabs (which appear in Java traceback output).
    public static function format_cell($cell) {
        return str_replace("\n", "<br />", str_replace(' ', '&nbsp;', s($cell)));
    }


    // Clean the given html by wrapping it in <div> tags and parsing it with libxml
    // and outputing the (supposedly) cleaned up HTML.
    public static function clean_html($html) {
        libxml_use_internal_errors(true);
        $html = "<div>". $html . "</div>"; // Wrap it in a div (seems to help libxml).
        $doc = new DOMDocument;
        if ($doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return $doc->saveHTML();
        } else {
            $message = "Errors in HTML\n<br />";
            foreach (libxml_get_errors() as $error) {
                $message .= "Line {$error->line} column {$error->line}: {$error->code}\n<br />";
            }
            libxml_clear_errors();
            $message .= "\n<br />" + $html;
            return $message;
        }
    }

    /**
     * Convert a given list of lines to an HTML <p> element.
     * @param type $lines
     */
    public static function make_html_para($lines) {
        if (count($lines) > 0) {
            $para = html_writer::start_tag('p');
            $para .= $lines[0];
            for ($i = 1; $i < count($lines); $i++) {
                $para .= html_writer::empty_tag('br') . $lines[$i];;
            }
            $para .= html_writer::end_tag('p');
        } else {
            $para = '';
        }
        return $para;
    }

}