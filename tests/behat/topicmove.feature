@mod @mod_moodleoverflow @javascript
Feature: Teachers can move a discussion in one moodleoverflow forum to another moodleoverflow.

  Background:
    Given I prepare a moodleoverflow feature background with users:
      | username | firstname | lastname | email                | idnumber | role      |
      | student1 | Student   | 1        | student1@example.com | 10       | student   |
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
    And I click in moodleoverflow on "link" type:
      | public moodleoverflow one | Move this discussion to another moodleoverflow |
    Then I should "" see the elements:
      | public moodleoverflow two | question anonymous | everything anonymous |
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
    And I click in moodleoverflow on "link" type:
      | question anonymous | Move this discussion to another moodleoverflow|
    And I should "not" see the elements:
      | Move discussion to public moodleoverflow one | Move discussion to public moodleoverflow two |
    And I should "" see the elements:
      | question anonymous | everything anonymous |
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
    And I should "not" see the elements:
     | Move discussion to public moodleoverflow one | Move discussion to public moodleoverflow two | Move discussion to question anonymous | Move discussion to everything anonymous |
