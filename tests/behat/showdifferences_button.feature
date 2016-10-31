@qtype @qtype_coderunner @javascript @showdifferences
Feature: showdifferences_button
  A failing question submission should contain a 'Show differences' button
  that highlights a differences between expected and actual answers when
  clicked.

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
      | id_display_1      | Show                    |
      | id_testcode_2     | print(sqr(-3))          |
      | id_expected_2     | 9                       |
      | id_display_2      | Hide                    |

  Scenario: As a teacher submitting a wrong answer to a question preview, I should see the Show differences button and it should work
    When I click on "a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n if n != -7 else 12345"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test           |
      | print(sqr(-3)) |
      | print(sqr(11)) |
    And "print(sqr(-7))" row "Expected" column of "coderunner-test-results" table should contain "49"
    And "print(sqr(-7))" row "Got" column of "coderunner-test-results" table should contain "12345"
    And I should not see highlighted "9"
    And I should not see highlighted "123"
    And I should not see highlighted "5"
    And I press "Show differences"
    And I should see highlighted "9"
    And I should see highlighted "123"
    And I should see highlighted "5"
    And I press "Hide differences"
    And I should not see highlighted "9"
    And I should not see highlighted "123"
    And I should not see highlighted "5"

  Scenario: As a teacher submitting a wrong answer to a question preview, if my submission fails only hidden tests I should not see the Show differences button
    When I click on "a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n if n != -3 else 12345"
    Then I should not see "Show differences"
