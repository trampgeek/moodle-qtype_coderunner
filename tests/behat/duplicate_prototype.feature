@qtype @qtype_coderunner @javascript @prototypetests @_file_upload
Feature: duplicate_prototypes
  In order to deal with duplicate prototypes
  As a teacher
  I should see an informative error message and be able to fix by editing the duplicates

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
      | contextlevel | reference | Question category | name           |
      | Course       | C1        | Top               | Test questions |

    # Upload the prototype_c_via_python_v1.xml in samples initially
    And I am on the "Course 1" "core_question > course question import" page logged in as teacher1
    And I upload "question/type/coderunner/tests/fixtures/prototype_c_via_python_v1.xml" file to "Import" filemanager
    And I set the field "id_format_xml" to "1"
    And I press "id_submitbutton"
    And I press "Continue"

    # Edit the prototype name to something else
    And I am on the "DEMO_PROTOTYPE_C_using_python" "core_question > edit" page logged in as teacher1
    And I click on "a[aria-controls='id_advancedcustomisationheadercontainer']" "css_element"
    And I set the field "typename" to "python3_duplicate"
    And I press "id_submitbutton"

    # Upload the prototype_c_via_python_v2.xml in samples to make a duplicate
    And I am on the "Course 1" "core_question > course question import" page logged in as teacher1
    And I upload "question/type/coderunner/tests/fixtures/prototype_c_via_python_v2.xml" file to "Import" filemanager
    And I set the field "id_format_xml" to "1"
    And I press "id_submitbutton"
    And I press "Continue"

    # Now delete the latest version of the first prototype, leaving you with two identical prototypes
    # Semantics of delete changed between Moodle 4.1 and 4.2 so need to go via history now.
    #And I wait "180" seconds
    And I am on the "DEMO_duplicate_prototype" "core_question > edit" page logged in as teacher1
    And I press "Cancel"
    And I choose "History" action for "DEMO_PROTOTYPE_C_using_python" in the question bank
    And I click on "table#categoryquestions tr.r1 td.checkbox input" "css_element"
    And I click on "button#bulkactionsui-selector" "css_element"
    And I click on "input.dropdown-item[name='deleteselected']" "css_element"
    And I press "Delete"

  Scenario: As a teacher, if I edit a question with a duplicate prototype I should see a duplicate prototype error
    When I am on the "DEMO_duplicate_prototype" "core_question > edit" page logged in as teacher1
    And I should see "This question was defined to be of type 'c_via_python' but the prototype is non-unique in the following questions:"
    And I should see "Name: DEMO_PROTOTYPE_C_using_python"
    Then I should see "Name: DEMO_duplicate_prototype"

  Scenario: As a teacher, I should be warned if the prototype is duplicated when making a new question
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I press "Create a new question ..."
    And I click on "input#item_qtype_coderunner" "css_element"
    And I press "submitbutton"
    And I set the field "id_coderunnertype" to "c_via_python" and dismiss the alert
    And I should see "Reverted to question type: 'Undefined'"
    And I should see "Could not load question type 'c_via_python' as the prototype is non-unique in the following questions:"
    And I should see "Name: DEMO_PROTOTYPE_C_using_python"
    Then I should see "Name: DEMO_duplicate_prototype"

  Scenario: As a teacher, I should be able to fix the duplicate prototype by renaming it
    When I choose "Delete" action for "DEMO_PROTOTYPE_C_using_python" in the question bank
    And I press "Delete"
    And I am on the "DEMO_duplicate_prototype" "core_question > edit" page logged in as teacher1
    Then I should not see "This question was defined to be of type 'c_via_python' but the prototype is non-unique in the following questions:"

  Scenario: As a teacher, I should not be allowed to save a duplicated prototype
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I press "Create a new question ..."
    And I click on "input#item_qtype_coderunner" "css_element"
    And I press "submitbutton"
    And I set the field "id_coderunnertype" to "c_via_python" and dismiss the alert
    And I set the field "name" to "question"
    And I set the field "id_questiontext" to "Question text"
    And I set the field "id_testcode_0" to "null"
    And I set the field "id_expected_0" to "null"
    And I should see "Reverted to question type: 'Undefined'"
    And I press "id_submitbutton"
    Then I should see "You must select the type of question"
