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


use dml_exception;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

/**
 * Unit tests for mod_moodleoverflow.
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * PHPUnit tests if activity completions appear in the events table.
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_moodleoverflow\
 */
final class activity_completion_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    // Construct functions.
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course(['enablecompletion' => 1]);
    }

    // Tests.

    /**
     * Test if the activity completion creates an event in the event table.
     *
     * @return void
     * @throws dml_exception
     */
    public function test_activity_completion(): void {
        global $DB;
        // Create a moodleoverflow with an activity completion that has a reminder for the next day.

        $date = time() + DAYSECS;
        $this->getDataGenerator()->create_module('moodleoverflow', [
            'course' => $this->course->id,
            'courseid' => $this->course->id,
            'name' => "Modflow1",
            'intro' => "Moodleoverflow with activity completion",
        ], ['completion' => COMPLETION_TRACKING_MANUAL, 'completionexpected' => $date]);

        $result = $DB->get_records('event');

        // The event should be created.
        $this->assertEquals(1, count($result));
        $this->assertEquals("Modflow1 should be completed", array_values($result)[0]->name);
        $this->assertEquals("expectcompletionon", array_values($result)[0]->eventtype);
        $this->assertEquals($date, array_values($result)[0]->timestart);
    }
}
