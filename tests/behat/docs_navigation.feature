@qtype @qtype_coderunner @docsnavigationtest
Feature: Navigate the CodeRunner documentation and import example questions
  In order to learn how to author CodeRunner questions
  As a teacher
  I need to browse the docs pages, download example questions and import them into my course

  Background:
    Given the CodeRunner test configuration file is loaded
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Navigate from the docs index to the examples and walkthrough pages
    Given I log in as "teacher1"
    When I visit "/question/type/coderunner/docs.php"
    Then I should see "Coderunner Question Editor"
    When I follow "Example questions"
    Then I should see "Available examples"
    And I should see "01 hello world"
    And "//a[contains(@href, '01-hello-world.xml')]" "xpath_element" should exist
    When I follow "Example walkthroughs"
    Then I should see "Example Walkthroughs"
    When I follow "Question editor reference"
    Then I should see "Coderunner Question Editor"

  Scenario: Import an example question into a course from the examples page
    Given I log in as "teacher1"
    When I visit "/question/type/coderunner/docs.php?page=example_questions.md"
    And I follow "01 hello world"
    Then I should see "Import example: 01 hello world"
    And I should see "Course 1"
    When I set the field "Course" to "Course 1"
    And I press "Import"
    Then I should see "Imported 1 question(s)"
    And I should see "Hello, World!"

  Scenario: A student cannot import example questions
    Given I log in as "student1"
    When I visit "/question/type/coderunner/import_example.php?slug=01-hello-world"
    Then I should see "not able to add questions to any course"
    When I follow "Back to the examples page"
    Then I should see "Available examples"
