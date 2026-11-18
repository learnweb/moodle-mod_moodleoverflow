@mod @mod_moodleoverflow @javascript
Feature: Teachers see the activity of the students in the user statistics, unless the admin switched them off

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role           |
      | student1 | Student   | 1        | student1@example.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@example.com | 11       | editingteacher |
    And the following "activities" exist:
      | activity       | name                | intro                           | course | idnumber |
      | moodleoverflow | Test Moodleoverflow | Test moodleoverflow description | C1     | 1        |
    And User "teacher1" adds to "Test Moodleoverflow" a discussion with topic "Topic question" and message "This is a question" automatically

  Scenario: The admin switched the user statistics off. The teacher does not see them
    Given the following config values are set as admin:
      | showuserstats | 0 | moodleoverflow |
    When I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "teacher1"
    Then "User statistics" "link" should not exist

  Scenario: The teacher sees every participant, students do not see the statistics
    When I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "student1"
    Then "User statistics" "link" should not exist
    When I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "teacher1"
    And I click on "Forum actions" "button"
    And I click on "User statistics" "link"
    Then "Teacher 1" "table_row" should exist
    And "Student 1" "table_row" should exist

  Scenario: The user statistics show the posts and votes over time as a chart
    Given the site is running Moodle version 5.3 or higher
    When I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "teacher1"
    And I click on "Forum actions" "button"
    And I click on "User statistics" "link"
    Then I should see "Posts and votes per day"

  Scenario: Test if reputation appears in the user statistics
    Given User "student1" replies "This is a question" with "This is an answer" automatically
    And I am on the "Topic question" "mod_moodleoverflow > Discussion" page logged in as "teacher1"
    And I click on the "solved" button of the moodleoverflow post "This is an answer"
    And I follow "Test Moodleoverflow"
    And I click on "Forum actions" "button"
    And I click on "User statistics" "link"
    Then I should see "30" in the "Student 1" "table_row"
    And I should see "2" in the "Teacher 1" "table_row"
