@qtype @qtype_coderunner
Feature: Import CodeRunner questions
  In order to reuse CodeRunner questions
  As a teacher
  I need to import them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | T1        | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I follow "Course 1"

  @javascript @_file_upload
  Scenario: Import CodeRunner questions
    When I navigate to "Import" node in "Course administration > Question bank"
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/coderunner/samples/simpledemoquestions.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 9 questions from file"
    And I should see "1. Write a C function with signature"
    And I should see "9. Write a Python3 program that repeatedly uses"
    And I press "Continue"
    And I should see "C function: sqr"

  @javascript @_file_upload
  Scenario: Import CodeRunner questions exported from V3.0.0.
    When I navigate to "Import" node in "Course administration > Question bank"
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/coderunner/tests/fixtures/simpledemoquestions_V3.0.0.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 12 questions from file"
    And I should see "1. Write a C function with signature"
    And I should see "12. There are three different Java question types built in to CodeRunner"
    And I press "Continue"
    And I should see "C sqr"
    And I click on "Edit" "link" in the "Java Demo Class Question" "table_row"
    And I set the field "useace" to ""
    And I should see "public class __Tester__ {" in the "id_template" "field"
