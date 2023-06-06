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
 */
class userstats_test extends \advanced_testcase {

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
     * Test, if a upvote is being counted.
     * @covers \userstats_table
     */
    public function test_upvote() {
        // Teacher upvotes the discussion and the answer of user2.
        $this->create_upvote($this->teacher, $this->discussion1[1], $this->answer1);

        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        foreach ($data as $student) {
            if ($student->id == $this->user2->id) {
                $upvotes = $student->receivedupvotes;
            }
        }
        $this->assertEquals(1, $upvotes);
    }

    /**
     * Test, if a downvote is being counted.
     * @covers \userstats_table
     */
    public function test_downvote() {
        // Teacher downvotes the discussion and the answer of user1.
        $this->create_downvote($this->teacher, $this->discussion2[1], $this->answer2);

        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        foreach ($data as $student) {
            if ($student->id == $this->user1->id) {
                $downvotes = $student->receiveddownvotes;
            }
        }
        $this->assertEquals(1, $downvotes);
    }

    /**
     * Test, if the activity is calculated correctly.
     * @covers \userstats_table
     */
    public function test_activity() {
        // User1 will rates 3 times.
        $this->create_helpful($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_upvote($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_downvote($this->user1, $this->discussion2[1], $this->post2);
        // User1 created 2 posts (1 discussion, 1 answer).
        // Activity = 5.
        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        foreach ($data as $student) {
            if ($student->id == $this->user1->id) {
                $activity = $student->forumactivity;
            }
        }
        $this->assertEquals(5, $activity);

    }
    /**
     * Test, if the reputation is calculated correctly.
     * @covers \userstats_table
     */
    public function test_reputation() {
        // User1 creates some ratings for user2, Teacher creates some ratings for user2.
        $this->create_helpful($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_upvote($this->user1, $this->discussion1[1], $this->answer1);
        $this->create_downvote($this->user1, $this->discussion2[1], $this->post2);
        $this->create_solution($this->teacher, $this->discussion1[1], $this->answer1);

        // Calculate the forum reputation of user2.
        $reputation = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation_instance($this->moodleoverflow->id, $this->user2->id);
        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        foreach ($data as $student) {
            if ($student->id == $this->user2->id) {
                $reputation2 = $student->forumreputation;
            }
        }
        $this->assertEquals($reputation, $reputation2);
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
        $location = array('course' => $this->course->id);
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->moodleoverflow->id);

        // Create a teacher.
        $this->teacher = $this->getDataGenerator()->create_user(array('firstname' => 'Tamaro', 'lastname' => 'Walter'));
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'student');

        // Create 2 users and their discussions and posts.
        $this->user1 = $this->getDataGenerator()->create_user(array('firstname' => 'Ava', 'lastname' => 'Davis'));
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course->id, 'student');
        $this->user2 = $this->getDataGenerator()->create_user(array('firstname' => 'Ethan', 'lastname' => 'Brown'));
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course->id, 'student');

        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->discussion1 = $this->generator->post_to_forum($this->moodleoverflow, $this->user1);
        $this->discussion2 = $this->generator->post_to_forum($this->moodleoverflow, $this->user2);
        $this->post1 = $DB->get_record('moodleoverflow_posts', array('id' => $this->discussion1[0]->firstpost), '*');
        $this->post2 = $DB->get_record('moodleoverflow_posts', array('id' => $this->discussion1[0]->firstpost), '*');
        $this->answer1 = $this->generator->reply_to_post($this->discussion1[1], $this->user2, true);
        $this->answer2 = $this->generator->reply_to_post($this->discussion2[1], $this->user1, true);
    }


    /**
     * Create a usertable and return it.
     */
    private function create_statstable() {
        $url = new \moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $this->coursemodule->id,
                                                                     'courseid' => $this->course->id,
                                                                     'mid' => $this->moodleoverflow->id]);
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
            'lastchanged' => time()
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
            'lastchanged' => time()
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
            'rating' => 3,
            'firstrated' => time(),
            'lastchanged' => time()
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
            'rating' => 4,
            'firstrated' => time(),
            'lastchanged' => time()
        ];
        return $this->generator->create_rating($record);
    }
}
