@qtype @qtype_coderunner @javascript @prototypetests
Feature: make_combinator_prototype
  In order to to create even more sophisticated CodeRunner questions
  As a teacher
  I must be able to create new combinator templates

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
      | contextlevel | reference | questioncategory | name          |
      | Course       | C1        | Top              | Behat Testing |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Question bank" in current page administration
    And I press "Create a new question ..."
    And I click on "input#item_qtype_coderunner" "css_element"
    And I press "submitbutton"
    And I set the field "id_coderunnertype" to "python3"
    And I set the field "name" to "PROTOTYPE_test_combinator_prototype"
    And I set the field "id_questiontext" to "Dummy question text"
    And I set the field "id_customise" to "1"
    And I set the field "id_useace" to "0"
    And I set the field "id_uiplugin" to "None"
    And I set the field "id_template" to:
      """
      {{ STUDENT_ANSWER }}
      {% for TEST in TESTCASES %}
      print({{ TEST.testcode }})
      {% if not loop.last %}
      print('#<ab@17943918#@>#')
      {% endif %}
      {% endfor %}
      """
    And I set the field "id_iscombinatortemplate" to "1"
    And I click on "a[aria-controls='id_advancedcustomisationheader']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "python3_test_combinator_prototype"
    And I press "id_submitbutton"

    # Now try to add a new question of type python3_test_combinator_prototype
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3_test_combinator_prototype |
      | name              | Combinator prototype tester       |
      | id_questiontext   | Write the inevitable sqr function |
      | id_testcode_0     | sqr(-11)                          |
      | id_expected_0     | 121                               |
      | id_testcode_1     | sqr(9)                            |
      | id_expected_1     | 81                                |

  Scenario: As a teacher, I get marked right (using combinator template) if I submit a correct answer to a CodeRunner question
    When I choose "Preview" action for "Combinator prototype tester" in the question bank
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"
