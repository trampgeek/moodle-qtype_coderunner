@qtype @qtype_coderunner @javascript @prototypetests
Feature: make_prototype
  In order to create more sophisticated CodeRunner questions
  As a teacher
  I must be able to create new question types

  # Written from the goal above rather than converted from the CodeRunner 5
  # feature, which made a prototype by setting "Yes (user defined)" on a
  # question. A prototype is no longer a question, so that journey does not
  # exist to convert: it is made in the prototype editor, and the editor is
  # this feature's subject. The second scenario is the journey the suite has
  # never had - change a prototype, watch an existing question change - which
  # is the whole point of the chain.

  Background:
    Given the CodeRunner test configuration file is loaded
    And the Jobe server supports "python3"
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

    # Make the question type.
    And I am on the "C1" "qtype_coderunner > prototype library" page logged in as teacher1
    And I click on "New prototype" "link"
    And I set the following fields to these values:
      | Name          | python3_test_prototype |
      | Inherits from | python3                |
    And I override the CodeRunner fields "useace, uiplugin, template, iscombinatortemplate"
    And I set the following fields to these values:
      | id_useace               | 0    |
      | id_uiplugin             | None |
      | id_iscombinatortemplate | 0    |
    And I set the ace field "template" to:
      """
      {{STUDENT_ANSWER}}

      print({{TEST.testcode}})
      """
    And I press "Save as new version"
    Then I should see "python3_test_prototype"

    # Now a question of that type, which is what the type was made for.
    And I am on the "Course 1" "core_question > course question bank" page
    And I add a CodeRunner question on "python3_test_prototype" filling the form with:
      | name              | Prototype tester                  |
      | id_questiontext   | Write the inevitable sqr function |
      | id_testcode_0     | sqr(-11)                          |
      | id_expected_0     | 121                               |
      | id_testcode_1     | sqr(9)                            |
      | id_expected_1     | 81                                |

  Scenario: A question on a new question type runs on that type's template
    When I choose "Preview" action for "Prototype tester" in the question bank
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "id_saverestart"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"

  Scenario: Editing a question type changes the questions already built on it
    # Nothing touches the question: the only edit is to its type, and the
    # question has to run on the new template from then on. This is what
    # inheritance is for, and what no earlier feature has asked of it.
    When I am on the "python3_test_prototype" "qtype_coderunner > prototype" page
    And I set the ace field "template" to:
      """
      {{STUDENT_ANSWER}}

      print('from version 2:', {{TEST.testcode}})
      """
    And I press "Save as new version"

    # Saving leaves us in the prototype editor, so back to the bank the
    # question lives in before asking for an action on it.
    And I am on the "Course 1" "core_question > course question bank" page
    And I choose "Preview" action for "Prototype tester" in the question bank
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "id_saverestart"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "from version 2: 121"
    And I should not see "Passed all tests!"
