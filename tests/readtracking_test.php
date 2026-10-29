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
use mod_moodleoverflow\event\readtracking_disabled;
use mod_moodleoverflow\event\readtracking_enabled;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\enum\tracking_type;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

/**
 * PHPUnit Tests for testing readtracking.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\readtracking
 */
final class readtracking_test extends advanced_testcase {
    use moodleoverflow_test_trait;

    /**
     * Test the logic in permissions::can_track().
     * @covers \mod_moodleoverflow\local\permissions::can_track
     */
    public function test_can_track(): void {

        // Reset after testing.
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $options = ['course' => $course->id, 'trackingtype' => tracking_type::OFF->value]; // Off.
        $mooff = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => tracking_type::FORCED->value]; // On.
        $moforce = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => tracking_type::OPTIONAL->value]; // Optional.
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

        $options = ['course' => $course->id, 'trackingtype' => tracking_type::OPTIONAL->value];
        $mooptional = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => tracking_type::FORCED->value];
        $moforce = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $options = ['course' => $course->id, 'trackingtype' => tracking_type::OFF->value];
        $mooff = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $this->assertEquals(false, service\readtracking::is_tracked($mooff, $student->id));

        // Moodleoverflow forced, should be on.
        $this->assertEquals(true, service\readtracking::is_tracked($moforce, $student->id));

        // Moodleoverflow optional, should be on.
        $this->assertEquals(true, service\readtracking::is_tracked($mooptional, $student->id));

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Moodleoverflow off, should be off.
        $this->assertEquals(false, service\readtracking::is_tracked($mooff, $student->id));

        // Moodleoverflow forced, counts as optional now, should be on.
        $this->assertEquals(true, service\readtracking::is_tracked($moforce, $student->id));

        // Moodleoverflow optional, should be on.
        $this->assertEquals(true, service\readtracking::is_tracked($mooptional, $student->id));

        // Stop tracking. While forcing is not allowed, the forced moodleoverflow can be untracked as well.
        service\readtracking::stop_tracking($moforce, $student->id);
        service\readtracking::stop_tracking($mooptional, $student->id);

        // Allow force.
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');

        // Preference off, moodleoverflow forced, should be on: forced tracking ignores the preference.
        $this->assertEquals(true, service\readtracking::is_tracked($moforce, $student->id));

        // Preference off, moodleoverflow optional, should be off.
        $this->assertEquals(false, service\readtracking::is_tracked($mooptional, $student->id));

        // Don't allow force.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');

        // Preference off, moodleoverflow forced (counts as optional), should be off.
        $this->assertEquals(false, service\readtracking::is_tracked($moforce, $student->id));

        // Preference off, moodleoverflow optional, should be off.
        $this->assertEquals(false, service\readtracking::is_tracked($mooptional, $student->id));

        // Start tracking again, should be on.
        service\readtracking::start_tracking($mooptional, $student->id);
        $this->assertEquals(true, service\readtracking::is_tracked($mooptional, $student->id));
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
        $options = ['course' => $course->id, 'trackingtype' => tracking_type::OPTIONAL->value];
        $modflow = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));

        $sink = $this->redirectEvents();
        service\readtracking::stop_tracking($modflow, $student->id);
        service\readtracking::start_tracking($modflow, $student->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(readtracking_disabled::class, $events[0]);
        $this->assertInstanceOf(readtracking_enabled::class, $events[1]);
        $this->assertEquals($student->id, $events[0]->relateduserid);

        // Users that cannot change the tracking (here: tracking is off) get an exception.
        $options = ['course' => $course->id, 'trackingtype' => tracking_type::OFF->value];
        $mooff = moodleoverflow::from_record($this->getDataGenerator()->create_module('moodleoverflow', $options));
        $this->expectException(\moodle_exception::class);
        service\readtracking::stop_tracking($mooff, $student->id);
    }

    /**
     * Test, if a post that was written before the cutoff date but approved after it counts as unread and can be
     * marked as read.
     */
    public function test_post_approved_after_cutoff_is_unread(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_course();
        set_config('oldpostdays', 14, 'moodleoverflow');
        $modflow = $this->create_moodleoverflow([
            'needsreview' => review_level::EVERYTHING->value,
            'trackingtype' => tracking_type::OPTIONAL->value,
        ]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);

        // The answer was written 20 days ago and waited for its review since then.
        $written = time() - 20 * DAYSECS;
        $params = (object) ['id' => $answer->get_id(), 'created' => $written, 'modified' => $written];
        $DB->update_record('moodleoverflow_posts', $params);
        $answer = post::from_id($answer->get_id());
        service\review::approve($answer, $this->teacher->id);

        $unread = service\readtracking::get_unread_post_ids($answer->get_discussion(), $this->otherstudent->id);
        $this->assertContains($answer->get_id(), $unread);

        service\readtracking::mark_post_read($answer, $this->otherstudent->id);
        $params = ['postid' => $answer->get_id(), 'userid' => $this->otherstudent->id];
        $this->assertTrue($DB->record_exists('moodleoverflow_read', $params));
    }
}
