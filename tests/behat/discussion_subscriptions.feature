@mod @mod_moodleoverflow @javascript
Feature: A user can control their own moodleoverflow subscription preferences for a discussion
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my discussion subscriptions

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role |
      | student1 | Student   | One      | student1@mail.com | 10       | student |

  Scenario: An optional moodleoverflow can have discussions subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 0              |
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject one" and message "Test post message one" automatically
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject two" and message "Test post message two" automatically
    When I am on the "Test moodleoverflow name" "moodleoverflow activity" page logged in as "student1"
    Then I should see "Subscribe to this forum"
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are not subscribed to this discussion. Click to subscribe." "icon" in the "Test post subject one" moodleoverflow discussion card
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "icon" in the "Test post subject one" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are not subscribed to this discussion. Click to subscribe." "icon" in the "Test post subject one" moodleoverflow discussion card
    And I reload the page
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I click on "Subscribe to this forum" "button"
    And I reload the page
    And I should see "Unsubscribe from this forum"
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "Unsubscribe from this forum" "button"
    And I reload the page
    And I should see "Subscribe to this forum"
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card

  Scenario: An automatic subscription moodleoverflow can have discussions unsubscribed from
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 2              |
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject one" and message "Test post message one" automatically
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject two" and message "Test post message two" automatically
    When I am on the "Test moodleoverflow name" "moodleoverflow activity" page logged in as "student1"
    Then I should see "Unsubscribe from this forum"
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "icon" in the "Test post subject one" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I click on "You are not subscribed to this discussion. Click to subscribe." "icon" in the "Test post subject one" moodleoverflow discussion card
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "icon" in the "Test post subject one" moodleoverflow discussion card
    And I reload the page
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject one" and "" moodleoverflow discussion card
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject two" and "" moodleoverflow discussion card
    And I click on "Unsubscribe from this forum" "button"
    And I reload the page
    And I should see "Subscribe to this forum"
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
    And I click on "Subscribe to this forum" "button"
    And I reload the page
    And I should see "Unsubscribe from this forum"
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "" exist in the "Test post subject one" and "Test post subject two" moodleoverflow discussion card
