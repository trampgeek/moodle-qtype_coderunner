@qtype @qtype_coderunner @docslinktest
Feature: Reach the CodeRunner documentation from the question author form
  In order to look up how to author a question while editing one
  As a teacher
  I need a link to the CodeRunner documentation on the question editing form

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

  Scenario: The documentation link is shown on the question editing form
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    Then I should see "CodeRunner Documentation"
    And "//div[contains(@class, 'coderunner-docs-link')]//a[contains(@href, 'docs.php') and contains(@href, 'index.md')]" "xpath_element" should exist

  Scenario: The documentation link opens docs.php and its index page
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I follow "CodeRunner Documentation"
    Then I should see "Coderunner Question Editor"
