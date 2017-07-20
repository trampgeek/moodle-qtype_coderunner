@qtype @qtype_coderunner @javascript @setuiplugintest @emily
Feature: Check that a selected UI plugin is saved
  To check that a selected UI Plugin is saved
  As a teacher
  I should be able to select a UI plugin and save the form

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
      | questioncategory | qtype      | name            | template |
      | Test questions   | coderunner | Square function | sqr      |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Question bank"
    And I navigate to "Question bank" node in "Course administration"

  Scenario: Edit a coderunner UI plugin
    When I click on "Edit" "link" in the "Square function" "table_row"
    And I set the following fields to these values:
      | customise | 1   |
      | uiplugin  | fsm |
    And I press "id_updatebutton"
    Then I should see "fsm"
    And I press "id_submitbutton"

  Scenario: UI plugin state is saved
    When I click on "Edit" "link" in the "Square function" "table_row"
    Then I should see "fsm"
   