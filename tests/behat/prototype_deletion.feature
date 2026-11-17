@qtype @qtype_coderunner @javascript @prototypetests @prototypedeletion @v6fail
Feature: Delete a prototype that nothing depends on
  In order to keep a course's library of question types tidy
  As a teacher
  I need to delete a prototype I no longer want, and to be stopped when
  something still depends on it

  # The guards matter more than the button. A question holds its prototype's
  # id, so deleting one out from under a question would leave that question
  # unresolvable -- the state the additive design exists to prevent -- and a
  # prototype with children sits in the middle of someone else's chain.
  #
  # The decision rule itself is pinned by prototype_repository_test.php. What
  # is here is the journey: that the action is offered where it is allowed,
  # withheld where it is not, and that confirming and cancelling both do what
  # they say.

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
    And the following "qtype_coderunner > prototypes" exist:
      | name           | course | parent         |
      | cosc131_spare  | C1     | python3        |
      | cosc131_parent | C1     | python3        |
      | cosc131_child  | C1     | cosc131_parent |

  Scenario: A prototype nothing depends on can be deleted
    When I am on the "C1" "qtype_coderunner > prototype library" page logged in as teacher1
    Then I should see "cosc131_spare"
    When I click on "Delete" "link" in the "cosc131_spare" "table_row"
    Then I should see "Warning - this will delete all"
    And I should see "irrecoverably"
    When I press "Continue"
    Then I should see "was deleted"
    # Not "I should not see", because the success notice quotes the name it
    # has just deleted. The row going is what the assertion is about.
    And "cosc131_spare" "table_row" should not exist

  Scenario: Cancelling the confirmation leaves the prototype alone
    When I am on the "C1" "qtype_coderunner > prototype library" page logged in as teacher1
    And I click on "Delete" "link" in the "cosc131_spare" "table_row"
    And I press "Cancel"
    Then I should see "cosc131_spare"

  Scenario: A prototype other prototypes derive from cannot be deleted
    When I am on the "C1" "qtype_coderunner > prototype library" page logged in as teacher1
    Then I should see "cosc131_child"
    And "Delete" "link" should not exist in the "cosc131_parent" "table_row"
    # The reason rides on the disabled button's title, so it is matched there
    # rather than as page text. The row's own child count says the same thing
    # in plain sight.
    And "//tr[contains(., 'cosc131_parent')]//span[contains(@title, 'other prototypes derive from it')]" "xpath_element" should exist

  # The guard that prevents a question being orphaned. The question is added
  # through the form, because that is the only way to put a question on a
  # named prototype, and it is what an author does anyway.
  Scenario: A prototype questions depend on cannot be deleted
    Given the Jobe server supports "python3"
    And I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I add a CodeRunner question on "cosc131_spare" filling the form with:
      | name            | Depends on the spare type         |
      | id_questiontext | Write the inevitable sqr function |
      | id_testcode_0   | print(sqr(-11))                   |
      | id_expected_0   | 121                               |
    When I am on the "C1" "qtype_coderunner > prototype library" page
    Then "Delete" "link" should not exist in the "cosc131_spare" "table_row"
    And "//tr[contains(., 'cosc131_spare')]//span[contains(@title, '1 question(s) use it')]" "xpath_element" should exist
