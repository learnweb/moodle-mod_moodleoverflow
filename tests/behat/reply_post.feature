@mod @mod_moodleoverflow @javascript
Feature: Replying to posts in a moodleoverflow

  Scenario: Answer and comment in the small form below the post
    Given I add a moodleoverflow discussion with posts from different users
    When I am on the "Discussion 1" "mod_moodleoverflow > Discussion" page logged in as "student1"
    And I click on "Comment" "link"
    And I set the field "Message" to "A comment"
    And I press "Post to forum"
    Then I should see "Your post was successfully added."
    And I should see "A comment"
    When I click on "Answer" "link"
    And I set the field "Message" to "A second answer"
    And I press "Post to forum"
    Then I should see "A second answer"

  Scenario: Advanced opens the full form with the text and the post that is replied to
    Given I add a moodleoverflow discussion with posts from different users
    When I am on the "Discussion 1" "mod_moodleoverflow > Discussion" page logged in as "teacher1"
    And I click on "Comment" "link"
    And I set the field "Message" to "A longer comment"
    And I press "Advanced"
    Then I should see "You are adressing the post from John Smith:"
    And the field "Message" matches value "A longer comment"
    When I click on "#moodleoverflow_showpost" "css_element"
    Then I should see "Answer from student"
    When I click on "#moodleoverflow_showpost" "css_element"
    Then I should not see "Answer from student"
    And I should not see "Subject"
