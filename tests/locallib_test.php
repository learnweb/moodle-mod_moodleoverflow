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
 * Helper functions for PHPUnit tests.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../locallib.php');

/**
 * Phpunit Tests for locallib
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends advanced_testcase {

    public function setUp(): void {
        \mod_moodleoverflow\subscriptions::reset_moodleoverflow_cache();
    }

    public function tearDown(): void {
        \mod_moodleoverflow\subscriptions::reset_moodleoverflow_cache();
    }

    /**
     * Test subscription using automatic subscription on create.
     * @covers \mod_moodleoverflow\subscriptions Subscription of users as default.
     */
    public function test_moodleoverflow_auto_subscribe_on_create(): void {
        global $DB;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = [];

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_INITIALSUBSCRIBE]; // Automatic Subscription.
        $mo = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        // Get the module context.
        $cm = $DB->get_record('course_modules', ['id' => $mo->cmid]);
        $context = \context_module::instance($cm->id);

        $result = \mod_moodleoverflow\subscriptions::get_subscribed_users($mo, $context);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_moodleoverflow\subscriptions::is_subscribed($user->id, $mo, $context));
        }
    }

    /**
     * Test subscription using forced subscription on create.
     * @covers \mod_moodleoverflow\subscriptions sorced Subscription of users.
     */
    public function test_moodleoverflow_forced_subscribe_on_create(): void {
        global $DB;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = [];

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE];
        $mo = $this->getDataGenerator()->create_module('moodleoverflow', $options);

        $cm = $DB->get_record('course_modules', ['id' => $mo->cmid]);
        $context = \context_module::instance($cm->id);

        $result = \mod_moodleoverflow\subscriptions::get_subscribed_users($mo, $context);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_moodleoverflow\subscriptions::is_subscribed($user->id, $mo, $context));
        }
    }

    /**
     * Test subscription using optional subscription on create.
     * @covers \mod_moodleoverflow\subscriptions optional subscription.
     */
    public function test_moodleoverflow_optional_subscribe_on_create(): void {
        global $DB;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = [];

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE]; // Subscription optional.
        $mo = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $cm = $DB->get_record('course_modules', ['id' => $mo->cmid]);
        $context = \context_module::instance($cm->id);

        $result = \mod_moodleoverflow\subscriptions::get_subscribed_users($mo, $context);
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_moodleoverflow\subscriptions::is_subscribed($user->id, $mo, $context));
        }
    }

    /**
     * Test subscription using disallow subscription on create.
     * @covers \mod_moodleoverflow\subscriptions prohibit Subscription of users.
     */
    public function test_moodleoverflow_disallow_subscribe_on_create(): void {
        global $DB;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = [];

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = ['course' => $course->id, 'forcesubscribe' => MOODLEOVERFLOW_DISALLOWSUBSCRIBE]; // Subscription prevented.
        $mo = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        $cm = $DB->get_record('course_modules', ['id' => $mo->cmid]);
        $context = \context_module::instance($cm->id);

        $result = \mod_moodleoverflow\subscriptions::get_subscribed_users($mo, $context);
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_moodleoverflow\subscriptions::is_subscribed($user->id, $mo, $context));
        }
    }

}
