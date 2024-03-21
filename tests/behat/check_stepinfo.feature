@qtype @qtype_coderunner @javascript @checkstepinfotest
Feature: Check that the QUESTION.stepinfo record is working.
  To check the QUESTION.stepinfo twig record works
  As a teacher
  I should be able to write a question that gives different feedback for different submissions.

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
      | id_name                 | Testing stepinfo                           |
      | id_questiontext         | Write a program that does anything.        |
      | id_precheck             | 1                                          |
      | id_answerboxlines       | 5                                          |
      | id_penaltyregime        | 10, 20, ...                                |
      | id_validateonsave       | 0                                          |
      | id_template             | print('{{ QUESTION.stepinfo \| json_encode }}') |
      | id_grader               | RegexGrader                                |
      | id_answer               |                                            |
      | id_iscombinatortemplate | 0                                          |
      | id_testcode_0           | # This isn't used                          |
      | id_expected_0           | "numchecks":2,"numprechecks":3,"fraction":0.*,"preferredbehaviour":"deferredfeedback","coderunnerversion":.*  |
      | id_twigall              | false                                      |
    And quiz "Test quiz" contains the following questions:
      | question         | page |
      | Testing stepinfo | 1    |

  Scenario: Click check twice, precheck 3 times, should get expected answer.
    When I am on the "Test quiz" "quiz activity" page
    And I press "Preview quiz"
    Then I should see "Write a program that does anything"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "# Blah 1"
    And I press "Precheck"
    Then I should see "{\"numchecks\":0,\"numprechecks\":0,\"fraction\":0,"
    And I should see "\"coderunnerversion\":"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "# Blah 2"
    And I press "Precheck"
    Then I should see "{\"numchecks\":0,\"numprechecks\":1,\"fraction\":0,"

    And I press "Check"
    Then I should see "{\"numchecks\":0,\"numprechecks\":2,\"fraction\":0,"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "# Blah 3"
    And I press "Check"
    Then I should see "{\"numchecks\":1,\"numprechecks\":2,\"fraction\":0,"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "# Blah 4"
    And I press "Precheck"
    Then I should see "{\"numchecks\":2,\"numprechecks\":2,\"fraction\":0,"
    And I press "Check"
    Then I should see "{\"numchecks\":2,\"numprechecks\":3,\"fraction\":0,"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "# Blah 5"
    And I press "Check"
    Then I should see "{\"numchecks\":3,\"numprechecks\":3,\"fraction\":0.8"
