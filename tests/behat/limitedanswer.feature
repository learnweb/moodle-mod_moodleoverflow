@mod @mod_moodleoverflow
Feature: Moodleoverflows can start in a limited answer mode, where answers from students are not enabled until a set date.

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role           |
      | student1 | Student   | 1        | student1@mail.com | 10       | student        |
      | teacher1 | Teacher   | 1        | teacher1@mail.com | 11       | editingteacher |

  Scenario: With limited answer mode on, a teacher can answer a post that a student can not. When the teacher changes the
            limitedanswer starttime to now, the student can now answer the post.
    Given the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber | la_starttime   |
      | moodleoverflow | Test Moodleoverflow       | Test moodleoverflow description  | C1      |  1        | ##now +1 day## |
    And User "admin" adds to "Test Moodleoverflow" a discussion with topic "Forum post 1" and message "This is the question message" automatically
    When I am on the "Forum post 1" "mod_moodleoverflow > Discussion" page logged in as "student1"
    # Before the start time, "Answer" is only a text with a help icon, not a link.
    Then "Answer" "link" should not exist
    When I set the "Test Moodleoverflow" moodleoverflow limitedanswerstarttime to now
    And I am on the "Forum post 1" "mod_moodleoverflow > Discussion" page
    And I click on "Answer" "link"
    Then I should see "Your reply"
    And I set the following fields to these values:
      | Message | This is the answer message |
    And I press "Post to forum"
    Then I should "" see the elements:
      | This is the answer message | This is the question message |

  @javascript
  Scenario: Setting up the limited answer mode, the times need to be in the right order
    Given the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber |
      | moodleoverflow | Test Moodleoverflow       | Test moodleoverflow description  | C1      |  1        |
    And I am on the "Test Moodleoverflow" "moodleoverflow activity editing" page logged in as "teacher1"
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
    Then I should "" see the elements:
      | End time must be in the future | The end time must be after the start time |

  @javascript
  Scenario: Setting up the limited answer mode, the start times need to be in the future
    Given the following "activities" exist:
      | activity       | name                      | intro                            | course  |  idnumber |
      | moodleoverflow | Test Moodleoverflow       | Test moodleoverflow description  | C1      |  1        |
    And I am on the "Test Moodleoverflow" "moodleoverflow activity editing" page logged in as "teacher1"
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
