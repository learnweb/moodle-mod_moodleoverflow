@mod @mod_moodleoverflow @javascript
Feature: Use moodleoverflow anonymously

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity  | moodleoverflow           |
      | course    | C1                       |
      | name      | Test moodleoverflow name |
      | anonymous | 2                        |

  @_file_upload
  Scenario: Other people should not see the questioners name in anonymous mode, not even as file author.
    Given I am on the "Test moodleoverflow name" "Activity" page logged in as "student1"
    And I press "Add a new discussion topic"
    And I set the following fields to these values:
      | Subject | This is Nina |
      | Message | She is nice. |
    And I upload "mod/moodleoverflow/tests/fixtures/NH.jpg" file to "Attachment" filemanager
    And I press "Post to forum"
    Then I should see "Anonymous (You)"
    When I am on the "Test moodleoverflow name" "Activity" page logged in as "teacher1"
    And I follow "This is Nina"
    Then I should not see "Student 1"
    And I should see "Questioner"
    Given I follow "Edit"
    And I click on "NH.jpg" "link"
    Then the field "Author" matches value "Anonymous"
