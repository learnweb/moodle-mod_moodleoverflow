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
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\enum\tracking_type;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

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
    use moodleoverflow_test_trait;

    /** @var \stdClass user that is not enrolled in the course */
    private $notenrolled;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Posts can be reviewed directly after they were created.
        set_config('reviewpossibleaftertime', -3600, 'moodleoverflow');

        $this->setup_course();
        $this->notenrolled = $this->getDataGenerator()->create_user();
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
        $modflow = $this->create_moodleoverflow();

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
     * Test, if a user with a suspended enrolment can not see the moodleoverflow; the mail cron relies on it.
     */
    public function test_can_view_moodleoverflow_suspended_enrolment(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow();

        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($instance, $this->student->id, ENROL_USER_SUSPENDED);
        $this->assertFalse(permissions::can_view_moodleoverflow($modflow, $this->student->id));
        $this->assertTrue(permissions::can_view_moodleoverflow($modflow, $this->otherstudent->id));
    }

    /**
     * Test, if only users that can see hidden courses can see a moodleoverflow in a hidden course.
     */
    public function test_can_view_moodleoverflow_hidden_course(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow();

        $DB->set_field('course', 'visible', 0, ['id' => $this->course->id]);
        rebuild_course_cache($this->course->id, true);
        $modflow = moodleoverflow::from_id($modflow->id);
        $this->assertFalse(permissions::can_view_moodleoverflow($modflow, $this->student->id));
        $this->assertTrue(permissions::can_view_moodleoverflow($modflow, $this->teacher->id));
    }

    /**
     * Test, if only users that ignore availability restrictions can see a moodleoverflow that is not available yet.
     */
    public function test_can_view_moodleoverflow_availability(): void {
        global $DB;
        set_config('enableavailability', 1);
        $modflow = $this->create_moodleoverflow();

        $availability = \core_availability\tree::get_root_json([
            \availability_date\condition::get_json('>=', time() + DAYSECS),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $modflow->get_cm()->id]);
        rebuild_course_cache($this->course->id, true);
        $modflow = moodleoverflow::from_id($modflow->id);
        $this->assertFalse(permissions::can_view_moodleoverflow($modflow, $this->student->id));
        $this->assertTrue(permissions::can_view_moodleoverflow($modflow, $this->teacher->id));
    }

    /**
     * Test, if a user without the capability to view discussions can not see the moodleoverflow.
     */
    public function test_can_view_moodleoverflow_without_viewdiscussion(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow();

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/moodleoverflow:viewdiscussion', CAP_PROHIBIT, $studentrole, $modflow->get_context()->id);
        $this->assertFalse(permissions::can_view_moodleoverflow($modflow, $this->student->id));
        $this->assertTrue(permissions::can_view_moodleoverflow($modflow, $this->teacher->id));
    }

    /**
     * Test, if questions that need a review are hidden and their answers are hidden too.
     */
    public function test_can_view_discussion_and_post(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $discussion = $question->get_discussion();

        // The question waits for a review. Only the author and the reviewers can see it.
        $this->assertEquals(0, $question->reviewed);
        $this->assertTrue(permissions::can_view_discussion($discussion, $this->student->id));
        $this->assertTrue(permissions::can_view_discussion($discussion, $this->teacher->id));
        $this->assertFalse(permissions::can_view_discussion($discussion, $this->otherstudent->id));
        $this->assertFalse(permissions::can_view_post($question, $this->otherstudent->id));

        // An answer of a reviewer is reviewed automatically, but stays hidden as long as the question is hidden.
        $answer = $this->generator->create_reply($question, $this->teacher);
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
        $modflow = $this->create_moodleoverflow(['anonymous' => anonymity::QUESTIONS->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_view_author($question, $this->teacher->id));
        $this->assertTrue(permissions::can_view_author($question, $this->student->id));
        $this->assertTrue(permissions::can_view_author($answer, $this->teacher->id));

        // Everything is anonymous.
        $modflow = $this->create_moodleoverflow(['anonymous' => anonymity::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_view_author($answer, $this->teacher->id));
        $this->assertTrue(permissions::can_view_author($answer, $this->otherstudent->id));

        // A post without an author (the privacy provider sets the userid to 0) has no visible author.
        $modflow = $this->create_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student);
        $DB->set_field('moodleoverflow_posts', 'userid', 0, ['id' => $question->get_id()]);
        $question = post::from_id($question->get_id());
        $this->assertFalse(permissions::can_view_author($question, $this->teacher->id));
    }

    /**
     * Test, if only users that can view rating information see the user statistics.
     */
    public function test_can_view_userstats(): void {
        $modflow = $this->create_moodleoverflow();

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
        $modflow = $this->create_moodleoverflow(['la_starttime' => time() + DAYSECS]);

        $this->assertTrue(permissions::can_start_discussion($modflow, $this->student->id));
        $this->assertFalse(permissions::can_start_discussion($modflow, $this->notenrolled->id));
        $this->assertFalse(permissions::can_start_discussion($modflow, guest_user()->id));
    }

    /**
     * Test, if replies need a reviewed parent and an open answer window.
     */
    public function test_can_reply(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $question = $this->generator->create_question($modflow, $this->student);

        // Nobody can reply to an unreviewed post, not even a reviewer.
        $this->assertFalse(permissions::can_reply($question, $this->student->id));
        $this->assertFalse(permissions::can_reply($question, $this->teacher->id));

        // After the approval everyone in the course can reply.
        $this->helper_approve($question);
        $question = post::from_id($question->get_id());
        $this->assertTrue(permissions::can_reply($question, $this->otherstudent->id));
        $this->assertFalse(permissions::can_reply($question, $this->notenrolled->id));

        // Before the answer window opens only teachers can reply, to questions and to answers.
        $modflow = $this->create_moodleoverflow(['la_starttime' => time() + DAYSECS]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->teacher);
        $this->assertFalse(permissions::can_reply($question, $this->student->id));
        $this->assertFalse(permissions::can_reply($answer, $this->student->id));
        $this->assertTrue(permissions::can_reply($question, $this->teacher->id));
    }

    /**
     * Test, if authors can edit their posts only in the edit window and before a review.
     */
    public function test_can_edit_post(): void {
        $modflow = $this->create_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student);

        $this->assertTrue(permissions::can_edit_post($question, $this->student->id));
        $this->assertFalse(permissions::can_edit_post($question, $this->otherstudent->id));
        $this->assertTrue(permissions::can_edit_post($question, $this->teacher->id));

        // After the edit window only users that can edit any post can edit it.
        set_config('maxeditingtime', -3600, 'moodleoverflow');
        $this->assertFalse(permissions::can_edit_post($question, $this->student->id));
        $this->assertTrue(permissions::can_edit_post($question, $this->teacher->id));
        set_config('maxeditingtime', 3600, 'moodleoverflow');

        // A reviewed question can not be changed by its author anymore.
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $this->assertTrue(permissions::can_edit_post($question, $this->student->id));
        $this->helper_approve($question);
        $question = post::from_id($question->get_id());
        $this->assertFalse(permissions::can_edit_post($question, $this->student->id));
    }

    /**
     * Test, if authors can only delete their posts as long as they have no replies.
     */
    public function test_can_delete_post(): void {
        $modflow = $this->create_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student);

        $this->assertTrue(permissions::can_delete_post($question, $this->student->id));
        $this->assertFalse(permissions::can_delete_post($question, $this->otherstudent->id));

        // With a reply only users that can delete any post can delete it.
        $this->generator->create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_delete_post($question, $this->student->id));
        $this->assertTrue(permissions::can_delete_post($question, $this->teacher->id));
    }

    /**
     * Test, if attachments are only possible if the activity allows them.
     */
    public function test_can_add_attachments(): void {
        $modflow = $this->create_moodleoverflow(['maxattachments' => 2]);
        $this->assertTrue(permissions::can_add_attachments($modflow, $this->student->id));

        $modflow = $this->create_moodleoverflow(['maxattachments' => 0]);
        $this->assertFalse(permissions::can_add_attachments($modflow, $this->student->id));
    }

    // Rating permissions.

    /**
     * Test the rules for votes and marks.
     */
    public function test_ratings(): void {
        $modflow = $this->create_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);
        $teacheranswer = $this->generator->create_reply($question, $this->teacher);
        $comment = $this->generator->create_reply($answer, $this->teacher);

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
        $modflow = $this->create_moodleoverflow(['allowrating' => 0]);
        $question = $this->generator->create_question($modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);
        $this->assertFalse(permissions::can_vote($answer, $this->student->id));
        $this->assertTrue(permissions::can_mark_helpful($answer, $this->student->id));
    }

    // Review permissions.

    /**
     * Test, if only reviewers can review posts and only after the review delay.
     */
    public function test_can_review_post(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->student);

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
        $source = $this->create_moodleoverflow();
        $destination = $this->create_moodleoverflow();
        $discussion = $this->generator->create_question($source, $this->student)->get_discussion();

        $this->assertTrue(permissions::can_move_discussion($discussion, $destination, $this->teacher->id));
        $this->assertFalse(permissions::can_move_discussion($discussion, $destination, $this->student->id));
        $this->assertFalse(permissions::can_move_discussion($discussion, $source, $this->teacher->id));

        // Moving must not reveal authors: the destination is at least as anonymous as the source.
        $anonymous = $this->create_moodleoverflow(['anonymous' => anonymity::EVERYTHING->value]);
        $anondiscussion = $this->generator->create_question($anonymous, $this->student)->get_discussion();
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
        $optional = $this->create_moodleoverflow(['trackingtype' => tracking_type::OPTIONAL->value]);
        $forced = $this->create_moodleoverflow(['trackingtype' => tracking_type::FORCED->value]);

        $this->assertTrue(permissions::can_change_tracking($optional, $this->student->id));
        $this->assertFalse(permissions::can_change_tracking($forced, $this->student->id));
        $this->assertFalse(permissions::can_change_tracking($optional, guest_user()->id));
    }

    // Subscription permissions.

    /**
     * Test, if only users that can see the moodleoverflow can be subscribed, and nobody if subscriptions are disabled.
     */
    public function test_can_subscribe(): void {
        $optional = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::OPTIONAL->value]);
        $disabled = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::DISABLED->value]);

        // Admins do not need an enrolment to subscribe themselves.
        $this->assertTrue(permissions::can_subscribe($optional, $this->student->id));
        $this->assertTrue(permissions::can_subscribe($optional, get_admin()->id));
        $this->assertFalse(permissions::can_subscribe($optional, $this->notenrolled->id));
        $this->assertFalse(permissions::can_subscribe($optional, guest_user()->id));
        $this->assertFalse(permissions::can_subscribe($optional, 0));

        // Not even teachers and admins can be subscribed if subscriptions are disabled.
        $this->assertFalse(permissions::can_subscribe($disabled, $this->teacher->id));
        $this->assertFalse(permissions::can_subscribe($disabled, get_admin()->id));
    }

    /**
     * Test, in which modes a student (subscribed by default) and a manager (not subscribed by default) can change their
     * subscription.
     * @dataProvider can_change_subscription_provider
     * @param subscription_mode $mode
     * @param bool $expectstudent
     * @param bool $expectmanager
     */
    public function test_can_change_subscription(subscription_mode $mode, bool $expectstudent, bool $expectmanager): void {
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => $mode->value]);
        $manager = $this->getDataGenerator()->create_and_enrol($this->course, 'manager');

        $this->assertEquals($expectstudent, permissions::can_change_subscription($modflow, $this->student->id));
        $this->assertEquals($expectmanager, permissions::can_change_subscription($modflow, $manager->id));
    }

    /**
     * Subscription modes and whether a student and a manager can change their subscription.
     * @return array
     */
    public static function can_change_subscription_provider(): array {
        return [
            'optional' => [subscription_mode::OPTIONAL, true, true],
            'forced' => [subscription_mode::FORCED, false, true],
            'auto' => [subscription_mode::AUTO, true, true],
            'disabled' => [subscription_mode::DISABLED, false, false],
        ];
    }

    /**
     * Test, if users can only change the subscription of discussions they can see, and not as default subscribers of a
     * forced moodleoverflow.
     */
    public function test_can_change_discussion_subscription(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $discussion = $this->generator->create_question($modflow, $this->student)->get_discussion();

        // The question waits for a review, so only its author and the reviewers can subscribe to it.
        $this->assertTrue(permissions::can_change_discussion_subscription($discussion, $this->student->id));
        $this->assertTrue(permissions::can_change_discussion_subscription($discussion, $this->teacher->id));
        $this->assertFalse(permissions::can_change_discussion_subscription($discussion, $this->otherstudent->id));

        // Default subscribers of a forced moodleoverflow cannot unsubscribe from single discussions.
        $forced = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::FORCED->value]);
        $discussion = $this->generator->create_question($forced, $this->teacher)->get_discussion();
        $this->assertFalse(permissions::can_change_discussion_subscription($discussion, $this->student->id));
    }

    /**
     * Test, if only actively enrolled users with the capability allowforcesubscribe are subscribed by default.
     */
    public function test_is_default_subscriber(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow();
        $manager = $this->getDataGenerator()->create_and_enrol($this->course, 'manager');

        $this->assertTrue(permissions::is_default_subscriber($modflow, $this->student->id));
        $this->assertTrue(permissions::is_default_subscriber($modflow, $this->teacher->id));
        $this->assertFalse(permissions::is_default_subscriber($modflow, $manager->id));
        $this->assertFalse(permissions::is_default_subscriber($modflow, get_admin()->id));

        // A suspended enrolment does not count.
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($instance, $this->student->id, ENROL_USER_SUSPENDED);
        $this->assertFalse(permissions::is_default_subscriber($modflow, $this->student->id));
    }

    // Helper functions.

    /**
     * Marks a post as reviewed.
     * @param post $post
     */
    private function helper_approve(post $post): void {
        global $DB;
        $DB->set_field('moodleoverflow_posts', 'reviewed', 1, ['id' => $post->get_id()]);
    }
}
