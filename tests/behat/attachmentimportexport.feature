@qtype @qtype_coderunner @javascript @_file_upload
Feature: Test importing and exporting of question with attachments
  In order to use attachments with my CodeRunner questions
  As a teacher
  I need to be able to import and export them

  Background:
    Given the CodeRunner test configuration file is loaded
    And the CodeRunner webservice is enabled
    And the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name            |
      | Test questions   | coderunner | Square function |
    And I am on the "Square function" "core_question > edit" page logged in as teacher
    And I click on "a[aria-controls='id_attachmentoptionscontainer']" "css_element"
    And I set the field "Answer" to "from sqrmodule import sqr"
    And I set the field "Validate on save" to "1"
    And I set the field "Allow attachments" to "1"
    And I set the field "Require attachments" to "1"
    And I set the field "filenamesregex" to "sqrmodule.py"
    And I upload "question/type/coderunner/tests/fixtures/sqrmodule.py" file to "Sample answer attachments" filemanager
    And I press "id_submitbutton"
    Then I should see "Question bank"

  @file_attachments
  Scenario: As a teacher I can export a question with an attached sample answer file
    When I am on the "Course 1" "core_question > course question export" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    Then following "click here" should download between "4500" and "5000" bytes
    # If the download step is the last in the scenario then we can sometimes run
    # into the situation where the download page causes an http redirect but behat
    # has already conducted its reset (generating an error). By putting a logout
    # step we avoid behat doing the reset until we are off that page.
    And I log out

  @file_attachments
  Scenario: As a teacher I can import a question with an attached sample answer file
    When I am on the "Course 1" "core_question > course question import" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I upload "question/type/coderunner/tests/fixtures/sqrexportwithsampleattachment.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 1 questions from file"
    And I press "Continue"
    And I should see "Python3 sqr function with an attachment"
    And I choose "Edit question" action for "Python3 sqr function with an attachment" in the question bank
    And I press "id_submitbutton"
    # Sample question has validate on save set, so should be checked on save
    Then I should see "Question bank"
    And I should see "Python3 sqr function with an attachment"
