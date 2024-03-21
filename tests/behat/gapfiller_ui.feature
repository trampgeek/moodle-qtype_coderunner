@qtype @qtype_coderunner @javascript @gapfiller
Feature: Test the GapFiller_UI
  In order to use the GapFiller UI
  As a teacher
  I should be able specify the required gaps in the global extra or test0 fields

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
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name         | template |
      | Test questions   | coderunner | Print answer | printans |

  Scenario: Edit a CodeRunner printans question into a gap-filler question
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | customise      | 1                             |
      | id_template    | print('{{ STUDENT_ANSWER }}') |
      | uiplugin       | Gapfiller                     |
      | id_expected_0  | ["bubble"]                    |

    And I set the field "id_globalextra" to:
     """
     I'm a line of text
     Field: {[10]} rest of line
     """

    And I press "id_updatebutton"

    Then "[name='cr_gapfiller_field']" "css_element" should exist

    And I set the field "cr_gapfiller_field" to "bubble"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"

    When I choose "Edit question" action for "Print answer" in the question bank
    And I set the field "id_globalextra" to:
     """
     I'm a line of text
     Textarea: {[2, 20]} rest of line
     """
    And I set the field "Validate on save" to "0"
    And I press "id_updatebutton"

    Then "[name='cr_gapfiller_field']" "css_element" should exist

    When I set the field "cr_gapfiller_field" to:
     """
     Line 1
     Line 2
     """
    And I set the field "id_expected_0" to:
     """
     Line 1
     Line 2
     """
    And I set the field "id_template" to:
     """
answer = eval('''{{ STUDENT_ANSWER | e('py')}}''')
assert isinstance(answer, list)
for element in answer: print(element)
     """
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"

    When I choose "Preview" action for "Print answer" in the question bank
    And I set the field "cr_gapfiller_field" to:
     """
     Line 1
     Line 2
     """
    And I press "Check"
    Then I should see "Passed all tests!"

  Scenario: Edit and run a gap-filler question using test0 as a source.
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | customise               | 1                             |
      | id_template             | print({{ STUDENT_ANSWER }}[0])|
      | id_iscombinatortemplate | 0                             |
      | uiplugin                | Gapfiller                     |
      | id_expected_0           | bubble                        |
      | id_templateparams       | {"gapfiller_ui_source": "test0"} |
      | id_validateonsave       | 0                             |
      | id_twigall              | 1                             |
      | id_testcode_0           | print("{[10]}")               |

    And I press "id_updatebutton"
    Then I should see "UI parameters can no longer be defined within the template parameters field"

    When I set the field "id_templateparams" to ""
    And I set the field "id_uiparameters" to "{\"ui_source\": \"test0\"}"
    And I press "id_updatebutton"
    Then "[name='cr_gapfiller_field']" "css_element" should exist

    And I set the field "cr_gapfiller_field" to "bubble"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"

    When I choose "Preview" action for "Print answer" in the question bank
    And I set the field "cr_gapfiller_field" to "not a bubble"
    And I press "Check"
    Then I should not see "Passed all tests!"

    And I set the field "cr_gapfiller_field" to "bubble"
    And I press "Check"
    Then I should see "Passed all tests!"
