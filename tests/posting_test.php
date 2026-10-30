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

namespace mod_moodleoverflow;

use mod_moodleoverflow\event\discussion_created;
use mod_moodleoverflow\event\post_created;
use mod_moodleoverflow\event\post_updated;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\local\service\posting;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the posting service: start a discussion, reply, edit and delete.
 *
 * The permission rules themselves are tested in permissions_test; here only that the service applies them.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\posting
 */
#[CoversClass(posting::class)]
final class posting_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var moodleoverflow A moodleoverflow without review. */
    private $modflow;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
        $this->modflow = $this->create_moodleoverflow();
    }

    // Create a discussion.

    /**
     * Test, if starting a discussion saves the question, subscribes the author, marks the question read and fires
     * only discussion_created.
     */
    public function test_create_discussion(): void {
        $sink = $this->redirectEvents();

        $discussion = posting::create_discussion($this->modflow, $this->student->id, $this->content('Question', ' Topic '));

        $question = $discussion->get_first_post();
        $this->assertEquals('Topic', $this->get_discussion_name($discussion->get_id()));
        $this->assertEquals('Question', $question->message);
        $this->assertEquals($this->student->id, $question->get_userid());
        $this->assertEquals(1, $question->reviewed);
        $this->assert_subscribed_and_read($question, $this->student->id);

        $events = $this->get_event_classes($sink->get_events());
        $this->assertContains(discussion_created::class, $events);
        $this->assertNotContains(post_created::class, $events);
    }

    /**
     * Test, if a question of a student waits for review, but not the question of a reviewer. Only the reviewed
     * question is announced to the subscribers now, the other one once it is approved.
     */
    public function test_create_discussion_review_status(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);

        $student = posting::create_discussion($modflow, $this->student->id, $this->content('Question', 'Topic'));
        $teacher = posting::create_discussion($modflow, $this->teacher->id, $this->content('Question', 'Topic'));

        $this->assertEquals(0, $student->get_first_post()->reviewed);
        $this->assertEquals(1, $teacher->get_first_post()->reviewed);
        $this->assertEquals([$teacher->get_first_post()->get_id()], $this->get_queued_notifications());
    }

    /**
     * Test, if the attachments of the question are saved from the draft area of the user.
     */
    public function test_create_discussion_saves_attachments(): void {
        $this->setUser($this->student);
        $draftid = $this->create_draft_file();

        $content = new post_content('Question', FORMAT_HTML, null, $draftid, 'Topic');
        $question = posting::create_discussion($this->modflow, $this->student->id, $content)->get_first_post();

        $files = get_file_storage()->get_area_files(
            $this->modflow->get_context()->id,
            'mod_moodleoverflow',
            'attachment',
            $question->get_id(),
            'id',
            false
        );
        $this->assertCount(1, $files);
    }

    /**
     * Test, if a discussion is refused without a subject or without the permission.
     */
    public function test_create_discussion_refused(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $this->assert_refused(
            fn() => posting::create_discussion($this->modflow, $outsider->id, $this->content('Question', 'Topic')),
            'nopostmoodleoverflow'
        );

        $this->expectException(\coding_exception::class);
        posting::create_discussion($this->modflow, $this->student->id, $this->content('Question', '  '));
    }

    // Reply.

    /**
     * Test, if a reply is saved with its parent, subscribes and marks it read for the author and fires post_created.
     */
    public function test_reply(): void {
        $question = $this->generator->create_question($this->modflow, $this->teacher);
        $sink = $this->redirectEvents();

        $answer = posting::reply($question, $this->student->id, $this->content('Answer', 'Ignored'));

        $this->assertEquals($question->get_id(), $answer->get_parentid());
        $this->assertEquals($this->student->id, $answer->get_userid());
        $this->assertEquals('Answer', $answer->message);
        $this->assert_subscribed_and_read($answer, $this->student->id);
        $this->assertContains(post_created::class, $this->get_event_classes($sink->get_events()));
    }

    /**
     * Test, if replies follow the review level for answers, not the one for questions. Only reviewed replies are
     * announced to the subscribers now.
     */
    public function test_reply_review_status(): void {
        $questionsonly = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $everything = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);

        $question = $this->generator->create_question($questionsonly, $this->teacher);
        $reviewed = posting::reply($question, $this->student->id, $this->content('Answer'));
        $this->assertEquals(1, $reviewed->reviewed);

        $question = $this->generator->create_question($everything, $this->teacher);
        $this->assertEquals(0, posting::reply($question, $this->student->id, $this->content('Answer'))->reviewed);
        $byreviewer = posting::reply($question, $this->teacher->id, $this->content('Answer'));
        $this->assertEquals(1, $byreviewer->reviewed);

        $this->assertEquals([$reviewed->get_id(), $byreviewer->get_id()], $this->get_queued_notifications());
    }

    /**
     * Test, if a reply to a post that waits for review is refused.
     */
    public function test_reply_refused(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $question = $this->generator->create_question($modflow, $this->student);

        $this->assert_refused(
            fn() => posting::reply($question, $this->otherstudent->id, $this->content('Answer')),
            'cannotreply'
        );
    }

    // Edit.

    /**
     * Test, if editing the question changes the message and renames the discussion, and the event names the author.
     */
    public function test_edit_question(): void {
        $question = $this->generator->create_question($this->modflow, $this->student);
        $sink = $this->redirectEvents();

        $edited = posting::edit($question, $this->teacher->id, $this->content('New message', 'New topic'));

        $this->assertEquals('New message', $edited->message);
        $this->assertEquals('New topic', $this->get_discussion_name($question->get_discussionid()));
        $events = array_values(array_filter($sink->get_events(), fn($event) => $event instanceof post_updated));
        $this->assertCount(1, $events);
        $this->assertEquals($this->student->id, $events[0]->relateduserid);
    }

    /**
     * Test, if the subject of an answer is ignored and the question can not lose its subject.
     */
    public function test_edit_subject(): void {
        $question = $this->generator->create_question($this->modflow, $this->student, ['name' => 'Topic']);
        $answer = $this->generator->create_reply($question, $this->student);

        posting::edit($answer, $this->student->id, $this->content('New answer', 'Other topic'));
        $this->assertEquals('Topic', $this->get_discussion_name($question->get_discussionid()));

        $this->expectException(\coding_exception::class);
        posting::edit($question, $this->student->id, $this->content('New message'));
    }

    /**
     * Test, if a student can not edit the post of another student.
     */
    public function test_edit_refused(): void {
        $question = $this->generator->create_question($this->modflow, $this->student);

        $this->assert_refused(
            fn() => posting::edit($question, $this->otherstudent->id, $this->content('New message', 'Topic')),
            'cannotupdatepost'
        );
    }

    // Delete.

    /**
     * Test, if deleting an answer deletes its comments, ratings and files, but keeps the discussion.
     */
    public function test_delete_answer(): void {
        global $DB;
        $question = $this->generator->create_question($this->modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);
        $comment = $this->generator->create_reply($answer, $this->student);
        $this->generator->create_rating($answer, $this->student, rating_type::UPVOTE);
        $this->generator->create_post_file($answer, 'attachment');
        $this->generator->create_post_file($answer, 'post');
        // Deleting clears the ids of the deleted objects, so save them first.
        $answerid = $answer->get_id();
        $commentid = $comment->get_id();

        $this->assertFalse(posting::delete($answer, $this->teacher->id));

        $this->assertTrue($DB->record_exists('moodleoverflow_posts', ['id' => $question->get_id()]));
        $this->assertFalse($DB->record_exists('moodleoverflow_posts', ['id' => $answerid]));
        $this->assertFalse($DB->record_exists('moodleoverflow_posts', ['id' => $commentid]));
        $this->assertFalse($DB->record_exists('moodleoverflow_ratings', ['postid' => $answerid]));
        $this->assertFalse($DB->record_exists('files', ['component' => 'mod_moodleoverflow', 'itemid' => $answerid]));
    }

    /**
     * Test, if deleting the question deletes the discussion with its subscriptions and digest queue rows.
     */
    public function test_delete_question(): void {
        global $DB;
        $discussion = posting::create_discussion($this->modflow, $this->student->id, $this->content('Question', 'Topic'));
        $questionid = $discussion->get_first_post()->get_id();
        $DB->insert_record('moodleoverflow_digest_queue', ['userid' => $this->teacher->id, 'postid' => $questionid]);
        // Deleting clears the id of the discussion, so save it first.
        $discussionid = $discussion->get_id();

        $this->assertTrue(posting::delete($discussion->get_first_post(), $this->teacher->id));

        $this->assertFalse($DB->record_exists('moodleoverflow_discussions', ['id' => $discussionid]));
        $this->assertFalse($DB->record_exists('moodleoverflow_posts', ['discussion' => $discussionid]));
        $this->assertFalse($DB->record_exists('moodleoverflow_discuss_subs', ['discussion' => $discussionid]));
        $this->assertFalse($DB->record_exists('moodleoverflow_digest_queue', ['postid' => $questionid]));
    }

    /**
     * Test, if the author of a deleted answer loses the grade from its votes.
     */
    public function test_delete_updates_grades(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow(['grademaxgrade' => 50, 'gradescalefactor' => 1]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        service\rating::vote($answer, $this->otherstudent->id, rating_type::UPVOTE);
        $params = ['moodleoverflowid' => $modflow->id, 'userid' => $this->student->id];
        $this->assertGreaterThan(0, $DB->get_field('moodleoverflow_grades', 'grade', $params));

        posting::delete($answer, $this->teacher->id);

        $this->assertEquals(0, (float) $DB->get_field('moodleoverflow_grades', 'grade', $params));
    }

    /**
     * Test, if a student can not delete the post of another student.
     */
    public function test_delete_refused(): void {
        $question = $this->generator->create_question($this->modflow, $this->student);

        $this->assert_refused(fn() => posting::delete($question, $this->otherstudent->id), 'cannotdeletepost');
    }

    // Helpers.

    /**
     * Builds the content of a post without files.
     *
     * @param string $message
     * @param string|null $subject
     * @return post_content
     */
    private function content(string $message, ?string $subject = null): post_content {
        return new post_content($message, FORMAT_HTML, null, null, $subject);
    }

    /**
     * Creates a file in a new draft area of the current user.
     *
     * @return int The draft item id.
     */
    private function create_draft_file(): int {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'file.txt',
        ], 'content');
        return $draftid;
    }

    /**
     * Returns the stored name of a discussion.
     *
     * @param int $discussionid
     * @return string
     */
    private function get_discussion_name(int $discussionid): string {
        global $DB;
        return $DB->get_field('moodleoverflow_discussions', 'name', ['id' => $discussionid], MUST_EXIST);
    }

    /**
     * Asserts that the author of a new post is subscribed to the discussion and has read the post.
     *
     * @param post $post
     * @param int $userid
     */
    private function assert_subscribed_and_read(post $post, int $userid): void {
        global $DB;
        $this->assertTrue($DB->record_exists('moodleoverflow_discuss_subs', [
            'discussion' => $post->get_discussionid(),
            'userid' => $userid,
        ]));
        $this->assertTrue($DB->record_exists('moodleoverflow_read', ['postid' => $post->get_id(), 'userid' => $userid]));
    }

    /**
     * Asserts that an action is refused with the given error code.
     *
     * @param callable $action
     * @param string $errorcode
     */
    private function assert_refused(callable $action, string $errorcode): void {
        try {
            $action();
            $this->fail('The action was not refused.');
        } catch (\moodle_exception $e) {
            $this->assertEquals($errorcode, $e->errorcode);
        }
    }

    /**
     * Returns the class names of events.
     *
     * @param array $events
     * @return string[]
     */
    private function get_event_classes(array $events): array {
        return array_map(fn($event) => get_class($event), $events);
    }
}
