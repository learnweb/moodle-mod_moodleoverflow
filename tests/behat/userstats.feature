@mod @mod_moodleoverflow @javascript
Feature: If the admin enabled user statistics, the teacher can see the activity of students in the course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber |
      | moodleoverflow | Test Moodleoverflow       | Test moodleoverflow description  | C1      |  1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add a new discussion to "Test Moodleoverflow" moodleoverflow with:
      | Subject | Topic question |
      | Message | This is a question  |

  Scenario: Userstats are not enabled per default. The teacher should not see the user statistics
    And I follow "Test Moodleoverflow"
    Then I should not see "View user statistics"

  Scenario: Userstats are enabled. The teacher should see the user statistics. The teacher should already have an acitivty point
            for writing a post.
    Given the following config values are set as admin:
      | showuserstats | 1 | moodleoverflow |
    And I follow "Test Moodleoverflow"
    Then I should see "View user statistics"
    When I press "View user statistics"
    Then the following should exist in the "statisticstable" table:
      | User full name | Received upvotes | Received downvotes | Activity (this forum) | Activity (coursewide) |
      | Teacher 1      | 0                | 0                  | 1                     | 1                     |
      | Student 1      | 0                | 0                  | 0                     | 0                     |

  Scenario: Test if reputation appears in the user statistics
    Given the following config values are set as admin:
      | showuserstats | 1 | moodleoverflow |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Moodleoverflow"
    And I follow "Topic question"
    And I click on "Answer" "link"
    And I set the following fields to these values:
      | Message | This is an answer |
    And I press "Post to forum"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Moodleoverflow"
    And I follow "Topic question"
    And I click on "Mark as solution" "text"
    And I follow "Test Moodleoverflow"
    And I press "View user statistics"
    Then the following should exist in the "statisticstable" table:
      | User full name | Received upvotes | Received downvotes | Activity (this forum) | Activity (coursewide) | Reputation (this forum) |
      | Teacher 1      | 0                | 0                  | 2                     | 2                     | 0                       |
      | Student 1      | 0                | 0                  | 1                     | 1                     | 30                      |
