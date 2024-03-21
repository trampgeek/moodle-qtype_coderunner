@qtype @qtype_coderunner @javascript
Feature: template_params_error
  In order to successfully edit CodeRunner question template parameters
  As a teacher
  I should get informative template parameter error messages

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
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3             |
      | name              | Dummy question      |
      | id_questiontext   | Do nothing          |
      | id_testcode_0     | Helloworld          |
      | id_expected_0     | Helloworld          |
    And I disable UI plugins in the CodeRunner question type

  Scenario: As a teacher, I should be given an informative Twig error
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_templateparamslang" to "twig"
    And I set the following fields to these values:
      | id_templateparams      | {{ /error }} |
    And I should not see "Unexpected token"
    And I press "id_submitbutton"
    And I should see "Template parameters must evaluate to blank or a valid JSON record"
    Then I should see "Unexpected token"

  Scenario: As a teacher, I should be given an informative Python error
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_templateparamslang" to "python3"
    And I set the following fields to these values:
      | id_templateparams      | print("error) |
    And I should not see "SyntaxError"
    And I press "id_submitbutton"
    And I should see "Template parameters must evaluate to blank or a valid JSON record"
    Then I should see "SyntaxError"

  Scenario: As a teacher, I should be given an informative C error
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_templateparamslang" to "c"
    And I set the following fields to these values:
      | id_templateparams      | #include <stdio |
    And I should not see "missing terminating > character"
    And I press "id_submitbutton"
    And I should see "Template parameters must evaluate to blank or a valid JSON record"
    Then I should see "error: missing terminating > character"

  Scenario: As a teacher, I should be given an informative Java error
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_templateparamslang" to "java"
    And I set the following fields to these values:
      | id_templateparams      | public static void main(String[] ar |
    And I should not see "prog.java:1: error:"
    And I press "id_submitbutton"
    And I should see "Template parameters must evaluate to blank or a valid JSON record"
    Then I should see "NO_PUBLIC_CLASS_FOUND.java"

  Scenario: As a teacher, I should be given an informative php error
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_templateparamslang" to "php"
    And I set the field "id_templateparams" to:
      """
      <?php
      echo "Hello wo
      ?>
      """
    And I should not see "PHP Parse error:"
    And I press "id_submitbutton"
    And I should see "Template parameters must evaluate to blank or a valid JSON record"
    Then I should see "PHP Parse error:"

  Scenario: As a teacher, I should be given an informative Octave error
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_templateparamslang" to "octave"
    And I set the following fields to these values:
      | id_templateparams      | component = [1 3 |
    And I should not see "Run error"
    And I press "id_submitbutton"
    And I should see "Template parameters must evaluate to blank or a valid JSON record"
    Then I should see "Run error"

  Scenario: As a teacher, I should be given an informative Pascal error
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_templateparamslang" to "pascal"
    And I set the following fields to these values:
      | id_templateparams      | program AProgram(output |
    And I should not see "Fatal: Syntax error"
    And I press "id_submitbutton"
    And I should see "Template parameters must evaluate to blank or a valid JSON record"
    Then I should see "Fatal: Syntax error"
