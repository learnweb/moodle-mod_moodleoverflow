@mod @mod_moodleoverflow @javascript
Feature: Teachers can move a discussion in one moodleoverflow forum to another moodleoverflow.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity       | name                      | intro                            | course  | anonymous | idnumber |
      | moodleoverflow | public moodleoverflow one | Test moodleoverflow description  | C1      | 0         | 1        |
      | moodleoverflow | public moodleoverflow two | Test moodleoverflow description  | C1      | 0         | 2        |
      | moodleoverflow | question anonymous        | Test moodleoverflow description  | C1      | 1         | 3        |
      | moodleoverflow | everything anonymous      | Test moodleoverflow description  | C1      | 2         | 4        |
    And I log in as "admin"

  Scenario: Move topic from public forum
    Given I am on "Course 1" course homepage
    And I add a new discussion to "public moodleoverflow one" moodleoverflow with:
      | Subject | Public Message |
      | Message | This is the public message  |
    And I follow "public moodleoverflow one"
    And I click on "Move this discussion to another moodleoverflow" "link"
    Then I should see "public moodleoverflow two"
    And I should see "question anonymous"
    And I should see "everything anonymous"
    And I should not see "Move discussion to public moodleoverflow one"
    When I click on "Move discussion to public moodleoverflow two" "link"
    And I am on "Course 1" course homepage
    And I follow "public moodleoverflow two"
    Then I should see "Public Message"

  Scenario: Move topic from question anonymous forum
    Given I am on "Course 1" course homepage
    And I add a new discussion to "question anonymous" moodleoverflow with:
      | Subject | Question Message |
      | Message | This is the question anonymous message |
    And I follow "question anonymous"
    And I click on "Move this discussion to another moodleoverflow" "link"
    And I should not see "Move discussion to public moodleoverflow one"
    And I should not see "Move discussion to public moodleoverflow two"
    And I should see "question anonymous"
    And I should see "everything anonymous"
    When I click on "Move discussion to everything anonymous" "link"
    And I am on "Course 1" course homepage
    And I follow "everything anonymous"
    Then I should see "Question Message"

  Scenario: Move topic from question anonymous forum
    Given I am on "Course 1" course homepage
    And I add a new discussion to "everything anonymous" moodleoverflow with:
      | Subject | Everything Message |
      | Message | This is the everything anonymous message |
    And I follow "everything anonymous"
    And I click on "Move this discussion to another moodleoverflow" "link"
    And I should not see "Move discussion to public moodleoverflow one"
    And I should not see "Move discussion to public moodleoverflow two"
    And I should not see "Move discussion to question anonymous"
    And I should not see "Move discussion to everything anonymous"
