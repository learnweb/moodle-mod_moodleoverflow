@mod @mod_moodleoverflow
Feature: Students can edit or delete their moodleoverflow posts within a set time limit
  In order to refine moodleoverflow posts
  As a user
  I need to edit or delete my moodleoverflow posts within a certain period of time after posting

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email             | idnumber | role           |
      | student1 | Student   | 1        | student1@mail.com | 10       | student        |
    And the following "activities" exist:
      | activity       | name                     | intro                            | course  | idnumber       |
      | moodleoverflow | Test moodleoverflow | Test moodleoverflow description  | C1      | moodleoverflow |
    And I navigate as "student1" to "Course 1" "Test moodleoverflow" ""
    And I add a new discussion to "Test moodleoverflow" moodleoverflow with:
      | Subject | Moodleoverflow post subject |
      | Message | This is the body |

  Scenario: Edit moodleoverflow post
    Given I click in moodleoverflow on "link" type:
      | Moodleoverflow post subject | Edit |
    When I set the following fields to these values:
      | Subject | Edited post subject |
      | Message | Edited post body |
    And I press "Save changes"
    And I wait to be redirected
    Then I should "" see the elements:
      | Edited post subject | Edited post body |

  Scenario: Delete moodleoverflow post
    Given I click in moodleoverflow on "link" type:
      | Moodleoverflow post subject | Delete |
    And I press "Continue"
    Then I should not see "Moodleoverflow post subject"
