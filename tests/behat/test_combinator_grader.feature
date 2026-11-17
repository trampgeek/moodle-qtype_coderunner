@qtype @qtype_coderunner @javascript @prototypetests
Feature: test_combinator_grader
  For the ultimate in grading flexibility
  As a teacher
  I must be able to create and use combinator template graders

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
    # The prototype is scaffolding here: what is under test is that a
    # combinator template grader's prologue, epilogue and result table reach
    # the student. Making it through the editor is make_prototype.feature's
    # subject, so it is stated as data. The template is multi-line and so is
    # named rather than quoted - see the generator's resolve_fixture_file().
    And the following "qtype_coderunner > prototypes" exist:
      | name                                     | parent  | course | useace | uiplugin | iscombinatortemplate | grader         | templatefile                   |
      | python3_test_combinator_grader_prototype | python3 | C1     | 0      | none     | 1                    | TemplateGrader | combinator_grader_template.py  |
    And I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I set CodeRunner behat testing flag

    # Now try to add a new question of type python3_test_combinator_prototype.
    # The sample answer has to go in the table below, and the table can only
    # type into a plain textarea -- Ace hides the one it manages. The editor is
    # not this feature's subject, so the UI plugins go away for it.
    And I disable UI plugins in the CodeRunner question type
    And I add a CodeRunner question on "python3_test_combinator_grader_prototype" filling the form with:
      | name              | Combinator prototype grader tester       |
      | id_questiontext   | Write the inevitable sqr function        |
      | id_testcode_0     | sqr(-11)                                 |
      | id_expected_0     | 121                                      |
      | id_ordering_0     | 10                                       |
      | id_testcode_1     | sqr(9)                                   |
      | id_expected_1     | 80                                       |
      | id_ordering_1     | 20                                       |
      | id_answer         | def sqr(n): return n * n                 |

    # The expected output of the second test is deliberately wrong -- sqr(9)
    # is 81 -- so saving is *meant* to fail validation and leave the form
    # standing, with the << button offering the actual output. That only
    # happens if the sample answer is there to be validated, so it is filled
    # in with the rest: the step above saves the form, and a separate step
    # afterwards had no form left to type into.
    Then I should see "Failed testing"
    And I should see "Click on the << button to replace the expected output of this testcase with actual output."

    When I press "<<"
    And I press "id_submitbutton"
    Then I should not see "Save changes"
    And I should not see "Write a sqr function"
    And I should see "Combinator prototype grader tester"

  Scenario: As a teacher, I get marked right (using combinator template) if I submit a correct answer to a CodeRunner question
    When I choose "Preview" action for "Combinator prototype grader tester" in the question bank
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "id_saverestart"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"
    And I should see "I am a prologue"
    And I should see "I am an epilogue"
