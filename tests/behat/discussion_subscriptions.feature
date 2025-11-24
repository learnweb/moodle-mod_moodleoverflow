@mod @mod_moodleoverflow
Feature: A user can control their own moodleoverflow subscription preferences for a discussion
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my discussion subscriptions

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role |
      | student1 | Student   | One      | student1@mail.com | 10       | student |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode "on"

  Scenario: An optional moodleoverflow can have discussions subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 0              |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    When I navigate as "student1" to "Course 1" "Test moodleoverflow name" ""
    Then I should see "Subscribe to this forum"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" moodleoverflow discussion card
    And I should "" see the elements:
      | Student One will be notified of new posts in 'Test post subject one' of 'Test moodleoverflow name' | Subscribe to this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject one" moodleoverflow discussion card
    And I should "" see the elements:
      | Student One will NOT be notified of new posts in 'Test post subject one' of 'Test moodleoverflow name' | Subscribe to this forum |
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" moodleoverflow discussion card
    And I should "" see the elements:
      | Student One will be notified of new posts in 'Test post subject one' of 'Test moodleoverflow name' | Subscribe to this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I follow "Subscribe to this forum"
    And I should "" see the elements:
      | Student One will be notified of new posts in 'Test moodleoverflow name' | Unsubscribe from this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I follow "Unsubscribe from this forum"
    And I should "" see the elements:
      | Student One will NOT be notified of new posts in 'Test moodleoverflow name' | Subscribe to this forum |
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card

  Scenario: An automatic subscription moodleoverflow can have discussions unsubscribed from
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 2              |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    When I navigate as "student1" to "Course 1" "Test moodleoverflow name" ""
    Then I should see "Unsubscribe from this forum"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject one" moodleoverflow discussion card
    And I should "" see the elements:
      | Student One will NOT be notified of new posts in 'Test post subject one' of 'Test moodleoverflow name' | Unsubscribe from this forum |
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" moodleoverflow discussion card
    And I should "" see the elements:
      | Student One will be notified of new posts in 'Test post subject one' of 'Test moodleoverflow name' | Unsubscribe from this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject one" moodleoverflow discussion card
    And I should "" see the elements:
      | Student One will NOT be notified of new posts in 'Test post subject one' of 'Test moodleoverflow name' | Unsubscribe from this forum |
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I follow "Unsubscribe from this forum"
    And I should "" see the elements:
      | Student One will NOT be notified of new posts in 'Test moodleoverflow name' | Subscribe to this forum |
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I follow "Subscribe to this forum"
    And I should "" see the elements:
      | Student One will be notified of new posts in 'Test moodleoverflow name' | Unsubscribe from this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
