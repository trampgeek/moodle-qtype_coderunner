@qtype @qtype_coderunner @javascript @sqrfunctests @v6triage
Feature: Test that answers changed after checking are highlighted as being different to the answer that was checked.
  As a teacher or student
  I must be able to see the "Results below are for a different answer to the answer above." message when an answer has changed after checking.

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
      | questioncategory | qtype      | name                        | template     |
      | Test questions   | coderunner | Square function (with Ace)  | sqr_with_ace |

  Scenario: Preview the Python3 sqr_with_ace function CodeRunner question get it right then change the answer and wait for changed answer notification
    When I am on the "Square function (with Ace)" "core_question > edit" page logged in as teacher1
    And I override the CodeRunner field "template"
    And I set the ace field "template" to:
    """
    {{ STUDENT_ANSWER }}
    {{ TEST.testcode }}
    """
    # Need to change question name or else the preview step will find two qids for the same name
    # That is, it isn't looking for the latest version!!!!
    And I set the field "id_name" to "Square function (with Ace) v2"
    And I press "id_submitbutton"
    And I am on the "Square function (with Ace) v2" "core_question > preview" page logged in as teacher1
    And I wait until the page is ready
    # Aha, for scratchpad use "answer_code" but "answer" for ace...
    And I set the ace field "answer" to "def sqr(n): return n * n"
    And I press "Check"
    And I wait until the page is ready
    Then I should see "Passed all tests!"
    Then I should not see "Results below are for a different answer to the answer above."
    And I set the ace field "answer" to "def sqr(n): return n * n * n"
    # And I wait until the page is ready --- no use as page won't reload
    # And I wait "1" seconds --- may not wait long enough
    # So wait for a changed notice to come up
    #   Of course this wouldn't work if we had multiple elements on the page
    #   but we can't do this as the prefix to changed-notice would be unique and unknown....
    And I wait until "[data-id$='-changed-notice']" "css_element" exists
    Then I should see "Results below are for a different answer to the answer above."
    # still passing for old answer
    Then I should see "Passed all tests!"

  Scenario: Preview the Python3 sqr_with_ace function CodeRunner question and submit wrong answer then change and wait for changed notification, then change back and wait for notification to go away.
    When I am on the "Square function (with Ace)" "core_question > edit" page logged in as teacher1
    And I override the CodeRunner field "template"
    And I set the ace field "template" to:
    """
    {{ STUDENT_ANSWER }}
    {{ TEST.testcode }}
    """
    # Need to change question name or else the preview step will find two qids for the same name
    # That is, it isn't looking for the latest version!!!!
    And I set the field "id_name" to "Square function (with Ace) v2"
    And I press "id_submitbutton"
    And I am on the "Square function (with Ace) v2" "core_question > preview" page logged in as teacher1
    And I set the ace field "answer" to:
    """
    def sqr(n);
        return n * n
    """
    And I press "Check"
    And I wait until the page is ready
    And I wait "1" seconds
    And I set the ace field "answer" to:
    """
    def sqr(n):
        return n * n
    """
    And I wait until the page is ready
    And I wait "1" seconds
    Then I should see "Results below are for a different answer to the answer above."
    And I set the ace field "answer" to:
    """
    def sqr(n);
        return n * n
    """
    And I wait until the page is ready
    And I wait "1" seconds
    Then I should not see "Results below are for a different answer to the answer above."

# Run with standard Python3 quesdtion
#  Scenario: Preview the Python3 sqr_with_ace function CodeRunner question and get it wrong then change and wait for changed notification
#    When I am on the "Square function (with Ace)" "core_question > preview" page logged in as teacher1
#    And I wait "1" seconds
#    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n * n"
#    And I press "Check"
#    Then the following should exist in the "coderunner-test-results" table:
#      | Test           |
#      | print(sqr(11)) |
#      | print(sqr(-7)) |
#
#    And "print(sqr(11))" row "Expected" column of "coderunner-test-results" table should contain "121"
#    And "print(sqr(11))" row "Got" column of "coderunner-test-results" table should contain "1331"
#    And I should see "Some hidden test cases failed, too."
#    And I should see "Marks for this submission: 3.00/31.00"

# +++++++++++++++++++++++++++++++++++++++++
# need to also try with sqr_with_scratchpad
# +++++++++++++++++++++++++++++++++++++++++
  Scenario: Preview the Python3 sqr_with_ace function CodeRunner question and submit wrong answer then change and wait for changed notification, then change back and wait for notification to go away.
    When I am on the "Square function (with Ace)" "core_question > edit" page logged in as teacher1
    And I override the CodeRunner field "template"
    And I set the ace field "template" to:
    """
    {{ STUDENT_ANSWER }}
    {{ TEST.testcode }}
    """
    And I override the CodeRunner field "uiplugin"
    And I choose the CodeRunner UI "Scratchpad"
    # Need to change question name or else the preview step will find two qids for the same name
    # That is, it isn't looking for the latest version!!!!
    And I set the field "id_name" to "Square function (with Ace) v2"
    And I press "id_submitbutton"
    And I am on the "Square function (with Ace) v2" "core_question > preview" page logged in as teacher1
    # Aha, for scratchpad use "answer_code" but "answer" for standard ace...
    And I set the ace field "answer_code" to:
    """
    def sqr(n);
        return n * n
    """
    And I press "Check"
    And I wait until the page is ready
    And I wait "1" seconds
    And I set the ace field "answer_code" to:
    """
    def sqr(n):
        return n * n
    """
    And I wait until the page is ready
    And I wait "1" seconds
    Then I should see "Results below are for a different answer to the answer above."
    And I set the ace field "answer_code" to:
    """
    def sqr(n);
        return n * n
    """
    And I wait until the page is ready
    # Seems to take a while to register the change back...
    And I wait "5" seconds
    Then I should not see "Results below are for a different answer to the answer above."
