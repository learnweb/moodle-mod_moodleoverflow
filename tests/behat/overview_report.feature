@mod @mod_moodleoverflow
Feature: Testing overview integration in moodleoverflow activity
  In order to summarize the moodleoverflow activity
  As a user
  I need to be able to see the moodleoverflow activity overview

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role           |
      | student1 | Student   | 1        | student1@example.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@example.com | 11       | editingteacher |
    And the following "activities" exist:
      | activity       | name                | intro                           | course | idnumber |
      | moodleoverflow | Test Moodleoverflow | Test moodleoverflow description | C1     | 1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add a new discussion to "Test Moodleoverflow" moodleoverflow with:
      | Subject | Topic question     |
      | Message | This is a question |

  @javascript
  Scenario: The moodleoverflow activity overview report should generate log events
    Given the site is running Moodle version 5.0 or higher
    And I am on the "Course 1" "course > activities > moodleoverflow" page logged in as "teacher1"
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I click on "Get these logs" "button"
    Then I should see "Course activities overview page viewed"
    And I should see "viewed the instance list for the module 'moodleoverflow'"

  @javascript
  Scenario: The moodleoverflow activity index redirect to the activities overview
    Given the site is running Moodle version 5.0 or higher
    When I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Activities" block
    And I click on "Moodleoverflows" "link" in the "Activities" "block"
    Then I should see "An overview of all activities in the course"
    And I should see "Name" in the "moodleoverflow_overview_collapsible" "region"
    And I should see "Unread posts" in the "moodleoverflow_overview_collapsible" "region"
    And I should see "Action" in the "moodleoverflow_overview_collapsible" "region"
