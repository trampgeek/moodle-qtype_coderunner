@qtype @qtype_coderunner @javascript @scratchpad @scratchpadmain
Feature: Test the Scratchpad UI
  In order to use the Scratchpad UI
  As a teacher
  I should be able specify the required html in either globalextra or prototypeextra

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
    And the webserver sandbox is enabled

  Scenario: Edit a CodeRunner question into a Scratchpad UI question
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I should not see "Run!"
    And I should see "▶Scratchpad"
    
    When I click on "▶Scratchpad" "button"
    Then I should see "Run!"
    And I should see "Prefix Answer?"

    When I click on "▼Scratchpad" "button"
    Then I should not see "Run!"
    And I should not see "Prefix Answer?"

  Scenario: Click the run button with program that outputs in Scratchpad Code
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "test_code" to "print('hello world')"
    
    Then I press "Run!"
    And I should see "hello world"

  Scenario: Click the run button with program that outputs in Answer Code 
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "answer_code" to "print('hello world')"
    
    Then I press "Run!"
    And I should not see "hello world"
    
    Then I set the field "prefix_ans" to "1"
    And I press "Run!"
    And I should see "hello world"
 
  Scenario: Click the run button with program that outputs in Answer Code and Scratchpad Code 
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "▶Scratchpad" "button"
    And I set the field "answer_code" to "print('hello world')"
    And I set the field "test_code" to "print('goodbye world')"
    
    Then I press "Run!"
    And I should not see "hello world"
    And I should see "goodbye world"
    
    Then I set the field "prefix_ans" to "1"
    And I press "Run!"
    And I should see "hello world"
    And I should see "goodbye world"

  Scenario: Edit a CodeRunner question into a Scratchpad UI question and get empty UI serialization
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I press the ctrl + alt M key
    Then I should not see "{\"answer_code\":\"\",\"test_code\":\"\",\"show_hide\":\"\",\"prefix_ans\":\"\"}"
  
  Scenario: Edit a CodeRunner question into a Scratchpad UI question and get UI serialization, while Scratchpad Hidden
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |

    And I press "id_submitbutton"
    Then I should see "Print answer"
    
    When I choose "Preview" action for "Print answer" in the question bank
    And I set the field "answer_code" to "print('hello world')"
    
    Then I press the ctrl + alt M key
    And I should see "{\"print('hello world')\":\"\",\"test_code\":\"\",\"show_hide\":\"\",\"prefix_ans\":\"\"}"

    Scenario: Edit a CodeRunner question into a Scratchpad UI question and get UI serialization, while Scratchpad Shown
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |

    And I press "id_submitbutton"
    Then I should see "Print answer"
    
    When I choose "Preview" action for "Print answer" in the question bank
    And I set the field "answer_code" to "print('hello world')"
    And I click on "▶Scratchpad" "button"
    
    Then I press the ctrl + alt M key
    And I should see "{\"answer_code\":\"print('hello world')\",\"test_code\":\"\",\"show_hide\":\"1\",\"prefix_ans\":\"\"}"