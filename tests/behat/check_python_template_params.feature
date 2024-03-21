@qtype @qtype_coderunner @javascript @pythonpreprocessortest
Feature: Check that Python and other languages can be used instead of Twig as a template params preprocessor and that they processes the STUDENT variable correctly.
  To check that the STUDENT template parameter variables work when a language other than python is the preprocessor
  As a teacher
  I should be able to write a function that prints the seed and my username it should be marked right

  Background:
    Given the CodeRunner test configuration file is loaded
    And the following "users" exist:
      | username | firstname       | lastname  | email            |
      | teacher1 | Teacher         | Last      | teacher1@asd.com |
      | student1 | Student First   | O'Connell | student@asd.com  |
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
      | id_name                 | Python preprocessor                        |
      | id_questiontext         | Write a program that prints True if seed parameter provided, then {{ firstname }} {{ lastname }}  |
      | id_answerboxlines       | 5                                          |
      | id_validateonsave       | 0                                          |
      | id_templateparams       | {"firstname": "twaddle", "lastname" : "twaddle" } |
      | id_templateparamslang   | None                                       |
      | id_template             |                                            |
      | id_iscombinatortemplate | 0                                          |
      | id_testcode_0           | # Unused                                   |
      | id_expected_0           | True {{ firstname }} {{ lastname }}        |
      | id_uiplugin             | none                                       |

    When I choose "Edit question" action for "Python preprocessor" in the question bank
    And I set the field "id_templateparams" to "import sys, json; keyvalues = {param.split('=')[0]: param.split('=')[1] for param in sys.argv[1:]}; print(json.dumps(keyvalues))"
    And I set the field "id_twigall" to "1"
    And I set the field "id_template" to "print({{seed}} > 0, end=' ');  {{STUDENT_ANSWER}}"
    And I set the field "id_templateparamslang" to "python3"
    And I set CodeRunner behat testing flag
    And I set the field "id_templateparamsevalpertry" to "1"
    And I press "Save changes"

    And I am on the "Test quiz" "mod_quiz > Edit" page logged in as teacher1
    And I open the "last" add to quiz menu
    And I follow "from question bank"
    And I set the field with xpath "//input[@type='checkbox' and @id='qbheadercheckbox']" to "1"
    And I press "Add selected questions to the quiz"
    And I set CodeRunner behat testing flag

  Scenario: Preview as a teacher, submit answer as a student, review as a teacher
    When I am on the "Test quiz" "quiz activity" page
    And I press "Preview quiz"
    Then I should see "Write a program that prints True if seed parameter provided, then Teacher Last"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('Teacher Last')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Test quiz" "quiz activity" page logged in as student1
    And I press "Attempt quiz"
    Then I should see "Write a program that prints True if seed parameter provided, then StudentFirst OConnell"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('StudentFirst OConnell')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Test quiz" "quiz activity" page logged in as teacher1
    And I follow "Attempts: 1"
    And I follow "Review attempt"
    Then I should see "Write a program that prints True if seed parameter provided, then StudentFirst OConnell"
    And I should see "Passed all tests"

  Scenario: Turn off per-try evaluation. Question should fail when attempted by student.
    When I am on the "Course 1" "core_question > course question bank" page
    And I choose "Edit question" action for "Python preprocessor" in the question bank
    And I set the following fields to these values:
      | id_questiontext             | Variant without per-try evaluation  |
    And I set the field "id_templateparamsevalpertry" to "0"
    And I press "id_submitbutton"
    Then I should see "Created by"

    When I am on the "Test quiz" "quiz activity" page
    And I press "Preview quiz"
    Then I should see "Variant without per-try evaluation"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('Teacher Last')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Test quiz" "quiz activity" page logged in as student1
    And I press "Attempt quiz"
    Then I should see "Variant without per-try evaluation"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('StudentFirst OConnell')"
    And I press "Check"
    Then I should see "True StudentFirst OConnell"
    And I should see "True Teacher Last"
    And I should not see "Passed all tests"
