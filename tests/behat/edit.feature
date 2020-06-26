@qtype @qtype_coderunner @javascript
Feature: Test editing a CodeRunner question
  In order to be able to update my CodeRunner questions
  As a teacher
  I need to edit them

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
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

  Scenario: Edit a CodeRunner question
    When I choose "Edit question" action for "Square function" in the question bank
    And I set the following fields to these values:
      | Question name | |
    And I press "Save changes"
    Then I should see "You must supply a value here."
    When I set the following fields to these values:
      | Penalty regime | 0.1,3.2 5.1, 4.8, ...|
      | Question name | Edited name |
    And I press "Save changes"
    Then I should see "Misuse of '...'"
    When I set the following fields to these values:
      | Penalty regime | 0.1,3.2 5.1, 6.1, ...|
    And I press "id_submitbutton"
    Then I should see "Edited name"
    And I should see "Created by"
    And I should see "Last modified by"
