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

use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\manager\mail_manager;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service\subscription;
use mod_moodleoverflow\task\send_mails;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

/**
 * Tests who gets a notification mail about a new post. The recipients follow the rules of the subscription service.
 * @package   mod_moodleoverflow
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\manager\mail_manager
 */
final class notification_mail_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var \phpunit_phpmailer_sink catches the sent mails */
    private $sink;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->sink = $this->redirect_mails();
        $this->setup_course();
    }

    /**
     * Test, if a user that unsubscribed from a discussion gets no mails about it, although they are subscribed to the
     * moodleoverflow.
     */
    public function test_discussion_optout(): void {
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::OPTIONAL->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        subscription::subscribe($modflow, $this->student->id);
        subscription::subscribe($modflow, $this->otherstudent->id);
        subscription::unsubscribe_discussion($question->get_discussion(), $this->student->id);

        $this->assertEquals([(int) $this->otherstudent->id], $this->get_recipients($question));
    }

    /**
     * Test, if a user that is subscribed to the moodleoverflow and to the discussion gets the mail once.
     */
    public function test_no_duplicates(): void {
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::OPTIONAL->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        subscription::subscribe($modflow, $this->student->id);
        subscription::subscribe_discussion($question->get_discussion(), $this->student->id);

        $this->assertEquals([(int) $this->student->id], $this->get_recipients($question));
    }

    /**
     * Test, if a forced moodleoverflow notifies the users with the capability allowforcesubscribe, but not a manager
     * without it and not the author.
     */
    public function test_forced(): void {
        $this->getDataGenerator()->create_and_enrol($this->course, 'manager');
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::FORCED->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);

        $expected = [(int) $this->student->id, (int) $this->otherstudent->id];
        sort($expected);
        $this->assertEquals($expected, $this->get_recipients($question));
    }

    /**
     * Test, if a moodleoverflow with disabled subscriptions notifies nobody, also not users that subscribed before.
     */
    public function test_disabled(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::DISABLED->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $DB->insert_record('moodleoverflow_subscriptions', [
            'userid' => $this->student->id,
            'moodleoverflow' => $modflow->id,
            'preference' => subscription::OPTIN,
        ]);

        $this->assertEquals([], $this->get_recipients($question));
    }

    /**
     * Test, if the task sends exactly one mail to every recipient.
     */
    public function test_send_mails(): void {
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::OPTIONAL->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        subscription::subscribe($modflow, $this->student->id);
        subscription::subscribe_discussion($question->get_discussion(), $this->student->id);
        subscription::subscribe($modflow, $this->otherstudent->id);
        subscription::unsubscribe_discussion($question->get_discussion(), $this->otherstudent->id);

        $this->sink->clear();
        $this->run_task(new send_mails());

        $this->assertEquals([$this->student->email], array_column($this->sink->get_messages(), 'to'));
    }

    // Helper functions.

    /**
     * Returns the users that would get a mail about a post, sorted by id.
     * @param post $post
     * @return int[]
     */
    private function get_recipients(post $post): array {
        $records = array_filter(
            mail_manager::get_unmailed_posts(0, time() + 1),
            fn($record) => $record->postid == $post->get_id()
        );
        $userids = array_map('intval', array_column($records, 'usertoid'));
        sort($userids);
        return $userids;
    }
}
