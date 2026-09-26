@qtype @qtype_coderunner @javascript @infopaneltest
Feature: Collapse the info panel of a CodeRunner question
  In order to reclaim horizontal space while attempting a CodeRunner question
  As a user attempting a CodeRunner question
  I need to be able to hide and restore the question info panel

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

  Scenario: Collapse and restore the question info panel
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    Then ".info .info-toggle-btn" "css_element" should exist
    And ".que.coderunner.info-collapsed" "css_element" should not exist
    When I click on ".info .info-toggle-btn" "css_element"
    Then ".que.coderunner.info-collapsed" "css_element" should exist
    And ".info-toggle-btn[title='Show question info']" "css_element" should exist
    When I click on ".info .info-toggle-btn" "css_element"
    Then ".que.coderunner.info-collapsed" "css_element" should not exist
    And ".info-toggle-btn[title='Hide question info']" "css_element" should exist

  Scenario: The collapsed info panel is remembered when the page is reloaded
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I click on ".info .info-toggle-btn" "css_element"
    And I reload the page
    Then ".que.coderunner.info-collapsed" "css_element" should exist
