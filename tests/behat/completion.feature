@mod @mod_moodleoverflow
Feature: A moodleoverflow is completed by writing posts

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity       | name         | course | idnumber | singlediscussion | completion | completionposts |
      | moodleoverflow | Step 1 forum | C1     | 1        | 1                | 2          | 1               |
    And User "teacher1" adds to "Step 1 forum" a discussion with topic "First step" and message "What do you expect from this course?" automatically

  @javascript
  Scenario: A reply completes the moodleoverflow
    Given I am on the "Course 1" course page logged in as "student1"
    And the "Write posts: 1" completion condition of "Step 1 forum" is displayed as "todo"
    When User "student1" replies "What do you expect from this course?" with "Many examples." automatically
    And I reload the page
    Then the "Write posts: 1" completion condition of "Step 1 forum" is displayed as "done"
