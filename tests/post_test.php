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
 * PHP Unit Tests for the Post class.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

// Use the post class.
use context;
use mod_moodleoverflow\post\post;
use mod_moodleoverflow\discussion\discussion;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../locallib.php');

/**
 *
 * Tests if the functions from the post class are working correctly.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\post\post
 */
final class post_test extends \advanced_testcase {

    /** @var stdClass test course */
    private $course;

    /** @var stdClass coursemodule */
    private $coursemodule;

    /** @var stdClass modulecontext */
    private $modulecontext;

    /** @var stdClass test moodleoverflow */
    private $moodleoverflow;

    /** @var stdClass test teacher */
    private $teacher;

    /** @var discussion a discussion */
    private $discussion;

    /** @var post a post */
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
     * Test, if a post is being created correctly
     */
    public function test_create_post(): void {
        global $DB;
        // Build a new post object.
        $time = time();
        $message = 'a unique message';
        $post = post::construct_without_id($this->discussion->get_id(), $this->post->get_id(), $this->teacher->id, $time,
                                           $time, $message, 0, '', 0, 1, null);
        $post->moodleoverflow_add_new_post();

        // The post should be in the database.
        $postscount = count($DB->get_records('moodleoverflow_posts', ['id' => $post->get_id()]));
        $this->assertEquals(1, $postscount);
    }

    /**
     * Test, if the message of a post can be edited successfully.
     */
    public function test_edit_post(): void {
        global $DB;

        // The post and the attachment should exist.
        $numberofattachments = count($DB->get_records('files', ['itemid' => $this->post->get_id()]));
        $this->assertEquals(2, $numberofattachments); // One Attachment is saved twice in 'files'.
        $post = count($DB->get_records('moodleoverflow_posts', ['id' => $this->post->get_id()]));
        $this->assertEquals(1, $post);

        // Gather important parameters.
        $message = 'a new message';

        $time = time();

        // Update the post.
        $this->post->moodleoverflow_edit_post($time, $message, $this->post->messageformat, $this->post->formattachments);

        // The message and modified time should be changed.
        $post = $DB->get_record('moodleoverflow_posts', ['id' => $this->post->get_id()]);
        $this->assertEquals($message,  $post->message);
        $this->assertEquals($time, $post->modified);
    }

    /**
     * Test, if a post and its attachment are deleted successfully.
     * @covers ::moodleoverflow_delete_post
     */
    public function test_moodleoverflow_delete_post(): void {
        global $DB;

        // The post and the attachment should exist.
        $numberofattachments = count($DB->get_records('files', ['itemid' => $this->post->get_id()]));
        $this->assertEquals(2, $numberofattachments); // One Attachment is saved twice in 'files'.
        $post = count($DB->get_records('moodleoverflow_posts', ['id' => $this->post->get_id()]));
        $this->assertEquals(1, $post);

        // Delete the post with its attachment.
        // Save the post id as it gets unsettled by the post object after being deleted.
        $postid = $this->post->get_id();
        $this->post->moodleoverflow_delete_post(true);

        // Now try to get the attachment, it should be deleted from the database.
        $numberofattachments = count($DB->get_records('files', ['itemid' => $postid]));
        $this->assertEquals(0, $numberofattachments);

        // Try to find the post, it should be deleted.
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
        $discussionrecord = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion[0]->id]);
        $this->discussion = discussion::from_record($discussionrecord);

        // Get a temporary post from the DB to add the attachment.
        $temppost = $DB->get_record('moodleoverflow_posts', ['id' => $this->discussion->get_firstpostid()]);

        // Create an attachment by inserting it directly in the database and update the post record.
        $this->add_new_attachment($temppost, $this->modulecontext, 'world.txt', 'hello world');

        // Build the real post object now. That is the object that will be tested.
        $postrecord = $DB->get_record('moodleoverflow_posts', ['id' => $this->discussion->get_firstpostid()]);
        $this->post = post::from_record($postrecord);
    }

    /**
     * Adds a new attachment to a post.
     *
     * @param stdClass $object The post object to which the attachment should be added.
     * @param context $modulecontext The context of the module.
     * @param string $filename The name of the file to be added.
     * @param string $filecontent The content of the file to be added.
     */
    private function add_new_attachment($object, $modulecontext, $filename, $filecontent) {
        global $DB;
        $fileinfo = [
            'contextid' => $modulecontext->id,           // ID of the context.
            'component' => 'mod_moodleoverflow',               // Your component name.
            'filearea'  => 'attachment',                       // Usually = table name.
            'itemid'    => $object->id,                      // Usually = ID of the item (e.g. the post.
            'filepath'  => '/',                                // Any path beginning and ending in /.
            'filename'  => $filename,                      // Any filename.
        ];
        $fs = get_file_storage();
        $fs->create_file_from_string($fileinfo, $filecontent); // Creates a new file containing the text 'hello world'.
        $DB->update_record('moodleoverflow_posts', $object);
    }
}
