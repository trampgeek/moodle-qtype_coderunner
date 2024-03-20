@qtype @qtype_coderunner @javascript
Feature: Test sandbox web service
  JavaScript should be able to send job requests directly to the sandbox
  server (Jobe) via Ajax.

  Background:
    Given the CodeRunner test configuration file is loaded
    And the following "users" exist:
      | username | firstname | lastname | email           |
      | teacher  | Teacher   | 1        | teacher@asd.com |
      | student  | Student   | 1        | student@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role        |
      | teacher  | C1     | teacher     |
      | student  | C1     | student     |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name             | template |
      | Test questions   | coderunner | Demo web service | demows   |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question         | page |
      | Demo web service | 1    |

  @javascript
  Scenario: As a student if I try to initiate a WS request I get an error if the service is disabled.
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as student
    And I press "Attempt quiz"
    And I press "Click me"
    Then I should see "ERROR: qtype_coderunner/Sandbox web service disabled."

  @javascript
  Scenario: As a student I can initiate a WS request and see the outcome if the service is enabled.
    When I log in as "admin"
    And I navigate to "Plugins > CodeRunner" in site administration
    And I set the following fields to these values:
    | Enable sandbox web service | Yes |
    And I press "Save changes"
    And I log out
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as student
    And I press "Attempt quiz"
    And I press "Click me"
    Then I should see "Hello me!"
    And I should see "Hello you!"
