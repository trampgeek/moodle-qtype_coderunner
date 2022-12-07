@qtype @qtype_coderunner @javascript @scratchpad
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
    And I set the field "id_answer" to ""
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

  Scenario: Edit a CodeRunner question into a Scratchpad UI question, changing UI params for run button name
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |
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

  Scenario: Edit a CodeRunner question into a Scratchpad UI question, changing UI params for scratchpad name
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise   | 1                                     |
      | id_uiplugin    | Scratchpad                            |
      | id_uiparameters| {"sp_name": "superuniquename123"}     |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I should not see "Run!"
    And I should not see "▶Scratchpad"
    But I should see "▶superuniquename123"
    
    When I click on "▶superuniquename123" "button"
    Then I should see "▼superuniquename123"
    And I should see "Run!"
    And I should see "Prefix Answer?"

    When I click on "▼superuniquename123" "button"
    And I should not see "Run!"
    And I should not see "Prefix Answer?"