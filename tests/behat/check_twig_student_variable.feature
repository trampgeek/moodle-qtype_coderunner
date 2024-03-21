@qtype @qtype_coderunner @javascript @studentvariabletest
Feature: Check the STUDENT Twig variable allows access to current username in CodeRunner
  To check the STUDENT Twig variable works
  As a teacher
  I should be able to write a function that prints my username it should be marked right

  Background:
    Given the CodeRunner test configuration file is loaded
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student@asd.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name      | course | idnumber |
      | quiz       | Test quiz | C1     | quiz1    |
    And I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I disable UI plugins in the CodeRunner question type
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype       | python3                                    |
      | id_customise            | 1                                          |
      | id_name                 | STUDENT variable                           |
      | id_questiontext         | Write a program that prints {{ STUDENT.username }}  |
      | id_answerboxlines       | 5                                          |
      | id_validateonsave       | 0                                          |
      | id_template             | {{ STUDENT_ANSWER }}                       |
      | id_answer               | print("{{STUDENT.username}}"               |
      | id_iscombinatortemplate | 0                                          |
      | id_testcode_0           | # This isn't used                          |
      | id_expected_0           | {{ STUDENT.username }}                     |
      | id_twigall              | true                                       |
    And quiz "Test quiz" contains the following questions:
      | question         | page |
      | STUDENT variable | 1    |

  Scenario: Preview as a teacher, submit answer as a student, review as a teacher
    When I am on the "Test quiz" "quiz activity" page
    And I press "Preview quiz"
    Then I should see "Write a program that prints teacher1"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('teacher1')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Test quiz" "quiz activity" page logged in as student1
    And I press "Attempt quiz"
    Then I should see "Write a program that prints student1"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('student1')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Test quiz" "quiz activity" page logged in as teacher1
    And I follow "Attempts: 1"
    And I follow "Review attempt"
    Then I should see "Write a program that prints student1"
    And I should see "Passed all tests"
