<?php
$templateparam = <<<BLAH
{ "randomstates": [
      { "func": "every_second",
        "stepsize": "second",
        "tests": [
            { "s": "Hi dude!",
              "ans": "H ue"
            },
            { "s": "",
               "ans": ""
            },
            { "s": "A much longer test string",
              "ans": "A mc ogrts tig"
            }
        ]
      },
      { "func": "every_third",
        "stepsize": "third",
        "tests": [
            { "s": "Hi dude!",
              "ans": "Hde"
            },
            { "s": "",
               "ans": ""
            },
            { "s": "A much longer test string",
              "ans": "Au nre rn"
            }
        ]
       }
    ]
}
BLAH;

$param = json_decode($templateparam);
print_r($param);
