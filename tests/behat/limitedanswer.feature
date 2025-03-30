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
            limitedanswer starttime to now, the student can now answer the post.
    Given the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber | la_starttime   |
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
    When I set the "Test Moodleoverflow" moodleoverflow limitedanswerstarttime to now
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

  Scenario: Setting up the limited answer mode, the times need to be in the right order
    Given the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber |
      | moodleoverflow | Test Moodleoverflow       | Test moodleoverflow description  | C1      |  1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Moodleoverflow"
    And I navigate to "Settings" in current page administration
    And I follow "Limited Answer Mode"
    And I click on "la_starttime[enabled]" "checkbox"
    And I set the following fields to these values:
     | id_la_starttime_day | ##tomorrow##%d## |
     | id_la_starttime_month | ##tomorrow##%B## |
     | id_la_starttime_year | ##tomorrow##%Y## |
     | id_la_starttime_hour | 12 |
     | id_la_starttime_minute | 30 |
    And I click on "la_endtime[enabled]" "checkbox"
    And I set the following fields to these values:
     | id_la_endtime_day | ##yesterday##%d## |
     | id_la_endtime_month | ##yesterday##%B## |
     | id_la_endtime_year | ##yesterday##%Y## |
     | id_la_endtime_hour | 12 |
     | id_la_endtime_minute | 30 |
    When I press "Save and display"
    And I follow "Limited Answer Mode"
    And I click on "#collapseElement-5" "css_element"
    Then I should see "End time must be in the future"
    And I should see "The end time must be after the start time"

  Scenario: Setting up the limited answer mode, the start times need to be in the future
    Given the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber |
      | moodleoverflow | Test Moodleoverflow       | Test moodleoverflow description  | C1      |  1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test Moodleoverflow"
    And I navigate to "Settings" in current page administration
    And I follow "Limited Answer Mode"
    And I click on "la_starttime[enabled]" "checkbox"
    And I set the following fields to these values:
      | id_la_starttime_day | ##yesterday##%d## |
      | id_la_starttime_month | ##yesterday##%B## |
      | id_la_starttime_year | ##yesterday##%Y## |
      | id_la_starttime_hour | 12 |
      | id_la_starttime_minute | 30 |
    When I press "Save and display"
    And I follow "Limited Answer Mode"
    And I click on "#collapseElement-5" "css_element"
    Then I should see "Start time must be in the future"
