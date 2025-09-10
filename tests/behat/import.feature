@qtype @qtype_coderunner
Feature: Import CodeRunner questions
  In order to reuse CodeRunner questions
  As a teacher
  I need to import them

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

  @javascript @_file_upload
  Scenario: Import questions exported from CodeRunner V2.
    When I am on the "Course 1" "core_question > course question import" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/coderunner/tests/fixtures/simpledemoquestions.V2.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 9 questions from file"
    And I should see "1. Write a C function with signature"
    And I should see "9. Write a Python3 program that repeatedly uses"
    And I press "Continue"
    And I should see "C function: sqr"

  @javascript @_file_upload
  Scenario: Import CodeRunner questions exported from V3
    When I am on the "Course 1" "core_question > course question import" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/coderunner/samples/simpledemoquestions.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 15 questions from file"
    And I should see "1. Write a C function with signature"
    And I should see "13. Given a database with (at least) a table"
    And I should see "15. Draw a graph with two nodes A and B"
    And I press "Continue"
    And I should see "C function: sqr"
    And I choose "Edit question" action for "Java Class: bod" in the question bank
    And I set the field "id_customise" to "1"
    And I set the field "id_useace" to "0"
    And I should see "public class __tester__ {" in the "id_template" "field"
