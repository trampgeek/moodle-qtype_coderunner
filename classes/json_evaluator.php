<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/* The qtype_coderunner_equality_grader class. Compares the output from a given test case,
 *  awarding full marks if and only if the output exactly matches the expected
 *  output. Otherwise, zero marks are awarded. The output is deemed to match
 *  the expected if the two are byte for byte identical after trailing white
 *  space has been removed from both.
 *  "Trailing white space" means all white space at the end of the strings
 *  plus all white space from the end of each line in the strings. It does
 *  not include blank lines within the strings or white space within the lines.
 */

/**
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Exception to throw when JSON evaluation fails.
class qtype_coderunner_json_evaluate_error extends Exception {

}

// A class for "evaluating" a JSON data structure.
// "Evaluation" in this sense means traversing the entire structure
// evaluating any objects with a single field named either
// @randominrange or @randompick by replacing the object with a
// random integer or an object chosen from a list of objects, respectively.
// This is used to produce an instance of the template parameters for a
// question when it has embedded randomisation "nodes".
class qtype_coderunner_json_evaluator {

    public function __construct($jsonstring) {
        $this->jsonobj = json_decode($jsonstring, true);
        $this->randomised = false;
        $this->newjson = null;
        $newjsonobj = $this->evaluate_json($this->jsonobj);
        if ($this->has_randomisation()) {
            $this->newjson = json_encode($newjsonobj);
            debugging("New json is" . print_r($this->newjson, true));
        }
    }

    // True if evaluation resulted in a different json structure
    public function has_randomisation() {
        return $this->randomised;
    }

    public function get_instance() {
        return $this->newjson;
    }

    // Evaluate a json expression (recursively), after the json has been
    // converted to PHP in an associative-array form.
    private function evaluate_json($array) {
        if (!is_array($array)) { // Leaf
            return $array;
        }

        $processed = array();
        foreach ($array as $key => $value) {
            $processed[$key] = $this->evaluate_json_value($value);
        }
        return $processed;
    }

    // Support function for evaluate_json.
    private function evaluate_json_value($value) {
        if (!is_array($value)) {
            return $value;
        } else if (count($value) != 1) {
            return $this->evaluate_json($value);
        } else if (array_keys($value)[0] === '@randomint') {
            $range = $value['@randomint'];
            if (!is_array($range) || count($range) != 2
                || !is_int($range[0]) || !is_int($range[1])
                || $range[1] < $range[0]) {
                    throw new qtype_coderunner_json_evaluate_error('badrandomintarg');
            } else {
                $this->randomised = true;
                return rand($range[0], $range[1] - 1);
            }
        } else if (array_keys($value)[0] === '@randompick') {
            $options = $value['@randompick'];
            if (!is_array($options) || empty($options)) {
                throw new qtype_coderunner_json_evaluate_error('badrandompickarg');
            } else {
                $i = rand(0, count($options) - 1);
                $this->randomised = true;
                return $this->evaluate_json_value($options[$i]);
            }
        } else {
            return $this->evaluate_json($value);
        }
    }
}