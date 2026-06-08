@mod @mod_questupload
Feature: Questionnaire Uploads can be public, private or template
  In order to view a questupload
  As a user
  The type of the questupload affects how it is displayed.

  @javascript
  Scenario: Add a template questupload
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | manager1 | Manager | 1 | manager1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | manager1 | C1 | manager |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questupload | Test questupload | Test questupload description | C1 | questupload0 |
    And I am on the "Test questupload" "mod_questupload > advsettings" page logged in as "manager1"
    And I should see "Content options"
    And I set the field "id_realm" to "template"
    And I press "Save and display"
    Then I should see "Template questuploads are not viewable"

  @javascript
  Scenario: Add a questupload from a public questupload
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | manager1 | Manager | 1 | manager1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | manager1 | C1 | manager |
      | manager1 | C2 | manager |
      | student1 | C2 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questupload | Test questupload | Test questupload description | C1 | questupload0 |
    And the following config values are set as admin:
      | coursebinenable | 0 | tool_recyclebin |
    And I log in as "manager1"
    And I am on site homepage
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    And I follow "Test questupload"
    And I navigate to "Questions" in current page administration
    And I add a "Check Boxes" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Min. forced responses | 1 |
      | Max. forced responses | 2 |
      | Question Text | Select one or two choices only |
      | Possible answers | One,Two,Three,Four |
# Neither of the following steps work in 3.2, since the admin options are not available on any page but "view".
    And I am on the "Test questupload" "mod_questupload > advsettings" page
    And I should see "Content options"
    And I set the field "id_realm" to "public"
    And I press "Save and return to course"
# Verify that a public questupload cannot be used in the same course.
    And I add a questupload activity to course "Course 1" section "1"
    And I expand all fieldsets
    Then I should see "(No public questuploads.)"
    And I press "Cancel"
# Verify that a public questupload can be used in a different course.
    And I am on site homepage
    And I add a questupload activity to course "Course 2" section "1"
    And I expand all fieldsets
    And I set the field "name" to "Questionnaire Upload from public"
    And I click on "Test questupload [Course 1]" "radio"
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 2" course homepage
    And I follow "Questionnaire Upload from public"
    Then I should see "Answer the questions..."
# Verify message for public questupload that has been deleted.
    And I log out
    And I log in as "manager1"
    And I am on site homepage
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I delete "Test questupload" activity
    And I am on site homepage
    And I am on the "Questionnaire Upload from public" "mod_questupload > view" page
    Then I should see "This questupload used to depend on a Public questupload which has been deleted."
    And I should see "It can no longer be used and should be deleted."
    And I log out
    And I am on the "Questionnaire Upload from public" "mod_questupload > view" page logged in as "student1"
    Then I should see "This questupload is no longer available. Ask your teacher to delete it."

  @javascript @_switch_window
  Scenario: Test view questions in print blank page.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | manager1 | C1     | manager        |
      | manager1 | C2     | manager        |
      | student1 | C2     | student        |
    And the following "activities" exist:
      | activity      | name               | description                    | course | idnumber       |
      | questupload | Test questupload | Test questupload description | C1     | questupload0 |
    And the following config values are set as admin:
      | coursebinenable | 0 | tool_recyclebin |
    And I log in as "manager1"
    And I am on site homepage
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    And I follow "Test questupload"
    And I navigate to "Questions" in current page administration
    And I add a "Date" question and I fill the form with:
      | Question Name | Q1                 |
      | Yes           | y                  |
      | Question Text | Enter today's date |
    And I add a "Dropdown Box" question and I fill the form with:
      | Question Name    | Q2                         |
      | Yes              | y                          |
      | Question Text    | Select one choice          |
      | Possible answers | 1=One,2=Two,3=Three,4=Four |
    And I add a "Essay Box" question and I fill the form with:
      | Question Name   | Q3               |
      | No              | n                |
      | Response format | 0                |
      | Input box size  | 10 lines         |
      | Question Text   | Enter your essay |
    And I add a "Slider" question and I fill the form with:
      | Question Name                | Q4                   |
      | Question Text                | Slider question test |
      | Left label                   | Left                 |
      | Right label                  | Right                |
      | Centre label                 | Center               |
      | Minimum slider range (left)  | 5                    |
      | Maximum slider range (right) | 100                  |
      | Slider starting value        | 5                    |
      | Slider increment value       | 5                    |
    And I add a "Text Box" question and I fill the form with:
      | Question Name | Q5         |
      | Yes           | y          |
      | Question Text | Enter zero |
    And I navigate to "Preview" in current page administration
    And I click on "Print Blank" "link"
    When I switch to a second window
    # Check date picker question type.
    Then "div.qn-datemsg + input[type='text']" "css_element" should exist
    And "div.qn-datemsg + div > input[type='date']" "css_element" should not exist
    # Check dropdown question type.
    And "div.printdropdown input[type='checkbox']" "css_element" should exist
    And "select.custom-select" "css_element" should not exist
    # Check essay question type.
    And ".qn-content textarea" "css_element" should exist
    And ".qn-content .tox-tinymce" "css_element" should not exist
    # Check slider question type.
    And ".question-slider .slider .bubble" "css_element" should not be visible
    # Check text box question type.
    And ".print-text" "css_element" should exist
