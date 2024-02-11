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
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2021, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

// A class to represent a single parameter with a name, type and value.
class qtype_coderunner_ui_parameter {

    /** @var string The name of the parameter. */
    public $name;

    /** @var string The type of the parameter, e.g. 'int', 'float', 'string', 'bool', 'list'. */
    public $type;

    /** @var mixed The value of the parameter. */
    public $value;

    /** @var bool Whether the parameter is required. */
    public $required;

    /** @var bool Whether the parameter has been updated since the initial load. */
    public $updated;

    public function __construct($name, $type, $value, $required = false) {
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
        $this->required = $required;
        $this->updated = false;
    }
}

/**
 * A class representing a set of qtype_coderunner_ui_parameter objects for
 * a particular ui plugin.
 */
class qtype_coderunner_ui_parameters {

    /** @var string The name of the ui plugin, e.g. ace, graph, etc. */
    public $uiname;

    /** @var array An associative array of parameter name => qtype_coderunner_ui_parameter */
    public $params;

    /**
     * Construct a ui_parameters object by reading the json file for the
     * specified ui_plugin.
     * @param string $uiname the name of the ui component, e.g. graph, used to
     * locate the JSON file that specifies the type and default value, e.g.
     * ui_graph.json.
     */
    public function __construct(string $uiname) {
        global $CFG;
        $filename = $CFG->dirroot . "/question/type/coderunner/amd/src/ui_{$uiname}.json";
        $this->uiname = $uiname;
        $this->params = [];
        if (file_exists($filename)) {
            $json = file_get_contents($filename);
            $spec = json_decode($json);
            foreach ((array) $spec->parameters as $name => $obj) {
                $required = isset($obj->required) && $obj->required;
                $this->params[$name] = new qtype_coderunner_ui_parameter($name, $obj->type, $obj->default, $required);
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
     * Return true if the given uiparameter is required.
     * @param string $parameter the name of the parameter of interest
     * @return true iff the nominated parameter has a required attribute that is true.
     */
    public function is_required(string $parameter) {
        return $this->params[$parameter]->required;
    }


    /**
     * Merge a set of parameter values, defined by a JSON string, into this.
     * @param string $json the JSON string defining the set of parameters to merge in.
     * @param boolean $ignorebad If a parameter in the json string does not
     * already have a key, ignore it. Otherwise an exception is raised.
     */
    public function merge_json($json, $ignorebad = false) {
        $newvalues = json_decode($json ?? '');
        if ($newvalues !== null) {  // If $json is valid.
            foreach ($newvalues as $key => $value) {
                $matchingkey = $this->find_key($key);
                if ($matchingkey === null) {
                    if ($ignorebad) {
                        continue;
                    } else {
                        throw new qtype_coderunner_exception(
                            "Unexpected key value ($key) when merging json for ui {$this->uiname}"
                        );
                    }
                }
                $this->params[$matchingkey]->value = $value;
                $this->params[$matchingkey]->updated = true;
            }
        }
    }


    /**
     * Search the set of parameters for one that equals the given one, or
     * is equal to it with the UI plugin name plus an underscore as a prefix.
     * The latter match is for legacy parameter names like table_num_rows for
     * the table ui plugin.
     * @param string $paramname The name of the parameter to find.
     * @return string The actual parameter name that matches the given one,
     * of null if no such name.
     */
    private function find_key(string $paramname) {
        foreach ($this->params as $param) {
            $alias = "{$this->uiname}_{$param->name}";
            if ($param->name === $paramname || $alias === $paramname) {
                return $param->name;
            }
        }
        return null;
    }


    /**
     * Return a list of all parameter names
     */
    public function all_names() {
        return array_keys($this->params);
    }


    /**
     * Return a list of all parameter names with star to denote required.
     */
    public function all_names_starred() {
        $names = [];
        foreach ($this->params as $param) {
            $names[] = $param->required ? $param->name . '*' : $param->name;
        }
        return $names;
    }


    /**
     * Return the json encoded parameter-name => parameter value set.
     * Used only in testing.
     */
    public function to_json() {
        $paramsarray = [];
        foreach ($this->params as $param) {
            if ($param->value !== null) {
                $paramsarray[$param->name] = $param->value;
            }
        }
        return json_encode($paramsarray);
    }


    /**
     * Return an associative array of all parameters. Currently unused.
     */
    public function params_as_array() {
        $paramsarray = [];
        foreach ($this->params as $param) {
            $paramsarray[$param->name] = $param->value;
        }
        return $paramsarray;
    }


    /**
     * Return an array of all those parameters that have been updated since
     * the initial load from the json file (i.e. those parameters that have
     * been defined within the prototype or the question itself).
     */
    public function updated_params() {
        $paramsarray = [];
        foreach ($this->params as $param) {
            if ($param->updated) {
                $paramsarray[$param->name] = $param->value;
            }
        }
        return $paramsarray;
    }


    // Return a table (array of arrays) of parameters, each row containing
    // the parameter name, description, default value and boolean 'isrequired'.
    public function table() {
        $table = [];
        foreach ($this->params as $param) {
            $descr = get_string("{$this->uiname}ui_{$param->name}_descr", 'qtype_coderunner');
            $table[] = [$param->name, $descr, $param->value, $param->required];
        }
        return $table;
    }
}
