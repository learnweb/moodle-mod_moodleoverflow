@mod @mod_moodleoverflow
Feature: Users search a moodleoverflow and see the found words in the discussion

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role    |
      | student1 | Student   | 1        | student1@example.com | 10       | student |
    And the following "activities" exist:
      | activity       | name                | intro                           | course | idnumber |
      | moodleoverflow | Test Moodleoverflow | Test moodleoverflow description | C1     | 1        |
    And User "student1" adds to "Test Moodleoverflow" a discussion with topic "Build tools" and message "Should we use Gradle or Maven?" automatically

  Scenario: A found word is highlighted in the discussion
    Given I am on the "Test Moodleoverflow" "moodleoverflow activity" page logged in as "student1"
    When I set the field "Search this forum" to "gradle"
    And I press "Search this forum"
    And I follow "Build tools"
    Then I should see "Gradle" in the "mark" "css_element"
    When I follow "Remove highlighting"
    Then "mark" "css_element" should not exist
