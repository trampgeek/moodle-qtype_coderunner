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

/** This file defines two legacy classes TestingOutcome and TestResult.
 *  These were the old names for the qtype_coderunner_testing_outcome and
 *  qtype_coderunner_test_result classes, before the plugin was refactored to
 *  moodle style standards. The legacy versions of the classes, which are
 *  subclasses of the modern versions, are still required to support
 *  the unserialisation of test results stored in the database.
 *  This file and its classes are deprecated and will be removed once older
 *  question attempts are no longer needed, around 2016/2017.
 *
 * @package    qtype
 * @subpackage coderunner
 * @deprecated
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

class TestingOutcome extends qtype_coderunner_testing_outcome {

    /** When the new object is constructed, convert all attributes to lower
     *  case and remove any underscores.
     */
    public function __wakeup() {
        foreach (get_object_vars($this) as $field => $value) {
            $newfield = str_replace('_', '', strtolower($field));
            if ($newfield !== $field) {
                $this->$newfield = $value;
                unset($this->field);
            }
        }
    }
}



class TestResult extends qtype_coderunner_test_result {

    /** When the new object is constructed, convert all attributes to lower
     *  case and remove any underscores.
     */
    public function __wakeup() {
        foreach (get_object_vars($this) as $field => $value) {
            $newfield = str_replace('_', '', strtolower($field));
            if ($newfield !== $field) {
                $this->$newfield = $value;
                unset($this->field);
            }
        }
    }
}


