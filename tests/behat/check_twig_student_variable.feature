@qtype @qtype_coderunner @javascript @studentvariabletest
Feature: Check the STUDENT Twig variable allows access to current username in CodeRunner
  To check the STUDENT Twig variable works
  As a teacher
  I should be able to write a function that prints my username it should be marked right

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
    And I log in as "teacher1"
    And I follow "C1"
    And I navigate to "Question bank" node in "Course administration"
    And I disable UI plugins
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype       | python3                                    |
      | id_customise            | 1                                          |
      | id_name                 | STUDENT variable                           |
      | id_questiontext         | Student answer is actually ignored!        |
      | id_answerboxlines       | 5                                          |
      | id_validateonsave       | 0                                          |
      | id_template             | print("{{STUDENT.username}}" == "teacher1")|
      | id_iscombinatortemplate | 0                                          |
      | id_testcode_0           | # This isn't used                          |
      | id_expected_0           | True                                       |
    
  Scenario: Preview the STUDENT variable function question and check it is marked right in CodeRunner
    When I click on "Preview" "link" in the "STUDENT variable" "table_row"
    And I switch to "questionpreview" window
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "blah blah blah"
    And I press "Check"
    And I should see "Passed all tests!"



