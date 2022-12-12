@qtype @qtype_coderunner @javascript @scratchpad @scratchpaduiparam
Feature: Test the Scratchpad UI, UI Params
  In order to use the Scratchpad UI
  As a teacher
  I should be able specify the UI Paramiters to change the Scratchpad UI

  Background:
    Given the following "users" exist:
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
    And the CodeRunner sandbox is enabled
    
    And I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_validateonsave" to ""
    
Scenario: Edit a CodeRunner Scratchpad UI question, change all available UI params 
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                       |
      | id_uiplugin     | Scratchpad                              |
      
    And I set the field "id_uiparameters" to:
    """
    {
        "sp_button_name": "Ran!",
        "sp_name":"Scratchblobert",
        "sp_html_out":true,
        "sp_prefix_name":"unhelpful label :)",
        "sp_run_wrapper": "print('hi')",
        "sp_run_lang": "Python3",
        "sp_html_out": true,
        "params": {
            "numprocs":100,
            "memlimit":1000
        }
    }
    """
    And I press "id_updatebutton"
    
    Then I should not see "The UI parameters for this question or its prototype are broken. Proceed with caution."

  
Scenario: Change UI param for run button name
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise   | 1                                       |
      | id_uiplugin    | Scratchpad                              |
      | id_uiparameters| {"sp_button_name": "superuniquename123"}|

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I should not see "superuniquename123"
    And I should not see "Run!"
    And I should see "▶Scratchpad"
    
    When I click on "▶Scratchpad" "button"
    Then I should see "superuniquename123"
    But I should not see "Run!"
    And I should see "Prefix Answer?"

    When I click on "▼Scratchpad" "button"
    Then I should not see "superuniquename123"
    And I should not see "Run!"
    And I should not see "Prefix Answer?"

  Scenario: Change UI param for Scratchpad name
    When I set the following fields to these values:
      | id_customise   | 1                                       |
      | id_uiplugin    | Scratchpad                              |
      | id_uiparameters| {"sp_name": "superuniquename123"}       |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I should not see "Run!"
    And I should not see "▶Scratchpad"
    But I should see "▶superuniquename123"
    
    When I click on "▶superuniquename123" "button"
    Then I should see "▼superuniquename123"
    And I should see "Run!"
    And I should see "Prefix Answer?"

    When I click on "▼superuniquename123" "button"
    And I should not see "Run!"
    And I should not see "Prefix Answer?"

  Scenario: Change UI param for run button name
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                       |
      | id_uiplugin     | Scratchpad                              |
      | id_uiparameters | {"sp_prefix_name": "superuniquename123"}|

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I should see "▶Scratchpad"
    And I should not see "superuniquename123"
    And I should not see "Run!"
    
    
    When I click on "▶Scratchpad" "button"
    Then I should see "superuniquename123"
    But I should not see "Prefix Answer?"
    And I should see "Run!"

    When I click on "▼Scratchpad" "button"
    Then I should not see "superuniquename123"
    And I should not see "Run!"
    And I should not see "Prefix Answer?"

  Scenario: Set HTML output to true, 'print' a button to output area
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                       |
      | id_uiplugin     | Scratchpad                              |
      | id_uiparameters | {"sp_html_out": true}                   |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "test_code" to "print('<button>Hi</button>')"
    Then I press "Run!"
    And I press "Hi"
  
  Scenario: Define wrapper in UI params and click run, insert both answer and Scratchpad code, NO prefix with answer, click run
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                                             |
      | id_uiplugin     | Scratchpad                                                    |
      | id_uiparameters | {"sp_run_wrapper": "print('Hello Wrapper', end=' ')\n{{ ANSWER_CODE }}\n{{ SCRATCHPAD_CODE }}"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    Then I press "Run!"
    And I should see "Hello Wrapper Hello Scratchpadcode"

  Scenario: Define wrapper in UI params and click run, insert both answer and Scratchpad code, prefix with answer, click run   
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                                             |
      | id_uiplugin     | Scratchpad                                                    |
      | id_uiparameters | {"sp_run_wrapper": "print('Hello Wrapper', end=' ')\n{{ ANSWER_CODE }}\n{{ SCRATCHPAD_CODE }}"} |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    And I set the field "prefix_ans" to "1"
    Then I press "Run!"
    And I should see "Hello Wrapper Hello Answercode Hello Scratchpadcode"

    Scenario: Define wrapper in global extra, insert both answer and Scratchpad code, NO prefix with answer, click run
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                                             |
      | id_uiplugin     | Scratchpad                                                    |      
      | id_uiparameters | {"sp_run_wrapper": "globalextra"}                             |
    And I set the field "globalextra" to:
    """
    print('Hello Wrapper', end=' ')
    {{ ANSWER_CODE }}
    {{ SCRATCHPAD_CODE }}
    """
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    Then I press "Run!"
    And I should see "Hello Wrapper Hello Scratchpadcode"

  Scenario: Define wrapper in global extra, insert both answer and Scratchpad code, prefix with answer, click run   
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise    | 1                                                             |
      | id_uiplugin     | Scratchpad                                                    |
      | id_uiparameters | {"sp_run_wrapper": "globalextra"}                             |
    And I set the field "globalextra" to:
    """
    print('Hello Wrapper', end=' ')
    {{ ANSWER_CODE }}
    {{ SCRATCHPAD_CODE }}
    """

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "answer_code" to "print('Hello Answercode', end=' ')"
    And I set the field "test_code" to "print('Hello Scratchpadcode', end=' ')"
    And I set the field "prefix_ans" to "1"
    Then I press "Run!"
    And I should see "Hello Wrapper Hello Answercode Hello Scratchpadcode"