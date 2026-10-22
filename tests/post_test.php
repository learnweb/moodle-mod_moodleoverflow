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

use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../locallib.php');

/**
 *
 * Tests if the functions from the post class are working correctly.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\models\post
 */
final class post_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var discussion a discussion of the student */
    private $discussion;

    /** @var post the question of the discussion, with an attachment */
    private $post;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
        $question = $this->generator->create_question($this->create_moodleoverflow(), $this->student);
        $this->generator->create_post_file($question, 'attachment', 'world.txt', 'hello world');
        // Load the post again to see the attachment flag.
        $this->post = post::from_id($question->get_id());
        $this->discussion = $this->post->get_discussion();
    }

    /**
     * Test, if a post is being created correctly
     */
    public function test_create_post(): void {
        global $DB;

        // Log in as the student.
        $this->setUser($this->student);

        // Build a new post object.
        $time = time();
        $message = 'a unique message';
        $post = post::construct_without_id(
            $this->discussion->get_id(),
            $this->post->get_id(),
            $this->student->id,
            $time,
            $time,
            $message,
            0,
            '',
            0,
            1,
            null
        );
        $post->add();

        // The post should be in the database.
        $postscount = count($DB->get_records('moodleoverflow_posts', ['id' => $post->get_id()]));
        $this->assertEquals(1, $postscount);
    }

    /**
     * Test, if the message of a post can be edited successfully.
     */
    public function test_edit_post(): void {
        global $DB;

        // Log in as the student.
        $this->setUser($this->student);

        // The post and the attachment should exist.
        $numberofattachments = count($DB->get_records('files', ['itemid' => $this->post->get_id()]));
        $this->assertEquals(2, $numberofattachments); // One Attachment is saved twice in 'files'.
        $post = count($DB->get_records('moodleoverflow_posts', ['id' => $this->post->get_id()]));
        $this->assertEquals(1, $post);

        // Gather important parameters.
        $message = 'a new message';

        $before = time();

        // Update the post.
        $this->post->edit($this->student->id, new post_content($message, $this->post->messageformat));

        // The message and modified time should be changed.
        $post = $DB->get_record('moodleoverflow_posts', ['id' => $this->post->get_id()]);
        $this->assertEquals($message, $post->message);
        $this->assertGreaterThanOrEqual($before, $post->modified);
    }

    /**
     * Test, if a post and its attachment are deleted successfully.
     * @covers \mod_moodleoverflow\local\models\post::delete
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
        $this->post->delete(true, $this->student->id);

        // Now try to get the attachment, it should be deleted from the database.
        $numberofattachments = count($DB->get_records('files', ['itemid' => $postid]));
        $this->assertEquals(0, $numberofattachments);

        // Try to find the post, it should be deleted.
        $post = count($DB->get_records('moodleoverflow_posts', ['id' => $postid]));
        $this->assertEquals(0, $post);
    }
}
