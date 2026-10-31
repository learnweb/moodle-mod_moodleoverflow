@mod @mod_moodleoverflow @javascript
Feature: If the admin enabled user statistics, the teacher can see the activity of students in the course

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role           |
      | student1 | Student   | 1        | student1@example.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@example.com | 11       | editingteacher |
    And the following "activities" exist:
      | activity       | name                | intro                           | course | idnumber |
      | moodleoverflow | Test Moodleoverflow | Test moodleoverflow description | C1     | 1        |
    And User "teacher1" adds to "Test Moodleoverflow" a discussion with topic "Topic question" and message "This is a question" automatically

  Scenario: Userstats are not enabled per default. The teacher should not see the user statistics
    When I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "teacher1"
    Then I should not see "View user statistics"

  Scenario: Userstats are enabled. The teacher sees every participant, students do not see the statistics
    Given the following config values are set as admin:
      | showuserstats | 1 | moodleoverflow |
    When I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "student1"
    Then I should not see "View user statistics"
    When I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "teacher1"
    And I click on "View user statistics" "text"
    Then "Teacher 1" "table_row" should exist
    And "Student 1" "table_row" should exist

  Scenario: Test if reputation appears in the user statistics
    Given the following config values are set as admin:
      | showuserstats | 1 | moodleoverflow |
    And User "student1" replies "This is a question" with "This is an answer" automatically
    And I am on the "Topic question" "mod_moodleoverflow > Discussion" page logged in as "teacher1"
    And I click on "Mark as solution" "text"
    And I follow "Test Moodleoverflow"
    And I click on "View user statistics" "text"
    Then I should see "30" in the "Student 1" "table_row"
    And I should see "2" in the "Teacher 1" "table_row"
