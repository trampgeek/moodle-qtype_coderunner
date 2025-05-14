@qtype @qtype_coderunner @javascript @prototypetests
Feature: make_prototype
  In order to to create more sophisticated CodeRunner questions
  As a teacher
  I must be able to create new question templates

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
      | contextlevel | reference | questioncategory | name          |
      | Course       | C1        | Top              | Behat Testing |
    And I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I press "Create a new question ..."
    And I click on "input#item_qtype_coderunner" "css_element"
    And I press "submitbutton"
    And I set the field "id_coderunnertype" to "python3"
    And I set the field "name" to "PROTOTYPE_test_prototype"
    And I set the field "id_questiontext" to "Dummy question text"
    And I set the field "id_customise" to "1"
    And I set the field "id_useace" to "0"
    And I set the field "id_uiplugin" to "None"
    And I set the field "id_template" to:
      """
      {{STUDENT_ANSWER}}

      print({{TEST.testcode}})
      """
    And I set the field "id_iscombinatortemplate" to "0"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "python3_test_prototype"
    And I press "id_submitbutton"

    # Now try to add a new question of type python3_test_prototype
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3_test_prototype            |
      | name              | Prototype tester                  |
      | id_questiontext   | Write the inevitable sqr function |
      | id_testcode_0     | sqr(-11)                          |
      | id_expected_0     | 121                               |
      | id_testcode_1     | sqr(9)                            |
      | id_expected_1     | 81                                |

  Scenario: As a teacher, I get marked right (using per-test-case template) if I submit a correct answer to a CodeRunner question
    When I choose "Preview" action for "Prototype tester" in the question bank
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "id_saverestart"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"

  Scenario: As a teacher, I should not be allowed to edit the question type if it IS a user-defined prototype
    When I am on the "PROTOTYPE_test_prototype" "core_question > edit" page logged in as teacher1
    Then I should see "This is a prototype; cannot change question type"

  Scenario: As a teacher, I should be allowed to edit the question type if it USES a user-defined prototype
    When I am on the "Prototype tester" "core_question > edit" page logged in as teacher1
    And I should see "python3_test_prototype"
    And I set the field "id_coderunnertype" to "python3"
    Then I should not see "This is a prototype; cannot change question type"

  Scenario: As a teacher, I should be able to toggle the prototyping off and be able to edit the question type
    When I am on the "PROTOTYPE_test_prototype" "core_question > edit" page logged in as teacher1
    And I should see "This is a prototype; cannot change question type"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "prototypetype" to "No"
    And I set the field "id_coderunnertype" to "python3" and dismiss the alert
    Then I should not see "This is a prototype; cannot change question type"

  Scenario: As a teacher, when I try to create the prototype with empty Sandbox language I should see the validation error
    Given I am on the "PROTOTYPE_test_prototype" "core_question > edit" page logged in as teacher1
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    When I set the field "language" to ""
    And I press "id_submitbutton"
    Then I should see "Sandbox language cannot be empty when creating a prototype."
    And I set the field "language" to "python3"
    And I press "id_submitbutton"
    And I should see "Question bank"
