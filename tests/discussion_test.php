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

use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\service\posting;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

/**
 * Tests if the functions from the discussion class are working correctly.
 * As the discussion class works as an administrator of the post class, most of the testcases are already realized in the
 * post_test.php file.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\models\discussion
 */
final class discussion_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var moodleoverflow test moodleoverflow */
    private $modflow;

    /** @var discussion a discussion of the student */
    private $discussion;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
        $this->modflow = $this->create_moodleoverflow();
        $this->discussion = $this->generator->create_question($this->modflow, $this->student)->get_discussion();
    }

    /**
     * Test, if a discussion is being created correctly
     */
    public function test_create_discussion(): void {
        global $DB;
        // Log in as the student.
        $this->setUser($this->student);

        // Build a new discussion object.
        $postcontent = new post_content(
            'a message',
            FORMAT_HTML,
            null,
            null,
            'Discussion Topic'
        );
        $discussion = posting::create_discussion($this->modflow, $this->student->id, $postcontent);
        $posts = $discussion->get_posts();
        $post = $posts[$discussion->get_firstpostid()];

        // The discussion and the firstpost should be in the DB.
        $dbdiscussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->get_id()]);
        $this->assertEquals($dbdiscussion->id, $discussion->get_id());
        $this->assertEquals('Discussion Topic', $dbdiscussion->name);

        $dbpost = $DB->get_record('moodleoverflow_posts', ['id' => $discussion->get_firstpostid()]);
        $this->assertEquals($dbpost->id, $post->get_id());
        $this->assertEquals($dbpost->discussion, $post->get_discussionid());
        $this->assertEquals('a message', $dbpost->message);
    }

    /**
     * Test, if a post and its attachment are deleted successfully.
     * @covers \mod_moodleoverflow\local\models\discussion::delete_post_from_discussion
     */
    public function test_delete_discussion(): void {
        global $DB;
        // Build the prepost object with necessary information.
        $prepost = new \stdClass();
        $prepost->modulecontext = $this->modflow->get_context();

        // Delete the discussion, but save the IDs first.
        $discussionid = $this->discussion->get_id();
        $postid = $this->discussion->get_firstpostid();
        $this->discussion->delete($prepost, $this->student->id);

        // The discussion and the post should not be in the DB anymore.
        $discussion = count($DB->get_records('moodleoverflow_discussions', ['id' => $discussionid]));
        $this->assertEquals(0, $discussion);

        $post = count($DB->get_records('moodleoverflow_posts', ['id' => $postid]));
        $this->assertEquals(0, $post);
    }

    /**
     * Test, if the last post is the reviewed post that joined or was edited last, and null without a reviewed post.
     */
    public function test_get_last_post(): void {
        global $DB;
        $question = $this->generator->create_question($this->modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        $unreviewed = $this->generator->create_reply($question, $this->student);
        $approved = $this->generator->create_reply($question, $this->otherstudent);
        $this->set_post_times($question, 100);
        $this->set_post_times($answer, 200);
        $this->set_post_times($unreviewed, 400, null, 0);
        // Written before the answer, but approved after it.
        $this->set_post_times($approved, 150, 300);

        $discussion = discussion::from_id($question->get_discussionid());
        $this->assertEquals($approved->get_id(), $discussion->get_last_post()->get_id());
        $this->assertEquals(300, $discussion->get_last_post()->get_time_active());

        // An edit makes the answer the last post.
        $DB->set_field('moodleoverflow_posts', 'modified', 500, ['id' => $answer->get_id()]);
        $discussion = discussion::from_id($question->get_discussionid());
        $this->assertEquals($answer->get_id(), $discussion->get_last_post()->get_id());

        // A discussion whose question waits for its review has no last post and keeps the values from its creation.
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $waiting = $this->generator->create_question($modflow, $this->student);
        $this->assertNull($waiting->get_discussion()->get_last_post());
        $this->assert_last_post($waiting->get_discussionid(), $waiting->created, $this->student->id);
    }

    /**
     * Test, if an unreviewed reply does not change the last post of the discussion.
     */
    public function test_unreviewed_reply_is_no_last_post(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);

        $this->generator->create_reply($question, $this->student);

        $this->assert_last_post($question->get_discussionid(), $question->modified, $this->teacher->id);
    }

    /**
     * Test, if editing a post makes it the last post with the editor as the user, but only once the post is reviewed.
     */
    public function test_edit_sets_editor_of_reviewed_post(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        // Both posts were written in the past, so the edits are the newest activity.
        $this->set_post_times($question, 100);
        $this->set_post_times($answer, 100, null, 0);

        // An edit of the unreviewed answer is no activity.
        $discussion = discussion::from_id($question->get_discussionid());
        $discussion->edit_post($answer, $this->student->id, $this->get_edit_content($answer));
        $this->assert_last_post($question->get_discussionid(), 100, $this->teacher->id);

        // Once reviewed, an edit by another user makes the answer the last post, with the editor as the user.
        post::from_id($answer->get_id())->mark_reviewed();
        $discussion = discussion::from_id($question->get_discussionid());
        $discussion->edit_post($answer, $this->otherstudent->id, $this->get_edit_content($answer));
        $edited = post::from_id($answer->get_id());
        $this->assert_last_post($question->get_discussionid(), $edited->get_time_active(), $this->otherstudent->id);
    }

    /**
     * Test, if deleting the last post falls back to the previous post and its author.
     */
    public function test_delete_of_last_post_falls_back(): void {
        $question = $this->generator->create_question($this->modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);

        $discussion = discussion::from_id($question->get_discussionid());
        $discussion->delete_post_from_discussion(
            (object) ['postid' => $answer->get_id(), 'deletechildren' => true],
            $this->teacher->id
        );

        $this->assert_last_post($question->get_discussionid(), $question->modified, $this->student->id);
    }

    /**
     * Sets the times and the review status of a post in the database.
     *
     * @param post $post
     * @param int $modified Also used as the creation time.
     * @param int|null $timereviewed
     * @param int $reviewed
     */
    private function set_post_times(post $post, int $modified, ?int $timereviewed = null, int $reviewed = 1): void {
        global $DB;
        $DB->update_record('moodleoverflow_posts', (object) [
            'id' => $post->get_id(),
            'created' => $modified,
            'modified' => $modified,
            'timereviewed' => $timereviewed,
            'reviewed' => $reviewed,
        ]);
    }

    /**
     * Builds the content that edits a post without changing its message.
     *
     * @param post $post
     * @return post_content
     */
    private function get_edit_content(post $post): post_content {
        return new post_content($post->message, $post->messageformat);
    }

    /**
     * Asserts the last post of a discussion as stored in the database.
     *
     * @param int $discussionid
     * @param int $time The expected timemodified.
     * @param int $userid The expected usermodified.
     */
    private function assert_last_post(int $discussionid, int $time, int $userid): void {
        global $DB;
        $record = $DB->get_record('moodleoverflow_discussions', ['id' => $discussionid], '*', MUST_EXIST);
        $this->assertEquals($time, $record->timemodified);
        $this->assertEquals($userid, $record->usermodified);
    }
}
