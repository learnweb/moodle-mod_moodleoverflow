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

global $CFG;
require_once(dirname(__FILE__) . '/../locallib.php');

class mod_moodleoverflow_locallib_testcase extends advanced_testcase {

    public function create_moodleoverflow_activity() {
        global $DB;

        // Create a new course category.
        $coursecategory = $this->getDataGenerator()->create_category();
        $this->assertEquals(2, $DB->count_records('course_categories'), 'Creating new course category failed.');

        // Create a new course.
        $course = $this->getDataGenerator()->create_course(array('fullname' => 'Created test course', 'category' => $coursecategory->id));
        $this->assertEquals(2, $DB->count_records('course', array()), 'Creating course failed');
        $this->assertEquals($coursecategory->id, $DB->get_record('course', array('fullname' => 'Created test course'))->category, 'Course not created correctly.');

        // Create a new moodleoverflow instance.
        $this->assertEquals(0, $DB->count_records('moodleoverflow'), 'Creating moodleoverflow instance can not be initiated.');
        $record1 = new stdClass();
        $record1->course         = $course->id;
        $record1->name           = 'Test Overflow Instance';
        $record1->intro          = 'Test Intro';
        $record1->introformat    = '1';
        $record1->timecreated    = '1493584103';
        $record1->timemodified   = '1493585356';
        $record1->forcesubscribe = 0;
        $record1->trackingtype   = 1;
        $DB->insert_record('moodleoverflow', $record1);
        $this->assertEquals(1, $DB->count_records('moodleoverflow'), 'Creating moodleoverflow instance failed.');
        $moodleoverflow = $DB->get_record('moodleoverflow', array('name' => 'Test Overflow Instance'));

        // Link the course and the module.
        $this->assertEquals(0, $DB->count_records('course_modules'), 'Creating course module can not be initiated.');
        $record2 = new stdClass();
        $record2->course = $course->id;
        $record2->module = $DB->get_record('modules', array('name' => 'moodleoverflow'))->id;
        $record2->instance = $moodleoverflow->id;
        $record2->section = 1;
        $record2->added = 1492701700;
        $DB->insert_record('course_modules', $record2);
        $this->assertEquals(1, $DB->count_records('course_modules'), 'Creating course module failed.');
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id, false, MUST_EXIST);

