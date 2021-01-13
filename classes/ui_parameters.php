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

/** Defines a UI Parameters object, which defines the types and default values for
 * all particular the parameters for a particular ui plugin. This class is used only during
 * question editing; at all other times the UI parameters are simply JSON
 * objects.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2021, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

// A class to represent a single parameter with a name, type and value.
class qtype_coderunner_ui_parameter {
    public function __construct($name, $type, $value) {
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
    }
}

/**
 * A class representing a set of qtype_coderunner_ui_parameter objects for
 * a particular ui plugin.
 */
class qtype_coderunner_ui_parameters {

    /**
     * Construct a ui_parameters object by reading the json file for the
     * specified ui_plugin.
     * @param string $name the name of the ui component, e.g. graph, used to
     * locate the JSON file that specifies the type and default value, e.g.
     * ui_graph.json.
     */
    public function __construct(string $name) {
        global $CFG;
        $filename = $CFG->dirroot . "/question/type/coderunner/amd/src/ui_{$name}.json";
        $this->params = array();
        if (file_exists($filename)) {
            $json = file_get_contents($filename);
            $spec = json_decode($json);
            foreach ((array) $spec->parameters as $name => $obj) {
                $this->params[$name] = new qtype_coderunner_ui_parameter($name, $obj->type, $obj->default);
            }
        }
    }
    
    
    // Return the number of parameters in this parameter set.
    public function length() {
        return count($this->params);
    }
    
    /**
     * Get the type of a particular parameter.
     * @param string $parameter the name of the parameter of interest
     * @return string the type of the parameter
     */
    public function type(string $parameter) {
        return $this->params[$parameter]->type;
    }
    
    
    /**
     * Get the default value (which may have been overridden by merge_json so
     * is really just the value) for a particular parameter.
     * @param string $parameter the name of the parameter of interest
     * @return the value of the parameter.
     */
    public function value(string $parameter) {
        return $this->params[$parameter]->value;
    }
    
    
    /**
     * Merge a set of parameter values, defined by a JSON string, into this.
     * @param string $json the JSON string defining the set of parameters to merge in.
     */
    public function merge_json(string $json) {
        $newvalues = json_decode($json);
        foreach ($newvalues as $key => $value) {
            if (!array_key_exists($key, (array) $this->params)) {
                throw new qtype_coderunner_exception('Unexpected key value when merging json');
            }
            $this->params[$key]->value = $value;
        }
    }
    
    
    /**
     * Return a list of all parameter names
     */
    public function all_names() {
        return array_keys($this->params);
    }
}
