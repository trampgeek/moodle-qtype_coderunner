@qtype @qtype_coderunner @javascript @showdifferences
Feature: Show differences in CodeRunner questions
  A failing question submission should contain a 'Show differences' button
  that highlights a differences between expected and actual answers when
  clicked.

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

  Scenario: As a teacher submitting a wrong answer to a CodeRunner question preview, the Show differences button should work
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n if n != -7 else 12345"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test           |
      | print(sqr(11)) |
    And "print(sqr(-7))" row "Expected" column of "coderunner-test-results" table should contain "49"
    And "print(sqr(-7))" row "Got" column of "coderunner-test-results" table should contain "12345"
    And I should see "Partially correct"
    And I should not see highlighted "9"
    And I should not see highlighted "123"
    And I should not see highlighted "5"
    And I press "Show differences"
    And I should see highlighted "9"
    And I should see highlighted "123"
    And I should see highlighted "5"
    And I press "Hide differences"
    And I should not see highlighted "9"
    And I should not see highlighted "123"
    And I should not see highlighted "5"

  Scenario: As a teacher submitting a wrong answer to a CodeRunner question preview and only hidden tests fail, I should not see the Show differences button
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n if n != -6 else 12345"
    And I press "Check"
    Then I should see "Partially correct"
    And I should see "Your code failed one or more hidden tests"
    And I should not see "Show differences"
