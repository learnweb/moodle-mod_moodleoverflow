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
use mod_moodleoverflow\tables\userstats_table;

defined('MOODLE_INTERNAL') || die();


global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');

/**
 * PHPUnit Tests for testing userstats.
 *
 * @package mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \userstats_table
 */
final class userstats_test extends \advanced_testcase {
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
    private $discussion1;

    /** @var \stdClass another faked discussion */
    private $discussion2;

    /** @var \stdClass a post */
    private $post1;

    /** @var \stdClass another post */
    private $post2;

    /** @var \stdClass answer to a post */
    private $answer1;

    /** @var \stdClass another answer to a post */
    private $answer2;

    /** @var \mod_moodleoverflow_generator $generator */
    private $generator;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        parent::setUp();
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
        parent::tearDown();
    }

    // Begin of test functions.

    /**
     * Test, if a upvote is being counted.
     * @covers \userstats_table
     */
    public function test_upvote(): void {
        // Teacher upvotes the discussion and the answer of user2.
        $this->create_upvote($this->teacher, $this->discussion1[1], $this->answer1);

        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $upvotes = $this->get_specific_userstats($data, $this->user2, 'receivedupvotes');
        $this->assertEquals(1, $upvotes);
    }

    /**
     * Test, if a downvote is being counted.
     * @covers \userstats_table
     */
    public function test_downvote(): void {
        // Teacher downvotes the discussion and the answer of user1.
        $this->create_downvote($this->teacher, $this->discussion2[1], $this->answer2);

        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $downvotes = $this->get_specific_userstats($data, $this->user1, 'receiveddownvotes');
        $this->assertEquals(1, $downvotes);
    }

    /**
     * Test, if the activity is calculated correctly.
     * @covers \userstats_table
     */
    public function test_activity(): void {
        // User1 will rates 3 times.
        $this->create_helpful($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_upvote($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_downvote($this->user1, $this->discussion2[1], $this->post2);
        // User1 created 2 posts (1 discussion, 1 answer).
        // Activity = 5.
        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $activity = $this->get_specific_userstats($data, $this->user1, 'forumactivity');
        $this->assertEquals(5, $activity);
    }
    /**
     * Test, if the reputation is calculated correctly.
     * @covers \userstats_table
     */
    public function test_reputation(): void {
        // User1 creates some ratings for user2, Teacher creates some ratings for user2.
        $this->create_helpful($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_upvote($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_downvote($this->user1, $this->discussion2[1], $this->post2);
        $this->create_solution($this->teacher, $this->discussion1[1], $this->answer1);

        // Calculate the forum reputation of user2.
        $reputation = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation_instance(
            $this->moodleoverflow->id,
            $this->user2->id
        );
        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $reputation2 = $this->get_specific_userstats($data, $this->user2, 'forumreputation');
        $this->assertEquals($reputation, $reputation2);
    }

    /**
     * Test, if userstats are calculated correctly if the moodleoverflow is partially anonymous.
     * @covers \userstats_table
     */
    public function test_partial_anonymous(): void {
        global $DB;
        // Test case: Only topic startes are anonymous.
        $this->make_anonymous(1);

        // Get the current userstats to compare later.
        $olduserstats = $this->create_statstable();
        $oldupvotesuser1 = $this->get_specific_userstats($olduserstats, $this->user1, 'receivedupvotes');
        $oldactivityuser1 = $this->get_specific_userstats($olduserstats, $this->user1, 'forumactivity');

        $oldupvotesuser2 = $this->get_specific_userstats($olduserstats, $this->user2, 'receivedupvotes');
        $oldactivityuser2 = $this->get_specific_userstats($olduserstats, $this->user2, 'forumactivity');

        // User1 starts a new discussion, the forum activity shouldn't change.
        $discussion = $this->generator->post_to_forum($this->moodleoverflow, $this->user1);
        $starterpost = $DB->get_record('moodleoverflow_posts', ['id' => $discussion[0]->firstpost], '*');
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->user1, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        // User2 now gives an answer to user1, his activity should change.
        $answeruser2 = $this->generator->reply_to_post($discussion[1], $this->user2, true);
        $newuserstats = $this->create_statstable();
        $newactivityuser2 = $this->get_specific_userstats($newuserstats, $this->user2, 'forumactivity');
        $this->assertEquals($oldactivityuser2 + 1, $newactivityuser2);
        $oldactivityuser2 = $newactivityuser2;  // Update it for further comparisons.

        // User1 rates the answer from user2 as helpful an gives it an upvote.
        // The activity of user1 should only change when he gives an upvote.
        // The received upvotes from user2 should change.
        $this->create_helpful($this->user1, $discussion[1], $answeruser2);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->user1, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        $this->create_upvote($this->user1, $discussion[1], $answeruser2);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->user1, 'forumactivity');
        $newupvotesuser2 = $this->get_specific_userstats($newuserstats, $this->user2, 'receivedupvotes');
        $this->assertEquals($oldactivityuser1 + 1, $newactivityuser1);
        $this->assertEquals($oldupvotesuser2 + 1, $newupvotesuser2);

        // User2 gives the discussion starter post an upvote.
        // Activity of User2 should change, the receivedupvotes from user1 shouln't change.
        $this->create_upvote($this->user2, $discussion[1], $starterpost);
        $newuserstats = $this->create_statstable();
        $newactivityuser2 = $this->get_specific_userstats($newuserstats, $this->user2, 'forumactivity');
        $newupvotesuser1 = $this->get_specific_userstats($newuserstats, $this->user1, 'receivedupvotes');
        $this->assertEquals($oldactivityuser2 + 1, $newactivityuser2);
        $this->assertEquals($oldupvotesuser1, $newupvotesuser1);
    }

    /**
     * Test, if userstats are calculated correctly if the moodleoverflow is totally anonymous.
     * @covers \userstats_table
     */
    public function test_total_anonymous(): void {
        // Test case: Only topic startes are anonymous.
        $this->make_anonymous(2);

        // Get the current userstats to compare later.
        $olduserstats = $this->create_statstable();
        $oldactivityuser1 = $this->get_specific_userstats($olduserstats, $this->user1, 'forumactivity');

        $oldupvotesuser2 = $this->get_specific_userstats($olduserstats, $this->user2, 'receivedupvotes');
        $oldactivityuser2 = $this->get_specific_userstats($olduserstats, $this->user2, 'forumactivity');

        // User1 starts a new discussion, the forum activity shouldn't change.
        $discussion = $this->generator->post_to_forum($this->moodleoverflow, $this->user1);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->user1, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        // User2 now gives an answer to user1, his activity shouldn't change.
        $answeruser2 = $this->generator->reply_to_post($discussion[1], $this->user2, true);
        $newuserstats = $this->create_statstable();
        $newactivityuser2 = $this->get_specific_userstats($newuserstats, $this->user2, 'forumactivity');
        $this->assertEquals($oldactivityuser2, $newactivityuser2);

        // User1 rates the answer from user2 as helpful an gives it an upvote.
        // The activity of user1 should only change when he gives an upvote.
        // User2 received upvotes should not change.
        $this->create_helpful($this->user1, $discussion[1], $answeruser2);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->user1, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        $this->create_upvote($this->user1, $discussion[1], $answeruser2);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->user1, 'forumactivity');
        $newupvotesuser2 = $this->get_specific_userstats($newuserstats, $this->user2, 'receivedupvotes');
        $this->assertEquals($oldactivityuser1 + 1, $newactivityuser1);
        $this->assertEquals($oldupvotesuser2, $newupvotesuser2);
    }

    // Helper functions.

    /**
     * This function creates:
     * - a course with a moodleoverflow
     * - a teacher
     * - 2 users, which create a discussion and a post in the discussion of the other user.
     */
    private function helper_course_set_up() {
        global $DB;
        // Create a new course with a moodleoverflow forum.
        $this->course = $this->getDataGenerator()->create_course();
        $location = ['course' => $this->course->id];
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->moodleoverflow->id);

        // Create a teacher.
        $this->teacher = $this->getDataGenerator()->create_user(['firstname' => 'Tamaro', 'lastname' => 'Walter']);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'student');

        // Create 2 users and their discussions and posts.
        $this->user1 = $this->getDataGenerator()->create_user(['firstname' => 'Ava', 'lastname' => 'Davis']);
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course->id, 'student');
        $this->user2 = $this->getDataGenerator()->create_user(['firstname' => 'Ethan', 'lastname' => 'Brown']);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course->id, 'student');

        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->discussion1 = $this->generator->post_to_forum($this->moodleoverflow, $this->user1);
        $this->discussion2 = $this->generator->post_to_forum($this->moodleoverflow, $this->user2);
        $this->post1 = $DB->get_record('moodleoverflow_posts', ['id' => $this->discussion1[0]->firstpost], '*');
        $this->post2 = $DB->get_record('moodleoverflow_posts', ['id' => $this->discussion1[0]->firstpost], '*');
        $this->answer1 = $this->generator->reply_to_post($this->discussion1[1], $this->user2, true);
        $this->answer2 = $this->generator->reply_to_post($this->discussion2[1], $this->user1, true);
    }

    /**
     * Makes the existing moodleoverflow anonymous.
     * There are 2 types of anonymous moodleoverflows:
     * anonymous = 1, the topic starter is anonymous
     * anonymous = 2, all users are anonymous
     *
     * @param int $anonymoussetting
     */
    private function make_anonymous($anonymoussetting) {
        global $DB;
        if ($anonymoussetting == 1 || $anonymoussetting == 2) {
            $this->moodleoverflow->anonymous = $anonymoussetting;
            $DB->update_record('moodleoverflow', $this->moodleoverflow);
        } else {
            throw new \Exception('invalid parameter, anonymoussetting should be 1 or 2');
        }
    }

    /**
     * Create a usertable and return it.
     */
    private function create_statstable() {
        $url = new \moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $this->coursemodule->id,
                                                                     'courseid' => $this->course->id,
                                                                     'mid' => $this->moodleoverflow->id, ]);
        $userstatstable = new userstats_table('testtable', $this->course->id, $this->moodleoverflow->id, $url);
        $userstatstable->get_table_data();
        return $userstatstable->get_usertable();
    }

    /**
     * Create a upvote to a post in an existing discussion.
     *
     * @param object $author       // The creator of the rating.
     * @param object $discussion   // Discussion object.
     * @param object $post         // Post that is being rated.
     *
     * @return $rating
     */
    private function create_upvote($author, $discussion, $post) {
        $record = (object) [
            'moodleoverflowid' => $this->moodleoverflow->id,
            'discussionid' => $discussion->id,
            'userid' => $author->id,
            'postid' => $post->id,
            'rating' => 2,
            'firstrated' => time(),
            'lastchanged' => time(),
        ];
        return $this->generator->create_rating($record);
    }

    /**
     * Create a downvote to a post in an existing discussion.
     *
     * @param object $author       // The creator of the rating.
     * @param object $discussion   // Discussion object.
     * @param object $post         // Post that is being rated.
     *
     * @return $rating
     */
    private function create_downvote($author, $discussion, $post) {
        $record = (object) [
            'moodleoverflowid' => $this->moodleoverflow->id,
            'discussionid' => $discussion->id,
            'userid' => $author->id,
            'postid' => $post->id,
            'rating' => 1,
            'firstrated' => time(),
            'lastchanged' => time(),
        ];
        return $this->generator->create_rating($record);
    }

    /**
     * Create a helpful rating to a post in an existing discussion.
     *
     * @param object $author       // The creator of the rating.
     * @param object $discussion   // Discussion object.
     * @param object $post         // Post that is being rated.
     *
     * @return $rating
     */
    private function create_helpful($author, $discussion, $post) {
        $record = (object) [
            'moodleoverflowid' => $this->moodleoverflow->id,
            'discussionid' => $discussion->id,
            'userid' => $author->id,
            'postid' => $post->id,
            'rating' => 4,
            'firstrated' => time(),
            'lastchanged' => time(),
        ];
        return $this->generator->create_rating($record);
    }

    /**
     * Create a solution rating to a post in an existing discussion.
     *
     * @param object $author       // The creator of the rating.
     * @param object $discussion   // Discussion object.
     * @param object $post         // Post that is being rated.
     *
     * @return $rating
     */
    private function create_solution($author, $discussion, $post) {
        $record = (object) [
            'moodleoverflowid' => $this->moodleoverflow->id,
            'discussionid' => $discussion->id,
            'userid' => $author->id,
            'postid' => $post->id,
            'rating' => 3,
            'firstrated' => time(),
            'lastchanged' => time(),
        ];
        return $this->generator->create_rating($record);
    }

    /**
     * Return a specific value from the userstatstable.
     *
     * @param array     $statstable
     * @param object    $user
     * @param string    $stats          // A key that specifies which value should be returned.
     */
    private function get_specific_userstats($statstable, $user, $stats) {
        foreach ($statstable as $student) {
            if ($student->id == $user->id) {
                switch ($stats) {
                    case 'receivedupvotes':
                        $result = $student->receivedupvotes;
                        break;
                    case 'receiveddownvotes':
                        $result = $student->receiveddownvotes;
                        break;
                    case 'forumactivity':
                        $result = $student->forumactivity;
                        break;
                    case 'forumreputation':
                        $result = $student->forumreputation;
                        break;
                    default:
                        throw new \Exception('parameter unknown');
                        break;
                }
            }
        }
        return $result;
    }
}
