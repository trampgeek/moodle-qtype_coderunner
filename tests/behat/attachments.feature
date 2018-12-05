@qtype @qtype_coderunner @javascript
Feature: Test editing and using attachments to a CodeRunner question
  In order to use attachments with my CodeRunner questions
  As a teacher
  I need to enable and configure them, then preview them.

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
      | questioncategory | qtype      | name            | template |
      | Test questions   | coderunner | Square function | sqr      |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration

  Scenario: Require 1 attachment on a CodeRunner question
    When I click on "Edit" "link" in the "Square function" "table_row"
    Then I should not see "Sample answer attachments"
    And I click on "a[aria-controls='id_attachmentoptions']" "css_element"
    And I set the following fields to these values:
      | attachments         | 2      |
      | attachmentsrequired | 1      |
      | filenamesregex      | .*\.py |
      | maxfilesize         | 100 kB |
      | validateonsave      | 1      |
    Then I should see "Sample answer attachments"
    And I press "id_submitbutton"
    Then I should see "Not enough attachments, 1 required."
