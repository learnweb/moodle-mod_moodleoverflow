@mod @mod_moodleoverflow @javascript
Feature: A user can see all posts that a user has written across their courses

  Background:
    Given the site is running Moodle version 5.3 or higher
    And I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role           |
      | student1 | Student   | 1        | student1@mail.com | 10       | student        |
      | student2 | Student   | 2        | student2@mail.com | 11       | student        |
      | teacher1 | Teacher   | 1        | teacher1@mail.com | 12       | editingteacher |
    And the following "activities" exist:
      | activity       | course | name            | anonymous |
      | moodleoverflow | C1     | Open overflow   | 0         |
      | moodleoverflow | C1     | Secret overflow | 2         |
    And User "student1" adds to "Open overflow" a discussion with topic "Open topic" and message "This post is public" automatically
    And User "student1" adds to "Secret overflow" a discussion with topic "Secret topic" and message "This post is hidden" automatically

  Scenario: A user sees every post they wrote themselves, the anonymous ones included
    Given I log in as "student1"
    When I am on the moodleoverflow posts page of "student1"
    Then I should "" see the elements:
      | Open overflow | Open topic | This post is public |
    And I should "" see the elements:
      | Secret overflow | Secret topic | This post is hidden |

  Scenario: A user does not see the anonymous posts of another user from the same course
    Given I log in as "student2"
    When I am on the moodleoverflow posts page of "student1"
    Then I should "" see the elements:
      | Open overflow | Open topic | This post is public |
    And I should "not" see the elements:
      | Secret overflow | Secret topic | This post is hidden |

  Scenario: A user only sees the posts from the courses they share with the other user
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C2     | student |
    And the following "activities" exist:
      | activity       | course | name              |
      | moodleoverflow | C2     | Unshared overflow |
    And User "student1" adds to "Unshared overflow" a discussion with topic "Unshared topic" and message "This post is out of reach" automatically
    And I log in as "student2"
    When I am on the moodleoverflow posts page of "student1"
    Then I should "" see the elements:
      | Open overflow | Open topic |
    And I should "not" see the elements:
      | Unshared overflow | Unshared topic | This post is out of reach |

  Scenario: A post that is still waiting for a review is only visible to its author
    Given the following "activities" exist:
      | activity       | course | name              | needsreview |
      | moodleoverflow | C1     | Reviewed overflow | 2           |
    And User "student1" adds to "Reviewed overflow" a discussion with topic "Pending topic" and message "This post is not reviewed yet" automatically
    And I log in as "student2"
    When I am on the moodleoverflow posts page of "student1"
    Then I should "not" see the elements:
      | Pending topic | This post is not reviewed yet |
    When I log out
    And I log in as "student1"
    And I am on the moodleoverflow posts page of "student1"
    Then I should "" see the elements:
      | Pending topic | This post is not reviewed yet |
