@mod @mod_questupload
Feature: Text questions can have zero as a valid response

  Background: Add a text question to a questupload and accept zero
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
    And I add a "Text Box" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Enter zero |
    Then I log out

  @javascript
  Scenario: Student must enter no more than six digits and decimal points.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Enter zero"
    And I set the field "Enter zero" to "0"
    And I press "Submit questupload"
    Then I should see "Thank you for completing this Questionnaire Upload."
    And I press "Continue"
    Then I should see "View your response(s)"
    And I should see "Test questupload"
    And I should see "Enter zero"
    And "//div[contains(@class,'questupload_text') and contains(@class,'questupload_response')]//span[@class='selected' and text()='0']" "xpath_element" should exist
