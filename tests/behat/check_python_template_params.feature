
@qtype @qtype_coderunner @javascript @pythonpreprocessortest
Feature: Check that Python can be used instead of Twig as a template params preprocessor and that it processes the STUDENT variable correctly.
  To check that the STUDENT template parameter variables work when python is the preprocessor
  As a teacher
  I should be able to write a function that prints the seed and my username it should be marked right

Background:
    Given the following "users" exist:
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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I disable UI plugins
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype       | python3                                    |
      | id_customise            | 1                                         |
      | id_name                 | STUDENT variable                           |
      | id_questiontext         | Write a program that prints True if seed parameter provided, then {{ username }}  |
      | id_answerboxlines       | 5                                          |
      | id_validateonsave       | 0                                          |
      | id_templateparams       | import sys, json; keyvalues = {param.split('=')[0]: param.split('=')[1] for param in sys.argv[1:]}; print(json.dumps(keyvalues)) |
      | id_templateparamslang   | python                                     |
      | id_template             | print(int("{{seed}}") > 0, "{{username}}")    |
      | id_answer               | # Unused                                   |
      | id_iscombinatortemplate | 0                                          |
      | id_testcode_0           | # Unused                                   |
      | id_expected_0           | True {{ username }}                        |
      | id_twigall              | true                                       |
   And quiz "Test quiz" contains the following questions:
      | question         | page |
      | STUDENT variable | 1    |

  Scenario: Preview as a teacher, submit answer as a student, review as a teacher
    When I am on "Course 1" course homepage
    And I follow "Test quiz"
    And I press "Preview quiz now"
    Then I should see "Write a program that prints True if seed parameter provided, then teacher1"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('teacher1')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test quiz"
    And I press "Attempt quiz"
    Then I should see "Write a program that prints True if seed parameter provided, then student1"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('student1')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test quiz"
    And I follow "Attempts: 1"
    And I follow "Review attempt"
    Then I should see "Write a program that prints True if seed parameter provided, then student1"
    And I should see "Passed all tests"

