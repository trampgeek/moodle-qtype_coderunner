@qtype @qtype_coderunner @javascript @twigprefixtests
Feature: twigprefix
  When I define a template parameter __twigprefix__ in a prototype
  As a teacher
  I must be able to use the Twig prefix data in a question.

  Background:
    Given the CodeRunner test configuration file is loaded
    And the Jobe server supports "python3"
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
    # The prototype is this feature's precondition, not its subject: what is
    # under test is whether a question can use the Twig prefix its prototype
    # defines. So it is stated as data rather than built through the editor,
    # which make_prototype.feature covers.
    And the following "qtype_coderunner > prototypes" exist:
      | name            | parent  | course | templateparamslang | useace | uiplugin | iscombinatortemplate | template           | templateparams                                                             |
      | test_twigprefix | python3 | C1     | python3            | 0      | none     | 0                    | {{STUDENT_ANSWER}} | print('{"__twigprefix__": "{% macro blah() %}BingleyBeep{% endmacro %}"}') |
    And I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I set CodeRunner behat testing flag

    # Now try to add a new question of type test_twigprefix
    And I add a CodeRunner question on "test_twigprefix" filling the form with:
      | name              | Prototype tester                  |
      | id_questiontext   | Print BingleyBeep                 |
      | id_testcode_0     |                                   |
      | id_twigall        | 1                                 |
      | id_expected_0     | {{ _self.blah() }}                |

  Scenario: As a teacher, I get marked right (using per-test-case template) if I submit a correct answer to the above question
    When I choose "Preview" action for "Prototype tester" in the question bank
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "id_saverestart"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('BingleyBeep')"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"
