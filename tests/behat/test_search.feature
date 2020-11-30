@mod @mod_moodleoverflow @mod_moodleoverflow_search
Feature: Add moodleoverflow activities and discussions
  In order to find discussions
  I need to be able to search them

  Background: : Add a moodleoverflow and a discussion
    Given the following config values are set as admin:
      | enableglobalsearch | 1    |
      | searchengine       | simpledb |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Moodleoverflow" to section "1" and I fill the form with:
      | Moodleoverflow name | Test moodleoverflow name |
      | Description | Test forum description |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Forum post 1 |
      | Message | This is the body |
    And I log out
    And I log in as "teacher1"
    And I update the global search index
    And I log out

  Scenario: As a teacher I should see all discussions in my course
    Given I log in as "teacher1"
    And I go to "search/index.php"
    And I fill in "id_q" with "Forum post 1"
    And I press "id_submitbutton"
    Then I should see "Forum post 1"
    And I should see "This is the body"

  Scenario: As an enrolled student I should see all discussions in my course
    Given I log in as "student1"
    And I go to "search/index.php"
    And I fill in "id_q" with "Forum post 1"
    And I press "id_submitbutton"
    Then I should see "Forum post 1"
    And I should see "This is the body"

  @test
  Scenario: As an unenrolled student I should see all discussions in my course
    Given I log in as "student2"
    And I go to "search/index.php"
    And I fill in "id_q" with "Forum post 1"
    And I press "id_submitbutton"
    Then I should not see "Forum post 1"
    And I should not see "This is the body"