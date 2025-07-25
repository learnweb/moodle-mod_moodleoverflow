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
 * PHP Unit Tests for the Discussion class.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use mod_moodleoverflow\post\post;
use mod_moodleoverflow\discussion\discussion;

/**
 * Tests if the functions from the discussion class are working correctly.
 * As the discussion class works as an administrator of the post class, most of the testcases are already realized in the
 * post_test.php file.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\discussion\discussion
 */
final class discussion_test extends \advanced_testcase {

    /** @var \stdClass test course */
    private $course;

    /** @var \stdClass coursemodule */
    private $coursemodule;

    /** @var \stdClass modulecontext */
    private $modulecontext;

    /** @var \stdClass test moodleoverflow */
    private $moodleoverflow;

    /** @var \stdClass test teacher */
    private $teacher;

    /** @var discussion a discussion */
    private $discussion;

    /** @var post the post from the discussion */
    private $post;

    /** @var \mod_moodleoverflow_generator $generator */
    private $generator;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->helper_course_set_up();
    }

    public function tearDown(): void {
        // Clear all caches.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();
        parent::tearDown();
    }

    /**
     * Test, if a discussion is being created correctly
     */
    public function test_create_discussion(): void {
        global $DB;

        // Build a prepost object with important information.
        $time = time();
        $prepost = new \stdClass();
        $prepost->userid = $this->teacher->id;
        $prepost->timenow = $time;
        $prepost->message = 'a message';
        $prepost->messageformat = 1;
        $prepost->reviewed = 0;
        $prepost->formattachments = '';
        $prepost->modulecontext = $this->modulecontext;

        // Build a new discussion object.
        $discussion = discussion::construct_without_id($this->course->id, $this->moodleoverflow->id, 'Discussion Topic',
                                           0, $this->teacher->id, $time, $time, $this->teacher->id);
        $discussionid = $discussion->moodleoverflow_add_discussion($prepost);
        $posts = $discussion->moodleoverflow_get_discussion_posts();
        $post = $posts[$discussion->get_firstpostid()];

        // The discussion and the firstpost should be in the DB.
        $dbdiscussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->get_id()]);
        $this->assertEquals($dbdiscussion->id, $discussionid);
        $this->assertEquals('Discussion Topic', $dbdiscussion->name);

        $dbpost = $DB->get_record('moodleoverflow_posts', ['id' => $discussion->get_firstpostid()]);
        $this->assertEquals($dbpost->id, $post->get_id());
        $this->assertEquals($dbpost->discussion, $post->get_discussionid());
        $this->assertEquals($prepost->message, $dbpost->message);
    }

    /**
     * Test, if a post and its attachment are deleted successfully.
     * @covers ::moodleoverflow_delete_post
     */
    public function test_delete_discussion(): void {
        global $DB;
        // Build the prepost object with necessary information.
        $prepost = new \stdClass();
        $prepost->modulecontext = $this->modulecontext;

        // Delete the discussion, but save the IDs first.
        $discussionid = $this->discussion->get_id();
        $postid = $this->discussion->get_firstpostid();
        $this->discussion->moodleoverflow_delete_discussion($prepost);

        // The discussion and the post should not be in the DB anymore.
        $discussion = count($DB->get_records('moodleoverflow_discussions', ['id' => $discussionid]));
        $this->assertEquals(0, $discussion);

        $post = count($DB->get_records('moodleoverflow_posts', ['id' => $postid]));
        $this->assertEquals(0, $post);
    }

    /**
     * This function creates:
     * - a course with a moodleoverflow
     * - a new discussion with a post. The post has an attachment.
     */
    private function helper_course_set_up() {
        global $DB;
        // Create a new course with a moodleoverflow forum.
        $this->course = $this->getDataGenerator()->create_course();
        $location = ['course' => $this->course->id];
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->moodleoverflow->id);
        $this->modulecontext = \context_module::instance($this->coursemodule->id);

        // Create a teacher.
        $this->teacher = $this->getDataGenerator()->create_user(['firstname' => 'Tamaro', 'lastname' => 'Walter']);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'student');

        // Create a discussion started from the teacher.
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $discussion = $this->generator->post_to_forum($this->moodleoverflow, $this->teacher);

        // Get the discussion and post object.
        $discussionrecord = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion[0]->id]);
        $postrecord = $DB->get_record('moodleoverflow_posts', ['id' => $discussion[1]->id]);

        $this->discussion = discussion::from_record($discussionrecord);
        $this->post = post::from_record($postrecord);
    }
}
