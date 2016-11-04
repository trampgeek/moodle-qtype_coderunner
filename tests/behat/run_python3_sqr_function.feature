@qtype @qtype_coderunner @javascript @sqrfunctests
Feature: Preview the Python 3 sqr function CodeRunner question
  To check the CodeRunner questions I created work
  As a teacher
  I must be able to preview them

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
    And I follow "Course 1"
    And I follow "Question bank"
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3                 |
      | name              | sqr acceptance question |
      | id_questiontext   | Write a sqr function    |
      | id_useace         |                         |
      | id_testcode_0     | print(sqr(-7))          |
      | id_expected_0     | 49                      |
      | id_testcode_1     | print(sqr(11))          |
      | id_expected_1     | 121                     |
      | id_testcode_2     | print(sqr(-3))          |
      | id_expected_2     | 9                       |
      | id_display_2      | Hide                    |

  Scenario: Preview the Python3 sqr function question and get it right
    When I click on "a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test           |
      | print(sqr(-3)) |
      | print(sqr(11)) |
    And "print(sqr(11))" row "Expected" column of "coderunner-test-results" table should contain "121"
    And "print(sqr(11))" row "Got" column of "coderunner-test-results" table should contain "121"
    And I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"

  Scenario: Preview the Python3 sqr function question and submit syntactically invalid answer
    When I click on "a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n); return n * n"
    And I press "Check"
    Then I should see "Syntax Error(s)"
    And I should see "Marks for this submission: 0.00/1.00"

  Scenario: Preview the Python3 sqr function question and git it wrong
    When I click on "a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n * n"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test           |
      | print(sqr(-3)) |
      | print(sqr(11)) |

    And "print(sqr(11))" row "Expected" column of "coderunner-test-results" table should contain "121"
    And "print(sqr(11))" row "Got" column of "coderunner-test-results" table should contain "1331"
    And I should see "Some hidden test cases failed, too."
    And I should see "Your code must pass all tests to earn any marks. Try again."
    And I should see "Marks for this submission: 0.00/1.00"
