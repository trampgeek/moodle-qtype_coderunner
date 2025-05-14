@qtype @qtype_coderunner @javascript @customisetests
Feature: Combinator template is called test-by-test if a runtime error occurs when processing CodeRunner questions
  In order to get feedback even when there are runtime errors
  As a student
  I need the combinator template to be used test-by-test if my answer gives a runtime error

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
    And I disable UI plugins in the CodeRunner question type
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3                 |
      | name              | sqr acceptance question |
      | id_questiontext   | Write a sqr function    |
      | id_answerboxlines | 5                       |
      | id_testcode_0     | sqr(-7)                 |
      | id_expected_0     | 49                      |
      | id_testcode_1     | sqr(11)                 |
      | id_expected_1     | 121                     |
      | id_testcode_2     | sqr(-3)                 |
      | id_expected_2     | 9                       |
      | id_display_2      | Hide                    |
    When I choose "Edit question" action for "sqr acceptance question" in the question bank
    And I set the field "id_customise" to "1"
    And I set the field "id_iscombinatortemplate" to "1"

    # Set up a standard combinator template
    And I set the field "id_template" to:
      """
      {{ STUDENT_ANSWER }}
      SEPARATOR = '#<ab@17943918#@>#'
      {% for TEST in TESTCASES %}
      print({{TEST.testcode}})
      {% if not loop.last %}
      print(SEPARATOR)
      {% endif %}
      {% endfor %}
      """
    And I press "id_submitbutton"

  Scenario: As a teacher, I get marked right if I submit a correct answer to a CodeRunner question
    When I choose "Preview" action for "sqr acceptance question" in the question bank
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test    |
      | sqr(-7) |
      | sqr(11) |
    And "sqr(11)" row "Expected" column of "coderunner-test-results" table should contain "121"
    And "sqr(11)" row "Got" column of "coderunner-test-results" table should contain "121"
    And I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"

  Scenario: As a teacher previewing a CodeRunner question, I should see all tests up to one that gives a runtime error then no more
    When I choose "Preview" action for "sqr acceptance question" in the question bank
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n if n != 11 else n[-1]"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
      | Test    |
      | sqr(11) |
    And "sqr(11)" row "Expected" column of "coderunner-test-results" table should contain "121"
    #And I should see "***Error***"  # WHY DOESN'T THIS WORK (with or without &nbsp;)??
    #And the following should not exist in the "coderunner-test-results" table:
    #  | sqr(-3) |
    And I should see "Testing was aborted due to error."
    # And I should see "Show differences" # WHY DOES THIS FAIL with a message found but not visible?
    And I should see "Marks for this submission: 0.00/1.00"
