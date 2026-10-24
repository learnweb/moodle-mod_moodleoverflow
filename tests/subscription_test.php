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
 * PHP Unit Tests for the subscription service.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use core\exception\coding_exception;
use mod_moodleoverflow\event\discussion_subscription_created;
use mod_moodleoverflow\event\subscription_created;
use mod_moodleoverflow\event\subscription_deleted;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\service\subscription;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

/**
 * Tests the rule "is subscribed", the subscription actions and the side effects of the subscription service.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\subscription
 */
final class subscription_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var \stdClass A manager of the course, not subscribed by default (no allowforcesubscribe). */
    private $manager;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
        $this->manager = $this->getDataGenerator()->create_and_enrol($this->course, 'manager');
    }

    // The rule.

    /**
     * Test, if the choice for the discussion counts before the choice for the moodleoverflow, which counts before the
     * default of the subscription mode. The recipients of mails follow the same rule.
     * @dataProvider rule_provider
     * @param subscription_mode $mode
     * @param bool $isdefault Whether the user is subscribed by default (student) or not (manager)
     * @param int|null $modflowchoice The choice for the moodleoverflow, null for none
     * @param int|null $discussionchoice The choice for the discussion, null for none
     * @param bool $expected
     */
    public function test_rule(
        subscription_mode $mode,
        bool $isdefault,
        ?int $modflowchoice,
        ?int $discussionchoice,
        bool $expected
    ): void {
        $userid = (int) ($isdefault ? $this->student->id : $this->manager->id);
        $modflow = $this->create_modflow($mode);
        $discussion = $this->generator->create_question($modflow, $this->teacher)->get_discussion();
        if ($modflowchoice !== null) {
            $this->add_choice($modflow, null, $userid, $modflowchoice);
        }
        if ($discussionchoice !== null) {
            $this->add_choice($modflow, $discussion, $userid, $discussionchoice);
        }

        $this->assertSame($expected, subscription::is_subscribed_to_discussion($discussion, $userid));
        $this->assertSame($expected, in_array($userid, subscription::get_recipients($discussion), true));
    }

    /**
     * Subscription modes, choices and whether the user is subscribed to the discussion.
     * @return array
     */
    public static function rule_provider(): array {
        $in = subscription::OPTIN;
        $out = subscription::OPTOUT;
        return [
            'optional, no choice' => [subscription_mode::OPTIONAL, true, null, null, false],
            'optional, moodleoverflow opt-in' => [subscription_mode::OPTIONAL, true, $in, null, true],
            'optional, discussion opt-in' => [subscription_mode::OPTIONAL, true, null, $in, true],
            'optional, discussion opt-out wins' => [subscription_mode::OPTIONAL, true, $in, $out, false],
            'optional, discussion opt-in wins' => [subscription_mode::OPTIONAL, true, $out, $in, true],
            'auto, default subscriber' => [subscription_mode::AUTO, true, null, null, true],
            'auto, no default subscriber' => [subscription_mode::AUTO, false, null, null, false],
            'auto, no default subscriber, opt-in' => [subscription_mode::AUTO, false, $in, null, true],
            'auto, moodleoverflow opt-out' => [subscription_mode::AUTO, true, $out, null, false],
            'auto, discussion opt-out' => [subscription_mode::AUTO, true, null, $out, false],
            'auto, discussion opt-in after opt-out' => [subscription_mode::AUTO, true, $out, $in, true],
            'forced, default subscriber' => [subscription_mode::FORCED, true, null, null, true],
            'forced, default subscriber, opt-outs do not count' => [subscription_mode::FORCED, true, $out, $out, true],
            'forced, no default subscriber' => [subscription_mode::FORCED, false, null, null, false],
            'forced, no default subscriber, opt-in' => [subscription_mode::FORCED, false, $in, null, true],
            'forced, no default subscriber, discussion opt-out' => [subscription_mode::FORCED, false, $in, $out, false],
            'disabled, default subscriber' => [subscription_mode::DISABLED, true, null, null, false],
            'disabled, opt-ins do not count' => [subscription_mode::DISABLED, false, $in, $in, false],
        ];
    }

    /**
     * Test, if a choice for a discussion does not subscribe the user to the whole moodleoverflow.
     */
    public function test_is_subscribed_ignores_discussion_choices(): void {
        $modflow = $this->create_modflow(subscription_mode::OPTIONAL);
        $discussion = $this->generator->create_question($modflow, $this->teacher)->get_discussion();

        $this->add_choice($modflow, $discussion, $this->student->id, subscription::OPTIN);
        $this->assertFalse(subscription::is_subscribed($modflow, $this->student->id));

        $this->add_choice($modflow, null, $this->student->id, subscription::OPTIN);
        $this->assertTrue(subscription::is_subscribed($modflow, $this->student->id));
    }

    /**
     * Test, if every subscribed user is a recipient exactly once, also users without an enrolment.
     */
    public function test_get_recipients(): void {
        $modflow = $this->create_modflow(subscription_mode::AUTO);
        $discussion = $this->generator->create_question($modflow, $this->teacher)->get_discussion();

        // The student is subscribed by default, through the moodleoverflow and through the discussion.
        $this->add_choice($modflow, null, $this->student->id, subscription::OPTIN);
        $this->add_choice($modflow, $discussion, $this->student->id, subscription::OPTIN);
        $this->add_choice($modflow, null, $this->otherstudent->id, subscription::OPTOUT);
        subscription::subscribe($modflow, get_admin()->id);

        $recipients = subscription::get_recipients($discussion);
        sort($recipients);
        $expected = [(int) $this->teacher->id, (int) $this->student->id, (int) get_admin()->id];
        sort($expected);
        $this->assertSame($expected, $recipients);
    }

    // The actions.

    /**
     * Test, if subscribing replaces the choices for discussions and a second call changes nothing.
     */
    public function test_subscribe(): void {
        global $DB;
        $modflow = $this->create_modflow(subscription_mode::OPTIONAL);
        $discussion = $this->generator->create_question($modflow, $this->teacher)->get_discussion();
        $this->add_choice($modflow, $discussion, $this->student->id, subscription::OPTOUT);

        $sink = $this->redirectEvents();
        subscription::subscribe($modflow, $this->student->id);
        subscription::subscribe($modflow, $this->student->id);
        $events = $sink->get_events();

        $this->assertTrue(subscription::is_subscribed_to_discussion($discussion, $this->student->id));
        $this->assertFalse($DB->record_exists('moodleoverflow_discuss_subs', ['userid' => $this->student->id]));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(subscription_created::class, $events[0]);
    }

    /**
     * Test, if a default subscriber of an auto moodleoverflow stays unsubscribed.
     */
    public function test_unsubscribe(): void {
        global $DB;
        $modflow = $this->create_modflow(subscription_mode::AUTO);

        $sink = $this->redirectEvents();
        subscription::unsubscribe($modflow, $this->student->id);
        $events = $sink->get_events();

        $this->assertFalse(subscription::is_subscribed($modflow, $this->student->id));
        $this->assertEquals(subscription::OPTOUT, $DB->get_field(
            'moodleoverflow_subscriptions',
            'preference',
            ['userid' => $this->student->id, 'moodleoverflow' => $modflow->id]
        ));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(subscription_deleted::class, $events[0]);
    }

    /**
     * Test, if subscribing to and unsubscribing from a discussion change one row.
     */
    public function test_discussion_actions(): void {
        global $DB;
        $modflow = $this->create_modflow(subscription_mode::OPTIONAL);
        $discussion = $this->generator->create_question($modflow, $this->teacher)->get_discussion();

        $sink = $this->redirectEvents();
        subscription::subscribe_discussion($discussion, $this->student->id);
        $events = $sink->get_events();
        $this->assertTrue(subscription::is_subscribed_to_discussion($discussion, $this->student->id));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(discussion_subscription_created::class, $events[0]);
        $this->assertEquals($discussion->get_id(), $events[0]->other['discussion']);

        subscription::unsubscribe_discussion($discussion, $this->student->id);
        $this->assertFalse(subscription::is_subscribed_to_discussion($discussion, $this->student->id));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', ['userid' => $this->student->id]));
    }

    /**
     * Test, if a user that cannot change the subscription is refused.
     */
    public function test_unsubscribe_refused(): void {
        $modflow = $this->create_modflow(subscription_mode::FORCED);

        $this->expectException(\moodle_exception::class);
        subscription::unsubscribe($modflow, $this->student->id);
    }

    // The side effects.

    /**
     * Test, in which cases a user follows the discussion they posted in.
     * @dataProvider follow_on_post_provider
     * @param subscription_mode $mode
     * @param int|null $discussionchoice The choice for the discussion before posting, null for none
     * @param int $autosubscribe The profile setting of the user
     * @param int|null $expected The choice for the discussion after posting, null for none
     */
    public function test_follow_on_post(
        subscription_mode $mode,
        ?int $discussionchoice,
        int $autosubscribe,
        ?int $expected
    ): void {
        global $DB;
        $modflow = $this->create_modflow($mode);
        $discussion = $this->generator->create_question($modflow, $this->teacher)->get_discussion();
        $DB->set_field('user', 'autosubscribe', $autosubscribe, ['id' => $this->student->id]);
        if ($discussionchoice !== null) {
            $this->add_choice($modflow, $discussion, $this->student->id, $discussionchoice);
        }

        subscription::follow_on_post($discussion, $this->student->id);

        $preference = $DB->get_field(
            'moodleoverflow_discuss_subs',
            'preference',
            ['userid' => $this->student->id, 'discussion' => $discussion->get_id()]
        );
        $this->assertSame($expected, $preference === false ? null : (int) $preference);
    }

    /**
     * Subscription modes, choices and the profile setting, and the choice for the discussion after posting.
     * @return array
     */
    public static function follow_on_post_provider(): array {
        return [
            'follows' => [subscription_mode::OPTIONAL, null, 1, subscription::OPTIN],
            'keeps the opt-out' => [subscription_mode::OPTIONAL, subscription::OPTOUT, 1, subscription::OPTOUT],
            'profile setting off' => [subscription_mode::OPTIONAL, null, 0, null],
            'already subscribed by default' => [subscription_mode::AUTO, null, 1, null],
            'forced' => [subscription_mode::FORCED, null, 1, null],
            'disabled' => [subscription_mode::DISABLED, null, 1, null],
        ];
    }

    /**
     * Test, if deleting by discussion keeps the choices for the moodleoverflow and parameters are combined.
     */
    public function test_delete_subscriptions(): void {
        global $DB;
        $modflow = $this->create_modflow(subscription_mode::OPTIONAL);
        $other = $this->create_modflow(subscription_mode::OPTIONAL);
        $discussion = $this->generator->create_question($modflow, $this->teacher)->get_discussion();
        foreach ([$this->student->id, $this->otherstudent->id] as $userid) {
            $this->add_choice($modflow, null, $userid, subscription::OPTIN);
            $this->add_choice($modflow, $discussion, $userid, subscription::OPTOUT);
            $this->add_choice($other, null, $userid, subscription::OPTIN);
        }

        subscription::delete_subscriptions(discussid: $discussion->get_id());
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs'));
        $this->assertEquals(4, $DB->count_records('moodleoverflow_subscriptions'));

        subscription::delete_subscriptions(userid: $this->student->id, modflowid: $modflow->id);
        $this->assertFalse(subscription::is_subscribed($modflow, $this->student->id));
        $this->assertTrue(subscription::is_subscribed($other, $this->student->id));
        $this->assertTrue(subscription::is_subscribed($modflow, $this->otherstudent->id));

        $this->expectException(coding_exception::class);
        subscription::delete_subscriptions();
    }

    // Helper functions.

    /**
     * Creates a moodleoverflow without subscription rows.
     * @param subscription_mode $mode
     * @return moodleoverflow
     */
    private function create_modflow(subscription_mode $mode): moodleoverflow {
        global $DB;
        $modflow = $this->create_moodleoverflow(['forcesubscribe' => $mode->value]);
        // Creating an auto moodleoverflow still subscribes all enrolled users through the old observer.
        $DB->delete_records('moodleoverflow_subscriptions', ['moodleoverflow' => $modflow->id]);
        return $modflow;
    }

    /**
     * Stores a choice of a user directly in the database.
     * @param moodleoverflow $modflow
     * @param discussion|null $discussion null for a choice for the whole moodleoverflow
     * @param int $userid
     * @param int $preference subscription::OPTIN or subscription::OPTOUT
     */
    private function add_choice(moodleoverflow $modflow, ?discussion $discussion, int $userid, int $preference): void {
        global $DB;
        $row = ['userid' => $userid, 'moodleoverflow' => $modflow->id, 'preference' => $preference];
        if ($discussion) {
            $DB->insert_record('moodleoverflow_discuss_subs', $row + ['discussion' => $discussion->get_id()]);
        } else {
            $DB->insert_record('moodleoverflow_subscriptions', $row);
        }
    }
}
