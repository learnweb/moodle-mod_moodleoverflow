@mod @mod_moodleoverflow @javascript
Feature: Users vote on posts and mark answers as solution or helpful
  In order to find good answers
  As a user
  I need to vote on posts and mark answers

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role           |
      | teacher1 | Teacher   | 1        | teacher1@mail.com | 10       | editingteacher |
      | student1 | Student   | 1        | student1@mail.com | 11       | student        |
      | student2 | Student   | 2        | student2@mail.com | 12       | student        |
    And the following "activities" exist:
      | activity       | course | name                | allowmultiplemarks |
      | moodleoverflow | C1     | Test moodleoverflow | 0                  |
    And User "student1" adds to "Test moodleoverflow" a discussion with topic "Question topic" and message "Question from student1" automatically
    And User "student2" replies "Question from student1" with "First answer" automatically
    And User "teacher1" replies "Question from student1" with "Second answer" automatically

  Scenario: A student votes on an answer, changes the vote and removes it
    When I navigate as "student1" to "Course 1" "Test moodleoverflow" "Question topic"
    And I click on the "upvote" button of the moodleoverflow post "First answer"
    Then the "upvote" button of the moodleoverflow post "First answer" should be "clicked"
    And the moodleoverflow post "First answer" should have "1" votes
    When I click on the "downvote" button of the moodleoverflow post "First answer"
    Then the "downvote" button of the moodleoverflow post "First answer" should be "clicked"
    And the "upvote" button of the moodleoverflow post "First answer" should be "notclicked"
    And the moodleoverflow post "First answer" should have "-1" votes
    # After a reload, the state comes from the template and a click removes the vote.
    When I reload the page
    Then the "downvote" button of the moodleoverflow post "First answer" should be "clicked"
    When I click on the "downvote" button of the moodleoverflow post "First answer"
    Then the "downvote" button of the moodleoverflow post "First answer" should be "notclicked"
    And the moodleoverflow post "First answer" should have "0" votes

  Scenario: Without rating changes the vote buttons stay visible but cannot be clicked after voting
    Given the following config values are set as admin:
      | allowratingchange | 0 | moodleoverflow |
    When I navigate as "student1" to "Course 1" "Test moodleoverflow" "Question topic"
    And I click on the "upvote" button of the moodleoverflow post "First answer"
    Then the "downvote" button of the moodleoverflow post "First answer" should be "disabled"
    And the "upvote" button of the moodleoverflow post "First answer" should be "clicked"
    And the vote buttons of the moodleoverflow post "First answer" should not be clickable
    When I reload the page
    Then the "downvote" button of the moodleoverflow post "First answer" should be "disabled"
    And the "upvote" button of the moodleoverflow post "First answer" should be "clicked"
    And the vote buttons of the moodleoverflow post "First answer" should not be clickable
    # Votes on other posts are still possible.
    And the "upvote" button of the moodleoverflow post "Second answer" should be "notclicked"

  Scenario: A teacher marks an answer as solution and the mark moves to another answer
    When I navigate as "teacher1" to "Course 1" "Test moodleoverflow" "Question topic"
    And I click on the "solved" button of the moodleoverflow post "First answer"
    Then the moodleoverflow post "First answer" should be marked as "solution"
    And I should see "Remove solution mark" in the moodleoverflow post "First answer"
    And I should see "Mark as solution" in the moodleoverflow post "Second answer"
    And I should not see "Also mark as solution"
    When I click on the "solved" button of the moodleoverflow post "Second answer"
    Then the moodleoverflow post "Second answer" should be marked as "solution"
    And I should see "Mark as solution" in the moodleoverflow post "First answer"
    And the moodleoverflow post "First answer" should not be marked as "solution"
    # After a reload, the page shows the same.
    When I reload the page
    Then the moodleoverflow post "Second answer" should be marked as "solution"
    And the moodleoverflow post "First answer" should not be marked as "solution"
    And I should not see "Also mark as solution"
    When I click on the "solved" button of the moodleoverflow post "Second answer"
    Then I should see "Mark as solution" in the moodleoverflow post "Second answer"
    And the moodleoverflow post "Second answer" should not be marked as "solution"

  Scenario: The questioner marks several answers as helpful when multiple marks are allowed
    Given the following "activities" exist:
      | activity       | course | name                          | allowmultiplemarks |
      | moodleoverflow | C1     | Multiple marks moodleoverflow | 1                  |
    And User "student1" adds to "Multiple marks moodleoverflow" a discussion with topic "Multiple topic" and message "Multiple question" automatically
    And User "student2" replies "Multiple question" with "Answer one" automatically
    And User "teacher1" replies "Multiple question" with "Answer two" automatically
    When I navigate as "student1" to "Course 1" "Multiple marks moodleoverflow" "Multiple topic"
    And I click on the "helpful" button of the moodleoverflow post "Answer one"
    Then the moodleoverflow post "Answer one" should be marked as "helpful"
    And I should see "Not Helpful" in the moodleoverflow post "Answer one"
    And I should see "Also mark as helpful" in the moodleoverflow post "Answer two"
    When I click on the "helpful" button of the moodleoverflow post "Answer two"
    Then the moodleoverflow post "Answer two" should be marked as "helpful"
    And the moodleoverflow post "Answer one" should be marked as "helpful"
    When I click on the "helpful" button of the moodleoverflow post "Answer one"
    Then I should see "Also mark as helpful" in the moodleoverflow post "Answer one"
    And the moodleoverflow post "Answer one" should not be marked as "helpful"
    And the moodleoverflow post "Answer two" should be marked as "helpful"
    # After a reload, the page shows the same.
    When I reload the page
    Then I should see "Also mark as helpful" in the moodleoverflow post "Answer one"
    And I should see "Not Helpful" in the moodleoverflow post "Answer two"
