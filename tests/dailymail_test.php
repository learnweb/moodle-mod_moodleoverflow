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
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

use mod_moodleoverflow\task\send_mails;
use mod_moodleoverflow\task\send_daily_mail;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');


/**
 * Class mod_moodleoverflow_dailymail_testcase.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dailymail_test extends \advanced_testcase {

    /** @var \stdClass collection of messages */
    private $sink;

    /** @var \stdClass test course */
    private $course;

    /** @var \stdClass test user*/
    private $user;

    /** @var \stdClass moodleoverflow instance */
    private $moodleoverflow;

    /** @var \stdClass coursemodule instance */
    private $coursemodule;

    /** @var \stdClass discussion instance */
    private $discussion;

    /** @var  moodleoverflow generator */
    private $generator;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        set_config('maxeditingtime', -10, 'moodleoverflow');

        unset_config('noemailever');
        $this->sink = $this->redirectEmails();
        $this->preventResetByRollback();
        $this->redirectMessages();
        // Create a new course with a moodleoverflow forum.
        $this->course = $this->getDataGenerator()->create_course();
        $location = ['course' => $this->course->id, 'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE];
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->moodleoverflow->id);
    }

    /**
     * Test tearDown.
     */
    public function tearDown(): void {
        // Clear all caches.
        \mod_moodleoverflow\subscriptions::reset_moodleoverflow_cache();
        \mod_moodleoverflow\subscriptions::reset_discussion_cache();
    }

    // Helper functions.

    /**
     * Function that creates a new user, which adds a new discussion an post to the moodleoverflow.
     * @param int $maildigest The maildigest setting: 0 = off , 1 = on
     */
    public function helper_create_user_and_discussion($maildigest) {
        // Create a user enrolled in the course as student.
        $this->user = $this->getDataGenerator()->create_user(['firstname' => 'Tamaro', 'email' => 'tamaromail@example.com',
                                                              'maildigest' => $maildigest]);
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');

        // Create a new discussion and post within the moodleoverflow.
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->discussion = $this->generator->post_to_forum($this->moodleoverflow, $this->user);
    }

    /**
     * Run the send daily mail task.
     * @return false|string
     */
    private function helper_run_send_daily_mail() {
        $mailtask = new send_daily_mail();
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
    private function helper_run_send_mails() {
        $mailtask = new send_mails();
        ob_start();
        $mailtask->execute();
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }



    // Begin of test functions.

    /**
     * Test if the task send_daily_mail sends a mail to the user.
     * @covers \send_daily_mail::execute
     */
    public function test_mail_delivery(): void {
        // Create user with maildigest = on.
        $this->helper_create_user_and_discussion('1');

        // Send a mail and test if the mail was sent.
        $this->helper_run_send_mails();
        $this->helper_run_send_daily_mail();
        $messages = $this->sink->count();

        $this->assertEquals(1, $messages);
    }

    /**
     * Test if the task send_daily_mail does not sends email from posts that are not in the course of the user.
     * @return void
     */
    public function test_delivery_not_enrolled(): void {
        // Create user with maildigest = on.
        $this->helper_create_user_and_discussion('1');

        // Create another user, course and a moodleoverflow post.
        $course = $this->getDataGenerator()->create_course();
        $location = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ethan', 'email' => 'ethanmail@example.com',
                                                           'maildigest' => '1']);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'teacher');
        $discussion = $this->generator->post_to_forum($moodleoverflow, $student);

        // Send the mails.
        $this->helper_run_send_mails();
        $this->helper_run_send_daily_mail();
        $messages = $this->sink->count();
        $content = $this->sink->get_messages();

        // There should be 2 mails.
        $this->assertEquals(2, $messages);

        // Check the recipient of the mails and the discussion that is addressed. There should be no false addressed discussions.
        $firstmail = $content[0];
        $secondmail = $content[1];
        $this->assertEquals('tamaromail@example.com', $firstmail->to);
        $this->assertStringContainsString($this->discussion[0]->name, $firstmail->body);
        $this->assertStringNotContainsString($discussion[0]->name, $firstmail->body);

        $this->assertEquals('ethanmail@example.com', $secondmail->to);
        $this->assertStringContainsString($discussion[0]->name, $secondmail->body);
        $this->assertStringNotContainsString($this->discussion[0]->name, $secondmail->body);
    }


    /**
     * Test if the content of the mail matches the supposed content.
     * @covers \send_daily_mail::execute
     */
    public function test_content_of_mail_delivery() {

        // Create user with maildigest = on.
        $this->helper_create_user_and_discussion('1');

        // Send the mails and count the messages.
        $this->helper_run_send_mails();
        $this->helper_run_send_daily_mail();
        $content = $this->sink->get_messages();
        $message = $content[0]->body;
        $message = str_replace(["\n\r", "\n", "\r"], '', $message);
        $messagecount = $this->sink->count();

        // Build the text that the mail should have.
        // Text structure at get_string('digestunreadpost', moodleoverflow).
        $linktocourse = '<a href=3D"https://www.example.com/mood=le/course/view.php?id=3D'. $this->course->id;
        $linktoforum = '<a href=3D"https://www.=example.com/moodle/mod/moodleoverflow/view.php?id=3D'. $this->coursemodule->id;
        $linktodiscussion = '<a href=3D"https://www.example.com/moodle/mod/moodleoverflow/=discussion.php?d=3D'
                            . $this->discussion[0]->id;

        // Assemble text.
        $text = 'Course: ' . $linktocourse . ' -> ' . $linktoforum . ', Topic: '
                . $linktodiscussion . ' has ' . $messagecount . ' unread posts.';

        $this->assertStringContainsString($linktocourse, $message);
        $this->assertStringContainsString($linktoforum, $message);
        $this->assertStringContainsString($linktodiscussion, $message);
        $this->assertStringContainsString($messagecount, $message);
    }


    /**
     * Test if the task does not send a mail when maildigest = 0
     * @covers \send_daily_mail::execute
     */
    public function test_mail_not_send() {
        // Creat user with daily_mail = off.
        $this->helper_create_user_and_discussion('0');

        // Now send the mails and test if no mail was sent.
        $this->helper_run_send_mails();
        $this->helper_run_send_daily_mail();
        $messages = $this->sink->count();

        $this->assertEquals(0, $messages);
    }

    /**
     * Test if database is updated after sending a mail
     * @covers \send_daily_mail::execute
     */
    public function test_records_removed() {
        global $DB;
        // Create user with maildigest = on.
        $this->helper_create_user_and_discussion('1');

        // Now send the mails.
        $this->helper_run_send_mails();
        $this->helper_run_send_daily_mail();

        // Now check the database if the records of the users are deleted.
        $records = $DB->get_records('moodleoverflow_mail_info', ['userid' => $this->user->id]);
        $this->assertEmpty($records);
    }
}
