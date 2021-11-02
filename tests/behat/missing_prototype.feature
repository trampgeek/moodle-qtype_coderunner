@qtype @qtype_coderunner @javascript @prototypetests
Feature: missing_prototype
  In order to deal with missing prototypes
  As a teacher
  I should see an informative error message and be able to fix by editing

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
      | contextlevel | reference | Question category | name           |
      | Course       | C1        | Top               | Test questions |

    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
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
    And I click on "a[aria-controls='id_advancedcustomisationheader']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "python3_test_prototype"
    And I press "id_submitbutton"

    # Now add a new question of type python3_test_prototype
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3_test_prototype            |
      | name              | Prototype tester                  |
      | id_questiontext   | Write the inevitable sqr function |
      | id_customise      | 1                                 |
      | id_uiplugin       | None                              |
      | id_testcode_0     | print(sqr(-11))                   |
      | id_expected_0     | 121                               |
      | id_testcode_1     | print(sqr(9))                     |
      | id_expected_1     | 81                                |

    # Now delete the prototype, leaving the question orphaned
    And I click on "PROTOTYPE_test_prototype" "text"
    And I press "Delete"
    And I press "Delete"

  Scenario: As a teacher, if I preview a question with a missing prototype I should see a missing prototype error
    Given I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

    When I choose "Preview" action for "Prototype tester" in the question bank
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Broken question (missing or duplicate prototype 'python3_test_prototype'). Cannot be run."

  Scenario: As a teacher, I should be able to re-parent the question and have it work correctly
    Given I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    When I choose "Edit question" action for "Prototype tester" in the question bank
    Then I should see "This question was defined to be of type 'python3_test_prototype' but the prototype does not exist, or is non-unique, or is unavailable in this context"
    And I set the field "id_coderunnertype" to "python3"
    And I set the field "id_customise" to "1"
    And I set the field "id_uiplugin" to "None"
    And I press "id_submitbutton"
    And I choose "Preview" action for "Prototype tester" in the question bank
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"
