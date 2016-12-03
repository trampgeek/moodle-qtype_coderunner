@qtype @qtype_coderunner @javascript @prototypetests
Feature: make_prototype
  In order to to create more sophisticated CodeRunner questions
  As a teacher
  I must be able to create new question templates

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
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Question bank"
    # Hack - add question first, then edit it, to avoid ace editor messing up
    # the setting of textareas. [I can't figure out how to fill them in if
    # Ace is running.]
    # Also, have to set a global variable behattesting to suppress normal
    # module.js autoindent.
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3                  |
      | name              | PROTOTYPE_test_prototype |
      | id_questiontext   | Dummy question text      |
      | id_useace         |                          |
      | id_testcode_0     | Dummy test code          |
    And I click on "a[title='Edit']" "css_element"
    And I ok any confirm dialogs
    And I set the field "id_customise" to "1"
    And I set CodeRunner behat testing flag
    And I set the field "id_iscombinatortemplate" to "0"
    And I set the field "id_template" to:
      """
      {{STUDENT_ANSWER}}

      print({{TEST.testcode}})
      """
    # Hack to scroll window into right place follows
    And I set the field "Question name" to "prototype acceptance question"

    And I click on "a[aria-controls='id_advancedcustomisationheader']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "python3_test_prototype"
    And I press "id_submitbutton"

    # Now try to add a new question of type python3_test_prototype
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3_test_prototype            |
      | name              | Prototype tester                  |
      | id_questiontext   | Write the inevitable sqr function |
      | id_useace         |                                   |
      | id_testcode_0     | sqr(-11)                          |
      | id_expected_0     | 121                               |
      | id_testcode_1     | sqr(9)                            |
      | id_expected_1     | 81                                |

  Scenario: As a teacher, I get marked right (using combinator template) if I submit a correct answer
    When I click on "table#categoryquestions tr.r1 a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"
