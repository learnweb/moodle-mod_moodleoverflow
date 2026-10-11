<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The module moodleoverflow tests.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use stdClass;

defined('MOODLE_INTERNAL') || die();


global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * PHPUnit Tests for testing the ratings.php.
 *
 * @package mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ratings_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var stdClass a post from the teacher*/
    private $post;

    /** @var stdClass answer from user 1 */
    private $answer1;

    /** @var stdClass answer from user 1 */
    private $answer2;

    /** @var stdClass answer from user 1 */
    private $answer3;

    /** @var stdClass answer from user 2 */
    private $answer4;

    /** @var stdClass answer from user 2 */
    private $answer5;

    /** @var stdClass answer from user 2 */
    private $answer6;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->helper_course_set_up();
    }

    // Begin of test functions.

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: Every group of rating exists (helful and solved posts, only helpful/solved and none)
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_everygroup(): void {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Test with every group of rating.

        // Create a array of the posts, save the sorted post and compare them to the order that they should have.
        $posts = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->post, $this->answer1, $this->answer3, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Change the rating preference of the teacher and sort again.
        $this->set_ratingpreferences(1);
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: One group of rating does not exist
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_threegroups(): void {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Test without posts that are only marked as solved.
        $posts = [$this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        $this->set_ratingpreferences(1);
        $rightorder = [$this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Test without posts that are only marked as helpful.
        $posts = [$this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        $this->set_ratingpreferences(1);
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Test without posts that are marked as both helpful and solved.
        $posts = [$this->post, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->post, $this->answer3, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        $this->set_ratingpreferences(1);
        $rightorder = [$this->post, $this->answer2, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: two groups of rating do not exist
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_twogroups(): void {
        $this->set_ratingpreferences(0);

        // Test case 1: helpful and solved post, only solved posts.
        $this->process_groups('sh', 's');

        // Test case 2: helpful and solved post, only helpful posts.
        $this->process_groups('sh', 'h');

        // Test case 3: helpful and solved post, not-marked posts.
        $this->process_groups('sh', 'o');

        // Test case 4: only solved posts and only helpful posts with ratingpreferences = 0.
        $this->set_ratingpreferences(0);
        $rightorder = [$this->post, $this->answer6, $this->answer5, $this->answer4, $this->answer2, $this->answer1, $this->answer3];
        $this->process_groups('s', 'h', $rightorder);

        // Test case 5: only solved posts and only helpful posts with ratingpreferences = 1.
        $this->set_ratingpreferences(1);
        $this->process_groups('s', 'h');

        // Test case 6: only solved posts and not-marked posts.
        $this->create_twogroups('s', 'o');
        $posts = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = [$this->post, $this->answer2, $this->answer1, $this->answer3, $this->answer6, $this->answer5, $this->answer4];
        $this->assert_order($posts, $rightorder);

        // Test case 6: only helpful posts and not-marked posts.
        $this->process_groups('h', 'o');
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: Only one group of rating exists, so only:
     * - helpful and solved posts, or
     * - helpful, or
     * - solved, or
     * - not marked
     * Extended Test Case: If the votesdifference is the same, the post should be sorted by their time of creation/modification.
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_onegroup(): void {
        $this->set_ratingpreferences(0);

        // Test case 1: only solved and helpful posts.
        $this->create_onegroup('sh');
        $posts = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = [$this->post, $this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);

        // Test case 2: only solvedposts.
        $this->create_onegroup('s');
        $rightorder = [$this->post, $this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);

        // Test case 3: only helpful posts.
        $this->create_onegroup('h');
        $rightorder = [$this->post, $this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);

        // Test case 4: only not marked posts.
        $this->create_onegroup('o');
        $rightorder = [$this->post, $this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);
    }

    // Helper functions.

    /**
     * This function creates:
     * - a course with a moodleoverflow
     * - a teacher, who creates a discussion with a post
     * - 2 students, who answer to the post from the teacher
     */
    private function helper_course_set_up() {
        $this->setup_course();
        [, $this->post] = $this->generator->post_to_forum($this->create_moodleoverflow(), $this->teacher);
        $this->answer1 = $this->generator->reply_to_post($this->post, $this->student);
        $this->answer2 = $this->generator->reply_to_post($this->post, $this->student);
        $this->answer3 = $this->generator->reply_to_post($this->post, $this->student);
        $this->answer4 = $this->generator->reply_to_post($this->post, $this->otherstudent);
        $this->answer5 = $this->generator->reply_to_post($this->post, $this->otherstudent);
        $this->answer6 = $this->generator->reply_to_post($this->post, $this->otherstudent);
    }

    /**
     * Sorts the posts and checks that they are in the expected order.
     *
     * @param array $posts The posts to sort.
     * @param array $rightorder The posts in the order they should have.
     */
    private function assert_order(array $posts, array $rightorder): void {
        $sorted = ratings::sort_answers_by_ratings($posts);
        $this->assertEquals(array_column($rightorder, 'id'), array_column(array_values($sorted), 'id'));
    }

    /**
     * Returns the six answers.
     *
     * @return stdClass[]
     */
    private function answers(): array {
        return [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
    }

    /**
     * Sets the ratingpreferences to 1 or 0:
     * 1 = solved posts will be shown above helpful posts.
     * 0 = helpful posts will be shown above solved posts.
     * @param int  $preference  the rating preference
     */
    private function set_ratingpreferences($preference) {
        foreach (array_merge([$this->post], $this->answers()) as $post) {
            $post->ratingpreference = $preference;
        }
    }

    /**
     * Sets the the votesdifference equal.
     * The Right Order now (if the answers are in the same group) is:
     * answer1, answer2, answer3, answer4, answer5, answer6.
     * @return void
     */
    private function set_votesdifference() {
        foreach ($this->answers() as $answer) {
            $this->set_votes($answer, 1, 1);
        }
    }

    // Creation functions, that create different rating situations of the posts in a discussion.

    /**
     * creates a rating of every type by adding attributes to the post:
     * - post that is solved and helpful
     * . post that is only helpful
     * - post that is only solved
     * - post that is not marked
     */
    private function create_everygroup() {
        // Answer => group, upvotes, downvotes.
        $ratings = [['sh', 0, 0], ['s', 0, 0], ['h', 0, 0], ['o', 1, 0], ['o', 0, 1], ['o', 0, 0]];
        foreach ($this->answers() as $i => $answer) {
            [$group, $upvotes, $downvotes] = $ratings[$i];
            $this->set_group($group, $answer);
            $this->set_votes($answer, $upvotes, $downvotes);
        }
    }

    /**
     * Creates a rating of one group for every post in the discussion
     * Creates up and downvotes
     * @param string $group
     * A Group can be:
     * - both as solution and helpful marked posts (sh)
     * - only solution posts (s)
     * - only helpful (h)
     * - no mark (o)
     */
    private function create_onegroup($group) {
        // Answer => upvotes, downvotes. Votesdifference: 0, -1, 1, 5, -2, 2.
        $votes = [[4, 4], [1, 2], [3, 2], [5, 0], [0, 2], [4, 2]];
        foreach ($this->answers() as $i => $answer) {
            $this->set_group($group, $answer);
            $this->set_votes($answer, ...$votes[$i]);
        }
        // Rightorder = answer4 , answer6, answer3, answer1, answer2, answer5.
    }

    /**
     * Creates ratings of the posts of the assigned groups in the discussion.
     * Creates up and downvotes
     * @param string $group1 Group of the first three answers.
     * @param string $group2 Group of the last three answers.
     * A Group can be:
     * - both as solution and helpful marked posts (sh)
     * - only solution posts (s)
     * - only helpful (h)
     * - no mark (o)
     */
    private function create_twogroups($group1, $group2) {
        // Answer => upvotes, downvotes. Votesdifference: -1, 3, -2, 0, 1, 2.
        $votes = [[3, 4], [4, 1], [0, 2], [5, 5], [6, 5], [4, 2]];
        foreach ($this->answers() as $i => $answer) {
            $this->set_group($i < 3 ? $group1 : $group2, $answer);
            $this->set_votes($answer, ...$votes[$i]);
        }

        // The Rightorder depends now on the group parameter.
        // Rightorder (sh,s) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (sh,h) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (sh,o) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (s,h) = answer6, answer5, answer4, answer2, answer1, answer3. with ratingpreference = 0.
        // Rightorder (s,h) = answer2, answer1, answer3, answer6, answer5, answer4. with ratingpreference = 1
        // Rightorder (s,o) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (h,o) = answer2, answer1, answer3, answer6, answer5, answer4.
    }

    /**
     * Executing the sort function and comparing the sorted post to the expected order.
     * @param string $group1
     * @param string $group2
     * @param array $orderposts
     * @return void
     */
    private function process_groups(string $group1, string $group2, array $orderposts = []) {
        $this->create_twogroups($group1, $group2);
        $posts = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = $orderposts ?: [$this->post, $this->answer2, $this->answer1, $this->answer3, $this->answer6, $this->answer5,
            $this->answer4, ];
        $this->assert_order($posts, $rightorder);
    }

    /**
     * Sets a post to a group of mark.
     * @param string $group Group can be: solved+helpful (sh), solved (s), helpful (h), not marked (o)
     * @param object $answer
     */
    private function set_group(string $group, object $answer) {
        $answer->markedhelpful = (int) in_array($group, ['sh', 'h']);
        $answer->markedsolution = (int) in_array($group, ['sh', 's']);
    }

    /**
     * Sets the votes and votesdifference of a post.
     * @param mixed $answer     The post
     * @param int $upvotes
     * @param int $downvotes
     * @return void
     */
    private function set_votes($answer, $upvotes, $downvotes) {
        $answer->upvotes = $upvotes;
        $answer->downvotes = $downvotes;
        $answer->votesdifference = $answer->upvotes - $answer->downvotes;
    }
}
