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

use advanced_testcase;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\readtracking;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * PHPUnit Tests for testing readtracking.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\readtracking
 */
final class readtracking_test extends advanced_testcase {
    /**
     * Test the logic in permissions::can_track().
     * @covers \mod_moodleoverflow\local\permissions::can_track
     */
    public function test_can_track(): void {

        // Reset after testing.
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OFF]; // Off.
        $mooff = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_FORCED]; // On.
        $moforce = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OPTIONAL]; // Optional.
        $mooptional = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        // Users: an enrolled student, a user that is not enrolled and the guest user.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $notenrolled = $this->getDataGenerator()->create_user();
        $guest = guest_user();

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $result = permissions::can_track($mooff, $student->id);
        $this->assertEquals(false, $result);

        // Moodleoverflow forced, should be on.
        $result = permissions::can_track($moforce, $student->id);
        $this->assertEquals(true, $result);

        // Moodleoverflow optional, should be on.
        $result = permissions::can_track($mooptional, $student->id);
        $this->assertEquals(true, $result);

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $result = permissions::can_track($mooff, $student->id);
        $this->assertEquals(false, $result);

        // Moodleoverflow forced, counts as optional now, should be on.
        $result = permissions::can_track($moforce, $student->id);
        $this->assertEquals(true, $result);

        // Moodleoverflow optional, should be on.
        $result = permissions::can_track($mooptional, $student->id);
        $this->assertEquals(true, $result);

        // Users that are not enrolled, guests and users that are not logged in can not track.
        $this->assertEquals(false, permissions::can_track($mooptional, $notenrolled->id));
        $this->assertEquals(false, permissions::can_track($mooptional, $guest->id));
        $this->assertEquals(false, permissions::can_track($mooptional, 0));

        // Read tracking switched off for the whole site, should be off.
        set_config('trackreadposts', 0, 'moodleoverflow');
        $result = permissions::can_track($mooptional, $student->id);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in readtracking::is_tracked() and the user preference set by start_tracking() / stop_tracking().
     * @covers \mod_moodleoverflow\local\service\readtracking::is_tracked
     */
    public function test_is_tracked(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OPTIONAL];
        $mooptional = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_FORCED];
        $moforce = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OFF];
        $mooff = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $this->assertEquals(false, readtracking::is_tracked($mooff, $student->id));

        // Moodleoverflow forced, should be on.
        $this->assertEquals(true, readtracking::is_tracked($moforce, $student->id));

        // Moodleoverflow optional, should be on.
        $this->assertEquals(true, readtracking::is_tracked($mooptional, $student->id));

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $this->assertEquals(false, readtracking::is_tracked($mooff, $student->id));

        // Moodleoverflow forced, counts as optional now, should be on.
        $this->assertEquals(true, readtracking::is_tracked($moforce, $student->id));

        // Moodleoverflow optional, should be on.
        $this->assertEquals(true, readtracking::is_tracked($mooptional, $student->id));

        // Stop tracking. While forcing is not allowed, the forced moodleoverflow can be untracked as well.
        readtracking::stop_tracking($moforce, $student->id);
        readtracking::stop_tracking($mooptional, $student->id);

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Preference off, moodleoverflow forced, should be on: forced tracking ignores the preference.
        $this->assertEquals(true, readtracking::is_tracked($moforce, $student->id));

        // Preference off, moodleoverflow optional, should be off.
        $this->assertEquals(false, readtracking::is_tracked($mooptional, $student->id));

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Preference off, moodleoverflow forced (counts as optional), should be off.
        $this->assertEquals(false, readtracking::is_tracked($moforce, $student->id));

        // Preference off, moodleoverflow optional, should be off.
        $this->assertEquals(false, readtracking::is_tracked($mooptional, $student->id));

        // Start tracking again, should be on.
        readtracking::start_tracking($mooptional, $student->id);
        $this->assertEquals(true, readtracking::is_tracked($mooptional, $student->id));
    }

    /**
     * Test that start_tracking() / stop_tracking() fire their events and check the permission.
     * @covers \mod_moodleoverflow\local\service\readtracking::start_tracking
     * @covers \mod_moodleoverflow\local\service\readtracking::stop_tracking
     */
    public function test_tracking_events(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OPTIONAL];
        $modflow = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $sink = $this->redirectEvents();
        readtracking::stop_tracking($modflow, $student->id);
        readtracking::start_tracking($modflow, $student->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(\mod_moodleoverflow\event\readtracking_disabled::class, $events[0]);
        $this->assertInstanceOf(\mod_moodleoverflow\event\readtracking_enabled::class, $events[1]);
        $this->assertEquals($student->id, $events[0]->relateduserid);

        // Users that cannot change the tracking (here: tracking is off) get an exception.
        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OFF];
        $mooff = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));
        $this->expectException(\moodle_exception::class);
        readtracking::stop_tracking($mooff, $student->id);
    }
}
