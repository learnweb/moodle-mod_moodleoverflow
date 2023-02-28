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

class mod_moodleoverflow_dailymail_test extends \advanced_testcase { 
    
    private $sink;
    private $messagesink;
    private $course;
    private $user;
    private $moodleoverflow;
    private $discussion;
    
    /**
     * Test setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        set_config('maxeditingtime', -10, 'moodleoverflow');

        unset_config('noemailever');
        $this->sink = $this->redirectEmails();

        $this->preventResetByRollback();
        $this->messagesink = $this->redirectMessages();

        // Create a new course with a moodleoverflow forum.
        $this->course = $this->getDataGenerator()->create_course();
        $location = array('course' => $this->course->id,'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE);
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow',$location);

    }

    /**
     * Test tearDown.
     */
    public function tearDown(): void {
        // Clear all caches.
        \mod_moodleoverflow\subscriptions::reset_moodleoverflow_cache();
        \mod_moodleoverflow\subscriptions::reset_discussion_cache();
    }

    /**
     * Function that creates a new user, which adds a new discussion an post to the moodleoverflow.
     */
    public function helper_create_user_and_discussion($maildigest) {
        // Create a user enrolled in the course as student.
        $this->user = $this->getDataGenerator()->create_user(array('firstname' => 'Tamaro', 'maildigest' => $maildigest));
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');

        //Create a new discussion and post within the moodleoverflow.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->discussion = $generator->post_to_forum($this->moodleoverflow, $this->user);
    }

    /**
     * Run the send daily mail task.
     * @return false|string
     */
    private function run_send_daily_mail() {
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
    private function run_send_mails() {
        $mailtask = new send_mails();
        ob_start();
        $mailtask->execute();
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Test if the task send_daily_mail sends a mail to the user
     */
    public function test_mail_delivery() {
        global $DB;

        // Create users with maildigest = on
        $this->helper_create_user_and_discussion('1');

        // Send a mail and test if the mail was sent.
        
        $this->run_send_mails();       //content2
        $this->run_send_daily_mail();  //content
        $messages = $this->sink->count();
        
        $this->assertEquals(1, $messages);
    }

    public function test_content_of_mail_delivery() {
        global $DB;

        // Creat Users with maildigest = on.
        $this->helper_create_user_and_discussion('1');

        //send the mails and count the messages.
        $this->run_send_mails();
        $content = $this->run_send_daily_mail();
        $messages = $this->sink->count();
        
        //Build the text that the mail should have.
        //Text structure: $string['digestunreadpost'] = 'Course: {$a->currentcourse} -> {$a->currentforum}, Topic: {$a->discussion} has {$a->unreadposts} unread posts.';.
        $currentcourse = $this->course->fullname;
        $currentforum = $this->moodleoverflow->name;
        $currentdiscussion = $this->discussion[0]->name;
        $text = 'Course: ' . $currentcourse . ' -> ' . $currentforum . ', Topic: ' . $currentdiscussion . ' has ' . $messages . ' unread posts.'; 
        $content = str_replace("\r\n","",$content);
        $text = str_replace("\r\n","",$text);
        
        //$this->assertisInt(0, strcmp($text, $content));   //strcmp compares 2 strings and retuns 0 if equal
        //$this->assertEquals($text, $content);
        $this->assertStringContainsString($text, $content);
    }

    public function test_mail_not_send() {
        global $DB;
        // Creat Users with daily_mail = off.
        $this->helper_create_user_and_discussion('0');

        // Now send the mails and test if no mail was sent.
        $this->run_send_mails();
        $this->run_send_daily_mail();
        $messages = $this->sink->count();
        
        $this->assertEquals(0,$messages);
    }
}