@mod @mod_questupload
Feature: Radio questions allow optional "other" responses with optional labels
  In order to allow users to enter "other" answers to a radio button question
  As a teacher
  I need to specify an "other" choice

  Background: Add two radio button question to a questupload with both "other" choice options specified
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questupload | Test questupload | Test questupload description | C1 | questupload0 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    And I navigate to "Questions" in current page administration
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Select one |
      | Possible answers | Red,Blue,Black,!other |
    Then I should see "position 1"
    And I should see "[Radio Buttons] (Q1)"
    And I should see "Select one"
    And I add a "Radio Buttons" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Question Text | Select another |
      | Possible answers | Green,Orange,Yellow,!other=Another colour: |
    Then I should see "position 2"
    And I should see "[Radio Buttons] (Q2)"
    And I should see "Select another"
    And I log out

  @javascript
  Scenario: Student selects other options and enters their own text.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Test questupload"
    And I click on "Other:" "radio"
    And I set the field "Text for Other:" to "Yellow"
    And I click on "Another colour:" "radio"
    And I set the field "Text for Another colour:" to "Indigo"
    And I press "Submit questupload"
    Then I should see "Thank you for completing this Questionnaire Upload."
    And I press "Continue"
    Then I should see "View your response(s)"
    And I should see "Test questupload"
    And I should see "Other: Yellow"
    And I should see "Another colour: Indigo"
