@mod @mod_questupload
Feature: Review responses with different capabilities
  In order to review and manage questupload responses
  As a user
  I need proper capabilities to access the view responses features

  @javascript
  Scenario: A teacher with mod/questupload:readallresponseanytime can see all responses.
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
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability           | permission |
      | mod/questupload:readallresponseanytime | Allow |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questupload | Test questupload | Test questupload description | C1 | questupload0 |
    And "Test questupload" has questions and responses
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    Then I should see "View all responses"
    And I navigate to "View all responses" in current page administration
    Then I should see "All responses"
    And I should see "View Default order"
    And I should see "Responses: 7 (Submissions: 6 | In progress: 1)"
    And I log out

  @javascript
  Scenario: A teacher denied mod/questupload:readallresponseanytime cannot see all responses.
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
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability           | permission |
      | mod/questupload:readallresponseanytime | Prohibit |
      | mod/questupload:readallresponses | Allow |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | questupload | Test questupload | Test questupload description | C1 | questupload0 |
    And "Test questupload" has questions and responses
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    Then I should not see "View all responses"
    And I log out

  @javascript
  Scenario: A teacher with mod/questupload:readallresponses can see responses after appropriate time rules.
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
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability           | permission |
      | mod/questupload:readallresponseanytime | Prohibit |
      | mod/questupload:readallresponses | Allow |
    And the following "activities" exist:
      | activity | name | description | course | idnumber | resp_view |
      | questupload | Test questupload | Test questupload description | C1 | questupload0 | 0 |
      | questupload | Test questupload 2 | Test questupload 2 description | C1 | questupload2 | 3 |
    And "Test questupload" has questions and responses
    And "Test questupload 2" has questions and responses
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    Then I should not see "View all responses"
    And I am on "Course 1" course homepage
    And I follow "Test questupload 2"
    Then I should see "View all responses"
    And I log out

  @javascript
  Scenario: A student cannot view all responses by default.
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber | resp_view |
      | questupload | Test questupload | Test questupload description | C1 | questupload0 | 3 |
    And "Test questupload" has questions and responses
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questupload"
    Then I should not see "View all responses"
    And I log out
