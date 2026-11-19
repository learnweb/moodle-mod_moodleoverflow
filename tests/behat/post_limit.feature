@mod @mod_moodleoverflow
Feature: Teachers limit the number of posts per participant

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role           |
      | student1 | Student   | 1        | student1@example.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@example.com | 11       | editingteacher |
    And the following "activities" exist:
      | activity       | name          | course | idnumber | postlimitquestions | postlimitanswers |
      | moodleoverflow | Limited forum | C1     | 1        | 1                  | 1                |
    And User "teacher1" adds to "Limited forum" a discussion with topic "Loops" and message "How do loops work?" automatically

  Scenario: A student who has used up the questions and answers cannot post until the limit allows it again
    Given User "student1" adds to "Limited forum" a discussion with topic "Arrays" and message "How long can an array be?" automatically
    And User "student1" replies "How do loops work?" with "They repeat code." automatically
    When I am on the "Limited forum" "moodleoverflow activity" page logged in as "student1"
    Then I should see "each participant can write 1 question and 1 answer or comment per day."
    And I should see "You have reached your limit of questions."
    And the "Add a new discussion topic" "button" should be disabled
    When I follow "Loops"
    Then I should see "You have reached your limit of answers and comments."
    And "[data-action='mod_moodleoverflow/reply']" "css_element" should not exist