        // Return the coursemodule.
        return $cm;
    }

    public function create_enrol_users($course) {
        global $DB;

        // Create two new users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->assertEquals(4, $DB->count_records('user', array()), 'Creating new users failed.');
        $this->setUser($user1);

        // Enrol both created users to the course.
        $this->assertEquals(0, $DB->count_records('user_enrolments'), 'Enrolling users can not be initiated.');
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->assertEquals(2, $DB->count_records('user_enrolments'), 'Enrolling users to course failed.');

        // Return the IDs as array.
        return array($user1->id, $user2->id);
    }

    public function create_discussion($course, $moodleoverflow, $user, $timestamp) {
        global $DB;

        // Increase the iterator.
        $i = $DB->count_records('moodleoverflow_discussions');
        $j = $i + 1;

        // Create a new discussion.
        $this->assertEquals($i, $DB->count_records('moodleoverflow_discussions'), 'Creating moodleoverflow discussion can not be initiated.');
        $record3 = new stdClass();
        $record3->course         = $course->id;
        $record3->moodleoverflow = $moodleoverflow->id;
        $record3->name           = 'First test discussion';
        $record3->firstpost      = 0;
        $record3->userid         = $user;
        $record3->timemodified   = 0;
        $record3->timestart      = $timestamp;
        $record3->usermodified   = $user;
        $DB->insert_record('moodleoverflow_discussions', $record3);
        $this->assertEquals($j, $DB->count_records('moodleoverflow_discussions'), 'Creating first moodleoverflow discussion can not be initiated.');
        $discussion = $DB->get_record('moodleoverflow_discussions', array('timestart' => $timestamp));

        // Initiate the iteration.
        $i = $DB->count_records('moodleoverflow_posts');
        $j = $i + 1;

        // Create a new startpost for the discussion.
        $this->assertEquals($i, $DB->count_records('moodleoverflow_posts'), 'Creating startposts for discussions can not be initiated.');
        $record4 = new stdClass();
        $record4->discussion = $discussion->id;
        $record4->parent = 0;
        $record4->userid = $user;
        $record4->created = $timestamp;
        $record4->modified = $timestamp;
        $record4->message = 'Startpost for the first discussion.';
        $DB->insert_record('moodleoverflow_posts', $record4);
        $this->assertEquals($j, $DB->count_records('moodleoverflow_posts'), 'Creating startposts for first discussion failed.');
        $post = $DB->get_record('moodleoverflow_posts', array('created' => $timestamp));

        // Connect the startpost with the discussion.
        $record3->id        = $discussion->id;
        $record3->firstpost = $post->id;
        $DB->update_record('moodleoverflow_discussions', $record3);
        $discussion->firstpost = $post->id;

        // Return the discussion.
        return $discussion;
    }

    public function create_post($discussion, $parent, $user, $timestamp) {
        global $DB;

        // Initiate iterator.
        $i = $DB->count_records('moodleoverflow_posts');
        $j = $i + 1;

        // Create a new post for the discussion.
        $this->assertEquals($i, $DB->count_records('moodleoverflow_posts'), 'Creating startposts for discussions can not be initiated.');
        $record4 = new stdClass();
        $record4->discussion = $discussion->id;
        $record4->parent = $parent;
        $record4->userid = $user;
        $record4->created = $timestamp;
        $record4->modified = $timestamp;
        $record4->message = 'Startpost for the first discussion.';
        $DB->insert_record('moodleoverflow_posts', $record4);
        $this->assertEquals($j, $DB->count_records('moodleoverflow_posts'), 'Creating startposts for first discussion failed.');
        $post = $DB->get_record('moodleoverflow_posts', array('created' => $timestamp));

        // We are not updating the modifieddate of the discussion.
        // This is handled by the corresponding function and will be tested seperatly.

        // Return the post.
        return $post;
    }

    public function mark_post_read($moodleoverflowid, $discussionid, $postid, $user, $timestamp) {
        global $DB;

        // Create an object.
        $record = new stdClass();
        $record->userid = $user;
        $record->moodleoverflowid = $moodleoverflowid;
        $record->discussionid = $discussionid;
        $record->postid = $postid;
        $record->firstread = $timestamp;
        $record->lastread = $timestamp;
        $DB->insert_record('moodleoverflow_read', $record);
    }

    public function test_get_discussion_count() {
        global $DB;

        // Reset all changes after the test.
        $this->resetAfterTest(true);

        // Retrieve the coursemodule.
        $cm = $this->create_moodleoverflow_activity();

        // Fetch course and moodleoverflow instance.
        $course          = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $moodleoverflow  = $DB->get_record('moodleoverflow', array('id' => $cm->instance), '*', MUST_EXIST);

        // Create two users.
        $users = $this->create_enrol_users($course);

        // Create a new discussion.
        $this->create_discussion($course, $moodleoverflow, $users[1], 1492703700);

        // Test moodleoverflow_get_discussions_count.
        $this->assertEquals(1, moodleoverflow_get_discussions_count($cm));

        // Create a new discussion.
        $this->create_discussion($course, $moodleoverflow, $users[1], 1492705700);

        // Test moodleoverflow_get_discussions_count.
        $this->assertEquals(2, moodleoverflow_get_discussions_count($cm));
    }

    public function test_get_discussions() {
        global $DB;

        // Reset all changes after the test.
        $this->resetAfterTest(true);

        // Retrieve the coursemodule.
        $cm = $this->create_moodleoverflow_activity();

        // Fetch course and moodleoverflow instance.
        $course          = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $moodleoverflow  = $DB->get_record('moodleoverflow', array('id' => $cm->instance), '*', MUST_EXIST);

        // Create two users.
        $users = $this->create_enrol_users($course);

        // Test with no results.
        $this->assertEquals(0, count(moodleoverflow_get_discussions($cm, 0, 0)));

        // Create a new discussion and test again.
        $discussion0 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703700);
        $this->assertEquals(1, count(moodleoverflow_get_discussions($cm, 0, 0)));

        // Create more discussions.
        $discussion1 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703701);
        $discussion2 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703702);
        $discussion3 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703703);
        $discussion4 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703704);
        $discussion5 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703705);
        $discussion6 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703706);
        $discussion7 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703707);
        $discussion8 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703708);
        $discussion9 = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703709);
        $this->assertEquals(10, count(moodleoverflow_get_discussions($cm, 0, 0)));

        // Test the join between posts and discussions.
        $this->assertEquals($discussion1->id, array_values(moodleoverflow_get_discussions($cm, 1, 1))[0]->discussion);

        // Test page-parameter.
        $this->assertEquals(4, count(moodleoverflow_get_discussions($cm, 0, 4)));
        $this->assertEquals(4, count(moodleoverflow_get_discussions($cm, 1, 4)));
        $this->assertEquals(2, count(moodleoverflow_get_discussions($cm, 2, 4)));

        // Test special parameter.
        $this->assertEquals(10, count(moodleoverflow_get_discussions($cm, 0, -1)));
        $this->assertEquals(10, count(moodleoverflow_get_discussions($cm, -1, 5)));
    }

    public function test_count_discussion_replies() {
        global $DB;

        // Reset all changes after the test.
        $this->resetAfterTest(true);

        // Retrieve the coursemodule.
        $cm = $this->create_moodleoverflow_activity();

        // Fetch course and moodleoverflow instance.
        $course          = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $moodleoverflow  = $DB->get_record('moodleoverflow', array('id' => $cm->instance), '*', MUST_EXIST);

        // Create two users.
        $users = $this->create_enrol_users($course);

        // Test with no results.
        $this->assertEmpty(moodleoverflow_count_discussion_replies($moodleoverflow->id));

        // Create a new discussion and test again.
        $discussion = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703700);
        $this->assertEmpty(moodleoverflow_count_discussion_replies($moodleoverflow->id));

        // Create a new post.
        $post = $this->create_post($discussion, $discussion->firstpost, $users[1], 1492704700);
        $this->assertEquals(1, moodleoverflow_count_discussion_replies($moodleoverflow->id)[$discussion->id]->replies);

        // Is the lastpostid correct?
        $this->assertEquals($post->id, moodleoverflow_count_discussion_replies($moodleoverflow->id)[$discussion->id]->lastpostid);
    }

    public function test_get_discussions_unread() {
        global $DB, $CFG;

        // Reset all changes after the test.
        $this->resetAfterTest(true);

        // Retrieve the coursemodule.
        $cm = $this->create_moodleoverflow_activity();

        // Fetch course and moodleoverflow instance.
        $course          = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $moodleoverflow  = $DB->get_record('moodleoverflow', array('id' => $cm->instance), '*', MUST_EXIST);

        // Create two users.
        // The first user is logged in.
        $users = $this->create_enrol_users($course);

        // First test.
        $this->assertEmpty(moodleoverflow_get_discussions_unread($cm));

        // Create a timestamp.
        $yesterday = round(time(), -2) - 24 * 60 * 60;

        // Create a discussion and test again.
        $discussion = $this->create_discussion($course, $moodleoverflow, $users[1], $yesterday);
        $this->assertEquals(1, moodleoverflow_get_discussions_unread($cm)[$discussion->id]);

        // Create a new post.
        $post1 = $this->create_post($discussion, $discussion->firstpost, $users[1], $yesterday + 50);
        $this->assertEquals(2, moodleoverflow_get_discussions_unread($cm)[$discussion->id]);

        // Create a new post which is older than the config.
        $cutoffdate = round(time(), -2) - ($CFG->moodleoverflow_oldpostdays * 24 * 60 * 60);
        $post2 = $this->create_post($discussion, $discussion->firstpost, $users[1], $cutoffdate - 50);
        $this->assertEquals(2, moodleoverflow_get_discussions_unread($cm)[$discussion->id]);

        // Mark the oldest post as read.
        $this->mark_post_read($moodleoverflow->id, $post2->discussion, $post2->id, $users[0], time());
        $this->assertEquals(2, moodleoverflow_get_discussions_unread($cm)[$discussion->id]);

        // Mark the other answer as read.
        $this->mark_post_read($moodleoverflow->id, $post1->discussion, $post1->id, $users[0], time());
        $this->assertEquals(1, moodleoverflow_get_discussions_unread($cm)[$discussion->id]);

        // Mark the startingpost as read.
        $this->mark_post_read($moodleoverflow->id, $discussion->id, $discussion->firstpost, $users[0], time());
        $this->assertEmpty(moodleoverflow_get_discussions_unread($cm));
    }

    public function test_get_full_post() {
        global $DB;

        // Reset all changes after the test.
        $this->resetAfterTest(true);

        // Retrieve the coursemodule.
        $cm = $this->create_moodleoverflow_activity();

        // Fetch course and moodleoverflow instance.
        $course          = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $moodleoverflow  = $DB->get_record('moodleoverflow', array('id' => $cm->instance), '*', MUST_EXIST);

        // Create two users.
        $users = $this->create_enrol_users($course);

        // Create and test a discussion.
        $discussion = $this->create_discussion($course, $moodleoverflow, $users[1], 1492703700);
        $this->assertEquals($discussion->id, moodleoverflow_get_post_full($discussion->firstpost)->discussion);

        // Create a new post and test it.
        $post = $this->create_post($discussion, $discussion->firstpost, $users[1], 1492704700);
        $this->assertEquals($discussion->id, moodleoverflow_get_post_full($post->id)->discussion);

        // Test non-valid inputs.
        $this->assertFalse(moodleoverflow_get_post_full(null));
        $this->assertFalse(moodleoverflow_get_post_full($post->id + 1));
    }

    public function test_user_can_post_discussion() {
        global $DB;

        // Reset all changes after the test.
        $this->resetAfterTest(true);

        // Retrieve the coursemodule.
        $cm = $this->create_moodleoverflow_activity();

        // Fetch course and moodleoverflow instance.
        $course          = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $moodleoverflow  = $DB->get_record('moodleoverflow', array('id' => $cm->instance), '*', MUST_EXIST);

        // Create two users.
        // Login the first user.
        $users = $this->create_enrol_users($course);

        // Test for the enrolled and logged in user.
        $this->assertTrue(moodleoverflow_user_can_post_discussion($moodleoverflow, $cm));

        // Test with context.
        $context = context_module::instance($cm->id);
        $this->assertTrue(moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $context));

        // Test without the coursemodule.
        // ToDo: This is causing an error because of a used function. What to do?
        // $this->assertTrue(moodleoverflow_user_can_post_discussion($moodleoverflow));

        // Logout and try again.
        $this->setUser(null);
        $this->assertFalse(moodleoverflow_user_can_post_discussion($moodleoverflow, $cm));

        // Create an unenrolled user and test again.
        $user3 = $this->getDataGenerator()->create_user();
        $this->setUser($user3);
        $this->assertFalse(moodleoverflow_user_can_post_discussion($moodleoverflow, $cm));

        // Create a guestuser and test again.
        $this->setGuestUser();
        $this->assertFalse(moodleoverflow_user_can_post_discussion($moodleoverflow, $cm));
    }

    public function test_track_can_track_moodleoverflows() {

        // ToDo: Implement this.
        $this->assertEquals(2, 1 + 1);
    }

    public function test_track_is_tracked() {

        // ToDo: Implement this.
        $this->assertEquals(2, 1 + 1);
    }

}