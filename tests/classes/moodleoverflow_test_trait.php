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

namespace mod_moodleoverflow\tests;

use core\task\task_base;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow_generator;
use phpunit_phpmailer_sink;
use stdClass;

/**
 * Shared set-up for moodleoverflow unit tests.
 *
 * Only holds what several test files need: a course with users, moodleoverflow instances, mails and tasks. Test data
 * inside a moodleoverflow (posts, ratings, files) is created by the plugin generator.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait moodleoverflow_test_trait {
    /** @var stdClass The test course. */
    protected $course;

    /** @var stdClass An editing teacher of the course. */
    protected $teacher;

    /** @var stdClass A student of the course. */
    protected $student;

    /** @var stdClass A second student of the course. */
    protected $otherstudent;

    /** @var mod_moodleoverflow_generator The plugin generator. */
    protected $generator;

    /**
     * Creates the course with an editing teacher and two students and loads the plugin generator.
     *
     * @param array $courseoptions Fields for the course, e.g. ['enablecompletion' => 1].
     */
    protected function setup_course(array $courseoptions = []): void {
        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('mod_moodleoverflow');
        $this->course = $datagenerator->create_course($courseoptions);
        $this->teacher = $datagenerator->create_and_enrol($this->course, 'editingteacher');
        $this->student = $datagenerator->create_and_enrol($this->course, 'student');
        $this->otherstudent = $datagenerator->create_and_enrol($this->course, 'student');
    }

    /**
     * Creates a moodleoverflow in the test course.
     *
     * @param array $options Fields for the instance, e.g. ['needsreview' => review_level::EVERYTHING->value].
     * @return moodleoverflow
     */
    protected function create_moodleoverflow(array $options = []): moodleoverflow {
        $record = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $this->course->id] + $options);
        return moodleoverflow::from_id($record->id);
    }

    /**
     * Creates users and enrols them as students.
     *
     * @param int $count Number of users.
     * @param stdClass|null $course The course, the test course by default.
     * @return stdClass[]
     */
    protected function create_students(int $count, ?stdClass $course = null): array {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = $this->getDataGenerator()->create_and_enrol($course ?? $this->course, 'student');
        }
        return $users;
    }

    /**
     * Lets mails be sent and catches them, and removes the waiting times before a post is mailed or reviewed.
     *
     * @return phpunit_phpmailer_sink
     */
    protected function redirect_mails(): phpunit_phpmailer_sink {
        unset_config('noemailever');
        set_config('maxeditingtime', -10, 'moodleoverflow');
        set_config('reviewpossibleaftertime', -10, 'moodleoverflow');
        return $this->redirectEmails();
    }

    /**
     * Executes a task and returns what it printed.
     *
     * @param task_base $task
     * @return string
     */
    protected function run_task(task_base $task): string {
        ob_start();
        $task->execute();
        return ob_get_clean();
    }
}
