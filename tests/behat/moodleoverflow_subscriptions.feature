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
      | Subscribe to forum | Unsubscribe from forum |
    And "You are subscribed to this discussion" "icon" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card
    And "Subscribe to this discussion" "button" should not exist

  Scenario: A forced subscription moodleoverflow cannot be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 1              |
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject" and message "Test post message" automatically
    When I am on the "Test moodleoverflow name" "moodleoverflow activity" page logged in as "student1"
    Then I should "not" see the elements:
      | Subscribe to forum | Unsubscribe from forum |
    And "You are subscribed to this discussion" "icon" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card
    And "Subscribe to this discussion" "button" should not exist

  @javascript
  Scenario: An optional moodleoverflow can be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 0              |
    And User "admin" adds to "Test moodleoverflow name" a discussion with topic "Test post subject" and message "Test post message" automatically
    When I am on the "Test moodleoverflow name" "moodleoverflow activity" page logged in as "student1"
    And I click on "Forum actions" "button"
    Then I should see "Subscribe to forum"
    And I should not see "Unsubscribe from forum"
    And I click on "Subscribe to forum" "button"
    And I reload the page
    And I click on "Forum actions" "button"
    And I should see "Unsubscribe from forum"
    And I should not see "Subscribe to forum"
