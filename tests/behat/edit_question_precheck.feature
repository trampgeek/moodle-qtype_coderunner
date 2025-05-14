@qtype @qtype_coderunner @javascript
Feature: edit_question_precheck
  In order to successfully edit CodeRunner questions
  As a teacher
  I should get informative error messages if saving was unsuccessful

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
    And I press "Create a new question ..."
    And I click on "input#item_qtype_coderunner" "css_element"
    And I press "submitbutton"
    And I set the field "id_coderunnertype" to "python3"
    And I set the field "name" to "PROTOTYPE_test_prototype"
    And I set the field "id_questiontext" to "Arbitrary prototype"
    And I set the field "id_customise" to "1"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "PROTOTYPE_test"
    And I press "id_submitbutton"
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3             |
      | name              | Dummy question      |
      | id_questiontext   | Do nothing          |
      | id_testcode_0     | Helloworld          |
      | id_expected_0     | Helloworld          |
    And I disable UI plugins in the CodeRunner question type

  Scenario: As a teacher, I should be warned if question type was not selected
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I press "Create a new question ..."
    And I click on "input#item_qtype_coderunner" "css_element"
    And I press "submitbutton"
    And I set the field "name" to "Trial"
    And I set the field "id_questiontext" to "Trial"
    And I set the field "id_testcode_0" to "Trial"
    And I set the field "id_expected_0" to "Trial"
    And I press "id_submitbutton"
    Then I should see "You must select the type of question"

  Scenario: As a teacher, I should be warned if the template params have invalid JSON
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_templateparams      | {"half":}  |
    And I press "id_submitbutton"
    Then I should see "Template parameters must evaluate to blank or a valid JSON record"

  Scenario: As a teacher, I should be warned if the result columns have invalid JSON
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_customise" to "1"
    And I set the field "resultcolumns" to "notjson"
    And I press "id_submitbutton"
    Then I should see "Result columns field is not a valid JSON string"

  Scenario: As a teacher, I should be warned if prototype name is used already
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_customise" to "1"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "PROTOTYPE_test"
    And I press "id_submitbutton"
    Then I should see "Illegal name for new prototype: already in use"

  Scenario: As a teacher, I should be warned if prototype name is empty if it is a user-defined prototype
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_customise" to "1"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I press "id_submitbutton"
    Then I should see "New question type name cannot be empty"

  Scenario: As a teacher, I should be warned if sandbox time limit is inappropriate
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_customise" to "1"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "cputimelimitsecs" to "notanumber"
    And I press "id_submitbutton"
    Then I should see "CPU time limit must be left blank or must be an integer greater than zero"

  Scenario: As a teacher, I should be warned if sandbox memory limit is inappropriate
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_customise" to "1"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "memlimitmb" to "notanumber"
    And I press "id_submitbutton"
    Then I should see "Memory limit must either be left blank or must be a non-negative integer"

  Scenario: As a teacher, I should be warned if sandbox parameters are not JSON
    When I am on the "Dummy question" "core_question > edit" page logged in as teacher1
    And I set the field "id_customise" to "1"
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "sandboxparams" to "notJson"
    And I press "id_submitbutton"
    Then I should see "'Other' field (sandbox params) must be either blank or a valid JSON record"
