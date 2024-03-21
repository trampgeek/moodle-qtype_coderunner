@qtype @qtype_coderunner @javascript @graphui
Feature: Check that the directed and undirected graph question types work.
  To check that simple directed and undirected graph questions work
  As a teacher
  I should be able to write simple graph questions and have them work correctly

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
    And I am on the "Course 1" "core_question > course question bank" page logged in as teacher1

  Scenario: Preview a coderunner directed graph question
    When I disable UI plugins in the CodeRunner question type
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | directed_graph                      |
      | id_name           | Test 2-node directed graph          |
      | id_questiontext   | Dummy question text                 |
      | id_testcode_0     | print(list(sorted(graph.items())))  |
      | id_expected_0     | [('A', [('B', 'wt')]), ('B', [])]   |
      | id_answer         | {"edgeGeometry":[{"lineAngleAdjust":0,"parallelPart":0.5,"perpendicularPart":0}],"nodeGeometry":[[246,163],[426,160]],"nodes":[["A",false],["B",false]],"edges":[[0,1,"wt"]]}|
    And I enable UI plugins in the CodeRunner question type
    And I choose "Preview" action for "Test 2-node directed graph" in the question bank
    Then I should see a canvas
    And I press "Fill in correct responses"
    And I press "Check"
    Then I should see "Passed all tests!"

  Scenario: Preview a coderunner undirected graph question
    When I disable UI plugins in the CodeRunner question type
    And I add a "CodeRunner" question filling the form with:
      | id_coderunnertype | undirected_graph                    |
      | name              | Test 2-node undirected graph        |
      | id_questiontext   | Dummy question text                 |
      | id_testcode_0     | print(list(sorted(graph.items())))  |
      | id_expected_0     | [('A', [('B', 'wt')]), ('B', [('A', 'wt')])] |
      | id_answer         | {"edgeGeometry":[{"lineAngleAdjust":0,"parallelPart":0.5,"perpendicularPart":0}],"nodeGeometry":[[246,163],[426,160]],"nodes":[["A",false],["B",false]],"edges":[[0,1,"wt"]]}|
    And I enable UI plugins in the CodeRunner question type
    And I choose "Preview" action for "Test 2-node undirected graph" in the question bank
    Then I should see a canvas
    And I press "Fill in correct responses"
    And I press "Check"
    Then I should see "Passed all tests!"
