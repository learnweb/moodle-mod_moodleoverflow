@mod @mod_moodleoverflow @javascript @mod_moodleoverflow_delete @javascript
Feature: Teachers and students can delete posts

  Background:
    Given I add a moodleoverflow discussion with posts from different users

  Scenario: Teacher deletes the whole discussion
    Given I navigate as "teacher1" to "Course 1" "Moodleoverflow 1" "Discussion 1"
    And I try to delete moodleoverflow post "Message from teacher"
    Then I should not see "Discussion 1"

  Scenario: Teacher only deletes reply post
    Given I navigate as "teacher1" to "Course 1" "Moodleoverflow 1" "Discussion 1"
    And I try to delete moodleoverflow post "Answer from student"
    Then I should see "Discussion 1"
    And I should not see "Answer from student"

  Scenario: Student deletes own reply post
    Given I navigate as "student1" to "Course 1" "Moodleoverflow 1" "Discussion 1"
    And I try to delete moodleoverflow post "Answer from student"
    Then I should see "Discussion 1"
    And I should not see "Answer from student"

  Scenario: Student tries to delete his post which the teacher already replied
    Given I navigate as "teacher1" to "Course 1" "Moodleoverflow 1" "Discussion 1"
    And I comment "Answer from student" with "comment from teacher"
    And I log out
    And I navigate as "student1" to "Course 1" "Moodleoverflow 1" "Discussion 1"
    And I try to delete moodleoverflow post "Answer from student"
    Then I should see "You are not allowed to delete this post because it already has replies."
