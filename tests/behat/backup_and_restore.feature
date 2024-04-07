@qtype @qtype_coderunner
Feature: Duplicate a course containing a CodeRunner question
  In order re-use my courses containing CodeRunner questions
  As a teacher
  I need to be able to back them up and restore them

  Background:
    Given the CodeRunner test configuration file is loaded
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name            | template |
      | Test questions   | coderunner | Square function | sqr      |
    And the following "activities" exist:
      | activity   | name      | course | idnumber |
      | quiz       | Test quiz | C1     | quiz1    |
    And quiz "Test quiz" contains the following questions:
      | Square function | 1 |
    And the following config values are set as admin:
      | enableasyncbackup | 0 |
    And I am on the "Course 1" "course" page logged in as admin

  @javascript
  Scenario: Backup and restore a course containing a CodeRunner question
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 2 |
    And I navigate to "Question bank" in current page administration
    And I choose "Edit question" action for "Square function" in the question bank
    Then the following fields match these values:
      | Question name                  | Square function                                 |
      | Question text                  | Write a function sqr(n) that returns n squared. |
      | General feedback               | No feedback available for coderunner questions. |
      | Default mark                   | 31                                              |
      | Penalty regime                 | 10, 20, ...                                     |
      | id_testcode_0                  | print(sqr(0))                                   |
      | id_expected_0                  | 0                                               |
      | id_display_0                   | Show                                            |
      | id_mark_0                      | 1                                               |
      | id_ordering_0                  | 10                                              |
      | id_testcode_1                  | print(sqr(1))                                   |
      | id_expected_1                  | 1                                               |
      | id_display_1                   | Show                                            |
      | id_mark_1                      | 2                                               |
      | id_ordering_1                  | 20                                              |
      | id_testcode_2                  | print(sqr(11))                                  |
      | id_expected_2                  | 121                                             |
      | id_display_2                   | Show                                            |
      | id_mark_2                      | 4                                               |
      | id_ordering_2                  | 30                                              |
      | id_testcode_3                  | print(sqr(-7))                                  |
      | id_expected_3                  | 49                                              |
      | id_display_3                   | Show                                            |
      | id_mark_3                      | 8                                               |
      | id_ordering_3                  | 40                                              |
      | id_testcode_4                  | print(sqr(-6))                                  |
      | id_expected_4                  | 36                                              |
      | id_display_4                   | Hide                                            |
      | id_mark_4                      | 16                                              |
      | id_ordering_4                  | 50                                              |
