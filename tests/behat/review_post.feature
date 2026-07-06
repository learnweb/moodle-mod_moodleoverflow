@mod @mod_moodleoverflow @javascript
Feature: Teachers can review posts in a moodleoverflow that requires reviewing
  In order to control which posts become visible
  As a teacher
  I need to approve or reject posts that are pending review

  Background:
    Given I set up a moodleoverflow with a student post pending review

  Scenario: A teacher approves a student post that is pending review
    Given I navigate as "teacher1" to "Course 1" "Moodleoverflow 1" "Discussion 1"
    Then I should see "Answer from student"
    And I should see "Approve"
    When I click on the "approve" moodleoverflow review button
    Then I should see "The post was approved."
    And I should see "There are no more posts in this forum that need to be reviewed."

  Scenario: A teacher rejects a student post that is pending review
    Given I navigate as "teacher1" to "Course 1" "Moodleoverflow 1" "Discussion 1"
    Then I should see "Reject"
    When I click on the "reject" moodleoverflow review button
    And I set the field with xpath "//textarea[contains(@class, 'reject-reason')]" to "This post was not good!"
    And I click on the "reject-submit" moodleoverflow review button
    Then I should see "The post was rejected."
    And I should see "There are no more posts in this forum that need to be reviewed."
