@qtype @qtype_coderunner @javascript @prototypetests @walkthrough
Feature: A question type made in one course, used in two
  In order to trust the CodeRunner 6 design end to end
  As a teacher with two courses
  I need to make a question type, build a question on it, inherit from it in
  another course and back again, and be stopped from deleting a type that
  something else is built on

  # From the author's outline of 26 November 2026. Its first half -- a type
  # made in CourseA, a question built on it, the attempt -- passed on
  # 26 November. The inheritance across courses, in both directions, was
  # written when *Inherits from* became the same pair of menus as the question
  # form's question type, and has not been run. The one step still marked
  # "# CHECK" is where the control is a guess rather than something another
  # feature already does.
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

  Scenario: A question type inherited across two courses runs, and what it is built on cannot be deleted
    # ---------------------------------------------------------------- CourseA
    # The question type. The library page is reached by its own page resolver
    # rather than through More > CodeRunner Management > Prototype manager,
    # which is the same destination in two fewer clicks; swap in the menu
    # clicks if the navigation itself is part of what is being tested.
    Given I am on the "CA" "qtype_coderunner > prototype library" page logged in as teacher1
    And I click on "New prototype" "link"
    And I choose the parent prototype "python3"
    And I set the following fields to these values:
      | Name             | python3_do_extra                                 |
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

    # CourseB writes its own question type on top of CourseA's. *Inherits
    # from* is the same pair of menus as the question form's question type, so
    # a parent in another course is one extra choice: the first menu says
    # whose library the second offers, and CourseA is named by its short name,
    # CA, as the menu names it.
    #
    # It overrides nothing. Everything cosc_b_extra does comes from CourseA,
    # which is what makes the question below a test of the chain rather than of
    # CourseB: if the cross-course link broke, the question would run the
    # student's answer instead of the code in Extra.
    When I am on the "CB" "qtype_coderunner > prototype library" page
    And I click on "New prototype" "link"
    And I choose the parent prototype source "CA"
    And I choose the parent prototype "python3_do_extra"
    And I set the following fields to these values:
      | Name             | cosc_b_extra                              |
      | One-line summary | CourseA's extra type, borrowed as a parent |
    And I press "Save as new version"
    Then I should see "CodeRunner prototypes"
    And I should see "cosc_b_extra"

    # A question in CourseB on CourseB's own type, which runs CourseA's
    # template through it.
    And I am on the "CourseB" "core_question > course question bank" page
    And I add a CodeRunner question on "cosc_b_extra" filling the form with:
      | name            | test_b_extra                  |
      | id_questiontext | Executing code in extra       |
      | id_answer       | print("This shouldn't print") |
      | id_extra_0      | print("I'm B's extra stuff")  |
      | id_expected_0   | I'm B's extra stuff           |
    And quiz "QuizB" contains the following questions:
      | question     | page |
      | test_b_extra | 1    |

    And I choose "Preview" action for "test_b_extra" in the question bank
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
    And I should see "I'm B's extra stuff"

    # ------------------------------------------------- back to CourseA's library
    # And the other way round: a type in CourseA built on CourseB's, so the
    # chain crosses courses twice over -- python3 (site), python3_do_extra
    # (CourseA), cosc_b_extra (CourseB), a_from_b (CourseA).
    When I am on the "CA" "qtype_coderunner > prototype library" page
    And I click on "New prototype" "link"
    And I choose the parent prototype source "CB"
    And I choose the parent prototype "cosc_b_extra"
    And I set the following fields to these values:
      | Name             | a_from_b                                  |
      | One-line summary | CourseB's type, inherited back into CourseA |
    And I press "Save as new version"
    Then I should see "CodeRunner prototypes"
    And I should see "a_from_b"

    # CourseB's type is built on CourseA's, so CourseA's may not be deleted --
    # and the reason is the one a dependent *prototype* gives, which is
    # checked before any question's, so it is what an author sees even though
    # test_do_extra uses it too. The button is shown disabled rather than
    # hidden, with the reason on its title, which is how
    # prototype_deletion.feature reads it.
    Then "Delete" "link" should not exist in the "python3_do_extra" "table_row"
    And "//tr[contains(., 'python3_do_extra')]//span[contains(@title, 'derive from it')]" "xpath_element" should exist

    # The new leaf is the end of the chain, so nothing stands in the way of
    # deleting it. The contrast is the point: the guard is about what depends
    # on a prototype, not about prototypes.
    And "Delete" "link" should exist in the "a_from_b" "table_row"
