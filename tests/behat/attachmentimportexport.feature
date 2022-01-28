@qtype @qtype_coderunner @javascript @_file_upload
Feature: Test importing and exporting of question with attachments
  In order to use attachments with my CodeRunner questions
  As a teacher
  I need to be able to import and export them

  Background:
    Given the following "users" exist:
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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I choose "Edit question" action for "Square function" in the question bank
    And I click on "a[aria-controls='id_attachmentoptions']" "css_element"
    And I set the field "Answer" to "from sqrmodule import sqr"
    And I set the field "Validate on save" to "1"
    And I set the field "Allow attachments" to "1"
    And I set the field "Require attachments" to "1"
    And I set the field "filenamesregex" to "sqrmodule.py"
    And I upload "question/type/coderunner/tests/fixtures/sqrmodule.py" file to "Sample answer attachments" filemanager
    And I press "id_submitbutton"
    Then I should see "Question bank"
    And I should see "Last modified by"

  @file_attachments
  Scenario: As a teacher I can export a question with an attached sample answer file
    Given I am on "Course 1" course homepage
    When I navigate to "Question bank > Export" in current page administration
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    Then following "click here" should download between "4600" and "4900" bytes
    # If the download step is the last in the scenario then we can sometimes run
    # into the situation where the download page causes an http redirect but behat
    # has already conducted its reset (generating an error). By putting a logout
    # step we avoid behat doing the reset until we are off that page.
    And I log out

  @file_attachments
  Scenario: As a teacher I can import a question with an attached sample answer file
    Given I am on "Course 1" course homepage
    When I navigate to "Question bank > Import" in current page administration
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
