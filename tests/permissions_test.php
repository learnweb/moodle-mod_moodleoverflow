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
 * PHP Unit Tests for the permissions class.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\enum\tracking_type;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests if the rules of the permissions class are working correctly.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\permissions
 */
final class permissions_test extends \advanced_testcase {
    /** @var \stdClass test course */
    private $course;

    /** @var \stdClass editing teacher of the course */
    private $teacher;

    /** @var \stdClass student of the course */
    private $student;

    /** @var \stdClass another student of the course */
    private $otherstudent;

    /** @var \stdClass user that is not enrolled in the course */
    private $notenrolled;

    /** @var \mod_moodleoverflow_generator $generator */
    private $generator;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Posts can be reviewed directly after they were created.
        set_config('reviewpossibleaftertime', -3600, 'moodleoverflow');

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->otherstudent = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->notenrolled = $this->getDataGenerator()->create_user();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
    }

    public function tearDown(): void {
        // Clear all caches.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();
        parent::tearDown();
    }

    /**
     * Test, if ensure() throws an exception only if the check failed.
     */
    public function test_ensure(): void {
        permissions::ensure(true, 'cannotreply');
        $this->expectException(\moodle_exception::class);
        permissions::ensure(false, 'cannotreply');
    }

    // View permissions.

    /**
     * Test, if only users with access to the course and the activity can see the moodleoverflow.
     */
    public function test_can_view_moodleoverflow(): void {
        $modflow = $this->helper_create_moodleoverflow();

        $this->assertTrue(permissions::can_view_moodleoverflow($modflow, $this->student->id));
        $this->assertFalse(permissions::can_view_moodleoverflow($modflow, $this->notenrolled->id));
        $this->assertFalse(permissions::can_view_moodleoverflow($modflow, 0));

        // Hide the activity. Only users that can see hidden activities can see it now.
        set_coursemodule_visible($modflow->get_cm()->id, 0);
        $modflow = moodleoverflow::from_id($modflow->id);
        $this->assertFalse(permissions::can_view_moodleoverflow($modflow, $this->student->id));
        $this->assertTrue(permissions::can_view_moodleoverflow($modflow, $this->teacher->id));
    }

    /**
     * Test, if questions that need a review are hidden and their answers are hidden too.
     */
    public function test_can_view_discussion_and_post(): void {
        $modflow = $this->helper_create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $question = $this->helper_create_question($modflow, $this->student);
        $discussion = $question->get_discussion();

        // The question waits for a review. Only the author and the reviewers can see it.
        $this->assertEquals(0, $question->reviewed);
        $this->assertTrue(permissions::can_view_discussion($discussion, $this->student->id));
        $this->assertTrue(permissions::can_view_discussion($discussion, $this->teacher->id));
        $this->assertFalse(permissions::can_view_discussion($discussion, $this->otherstudent->id));
        $this->assertFalse(permissions::can_view_post($question, $this->otherstudent->id));

        // An answer of a reviewer is reviewed automatically, but stays hidden as long as the question is hidden.
        $answer = $this->helper_create_reply($question, $this->teacher);
        $this->assertEquals(1, $answer->reviewed);
        $this->assertFalse(permissions::can_view_post($answer, $this->otherstudent->id));

        // After the approval of the question everyone in the course can see both posts.
        $this->helper_approve($question);
        $question = post::from_id($question->get_id());
        $answer = post::from_id($answer->get_id());
        $this->assertTrue(permissions::can_view_post($question, $this->otherstudent->id));
        $this->assertTrue(permissions::can_view_post($answer, $this->otherstudent->id));
        $this->assertFalse(permissions::can_view_post($answer, $this->notenrolled->id));
    }

    /**
     * Test, if the author of a post is only visible if the anonymity setting allows it.
     */
    public function test_can_view_author(): void {
        global $DB;

        // Only questioners are anonymous. Authors always see their own name.
        $modflow = $this->helper_create_moodleoverflow(['anonymous' => anonymity::QUESTIONS->value]);
        $question = $this->helper_create_question($modflow, $this->student);
        $answer = $this->helper_create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_view_author($question, $this->teacher->id));
        $this->assertTrue(permissions::can_view_author($question, $this->student->id));
        $this->assertTrue(permissions::can_view_author($answer, $this->teacher->id));

        // Everything is anonymous.
        $modflow = $this->helper_create_moodleoverflow(['anonymous' => anonymity::EVERYTHING->value]);
        $question = $this->helper_create_question($modflow, $this->student);
        $answer = $this->helper_create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_view_author($answer, $this->teacher->id));
        $this->assertTrue(permissions::can_view_author($answer, $this->otherstudent->id));

        // A post without an author (the privacy provider sets the userid to 0) has no visible author.
        $modflow = $this->helper_create_moodleoverflow();
        $question = $this->helper_create_question($modflow, $this->student);
        $DB->set_field('moodleoverflow_posts', 'userid', 0, ['id' => $question->get_id()]);
        $question = post::from_id($question->get_id());
        $this->assertFalse(permissions::can_view_author($question, $this->teacher->id));
    }

    /**
     * Test, if only users that can view rating information see the user statistics.
     */
    public function test_can_view_userstats(): void {
        $modflow = $this->helper_create_moodleoverflow();

        set_config('showuserstats', 1, 'moodleoverflow');
        $this->assertTrue(permissions::can_view_userstats($modflow, $this->teacher->id));
        $this->assertFalse(permissions::can_view_userstats($modflow, $this->student->id));

        // The admin setting switches the statistics off for everyone.
        set_config('showuserstats', 0, 'moodleoverflow');
        $this->assertFalse(permissions::can_view_userstats($modflow, $this->teacher->id));
    }

    // Posting permissions.

    /**
     * Test, if users can start discussions, also while the answer window is closed.
     */
    public function test_can_start_discussion(): void {
        $modflow = $this->helper_create_moodleoverflow(['la_starttime' => time() + DAYSECS]);

        $this->assertTrue(permissions::can_start_discussion($modflow, $this->student->id));
        $this->assertFalse(permissions::can_start_discussion($modflow, $this->notenrolled->id));
        $this->assertFalse(permissions::can_start_discussion($modflow, guest_user()->id));
    }

    /**
     * Test, if replies need a reviewed parent and an open answer window.
     */
    public function test_can_reply(): void {
        $modflow = $this->helper_create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $question = $this->helper_create_question($modflow, $this->student);

        // Nobody can reply to an unreviewed post, not even a reviewer.
        $this->assertFalse(permissions::can_reply($question, $this->student->id));
        $this->assertFalse(permissions::can_reply($question, $this->teacher->id));

        // After the approval everyone in the course can reply.
        $this->helper_approve($question);
        $question = post::from_id($question->get_id());
        $this->assertTrue(permissions::can_reply($question, $this->otherstudent->id));
        $this->assertFalse(permissions::can_reply($question, $this->notenrolled->id));

        // Before the answer window opens only teachers can reply, to questions and to answers.
        $modflow = $this->helper_create_moodleoverflow(['la_starttime' => time() + DAYSECS]);
        $question = $this->helper_create_question($modflow, $this->teacher);
        $answer = $this->helper_create_reply($question, $this->teacher);
        $this->assertFalse(permissions::can_reply($question, $this->student->id));
        $this->assertFalse(permissions::can_reply($answer, $this->student->id));
        $this->assertTrue(permissions::can_reply($question, $this->teacher->id));
    }

    /**
     * Test, if authors can edit their posts only in the edit window and before a review.
     */
    public function test_can_edit_post(): void {
        $modflow = $this->helper_create_moodleoverflow();
        $question = $this->helper_create_question($modflow, $this->student);

        $this->assertTrue(permissions::can_edit_post($question, $this->student->id));
        $this->assertFalse(permissions::can_edit_post($question, $this->otherstudent->id));
        $this->assertTrue(permissions::can_edit_post($question, $this->teacher->id));

        // After the edit window only users that can edit any post can edit it.
        set_config('maxeditingtime', -3600, 'moodleoverflow');
        $this->assertFalse(permissions::can_edit_post($question, $this->student->id));
        $this->assertTrue(permissions::can_edit_post($question, $this->teacher->id));
        set_config('maxeditingtime', 3600, 'moodleoverflow');

        // A reviewed question can not be changed by its author anymore.
        $modflow = $this->helper_create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $question = $this->helper_create_question($modflow, $this->student);
        $this->assertTrue(permissions::can_edit_post($question, $this->student->id));
        $this->helper_approve($question);
        $question = post::from_id($question->get_id());
        $this->assertFalse(permissions::can_edit_post($question, $this->student->id));
    }

    /**
     * Test, if authors can only delete their posts as long as they have no replies.
     */
    public function test_can_delete_post(): void {
        $modflow = $this->helper_create_moodleoverflow();
        $question = $this->helper_create_question($modflow, $this->student);

        $this->assertTrue(permissions::can_delete_post($question, $this->student->id));
        $this->assertFalse(permissions::can_delete_post($question, $this->otherstudent->id));

        // With a reply only users that can delete any post can delete it.
        $this->helper_create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_delete_post($question, $this->student->id));
        $this->assertTrue(permissions::can_delete_post($question, $this->teacher->id));
    }

    /**
     * Test, if attachments are only possible if the activity allows them.
     */
    public function test_can_add_attachments(): void {
        $modflow = $this->helper_create_moodleoverflow(['maxattachments' => 2]);
        $this->assertTrue(permissions::can_add_attachments($modflow, $this->student->id));

        $modflow = $this->helper_create_moodleoverflow(['maxattachments' => 0]);
        $this->assertFalse(permissions::can_add_attachments($modflow, $this->student->id));
    }

    // Rating permissions.

    /**
     * Test the rules for votes and marks.
     */
    public function test_ratings(): void {
        $modflow = $this->helper_create_moodleoverflow();
        $question = $this->helper_create_question($modflow, $this->student);
        $answer = $this->helper_create_reply($question, $this->otherstudent);
        $teacheranswer = $this->helper_create_reply($question, $this->teacher);
        $comment = $this->helper_create_reply($answer, $this->teacher);

        // Votes are possible on posts of others, but not on own posts.
        $this->assertTrue(permissions::can_vote($answer, $this->student->id));
        $this->assertFalse(permissions::can_vote($answer, $this->otherstudent->id));

        // Only the questioner marks direct answers as helpful.
        $this->assertTrue(permissions::can_mark_helpful($answer, $this->student->id));
        $this->assertFalse(permissions::can_mark_helpful($answer, $this->teacher->id));
        $this->assertFalse(permissions::can_mark_helpful($comment, $this->student->id));

        // Solved marks need the capability and a direct answer. Teachers may mark their own answer.
        $this->assertTrue(permissions::can_mark_solved($answer, $this->teacher->id));
        $this->assertTrue(permissions::can_mark_solved($teacheranswer, $this->teacher->id));
        $this->assertFalse(permissions::can_mark_solved($comment, $this->teacher->id));

        // Without rating no votes are possible, but marks are.
        $modflow = $this->helper_create_moodleoverflow(['allowrating' => 0]);
        $question = $this->helper_create_question($modflow, $this->student);
        $answer = $this->helper_create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_vote($answer, $this->student->id));
        $this->assertTrue(permissions::can_mark_helpful($answer, $this->student->id));
    }

    // Review permissions.

    /**
     * Test, if only reviewers can review posts and only after the review delay.
     */
    public function test_can_review_post(): void {
        $modflow = $this->helper_create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $question = $this->helper_create_question($modflow, $this->student);

        $this->assertTrue(permissions::can_review_posts($modflow, $this->teacher->id));
        $this->assertFalse(permissions::can_review_posts($modflow, $this->student->id));
        $this->assertTrue(permissions::can_review_post($question, $this->teacher->id));
        $this->assertFalse(permissions::can_review_post($question, $this->student->id));

        // Not before the review delay has passed.
        set_config('reviewpossibleaftertime', 3600, 'moodleoverflow');
        $this->assertFalse(permissions::can_review_post($question, $this->teacher->id));
        set_config('reviewpossibleaftertime', -3600, 'moodleoverflow');

        // A reviewed post can not be reviewed again.
        $this->helper_approve($question);
        $question = post::from_id($question->get_id());
        $this->assertFalse(permissions::can_review_post($question, $this->teacher->id));
    }

    // Discussion permissions.

    /**
     * Test, if discussions can only be moved to suitable moodleoverflows.
     */
    public function test_can_move_discussion(): void {
        $source = $this->helper_create_moodleoverflow();
        $destination = $this->helper_create_moodleoverflow();
        $discussion = $this->helper_create_question($source, $this->student)->get_discussion();

        $this->assertTrue(permissions::can_move_discussion($discussion, $destination, $this->teacher->id));
        $this->assertFalse(permissions::can_move_discussion($discussion, $destination, $this->student->id));
        $this->assertFalse(permissions::can_move_discussion($discussion, $source, $this->teacher->id));

        // Moving must not reveal authors: the destination is at least as anonymous as the source.
        $anonymous = $this->helper_create_moodleoverflow(['anonymous' => anonymity::EVERYTHING->value]);
        $anondiscussion = $this->helper_create_question($anonymous, $this->student)->get_discussion();
        $this->assertTrue(permissions::can_move_discussion($discussion, $anonymous, $this->teacher->id));
        $this->assertFalse(permissions::can_move_discussion($anondiscussion, $destination, $this->teacher->id));

        // Only moodleoverflows of the same course are possible destinations.
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $othercourse->id, 'editingteacher');
        $otherrecord = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $othercourse->id]);
        $other = moodleoverflow::from_id($otherrecord->id);
        $this->assertFalse(permissions::can_move_discussion($discussion, $other, $this->teacher->id));
    }

    // Read tracking permissions (can_track() is tested in readtracking_test).

    /**
     * Test, if users can only change the tracking if the tracking type lets them choose.
     */
    public function test_can_change_tracking(): void {
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');
        $optional = $this->helper_create_moodleoverflow(['trackingtype' => tracking_type::OPTIONAL->value]);
        $forced = $this->helper_create_moodleoverflow(['trackingtype' => tracking_type::FORCED->value]);

        $this->assertTrue(permissions::can_change_tracking($optional, $this->student->id));
        $this->assertFalse(permissions::can_change_tracking($forced, $this->student->id));
        $this->assertFalse(permissions::can_change_tracking($optional, guest_user()->id));
    }

    // Helper functions.

    /**
     * Creates a moodleoverflow in the test course.
     * @param array $options Instance settings that differ from the default.
     * @return moodleoverflow
     */
    private function helper_create_moodleoverflow(array $options = []): moodleoverflow {
        $record = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $this->course->id] + $options);
        return moodleoverflow::from_id($record->id);
    }

    /**
     * Creates a discussion and returns its question.
     * @param moodleoverflow $modflow
     * @param \stdClass $author
     * @return post
     */
    private function helper_create_question(moodleoverflow $modflow, \stdClass $author): post {
        [, $post] = $this->generator->post_to_forum($modflow, $author);
        return post::from_id($post->id);
    }

    /**
     * Creates a reply to a post.
     * @param post $parent
     * @param \stdClass $author
     * @return post
     */
    private function helper_create_reply(post $parent, \stdClass $author): post {
        $parentrecord = (object) ['discussion' => $parent->get_discussionid(), 'id' => $parent->get_id()];
        $record = $this->generator->reply_to_post($parentrecord, $author);
        return post::from_id($record->id);
    }

    /**
     * Marks a post as reviewed.
     * @param post $post
     */
    private function helper_approve(post $post): void {
        global $DB;
        $DB->set_field('moodleoverflow_posts', 'reviewed', 1, ['id' => $post->get_id()]);
    }
}
