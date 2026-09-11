@qtype @qtype_coderunner @javascript @layoutswitchertest
Feature: Switch the layout of a CodeRunner question
  In order to arrange the question text and answer box to suit my screen
  As a user attempting a CodeRunner question
  I need to be able to switch between stacked and side-by-side layouts

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

  Scenario: The layout defaults to stacked with both layout buttons shown
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    Then ".coderunner-layout-btn[title='Stacked']" "css_element" should exist
    And ".coderunner-layout-btn[title='Side by side']" "css_element" should exist
    And ".coderunner-layout-btn[title='Stacked'].active" "css_element" should exist
    And ".que.coderunner.layout-split" "css_element" should not exist

  Scenario: Switch to the side-by-side layout and back to stacked
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I click on ".coderunner-layout-btn[title='Side by side']" "css_element"
    Then ".que.coderunner.layout-split" "css_element" should exist
    And ".coderunner-layout-btn[title='Side by side'].active" "css_element" should exist
    And ".coderunner-layout-btn[title='Stacked'].active" "css_element" should not exist
    When I click on ".coderunner-layout-btn[title='Stacked']" "css_element"
    Then ".que.coderunner.layout-split" "css_element" should not exist
    And ".coderunner-layout-btn[title='Stacked'].active" "css_element" should exist
    And ".coderunner-layout-btn[title='Side by side'].active" "css_element" should not exist

  Scenario: The chosen layout is remembered when the page is reloaded
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I click on ".coderunner-layout-btn[title='Side by side']" "css_element"
    And I reload the page
    Then ".que.coderunner.layout-split" "css_element" should exist
    And ".coderunner-layout-btn[title='Side by side'].active" "css_element" should exist

  Scenario: Dragging the divider resizes the question and answer boxes
    When I am on the "Square function" "core_question > preview" page logged in as teacher1
    And I click on ".coderunner-layout-btn[title='Side by side']" "css_element"
    Then ".que.coderunner .formulation .divider" "css_element" should exist
    # Switching to side-by-side clears any inline flex sizing, so a flex style
    # appearing on the question box proves the drag handler resized it.
    When I drag the CodeRunner divider by "80" pixels
    Then ".que.coderunner .question_box[style*='flex']" "css_element" should exist
