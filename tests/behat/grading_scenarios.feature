@qtype @qtype_coderunner @javascript @sqrfunctests @coderunnergrading
Feature: Check grading with the Python 3 sqr function CodeRunner question
  To check the CodeRunner questions I created are graded correctly in different submission scenarios
  As a teacher
  I must be able to preview them

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
      | questioncategory | qtype      | name            |
      | Test questions   | coderunner | Square function |
    And I disable UI plugins in the CodeRunner question type

  Scenario: Preview the Python3 sqr function CodeRunner question submit two different wrong answers then the right answer
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n"
    And I press "Check"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return 3"
    And I press "Check"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should see "Mark 24.80 out of 31.00"

  Scenario: Preview the Python3 sqr function CodeRunner question submit the same wrong answer twice then the right answer
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n"
    And I press "Check"
    And I press "Check"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should see "Mark 27.90 out of 31.00"

  Scenario: Preview the Python3 sqr function CodeRunner question precheck, submit the same wrong answer twice, fix, precheck then check
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n"
    And I press "Precheck"
    And I press "Check"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Precheck"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should see "Mark 27.90 out of 31.00"

  Scenario: Preview the Python3 sqr function CodeRunner question precheck, submit the same wrong answer twice, fix, then check
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n"
    And I press "Precheck"
    And I press "Check"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should see "Mark 27.90 out of 31.00"

  Scenario: Preview the Python3 sqr function CodeRunner question precheck a wrong answer then close and submit
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return 0"
    And I press "Precheck"
    And I press "Submit and finish"
    And I should see "Mark 1.00 out of 31.00"
