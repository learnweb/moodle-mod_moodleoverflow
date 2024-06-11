@mod @mod_moodleoverflow @javascript
  Feature: Moodleoverflows can start in a limited answer mode, where answers from
    students are not enabled until a set date.

  Background:
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

  Scenario: With limited answer mode on, a teacher can answer a post that a student can not. When the teacher changes the
            limitedanswer date to now, the student can now answer the post.
    Given the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber | limitedanswer  |
      | moodleoverflow | Test Moodleoverflow       | Test moodleoverflow description  | C1      |  1        | ##now +1 day## |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Moodleoverflow"
    And I add a new discussion to "Test Moodleoverflow" moodleoverflow with:
      | Subject | Forum post 1 |
      | Message | This is the question message |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test Moodleoverflow"
    And I follow "Forum post 1"
    And I click on "Answer" "text"
    Then I should not see "Your reply"
    When I set the "Test Moodleoverflow" moodleoverflow limitedanswertime to now
    And I am on "Course 1" course homepage
    And I follow "Test Moodleoverflow"
    And I follow "Forum post 1"
    And I click on "Answer" "text"
    Then I should see "Your reply"
    And I set the following fields to these values:
      | Subject | Re: Forum post 1 |
      | Message | This is the answer message |
    And I press "Post to forum"
    Then I should see "This is the answer message"
     And I should see "This is the question message"