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
 * @package    mod_moodleoverflow
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use mod_moodleoverflow\external\review_approve_post;
use mod_moodleoverflow\external\review_reject_post;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\manager\mail_manager;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\task\send_mails;
use mod_moodleoverflow\task\send_review_mails;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');

/**
 * PHPUnit tests for reviewing posts: the review service, its externals and the review mails.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @group mod_moodleoverflow
 * @covers \mod_moodleoverflow\external\review_approve_post::execute
 * @covers \mod_moodleoverflow\external\review_reject_post::execute
 * @covers \mod_moodleoverflow\local\service\review
 */
final class review_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /**
     * @var \phpunit_message_sink
     */
    private $mailsink;


    /**
     * set Up testing data.
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');

        $this->course = $this->getDataGenerator()->create_course();

        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->mailsink = $this->redirect_mails();
    }

    /**
     * Closing mailing links.
     * @return void
     */
    protected function tearDown(): void {
        $this->mailsink->clear();
        $this->mailsink->close();
        unset($this->mailsink);
        parent::tearDown();
    }

    /**
     * Test reviews functionality in forums where teachers should review everything.
     *
     * @runInSeparateProcess
     */
    public function test_forum_review_everything(): void {
        global $DB;

        $options = ['needsreview' => review_level::EVERYTHING->value, 'forcesubscribe' => subscription_mode::FORCED->value];

        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 0, mail_manager::MAILED_REVIEW_SUCCESS);

        // There should be one review mail for the teacher.
        // And 1 notification mail for the student (teacher does not get a notification mail for his own post).
        $this->assertEquals(2, $this->mailsink->count()); // Teacher has to approve student message.

        $this->mailsink->clear();

        $this->setUser($this->teacher);
        $this->assertSame('', review_approve_post::execute($posts['studentpost']->get_id()));

        $this->run_task(new send_mails());
        $this->run_task(new send_review_mails());

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $posts['studentpost']->get_id()]);
        $this->assert_matches_properties(['mailed' => mail_manager::MAILED_SUCCESS, 'reviewed' => 1], $post);
        $this->assertNotNull($post->timereviewed ?? null);

        // There should be one notification mail for the approved post.
        $this->assertEquals(1, $this->mailsink->count());
        $this->mailsink->clear();

        $this->setUser($this->student);
        $studentanswer1 = $this->generator->create_reply($posts['teacherpost'], $this->student);
        $studentanswer2 = $this->generator->create_reply($posts['teacherpost'], $this->student);
        $this->setAdminUser();

        $this->run_task(new send_mails());
        $this->run_task(new send_review_mails());

        // There should be two review mails for the teacher.
        $this->assertEquals(2, $this->mailsink->count());

        $this->mailsink->clear();

        $this->assertNotEmpty(review_approve_post::execute($studentanswer1->get_id()));
        $this->assertSame('', review_reject_post::execute($studentanswer2->get_id(), 'This post was not good!'));

        $this->run_task(new send_mails());
        $this->run_task(new send_review_mails());

        // One review mail for the teacher and one notification mail for the student.
        $this->assertEquals(2, $this->mailsink->count());

        $rejectionmessage = $this->mailsink->get_messages()[0];

        // Check student gets rejection message.
        $this->assertStringContainsString('This post was not good', $rejectionmessage->body);
        $this->assertEquals($this->student->email, $rejectionmessage->to);

        // Check post was deleted.
        $this->assertEquals(0, $DB->count_records('moodleoverflow_posts', ['id' => $studentanswer2->get_id()]));
    }

    /**
     * Test reviews functionality in forums where teachers should review questions.
     *
     * @runInSeparateProcess
     */
    public function test_forum_review_only_questions(): void {
        global $DB;

        $options = ['needsreview' => review_level::QUESTIONS->value, 'forcesubscribe' => subscription_mode::FORCED->value];
        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 0, mail_manager::MAILED_REVIEW_SUCCESS);

        // There should be one review needed mail for the teacher and one notification mail for the student.
        $this->assertEquals(2, $this->mailsink->count());

        $this->mailsink->clear();

        $this->setUser($this->teacher);
        $this->assertSame('', review_approve_post::execute($posts['studentpost']->get_id()));

        $this->run_task(new send_mails());
        $this->run_task(new send_review_mails());

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $posts['studentpost']->get_id()]);
        $this->assert_matches_properties(['mailed' => mail_manager::MAILED_SUCCESS, 'reviewed' => 1], $post);
        $this->assertNotNull($post->timereviewed ?? null);

        // There should be one notification mail for the student for the approved post.
        $this->assertEquals(1, $this->mailsink->count());

        $studentanswer1 = $this->generator->create_reply($posts['teacherpost'], $this->student);
        $studentanswer2 = $this->generator->create_reply($posts['teacherpost'], $this->student);

        $this->check_mail_records($studentanswer1, $studentanswer2, 1, 1, mail_manager::MAILED_SUCCESS);
    }

    /**
     * Test reviews functionality when reviewing is allowed in admin settings.
     */
    public function test_forum_review_disallowed(): void {
        $options = ['needsreview' => review_level::EVERYTHING->value, 'forcesubscribe' => subscription_mode::FORCED->value];

        set_config('allowreview', 0, 'moodleoverflow');

        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 1, mail_manager::MAILED_SUCCESS);

        // There should be 2 notifications mails (one for each post).
        $this->assertEquals(2, $this->mailsink->count()); // Teacher has to approve student message.

        $this->mailsink->clear();

        $this->setUser($this->student);
        $studentanswer1 = $this->generator->create_reply($posts['teacherpost'], $this->student);
        $studentanswer2 = $this->generator->create_reply($posts['teacherpost'], $this->student);
        $this->setAdminUser();

        $this->check_mail_records($studentanswer1, $studentanswer2, 1, 1, mail_manager::MAILED_SUCCESS);
    }

    /**
     * Test, if a refused rejection neither deletes the post nor sends the rejection mail.
     */
    public function test_reject_without_permission_sends_no_mail(): void {
        global $DB;
        $posts = $this->create_post(['needsreview' => review_level::EVERYTHING->value]);
        $this->mailsink->clear();

        $this->setUser($this->student);
        $this->assert_refused(fn() => review_reject_post::execute($posts['studentpost']->get_id(), 'Rejected by myself'));

        $this->assertEquals(0, $this->mailsink->count());
        $this->assertTrue($DB->record_exists('moodleoverflow_posts', ['id' => $posts['studentpost']->get_id()]));
    }

    /**
     * Test, if approving sets the review fields and removes the post from the posts to review.
     */
    public function test_approve(): void {
        global $DB;
        $modflow = $this->create_review_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student);

        service\review::approve($question, $this->teacher->id);

        $record = $DB->get_record('moodleoverflow_posts', ['id' => $question->get_id()], '*', MUST_EXIST);
        $this->assertEquals(1, $record->reviewed);
        $this->assertNotNull($record->timereviewed);
        $this->assertEquals([], service\review::get_posts_to_review($modflow, $this->teacher->id));
    }

    /**
     * Test, if approving makes the post the last post of the discussion at the time of the review, with its author.
     */
    public function test_approve_updates_last_post(): void {
        global $DB;
        $modflow = $this->create_review_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        // Both posts were written in the past, so the review is the newest activity.
        $DB->set_field('moodleoverflow_posts', 'created', 100, ['discussion' => $question->get_discussionid()]);
        $DB->set_field('moodleoverflow_posts', 'modified', 100, ['discussion' => $question->get_discussionid()]);

        service\review::approve(post::from_id($answer->get_id()), $this->teacher->id);

        $timereviewed = $DB->get_field('moodleoverflow_posts', 'timereviewed', ['id' => $answer->get_id()], MUST_EXIST);
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $question->get_discussionid()], '*', MUST_EXIST);
        $this->assertEquals($timereviewed, $discussion->timemodified);
        $this->assertEquals($this->student->id, $discussion->usermodified);
    }

    /**
     * Test, if rejecting an answer deletes only the answer.
     */
    public function test_reject_answer(): void {
        global $DB;
        $modflow = $this->create_review_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        $answerid = $answer->get_id();
        $this->setUser($this->teacher);

        service\review::reject($answer, $this->teacher->id);

        $this->assertFalse($DB->record_exists('moodleoverflow_posts', ['id' => $answerid]));
        $this->assertTrue($DB->record_exists('moodleoverflow_posts', ['id' => $question->get_id()]));
    }

    /**
     * Test, if rejecting a question deletes the whole discussion.
     */
    public function test_reject_question(): void {
        global $DB;
        $modflow = $this->create_review_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student);
        $discussionid = $question->get_discussionid();
        $this->setUser($this->teacher);

        service\review::reject($question, $this->teacher->id);

        $this->assertFalse($DB->record_exists('moodleoverflow_discussions', ['id' => $discussionid]));
        $this->assertFalse($DB->record_exists('moodleoverflow_posts', ['discussion' => $discussionid]));
    }

    /**
     * Test, if an answer can be rejected while a newer answer still waits for its review. The last activity of the
     * discussion only counts reviewed posts, so it stays at the question.
     */
    public function test_reject_older_answer_with_newer_unreviewed_answer(): void {
        global $DB;
        $modflow = $this->create_review_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->teacher);
        $older = $this->generator->create_reply($question, $this->student);
        $newer = $this->generator->create_reply($question, $this->student);
        $olderid = $older->get_id();
        $this->setUser($this->teacher);

        service\review::reject($older, $this->teacher->id);

        $this->assertFalse($DB->record_exists('moodleoverflow_posts', ['id' => $olderid]));
        $this->assertTrue($DB->record_exists('moodleoverflow_posts', ['id' => $newer->get_id()]));
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $question->get_discussionid()], '*', MUST_EXIST);
        $this->assertEquals($question->modified, $discussion->timemodified);
        $this->assertEquals($this->teacher->id, $discussion->usermodified);
    }

    /**
     * Test, if approving and rejecting are refused for users who may not review the post, and the post stays as it is.
     */
    public function test_approve_and_reject_need_permission(): void {
        global $DB;
        $modflow = $this->create_review_moodleoverflow();
        $unreviewed = $this->generator->create_question($modflow, $this->student);
        $reviewed = $this->generator->create_question($modflow, $this->teacher);
        $this->setUser($this->teacher);

        // A student cannot review, not even their own post.
        $this->assert_refused(fn() => service\review::approve($unreviewed, $this->student->id));
        $this->assert_refused(fn() => service\review::reject($unreviewed, $this->student->id));

        // A post that is already reviewed cannot be reviewed again.
        $this->assert_refused(fn() => service\review::approve($reviewed, $this->teacher->id));
        $this->assert_refused(fn() => service\review::reject($reviewed, $this->teacher->id));

        // Within the review delay, not even a teacher can review.
        set_config('reviewpossibleaftertime', 3600, 'moodleoverflow');
        $this->assert_refused(fn() => service\review::approve($unreviewed, $this->teacher->id));
        $this->assert_refused(fn() => service\review::reject($unreviewed, $this->teacher->id));

        $record = $DB->get_record('moodleoverflow_posts', ['id' => $unreviewed->get_id()], '*', MUST_EXIST);
        $this->assertEquals(0, $record->reviewed);
        $this->assertTrue($DB->record_exists('moodleoverflow_posts', ['id' => $reviewed->get_id()]));
    }

    /**
     * Test, if the posts to review are the unreviewed posts of the instance, ordered by discussion and then by id.
     */
    public function test_get_posts_to_review(): void {
        $modflow = $this->create_review_moodleoverflow();
        $teacherquestion = $this->generator->create_question($modflow, $this->teacher);
        $studentquestion = $this->generator->create_question($modflow, $this->student);
        // The answer has a higher id than the student question, but belongs to the older discussion.
        $answer = $this->generator->create_reply($teacherquestion, $this->student);
        // Posts of another moodleoverflow are not listed.
        $othermodflow = $this->create_review_moodleoverflow();
        $this->generator->create_question($othermodflow, $this->student);

        $expected = [$answer->get_id(), $studentquestion->get_id()];
        $this->assertEquals($expected, $this->get_ids(service\review::get_posts_to_review($modflow, $this->teacher->id)));
        $next = service\review::get_next_post_to_review($modflow, $this->teacher->id);
        $this->assertEquals($answer->get_id(), $next->get_id());

        // Users who cannot review get nothing.
        $this->assertEquals([], service\review::get_posts_to_review($modflow, $this->student->id));
        $this->assertNull(service\review::get_next_post_to_review($modflow, $this->student->id));
    }

    /**
     * Test, if posts within the review delay are not listed yet.
     */
    public function test_get_posts_to_review_within_delay(): void {
        $modflow = $this->create_review_moodleoverflow();
        $this->generator->create_question($modflow, $this->student);
        set_config('reviewpossibleaftertime', 3600, 'moodleoverflow');

        $this->assertEquals([], service\review::get_posts_to_review($modflow, $this->teacher->id));
        $this->assertNull(service\review::get_next_post_to_review($modflow, $this->teacher->id));
    }

    /**
     * Test, if the posts to review of a discussion are only the unreviewed posts of that discussion.
     */
    public function test_get_posts_to_review_in_discussion(): void {
        $modflow = $this->create_review_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        $secondanswer = $this->generator->create_reply($question, $this->student);
        $this->generator->create_question($modflow, $this->student);
        // Loaded after the replies, so its list of posts contains them.
        $discussion = discussion::from_id($question->get_discussionid());

        $posts = service\review::get_posts_to_review_in_discussion($discussion, $this->teacher->id);
        $this->assertEquals([$answer->get_id(), $secondanswer->get_id()], $this->get_ids($posts));
        $this->assertEquals([], service\review::get_posts_to_review_in_discussion($discussion, $this->student->id));
    }

    /**
     * Write own function to check if objects match.
     * @param object|array $expected
     * @param object|array $actual
     */
    private function assert_matches_properties($expected, $actual) {
        $expected = (array)$expected;
        $actual = (object)$actual;
        foreach ($expected as $key => $value) {
            $this->assertObjectHasProperty($key, $actual, "Failed asserting that attribute '$key' exists.");
            $this->assertEquals($value, $actual->$key, "Failed asserting that \$obj->$key '" . $actual->$key . "' equals '$value'");
        }
    }

    /**
     * Creates a moodleoverflow with a question of the teacher and a question of the student.
     * @param array $options Settings of the moodleoverflow.
     * @return post[] The questions, keyed by 'teacherpost' and 'studentpost'.
     */
    private function create_post(array $options): array {
        $modflow = $this->create_moodleoverflow($options);
        return [
            'teacherpost' => $this->generator->create_question($modflow, $this->teacher),
            'studentpost' => $this->generator->create_question($modflow, $this->student),
        ];
    }

    /**
     * Check Mail object before and after sending.
     * @param post $teacherpost
     * @param post $studentpost
     * @param int $review1
     * @param int $review2
     * @param int $mailed
     * @return void
     * @throws \dml_exception
     */
    private function check_mail_records($teacherpost, $studentpost, $review1, $review2, $mailed) {
        global $DB;

        $this->assert_matches_properties(
            ['mailed' => mail_manager::MAILED_PENDING,
                                          'reviewed' => $review1, 'timereviewed' => null, ],
            $DB->get_record('moodleoverflow_posts', ['id' => $teacherpost->get_id()])
        );
        $this->assert_matches_properties(
            ['mailed' => mail_manager::MAILED_PENDING,
                                          'reviewed' => $review2, 'timereviewed' => null, ],
            $DB->get_record('moodleoverflow_posts', ['id' => $studentpost->get_id()])
        );

        $this->run_task(new send_mails());
        $this->run_task(new send_review_mails());

        $this->assert_matches_properties(
            ['mailed' => mail_manager::MAILED_SUCCESS,
                                          'reviewed' => $review1, 'timereviewed' => null, ],
            $DB->get_record('moodleoverflow_posts', ['id' => $teacherpost->get_id()])
        );
        $this->assert_matches_properties(
            ['mailed' => $mailed, 'reviewed' => $review2, 'timereviewed' => null],
            $DB->get_record('moodleoverflow_posts', ['id' => $studentpost->get_id()])
        );
    }

    /**
     * Asserts that a review action is refused with the error code cannotreviewpost.
     *
     * @param callable $action
     */
    private function assert_refused(callable $action): void {
        try {
            $action();
            $this->fail('The review action was not refused.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('cannotreviewpost', $e->errorcode);
        }
    }

    /**
     * Returns the ids of posts, in their order.
     *
     * @param post[] $posts
     * @return int[]
     */
    private function get_ids(array $posts): array {
        return array_values(array_map(fn(post $post) => $post->get_id(), $posts));
    }

    /**
     * Creates a moodleoverflow in which every post has to be reviewed.
     *
     * @return moodleoverflow
     */
    private function create_review_moodleoverflow(): moodleoverflow {
        return $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
    }
}
