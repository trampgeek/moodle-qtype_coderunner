@qtype @qtype_coderunner @javascript @fsm
Feature: Check that the given answer to a DFA question is marked as correct
  To check that my answer to a DFA question is correct
  As a teacher
  I should be able to submit my answer and have it marked as correct

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
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And I log in as "teacher1"
    And I follow "C1"
    And I navigate to "Question bank" node in "Course administration"
    And I disable UI plugins
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | python3                  |
      | name              | PROTOTYPE_dfa            |
      | id_questiontext   | Dummy question text      |
      | id_testcode_0     | Dummy test code          |
      | id_customise      | 1                        |
    And I click on "a[title='Edit']" "css_element"
    And I ok any confirm dialogs

    And I set CodeRunner behat testing flag
    And I set the field "id_iscombinatortemplate" to "0"
    And I fill in my template
    # Hack to scroll window into right place follows
    And I set the field "Question name" to "dfa prototype"

    And I click on "a[aria-controls='id_advancedcustomisationheader']" "css_element"
    And I set the field "prototypetype" to "Yes (user defined)"
    And I set the field "typename" to "dfa_test_prototype"
    And I press "id_submitbutton"

    # Now try to add a new question of type python3_test_prototype
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | dfa_test_prototype           |
      | name              | Prototype tester                  |
      | id_questiontext   | Draw a thing!                     |
      | id_answer         | {"edgeGeometry":[{"lineAngleAdjust":0,"parallelPart":0.5666666666666667,"perpendicularPart":12},{"anchorAngle":-1.3564737260029103},{"lineAngleAdjust":0,"parallelPart":0,"perpendicularPart":0},{"lineAngleAdjust":0,"parallelPart":0.44,"perpendicularPart":18},{"lineAngleAdjust":0,"parallelPart":0,"perpendicularPart":0},{"lineAngleAdjust":0,"parallelPart":0,"perpendicularPart":0},{"anchorAngle":1.441553504583314},{"lineAngleAdjust":0,"parallelPart":0,"perpendicularPart":0},{"deltaX":-50,"deltaY":-50}],"nodeGeometry":[[100,100],[400,100],[400,274],[100,274]],"nodes":[["q_0",false],["q_1",false],["q_2",false],["q_3",true]],"edges":[[0,1,"0"],[0,0,"1"],[1,2,"0"],[1,0,"1"],[2,3,"0"],[2,0,"1"],[3,3,"0"],[3,0,"1"],[-1,0,""]]} |
      | id_expected_0     | Good                              |
  
  Scenario: Preview a coderunner UI plugin
    When I click on "Preview" "link" in the "Prototype tester" "table_row"
    And I switch to "questionpreview" window
    And I press "Fill in correct responses"
    And I press "Check"
    Then I should see "Good"