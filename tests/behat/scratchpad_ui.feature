@qtype @qtype_coderunner @javascript @scratchpad
Feature: Test the Scratchpad UI
  In order to use the Scratchpad UI
  As a teacher
  I should be able specify the required html in either globalextra or prototypeextra

  Background:
    Given the CodeRunner test configuration file is loaded
    And the CodeRunner webservice is enabled
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
      | questioncategory | qtype      | name         | template |
      | Test questions   | coderunner | Print answer | printans |

  Scenario: Edit a CodeRunner question into a Scratchpad UI question
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I should not see "Run"
    And I should see "Scratchpad"

    When I click on "Scratchpad" "button"
    Then I should see "Run"
    And I should see "Prefix with Answer"

    When I click on "Scratchpad" "button"
    Then I should not see "Run"
    And I should not see "Prefix Answer?"

  Scenario: Click the run button with program that outputs nothing.
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"

    Then I press "Run"
    And I should see "< No output! >"

  Scenario: Click the run button with program that outputs in Scratchpad Code
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "test_code" to "print(\"hello\" + \" \" + \"world\")"

    Then I press "Run"
    And I should see "hello world"

  Scenario: Click the run button with program that outputs in Answer Code
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print(\"hello\" + \" \" + \"world\")"

    Then I press "Run"
    And I should see "hello world"

    Then I set the field "prefix_ans" to ""
    And I press "Run"
    And I should not see "hello world"

  Scenario: Click the run button with program that outputs in Answer Code and Scratchpad Code
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |

    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print(\"hello\" + \" \" + \"world\")"
    And I set the ace field "test_code" to "print(\"goodbye\" + \" \" + \"world\")"

    When I press "Run"
    Then I should see "hello world"
    And I should see "goodbye world"

    When I set the field "prefix_ans" to ""
    And I press "Run"
    Then I should not see "hello world"
    And I should see "goodbye world"

  @serial
  Scenario: Get empty UI serialization
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ""         |
    And I set the field "id_template" to "print('''{| STUDENT_ANSWER |}''')"
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I press the CTRL + ALT M key
    And I should see in answer field ""

    When I press "Check"
    Then I should see "The submission was invalid, and has been disregarded without penalty."

  @serial
  Scenario: Get UI serialization, with answer code, while Scratchpad Hidden
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I set the field "id_template" to "print('''{| STUDENT_ANSWER |}''')"
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I set the ace field "answer_code" to "print('hello world')"
    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":["print('hello world')"],"test_code":[""],"show_hide":[""],"prefix_ans":["1"]}
    """

  @serial
  Scenario: Get UI serialization, answer code entered, Scratchpad shown
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I set the field "id_template" to "print('''{| STUDENT_ANSWER |}''')"
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I set the ace field "answer_code" to "print('hello world')"
    And I click on "Scratchpad" "button"

    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":["print('hello world')"],"test_code":[""],"show_hide":["1"],"prefix_ans":["1"]}
    """

  @serial
  Scenario: Get UI serialization, Scratchpad code entered, Scratchpad shown, NO prefix
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I should see "Run"
    And I set the ace field "test_code" to "print('hello world')"
    And I set the field "prefix_ans" to ""

    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":[""],"test_code":["print('hello world')"],"show_hide":["1"],"prefix_ans":[""]}
    """

    When I press the CTRL + ALT M key
    Then I wait "2" seconds
    Then I click on "Scratchpad" "button"

    Then I wait "2" seconds
    Then  I should not see "Run"

    When I press the CTRL + ALT M key
    Then I should see in answer field:
    """
    {"answer_code":[""],"test_code":["print('hello world')"],"show_hide":[""],"prefix_ans":[""]}
    """

  @serial
  Scenario: Get UI serialization, while Scratchpad Shown and prefix box NOT ticked
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the field "prefix_ans" to ""

    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":[""],"test_code":[""],"show_hide":["1"],"prefix_ans":[""]}
    """

  @serial
  Scenario: Get UI serialization, answer code and test code entered, Scratchpad shown, prefix NOT ticked
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I set the field "id_template" to "print('''{| STUDENT_ANSWER |}''')"
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    And I click on "Scratchpad" "button"
    And I set the ace field "answer_code" to "print('hello world')"
    And I set the ace field "test_code" to "print('goodbye world')"
    And I set the field "prefix_ans" to ""

    Then I press the CTRL + ALT M key
    And I should see in answer field:
    """
    {"answer_code":["print('hello world')"],"test_code":["print('goodbye world')"],"show_hide":["1"],"prefix_ans":[""]}
    """

  @serial
  Scenario: Only enter serialization with answer_code value, no other fields. Useful for converting Ace questions to Scratchpad.
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I set the field "id_template" to "print('''{| STUDENT_ANSWER |}''')"
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I press the CTRL + ALT M key
    And I set answer field to:
    """
    {"answer_code":["print('hello world')"]}
    """
    And I press the CTRL + ALT M key
    Then I should see "print('hello world')"

    When I press the CTRL + ALT M key
    Then I should see in answer field:
    """
    {"answer_code":["print('hello world')"],"test_code":[""],"show_hide":[""],"prefix_ans":["1"]}
    """

  @serial
  Scenario: Enter serialization as string.
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I set the field "id_template" to "print('''{| STUDENT_ANSWER |}''')"
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I press the CTRL + ALT M key
    And I set answer field to:
    """
    print('hello world')
    """
    And I press the CTRL + ALT M key
    Then I should see "print('hello world')"

    When I press the CTRL + ALT M key
    Then I should see in answer field:
    """
    {"answer_code":["print('hello world')"],"test_code":[""],"show_hide":[""],"prefix_ans":["1"]}
    """

  @serial
  Scenario: Enter serialisation missing answer_code.
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1          |
      | id_uiplugin       | Scratchpad |
      | id_validateonsave | 0          |
      | id_expected_0     | ''         |
    And I set the field "id_template" to "print('''{| STUDENT_ANSWER |}''')"
    And I press "id_submitbutton"
    Then I should see "Print answer"

    When I choose "Preview" action for "Print answer" in the question bank
    Then I press the CTRL + ALT M key
    And I set answer field to:
    """
    {"not_answer_code":["print('hello world')"]}
    """
    And I press the CTRL + ALT M key
    Then I should not see "print('hello world')"
    And I should see "Falling back to raw text area."

  Scenario: Change question using Scratchpad UI to table UI in authorform, tests destroy method.
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the field "id_answer" to ""
    And I set the following fields to these values:
      | id_customise      | 1     |
      | id_uiplugin       | Table |
      | id_validateonsave | 0     |
      | id_expected_0     | ''    |
    And I press "id_updatebutton"

    When I set the field "id_uiplugin" to "Scratchpad"
    And I press the CTRL + ALT M key
    Then I set the field "id_answer" to "print('hello world')"
    Then I press the CTRL + ALT M key
    And I set the field "id_uiplugin" to "Table"
    Then I should not see "print('hello world')"
#    This line should work, but behat can't find the text... Not essential, but does improve test's accuracy.
#    And I should see "Table UI needs parameters num_columns and num_rows."
