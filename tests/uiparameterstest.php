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

/**
 * Unit tests for various UI parameter-handling classes and functionality.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2021 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');

/**
 * Unit tests for UI parameters
 */
class qtype_coderunner_ui_parameters_test extends qtype_coderunner_testcase {

    // Test that the json specifier for the graph_ui class can be loaded.
    public function test_load() {
        $graphuiparams = new qtype_coderunner_ui_parameters('ui_graph');
        $this->assertEquals("boolean", $graphuiparams->type('isdirected'));
        $this->assertEquals(true, $graphuiparams->default('isdirected'));
        $this->assertEquals("int", $graphuiparams->type('noderadius'));
        $this->assertEquals(26, $graphuiparams->default('noderadius'));
    }
    

}
