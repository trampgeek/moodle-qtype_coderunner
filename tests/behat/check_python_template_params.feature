@qtype @qtype_coderunner @javascript @pythonpreprocessortest
Feature: Check that Python and other languages can be used instead of Twig as a template params preprocessor and that they processes the STUDENT variable correctly.
  To check that the STUDENT template parameter variables work when a language other than python is the preprocessor
  As a teacher or a student
  I should be able to write a function that prints the seed and my username
  It should be marked right

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
    And the following "activities" exist:
      | activity   | name    | intro                                     | course | idnumber  |
      | quiz       | Quiz 1  | Quiz 1 for testing the Add menu           | C1     | quiz1     |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |

Scenario: if evaluate per student is true, both teacher and student should be able to answer with their own names
    When I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    And I disable UI plugins in the CodeRunner question type
    And I open the "last" add to quiz menu
    And I follow "a new question"
    And I set the field "item_qtype_coderunner" to "1"
    And I press "submitbutton"
    Then I should see "Adding a CodeRunner question"

    And I set the field "id_coderunnertype" to "python3"
    And I set the field "id_customise" to "1"
    And I set the field "id_name" to "Python preprocessor"
    And I set the field "id_questiontext" to "Write a program that prints True if seed parameter provided, then {{ firstname }} {{ lastname }}"
    And I set the field "id_answerboxlines" to "5"
    And I set the field "id_templateparams" to "import sys, json; keyvalues = {param.split('=')[0]: param.split('=')[1] for param in sys.argv[1:]}; print(json.dumps(keyvalues))"
    And I set the field "id_twigall" to "1"
    And I set the field "id_template" to "print({{seed}} > 0, end=' ');  {{STUDENT_ANSWER}}"
    And I set the field "id_templateparamslang" to "python3"
    And I set CodeRunner behat testing flag
    And I set the field "id_templateparamsevalpertry" to "1"
    And I set the field "id_expected_0" to "True {{ firstname }} {{ lastname }}"
    And I set the field "id_uiplugin" to "none"
    And I press "id_submitbutton"
    Then I should see "Python preprocessor Write a program that prints True if seed"

    When I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    When I press "Preview quiz"
    Then I should see "Write a program that prints True if seed parameter provided, then Teacher Last"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('Teacher Last')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Quiz 1" "quiz activity" page logged in as student1
    And I press "Attempt quiz"
    Then I should see "Write a program that prints True if seed parameter provided, then StudentFirst OConnell"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('StudentFirst OConnell')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    And I follow "Attempts: 1"
    And I follow "Review attempt"
    Then I should see "Write a program that prints True if seed parameter provided, then StudentFirst OConnell"
    And I should see "Passed all tests"

Scenario: if evaluate per student is unchecked, the teacher's name is displayed not the student's.
    When I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    And I disable UI plugins in the CodeRunner question type
    And I open the "last" add to quiz menu
    And I follow "a new question"
    And I set the field "item_qtype_coderunner" to "1"
    And I press "submitbutton"
    Then I should see "Adding a CodeRunner question"

    And I set the field "id_coderunnertype" to "python3"
    And I set the field "id_customise" to "1"
    And I set the field "id_name" to "Python preprocessor"
    And I set the field "id_questiontext" to "Write a program that prints True if seed parameter provided, then {{ firstname }} {{ lastname }}"
    And I set the field "id_answerboxlines" to "5"
    And I set the field "id_templateparams" to "import sys, json; keyvalues = {param.split('=')[0]: param.split('=')[1] for param in sys.argv[1:]}; print(json.dumps(keyvalues))"
    And I set the field "id_twigall" to "1"
    And I set the field "id_template" to "print({{seed}} > 0, end=' ');  {{STUDENT_ANSWER}}"
    And I set the field "id_templateparamslang" to "python3"
    And I set CodeRunner behat testing flag
    And I set the field "id_templateparamsevalpertry" to "0"
    And I set the field "id_expected_0" to "True {{ firstname }} {{ lastname }}"
    And I set the field "id_uiplugin" to "none"
    And I press "id_submitbutton"
    Then I should see "Python preprocessor Write a program that prints True if seed"

    When I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    When I press "Preview quiz"
    Then I should see "Write a program that prints True if seed parameter provided, then Teacher Last"

    When I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('Teacher Last')"
    And I press "Check"
    Then I should see "Passed all tests"

    When I log out
    And I am on the "Quiz 1" "quiz activity" page logged in as student1
    And I press "Attempt quiz"
    Then I should see "Write a program that prints True if seed parameter provided, then Teacher Last"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "print('StudentFirst OConnell')"
    And I press "Check"
    Then I should not see "Passed all tests"

    When I log out
    And I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    And I follow "Attempts: 1"
    And I follow "Review attempt"
    Then I should not see "Write a program that prints True if seed parameter provided, then StudentFirst OConnell"
    And I should not see "Passed all tests"
