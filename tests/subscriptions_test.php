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
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');

/**
 * Class mod_moodleoverflow_subscriptions_testcase.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \subscriptions
 */
final class subscriptions_test extends advanced_testcase {

    /**
     * Test setUp.
     */
    public function setUp(): void {
        parent::setUp();
        // Clear all caches.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();
    }

    /**
     * Test tearDown.
     */
    public function tearDown(): void {
        // Clear all caches.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();
        parent::tearDown();
    }

    /**
     * Helper to create the required number of users in the specified course.
     * Users are enrolled as students.
     *
     * @param  \stdClass $course The course object
     * @param int      $count  The number of users to create
     *
     * @return array The users created
     */
    protected function helper_create_users($course, $count) {
        $users = [];

        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Crate a new discussion and post within the moodleoverflow.
     *
     * @param  \stdClass $moodleoverflow The moodleoverflow to post in
     * @param  \stdClass $author         The author to post as
     *
     * @return array Array containing the discussion object and the post object.
     */
    protected function helper_post_to_moodleoverflow($moodleoverflow, $author) {
        global $DB;

        // Retrieve the generator.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');

        // Create a discussion in the moodleoverflow, add a post to that discussion.
        $record = new  \stdClass();
        $record->course = $moodleoverflow->course;
        $record->userid = $author->id;
        $record->moodleoverflow = $moodleoverflow->id;
        $discussion = $generator->create_discussion($record, $moodleoverflow);

        // Retrieve the post which was created.
        $post = $DB->get_record('moodleoverflow_posts', ['discussion' => $discussion->id]);

        // Return the discussion and the post.
        return [$discussion->id, $post];
    }

    /**
     * Test to set subscription modes.
     */
    public function test_subscription_modes(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $modulecontext = \context_module::instance($moodleoverflow->cmid);

        // Create a user enrolled in the course as a student.
        list ($user) = $this->helper_create_users($course, 1);

        // Must be logged in as the current user.
        $this->setUser($user);

        // Test the forced subscription.
        subscriptions::set_subscription_mode($moodleoverflow->id, MOODLEOVERFLOW_FORCESUBSCRIBE);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id]);
        $this->assertEquals(MOODLEOVERFLOW_FORCESUBSCRIBE,
            subscriptions::get_subscription_mode($moodleoverflow));
        $this->assertTrue(subscriptions::is_forcesubscribed($moodleoverflow));
        $this->assertFalse(subscriptions::is_subscribable($moodleoverflow, $modulecontext));
        $this->assertFalse(subscriptions::subscription_disabled($moodleoverflow));

