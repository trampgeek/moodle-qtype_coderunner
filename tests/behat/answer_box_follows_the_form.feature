@qtype @qtype_coderunner @javascript @answerboxfollowsform @v6fail
Feature: The author's answer box shows what the student will see
  In order to write a question without saving it to find out what it does
  As a teacher
  I need the answer box to follow the settings the form is showing

  # Every scenario here covers a fault an author reported from the test server
  # on 16 November 2026, and each one is a fault no other feature would catch:
  # the suite sets Ace fields and reads them back, which works just as well
  # with no language and no UI as with the right ones. All three are fixed, so
  # all three should pass; they are kept together because they are one subject
  # -- that the answer box shows what the question currently says, not what it
  # said when it was last saved.

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
      | questioncategory | qtype      | name            | template |
      | Test questions   | coderunner | Square function | sqr      |

  # The question overrides no language, so the only way Ace can know it is
  # python3 is through the prototype chain. The editor's mode is what syntax
  # colouring is, and a field handed no language falls back to plain text.
  Scenario: The answer box is syntax coloured for the language the question resolves to
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    Then the ace field "answer" should be highlighting "python3"

  # The UI a box runs is chosen server-side, so changing the question type has
  # to carry the box with it. It did not: core's question_edit_form::set_data()
  # flattens the question's options onto the question and makes them the form's
  # defaults, and those options were resolved against the *stored* prototype --
  # so every inherited field, this one among them, answered for the old
  # prototype until the question was saved. data_preprocessing() re-points them
  # at the chosen chain, which is the one window in which it can be done.
  Scenario: The answer box UI follows a change of question type
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I choose the CodeRunner prototype "python3_scratchpad"
    Then "Scratchpad" "button" should exist
    When I choose the CodeRunner prototype "python3"
    Then "Scratchpad" "button" should not exist

  # A field hidden by its switch went on being read by the template, so a
  # question could be graded on something its author could not see. Anything
  # with content in it is shown now whatever the switch says, because clearing
  # the field is what stops it mattering and that needs the field visible.
  Scenario: A test case field with something in it is shown whatever its switch says
    When I am on the "Square function" "core_question > edit" page logged in as teacher1
    And I override the CodeRunner fields "showtestextra"
    And I set the field "id_showtestextra" to "1"
    And I press "id_submitbutton"
    And I choose "Edit question" action for "Square function" in the question bank
    And I set the field "id_extra_0" to "# this is read by the template"
    And I press "id_submitbutton"
    And I choose "Edit question" action for "Square function" in the question bank
    And I set the field "id_showtestextra" to "0"
    And I press "id_submitbutton"
    And I choose "Edit question" action for "Square function" in the question bank
    Then I should see "# this is read by the template"
