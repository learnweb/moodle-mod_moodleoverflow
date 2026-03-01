@mod @mod_moodleoverflow @javascript
Feature: Testing overview integration in moodleoverflow activity
  In order to summarize the moodleoverflow activity
  As a user
  I need to be able to see the moodleoverflow activity overview

  Background:
    Given the site is running Moodle version 5.1 or higher
    And I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role           |
      | student1 | Student   | 1        | student1@example.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@example.com | 11       | editingteacher |
    And the following "activities" exist:
      | activity       | name                | intro                           | course | idnumber | trackingtype | forcesubscribe |
      | moodleoverflow | Test Moodleoverflow | Test moodleoverflow description | C1     | 1        | 1            | 0              |
    And User "teacher1" adds to "Test Moodleoverflow" a discussion with topic "Topic1" and message "message1" automatically
    And User "student1" replies "message1" with "message2" automatically

  Scenario: The bin to mark all unread posts as read should work
    When I am on the "C1" "course > activities > moodleoverflow" page logged in as "teacher1"
    Then I should see "1" in the ".unread-bubble" "css_element"
    When I click on ".mark-read" "css_element"
    Then I should see "0" in the ".unread-bubble" "css_element"

  Scenario: The subscription toggle item should work
    Given I am on the "C1" "course > activities > moodleoverflow" page logged in as "student1"
    And I should not be subscribed to "Test Moodleoverflow"
    When I click on ".moodleoverflow-subscription-toggle .form-check-input" "css_element"
    And I reload the page
    Then I should " " be subscribed to "Test Moodleoverflow"

  Scenario: The readtracking toggle item should work
    Given I am on the "C1" "course > activities > moodleoverflow" page logged in as "student1"
    And I should " " have readtracking on in "Test Moodleoverflow"
    When I click on ".moodleoverflow-readtracking-toggle .form-check-input" "css_element"
    And I reload the page
    Then I should not have readtracking on in "Test Moodleoverflow"
