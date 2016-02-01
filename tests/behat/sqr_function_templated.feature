@questiontype @questiontype_coderunner @javascript @customisetests
Feature: sqr_function_templated
    As a teacher I must be able to customise and run a Python3 sqr function. 
    The combinator template should be used first but the per-test template
    should be used if runtime errors occur. 

Background:
    Given the following "users" exist:
       | username | firstname | lastname | email |
       | teacher1 | Teacher | 1 | teacher1@asd.com |
    And the following "courses" exist:
       | fullname | shortname | category |
       | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
       | user | course | role |
       | teacher1 | C1 | editingteacher |
    And the following "question categories" exist:
       | contextlevel | reference | questioncategory | name |
       | Course       |    C1     |   Top            | Behat Testing |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Question bank"
    And I add a "CodeRunner" question filling the form with:
        | id_coderunnertype | python3 |
        | name              | sqr acceptance question |
        | id_questiontext   | Write a sqr function |
        | id_useace         | |
        | id_testcode_0     | sqr(-7) |
        | id_expected_0     | 49 |  
        | id_testcode_1     | sqr(11) |
        | id_expected_1     | 121 |
        | id_testcode_2     | sqr(-3) |
        | id_expected_2     | 9 |
        | id_display_2      | Hide |
    And I click on "a[title='Edit']" "css_element"
    And I set the field "id_customise" to "1"
    And I cancel any confirm dialogs
    # Set up a per-test template with extra (junk) output
    And I set the field "id_pertesttemplate" to:
    """
    {{STUDENT_ANSWER}}
    print(str({{TEST.testcode}}) + 'UsingPerTestTemplate')
    """
    # Hack to scroll window into right place follows
    And I set the field "Question name" to "sqr acceptance question"
    And I click on "a[aria-controls='id_advancedcustomisationheader']" "css_element"
    # Combinator template is correct
    And I set the field "id_combinatortemplate" to:
    """
    {{ STUDENT_ANSWER }}
    SEPARATOR = '#<ab@17943918#@>#'
    {% for TEST in TESTCASES %}
    print({{TEST.testcode}})
    {% if not loop.last %}
    print(SEPARATOR)
    {% endif %}
    {% endfor %}
    """
    And I press "id_submitbutton"


Scenario: As a teacher, I get marked right (using combinator template) if I submit a correct answer
    When I click on "a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
        | Test    |
        | sqr(-3) |
        | sqr(11) |
    And "sqr(11)" row "Expected" column of "coderunner-test-results" table should contain "121"
    And "sqr(11)" row "Got" column of "coderunner-test-results" table should contain "121"
    And I should see "Passed all tests!"
    And I should not see "Show differences"
    And I should see "Marks for this submission: 1.00/1.00"

Scenario: As a teacher, I get marked wrong (using per-test template) if I submit an answer that gives a runtime error
    When I click on "a[title='Preview']" "css_element"
    And I switch to "questionpreview" window
    And I set the field "id_behaviour" to "Adaptive mode"
    And I press "Start again with these options"
    And I set the field with xpath "//textarea[contains(@name, 'answer')]" to "def sqr(n): return n * n if n != -3 else sqr(n)"
    And I press "Check"
    Then the following should exist in the "coderunner-test-results" table:
        | Test    |
        | sqr(-3) |
        | sqr(11) |
    And "sqr(11)" row "Expected" column of "coderunner-test-results" table should contain "121"
    And "sqr(11)" row "Got" column of "coderunner-test-results" table should contain "121UsingPerTestTemplate"
    And I should see "Marks for this submission: 0.00/1.00"