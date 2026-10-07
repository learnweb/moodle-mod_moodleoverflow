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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * PHPUnit Tests for testing readtracking.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \readtracking
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
     * Test the logic in the test_forum_tp_is_tracked() function.
     */
    public function test_moodleoverflow_is_tracked(): void {
        global $USER;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OPTIONAL];
        $mooptional = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_FORCED];
        $moforce = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => MOODLEOVERFLOW_TRACKING_OFF];
        $mooff = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $result = readtracking::moodleoverflow_is_tracked($mooff, $USER->id);
        $this->assertEquals(false, $result);

        // Moodleoverflow force, should be off.
        $result = readtracking::moodleoverflow_is_tracked($moforce, $USER->id);
        $this->assertEquals(false, $result);

        // Moodleoverflow optional, should be off.
        $result = readtracking::moodleoverflow_is_tracked($mooptional, $USER->id);
        $this->assertEquals(false, $result);

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $result = readtracking::moodleoverflow_is_tracked($mooff, $USER->id);
        $this->assertEquals(false, $result);

        // Moodleoverflow force, should be off.
        $result = readtracking::moodleoverflow_is_tracked($moforce, $USER->id);
        $this->assertEquals(false, $result);

        // Moodleoverflow optional, should be off.
        $result = readtracking::moodleoverflow_is_tracked($mooptional, $USER->id);
        $this->assertEquals(false, $result);

        // Stop tracking so we can test again.
        readtracking::stop_tracking($moforce, $USER->id);
        readtracking::stop_tracking($mooptional, $USER->id);

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Preference off, moodleoverflow force, should be on.
        $result = readtracking::moodleoverflow_is_tracked($moforce, $USER->id);
        $this->assertEquals(false, $result);

        // Preference off, moodleoverflow optional, should be on.
        $result = readtracking::moodleoverflow_is_tracked($mooptional, $USER->id);
        $this->assertEquals(false, $result);

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Preference off, moodleoverflow force, should be on.
        $result = readtracking::moodleoverflow_is_tracked($moforce, $USER->id);
        $this->assertEquals(false, $result);

        // Preference off, moodleoverflow optional, should be on.
        $result = readtracking::moodleoverflow_is_tracked($mooptional, $USER->id);
        $this->assertEquals(false, $result);
    }
}
