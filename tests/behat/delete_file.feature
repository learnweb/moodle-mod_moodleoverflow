@mod @mod_moodleoverflow @javascript @_file_upload
Feature: Delete attachments
  In order to delete discussions also files need to be deleted

  Scenario: Delete a file
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role           |
      | student1 | Student   | 1        | student1@mail.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@mail.com | 11       | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode "on"
    And I add a moodleoverflow to course "C1" section "1" and I fill the form with:
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
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test moodleoverflow name"
    And I follow "Forum post 1"
    And I should see "This is the message of the answer post"
    And "//div[contains(@class, 'moodleoverflowpost')]//div[contains(@class, 'attachments')]//img[contains(@src, 'NH.jpg')]" "xpath_element" should exist
    And I click on "Delete" "link"
    And I click on "Continue" "button"
    Then I should "" see the elements:
      | Test moodleoverflow name | This is the body |
    And I should not see "This is the message of the answer post"
    And "//div[contains(@class, 'moodleoverflowpost')]//div[contains(@class, 'attachments')]//img[contains(@src, 'NH.jpg')]" "xpath_element" should not exist
