@mod @mod_moodleoverflow @javascript
Feature: Add moodleoverflow activities and discussions
  In order to discuss topics with other users
  As a teacher
  I need to add forum activities to moodle courses

  Scenario: Add a moodleoverflow and a discussion
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
