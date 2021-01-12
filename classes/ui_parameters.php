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

class qtype_coderunner_ui_parameters {

    /**
     * Construct a ui_parameters object by reading the json file for the
     * specified ui_plugin.
     * @param string $name the name of the ui component, e.g. graph_ui, used to
     * locate the JSON file that specifies the type and default value, e.g.
     * graph_ui.json.
     */
    public function __construct(string $name) {
        global $CFG;
        $json = file_get_contents($CFG->dirroot . "/question/type/coderunner/amd/src/$name.json");
        $spec = json_decode($json);
        $this->params = $spec->parameters;
    }
    
    /**
     * Get the type of a particular parameter.
     * @param string $parameter the name of the parameter of interest
     * @return string the type of the parameter
     */
    public function type(string $parameter) {
        return $this->params->$parameter->type;
    }
    
    /**
     * Get the default value for a particular parameter.
     * @param string $parameter the name of the parameter of interest
     * @return the default value of the parameter.
     */
    public function default(string $parameter) {
        return $this->params->$parameter->default;
    }
    
    /**
     * Merge a set of parameter values, defined by a JSON string, into this.
     * @param string $json the JSON string defining the set of parameters to merge in.
     */
    public function merge_json(string $json) {
        $newvalues = json_decode($json);
        foreach ($newvalues as $key=>$value) {
            if (!array_key_exists($key, (array) $this->params)) {
                throw new qtype_coderunner_exception('Unexpected key value when merging json');
            }
            $this->params->$key = $value;
        }
    }
}
