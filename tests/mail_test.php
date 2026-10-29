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

use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\output\mail\notification;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests who gets which mail. The recipients themselves come from the subscription service (subscription_test), the
 * sending from the message API, which catches the messages here.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\mail
 * @covers \mod_moodleoverflow\output\mail\notification
 */
#[CoversClass(service\mail::class)]
#[CoversClass(notification::class)]
final class mail_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
    }

    /**
     * Test, if every subscriber except the author gets the notification, and a user with a digest gets the post in the
     * digest instead.
     */
    public function test_send_notification(): void {
        global $DB;
        $DB->set_field('user', 'maildigest', 1, ['id' => $this->otherstudent->id]);
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::FORCED->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $sink = $this->redirectMessages();

        $this->assertEquals([], service\mail::send_notification($question));

        $this->assertEquals([$this->student->id], array_column($sink->get_messages(), 'useridto'));
        $this->assertTrue($DB->record_exists('moodleoverflow_mail_info', ['userid' => $this->otherstudent->id]));
    }

    /**
     * Test, if a post that became visible longer ago than the maximal mailing time is not sent, so a cron that did not
     * run for a long time does not send old posts.
     */
    public function test_old_post_is_not_sent(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::FORCED->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $old = time() - 10 * DAYSECS;
        $DB->update_record('moodleoverflow_posts', (object) ['id' => $question->get_id(), 'created' => $old, 'modified' => $old]);
        $sink = $this->redirectMessages();

        service\mail::send_notification(post::from_id($question->get_id()));

        $this->assertEquals(0, $sink->count());
    }

    /**
     * Test, if the notification about a post in an anonymous moodleoverflow does not reveal the author.
     */
    public function test_notification_hides_anonymous_author(): void {
        $modflow = $this->create_moodleoverflow([
            'forcesubscribe' => subscription_mode::FORCED->value,
            'anonymous' => anonymity::EVERYTHING->value,
        ]);
        $question = $this->generator->create_question($modflow, $this->student);
        $sink = $this->redirectMessages();

        service\mail::send_notification($question);

        $this->assertNotEmpty($sink->get_messages());
        foreach ($sink->get_messages() as $message) {
            $this->assertStringNotContainsString(fullname($this->student), $message->fullmessage);
        }
    }
}
