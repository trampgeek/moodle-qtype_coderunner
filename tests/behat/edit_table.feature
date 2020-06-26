@qtype @qtype_coderunner @javascript
Feature: Test editing a CodeRunner question using the Table UI
  In order to edit a table question
  As a teacher
  I should be able to set the table headers and see the table in the edit form.

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
      | questioncategory | qtype      | name         | template |
      | Test questions   | coderunner | Print answer | printans |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration


  Scenario: Edit a CodeRunner printans question into a table question
    When I choose "Edit question" action for "Print answer" in the question bank
    And I set the following fields to these values:
      | customise      | 1                             |
      | id_template    | print('{{ STUDENT_ANSWER }}') |
      | uiplugin       | Table                         |

    Then I should see "Table UI needs template parameters"

    And I set the following fields to these values:
      | templateparams | {"table_num_columns": 2,"table_num_rows": 2,"table_column_headers": ["Col1", "Col2"]}|
    And I press "id_updatebutton"
    Then I should not see "Table UI needs template parameters"

    And I set the following fields to these values:
      | expected[0]    | Not expected at all |
      | validateonsave | 1                   |

    And I set the field with xpath "//div[@id='id_answer_wrapper']//tbody/tr[1]/td[1]/textarea" to "row0col0"
    And I set the field with xpath "//div[@id='id_answer_wrapper']//tbody/tr[1]/td[2]/textarea" to "row0col1"
    And I set the field with xpath "//div[@id='id_answer_wrapper']//tbody/tr[2]/td[1]/textarea" to "row1col0"
    And I set the field with xpath "//div[@id='id_answer_wrapper']//tbody/tr[2]/td[2]/textarea" to "row1col1"

    And I press "id_updatebutton"
    Then I should see "Failed 1 test(s)"

    And I set the following fields to these values:
      | expected[0]    | [["row0col0","row0col1"],["row1col0","row1col1"]] |

    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"
    And I should see "Last modified by"
