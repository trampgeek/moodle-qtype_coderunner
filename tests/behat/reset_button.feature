@qtype @qtype_coderunner @javascript @resetbuttontest
Feature: Preview the Python 3 sqr function CodeRunner question with a preload
  As a teacher
  I must be able to preview a question with a preloaded answer box
  I should see a Reset answer button that resets the preload,

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
      | questioncategory | qtype      | name            | answerpreload           |
      | Test questions   | coderunner | Square function | # Your answer goes here |

  Scenario: Preview the Python3 sqr function, get it wrong, then reset it
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I should see "# Your answer goes here"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test           |
      | print(sqr(11)) |
      | print(sqr(-7)) |
    And "print(sqr(11))" row "Expected" column of "coderunner-test-results" table should contain "121"
    And "print(sqr(11))" row "Got" column of "coderunner-test-results" table should contain "11"
    Then I should see "Marks for this submission: 3.00/31.00"
    And I should not see "# Your answer goes here"
    When I set CodeRunner behat testing flag
    And I press "Reset answer"
    And I press "Check"
    Then I should see "# Your answer goes here"
    And I should see "You must complete or edit the preloaded answer."
