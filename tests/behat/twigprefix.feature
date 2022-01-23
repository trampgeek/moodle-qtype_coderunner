
@qtype @qtype_coderunner @javascript @twigprefixtests @_alert
Feature: twigprefix
  When I define a template parameter __twigprefix__ in a prototype
  As a teacher
  I must be able to use the Twig prefix data in a question.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | questioncategory | name          |
      | Course       | C1        | Top              | Behat Testing |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I press "Create a new question ..."
    And I click on "input#item_qtype_coderunner" "css_element"
    And I press "submitbutton"
    And I set the field "id_coderunnertype" to "python3"
    And I set the field "name" to "PROTOTYPE_test_twigprefix"
    And I set the field "id_templateparams" to "print('{\"__twigprefix__\": \"{% macro blah() %}BingleyBeep{% endmacro %}\"}')"
    And I set the field "id_templateparamslang" to "Python3" and dismiss the alert
    And I set the field "id_questiontext" to "Dummy question text"
    And I set the field "id_customise" to "1"
    And I set the field "id_useace" to "0"
    And I set the field "id_uiplugin" to "None"
    And I set the field "id_template" to "{{STUDENT_ANSWER}}"
    And I set the field "id_iscombinatortemplate" to "0"
    And I click on "a[aria-controls='id_advancedcustomisationheader']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "test_twigprefix"
    And I press "id_submitbutton"

    # Now try to add a new question of type test_twigprefix
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | test_twigprefix                   |
      | name              | Prototype tester                  |
      | id_questiontext   | Print BingleyBeep                 |
      | id_testcode_0     |                                   |
      | id_twigall        | 1                                 |
      | id_expected_0     | {{ _self.blah() }}                |

  Scenario: As a teacher, I get marked right (using per-test-case template) if I submit a correct answer to the above question
    When I choose "Preview" action for "Prototype tester" in the question bank
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('BingleyBeep')"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"