        // Test the disallowed subscription.
        subscriptions::set_subscription_mode($moodleoverflow->id, MOODLEOVERFLOW_DISALLOWSUBSCRIBE);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id]);
        $this->assertTrue(subscriptions::subscription_disabled($moodleoverflow));
        $this->assertFalse(subscriptions::is_subscribable($moodleoverflow, $modulecontext));
        $this->assertFalse(subscriptions::is_forcesubscribed($moodleoverflow));

        // Test the initial subscription.
        subscriptions::set_subscription_mode($moodleoverflow->id, MOODLEOVERFLOW_INITIALSUBSCRIBE);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id]);
        $this->assertTrue(subscriptions::is_subscribable($moodleoverflow, $modulecontext));
        $this->assertFalse(subscriptions::subscription_disabled($moodleoverflow));
        $this->assertFalse(subscriptions::is_forcesubscribed($moodleoverflow));

        // Test the choose subscription.
        subscriptions::set_subscription_mode($moodleoverflow->id, MOODLEOVERFLOW_CHOOSESUBSCRIBE);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id]);
        $this->assertTrue(subscriptions::is_subscribable($moodleoverflow, $modulecontext));
        $this->assertFalse(subscriptions::subscription_disabled($moodleoverflow));
        $this->assertFalse(subscriptions::is_forcesubscribed($moodleoverflow));
    }

    /**
     * Test fetching unsubscribable moodleoverflows.
     */
    public function test_unsubscribable_moodleoverflows(): void {
        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id];
        $mof = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $mof->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create a user enrolled in the course as a student.
        list ($user) = $this->helper_create_users($course, 1);

        // Must be logged in as the current user.
        $this->setUser($user);

        // Without any subscriptions, there should be nothing returned.
        $result = subscriptions::get_unsubscribable_moodleoverflows();
        $this->assertEquals(0, count($result));

        // Create the moodleoverflows.
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE];
        $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_DISALLOWSUBSCRIBE];
        $disallow = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $choose = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // At present the user is only subscribed to the initial moodleoverflow.
        $result = subscriptions::get_unsubscribable_moodleoverflows();
        $this->assertEquals(1, count($result));

        // Ensure that the user is enrolled in all of the moodleoverflows execpt force subscribe.
        subscriptions::subscribe_user($user->id, $disallow, $modulecontext);
        subscriptions::subscribe_user($user->id, $choose, $modulecontext);

        // At present the user  is subscribed to all three moodleoverflows.
        $result = subscriptions::get_unsubscribable_moodleoverflows();
        $this->assertEquals(3, count($result));
    }

    /**
     * Test that toggeling the moodleoverflow-level subscription for a different user does not affect their discussion-level.
     */
    public function test_moodleoverflow_toggle_as_other(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course with a moodleoverflow. Get the module context and enroll a user in the course as a student.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);
        list ($author) = $this->helper_create_users($course, 1);

        // Post a discussion to the moodleoverflow.
        $discussion = new \stdClass();
        list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
        unset($post);
        $discussion->moodleoverflow = $moodleoverflow->id;

        // Check that the user is currently not subscribed to the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // Check that the user is unsubscribed from the discussion too.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // Declare the options for the subscription tables.
        $discussoptions = ['userid' => $author->id, 'discussion' => $discussion->id];
        $subscriptionoptions = ['userid' => $author->id, 'moodleoverflow' => $moodleoverflow->id];

        // Check thast we have no records in either on the subscription tables.
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Subscribing to the moodleoverflow should add a record to the moodleoverflow subscription table.
        subscriptions::subscribe_user($author->id, $moodleoverflow, $modulecontext);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Unsubscribing should remove the record from the moodleoverflow subscription table. The discussion table stays the same.
        subscriptions::unsubscribe_user($author->id, $moodleoverflow, $modulecontext);
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Subscribing to the discussion should only add a record to the discussion subscription table.
        subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext);
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Unsubscribing should remove the record from the moodleoverflow subscriptions and modify the discussion subscriptions.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // Resubscribe to the discussion so that we can check the effect of moodleoverflow-level subscriptions.
        subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // Subscribing to the moodleoverflow should have no effect on the discussion subscription.
        subscriptions::subscribe_user($author->id, $moodleoverflow, $modulecontext);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Unsubbing from the moodleoverflow should have no effect on the discussion subscription.
        subscriptions::unsubscribe_user($author->id, $moodleoverflow, $modulecontext);
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Subscribing to the moodleoverflow should remove the per-discussion subscription preference if the user wanted the change.
        subscriptions::subscribe_user($author->id, $moodleoverflow, $modulecontext, true);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Now unsubscribe from the current discussion whilst being subscribed to the moodleoverflow as a whole.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Unsubscribing from the moodleoverflow should remove per-discussion subscription preference if the user wanted the change.
        subscriptions::unsubscribe_user($author->id, $moodleoverflow, $modulecontext, true);
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Subscribe to the discussion.
        subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext);
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Subscribe to the moodleoverflow without removing the discussion preferences.
        subscriptions::subscribe_user($author->id, $moodleoverflow, $modulecontext);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Unsubscribe from the discussion should result in a change.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
    }

    /**
     * Test that a user unsubscribed from a moodleoverflow is not subscribed to it's discussions by default.
     */
    public function test_moodleoverflow_discussion_subscription_moodleoverflow_unsubscribed(): void {
        // Reset the database after the test.
        $this->resetAfterTest(true);

        // Create a course with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Create users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 1);

        // Check that the user is currently not subscribed to the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $moodleoverflow));

        // Post a discussion to the moodleoverflow.
        list($discussion, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
        unset($post);

        // Check that the user is unsubscribed from the discussion too.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow,
            $moodleoverflow, $discussion));
    }

    /**
     * Test that the act of subscribing to a moodleoverflow subscribes the user to it's discussions by default.
     */
    public function test_moodleoverflow_discussion_subscription_moodleoverflow_subscribed(): void {
        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 1);

        // Enrol the user in the moodleoverflow.
        // If a subscription was added, we get the record ID.
        $this->assertIsInt(subscriptions::subscribe_user($author->id,
            $moodleoverflow, $modulecontext));

        // If we already have a subscription when subscribing the user, we get a boolean (true).
        $this->assertTrue(subscriptions::subscribe_user($author->id, $moodleoverflow, $modulecontext));

        // Check that the user is currently subscribed to the moodleoverflow.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // Post a discussion to the moodleoverflow.
        list($discussion, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
        unset($post);

        // Check that the user is subscribed to the discussion too.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext,
            $discussion));
    }

    /**
     * Test that a user unsubscribed from a moodleoverflow can be subscribed to a discussion.
     */
    public function test_moodleoverflow_discussion_subscription_moodleoverflow_unsubscribed_discussion_subscribed(): void {
        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course and a new moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create a user enrolled in the course as a student.
        list($author) = $this->helper_create_users($course, 1);

        // Check that the user is currently not subscribed to the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $moodleoverflow));

        // Post a discussion to the moodleoverflow.
        $discussion = new \stdClass();
        list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
        unset($post);
        $discussion->moodleoverflow = $moodleoverflow->id;

        // Attempting to unsubscribe from the discussion should not make a change.
        $this->assertFalse(subscriptions::unsubscribe_user_from_discussion($author->id,
            $discussion, $modulecontext));

        // Then subscribe them to the discussion.
        $this->assertTrue(subscriptions::subscribe_user_to_discussion($author->id,
            $discussion, $modulecontext));

        // Check that the user is still unsubscribed from the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $moodleoverflow));

        // But subscribed to the discussion.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext,
            $discussion->id));
    }

    /**
     * Test that a user subscribed to a moodleoverflow can be unsubscribed from a discussion.
     */
    public function test_moodleoverflow_discussion_subscription_moodleoverflow_subscribed_discussion_unsubscribed(): void {
        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create two users enrolled in the course as students.
        list($author) = $this->helper_create_users($course, 2);

        // Enrol the student in the moodleoverflow.
        subscriptions::subscribe_user($author->id, $moodleoverflow, $modulecontext);

        // Check that the user is currently subscribed to the moodleoverflow.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // Post a discussion to the moodleoverflow.
        $discussion = new \stdClass();
        list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
        unset($post);
        $discussion->moodleoverflow = $moodleoverflow->id;

        // Then unsubscribe them from the discussion.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);

        // Check that the user is still subscribed to the moodleoverflow.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // But unsubscribed from the discussion.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext,
            $discussion->id));
    }

    /**
     * Test the effect of toggling the discussion subscription status when subscribed to the moodleoverflow.
     */
    public function test_moodleoverflow_discussion_toggle_moodleoverflow_subscribed(): void {
        global $DB;
        // Reset the database after testing.
        $this->resetAfterTest();

        // Create a course with a moodleoverflow and enroll two users in the course as students and in the moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);
        list($author) = $this->helper_create_users($course, 2);
        subscriptions::subscribe_user($author->id, $moodleoverflow, $modulecontext);

        // Post a discussion to the moodleoverflow.
        $discussion = new \stdClass();
        list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
        unset($post);
        $discussion->moodleoverflow = $moodleoverflow->id;

        // Check that the user is currently subscribed to the moodleoverflow and initially subscribed to that discussion.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // An attempt to subscribe again should result in a falsey return to indicate that no change was made.
        $this->assertFalse(subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext));

        // Declare the options for the subscription tables.
        $discussoptions = ['userid' => $author->id, 'discussion' => $discussion->id];
        $subscriptionoptions = ['userid' => $author->id, 'moodleoverflow' => $moodleoverflow->id];

        // And there should be no discussion subscriptions (and one moodleoverflow subscription).
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // Unsubscribe them from the discussion while still subscribe the moodleoverflow. Attempt to unsubscribe again should fail.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));
        $this->assertFalse(subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext));

        // And there should be a discussion subscriptions (and one moodleoverflow subscription).
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // But unsubscribed from the discussion.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // There should be one record in the discussion and moodleoverflow subscription tracking table.
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // Now subscribe the user again to the discussion. Now discussion and moodleoverflow should be subscribed.
        subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext);
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // And one in the moodleoverflow subscription and no record in the discussion tracking table.
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // And unsubscribe again. Only moodleoverflow should be subscribed. Discussion should be unsubscribed.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // There should be one record in the moodleoverflow and discussion subscription tracking table.
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // And subscribe the user again to the discussion. Both should be subscribed.
        subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext);
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // There should be no record in the discussion and one in the moodleoverflow subscription tracking table.
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // And unsubscribe again from the discussion. Only moodleoverflow should be subscribed.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // There should be a record in the discussion and one in the moodleoverflow subscription tracking table.
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // Now unsubscribe the user from the moodleoverflow.
        $this->assertTrue(subscriptions::unsubscribe_user($author->id, $moodleoverflow, $modulecontext, true));

        // This removes both the moodleoverflow, and the moodleoverflow records.
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));

        // And should have reset the discussion cache value.
        $result = subscriptions::fetch_discussion_subscription($moodleoverflow->id, $author->id);
        $this->assertIsArray($result);
        $this->assertFalse(isset($result[$discussion->id]));
    }

    /**
     * Test the effect of toggling the discussion subscription status when unsubscribed from the moodleoverflow.
     */
    public function test_moodleoverflow_discussion_toggle_moodleoverflow_unsubscribed(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow and get the module context. Create two users enrolled inthe course as students.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);
        list($author) = $this->helper_create_users($course, 2);

        // Check that the user is currently unsubscribed to the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // Post a discussion to the moodleoverflow.
        $discussion = new \stdClass();
        list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
        unset($post);
        $discussion->moodleoverflow = $moodleoverflow->id;

        // Declare the options for the subscription tables.
        $discussoptions = ['userid' => $author->id, 'discussion' => $discussion->id];

        // Check that the user is initially unsubscribed to that discussion.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // Then subscribe them to the discussion.
        $this->assertTrue(subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext));

        // An attempt to subscribe again should result in a falsey return to indicate that no change was made.
        $this->assertFalse(subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext));

        // Check that the user is still unsubscribed from the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // But subscribed to the discussion.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // There should be a record in the discussion subscription tracking table.
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Now unsubscribe the user again from the discussion.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);

        // Check that the user is still unsubscribed from the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // And is unsubscribed from the discussion again.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // There should be no record in the discussion subscription tracking table.
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // And subscribe the user again to the discussion.
        subscriptions::subscribe_user_to_discussion($author->id, $discussion, $modulecontext);

        // And is subscribed to the discussion again.
        $this->assertTrue(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // Check that the user is still unsubscribed from the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // There should be a record in the discussion subscription tracking table.
        $options = ['userid' => $author->id, 'discussion' => $discussion->id];
        $count = $DB->count_records('moodleoverflow_discuss_subs', $options);
        $this->assertEquals(1, $count);

        // And unsubscribe again.
        subscriptions::unsubscribe_user_from_discussion($author->id, $discussion, $modulecontext);

        // But unsubscribed from the discussion.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext, $discussion->id));

        // Check that the user is still unsubscribed from the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($author->id, $moodleoverflow, $modulecontext));

        // There should be no record in the discussion subscription tracking table.
        $options = ['userid' => $author->id, 'discussion' => $discussion->id];
        $count = $DB->count_records('moodleoverflow_discuss_subs', $options);
        $this->assertEquals(0, $count);
    }

    /**
     * Test that the correct users are returned when fetching subscribed users
     * from a moodleoverflow where users can choose to subscribe and unsubscribe.
     */
    public function test_fetch_subscribed_users_subscriptions(): void {
        global $CFG;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow. where users are initially subscribed and get the module context.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create five user enrolled in the course as a student.
        $usercount = 5;
        $users = $this->helper_create_users($course, $usercount);

        // All users should be subscribed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount, count($subscribers));

        // Subscribe the guest user too to the moodleoverflow - they should never be returned by this function.
        $this->getDataGenerator()->enrol_user($CFG->siteguest, $course->id);
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount, count($subscribers));

        // Unsubscribe 2 users.
        for ($i = 0; $i < 2; $i++) {
            subscriptions::unsubscribe_user($users[$i]->id, $moodleoverflow, $modulecontext);
        }

        // The subscription count should now take into account those users who have been unsubscribed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount - 2, count($subscribers));
    }

    /**
     * Test that the correct users are returned hwen fetching subscribed users from a moodleoverflow where users are forcibly
     * subscribed.
     */
    public function test_fetch_subscribed_users_forced(): void {
        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow. where users are initially subscribed.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create some user enrolled in the course as a student.
        $usercount = 5;
        $this->helper_create_users($course, $usercount);

        // All users should be subscribed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount, count($subscribers));
    }

    /**
     * Test that unusual combinations of discussion subscriptions do not affect the subscribed user list.
     */
    public function test_fetch_subscribed_users_discussion_subscriptions(): void {
        global $DB;

        // Reset after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow. where users are initially subscribed.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create some user enrolled in the course as a student.
        $usercount = 5;
        $users = $this->helper_create_users($course, $usercount);

        // Create the discussion.
        $discussion = new \stdClass();
        list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $users[0]);
        unset($post);
        $discussion->moodleoverflow = $moodleoverflow->id;

        // All users should be subscribed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount, count($subscribers));
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext, null, true);
        $this->assertEquals($usercount, count($subscribers));

        subscriptions::unsubscribe_user_from_discussion($users[0]->id, $discussion, $modulecontext);

        // All users should be subscribed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount, count($subscribers));

        // All users should be subscribed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext, null, true);
        $this->assertEquals($usercount, count($subscribers));

        // Manually insert an extra subscription for one of the users.
        $record = new  \stdClass();
        $record->userid = $users[2]->id;
        $record->moodleoverflow = $moodleoverflow->id;
        $record->discussion = $discussion->id;
        $record->preference = time();
        $DB->insert_record('moodleoverflow_discuss_subs', $record);

        // The discussion count should not have changed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount, count($subscribers));
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext, null, true);
        $this->assertEquals($usercount, count($subscribers));

        // Unsubscribe 2 users.
        subscriptions::unsubscribe_user($users[0]->id, $moodleoverflow, $modulecontext);
        subscriptions::unsubscribe_user($users[1]->id, $moodleoverflow, $modulecontext);

        // The subscription count should now take into account those users who have been unsubscribed.
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount - 2, count($subscribers));
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext, null, true);
        $this->assertEquals($usercount - 2, count($subscribers));

        // Now subscribe one of those users back to the discussion.
        subscriptions::subscribe_user_to_discussion($users[0]->id, $discussion, $modulecontext);
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext);
        $this->assertEquals($usercount - 2, count($subscribers));
        $subscribers = subscriptions::get_subscribed_users($moodleoverflow, $modulecontext, null, true);
        $this->assertEquals($usercount - 1, count($subscribers));
    }

    /**
     * Test whether a user is force-subscribed to a moodleoverflow.
     */
    public function test_force_subscribed_to_moodleoverflow(): void {
        global $DB;
        // Reset database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();

        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Remove the allowforcesubscribe capability from the user.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $context = \context_module::instance($cm->id);

        // Create a user enrolled in the course as a student.
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $roleids['student']);

        // Check that the user is currently subscribed to the moodleoverflow.
        $this->assertTrue(subscriptions::is_subscribed($user->id, $moodleoverflow, $context));

        assign_capability('mod/moodleoverflow:allowforcesubscribe', CAP_PROHIBIT, $roleids['student'],
            $context);
        $context->mark_dirty();
        $this->assertFalse(has_capability('mod/moodleoverflow:allowforcesubscribe', $context, $user->id));
    }

    /**
     * Test that the subscription cache can be pre-filled.
     */
    public function test_subscription_cache_prefill(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Reset the subscription cache.
        subscriptions::reset_moodleoverflow_cache();

        // Filling the subscription cache should only use a single query, except for Postgres, which delegates actual reading
        // to Cursors, thus tripling the amount of queries. We intend to test the cache, though, so no worries.
        $this->assertNull(subscriptions::fill_subscription_cache($moodleoverflow->id));
        $postfillcount = $DB->perf_get_reads();

        // Now fetch some subscriptions from that moodleoverflow - these should use
        // the cache and not perform additional queries.
        foreach ($users as $user) {
            $this->assertTrue(subscriptions::fetch_subscription_cache($moodleoverflow->id, $user->id));
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals($finalcount, $postfillcount);
    }

    /**
     * Test that the subscription cache can filled user-at-a-time.
     */
    public function test_subscription_cache_fill(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();

        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Reset the subscription cache.
        subscriptions::reset_moodleoverflow_cache();

        // Filling the subscription cache should only use a single query.
        $startcount = $DB->perf_get_reads();

        // Fetch some subscriptions from that moodleoverflow - these should not use the cache and will perform additional queries.
        foreach ($users as $user) {
            $this->assertTrue(subscriptions::fetch_subscription_cache($moodleoverflow->id, $user->id));
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(20, $finalcount - $startcount);
    }

    /**
     * Test that the discussion subscription cache can filled course-at-a-time.
     */
    public function test_discussion_subscription_cache_fill_for_course(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();

        // Create the moodleoverflows.
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_DISALLOWSUBSCRIBE];
        $disallowobject = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $chooseobject = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $initialobject = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Create some users and keep a reference to the first user.
        $users = $this->helper_create_users($course, 20);
        $user = reset($users);

        // Reset the subscription caches.
        subscriptions::reset_moodleoverflow_cache();

        $result = subscriptions::fill_subscription_cache_for_course($course->id, $user->id);
        $this->assertNull($result);
        $postfillcount = $DB->perf_get_reads();
        $this->assertFalse(subscriptions::fetch_subscription_cache($disallowobject->id, $user->id));
        $this->assertFalse(subscriptions::fetch_subscription_cache($chooseobject->id, $user->id));
        $this->assertTrue(subscriptions::fetch_subscription_cache($initialobject->id, $user->id));
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(0, $finalcount - $postfillcount);

        // Test for all users.
        foreach ($users as $user) {
            $result = subscriptions::fill_subscription_cache_for_course($course->id, $user->id);
            $this->assertNull($result);
            $this->assertFalse(subscriptions::fetch_subscription_cache($disallowobject->id, $user->id));
            $this->assertFalse(subscriptions::fetch_subscription_cache($chooseobject->id, $user->id));
            $this->assertTrue(subscriptions::fetch_subscription_cache($initialobject->id, $user->id));
        }
        $finalcount = $DB->perf_get_reads();
        $reads = $finalcount - $postfillcount;
        if ($reads === 20 || $reads === 60) {
            // Postgres uses cursors since M35 and therefore requires triple the amount of reads.
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false, 'Unexpected amount of reads required to fill discussion subscription cache for a course.');
        }
    }

    /**
     * Test that the discussion subscription cache can be forcibly updated for a user.
     */
    public function test_discussion_subscription_cache_prefill(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Post some discussions to the moodleoverflow.
        $discussions = [];
        $author = $users[0];
        for ($i = 0; $i < 20; $i++) {
            $discussion = new \stdClass();
            list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
            unset($post);
            $discussion->moodleoverflow = $moodleoverflow->id;
            $discussions[] = $discussion;
        }

        // Unsubscribe half the users from the half the discussions.
        $moodleoverflowcount = 0;
        $usercount = 0;
        foreach ($discussions as $data) {
            if ($moodleoverflowcount % 2) {
                continue;
            }
            foreach ($users as $user) {
                if ($usercount % 2) {
                    continue;
                }
                subscriptions::unsubscribe_user_from_discussion($user->id, $data, $modulecontext);
                $usercount++;
            }
            $moodleoverflowcount++;
        }

        // Reset the subscription caches.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();

        // Filling the discussion subscription cache should only use a single query.
        $this->assertNull(subscriptions::fill_discussion_subscription_cache($moodleoverflow->id));
        $postfillcount = $DB->perf_get_reads();

        // Now fetch some subscriptions from that moodleoverflow - these should use
        // the cache and not perform additional queries.
        foreach ($users as $user) {
            $result = subscriptions::fetch_discussion_subscription($moodleoverflow->id, $user->id);
            $this->assertIsArray($result);
        }
        $finalcount = $DB->perf_get_reads();
        $this->assertEquals(0, $finalcount - $postfillcount);
    }

    /**
     * Test that the discussion subscription cache can filled user-at-a-time.
     */
    public function test_discussion_subscription_cache_fill(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Create some users.
        $users = $this->helper_create_users($course, 20);

        // Post some discussions to the moodleoverflow.
        $discussions = [];
        $author = $users[0];
        for ($i = 0; $i < 20; $i++) {
            $discussion = new \stdClass();
            list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $author);
            unset($post);
            $discussion->moodleoverflow = $moodleoverflow->id;
            $discussions[] = $discussion;
        }

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);

        // Unsubscribe half the users from the half the discussions.
        $moodleoverflowcount = 0;
        $usercount = 0;
        foreach ($discussions as $data) {
            if ($moodleoverflowcount % 2) {
                continue;
            }
            foreach ($users as $user) {
                if ($usercount % 2) {
                    continue;
                }
                subscriptions::unsubscribe_user_from_discussion($user->id, $data, $modulecontext);
                $usercount++;
            }
            $moodleoverflowcount++;
        }

        // Reset the subscription caches.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();

        $startcount = $DB->perf_get_reads();

        // Now fetch some subscriptions from that moodleoverflow - these should use
        // the cache and not perform additional queries.
        foreach ($users as $user) {
            $result = subscriptions::fetch_discussion_subscription($moodleoverflow->id, $user->id);
            $this->assertIsArray($result);
        }
        $finalcount = $DB->perf_get_reads();
        $reads = $finalcount - $startcount;
        if ($reads === 20 || $reads === 60) {
            // Postgres uses cursors since M35 and therefore requires triple the amount of reads.
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false, 'Unexpected amount of reads required to fill discussion subscription cache.');
        }

    }

    /**
     * Test that after toggling the moodleoverflow subscription as another user,
     * the discussion subscription functionality works as expected.
     */
    public function test_moodleoverflow_subscribe_toggle_as_other_repeat_subscriptions(): void {
        global $DB;

        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow and get the module context. Create a user enrolled in the course as a student.
        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE];
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $modulecontext = \context_module::instance($cm->id);
        list($user) = $this->helper_create_users($course, 1);

        // Post a discussion to the moodleoverflow.
        $discussion = new \stdClass();
        list($discussion->id, $post) = $this->helper_post_to_moodleoverflow($moodleoverflow, $user);
        unset($post);
        $discussion->moodleoverflow = $moodleoverflow->id;

        // Declare the options for the subscription tables.
        $discussoptions = ['userid' => $user->id, 'discussion' => $discussion->id];
        $subscriptionoptions = ['userid' => $user->id, 'moodleoverflow' => $moodleoverflow->id];

        // Confirm that the user is currently not subscribed to the moodleoverflow.
        $this->assertFalse(subscriptions::is_subscribed($user->id, $moodleoverflow, $modulecontext));

        // Confirm that the user is unsubscribed from the discussion too.
        $this->assertFalse(subscriptions::is_subscribed($user->id, $moodleoverflow, $modulecontext, $discussion->id));

        // Confirm that we have no records in either of the subscription tables.
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Subscribing to the moodleoverflow should create a record in the subscriptions table,
        // but not the moodleoverflow discussion subscriptions table.
        subscriptions::subscribe_user($user->id, $moodleoverflow, $modulecontext);
        $this->assertEquals(1, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Now unsubscribe from the discussion. This should return true.
        $uid = $user->id;
        $this->assertTrue(subscriptions::unsubscribe_user_from_discussion($uid, $discussion, $modulecontext));

        // Attempting to unsubscribe again should return false because no change was made.
        $this->assertFalse(subscriptions::unsubscribe_user_from_discussion($uid, $discussion, $modulecontext));

        // Subscribing to the discussion again should return truthfully as the subscription preference was removed.
        $this->assertTrue(subscriptions::subscribe_user_to_discussion($user->id, $discussion, $modulecontext));

        // Attempting to subscribe again should return false because no change was made.
        $this->assertFalse(subscriptions::subscribe_user_to_discussion($user->id, $discussion, $modulecontext));

        // Now unsubscribe from the discussion. This should return true once more.
        $this->assertTrue(subscriptions::unsubscribe_user_from_discussion($uid, $discussion, $modulecontext));

        // And unsubscribing from the moodleoverflow but not as a request from the user should maintain their preference.
        subscriptions::unsubscribe_user($user->id, $moodleoverflow, $modulecontext);

        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));

        // Subscribing to the discussion should return truthfully because a change was made.
        $this->assertTrue(subscriptions::subscribe_user_to_discussion($user->id, $discussion, $modulecontext));
        $this->assertEquals(0, $DB->count_records('moodleoverflow_subscriptions', $subscriptionoptions));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_discuss_subs', $discussoptions));
    }

    /**
     * Returns a list of possible states.
     *
     * @return array
     */
    public static function is_subscribable_moodleoverflows() {
        return [['forcesubscribe' => MOODLEOVERFLOW_DISALLOWSUBSCRIBE], ['forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE],
                ['forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE], ['forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE], ];
    }

    /**
     * Returns whether a moodleoverflow is subscribable.
     *
     * @return array
     */
    public static function is_subscribable_provider(): array {
        $data = [];
        foreach (self::is_subscribable_moodleoverflows() as $moodleoverflow) {
            $data[] = [$moodleoverflow];
        }
        return $data;
    }

    /**
     * Tests if a moodleoverflow is subscribable when a user is logged out.
     *
     * @param array $options
     *
     * @dataProvider is_subscribable_provider
     * @return void
     */
    public function test_is_subscribable_logged_out($options): void {
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options['course'] = $course->id;
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        $this->assertFalse(subscriptions::is_subscribable($moodleoverflow, \context_module::instance($moodleoverflow->cmid)));
    }

    /**
     * Tests if a moodleoverflow is subscribable by a guest.
     *
     * @param array $options
     *
     * @dataProvider is_subscribable_provider
     * @return void
     */
    public function test_is_subscribable_is_guest($options): void {
        global $DB;
        $this->resetAfterTest(true);

        // Create a guest user.
        $guest = $DB->get_record('user', ['username' => 'guest']);
        $this->setUser($guest);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options['course'] = $course->id;
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        $this->assertFalse(subscriptions::is_subscribable($moodleoverflow, \context_module::instance($moodleoverflow->cmid)));
    }

    /**
     * Returns subscription options.
     * @return array
     */
    public static function is_subscribable_loggedin_provider(): array {
        return [
            [['forcesubscribe' => MOODLEOVERFLOW_DISALLOWSUBSCRIBE], false],
            [['forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE], true],
            [['forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE], true],
            [['forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE], false],
        ];
    }

    /**
     * Tests if a moodleoverflow is subscribable when a user is logged in.
     *
     * @param array $options
     * @param bool  $expect
     *
     * @dataProvider is_subscribable_loggedin_provider
     * @return void
     */
    public function test_is_subscribable_loggedin($options, $expect): void {
        // Reset the database after testing.
        $this->resetAfterTest(true);

        // Create a course, with a moodleoverflow.
        $course = $this->getDataGenerator()->create_course();
        $options['course'] = $course->id;
        $moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Create a new user.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $this->assertEquals($expect, subscriptions::is_subscribable($moodleoverflow,
            \context_module::instance($moodleoverflow->cmid)));
    }
}
