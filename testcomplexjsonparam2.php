<?php
$templateparam = <<<BLAH
{
    "maxnumconstants": 5,
    "@randomint": ["i", 0, 10],
    "@randomselect": {
        "name": ["Bob", "Carol", "Ted", "Alice"],
        "age" : [20, 23, 27, 31]
    },
    "@randomselect": {
        "person": [
            {"name": "Bob", "age": 20},
            {"name": "Carol", "age": 23},
            {"name": "Ted", "age": 27},
            {"name": "Alice", "age": 31}
        ]
    }
}
BLAH;

$param = json_decode($templateparam, true);
var_dump($param);
