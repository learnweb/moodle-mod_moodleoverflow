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
    Given I am on the "Test moodleoverflow" "moodleoverflow activity" page logged in as "student1"
    And I click on "Add a new discussion topic" "text"
    And I set the following fields to these values:
      | Subject | This is Nina |
      | Message | She is nice. |
    And I upload "mod/moodleoverflow/tests/fixtures/NH.jpg" file to "Attachment" filemanager
    And I press "Post to forum"
    Then I should see "Anonymous (You)"
    When I am on the "This is Nina" "mod_moodleoverflow > Discussion" page logged in as "teacher1"
    Then I should not see "Student 1"
    And I should see "Questioner"
    When I click in moodleoverflow on "link" type:
      | Edit | NH.jpg |
    Then the field "Author" matches value "Anonymous"

  Scenario: Teachers who may post with their name are shown by name in anonymous mode
    Given the following "permission overrides" exist:
      | capability                      | permission | role           | contextlevel | reference |
      | mod/moodleoverflow:postwithname | Allow      | editingteacher | System       |           |
    And User "student1" adds to "Test moodleoverflow" a discussion with topic "Anonymous question" and message "Who answers?" automatically
    And User "teacher1" replies "Who answers?" with "The teacher answers." automatically
    When I am on the "Anonymous question" "mod_moodleoverflow > Discussion" page logged in as "student1"
    Then I should see "Teacher 1" in the moodleoverflow post "The teacher answers."
    And I should see "Anonymous (You)" in the moodleoverflow post "Who answers?"
