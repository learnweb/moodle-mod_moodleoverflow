@mod @mod_moodleoverflow
Feature: A user can control their own subscription preferences for a moodleoverflow
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my moodleoverflow subscriptions

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                   | idnumber | role      |
      | student1 | Student   | One      | student.one@example.com | 10       | student   |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode "on"

  Scenario: A disallowed subscription moodleoverflow cannot be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 3              |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I navigate as "student1" to "Course 1" "Test moodleoverflow name" ""
    Then I should "not" see the elements:
      | Subscribe to this forum | Unsubscribe from this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card

  Scenario: A forced subscription moodleoverflow cannot be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 1              |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I navigate as "student1" to "Course 1" "Test moodleoverflow name" ""
    Then I should "not" see the elements:
      | Subscribe to this forum | Unsubscribe from this forum |
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card
    And "You are not subscribed to this discussion. Click to subscribe." "link" should "not" exist in the "Test post subject" and "" moodleoverflow discussion card

  Scenario: An optional moodleoverflow can be subscribed to
    Given the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       | forcesubscribe |
      | moodleoverflow | Test moodleoverflow name | Test moodleoverflow description  | C1      | moodleoverflow | 0              |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I navigate as "student1" to "Course 1" "Test moodleoverflow name" ""
    Then I should see "Subscribe to this forum"
    And I should not see "Unsubscribe from this forum"
    And I follow "Subscribe to this forum"
    And I should "" see the elements:
      | Student One will be notified of new posts in 'Test moodleoverflow name' | Unsubscribe from this forum |
    And I should not see "Subscribe to this forum"
