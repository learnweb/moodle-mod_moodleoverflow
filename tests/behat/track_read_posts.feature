@mod @mod_moodleoverflow @javascript
Feature: A teacher can set one of 3 possible options for tracking read moodleoverflow posts
  In order to ease the moodleoverflow posts follow up
  As a user
  I need to distinct the unread posts from the read ones

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role           |
      | student1 | Student   | 1        | student1@mail.com | 10       | student        |

  Scenario: Tracking moodleoverflow posts off
    When The admin posts "Test post subject" in "Test moodleoverflow name" with tracking type "0"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should not see "1 unread post"
    And I follow "Test moodleoverflow name"
    And I should not see "Track unread posts"

  Scenario: Tracking moodleoverflow posts optional
    When The admin posts "Test post subject" in "Test moodleoverflow name" with tracking type "1"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "1 unread post"
    And I click in moodleoverflow on "link" type:
      | Test moodleoverflow name | Don't track unread posts |
    And I wait to be redirected
    And I am on "Course 1" course homepage
    And I should not see "1 unread post"
    And I click in moodleoverflow on "link" type:
      | Test moodleoverflow name | Track unread posts |
    And I wait to be redirected
    And I click on "1" "link" in the "Test post subject" moodleoverflow discussion card
    And I am on "Course 1" course homepage
    And I should not see "1 unread post"

  Scenario: Tracking moodleoverflow posts forced
    Given the following config values are set as admin:
      | allowforcedreadtracking | 1 | moodleoverflow |
    And The admin posts "Test post subject" in "Test moodleoverflow name" with tracking type "2"
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I am on "Course 1" course homepage
    And I should not see "1 unread post"

  Scenario: Tracking moodleoverflow posts forced (with force disabled)
    Given the following config values are set as admin:
      | allowforcedreadtracking | 1 | moodleoverflow |
    And The admin posts "Test post subject" in "Test moodleoverflow name" with tracking type "2"
    And the following config values are set as admin:
      | allowforcedreadtracking | 0 | moodleoverflow |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "1 unread post"
    And I click in moodleoverflow on "link" type:
      | Test moodleoverflow name | Don't track unread posts |
    And I wait to be redirected
    And I am on "Course 1" course homepage
    And I should not see "1 unread post"
    And I click in moodleoverflow on "link" type:
      | Test moodleoverflow name | Track unread posts |
    And I wait to be redirected
    And I click on "1" "link" in the "Test post subject" moodleoverflow discussion card
    And I am on "Course 1" course homepage
    And I should not see "1 unread post"

  Scenario: Marking all unread posts as read.
    Given the following config values are set as admin:
      | allowforcedreadtracking | 1 | moodleoverflow |
    And The admin posts "Test post subject" in "Test moodleoverflow name" with tracking type "2"
    And The admin posts "Test post subject 2" in "Test moodleoverflow name"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "2 unread post"
    And I follow "Test moodleoverflow name"
    When I click on "Mark all posts as read" "link"
    And I am on "Course 1" course homepage
    Then I should not see "2 unread post"
