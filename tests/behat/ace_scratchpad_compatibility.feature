@qtype @javascript @qtype_coderunner @scratchpad
Feature: Ace UI convert to Scratchpad UI questions with one click
  In order to use the Scratchpad UI
  As a teacher
  I should be able to change a question from using Ace to Scratchpad in one click

  Background:
    Given the CodeRunner test configuration file is loaded
    And the CodeRunner webservice is enabled
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

    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise | 1                        |
      | id_answer    | def sqr(n): return n * n |

  Scenario: Edit a CodeRunner Ace UI question, changing it to be a Scratchpad UI question.
    When I set the field "id_uiplugin" to "Scratchpad"
    Then I should see "def sqr(n): return n * n"
    And I click on "Scratchpad" "button"
    And I should see "Run"
    And I should see "Prefix with Answer"

    When I press "id_submitbutton"
    Then I should see "Square function"

    When I choose "Preview" action for "Square function" in the question bank
    And I set the ace field "answer_code" to "def sqr(n): return n * n"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test    |
      | sqr(-7) |
      | sqr(11) |
    And I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Correct"

    When I press "Close preview"
    Then I should see "Square function"

    When I choose "Edit question" action for "Square function" in the question bank
    And I set the field "id_uiplugin" to "Ace"
    And I should see "def sqr(n): return n * n"

    When I press "id_submitbutton"
    Then I should see "Square function"

    When I choose "Preview" action for "Square function" in the question bank
    Then I should not see "Scratchpad"

    When I set the ace field "_answer" to "def sqr(n): return n * n"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test    |
      | sqr(-7) |
      | sqr(11) |
    And I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Correct"
