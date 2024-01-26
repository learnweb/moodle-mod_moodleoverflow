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
class ratings_test extends \advanced_testcase {

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

    /** @var array The whole Discussion */
    private $discussion;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->helper_course_set_up();
    }

    /**
     * Test tearDown.
     */
    public function tearDown(): void {
        // Clear all caches.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();
    }

    // Begin of test functions.

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: Every group of rating exists (helpful and solved posts, only helpful/solved and posts with no mark)
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_everygroup() {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Create a array of the posts, save the sorted post and compare them to the order that they should have.
        $rightorder = [$this->post, $this->answer1, $this->answer3, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->process_every_and_three_groups($this->discussion, $rightorder, 0);

        // Change the rating preference of the teacher and sort again.
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->process_every_and_three_groups($this->discussion, $rightorder, 1);
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: One group of rating does not exist
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_threegroups() {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Test Case 1: Without posts that are only marked as solved.
        $posts = [$this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = [$this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->process_every_and_three_groups($posts, $rightorder, 0);
        $this->process_every_and_three_groups($posts, $rightorder, 1);

        // Test without posts that are only marked as helpful.
        $posts = [$this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = [$this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->process_every_and_three_groups($posts, $rightorder, 0);
        $this->process_every_and_three_groups($posts, $rightorder, 1);

        // Test without posts that are marked as both helpful and solved.
        $posts = [$this->post, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = [$this->post, $this->answer3, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->process_every_and_three_groups($posts, $rightorder, 0);

        $rightorder = [$this->post, $this->answer2, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->process_every_and_three_groups($posts, $rightorder, 1);
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: Only two group of posts exists.
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_twogroups() {
        $this->set_ratingpreferences(0);

        // Test case 1: helpful and solved post, only solved posts.
        $this->process_two_groups('sh', 's');

        // Test case 2: helpful and solved post, only helpful posts.
        $this->process_two_groups('sh', 'h');

        // Test case 3: helpful and solved post, not-marked posts.
        $this->process_two_groups('sh', 'o');

        // Test case 4: only solved posts and only helpful posts with ratingpreferences = 0.
        $this->set_ratingpreferences(0);
        $rightorder = [$this->post, $this->answer6, $this->answer5, $this->answer4, $this->answer2, $this->answer1, $this->answer3];
        $this->process_two_groups('s', 'h', $rightorder);

        // Test case 5: only solved posts and only helpful posts with ratingpreferences = 1.
        $this->set_ratingpreferences(1);
        $this->process_two_groups('s', 'h');

        // Test case 6: only solved posts and not-marked posts.
        $rightorder = [$this->post, $this->answer2, $this->answer1, $this->answer3, $this->answer6, $this->answer5, $this->answer4];
        $this->process_two_groups('s', 'h', $rightorder);

        // Test case 7: only helpful posts and not-marked posts.
        $this->process_two_groups('h', 'o');
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: Only one group of rating exists, so only:
     * - helpful and solved posts, or
     * - helpful, or
     * - solved, or
     * - not marked
     * Test first if they are sorted correctly after the votes.
     * Test second if they are sorted correctly after time if the votesdifference is the same on all posts.
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_onegroup() {
        $this->set_ratingpreferences(0);

        // Define the right order of posts that will be used in this function.
        $order1 = [$this->post, $this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];

        // Test case 1: only solved and helpful posts.
        $this->process_one_group($this->discussion, $order1, $this->discussion, 'sh');

        // Test case 2: only solvedposts.
        $this->process_one_group($this->discussion, $order1, $this->discussion, 's');

        // Test case 3: only helpful posts.
        $this->process_one_group($this->discussion, $order1, $this->discussion, 'h');

        // Test case 4: only not marked posts.
        $this->process_one_group($this->discussion, $order1, $this->discussion, 'o');
    }

    // End of Test Functions.

    // Helper functions.

    /**
     * This function creates:
     * - a course with a moodleoverflow
     * - a teacher, who creates a discussion with a post
     * - 2 users, which answer to the post from the teacher
     */
    private function helper_course_set_up() {
        global $DB;
        // Create a new course with a moodleoverflow forum.
        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);

        // Create a teacher.
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Tamaro', 'lastname' => 'Walter']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'student');

        // Create 2 users.
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Ava', 'lastname' => 'Davis']);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Ethan', 'lastname' => 'Brown']);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        // Create a discussion, a parent post and six answers.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $discussion = $generator->post_to_forum($moodleoverflow, $teacher);
        $this->discussion[] = $this->post = $DB->get_record('moodleoverflow_posts', ['id' => $discussion[0]->firstpost], '*');
        $this->discussion[] = $this->answer1 = $generator->reply_to_post($discussion[1], $user1);
        $this->discussion[] = $this->answer2 = $generator->reply_to_post($discussion[1], $user1);
        $this->discussion[] = $this->answer3 = $generator->reply_to_post($discussion[1], $user1);
        $this->discussion[] = $this->answer4 = $generator->reply_to_post($discussion[1], $user2);
        $this->discussion[] = $this->answer5 = $generator->reply_to_post($discussion[1], $user2);
        $this->discussion[] = $this->answer6 = $generator->reply_to_post($discussion[1], $user2);
    }


    /**
     * This function compares 2 arrays with posts and checks if the order is the same.
     *
     * @param array $sortedposts      - The sorted posts
     * @param array $rightorder       - The posts with the order they should have
     * The function returns 1 if $sortedposts matches $posts, else 0
     */
    private function postsorderequal($sortedposts, $rightorder) {
        if (count($sortedposts) != count($rightorder)) {
            return 0;
        }
        for ($i = 0; $i < count($sortedposts); $i++) {
            // Get the current elements.
            $sortedpost = current($sortedposts);
            $post = current($rightorder);
            if ($sortedpost->id == $post->id) {
                // Go to the next elements.
                next($sortedposts);
                next($rightorder);
            } else {
                return 0;
            }
        }
        return 1;
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
        $this->set_group('sh', $this->answer1);
        $this->set_votes($this->answer1, 0,0); // Votesdifference = 0.

        $this->set_group('s', $this->answer2);
        $this->set_votes($this->answer2, 0,0); // Votesdifference = 0.

        $this->set_group('h', $this->answer3);
        $this->set_votes($this->answer3, 0,0); // Votesdifference = 0.

        $this->set_group('o', $this->answer4);
        $this->set_votes($this->answer4, 1,0); // Votesdifference = 1.

        $this->set_group('o', $this->answer5);
        $this->set_votes($this->answer5, 0,1); // Votesdifference = -1.

        $this->set_group('o', $this->answer6);
        $this->set_votes($this->answer6, 0,0); // Votesdifference = 0.
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
        $answers = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        foreach ($answers as $answer) {
            $this->set_group($group, $answer);
        }

        // Votes for the answerposts, Rightorder = answer4 , answer6, answer3, answer1, answer2, answer5.
        $this->set_votes($this->answer1, 4,4); // Votesdifference = 0.
        $this->set_votes($this->answer2, 1,2); // Votesdifference = -1.
        $this->set_votes($this->answer3, 3,2); // Votesdifference = 1.
        $this->set_votes($this->answer4, 5,0); // Votesdifference = 5.
        $this->set_votes($this->answer5, 0,2); // Votesdifference = -2.
        $this->set_votes($this->answer6, 4,2); // Votesdifference = 2.
    }

    /**
     * Creates ratings of the posts of the assigned groups in the discussion.
     * Creates up and downvotes.
     * @param string $group1
     * @param string $group2
     * A Group can be:
     * - both as solution and helpful marked posts (sh)
     * - only solution posts (s)
     * - only helpful (h)
     * - no mark (o)
     */
    private function create_twogroups($group1, $group2) {
        // Set the first 3 answers to the first group of rating.
        foreach([$this->answer1, $this->answer2, $this->answer3] as $answer) {
            $this->set_group($group1, $answer);
        }
        // Set the last 3 answers to the second group of rating.
        foreach([$this->answer4, $this->answer5, $this->answer6] as $answer) {
            $this->set_group($group2, $answer);
        }

        // Now set the up and downvotes for every answer.
        $this->set_votes($this->answer1, 3, 4); // Votesdifference = -1.
        $this->set_votes($this->answer2, 4, 1); // Votesdifference = -3.
        $this->set_votes($this->answer3, 0, 2); // Votesdifference = -2.
        $this->set_votes($this->answer4, 5, 5); // Votesdifference = 0.
        $this->set_votes($this->answer5, 6, 5); // Votesdifference = 1.
        $this->set_votes($this->answer6, 4, 2); // Votesdifference = 2.

        // The Rightorder depends now on the group parameter.
        // Rightorder (sh,s),(sh,h),(sh,o),(s,h),(s,o),(h,o)  = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (s,h) = answer6, answer5, answer4, answer2, answer1, answer3. with ratingpreference = 0.
        // Rightorder (s,h) = answer2, answer1, answer3, answer6, answer5, answer4. with ratingpreference = 1.
    }

    //  Little function to improve readability.

    /**
     * Function to execute the sort function and comparing the sorted to the expected order
     * Helper function for test function test_answersorting_everygroup and test_answersorting_threegroups.
     * @param $posts
     * @param $rightorder
     * @param $ratingpreference
     * @return void
     */
    private function process_every_and_three_groups($posts, $rightorder, $ratingpreference) {
        $this->set_ratingpreferences($ratingpreference);
        $result = $this->postsorderequal(ratings::moodleoverflow_sort_answers_by_ratings($posts), $rightorder);
        $this->assertEquals(1, $result);
    }

    /**
     * Function to execute the sort function and comparing the sorted to the expected order
     * Helper function for test function test_answersorting_twogroups.
     * @param String $group1
     * @param string $group2
     * @param array|null $orderposts
     * @return void
     */
    private function process_two_groups(String $group1, string $group2, array $orderposts = null) {
        $this->create_twogroups($group1, $group2);
        $rightorder = [$this->post, $this->answer2, $this->answer1, $this->answer3, $this->answer6, $this->answer5, $this->answer4];
        if ($orderposts) {
            $rightorder = $orderposts;
        }
        $result = $this->postsorderequal(ratings::moodleoverflow_sort_answers_by_ratings($this->discussion), $rightorder);
        $this->assertEquals(1, $result);
    }

    /**
     * Function to execute the sort function and comparing the sorted to the expected order
     * Helper function for test function test_answersorting_onegroup
     * @param array $posts               Posts that will be sorted.
     * @param array $rightorder1         First Expected order.
     * @param array $rightorder2         Second Expected order.
     * @return void
     */
    private function process_one_group($posts, $rightorder1, $rightorder2, $group) {
        $this->create_onegroup($group);
        $result = $this->postsorderequal(ratings::moodleoverflow_sort_answers_by_ratings($posts), $rightorder1);
        $this->assertEquals(1, $result);

        $this->set_votesdifference_equal();
        $result = $this->postsorderequal(ratings::moodleoverflow_sort_answers_by_ratings($posts), $rightorder2);
        $this->assertEquals(1, $result);
    }

    /**
     * Sets a post to a group of mark.
     * @param String $group     Group can be: solved+helpful, solved, helpful, not marked
     * @param object $answer    Object that will be assign to a group.
     * @return void
     */
    private function set_group(String $group, object $answer) {
        switch($group) {
            case 'sh':
                $answer->markedhelpful = 1;
                $answer->markedsolution = 1;
                break;
            case 's':
                $answer->markedhelpful = 0;
                $answer->markedsolution = 1;
                break;
            case 'h':
                $answer->markedhelpful = 1;
                $answer->markedsolution = 0;
                break;
            case 'o':
                $answer->markedhelpful = 0;
                $answer->markedsolution = 0;
                break;
        }
    }

    /**
     * Sets the votes and votes difference of a post.
     * @param mixed $answer     The post that will be changed
     * @param int $upvotes      Number of upvotes
     * @param int $downvotes    Number of downvotes
     * @return void
     */
    private function set_votes($answer, $upvotes, $downvotes) {
        $answer->upvotes = $upvotes;
        $answer->downvotes = $downvotes;
        $answer->votesdifference = $answer->upvotes - $answer->downvotes;
    }

    /**
     * Sets the votes and votesdifference equal.
     * The Right Order now (if the answers are in the same group) is:  answer1, answer2, answer3, answer4, answer5, answer6.
     * @return void
     */
    private function set_votesdifference_equal() {
        foreach ([$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6] as $answer) {
            $answer->upvotes = 1;
            $answer->downvotes = 1;
            $answer->votesdifference = $answer->upvotes - $answer->downvotes;;
        }
    }

    /**
     * Sets the ratingpreferences to 1 or 0:
     * 1 = solved posts will be shown above helpful posts.
     * 0 = helpful posts will be shown above solved posts.
     * @param int  $preference  the rating preference
     */
    private function set_ratingpreferences($preference) {
        if ($preference == 0 || $preference == 1) {
            $this->post->ratingpreference = $preference;
            foreach([$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6] as $answer) {
                $answer->ratingpreference = $preference;
            }
        }
    }
}
