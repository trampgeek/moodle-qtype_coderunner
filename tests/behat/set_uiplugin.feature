@qtype @qtype_coderunner @javascript @setuiplugintest
Feature: Check that a selected UI plugin is saved
  To check that a selected UI Plugin is saved
  As a teacher
  I should be able to select a UI plugin and save the form

  Background:
    Given the CodeRunner test configuration file is loaded
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
      | questioncategory | qtype      | name            | template |
      | Test questions   | coderunner | Square function | sqr      |
    And I enable UI plugins in the CodeRunner question type

  Scenario: Selecting the Graph UI plugin results in a canvas being displayed
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise | 1     |
      | id_uiplugin  | graph |
    Then I should see a canvas

  Scenario: UI plugin state is saved when question is saved
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise | 1     |
      | id_uiplugin  | graph |
    And I press "id_submitbutton"
    When I choose "Edit question" action for "Square function" in the question bank
    Then I should see a canvas

  Scenario: UI plugin state is saved for student
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise | 1     |
      | id_uiplugin  | graph |
    And I press "id_submitbutton"
    When I choose "Preview" action for "Square function" in the question bank
    Then I should see a canvas
