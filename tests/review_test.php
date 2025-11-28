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

use mod_moodleoverflow\task\send_mails;
use mod_moodleoverflow\task\send_review_mails;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');

/**
 * PHPUnit Tests for testing readtracking.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @group mod_moodleoverflow
 * @covers \mod_moodleoverflow_external::review_approve_post
 * @covers \mod_moodleoverflow_external::review_reject_post
 */
final class review_test extends \advanced_testcase {
    /** @var \mod_moodleoverflow_generator $generator */
    private $generator;
    /**
     * @var  \stdClass
     */
    private $teacher;
    /**
     * @var  \stdClass
     */
    private $student;
    /**
     * @var  \stdClass
     */
    private $course;
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

        set_config('reviewpossibleaftertime', -10, 'moodleoverflow');
        set_config('maxeditingtime', -10, 'moodleoverflow');

        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');

        $this->course = $this->getDataGenerator()->create_course();

        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        unset_config('noemailever');
        $this->mailsink = $this->redirectEmails();
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
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/moodleoverflow/externallib.php');

        $options = ['course' => $this->course->id, 'needsreview' => review::EVERYTHING,
            'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE, ];

        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 0, MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS);

        // There should be one review mail for the teacher.
        // And 1 notification mail for the student (teacher does not get a notification mail for his own post).
        $this->assertEquals(2, $this->mailsink->count()); // Teacher has to approve student message.

        $this->mailsink->clear();

        $this->assertNull(\mod_moodleoverflow_external::review_approve_post($posts['studentpost']->id));

        $this->run_send_notification_mails();
        $this->run_send_review_mails();

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $posts['studentpost']->id]);
        $this->assert_matches_properties(['mailed' => MOODLEOVERFLOW_MAILED_SUCCESS, 'reviewed' => 1], $post);
        $this->assertNotNull($post->timereviewed ?? null);

        // There should be one notification mail for the approved post.
        $this->assertEquals(1, $this->mailsink->count());
        $this->mailsink->clear();

        $this->setUser($this->student);
        $studentanswer1 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $studentanswer2 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $this->setAdminUser();

        $this->run_send_notification_mails();
        $this->run_send_review_mails();

        // There should be two review mails for the teacher.
        $this->assertEquals(2, $this->mailsink->count());

        $this->mailsink->clear();

        $this->assertNotNull(\mod_moodleoverflow_external::review_approve_post($studentanswer1->id));
        $this->assertNull(\mod_moodleoverflow_external::review_reject_post($studentanswer2->id, 'This post was not good!'));

        $this->run_send_notification_mails();
        $this->run_send_review_mails();

        // One review mail for the teacher and one notification mail for the student.
        $this->assertEquals(2, $this->mailsink->count());

        $rejectionmessage = $this->mailsink->get_messages()[0];

        // Check student gets rejection message.
        $this->assertStringContainsString('This post was not good', $rejectionmessage->body);
        $this->assertEquals($this->student->email, $rejectionmessage->to);

        // Check post was deleted.
        $this->assertEquals(0, $DB->count_records('moodleoverflow_posts', ['id' => $studentanswer2->id]));
    }

    /**
     * Test reviews functionality in forums where teachers should review questions.
     *
     * @runInSeparateProcess
     */
    public function test_forum_review_only_questions(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/moodleoverflow/externallib.php');

        $options = ['course' => $this->course->id, 'needsreview' => review::QUESTIONS,
            'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE, ];
        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 0, MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS);

        // There should be one review needed mail for the teacher and one notification mail for the student.
        $this->assertEquals(2, $this->mailsink->count());

        $this->mailsink->clear();

        $this->assertNull(\mod_moodleoverflow_external::review_approve_post($posts['studentpost']->id));

        $this->run_send_notification_mails();
        $this->run_send_review_mails();

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $posts['studentpost']->id]);
        $this->assert_matches_properties(['mailed' => MOODLEOVERFLOW_MAILED_SUCCESS, 'reviewed' => 1], $post);
        $this->assertNotNull($post->timereviewed ?? null);

        // There should be one notification mail for the student for the approved post.
        $this->assertEquals(1, $this->mailsink->count());

        $studentanswer1 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $studentanswer2 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);

        $this->check_mail_records($studentanswer1, $studentanswer2, 1, 1, MOODLEOVERFLOW_MAILED_SUCCESS);
    }

    /**
     * Test reviews functionality when reviewing is allowed in admin settings.
     */
    public function test_forum_review_disallowed(): void {
        $options = ['course' => $this->course->id, 'needsreview' => review::EVERYTHING,
            'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE, ];

        set_config('allowreview', 0, 'moodleoverflow');

        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 1, MOODLEOVERFLOW_MAILED_SUCCESS);

        // There should be 2 notifications mails (one for each post).
        $this->assertEquals(2, $this->mailsink->count()); // Teacher has to approve student message.

        $this->mailsink->clear();

        $this->setUser($this->student);
        $studentanswer1 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $studentanswer2 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $this->setAdminUser();

        $this->check_mail_records($studentanswer1, $studentanswer2, 1, 1, MOODLEOVERFLOW_MAILED_SUCCESS);
    }

    /**
     * Run the send mails task.
     * @return false|string
     */
    private function run_send_review_mails() {
        $mailtask = new send_review_mails();
        ob_start();
        $mailtask->execute();
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Run the send mails task.
     * @return false|string
     */
    private function run_send_notification_mails() {
        $mailtask = new send_mails();
        ob_start();
        $mailtask->execute();
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
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
     * Create two posts.
     * @param array $options
     * @return array the teacher and the studentpost.
     */
    private function create_post($options) {
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        [, $teacherpost] = $this->generator->post_to_forum($moodleoverflow, $this->teacher);
        [, $studentpost] = $this->generator->post_to_forum($moodleoverflow, $this->student);

        return ['teacherpost' => $teacherpost, 'studentpost' => $studentpost];
    }

    /**
     * Check Mail object before and after sending.
     * @param \stdClass $teacherpost
     * @param \stdClass $studentpost
     * @param int $review1
     * @param int $review2
     * @param int $mailed
     * @return void
     * @throws \dml_exception
     */
    private function check_mail_records($teacherpost, $studentpost, $review1, $review2, $mailed) {
        global $DB;

        $this->assert_matches_properties(
            ['mailed' => MOODLEOVERFLOW_MAILED_PENDING,
                                          'reviewed' => $review1, 'timereviewed' => null, ],
            $DB->get_record('moodleoverflow_posts', ['id' => $teacherpost->id])
        );
        $this->assert_matches_properties(
            ['mailed' => MOODLEOVERFLOW_MAILED_PENDING,
                                          'reviewed' => $review2, 'timereviewed' => null, ],
            $DB->get_record('moodleoverflow_posts', ['id' => $studentpost->id])
        );

        $this->run_send_notification_mails();
        $this->run_send_review_mails();

        $this->assert_matches_properties(
            ['mailed' => MOODLEOVERFLOW_MAILED_SUCCESS,
                                          'reviewed' => $review1, 'timereviewed' => null, ],
            $DB->get_record('moodleoverflow_posts', ['id' => $teacherpost->id])
        );
        $this->assert_matches_properties(
            ['mailed' => $mailed, 'reviewed' => $review2, 'timereviewed' => null],
            $DB->get_record('moodleoverflow_posts', ['id' => $studentpost->id])
        );
    }
}
