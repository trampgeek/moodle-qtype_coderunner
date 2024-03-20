@qtype @qtype_coderunner @javascript @gapfiller
Feature: Test the HTML_UI
  In order to use the HTML UI
  As a teacher
  I should be able specify the required html in either globalextra or prototypeextra

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
      | questioncategory | qtype      | name         | template |
      | Test questions   | coderunner | Print answer | printans |

  Scenario: Edit a CodeRunner printans question into an html UI question
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise   | 1                                 |
      | id_template    | print('''{{ STUDENT_ANSWER }}''') |
      | id_uiplugin    | Html                              |
      | id_expected_0  | {"cr_inputfield":["bubble"]}      |

    And I set the field "id_globalextra" to:
     """
     <p>I'm a line of text</p>
     Field: <input type='text' class='coderunner-ui-element' name='cr_inputfield'/> rest of line
     """

    And I press "id_updatebutton"
    Then "[name='cr_inputfield']" "css_element" should exist

    And I set the field "cr_inputfield" to "bubble"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed"
    And I should see "Created by"

    When I choose "Edit question" action for "Print answer" in the question bank
    And I set the field "id_globalextra" to ""
    And I set the field "id_prototypeextra" to:
     """
     <p>I'm a line of text</p>
     Field: <input type='text' class='coderunner-ui-element' name='cr_inputfield'/> rest of line
     """
    And I set the field "id_uiparameters" to "{\"html_src\": \"prototypeextra\"}"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then "[name='cr_inputfield']" "css_element" should not exist
    Then I should not see "Failed"
    And I should see "Created by"

    When I choose "Preview" action for "Print answer" in the question bank
    And I set the field "cr_inputfield" to "bubble"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I set the field "cr_inputfield" to "bobble"
    And I press "Check"
    Then I should not see "Passed all tests!"
