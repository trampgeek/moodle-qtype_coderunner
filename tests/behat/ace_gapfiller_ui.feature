@qtype @qtype_coderunner @javascript @ace_gapfiller
Feature: Test the Ace Gapfiller UI
  In order to use the Ace Gapfiller UI
  As a teacher
  I should be able to specify single- and multi-line gaps in the global extra field

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
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype      | name         | template |
      | Test questions   | coderunner | Print answer | printans |

  Scenario: A single-line gap with no maximum width can grow past its default width
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | customise     | 1             |
      | uiplugin      | Ace_gapfiller |
      | id_expected_0 | A longer answer than ten chars |
    And I set the field "id_template" to:
      """
      answer = eval('''{{ STUDENT_ANSWER | e('py')}}''')
      assert isinstance(answer, list)
      for element in answer: print(element)
      """
    And I set the field "id_globalextra" to:
      """
      {[10]}
      """
    And I press "id_updatebutton"
    Then ".ace-gap-outline" "css_element" should exist

    When I click at the centre of ".ace-gap-outline" "css_element"
    And I type "A longer answer than ten chars"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"

  Scenario: A single-line gap with a maximum width truncates further typing at that width
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | customise     | 1             |
      | uiplugin      | Ace_gapfiller |
      | id_expected_0 | ABCDEFGH      |
    And I set the field "id_template" to:
      """
      answer = eval('''{{ STUDENT_ANSWER | e('py')}}''')
      assert isinstance(answer, list)
      for element in answer: print(element)
      """
    And I set the field "id_globalextra" to:
      """
      {[5-8]}
      """
    And I press "id_updatebutton"
    Then ".ace-gap-outline" "css_element" should exist

    When I click at the centre of ".ace-gap-outline" "css_element"
    And I type "ABCDEFGHIJ"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"

  Scenario: A multi-line gap with a fixed number of rows and a fixed width
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | customise | 1             |
      | uiplugin  | Ace_gapfiller |
    And I set the field "id_template" to:
      """
      answer = eval('''{{ STUDENT_ANSWER | e('py')}}''')
      assert isinstance(answer, list)
      for element in answer: print(element)
      """
    And I set the field "id_globalextra" to:
      """
      {[3,15]}
      """
    And I set the field "id_expected_0" to:
      """
      print(m)
      print(v)
      print(p)
      """
    And I press "id_updatebutton"
    Then ".ace-gap-outline-top" "css_element" should exist

    When I click at the centre of ".ace-gap-outline-top" "css_element"
    And I type "print(m)"
    And I press the enter key
    And I type "print(v)"
    And I press the enter key
    And I type "print(p)"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"

  Scenario: A multi-line gap with a variable number of rows and a variable width
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | customise | 1             |
      | uiplugin  | Ace_gapfiller |
    And I set the field "id_template" to:
      """
      answer = eval('''{{ STUDENT_ANSWER | e('py')}}''')
      assert isinstance(answer, list)
      for element in answer: print(element)
      """
    And I set the field "id_globalextra" to:
      """
      {[2-4, 8-20]}
      """
    And I set the field "id_expected_0" to:
      """
      first line is long
      second
      third
      fourth
      """
    And I press "id_updatebutton"
    Then ".ace-gap-outline-top" "css_element" should exist

    When I click at the centre of ".ace-gap-outline-top" "css_element"
    And I type "first line is long"
    And I press the enter key
    And I type "second"
    And I press the enter key
    And I type "third"
    And I press the enter key
    And I type "fourth"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"

  Scenario: A multi-line gap reproduces its leading and trailing text on every row, without it leaking into the answer
    When I am on the "Print answer" "core_question > edit" page logged in as teacher1
    And I set the following fields to these values:
      | customise | 1             |
      | uiplugin  | Ace_gapfiller |
    And I set the field "id_template" to:
      """
      answer = eval('''{{ STUDENT_ANSWER | e('py')}}''')
      assert isinstance(answer, list)
      for element in answer: print(element)
      """
    And I set the field "id_globalextra" to:
      """
      if True:
          {[2,10]} # note
      print('done')
      """
    And I set the field "id_expected_0" to:
      """
      aaa
      bbb
      """
    And I press "id_updatebutton"
    Then ".ace-gap-outline-top" "css_element" should exist
    And I should see "# note" in the ".ace-gapfiller" "css_element"

    When I click at the centre of ".ace-gap-outline-top" "css_element"
    And I type "aaa"
    And I press the enter key
    And I type "bbb"
    And I set the field "Validate on save" to "1"
    And I press "id_submitbutton"
    Then I should not see "Failed 1 test(s)"
    And I should see "Created by"
