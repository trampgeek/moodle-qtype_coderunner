<?php

class qtype_coderunner_exception extends Exception {
}

$templateparam = <<<BLAH
{
    "maxnumconstants": 5,
    "i":    {"@randominrange": [0, 10]},
    "j":    {"@randominrange": [-100, -1]},
    "name": {"@randompick": ["Bob", "Carol", "Ted", "Alice"]},
    "age":  {"@randompick": [10, {"@randominrange": [20, 50]}]},
    "person": {
        "@randompick": [
            {"name": "Bob", "age": 20},
            {"name": "Carol", "age": 23},
            {"name": {"@randompick": ["Ted1", "Ted2"]}, "age": 27},
            {"name": "Alice", "age": {"@randominrange": [40, 50]}}
        ]
    }
}
BLAH;


function process_json($params_json) {
    $params = json_decode($params_json, true);
    $processed = evaluate_json_array($params);
    echo json_encode($processed);
}



function evaluate_json_array($array) {
    if (!is_array($array)) { // Leaf
        return $array;
    }

    $processed = array();
    foreach ($array as $key => $value) {
        $processed[$key] = evaluate_json_value($value);
    }
    return $processed;
}


function evaluate_json_value($value) {
    if (!is_array($value)) {
        return $value;
    } else if (count($value) != 1) {
        return process_array($value);
    } else if (array_keys($value)[0] === '@randominrange') {
        $range = $value['@randominrange'];
        if (!is_array($range) || count($range) != 2
            || !is_int($range[0]) || !is_int($range[1])
            || $range[1] < $range[0]) {
                throw new qtype_coderunner_exception('badrandominrangearg');
        } else {
            return rand($range[0], $range[1] - 1);
        }
    } else if (array_keys($value)[0] === '@randompick') {
        $options = $value['@randompick'];
        if (!is_array($options) || empty($options)) {
            throw new qtype_coderunner_exception('badrandompickarg');
        } else {
            $i = rand(0, count($options) - 1);
            return evaluate_json_value($options[$i]);
        }
    } else {
        return evaluate_json_array($value);
    }
}



process_json($templateparam);


