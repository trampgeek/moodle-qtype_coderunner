@qtype @javascript @qtype_coderunner @scratchpad
Feature: Test the Scratchpad UI, UI Params
  In order to use the Scratchpad UI
  As a teacher
  I should be able specify the UI Parameters to change the Scratchpad UI

  Background:
    Given the CodeRunner test configuration file is loaded
    And the CodeRunner webservice is enabled
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name         | template |
      | Test questions   | coderunner | Print answer | printans |

    And I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_validateonsave" to ""

  Scenario: Edit a CodeRunner Scratchpad UI question, change all available UI params
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise | 1          |
      | id_uiplugin  | Scratchpad |

    And I set the field "id_uiparameters" to:
    """
    {
        "button_name": "Ran!",
        "scratchpad_name":"Scratchblobert",
        "html_output":true,
        "prefix_name":"unhelpful label :)",
        "wrapper_src": "globalextra",
        "run_lang": "Python3",
        "help_text": "hi",
        "params": {
            "numprocs":100,
            "memlimit":1000
        },
        "disable_scratchpad": false,
        "invert_prefix": true
    }
    """
    And I press "id_updatebutton"
    Then I should not see "The UI parameters for this question or its prototype are broken. Proceed with caution."

  Scenario: Change UI param for run button name
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                     |
      | id_uiplugin     | Scratchpad                            |
      | id_uiparameters | {"button_name": "superuniquename123"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I should not see "superuniquename123"
    And I should not see "Run"
    And I should see "Scratchpad"

    When I click on "Scratchpad" "button"
    Then I should see "superuniquename123"
    But I should not see "Run"
    And I should see "Prefix with Answer"

    When I click on "Scratchpad" "button"
    Then I should not see "superuniquename123"
    And I should not see "Run"
    And I should not see "Prefix Answer?"

  Scenario: Change UI param for Scratchpad name
    When I set the following fields to these values:
      | id_customise    | 1                                         |
      | id_uiplugin     | Scratchpad                                |
      | id_uiparameters | {"scratchpad_name": "superuniquename123"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I should not see "Run"
    And I should not see "Scratchpad"
    But I should see "superuniquename123"

    When I click on "superuniquename123" "button"
    Then I should see "superuniquename123"
    And I should see "Run"
    And I should see "Prefix with Answer"

    When I click on "superuniquename123" "button"
    And I should not see "Run"
    And I should not see "Prefix Answer?"

  Scenario: Change UI param for run button name
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                     |
      | id_uiplugin     | Scratchpad                            |
      | id_uiparameters | {"prefix_name": "superuniquename123"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I should see "Scratchpad"
    And I should not see "superuniquename123"
    And I should not see "Run"

    When I click on "Scratchpad" "button"
    Then I should see "superuniquename123"
    But I should not see "Prefix Answer?"
    And I should see "Run"

    When I click on "Scratchpad" "button"
    Then I should not see "superuniquename123"
    And I should not see "Run"
    And I should not see "Prefix Answer?"

  Scenario: Define wrapper in UI params and click run, insert both answer and Scratchpad code, NO prefix with answer, click run
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                              |
      | id_uiplugin     | Scratchpad                     |
      | id_uiparameters | {"wrapper_src": "globalextra"} |
    And I set the field "globalextra" to:
    """
    print('Hello Wrapper', end=' ')
    {| ANSWER_CODE |}
    {| SCRATCHPAD_CODE |}
    """

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the ace field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    And I set the field "prefix_ans" to ""
    Then I press "Run"
    And I should see "Hello Wrapper Hello Scratchpadcode"

  Scenario: Define wrapper in global extra and click run, insert both answer and Scratchpad code, prefix with answer, click run
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                              |
      | id_uiplugin     | Scratchpad                     |
      | id_uiparameters | {"wrapper_src": "globalextra"} |
    And I set the field "globalextra" to:
    """
    print('Hello Wrapper', end=' ')
    {| ANSWER_CODE |}
    {| SCRATCHPAD_CODE |}
    """

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the ace field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    Then I press "Run"
    And I should see "Hello Wrapper Hello Answercode Hello Scratchpadcode"

  Scenario: Define wrapper in prototype extra and click run, insert both answer and Scratchpad code, prefix with answer, click run
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1                                 |
      | id_uiplugin       | Scratchpad                        |
      | id_uiparameters   | {"wrapper_src": "prototypeextra"} |
      | id_prototypetype  | 2                                 |
      | id_typename       | typename123                       |
      | id_validateonsave | 0                                 |

    And I set the field "globalextra" to:
    """
    print('Hello GlobalExtra', end=' ')
    {| ANSWER_CODE |}
    {| SCRATCHPAD_CODE |}
    """
    And I set the field "prototypeextra" to:
    """
    print('Hello PrototypeExtra', end=' ')
    {| ANSWER_CODE |}
    {| SCRATCHPAD_CODE |}
    """
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I wait "2" seconds
    Then I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the ace field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    Then I press "Run"
    Then I wait "2" seconds
    Then I should see "Hello PrototypeExtra Hello Answercode Hello Scratchpadcode"
    And I should not see "Hello GlobalExtra"

  Scenario: Define wrapper in global extra, insert both answer and Scratchpad code, NO prefix with answer, click run
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                              |
      | id_uiplugin     | Scratchpad                     |
      | id_uiparameters | {"wrapper_src": "globalextra"} |
    And I set the field "globalextra" to:
    """
    print('Hello Wrapper', end=' ')
    {| ANSWER_CODE |}
    {| SCRATCHPAD_CODE |}
    """
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the ace field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    And I set the field "prefix_ans" to ""
    Then I press "Run"
    And I should see "Hello Wrapper Hello Scratchpadcode"

  Scenario: Define wrapper in global extra, insert both answer and Scratchpad code, prefix with answer, click run
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                              |
      | id_uiplugin     | Scratchpad                     |
      | id_uiparameters | {"wrapper_src": "globalextra"} |
    And I set the field "globalextra" to:
    """
    print('Hello Wrapper', end=' ')
    {| ANSWER_CODE |}
    {| SCRATCHPAD_CODE |}
    """

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the ace field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    And I set the field "prefix_ans" to "1"
    Then I press "Run"
    And I should see "Hello Wrapper Hello Answercode Hello Scratchpadcode"

  Scenario: I set UI param invert_prefix to true and check serialisation
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                         |
      | id_uiplugin     | Scratchpad                |
      | id_uiparameters | {"invert_prefix": "true"} |
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I should see "Run"
    And I set the field "prefix_ans" to "1"

    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":[""],"test_code":[""],"show_hide":["1"],"prefix_ans":[""]}
    """

    When I press the CTRL + ALT M key
    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":[""],"test_code":[""],"show_hide":["1"],"prefix_ans":[""]}
    """

  Scenario: I set UI param invert_prefix to true and check serialisation
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                         |
      | id_uiplugin     | Scratchpad                |
      | id_uiparameters | {"invert_prefix": "true"} |
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the field "prefix_ans" to ""

    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":[""],"test_code":[""],"show_hide":["1"],"prefix_ans":["1"]}
    """

  Scenario: I set UI param invert_prefix to true and check prefix run functionality
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                         |
      | id_uiplugin     | Scratchpad                |
      | id_uiparameters | {"invert_prefix": "true"} |
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print(\"hello\" + \" \" + \"world\")"
    And I set the ace field "test_code" to "print(\"goodbye\" + \" \" + \"world\")"

    When I press "Run"
    Then I should not see "hello world"
    And I should see "goodbye world"

    When I set the field "prefix_ans" to "1"
    And I press "Run"
    Then I should see "hello world"
    And I should see "goodbye world"

  Scenario: Set UI param for disabling scratchpad and check serialisation.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                              |
      | id_uiplugin     | Scratchpad                     |
      | id_uiparameters | {"disable_scratchpad": "true"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I should not see "Scratchpad"
    And I should not see "Run"
    And I should not see "Prefix with Answer"

    When I press the CTRL + ALT M key
    Then I should see in answer field ""

    When I press the CTRL + ALT M key
    Then I wait "2" seconds
    And I set the ace field "answer_code" to "print('hello world')"

    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":["print('hello world')"],"test_code":[""],"show_hide":[""],"prefix_ans":["1"]}
    """

  Scenario: Set UI param for help text.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                    |
      | id_uiplugin     | Scratchpad                           |
      | id_uiparameters | {"help_text": "superusefulhelptext"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the field "prefix_ans" to ""
    And I press the tab key
    Then I should see "superusefulhelptext"

  Scenario: Set output_display_mode to text, 'print' a hidden div to output area
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "text"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to "print('<div hidden>Hi</div>')"
    Then I press "Run"
    And I should see "<div hidden>Hi</div>"

  Scenario: Set output_display_mode to text, get no output.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "text"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I press "Run"
    And I press "Run"
    Then I should see "< No output! >"
    And I should not see "< No output! >< No output! >"

  Scenario: Set output_display_mode to text, get a runtime error, get a compile error.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "text"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to "d"
    And I press "Run"
    Then I should see "NameError: name 'd' is not defined."

    When I set the ace field "test_code" to "'d'd'd'"
    And I press "Run"
    Then I should see "SyntaxError: invalid syntax"

  Scenario: Set output_display_mode to json, 'print' a hidden div to output area
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "json"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to:
    """
    import json
    output = {'returncode': 0,
              'stdout' : '<div hidden>Hi</div>',
              'stderr' : '',
              'files' : ''
    }
    print(json.dumps(output))
    """
    Then I press "Run"
    And I set the ace field "test_code" to ""
    And I should see "<div hidden>Hi</div>"

  Scenario: Set output_display_mode to json, 'print' a hidden div to output area
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "json"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to:
    """
        output = {'returncode': 0,
              'stdout' : '<div hidden>Hi</div>',
              'stderr' : '',
              'files' : ''
    """
    And I press "Run"
    Then I should see "Error parsing JSON. Output from wrapper:"

  Scenario: Set output_display_mode to json, 'print' a hidden div to output area
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "json"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to:
    """
    import json
    output = {'returncode': 0,
              'stdout' : '<div hidden>Hi</div>',
              'files' : ''
    }
    print(json.dumps(output))
    """
    And I press "Run"
    Then I should see "stderr"

  Scenario: Set output_display_mode to json, get no output.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "json"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to:
    """
    import json
    output = {'returncode': 0,
              'stdout' : '',
              'stderr' : '',
              'files' : ''
    }
    print(json.dumps(output))
    """
    And I press "Run"
    And I press "Run"
    Then I should see "< No output! >"
    And I should not see "< No output! >< No output! >"

  Scenario: Set output_display_mode to json, get a runtime error, get a compile error.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "json"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to:
    """
    import json
    output = {'returncode': 0,
              'stdout' : 'hello wolrd',
              'stderr' : 'i am error',
              'files' : ''
    }
    print(json.dumps(output))
    """
    And I press "Run"
    Then I should see "hello wolrdi am error"

  Scenario: Set HTML output_display_mode to html, 'print' a button to output area
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "html"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to "print('<button>Hi</button>')"
    Then I press "Run"
    And I press "Hi"

  Scenario: Set HTML output_display_mode to html, get no output.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "html"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I press "Run"
    And I press "Run"
    Then I should see "< No output! >"
    And I should not see "< No output! >< No output! >"

  Scenario: Set output_display_mode to html, get a runtime error, get a compile error.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                               |
      | id_uiplugin     | Scratchpad                      |
      | id_uiparameters | {"output_display_mode": "html"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to "d"
    And I press "Run"
    Then I should see "NameError: name 'd' is not defined."

    When I set the ace field "test_code" to "'d'd'd'"
    And I press "Run"
    Then I should see "SyntaxError: invalid syntax"

  Scenario: Create a wrapper with student code in single double-quote string (python) and enter program containing many quotes.
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                  |
      | id_uiplugin     | Scratchpad         |
      | id_uiparameters | {"escape": "true", "wrapper_src": "globalextra"} |
    And I set the field "globalextra" to "exec(\"{| ANSWER_CODE |}\n{| SCRATCHPAD_CODE |}\")"

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print(\"\"\"h\"\"\", end='')"
    And I set the ace field "test_code" to:
    """
    print("i", end='')
    print('1', end='')
    print('''2''', end='')
    print('3', end='')
    """
    And I press "Run"
    Then I should see "hi123"
