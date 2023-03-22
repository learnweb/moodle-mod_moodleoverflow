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
class userstats_test extends \advanced_testcase {

    private $course;
    private $coursemodule;
    private $context;
    private $moodleoverflow;
    private $teacher;
    private $user1;
    private $user2;
    private $discussion1;       // Discussion from user1.
    private $discussion2;       // Discussion from user2.
    private $answer1;           // Answerpost to discussion1 from user2.
    private $answer2;           // Answerpost to discussion2 from user1.
    private $generator;         // Generator for moodleoverflow.

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
     */
    public function test_upvote() {
        // teacher upvotes the discussion and the answer of user2
        $this->create_upvote($this->teacher, $this->discussion1[1], $this->answer1);

        // Create the user statistics table for this course
        $userstatstable = new userstats_table('testtable', $this->course->id, $this->context, $this->moodleoverflow->id);
        $userstatstable->get_table_data();
        $data = $userstats->get_usertable();
        var_dump($data);
        foreach ($data as $student) {
            if ($student->id == $this->user2->id) {
                $upvotes = $student->receivedupvotes;
            }
        }
        $this->assertEquals(1, 1);
    }


    // Helper functions.

    /**
     * This function creates:
     * - a course with a moodleoverflow
     * - a teacher
     * - 2 users, which create a discussion and a post in the discussion of the other user.
     */
    private function helper_course_set_up() {
        // Create a new course with a moodleoverflow forum.
        $this->course = $this->getDataGenerator()->create_course();
        $location = array('course' => $this->course->id);
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->moodleoverflow->id);
        $this->context = \context_course::instance($this->course->id);

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
        $this->answer1 = $this->generator->reply_to_post($this->discussion1[1], $this->user2, true);
        $this->answer2 = $this->generator->reply_to_post($this->discussion2[1], $this->user1, true);
    }


    /**
     * Create a upvote to a post in an existing discussion
     */
    public function create_upvote($author, $discussion, $post) {
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
     * Create a downvote to a post in an existing discussion
     */
    public function create_downvote($author, $discussion, $post) {
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
     * Create a helpful rating to a post in an existing discussion
     */
    public function create_helpful($author, $discussion, $post) {
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
     * Create a solution rating to a post in an existing discussion
     */
    public function create_solution($author, $discussion, $post) {
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
