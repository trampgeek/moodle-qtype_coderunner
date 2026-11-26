@qtype @qtype_coderunner @javascript @prototypetests @walkthrough
Feature: A question type made in one course, used in two
  In order to trust the CodeRunner 6 design end to end
  As a teacher with two courses
  I need to make a question type, build a question on it, borrow it from
  another course, and be stopped from deleting it while questions use it

  # From the author's outline of 26 November 2026, and passing as of that date.
  # The one step still marked "# CHECK" is where the control is a guess rather
  # than something another feature already does.
  #
  # Why one long scenario rather than several: the point is the journey, and
  # each stage depends on the last. A prototype has to exist before a question
  # can be built on it, a question has to exist before another course can
  # borrow its type, and both have to exist before the deletion guard has
  # anything to refuse.

  Background:
    Given the CodeRunner test configuration file is loaded
    And the Jobe server supports "python3"
    And the CodeRunner webservice is enabled
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | CourseA  | CA        | 0        |
      | CourseB  | CB        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | CA     | editingteacher |
      | teacher1 | CB     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | questioncategory | name          |
      | Course       | CA        | Top              | Behat Testing |
      | Course       | CB        | Top              | Behat Testing |

  Scenario: A borrowed question type runs in both courses and cannot be deleted
    # ---------------------------------------------------------------- CourseA
    # The question type. The library page is reached by its own page resolver
    # rather than through More > CodeRunner Management > Prototype manager,
    # which is the same destination in two fewer clicks; swap in the menu
    # clicks if the navigation itself is part of what is being tested.
    Given I am on the "CA" "qtype_coderunner > prototype library" page logged in as teacher1
    And I click on "New prototype" "link"
    And I set the following fields to these values:
      | Name             | python3_do_extra                                 |
      | Inherits from    | python3                                          |
      | One-line summary | Executes whatever code is in extra (1 test case) |

    # Each inheritable field has to have its Inherit box cleared before its
    # control can be set, so a field this type changes is named here before it
    # is set below.
    And I override the CodeRunner fields "template, iscombinatortemplate"
    And I set the ace field "template" to:
      """
      {{ TEST.extra }}
      """
    And I set the following fields to these values:
      | id_iscombinatortemplate | 0 |
    And I press "Save as new version"
    Then I should see "CodeRunner prototypes"
    And I should see "python3_do_extra"

    # The quiz. Created as data rather than through core's activity form,
    # which is not what this feature is about -- and the step that puts the
    # question into it, below, *requires* this: core's "quiz ... contains the
    # following questions" calls quiz_add_quiz_question(), a function in
    # mod/quiz/locallib.php. Nothing loads that file into the Behat CLI
    # process except mod_quiz's own generator, so a quiz made by clicking
    # through the form leaves that step dying on "Call to undefined function
    # quiz_add_quiz_question()".
    Given the following "activities" exist:
      | activity   | name  | course | idnumber |
      | quiz       | QuizA | CA     | quizA    |

    # The question, built on the new type. One step, because choosing a
    # prototype rebuilds the form and discards anything filled in before it.
    And I am on the "CourseA" "core_question > course question bank" page
    And I add a CodeRunner question on "python3_do_extra" filling the form with:
      | name            | test_do_extra                 |
      | id_questiontext | Executing code in extra       |
      | id_answer       | print("This shouldn't print") |
      | id_extra_0      | print("I'm extra stuff")      |
      | id_expected_0   | I'm extra stuff               |
    And quiz "QuizA" contains the following questions:
      | question      | page |
      | test_do_extra | 1    |

    # The attempt. Previewed from the question bank, which is what every other
    # feature in this suite does and needs no quiz attempt; the author asked
    # for the preview *in the quiz*, which would be "I am on the QuizA
    # mod_quiz > View page" and then Preview quiz. # CHECK
    And I choose "Preview" action for "test_do_extra" in the question bank
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "id_saverestart"
    And I set the ace field "answer" to:
    """
    print("This shouldn't print either")
    """
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should see "Marks for this submission: 1.00/1.00"

    # The type ran the code in Extra and ignored the student's answer, which
    # is the whole of what python3_do_extra claims to do.
    And I should see "I'm extra stuff"

    # ---------------------------------------------------------------- CourseB
    # QuizB, as data, for the same reason as QuizA above.
    Given the following "activities" exist:
      | activity   | name  | course | idnumber |
      | quiz       | QuizB | CB     | quizB    |

    # The same type, borrowed. Two menus: the first says whose prototypes the
    # second offers, so borrowing is one extra choice rather than a step of
    # its own. CourseA is named by its short name, CA, as the menu names it.
    When I am on the "CourseB" "core_question > course question bank" page
    And I press "Create a new question ..."
    And I set the field "CodeRunner" to "1"
    And I click on ".submitbutton" "css_element"

    And I choose the CodeRunner prototype source "CA"
    And I choose the CodeRunner prototype "python3_do_extra"

    # The form rebuilds on that choice, so everything else is filled after it.
    And I set the following fields to these values:
      | name            | test_do_extra2                           |
      | id_questiontext | Executing code in extra from Course A    |
      | id_extra_0      | print("I'm more extra stuff")            |
      | id_expected_0   | I'm more extra stuff                     |
    And I set the ace field "answer" to "print('This should not print either')"
    And I press "id_submitbutton"
    Then I should see "test_do_extra2"

    And quiz "QuizB" contains the following questions:
      | question       | page |
      | test_do_extra2 | 1    |
    And I choose "Preview" action for "test_do_extra2" in the question bank
    And I click on "a[aria-controls='id_attemptoptionsheadercontainer']" "css_element"
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "id_saverestart"
    And I set the ace field "answer" to:
    """
    print("This shouldn't print either")
    """
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should see "Marks for this submission: 1.00/1.00"
    And I should see "I'm more extra stuff"

    # ------------------------------------------------- back to CourseA's library
    # Two courses' questions now depend on the type, so it may not be deleted.
    # The button is shown disabled rather than hidden, with the reason on its
    # title, which is how prototype_deletion.feature reads it.
    When I am on the "CA" "qtype_coderunner > prototype library" page
    Then I should see "python3_do_extra"
    And "Delete" "link" should not exist in the "python3_do_extra" "table_row"
    And "//tr[contains(., 'python3_do_extra')]//span[contains(@title, 'question(s) use it')]" "xpath_element" should exist
