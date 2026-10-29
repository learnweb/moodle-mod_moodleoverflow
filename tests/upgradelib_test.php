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
 * PHP Unit Tests for the functions of the upgrade steps.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/db/upgradelib.php');

/**
 * Tests the functions of the upgrade steps on every database of the CI.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::mod_moodleoverflow_convert_subscription_choices
 */
#[CoversFunction('mod_moodleoverflow_convert_subscription_choices')]
final class upgradelib_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /**
     * Test, if users without a row in an auto moodleoverflow get an opt-out and discussion opt-ins become 1.
     */
    public function test_convert_subscription_choices(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_course();
        $auto = $this->create_moodleoverflow(['forcesubscribe' => subscription_mode::AUTO->value]);
        $optional = $this->create_moodleoverflow();
        $discussion = $this->generator->create_question($optional, $this->teacher)->get_discussion();

        // Old data: only the student is subscribed to the auto moodleoverflow, discussion opt-ins store a time.
        $DB->delete_records('moodleoverflow_subscriptions');
        $DB->insert_record('moodleoverflow_subscriptions', ['userid' => $this->student->id, 'moodleoverflow' => $auto->id]);
        $row = ['moodleoverflow' => $optional->id, 'discussion' => $discussion->get_id()];
        $DB->insert_record('moodleoverflow_discuss_subs', $row + ['userid' => $this->student->id, 'preference' => time()]);
        $DB->insert_record('moodleoverflow_discuss_subs', $row + ['userid' => $this->otherstudent->id, 'preference' => -1]);

        mod_moodleoverflow_convert_subscription_choices();
        // A second run must not change anything.
        mod_moodleoverflow_convert_subscription_choices();

        $this->assertEquals(
            [$this->student->id => 1, $this->otherstudent->id => -1, $this->teacher->id => -1],
            $DB->get_records_menu('moodleoverflow_subscriptions', ['moodleoverflow' => $auto->id], '', 'userid, preference')
        );
        $this->assertFalse($DB->record_exists('moodleoverflow_subscriptions', ['moodleoverflow' => $optional->id]));
        $this->assertEquals(
            [$this->student->id => 1, $this->otherstudent->id => -1],
            $DB->get_records_menu('moodleoverflow_discuss_subs', ['discussion' => $discussion->get_id()], '', 'userid, preference')
        );
    }
}
