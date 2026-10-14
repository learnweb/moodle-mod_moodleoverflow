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
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\local\tables\userstats_table;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

defined('MOODLE_INTERNAL') || die();


global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');

/**
 * PHPUnit Tests for testing userstats.
 *
 * @package mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_moodleoverflow\local\tables\userstats_table
 */
final class userstats_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var moodleoverflow test moodleoverflow */
    private $modflow;

    /** @var post the question of the student */
    private $question1;

    /** @var post the question of the other student */
    private $question2;

    /** @var post the answer of the other student to the question of the student */
    private $answer1;

    /** @var post the answer of the student to the question of the other student */
    private $answer2;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->helper_course_set_up();
    }

    // Begin of test functions.

    /**
     * Test, if a upvote is being counted.
     * @covers \mod_moodleoverflow\local\tables\userstats_table
     */
    public function test_upvote(): void {
        // Teacher upvotes the answer of the other student.
        $this->generator->create_rating($this->answer1, $this->teacher, RATING_UPVOTE);

        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $upvotes = $this->get_specific_userstats($data, $this->otherstudent, 'receivedupvotes');
        $this->assertEquals(1, $upvotes);
    }

    /**
     * Test, if a downvote is being counted.
     * @covers \mod_moodleoverflow\local\tables\userstats_table
     */
    public function test_downvote(): void {
        // Teacher downvotes the answer of the student.
        $this->generator->create_rating($this->answer2, $this->teacher, RATING_DOWNVOTE);

        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $downvotes = $this->get_specific_userstats($data, $this->student, 'receiveddownvotes');
        $this->assertEquals(1, $downvotes);
    }

    /**
     * Test, if the activity is calculated correctly.
     * @covers \mod_moodleoverflow\local\tables\userstats_table
     */
    public function test_activity(): void {
        // The student rates 3 times.
        $this->generator->create_rating($this->answer1, $this->student, RATING_HELPFUL);
        $this->generator->create_rating($this->answer1, $this->student, RATING_UPVOTE);
        $this->generator->create_rating($this->question2, $this->student, RATING_DOWNVOTE);
        // The student created 2 posts (1 discussion, 1 answer).
        // Activity = 5.
        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $activity = $this->get_specific_userstats($data, $this->student, 'forumactivity');
        $this->assertEquals(5, $activity);
    }
    /**
     * Test, if the reputation is calculated correctly.
     * @covers \mod_moodleoverflow\local\tables\userstats_table
     */
    public function test_reputation(): void {
        // The student and the teacher rate the other student.
        $this->generator->create_rating($this->answer1, $this->student, RATING_HELPFUL);
        $this->generator->create_rating($this->answer1, $this->student, RATING_UPVOTE);
        $this->generator->create_rating($this->question2, $this->student, RATING_DOWNVOTE);
        $this->generator->create_rating($this->answer1, $this->teacher, RATING_SOLVED);

        // Calculate the forum reputation of the other student.
        $reputation = service\rating::get_instance_reputation($this->modflow, $this->otherstudent->id);
        // Create the user statistics table for this course and save it in $data.
        $data = $this->create_statstable();
        $reputation2 = $this->get_specific_userstats($data, $this->otherstudent, 'forumreputation');
        $this->assertEquals($reputation, $reputation2);
    }

    /**
     * Test, if userstats are calculated correctly if the moodleoverflow is partially anonymous.
     * @covers \mod_moodleoverflow\local\tables\userstats_table
     */
    public function test_partial_anonymous(): void {
        // Test case: Only topic startes are anonymous.
        $this->make_anonymous(1);

        // Get the current userstats to compare later.
        $olduserstats = $this->create_statstable();
        $oldupvotesuser1 = $this->get_specific_userstats($olduserstats, $this->student, 'receivedupvotes');
        $oldactivityuser1 = $this->get_specific_userstats($olduserstats, $this->student, 'forumactivity');

        $oldupvotesuser2 = $this->get_specific_userstats($olduserstats, $this->otherstudent, 'receivedupvotes');
        $oldactivityuser2 = $this->get_specific_userstats($olduserstats, $this->otherstudent, 'forumactivity');

        // The student starts a new discussion, the forum activity shouldn't change.
        $starterpost = $this->generator->create_question($this->modflow, $this->student);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->student, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        // The other student now gives an answer to the student, the activity should change.
        $answeruser2 = $this->generator->create_reply($starterpost, $this->otherstudent);
        $newuserstats = $this->create_statstable();
        $newactivityuser2 = $this->get_specific_userstats($newuserstats, $this->otherstudent, 'forumactivity');
        $this->assertEquals($oldactivityuser2 + 1, $newactivityuser2);
        $oldactivityuser2 = $newactivityuser2;  // Update it for further comparisons.

        // The student rates the answer of the other student as helpful and gives it an upvote.
        // The activity of the student should only change with the upvote.
        // The received upvotes of the other student should change.
        $this->generator->create_rating($answeruser2, $this->student, RATING_HELPFUL);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->student, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        $this->generator->create_rating($answeruser2, $this->student, RATING_UPVOTE);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->student, 'forumactivity');
        $newupvotesuser2 = $this->get_specific_userstats($newuserstats, $this->otherstudent, 'receivedupvotes');
        $this->assertEquals($oldactivityuser1 + 1, $newactivityuser1);
        $this->assertEquals($oldupvotesuser2 + 1, $newupvotesuser2);

        // The other student gives the discussion starter post an upvote.
        // The activity of the other student should change, the received upvotes of the student shouldn't change.
        $this->generator->create_rating($starterpost, $this->otherstudent, RATING_UPVOTE);
        $newuserstats = $this->create_statstable();
        $newactivityuser2 = $this->get_specific_userstats($newuserstats, $this->otherstudent, 'forumactivity');
        $newupvotesuser1 = $this->get_specific_userstats($newuserstats, $this->student, 'receivedupvotes');
        $this->assertEquals($oldactivityuser2 + 1, $newactivityuser2);
        $this->assertEquals($oldupvotesuser1, $newupvotesuser1);
    }

    /**
     * Test, if userstats are calculated correctly if the moodleoverflow is totally anonymous.
     * @covers \mod_moodleoverflow\local\tables\userstats_table
     */
    public function test_total_anonymous(): void {
        // Test case: Only topic startes are anonymous.
        $this->make_anonymous(2);

        // Get the current userstats to compare later.
        $olduserstats = $this->create_statstable();
        $oldactivityuser1 = $this->get_specific_userstats($olduserstats, $this->student, 'forumactivity');

        $oldupvotesuser2 = $this->get_specific_userstats($olduserstats, $this->otherstudent, 'receivedupvotes');
        $oldactivityuser2 = $this->get_specific_userstats($olduserstats, $this->otherstudent, 'forumactivity');

        // The student starts a new discussion, the forum activity shouldn't change.
        $starterpost = $this->generator->create_question($this->modflow, $this->student);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->student, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        // The other student now gives an answer to the student, the activity shouldn't change.
        $answeruser2 = $this->generator->create_reply($starterpost, $this->otherstudent);
        $newuserstats = $this->create_statstable();
        $newactivityuser2 = $this->get_specific_userstats($newuserstats, $this->otherstudent, 'forumactivity');
        $this->assertEquals($oldactivityuser2, $newactivityuser2);

        // The student rates the answer of the other student as helpful and gives it an upvote.
        // The activity of the student should only change with the upvote.
        // The received upvotes of the other student should not change.
        $this->generator->create_rating($answeruser2, $this->student, RATING_HELPFUL);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->student, 'forumactivity');
        $this->assertEquals($oldactivityuser1, $newactivityuser1);

        $this->generator->create_rating($answeruser2, $this->student, RATING_UPVOTE);
        $newuserstats = $this->create_statstable();
        $newactivityuser1 = $this->get_specific_userstats($newuserstats, $this->student, 'forumactivity');
        $newupvotesuser2 = $this->get_specific_userstats($newuserstats, $this->otherstudent, 'receivedupvotes');
        $this->assertEquals($oldactivityuser1 + 1, $newactivityuser1);
        $this->assertEquals($oldupvotesuser2, $newupvotesuser2);
    }

    // Helper functions.

    /**
     * This function creates:
     * - a course with a moodleoverflow
     * - a teacher
     * - 2 students, who create a discussion and a post in the discussion of the other student.
     */
    private function helper_course_set_up() {
        $this->setup_course();
        $this->modflow = $this->create_moodleoverflow();
        $this->question1 = $this->generator->create_question($this->modflow, $this->student);
        $this->question2 = $this->generator->create_question($this->modflow, $this->otherstudent);
        $this->answer1 = $this->generator->create_reply($this->question1, $this->otherstudent);
        $this->answer2 = $this->generator->create_reply($this->question2, $this->student);
    }

    /**
     * Makes the existing moodleoverflow anonymous.
     * There are 2 types of anonymous moodleoverflows:
     * anonymous = 1, the topic starter is anonymous
     * anonymous = 2, all users are anonymous
     *
     * @param int $anonymoussetting
     */
    private function make_anonymous($anonymoussetting) {
        global $DB;
        if ($anonymoussetting == 1 || $anonymoussetting == 2) {
            $DB->set_field('moodleoverflow', 'anonymous', $anonymoussetting, ['id' => $this->modflow->id]);
        } else {
            throw new \Exception('invalid parameter, anonymoussetting should be 1 or 2');
        }
    }

    /**
     * Create a usertable and return it.
     */
    private function create_statstable() {
        $url = new \moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $this->modflow->get_cm()->id]);
        $userstatstable = new userstats_table('testtable', $this->course->id, $this->modflow->id, $url);
        $userstatstable->get_table_data();
        return $userstatstable->get_usertable();
    }

    /**
     * Return a specific value from the userstatstable.
     *
     * @param array     $statstable
     * @param object    $user
     * @param string    $stats          // A key that specifies which value should be returned.
     */
    private function get_specific_userstats($statstable, $user, $stats) {
        foreach ($statstable as $student) {
            if ($student->id == $user->id) {
                switch ($stats) {
                    case 'receivedupvotes':
                        $result = $student->receivedupvotes;
                        break;
                    case 'receiveddownvotes':
                        $result = $student->receiveddownvotes;
                        break;
                    case 'forumactivity':
                        $result = $student->forumactivity;
                        break;
                    case 'forumreputation':
                        $result = $student->forumreputation;
                        break;
                    default:
                        throw new \Exception('parameter unknown');
                        break;
                }
            }
        }
        return $result;
    }
}
