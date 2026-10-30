@mod @mod_moodleoverflow @mod_moodleoverflow_delete
Feature: Teachers and students can delete posts

  Background:
    Given I add a moodleoverflow discussion with posts from different users

  @javascript
  Scenario: Teacher deletes the whole discussion
    Given I am on the "Discussion 1" "mod_moodleoverflow > Discussion" page logged in as "teacher1"
    And I try to delete moodleoverflow post "Message from teacher"
    Then I should not see "Discussion 1"

  @javascript
  Scenario: Teacher only deletes reply post
    Given I am on the "Discussion 1" "mod_moodleoverflow > Discussion" page logged in as "teacher1"
    And I try to delete moodleoverflow post "Answer from student"
    Then I should see "Discussion 1"
    And I should not see "Answer from student"

  @javascript
  Scenario: Student deletes own reply post
    Given I am on the "Discussion 1" "mod_moodleoverflow > Discussion" page logged in as "student1"
    And I try to delete moodleoverflow post "Answer from student"
    Then I should see "Discussion 1"
    And I should not see "Answer from student"

  Scenario: Student can not delete his post which the teacher already replied
    Given I am on the "Discussion 1" "mod_moodleoverflow > Discussion" page logged in as "teacher1"
    And I comment "Answer from student" with "comment from teacher"
    And I am on the "Discussion 1" "mod_moodleoverflow > Discussion" page logged in as "student1"
    Then "Delete" "link" should not exist in the "//div[contains(@class, 'moodleoverflowpost')][contains(., 'Answer from student')]" "xpath_element"
