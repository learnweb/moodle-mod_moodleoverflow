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

namespace block_townsquare;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

use mod_moodleoverflow\manager\mail_manager;
use mod_moodleoverflow\subscriptions;
use stdClass;

/**
 * Unit tests for the mod_moodleoverflow plugin.
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * PHPUnit tests for testing the process of sending notification of new posts via email.
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_moodleoverflow\manager\mail_manager::moodleoverflow_send_mails
 */
final class notification_mail_test extends \advanced_testcase {

    // Attributes.

    /** @var object The data that will be used for testing.
     * This Class contains the test data:
     * - one course.
     * - a moodleoverflow activity
     * - a teacher.
     * - two students.
     */
    private $testdata;

    // Construct functions.
    public function setUp(): void {
        parent::setUp();
        $this->testdata = new stdClass();
        $this->resetAfterTest();
        $this->helper_course_set_up();
    }

    public function tearDown(): void {
        $this->testdata = null;
        parent::tearDown();
    }

    // Tests.

    /**
     * Test, if calendar events are sorted correctly.
     */
    public function test_sortorder(): void {
        global $DB;
        //var_export($DB->get_records('moodleoverflow_posts'));
        mail_manager::moodleoverflow_send_mails();
    }

    // Helper functions.

    /**
     * Helper function that creates:
     * - two courses.
     * - an assignment in each course.
     * - an activity completion in the first course.
     * - a teacher that is enrolled in both courses.
     * - a student in each course.
     */
    private function helper_course_set_up(): void {
        global $DB;
        $datagenerator = $this->getDataGenerator();
        $plugingenerator = $datagenerator->get_plugin_generator('mod_moodleoverflow');

        // Create a new course.
        $this->testdata->course = $datagenerator->create_course();

        // Create a teacher and a student and enroll them in the course.
        $this->testdata->teacher = $datagenerator->create_user(['firstname' => 'Tamaro', 'email' => 'tamaromail@example.com',
                                                                'maildigest' => 0, ]);
        $this->testdata->student1 = $datagenerator->create_user(['firstname' => 'Student1', 'email' => 'student1mail@example.com',
                                                                 'maildigest' => 0, ]);
        $this->testdata->student2 = $datagenerator->create_user(['firstname' => 'Student1', 'email' => 'student1mail@example.com',
                                                                 'maildigest' => 0, ]);

        $datagenerator->enrol_user($this->testdata->teacher->id, $this->testdata->course->id, 'teacher');
        $datagenerator->enrol_user($this->testdata->student1->id, $this->testdata->course->id, 'student');
        $datagenerator->enrol_user($this->testdata->student2->id, $this->testdata->course->id, 'student');

        // Create a moodleoverflow with a discussion from the teacher.
        $options = ['course' => $this->testdata->course->id, 'forcesubscribe' => MOODLEOVERFLOW_FORCESUBSCRIBE];
        $this->testdata->moodleoverflow = $datagenerator->create_module('moodleoverflow', $options);
        $this->testdata->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->testdata->moodleoverflow->id);
        $this->testdata->discussion = $plugingenerator->post_to_forum($this->testdata->moodleoverflow, $this->testdata->teacher);

        // TODO: THIS need to be done with the plugin generator, only temporary solution
        // subscribe users to moodleoverflow
        $DB->insert_record('mooodleoverflow_subscriptions', ['userid' => $this->testdata->teacher->id, 'moodleoverflow' => $this->testdata->moodleoverflow->id]);
        $DB->insert_record('mooodleoverflow_subscriptions', ['userid' => $this->testdata->student1->id, 'moodleoverflow' => $this->testdata->moodleoverflow->id]);
        $DB->insert_record('mooodleoverflow_subscriptions', ['userid' => $this->testdata->student2->id, 'moodleoverflow' => $this->testdata->moodleoverflow->id]);
    }

}
