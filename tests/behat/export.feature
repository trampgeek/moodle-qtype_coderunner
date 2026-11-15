@qtype @qtype_coderunner @v6triage
Feature: Export CodeRunner questions
  In order to be able to reuse my CodeRunner questions
  As a teacher
  I need to export them

  Background:
    Given the CodeRunner test configuration file is loaded
    And the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name            | template |
      | Test questions   | coderunner | Square function | sqr      |

  Scenario: Export a CodeRunner question
    When I am on the "Course 1" "core_question > course question export" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    # The upper bound was 4600 and a CodeRunner 6 export measured 4615: the file
    # carries the new prototypeid and overrides elements. The exact size varies
    # with the prototype id and the overrides, so the range is deliberately loose;
    # asserting what the export actually contains is M5's business, not a byte count.
    Then following "click here" should download between "4000" and "5200" bytes
    # If the download step is the last in the scenario then we can sometimes run
    # into the situation where the download page causes a http redirect but behat
    # has already conducted its reset (generating an error). By putting a logout
    # step we avoid behat doing the reset until we are off that page.
    And I log out
