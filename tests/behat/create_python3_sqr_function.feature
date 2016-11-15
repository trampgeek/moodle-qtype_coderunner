@qtype @qtype_coderunner @javascript
Feature: Create a CoderRunner question (the sqr function example)
  In order to test my students' programming ability
  As a teacher
  I need to create a new CodeRunner question

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
    And I log in as "teacher1"
    And I follow "Course 1"

  Scenario: As a teacher, I create a Python3 sqr(n) -> n**2 function
    When I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3                 |
      | name              | sqr acceptance question |
      | id_questiontext   | Write a sqr function    |
      | id_testcode_0     | print(sqr(-7))          |
      | id_expected_0     | 49                      |
    Then I should not see "Save changes"
    And I should not see "Write a sqr function"
    And I should see "sqr acceptance question"
