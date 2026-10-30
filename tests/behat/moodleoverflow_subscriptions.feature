@mod @mod_moodleoverflow
Feature: A user can control their own subscription preferences for a moodleoverflow
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my moodleoverflow subscriptions

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                   | idnumber | role      |
      | student1 | Student   | One      | student.one@example.com | 10       | student   |

  Scenario: A disallowed subscription moodleoverflow cannot be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 3              |
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject" and message "Test post message" automatically
    When I am on the "Test moodleoverflow name" "moodleoverflow activity" page logged in as "student1"
    Then I should "not" see the elements:
      | Subscribe to this forum | Unsubscribe from this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card

  Scenario: A forced subscription moodleoverflow cannot be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 1              |
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject" and message "Test post message" automatically
    When I am on the "Test moodleoverflow name" "moodleoverflow activity" page logged in as "student1"
    Then I should "not" see the elements:
      | Subscribe to this forum | Unsubscribe from this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "icon" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "icon" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card

  @javascript
  Scenario: An optional moodleoverflow can be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 0              |
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject" and message "Test post message" automatically
    When I am on the "Test moodleoverflow name" "moodleoverflow activity" page logged in as "student1"
    Then I should see "Subscribe to this forum"
    And I should not see "Unsubscribe from this forum"
    And I click on "Subscribe to this forum" "button"
    And I reload the page
    And I should see "Unsubscribe from this forum"
    And I should not see "Subscribe to this forum"
