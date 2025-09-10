@qtype @qtype_coderunner @javascript @quizvariabletest
Feature: Check the QUIZ Twig variable allows access to the current quiz name and tags.
  To check the QUIZ Twig variable works
  As a teacher
  I should be able to write a function that uses the quiz name in the question spec.

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
    And I am on the "Test quiz" "quiz activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Tags" to "one, two"
    And I press "Save and display"
    And I am on the "Course 1" "core_question > course question bank" page
    And I disable UI plugins in the CodeRunner question type
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype       | python3                                    |
      | id_customise            | 1                                          |
      | id_name                 | QUIZ variable                              |
      | id_questiontext         | Quiz is {{ QUIZ.name }}, tags are {{ QUIZ.tags \| json_encode}} |
      | id_answerboxlines       | 5                                          |
      | id_validateonsave       | 0                                          |
      | id_template             | # Unused                                   |
      | id_answer               | # Unused                                   |
      | id_iscombinatortemplate | 0                                          |
      | id_testcode_0           | # Unused                                   |
      | id_expected_0           | OK though we never run this Q              |
      | id_twigall              | true                                       |
    And quiz "Test quiz" contains the following questions:
      | question         | page |
      | QUIZ variable    | 1    |

  Scenario: Preview as a teacher, submit answer as a student, review as a teacher. Quiz name is empty on preview.
    When I am on the "QUIZ variable" "core_question > preview" page
    Then I should see "Quiz is , tags are []"

    When I log out
    And I am on the "Test quiz" "quiz activity" page logged in as student1
    And I press "Attempt quiz"
    Then I should see "Quiz is Test quiz, tags are [\"one\",\"two\"]"

