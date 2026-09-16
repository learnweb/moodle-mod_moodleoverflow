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
 * PHP Unit Tests for the moodleoverflow model class.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\enum\tracking_type;
use mod_moodleoverflow\local\models\moodleoverflow;
use stdClass;
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');


/**
 *
 * Tests if the functions from the moodleoverflow model class are working correctly.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\models\moodleoverflow
 */
final class moodleoverflow_test extends \advanced_testcase {
    /** @var stdClass test course */
    private $course;

    /** @var stdClass test moodleoverflow record */
    private $record;

    /** @var stdClass coursemodule */
    private $coursemodule;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Create a new course with a moodleoverflow.
        $this->course = $this->getDataGenerator()->create_course();
        $this->record = $this->getDataGenerator()->create_module('moodleoverflow', [
            'course' => $this->course->id,
            'anonymous' => anonymity::QUESTIONS->value,
            'needsreview' => review_level::EVERYTHING->value,
            'forcesubscribe' => subscription_mode::FORCED->value,
            'trackingtype' => tracking_type::FORCED->value,
            'allowrating' => 0,
            'allowreputation' => 0,
        ]);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->record->id);
    }

    /**
     * Test, if the model can be built from an id, a course module id and a database record.
     */
    public function test_construction(): void {
        global $DB;
        $dbrecord = $DB->get_record('moodleoverflow', ['id' => $this->record->id]);

        $fromrecord = moodleoverflow::from_record($dbrecord);
        $fromid = moodleoverflow::from_id($this->record->id);
        $fromcmid = moodleoverflow::from_cmid($this->coursemodule->id);

        // All three ways should result in the same moodleoverflow.
        $this->assertEquals($fromrecord, $fromid);
        $this->assertEquals($fromrecord, $fromcmid);

        // The string values from the database should be cast to the right types.
        $this->assertSame((int) $this->record->id, $fromid->id);
        $this->assertSame((int) $this->course->id, $fromid->course);
        $this->assertFalse($fromid->allowrating);
        $this->assertSame(anonymity::QUESTIONS, $fromid->get_anonymity());
        $this->assertSame(subscription_mode::FORCED, $fromid->get_subscription_mode());
    }

    /**
     * Test, if a record without id or with an invalid setting is rejected.
     */
    public function test_construction_with_invalid_record(): void {
        global $DB;
        $dbrecord = $DB->get_record('moodleoverflow', ['id' => $this->record->id]);

        // A record without an id is not allowed.
        $withoutid = clone $dbrecord;
        unset($withoutid->id);
        try {
            moodleoverflow::from_record($withoutid);
            $this->fail('A record without an id should throw an exception.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('needs an id', $e->getMessage());
        }

        // An unknown anonymity value is not allowed.
        $invalidsetting = clone $dbrecord;
        $invalidsetting->anonymous = 5;
        $this->expectException(\ValueError::class);
        moodleoverflow::from_record($invalidsetting);
    }

    /**
     * Test, if the exported database object matches the database record.
     */
    public function test_build_db_object(): void {
        global $DB;
        $dbrecord = $DB->get_record('moodleoverflow', ['id' => $this->record->id]);
        $dbobject = moodleoverflow::from_id($this->record->id)->build_db_object();

        // Every column should be exported with the same value, flags as integers.
        $this->assertEquals($dbrecord, $dbobject);
        $this->assertSame(0, $dbobject->allowrating);
        $this->assertObjectNotHasProperty('cm', $dbobject);

        // The object can be written back to the database.
        $dbobject->name = 'A new name';
        $DB->update_record('moodleoverflow', $dbobject);
        $this->assertEquals('A new name', moodleoverflow::from_id($this->record->id)->name);
    }

    /**
     * Test, if the course module, context and course are the ones of the instance.
     */
    public function test_place_in_moodle(): void {
        $moodleoverflow = moodleoverflow::from_id($this->record->id);

        $this->assertEquals($this->coursemodule->id, $moodleoverflow->get_cm()->id);
        $this->assertEquals($this->record->id, $moodleoverflow->get_cm()->instance);
        $this->assertEquals(\context_module::instance($this->coursemodule->id), $moodleoverflow->get_context());
        $this->assertEquals($this->course->id, $moodleoverflow->get_course()->id);
    }

    /**
     * Test, if the limited answer window is open at the right times.
     */
    public function test_answer_window(): void {
        global $DB;
        $moodleoverflow = moodleoverflow::from_id($this->record->id);

        // Without start and end, answers are always allowed.
        $this->assertEquals([null, null], $moodleoverflow->get_answer_window());
        $this->assertTrue($moodleoverflow->is_answer_window_open());

        // Set a window from 1000 to 2000.
        $record = $DB->get_record('moodleoverflow', ['id' => $this->record->id]);
        $record->la_starttime = 1000;
        $record->la_endtime = 2000;
        $moodleoverflow = moodleoverflow::from_record($record);

        $this->assertEquals([1000, 2000], $moodleoverflow->get_answer_window());
        $this->assertFalse($moodleoverflow->is_answer_window_open(999));
        $this->assertTrue($moodleoverflow->is_answer_window_open(1000));
        $this->assertTrue($moodleoverflow->is_answer_window_open(2000));
        $this->assertFalse($moodleoverflow->is_answer_window_open(2001));
    }

    /**
     * Test, if the review level considers the global allowreview setting.
     */
    public function test_review(): void {
        $moodleoverflow = moodleoverflow::from_id($this->record->id);

        // Reviewing allowed by the admin: the instance setting counts.
        set_config('allowreview', 1, 'moodleoverflow');
        $this->assertSame(review_level::EVERYTHING, $moodleoverflow->get_review_level());
        $this->assertTrue($moodleoverflow->requires_review(true));
        $this->assertTrue($moodleoverflow->requires_review(false));

        // Reviewing disallowed by the admin: nothing needs a review.
        set_config('allowreview', 0, 'moodleoverflow');
        $this->assertSame(review_level::NONE, $moodleoverflow->get_review_level());
        $this->assertFalse($moodleoverflow->requires_review(true));
        $this->assertFalse($moodleoverflow->requires_review(false));
    }

    /**
     * Test, if anonymity only hides the question author in question anonymous instances.
     */
    public function test_anonymity(): void {
        $moodleoverflow = moodleoverflow::from_id($this->record->id);

        $this->assertTrue($moodleoverflow->is_author_anonymous(true));
        $this->assertFalse($moodleoverflow->is_author_anonymous(false));
    }

    /**
     * Test, if rating and reputation can only be disabled if the admin allows it.
     */
    public function test_rating_and_reputation(): void {
        $moodleoverflow = moodleoverflow::from_id($this->record->id);

        // Disabling allowed by the admin: the instance setting counts.
        set_config('allowdisablerating', 1, 'moodleoverflow');
        $this->assertFalse($moodleoverflow->is_rating_enabled());
        $this->assertFalse($moodleoverflow->is_reputation_enabled());

        // Disabling disallowed by the admin: rating and reputation are always enabled.
        set_config('allowdisablerating', 0, 'moodleoverflow');
        $this->assertTrue($moodleoverflow->is_rating_enabled());
        $this->assertTrue($moodleoverflow->is_reputation_enabled());
    }

    /**
     * Test, if the tracking type considers the global trackreadposts and allowforcedreadtracking settings.
     */
    public function test_tracking_type(): void {
        $moodleoverflow = moodleoverflow::from_id($this->record->id);

        // Forced tracking allowed by the admin: the instance setting counts.
        set_config('trackreadposts', 1, 'moodleoverflow');
        set_config('allowforcedreadtracking', 1, 'moodleoverflow');
        $this->assertSame(tracking_type::FORCED, $moodleoverflow->get_tracking_type());

        // Forced tracking disallowed by the admin: users can choose.
        set_config('allowforcedreadtracking', 0, 'moodleoverflow');
        $this->assertSame(tracking_type::OPTIONAL, $moodleoverflow->get_tracking_type());

        // Read tracking disabled by the admin: tracking is always off.
        set_config('trackreadposts', 0, 'moodleoverflow');
        $this->assertSame(tracking_type::OFF, $moodleoverflow->get_tracking_type());
    }

    /**
     * Test, if an instance is only graded if max grade and scale factor are set.
     */
    public function test_is_graded(): void {
        global $DB;
        $record = $DB->get_record('moodleoverflow', ['id' => $this->record->id]);

        $record->grademaxgrade = 0;
        $record->gradescalefactor = 0;
        $this->assertFalse(moodleoverflow::from_record($record)->is_graded());

        $record->grademaxgrade = 10;
        $this->assertFalse(moodleoverflow::from_record($record)->is_graded());

        $record->gradescalefactor = 2;
        $this->assertTrue(moodleoverflow::from_record($record)->is_graded());
    }
}
