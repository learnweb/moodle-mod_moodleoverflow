@mod @mod_moodleoverflow @javascript @mod_moodleoverflow_delete @javascript @_file_upload
Feature: Delete attachments
  In order to delete discussions also files need to be deleted

  Background: Add a moodleoverflow and a discussion
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
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
    And I add a "moodleoverflow" activity to course "C1" section "1" and I fill the form with:
      | Moodleoverflow name | Test moodleoverflow name |
      | Description | Test forum description |
    And I add a new discussion to "Test moodleoverflow name" moodleoverflow with:
      | Subject | Forum post 1 |
      | Message | This is the body |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test moodleoverflow name"
    And I follow "Forum post 1"
    And I click on "Answer" "link"
    And I set the following fields to these values:
      | Subject | A reply post |
      | Message | This is the message of the answer post |
    And I upload "mod/moodleoverflow/tests/fixtures/NH.jpg" file to "Attachment" filemanager
    And I press "Post to forum"

  Scenario Outline: delete with role
    Given I log in as "<role>"
    And I am on "Course 1" course homepage
    And I follow "Test moodleoverflow name"
    And I follow "Forum post 1"
    And I should see "This is the message of the answer post"
    And "//div[contains(@class, 'moodleoverflowpost')]//div[contains(@class, 'attachments')]//img[contains(@src, 'NH.jpg')]" "xpath_element" should exist
    And I click on "Delete" "link"
    And I click on "Continue" "button"
    Then I should see "Test moodleoverflow name"
    And I should see "This is the body"
    And I should not see "This is the message of the answer post"
    And "//div[contains(@class, 'moodleoverflowpost')]//div[contains(@class, 'attachments')]//img[contains(@src, 'NH.jpg')]" "xpath_element" should not exist

    Examples:
      | role     |
      | student1 |
