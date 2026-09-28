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
     * Test the logic in the can_trac() function.
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

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Modleoverflow off, should be off.
        $result = readtracking::can_track($mooff);
        $this->assertEquals(false, $result);

        // Moodleoverflow on, should be off.
        $result = readtracking::can_track($moforce);
        $this->assertEquals(false, $result);

        // Moodleoverflow optional, should be false.
        $result = readtracking::can_track($mooptional);
        $this->assertEquals(false, $result);

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $result = readtracking::can_track($mooff);
        $this->assertEquals(false, $result);

        // Moodleoverflow on, should be off.
        $result = readtracking::can_track($moforce);
        $this->assertEquals(false, $result);

        // Moodleoverflow optional, should be off.
        $result = readtracking::can_track($mooptional);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in the test_forum_tp_is_tracked() function.
     */
    public function test_moodleoverflow_is_tracked(): void {

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
        $result = readtracking::moodleoverflow_is_tracked($mooff);
        $this->assertEquals(false, $result);

        // Moodleoverflow force, should be off.
        $result = readtracking::moodleoverflow_is_tracked($moforce);
        $this->assertEquals(false, $result);

        // Moodleoverflow optional, should be off.
        $result = readtracking::moodleoverflow_is_tracked($mooptional);
        $this->assertEquals(false, $result);

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $result = readtracking::moodleoverflow_is_tracked($mooff);
        $this->assertEquals(false, $result);

        // Moodleoverflow force, should be off.
        $result = readtracking::moodleoverflow_is_tracked($moforce);
        $this->assertEquals(false, $result);

        // Moodleoverflow optional, should be off.
        $result = readtracking::moodleoverflow_is_tracked($mooptional);
        $this->assertEquals(false, $result);

        // Stop tracking so we can test again.
        readtracking::stop_tracking($moforce->id);
        readtracking::stop_tracking($mooptional->id);

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Preference off, moodleoverflow force, should be on.
        $result = readtracking::moodleoverflow_is_tracked($moforce);
        $this->assertEquals(false, $result);

        // Preference off, moodleoverflow optional, should be on.
        $result = readtracking::moodleoverflow_is_tracked($mooptional);
        $this->assertEquals(false, $result);

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Preference off, moodleoverflow force, should be on.
        $result = readtracking::moodleoverflow_is_tracked($moforce);
        $this->assertEquals(false, $result);

        // Preference off, moodleoverflow optional, should be on.
        $result = readtracking::moodleoverflow_is_tracked($mooptional);
        $this->assertEquals(false, $result);
    }
}
