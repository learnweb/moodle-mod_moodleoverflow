@mod @mod_moodleoverflow @javascript
Feature: Use moodleoverflow anonymously

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role           |
      | student1 | Student   | 1        | student1@mail.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@mail.com | 11       | editingteacher |
    And the following "activity" exists:
      | activity  | moodleoverflow      |
      | course    | C1                  |
      | name      | Test moodleoverflow |
      | anonymous | 2                   |

  @_file_upload
  Scenario: Other people should not see the questioners name in anonymous mode, not even as file author.
    Given I navigate as "student1" to "Course 1" "Test moodleoverflow" ""
    And I press "Add a new discussion topic"
    And I set the following fields to these values:
      | Subject | This is Nina |
      | Message | She is nice. |
    And I upload "mod/moodleoverflow/tests/fixtures/NH.jpg" file to "Attachment" filemanager
    And I press "Post to forum"
    Then I should see "Anonymous (You)"
    When I navigate as "teacher1" to "Course 1" "Test moodleoverflow" ""
    And I follow "This is Nina"
    Then I should not see "Student 1"
    And I should see "Questioner"
    When I click in moodleoverflow on "link" type:
      | Edit | NH.jpg |
    Then the field "Author" matches value "Anonymous"
