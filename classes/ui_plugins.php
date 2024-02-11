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

/** Defines a ui_plugins class which contains a list of available ui_plugins
 * and their attributes.
 *
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2021, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class qtype_coderunner_ui_plugins {
    private static $instance = null;
    /** @var array ui_plugins object. */
    public $plugins;

    /**
     * Construct a ui_plugins object by reading amd/src directory to identify
     * all available plugins and their specifications (if available).
     * uinames are internally lower-case and filenames should be lower-case
     * too. Capitalised names are used only in the UI plugin selector.
     */
    public function __construct() {
        global $CFG;

        $files = scandir($CFG->dirroot . '/question/type/coderunner/amd/src');
        $this->plugins = [];
        foreach ($files as $file) {
            if (substr($file, 0, 3) === 'ui_' && substr($file, -3) === '.js') {
                $uiname = substr($file, 3, -3);
                $parameters = new qtype_coderunner_ui_parameters($uiname);
                $plugin = new qtype_coderunner_ui_plugin($uiname, $parameters);
                $this->plugins[$uiname] = $plugin;
            }
        }

        // Add a dummy 'None' uiplugin.
        $emptyparameters = new qtype_coderunner_ui_parameters('none');
        $this->plugins['none'] = new qtype_coderunner_ui_plugin(null, $emptyparameters);
    }

    // This is a singleton class. Construct the object only if we haven't
    // already done so.
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new qtype_coderunner_ui_plugins();
        }
        return self::$instance;
    }

    // Return an array of all ui plugin names.
    public function all_names() {
        return array_keys($this->plugins);
    }


    // Return an array of all the names of ui plugsin that don't have any
    // parameters. Used to suppress the ui parameter panel in the question
    // editing form.
    public function all_with_no_params() {
        $result = [];
        foreach ($this->plugins as $plugin) {
            if ($plugin->parameters()->length() == 0) {
                $result[] = $plugin->uiname;
            }
        }
        return $result;
    }


    /**
     * Get the qtype_coderunner_ui_plugin object for the given plugin name.
     * @param string $uiname
     * @return qtype_coderunner_ui_plugin the specs for the given plugin.
     */
    public function get_plugin($uiname) {
        return $this->plugsins[$uiname];
    }


    // Return the parameters for the given ui plugin name.
    public function parameters($name) {
        $params = $this->plugins[$name]->parameters();
        return $params;
    }

    // Return an array mapping from uiname to a ucfirst version of the name,
    // and including a None => None entry, suitable for use in the plugin
    // dropdown selector.
    public function dropdownlist() {
        $uiplugins = [];
        foreach (array_values($this->plugins) as $plugin) {
            $uiplugins[$plugin->uiname] = ucfirst($plugin->uiname);
        }
        return $uiplugins;
    }
}


// A class to represent a single plugin. The uiname is the lower case
// plugin name, e.g. 'ace', 'graph'.
class qtype_coderunner_ui_plugin {

    /** @var string  */
    public $uiname;

    /** @var qtype_coderunner_ui_parameters */
    public $params;

    /**
     *
     * @param string-or-null $filename the ui plugins filename or null for 'None'
     * @param qtype_coderunner_ui_parameters $parameters the set of parameters available.
     */
    public function __construct($uiname, qtype_coderunner_ui_parameters $parameters) {
        if ($uiname !== null) {
            $this->uiname = $uiname;
        } else {
            // Special case for the 'None' ui plugin.
            $this->uiname = 'none';
        }
        $this->params = $parameters;
    }

    /**
     *
     * @return qtype_coderunner_ui_parameters the parameters for this plugin.
     */
    public function parameters() {
        return $this->params;
    }
}
