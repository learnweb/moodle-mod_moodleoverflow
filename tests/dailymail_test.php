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

use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\task\send_mails;
use mod_moodleoverflow\task\send_daily_mails;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');


/**
 * PHPUnit tests for the daily mail digest.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\task\send_daily_mails::execute
 */
final class dailymail_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var \phpunit_phpmailer_sink catches the sent mails */
    private $sink;

    /** @var moodleoverflow the moodleoverflow with forced subscription */
    private $modflow;

    /** @var stdClass the discussion of the student */
    private $discussion;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->sink = $this->redirect_mails();
        $this->preventResetByRollback();

        // Create a new course with a moodleoverflow forum.
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->course = $this->getDataGenerator()->create_course();
        $this->modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::FORCED->value]);
    }

    // Helper functions.

    /**
     * Function that creates a new user, which adds a new discussion an post to the moodleoverflow.
     * @param int $maildigest The maildigest setting: 0 = off , 1 = on
     */
    public function helper_test_set_up($maildigest) {
        // Create a user enrolled in the course as student.
        $this->teacher = $this->getDataGenerator()->create_user(['firstname' => 'Tamaro', 'email' => 'tamaromail@example.com',
                                                              'maildigest' => $maildigest, ]);
        $this->student = $this->getDataGenerator()->create_user(['firstname' => 'Student1', 'email' => 'student1mail@example.com',
                                                              'maildigest' => $maildigest, ]);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'teacher');
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'teacher');

        // Create a new discussion and post within the moodleoverflow.
        [$this->discussion] = $this->generator->post_to_forum($this->modflow, $this->student);
    }

    /**
     * Run the send daily mail task.
     * @return string
     */
    private function helper_run_send_daily_mails() {
        $this->run_task(new send_mails());
        return $this->run_task(new send_daily_mails());
    }

    // Begin of test functions.

    /**
     * Test if the task send_daily_mails sends a mail to the user.
     */
    public function test_mail_delivery(): void {
        // Create user with maildigest = on.
        $this->helper_test_set_up('1');

        // Send a mail and test if the mail was sent.
        $this->helper_run_send_daily_mails();
        $messages = $this->sink->count();

        $this->assertEquals(1, $messages);
    }

    /**
     * Test if the task send_daily_mails does not sends email from posts that are not in the course of the user.
     */
    public function test_delivery_not_enrolled(): void {
        // Create user with maildigest = on.
        $this->helper_test_set_up('1');

        // Create another user, course and a moodleoverflow post.
        $course = $this->getDataGenerator()->create_course();
        $location = ['course' => $course->id, 'forcesubscribe' => subscription_mode::FORCED->value];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Student2', 'email' => 'student2@example.com',
                                                           'maildigest' => '1', ]);
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Teacher2', 'email' => 'teacher2@example.com',
                                                            'maildigest' => '1', ]);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');
        $discussion = $this->generator->post_to_forum($moodleoverflow, $student);

        // Send the mails.
        $this->helper_run_send_daily_mails();
        $messages = $this->sink->count();
        $content = $this->sink->get_messages();

        // There should be 2 mails.
        $this->assertEquals(2, $messages);

        // Check the recipient of the mails and the discussion that is addressed. There should be no false addressed discussions.
        $firstmail = $content[0];
        $secondmail = $content[1];
        // Depending on the order of the mails, check the recipient and the discussion that is addressed.
        if ($firstmail->to == "tamaromail@example.com") {
            $this->assertStringContainsString($this->discussion->name, $firstmail->body);
            $this->assertStringNotContainsString($discussion[0]->name, $firstmail->body);
            $this->assertEquals('teacher2@example.com', $secondmail->to);
            $this->assertStringContainsString($discussion[0]->name, $secondmail->body);
            $this->assertStringNotContainsString($this->discussion->name, $secondmail->body);
        } else {
            $this->assertEquals('teacher2@example.com', $firstmail->to);
            $this->assertStringContainsString($discussion[0]->name, $firstmail->body);
            $this->assertStringNotContainsString($this->discussion->name, $firstmail->body);
            $this->assertEquals('tamaromail@example.com', $secondmail->to);
            $this->assertStringContainsString($this->discussion->name, $secondmail->body);
            $this->assertStringNotContainsString($discussion[0]->name, $secondmail->body);
        }
    }


    /**
     * Test if the content of the mail matches the supposed content.
     */
    public function test_content_of_mail_delivery(): void {

        // Create user with maildigest = on.
        $this->helper_test_set_up('1');

        // Send the mails and count the messages.
        $this->helper_run_send_daily_mails();
        $content = $this->sink->get_messages();
        $message = $content[0]->body;
        $message = str_replace(["\n\r", "\n", "\r"], '', $message);
        $messagecount = $this->sink->count();

        // Build the text that the mail should have.
        // Text structure at get_string('digestunreadpost', moodleoverflow).
        $linktocourse = '<a href=3D"https://www.example.com/mood=le/course/view.php?id=3D' . $this->course->id;
        $linktoforum = '<a href=3D"https://www.=example.com/moodle/mod/moodleoverflow/view.php?m=3D' . $this->modflow->id;
        $linktodiscussion = '<a href=3D"https://www.example.com/moodle/mod/moodleoverflow/d=iscussion.php?d=3D'
                            . $this->discussion->id;

        $this->assertStringContainsString($linktocourse, $message);
        $this->assertStringContainsString($linktoforum, $message);
        $this->assertStringContainsString($linktodiscussion, $message);
        $this->assertStringContainsString($messagecount, $message);
    }


    /**
     * Test if the task does not send a mail when maildigest = 0
     */
    public function test_mail_not_send(): void {
        // Creat user with daily_mail = off.
        $this->helper_test_set_up('0');

        // Now send the mails and test if no mail was sent.
        $this->helper_run_send_daily_mails();
        $messages = $this->sink->get_messages()[0];

        // The teacher now gets a notification mail. The subject of the mail is now different.
        $this->assertNotEquals($messages->subject, get_string('tasksenddailymails', 'mod_moodleoverflow'));
    }

    /**
     * Test if database is updated after sending a mail
     */
    public function test_records_removed(): void {
        global $DB;
        // Create user with maildigest = on.
        $this->helper_test_set_up('1');

        // Now send the mails.
        $this->helper_run_send_daily_mails();

        // Now check the database if the records of the users are deleted.
        $records = $DB->get_records('moodleoverflow_mail_info', ['userid' => $this->teacher->id]);
        $this->assertEmpty($records);
    }
}
