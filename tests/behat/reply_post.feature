@mod @mod_moodleoverflow @javascript
Feature: Replying to posts in a moodleoverflow

  Scenario: While replying to a post I need to see the original post I'm answering to
    Given I add a moodleoverflow discussion with posts from different users
    When I navigate as "teacher1" to "C1" "Moodleoverflow 1" "Discussion 1"
    And I click on "Answer" "link"
    Then I should see "You are adressing the post from Tamaro Walter:"
    When I click on "#moodleoverflow_showpost" "css_element"
    Then I should see "Message from teacher"
    When I click on "#moodleoverflow_showpost" "css_element"
    Then I should not see "Message from teacher"