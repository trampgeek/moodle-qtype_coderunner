@qtype @qtype_coderunner @javascript @_file_upload @v6triage @v6fail
Feature: Test editing and using attachments to a CodeRunner question
  In order to use attachments with my CodeRunner questions
  As a teacher
  I need to enable and configure them, then preview them.

  Background:
    Given the CodeRunner test configuration file is loaded
    And the Jobe server supports "python3"
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
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name            |
      | Test questions   | coderunner | Square function |
    And I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I override the CodeRunner fields "attachments, attachmentsrequired, filenamesregex"
    And I set the field "Answer" to "from sqrmodule import sqr"
    And I set the field "Validate on save" to "1"
    And I set the field "id_attachments" to "1"
    And I set the field "id_attachmentsrequired" to "1"
    And I set the field "id_filenamesregex" to "sqrmodulexx.py"
    And I press "id_submitbutton"
    Then I should see "Not enough attachments, 1 required."
    When I upload "question/type/coderunner/tests/fixtures/sqrmodule.py" file to "Sample answer attachments" filemanager
    And I press "id_submitbutton"
    Then I should see "Disallowed file name(s): sqrmodule.py"
    # The form redisplays with its sections collapsed, and the singular set
    # step does not expand them, so a field in one is not interactable.
    When I expand all fieldsets
    And I set the field "id_filenamesregex" to "sqr[xm]odu.e.p.+"
    # The above line tests with a simple regular expression that sqrmodule.py is accepted
    And I press "id_submitbutton"
    # The question itself, not the words "Question bank": those appear in the
    # navbar of the edit page too, so this passed while the save was still
    # failing and the first real assertion was a scenario away.
    Then "Square function" "table_row" should exist

  @javascript @file_attachments
  Scenario: As a teacher I can preview my question but get an error without attachment.
    When I choose "Preview" action for "Square function" in the question bank
    When I set the field "Answer" to "from sqrmodule import sqr"
    And I press "Check"
    Then I should see "Not enough attachments, 1 required."

  @javascript @file_attachments
  Scenario: As a teacher I can preview my question and get it right with an attachment
    When I choose "Preview" action for "Square function" in the question bank
    And I upload "question/type/coderunner/tests/fixtures/sqrmodule.py" file to "" filemanager
    And I set the field "Answer" to "from sqrmodule import sqr"
    And I press "Check"
    Then I should see "Passed all tests"
