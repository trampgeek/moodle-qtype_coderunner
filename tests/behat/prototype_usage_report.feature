@qtype @qtype_coderunner @javascript
Feature: See what depends on a prototype before changing it
  In order to know what a change to a prototype would break
  As a teacher who maintains prototypes
  I need to see the questions built on one, and reach them

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
    # A parent and a child, so the report has a chain to describe rather than a
    # single layer. The questions below are built on each in turn, which is what
    # makes the child's own count and the "through child prototype" column mean
    # anything.
    And the following "qtype_coderunner > prototypes" exist:
      | name            | parent          | course | uiplugin | summary                |
      | base_proto      | python3         | C1     | none     | The one under test     |
      | derived_proto   | base_proto      | C1     | none     | Built on base_proto    |
      | lonely_proto    | python3         | C1     | none     | Nothing uses this      |

  Scenario: A prototype nothing depends on says so
    When I am on the "lonely_proto" "qtype_coderunner > prototype usage" page logged in as teacher1
    Then I should see "What depends on lonely_proto"
    And I should see "Nothing depends on this prototype yet."
    When I follow "Back to the prototype list"
    Then I should see "lonely_proto"

  Scenario: The usage report counts the questions and reaches them
    Given I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I add a CodeRunner question on "base_proto" filling the form with:
      | name              | Direct question    |
      | id_questiontext   | Built on the base  |
      | id_testcode_0     | print(1)           |
      | id_expected_0     | 1                  |
    And I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I add a CodeRunner question on "derived_proto" filling the form with:
      | name              | Inherited question    |
      | id_questiontext   | Built on the child    |
      | id_testcode_0     | print(2)              |
      | id_expected_0     | 2                     |

    # Reached by clicking the count in the library, because that link is the
    # only way an author finds this page.
    When I am on the "C1" "qtype_coderunner > prototype library" page logged in as teacher1
    Then I should see "base_proto"
    When I click on "1" "link" in the "base_proto" "table_row"
    Then I should see "What depends on base_proto"
    And I should see "Child prototypes"
    And I should see "derived_proto"
    And I should see "Questions, by course"
    And I should see "C1" in the "table.table-sm" "css_element"

    # The course name leads to the questions themselves, which is what the
    # report is for: a count alone does not say what would break.
    When I click on "C1" "link" in the "table.table-sm" "css_element"
    Then I should see "Questions using base_proto in C1"
    And I should see "Direct question"
    And I should see "Inherited question"
    And I should see "Question bank"
    # The inherited one is reached through the child, and the report says so.
    And I should see "Through child prototype"
    And I should see "derived_proto"
    When I follow "Back to the usage report"
    Then I should see "What depends on base_proto"

  Scenario: A child prototype reports only its own questions
    Given I am on the "Course 1" "core_question > course question bank" page logged in as teacher1
    And I add a CodeRunner question on "derived_proto" filling the form with:
      | name              | Inherited question    |
      | id_questiontext   | Built on the child    |
      | id_testcode_0     | print(2)              |
      | id_expected_0     | 2                     |
    When I am on the "derived_proto" "qtype_coderunner > prototype usage" page logged in as teacher1
    Then I should see "What depends on derived_proto"
    And I should see "Questions, by course"
    And I should see "C1" in the "table.table-sm" "css_element"
    And I should not see "Child prototypes"
    When I click on "C1" "link" in the "table.table-sm" "css_element"
    Then I should see "Questions using derived_proto in C1"
    And I should see "Inherited question"
    # Nothing stands between the question and this prototype, so there is no
    # child column to fill in.
    And I should not see "Through child prototype"
