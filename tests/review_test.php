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
 */
class review_test extends \advanced_testcase {

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
     * @var \phpunit_message_sink
     */
    private $messagesink;

    /**
     * set Up testing data.
     * @return void
     */
    protected function setUp(): void {
        $this->resetAfterTest();

        set_config('reviewpossibleaftertime', -10, 'moodleoverflow');
        set_config('maxeditingtime', -10, 'moodleoverflow');

        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');

        $this->course = $this->getDataGenerator()->create_course();

        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        unset_config('noemailever');
        $this->mailsink = $this->redirectEmails();

        $this->preventResetByRollback();
        $this->messagesink = $this->redirectMessages();
    }

    /**
     * Closing mailing links.
     * @return void
     */
    protected function tearDown(): void {
        $this->mailsink->clear();
        $this->mailsink->close();
        unset($this->mailsink);

        $this->messagesink->clear();
        $this->messagesink->close();
        unset($this->messagesink);
    }

    /**
     * Test reviews functionality in forums where teachers should review everything.
     *
     * @runInSeparateProcess
     */
    public function test_forum_review_everything() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/moodleoverflow/externallib.php');

        $options = array('course' => $this->course->id, 'needsreview' => review::EVERYTHING,
            'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE);

        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 0, MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS);

        $this->assertEquals(1, $this->mailsink->count()); // Teacher has to approve student message.
        $this->assertEquals(2, $this->messagesink->count()); // Student and teacher get notification for student message.

        $this->mailsink->clear();
        $this->messagesink->clear();

        $this->assertNull(\mod_moodleoverflow_external::review_approve_post($posts['studentpost']->id));

        $this->run_send_mails();
        $this->run_send_mails(); // Execute twice to ensure no duplicate mails.

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $posts['studentpost']->id]);
        $this->assert_matches_properties(['mailed' => MOODLEOVERFLOW_MAILED_SUCCESS, 'reviewed' => 1], $post);
        $this->assertNotNull($post->timereviewed ?? null);

        $this->assertEquals(0, $this->mailsink->count());
        $this->assertEquals(2, $this->messagesink->count());

        $this->messagesink->clear();

        $studentanswer1 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $studentanswer2 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);

        $this->run_send_mails();
        $this->run_send_mails(); // Execute twice to ensure no duplicate mails.

        $this->assertEquals(2, $this->mailsink->count());
        $this->assertEquals(0, $this->messagesink->count());

        $this->mailsink->clear();

        $this->assertNotNull(\mod_moodleoverflow_external::review_approve_post($studentanswer1->id));
        $this->assertNull(\mod_moodleoverflow_external::review_reject_post($studentanswer2->id, 'This post was not good!'));

        $this->run_send_mails();
        $this->run_send_mails(); // Execute twice to ensure no duplicate mails.

        $this->assertEquals(1, $this->mailsink->count());
        $this->assertEquals(2, $this->messagesink->count());

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
    public function test_forum_review_only_questions() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/moodleoverflow/externallib.php');

        $options = array('course' => $this->course->id, 'needsreview' => review::QUESTIONS,
            'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE);
        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 0, MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS);

        $this->assertEquals(1, $this->mailsink->count()); // Teacher has to approve student message.
        $this->assertEquals(2, $this->messagesink->count()); // Student and teacher get notification for student message.

        $this->mailsink->clear();
        $this->messagesink->clear();

        $this->assertNull(\mod_moodleoverflow_external::review_approve_post($posts['studentpost']->id));

        $this->run_send_mails();
        $this->run_send_mails(); // Execute twice to ensure no duplicate mails.

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $posts['studentpost']->id]);
        $this->assert_matches_properties(['mailed' => MOODLEOVERFLOW_MAILED_SUCCESS, 'reviewed' => 1], $post);
        $this->assertNotNull($post->timereviewed ?? null);

        $this->assertEquals(0, $this->mailsink->count());
        $this->assertEquals(2, $this->messagesink->count());

        $this->messagesink->clear();

        $studentanswer1 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $studentanswer2 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);

        $this->check_mail_records($studentanswer1, $studentanswer2, 1, 1, MOODLEOVERFLOW_MAILED_SUCCESS);

        $this->assertEquals(0, $this->mailsink->count());
        $this->assertEquals(4, $this->messagesink->count());
    }

    /**
     * Test reviews functionality when reviewing is allowed in admin settings.
     */
    public function test_forum_review_disallowed() {
        $options = array('course' => $this->course->id, 'needsreview' => review::EVERYTHING,
            'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE);

        set_config('allowreview', 0, 'moodleoverflow');

        $posts = $this->create_post($options);
        $this->check_mail_records($posts['teacherpost'], $posts['studentpost'], 1, 1, MOODLEOVERFLOW_MAILED_SUCCESS);

        $this->assertEquals(0, $this->mailsink->count()); // Teacher has to approve student message.
        $this->assertEquals(4, $this->messagesink->count()); // Student and teacher get notification for student message.

        $this->mailsink->clear();
        $this->messagesink->clear();

        $studentanswer1 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);
        $studentanswer2 = $this->generator->reply_to_post($posts['teacherpost'], $this->student, false);

        $this->check_mail_records($studentanswer1, $studentanswer2, 1, 1, MOODLEOVERFLOW_MAILED_SUCCESS);

        $this->assertEquals(0, $this->mailsink->count());
        $this->assertEquals(4, $this->messagesink->count());
    }

    /**
     * Run the send mails task.
     * @return false|string
     */
    private function run_send_mails() {
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
            $this->assertObjectHasAttribute($key, $actual, "Failed asserting that attribute '$key' exists.");
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

        list(, $teacherpost) = $this->generator->post_to_forum($moodleoverflow, $this->teacher);
        list(, $studentpost) = $this->generator->post_to_forum($moodleoverflow, $this->student);

        return array('teacherpost' => $teacherpost, 'studentpost' => $studentpost);
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

        $this->assert_matches_properties(['mailed' => MOODLEOVERFLOW_MAILED_PENDING, 'reviewed' => $review1,
                                          'timereviewed' => null],
                                         $DB->get_record('moodleoverflow_posts', ['id' => $teacherpost->id]));
        $this->assert_matches_properties(['mailed' => MOODLEOVERFLOW_MAILED_PENDING, 'reviewed' => $review2,
                                          'timereviewed' => null],
                                         $DB->get_record('moodleoverflow_posts', ['id' => $studentpost->id]));

        $this->run_send_mails();
        $this->run_send_mails(); // Execute twice to ensure no duplicate mails.

        $this->assert_matches_properties(['mailed' => MOODLEOVERFLOW_MAILED_SUCCESS, 'reviewed' => $review1,
                                          'timereviewed' => null],
                                         $DB->get_record('moodleoverflow_posts', ['id' => $teacherpost->id]));
        $this->assert_matches_properties(['mailed' => $mailed, 'reviewed' => $review2, 'timereviewed' => null],
            $DB->get_record('moodleoverflow_posts', ['id' => $studentpost->id]));
    }
}
