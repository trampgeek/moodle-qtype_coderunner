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


namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');

/**
 * Unit tests for UI parameters
 * @coversNothing
 */
class ui_parameters_test extends \qtype_coderunner_testcase {
    // Test that the json specifier for the graph_ui class can be loaded.
    public function test_params() {
        $graphuiparams = new \qtype_coderunner_ui_parameters('graph');
        $this->assertEquals("boolean", $graphuiparams->type('isdirected'));
        $this->assertEquals(true, $graphuiparams->value('isdirected'));
        $this->assertEquals("int", $graphuiparams->type('noderadius'));
        $this->assertEquals(26, $graphuiparams->value('noderadius'));
        $graphuiparams->merge_json('{"noderadius": 30}');
        $this->assertEquals(30, $graphuiparams->value('noderadius'));
        $json = $graphuiparams->to_json();
        $paramsarray = json_decode($json, true);
        $this->assertContains('noderadius', array_keys($paramsarray));
        $this->assertEquals(30, $paramsarray['noderadius']);
        $aceparams = new \qtype_coderunner_ui_parameters('ace');
        $this->assertEquals(false, $aceparams->value('auto_switch_light_dark'));
        $this->assertEquals("14px", $aceparams->value('font_size'));
        $this->assertEquals(true, $aceparams->value('import_from_scratchpad'));
        $this->assertEquals(false, $aceparams->value('live_autocompletion'));
        $this->assertEquals("textmate", $aceparams->value('theme'));
    }

    // Test that we can get a list of all plugins and their parameter lists.
    public function test_plugin_list() {
        $plugins = \qtype_coderunner_ui_plugins::get_instance();
        $names = $plugins->all_names();
        $this->assertContains('ace', $names);
        $this->assertContains('graph', $names);
        $this->assertContains('gapfiller', $names);
        $this->assertContains('html', $names);
        $this->assertContains('scratchpad', $names);
    }

    // Test the dropdown list for the plugins.
    public function test_dropdown() {
        $plugins = \qtype_coderunner_ui_plugins::get_instance();
        $dropdowns = $plugins->dropdownlist();
        $this->assertEquals('None', $dropdowns['none']);
        $this->assertEquals('Graph', $dropdowns['graph']);
    }
}
