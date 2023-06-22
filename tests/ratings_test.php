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
use mod_moodleoverflow\ratings;

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
    /** @var \stdClass test course */
    private $course;

    /** @var \stdClass coursemodule */
    private $coursemodule;

    /** @var \stdClass test moodleoverflow */
    private $moodleoverflow;

    /** @var \stdClass test teacher */
    private $teacher;

    /** @var \stdClass test user */
    private $user1;

    /** @var \stdClass another test user */
    private $user2;

    /** @var \stdClass a discussion */
    private $discussion;

    /** @var \stdClass a post from the teacher*/
    private $post;

    /** @var \stdClass answer from user 1 */
    private $answer1;

    /** @var \stdClass answer from user 1 */
    private $answer2;

    /** @var \stdClass answer from user 1 */
    private $answer3;

    /** @var \stdClass answer from user 2 */
    private $answer4;

    /** @var \stdClass answer from user 2 */
    private $answer5;

    /** @var \stdClass answer from user 2 */
    private $answer6;

    /** @var \mod_moodleoverflow_generator $generator */
    private $generator;

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
        \mod_moodleoverflow\subscriptions::reset_moodleoverflow_cache();
        \mod_moodleoverflow\subscriptions::reset_discussion_cache();
    }

    // Begin of test functions.

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: Every group of rating exists (helful and solved posts, only helpful/solved and none)
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_everygroup() {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Test with every group of rating.

        // Create a array of the posts, save the sorted post and compare them to the order that they should have.
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $this->set_ratingpreferences(0);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer1, $this->answer3, $this->answer2,
                                 $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        // Change the rating preference of the teacher and sort again.
        $this->set_ratingpreferences(1);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer1, $this->answer2, $this->answer3,
                                 $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: One group of rating does not exist
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_threegroups() {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Test without posts that are only marked as solved.
        $posts = array($this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $this->set_ratingpreferences(0);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        $this->set_ratingpreferences(1);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer1, $this->answer3, $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        // Test without posts that are only marked as helpful.
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer5, $this->answer6);
        $this->set_ratingpreferences(0);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        $this->set_ratingpreferences(1);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer1, $this->answer2, $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        // Test without posts that are marked as both helpful and solved.
        $posts = array($this->post, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $this->set_ratingpreferences(0);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer3, $this->answer2, $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        $this->set_ratingpreferences(1);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer2, $this->answer3, $this->answer4, $this->answer6, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: two groups of rating do not exist
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_twogroups() {
        $this->set_ratingpreferences(0);

        // Test case 1: helpful and solved post, only solved posts.
        $group1 = 'sh';
        $group2 = 's';
        $this->process_groups($group1, $group2);

        // Test case 2: helpful and solved post, only helpful posts.
        $group2 = 'h';
        $this->process_groups($group1, $group2);

        // Test case 3: helpful and solved post, not-marked posts.
        $group2 = 'o';
        $this->process_groups($group1, $group2);

        // Test case 4: only solved posts and only helpful posts with ratingpreferences = 0.
        $group1 = 's';
        $group2 = 'h';
        $this->set_ratingpreferences(0);
        $rightorderposts = array($this->post, $this->answer6, $this->answer5, $this->answer4,
            $this->answer2, $this->answer1, $this->answer3);
        $this->process_groups($group1, $group2, $rightorderposts);

        // Test case 5: only solved posts and only helpful posts with ratingpreferences = 1.
        $this->set_ratingpreferences(1);
        $this->process_groups($group1, $group2);

        // Test case 6: only solved posts and not-marked posts.
        $group2 = 'o';
        $this->create_twogroups($group1, $group2);
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer2, $this->answer1, $this->answer3,
                                 $this->answer6, $this->answer5, $this->answer4);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        // Test case 6: only helpful posts and not-marked posts.
        $group1 = 'h';
        $this->process_groups($group1, $group2);
    }

    /**
     * Tests function ratings::moodleoverflow_sort_answer_by_ratings
     * Test case: Only one group of rating exists, so only:
     * - helpful and solved posts, or
     * - helpful, or
     * - solved, or
     * - not marked
     * @covers \ratings::moodleoverflow_sort_answer_by_ratings()
     */
    public function test_answersorting_onegroup() {
        $this->set_ratingpreferences(0);

        // Test case 1: only solved and helpful posts.
        $group = 'sh';
        $this->create_onegroup($group);
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer4, $this->answer6, $this->answer3,
                                 $this->answer1, $this->answer2, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        // Test case 1: only solvedposts.
        $group = 's';
        $this->create_onegroup($group);
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer4, $this->answer6, $this->answer3,
                                 $this->answer1, $this->answer2, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        // Test case 1: only helpful posts.
        $group = 'h';
        $this->create_onegroup($group);
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer4, $this->answer6, $this->answer3,
                                 $this->answer1, $this->answer2, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);

        // Test case 1: only not marked posts.
        $group = 'o';
        $this->create_onegroup($group);
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer4, $this->answer6, $this->answer3,
                                 $this->answer1, $this->answer2, $this->answer5);
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);
    }

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
        $this->course = $this->getDataGenerator()->create_course();
        $location = array('course' => $this->course->id);
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->moodleoverflow->id);

        // Create a teacher.
        $this->teacher = $this->getDataGenerator()->create_user(array('firstname' => 'Tamaro', 'lastname' => 'Walter'));
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'student');

        // Create 2 users.
        $this->user1 = $this->getDataGenerator()->create_user(array('firstname' => 'Ava', 'lastname' => 'Davis'));
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course->id, 'student');
        $this->user2 = $this->getDataGenerator()->create_user(array('firstname' => 'Ethan', 'lastname' => 'Brown'));
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course->id, 'student');

        // Create a discussion, a parent post and six answers.
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->discussion = $this->generator->post_to_forum($this->moodleoverflow, $this->teacher);
        $this->post = $DB->get_record('moodleoverflow_posts', array('id' => $this->discussion[0]->firstpost), '*');
        $this->answer1 = $this->generator->reply_to_post($this->discussion[1], $this->user1, true);
        $this->answer2 = $this->generator->reply_to_post($this->discussion[1], $this->user1, true);
        $this->answer3 = $this->generator->reply_to_post($this->discussion[1], $this->user1, true);
        $this->answer4 = $this->generator->reply_to_post($this->discussion[1], $this->user2, true);
        $this->answer5 = $this->generator->reply_to_post($this->discussion[1], $this->user2, true);
        $this->answer6 = $this->generator->reply_to_post($this->discussion[1], $this->user2, true);
    }


    /**
     * This function compares 2 arrays with posts and checks if the order is the same.
     *
     * @param array $sortedposts - The sorted posts
     * @param array $rightorderposts       - The posts with the order they should have
     * The function returns 1 if $sortedposts matches $posts, else 0
     */
    private function postsorderequal($sortedposts, $rightorderposts) {
        if (count($sortedposts) != count($rightorderposts)) {
            return 0;
        }
        for ($i = 0; $i < count($sortedposts); $i++) {
            // Get the current elements.
            $sortedpost = current($sortedposts);
            $post = current($rightorderposts);
            if ($sortedpost->id == $post->id) {
                // Go to the next elements.
                next($sortedposts);
                next($rightorderposts);
            } else {
                return 0;
            }
        }
        return 1;
    }

    /**
     * sets the ratingpreferences to 1 or 0:
     * 1 = solved posts will be shown above helpful posts.
     * 0 = helpful posts will be shown above solved posts.
     * @param int  $preference  the rating preference
     */
    private function set_ratingpreferences($preference) {
        if ($preference == 0 || $preference == 1) {
            $this->post->ratingpreference = $preference;
            $this->answer1->ratingpreference = $preference;
            $this->answer2->ratingpreference = $preference;
            $this->answer3->ratingpreference = $preference;
            $this->answer4->ratingpreference = $preference;
            $this->answer5->ratingpreference = $preference;
            $this->answer6->ratingpreference = $preference;
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
        // Answer1.
        $this->answer1->upvotes = 0;
        $this->answer1->downvotes = 0;
        $this->answer1->votesdifference = $this->answer1->upvotes - $this->answer1->downvotes;
        $this->answer1->markedhelpful = 1;
        $this->answer1->markedsolution = 1;

        // Answer2.
        $this->answer2->upvotes = 0;
        $this->answer2->downvotes = 0;
        $this->answer2->votesdifference = $this->answer2->upvotes - $this->answer2->downvotes;
        $this->answer2->markedhelpful = 0;
        $this->answer2->markedsolution = 1;

        // Answer3.
        $this->answer3->upvotes = 0;
        $this->answer3->downvotes = 0;
        $this->answer3->votesdifference = $this->answer3->upvotes - $this->answer3->downvotes;
        $this->answer3->markedhelpful = 1;
        $this->answer3->markedsolution = 0;

        // Answer4.
        $this->answer4->upvotes = 1;
        $this->answer4->downvotes = 0;
        $this->answer4->votesdifference = $this->answer4->upvotes - $this->answer4->downvotes;
        $this->answer4->markedhelpful = 0;
        $this->answer4->markedsolution = 0;

        // Answer5.
        $this->answer5->upvotes = 0;
        $this->answer5->downvotes = 1;
        $this->answer5->votesdifference = $this->answer5->upvotes - $this->answer5->downvotes;
        $this->answer5->markedhelpful = 0;
        $this->answer5->markedsolution = 0;

        // Answer6.
        $this->answer6->upvotes = 0;
        $this->answer6->downvotes = 0;
        $this->answer6->votesdifference = $this->answer6->upvotes - $this->answer6->downvotes;
        $this->answer6->markedhelpful = 0;
        $this->answer6->markedsolution = 0;
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
        $answers = array($this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        foreach ($answers as $answer) {
            switch ($group) {
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

        // Votes for the answerposts
        // Answer1.
        $this->answer1->upvotes = 4;
        $this->answer1->downvotes = 4;
        $this->answer1->votesdifference = $this->answer1->upvotes - $this->answer1->downvotes; // Vd = 0.

        // Answer2.
        $this->answer2->upvotes = 1;
        $this->answer2->downvotes = 2;
        $this->answer2->votesdifference = $this->answer2->upvotes - $this->answer2->downvotes; // Vd = -1.

        // Answer3.
        $this->answer3->upvotes = 3;
        $this->answer3->downvotes = 2;
        $this->answer3->votesdifference = $this->answer3->upvotes - $this->answer3->downvotes; // Vd = 1.

        // Answer4.
        $this->answer4->upvotes = 5;
        $this->answer4->downvotes = 0;
        $this->answer4->votesdifference = $this->answer4->upvotes - $this->answer4->downvotes; // Vd = 5.

        // Answer5.
        $this->answer5->upvotes = 0;
        $this->answer5->downvotes = 2;
        $this->answer5->votesdifference = $this->answer5->upvotes - $this->answer5->downvotes; // Vd = -2.

        // Answer6.
        $this->answer6->upvotes = 4;
        $this->answer6->downvotes = 2;
        $this->answer6->votesdifference = $this->answer6->upvotes - $this->answer6->downvotes; // Vd = 2.

        // Rightorder = answer4 , answer6, answer3, answer1, answer2, answer5.
    }

    /**
     * Creates ratings of the posts of the assigned groups in the discussion.
     * Creates up and downvotes
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
        switch ($group1) {
            case 'sh':
                $this->answer1->markedhelpful = 1;
                $this->answer1->markedsolution = 1;
                $this->answer2->markedhelpful = 1;
                $this->answer2->markedsolution = 1;
                $this->answer3->markedhelpful = 1;
                $this->answer3->markedsolution = 1;
                break;
            case 's':
                $this->answer1->markedhelpful = 0;
                $this->answer1->markedsolution = 1;
                $this->answer2->markedhelpful = 0;
                $this->answer2->markedsolution = 1;
                $this->answer3->markedhelpful = 0;
                $this->answer3->markedsolution = 1;
                break;
            case 'h':
                $this->answer1->markedhelpful = 1;
                $this->answer1->markedsolution = 0;
                $this->answer2->markedhelpful = 1;
                $this->answer2->markedsolution = 0;
                $this->answer3->markedhelpful = 1;
                $this->answer3->markedsolution = 0;
                break;
            case 'o':
                $this->answer1->markedhelpful = 0;
                $this->answer1->markedsolution = 0;
                $this->answer2->markedhelpful = 0;
                $this->answer2->markedsolution = 0;
                $this->answer3->markedhelpful = 0;
                $this->answer3->markedsolution = 0;
                break;
        }

        switch ($group2) {
            case 'sh':
                $this->answer4->markedhelpful = 1;
                $this->answer4->markedsolution = 1;
                $this->answer5->markedhelpful = 1;
                $this->answer5->markedsolution = 1;
                $this->answer6->markedhelpful = 1;
                $this->answer6->markedsolution = 1;
                break;
            case 's':
                $this->answer4->markedhelpful = 0;
                $this->answer4->markedsolution = 1;
                $this->answer5->markedhelpful = 0;
                $this->answer5->markedsolution = 1;
                $this->answer6->markedhelpful = 0;
                $this->answer6->markedsolution = 1;
                break;
            case 'h':
                $this->answer4->markedhelpful = 1;
                $this->answer4->markedsolution = 0;
                $this->answer5->markedhelpful = 1;
                $this->answer5->markedsolution = 0;
                $this->answer6->markedhelpful = 1;
                $this->answer6->markedsolution = 0;
                break;
            case 'o':
                $this->answer4->markedhelpful = 0;
                $this->answer4->markedsolution = 0;
                $this->answer5->markedhelpful = 0;
                $this->answer5->markedsolution = 0;
                $this->answer6->markedhelpful = 0;
                $this->answer6->markedsolution = 0;
                break;
        }

        // Now set the up and downvotes for every answer.
        // Answer1.
        $this->answer1->upvotes = 3;
        $this->answer1->downvotes = 4;
        $this->answer1->votesdifference = $this->answer1->upvotes - $this->answer1->downvotes; // Vd = -1.

        // Answer2.
        $this->answer2->upvotes = 4;
        $this->answer2->downvotes = 1;
        $this->answer2->votesdifference = $this->answer2->upvotes - $this->answer2->downvotes; // Vd = 3.

        // Answer3.
        $this->answer3->upvotes = 0;
        $this->answer3->downvotes = 2;
        $this->answer3->votesdifference = $this->answer3->upvotes - $this->answer3->downvotes; // Vd = -2.

        // Answer4.
        $this->answer4->upvotes = 5;
        $this->answer4->downvotes = 5;
        $this->answer4->votesdifference = $this->answer4->upvotes - $this->answer4->downvotes; // Vd = 0.

        // Answer5.
        $this->answer5->upvotes = 6;
        $this->answer5->downvotes = 5;
        $this->answer5->votesdifference = $this->answer5->upvotes - $this->answer5->downvotes; // Vd = 1.

        // Answer6.
        $this->answer6->upvotes = 4;
        $this->answer6->downvotes = 2;
        $this->answer6->votesdifference = $this->answer6->upvotes - $this->answer6->downvotes; // Vd = 2.

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
     * @param String $group1
     * @param string $group2
     * @param array|null $orderposts
     * @return void
     */
    private function process_groups(String $group1, string $group2, array $orderposts = null) {
        $this->create_twogroups($group1, $group2);
        $posts = array($this->post, $this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6);
        $sortedposts = ratings::moodleoverflow_sort_answers_by_ratings($posts);
        $rightorderposts = array($this->post, $this->answer2, $this->answer1, $this->answer3,
            $this->answer6, $this->answer5, $this->answer4);
        if ($orderposts) {
            $rightorderposts = $orderposts;
        }
        $result = $this->postsorderequal($sortedposts, $rightorderposts);
        $this->assertEquals(1, $result);
    }
}
