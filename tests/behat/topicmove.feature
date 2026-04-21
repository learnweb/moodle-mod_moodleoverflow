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
    Given User "admin" adds to "public moodleoverflow one" a discussion with topic "Public Message" and message "This is the public message" automatically
    And I am on "Course 1" course homepage
    And I click on "public moodleoverflow one" "link"
    And I click on "[data-action='moodleoverflow/movetopic-select']" "css_element"
    Then I should "" see the elements:
      | public moodleoverflow two | question anonymous | everything anonymous |
    And I should not see "Move discussion to public moodleoverflow one"
    When I click on "[data-destinationname='public moodleoverflow two']" "css_element"
    And I am on "Course 1" course homepage
    And I follow "public moodleoverflow two"
    Then I should see "Public Message"

  Scenario: Move topic from question anonymous forum
    Given User "admin" adds to "question anonymous" a discussion with topic "Question Message" and message "This is the question anonymous message" automatically
    And I am on "Course 1" course homepage
    And I click on "question anonymous" "link"
    And I click on "[data-action='moodleoverflow/movetopic-select']" "css_element"
    And I should "not" see the elements:
      | Move discussion to public moodleoverflow one | Move discussion to public moodleoverflow two |
    And I should "" see the elements:
      | question anonymous | everything anonymous |
    When I click on "[data-destinationname='everything anonymous']" "css_element"
    And I am on "Course 1" course homepage
    And I follow "everything anonymous"
    Then I should see "Question Message"

  Scenario: Move topic from question anonymous forum
    Given User "admin" adds to "everything anonymous" a discussion with topic "Everything Message" and message "This is the everything anonymous message" automatically
    Given I am on "Course 1" course homepage
    And I follow "everything anonymous"
    And "[data-action='moodleoverflow/movetopic-select']" "css_element" should not exist