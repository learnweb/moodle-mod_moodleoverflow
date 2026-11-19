@mod @mod_moodleoverflow
Feature: A single discussion moodleoverflow shows its one discussion on the forum page

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role           |
      | student1 | Student   | 1        | student1@example.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@example.com | 11       | editingteacher |
    And the following "activities" exist:
      | activity       | name            | intro                 | course | idnumber | singlediscussion |
      | moodleoverflow | Chapter 3 forum | Comments on chapter 3 | C1     | 1        | 1                |

  Scenario: The teacher starts the one discussion
    Given I am on the "Chapter 3 forum" "moodleoverflow activity" page logged in as "teacher1"
    Then I should see "Single discussion"
    And I should see "Add a new discussion topic"
    When User "teacher1" adds to "Chapter 3 forum" a discussion with topic "What did you learn?" and message "Share what you found most interesting." automatically
    And I reload the page
    Then I should see "Share what you found most interesting."
    And I should not see "Add a new discussion topic"

  Scenario: Students answer the discussion on the forum page
    Given User "teacher1" adds to "Chapter 3 forum" a discussion with topic "What did you learn?" and message "Share what you found most interesting." automatically
    When I am on the "Chapter 3 forum" "moodleoverflow activity" page logged in as "student1"
    Then I should see "Share what you found most interesting."
    And I should not see "Add a new discussion topic"
    When I click on "Answer" "link"
    And I set the following fields to these values:
      | Message | The examples on templates. |
    And I press "Post to forum"
    Then I should see "The examples on templates."
