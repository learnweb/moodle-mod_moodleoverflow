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

namespace mod_moodleoverflow\local\tables;

defined('MOODLE_INTERNAL') || die();

use core\exception\coding_exception;
use core\exception\moodle_exception;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;
global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');
require_once($CFG->libdir . '/tablelib.php');

/**
 * Table listing all user statistics of a course. Needed in userstats.php.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstats_table extends \flexible_table {
    /** @var int the Course ID*/
    private $courseid;

    /** @var int Moodleoverflow that started the printing of statistics*/
    private $moodleoverflowid;

    /** @var array table that will have objects with every user and his statistics. */
    private $userstatsdata = [];

    /**
     * Constructor for workflow_table.
     *
     * @param int $uniqueid Unique id of this table.
     * @param int $courseid
     * @param int $moodleoverflow ID if the moodleoverflow
     * @param string $url The url of the table
     */
    public function __construct($uniqueid, $courseid, $moodleoverflow, $url) {
        global $PAGE, $OUTPUT;
        parent::__construct($uniqueid);
        $PAGE->requires->js_call_amd('mod_moodleoverflow/activityhelp', 'init');

        $this->courseid = $courseid;
        $this->moodleoverflowid = $moodleoverflow;
        $helpactivity = $OUTPUT->help_icon('helpamountofactivity', 'moodleoverflow');

        $this->set_attribute('class', 'moodleoverflow-statistics-table');
        $this->set_attribute('id', $uniqueid);
        $this->define_columns(['username', 'receivedupvotes', 'receiveddownvotes', 'forumactivity', 'courseactivity',
                               'forumreputation', 'coursereputation', ]);
        $this->define_baseurl($url);
        $this->define_headers([get_string('fullnameuser'),
                               get_string('userstatsupvotes', 'moodleoverflow'),
                               get_string('userstatsdownvotes', 'moodleoverflow'),
                               (get_string('userstatsforumactivity', 'moodleoverflow') . $helpactivity),
                               (get_string('userstatscourseactivity', 'moodleoverflow') . $helpactivity),
                               get_string('userstatsforumreputation', 'moodleoverflow'),
                               get_string('userstatscoursereputation', 'moodleoverflow'), ]);
        $this->get_table_data();
        $this->sortable(true, 'coursereputation', SORT_DESC);
        $this->no_sorting('username');
        $this->setup();
    }

    /**
     * Method to display the table.
     * @return void
     */
    public function out() {
        $this->start_output();
        $this->sort_table_data($this->get_sort_order());
        $this->format_and_add_array_of_rows($this->userstatsdata, true);
        $this->text_sorting('coursereputation');
        $this->finish_output();
    }

    /**
     * Method to collect all the data.
     * Method will collect all users from the given course and will determine the user statistics
     * Builds an 2d-array with user statistic
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_table_data(): void {
        // Get all userdata from a course.
        $context = \context_course::instance($this->courseid);
        $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname');
        $modflow = moodleoverflow::from_id($this->moodleoverflowid);

        // Step 1.0: Build the datatable with all relevant information.
        $ratingdata = $this->get_rating_data();
        $this->add_anonymity($ratingdata);

        // Step 2.0: Now collect the data for every user in the course.
        foreach ($users as $user) {
            $student = $this->createstudent($user);

            foreach ($ratingdata as $row) {
                // Did the student receive an up- or downvote?
                if ($row->postuserid == $student->id) {
                    $this->process_received_votes($student, $row);
                }

                // Did a student submit a rating?
                if ($row->rateuserid == $student->id) {
                    $this->process_submitted_ratings($student, $row);
                }

                // Did the student write a post?
                if ($row->postuserid == $student->id) {
                    $this->process_written_posts($student, $row);
                }
            }
            // Get the user reputation from the course.
            $student->forumreputation = service\rating::get_instance_reputation($modflow, $student->id);
            $student->coursereputation = service\rating::get_course_reputation($this->courseid, $student->id);
            $this->userstatsdata[] = $student;
        }
    }

    /**
     * Return the userstatsdata-table.
     */
    public function get_usertable() {
        return $this->userstatsdata;
    }

    // Functions that show the data.

    /**
     * username column
     * @param object $row
     * @return string
     */
    public function col_username($row) {
        return $row->link;
    }

    /**
     * upvotes column
     * @param object $row
     * @return string
     */
    public function col_receivedupvotes($row) {
        return $this->badge_render($row->receivedupvotes);
    }

    /**
     * downvotes column
     * @param object $row
     * @return string
     */
    public function col_receiveddownvotes($row) {
        return $this->badge_render($row->receiveddownvotes);
    }

    /**
     * Forum activity column
     * @param object $row
     * @return string
     */
    public function col_forumactivity($row) {
        return $this->badge_render($row->forumactivity);
    }

    /**
     * Forum reputation column
     * @param object $row
     * @return string
     */
    public function col_forumreputation($row) {
        return $this->badge_render($row->forumreputation);
    }

    /**
     * Course activity column
     * @param object $row
     * @return string
     */
    public function col_courseactivity($row) {
        return $this->badge_render($row->courseactivity);
    }

    /**
     * Course reputation column
     * @param object $row
     * @return string
     */
    public function col_coursereputation($row) {
        return $this->badge_render($row->coursereputation);
    }

    /**
     * Depending on the value display success or warning badge.
     * @param int $number
     * @return string
     */
    private function badge_render($number) {
        if ($number > 0) {
            return \html_writer::tag('h5', \html_writer::start_span('badge bg-success') .
                $number . \html_writer::end_span());
        } else {
            return \html_writer::tag('h5', \html_writer::start_span('badge bg-warning') .
                $number . \html_writer::end_span());
        }
    }

    // Helper functions.

    /**
     * Return a student object.
     * @param \stdClass $user
     * @return object
     */
    private function createstudent($user) {
        $student = new \stdClass();
        $student->id = $user->id;
        $student->name = $user->firstname . ' ' . $user->lastname;
        $linktostudent = new \moodle_url('/user/view.php', ['id' => $student->id, 'course' => $this->courseid]);
        $student->link = \html_writer::link($linktostudent->out(), $student->name);
        $student->submittedposts = [];      // Posts written by the student. Key = postid, Value = postid.
        $student->ratedposts = [];          // Posts that the student rated. Key = rateid, Value = rateid.
        $student->receivedupvotes = 0;
        $student->receiveddownvotes = 0;
        $student->forumactivity = 0;        // Number of written posts and submitted ratings in the current moodleoverflow.
        $student->courseactivity = 0;       // Number of written posts and submitted ratings in the course.
        $student->forumreputation = 0;      // Reputation in the current moodleoverflow.
        $student->coursereputation = 0;     // Reputation in the course.
        return $student;
    }

    /**
     * All ratings upvotes downbotes activity etc. from the current course.
     * @return array
     * @throws \dml_exception
     */
    private function get_rating_data() {
        global $DB;
        $sqlquery = 'SELECT ROW_NUMBER() OVER (ORDER BY ratings.id) AS row_num,
                            discuss.id AS discussid,
                            discuss.userid AS discussuserid,
                            posts.id AS postid,
                            posts.userid AS postuserid,
                            ratings.id AS rateid,
                            ratings.rating AS rating,
                            ratings.userid AS rateuserid,
                            ratings.postid AS ratepostid,
                            moodleoverflow.id AS moodleoverflowid
                      FROM {moodleoverflow_discussions} discuss
                      LEFT JOIN {moodleoverflow_posts} posts ON discuss.id = posts.discussion
                      LEFT JOIN {moodleoverflow_ratings} ratings ON posts.id = ratings.postid
                      LEFT JOIN {moodleoverflow} moodleoverflow ON discuss.moodleoverflow = moodleoverflow.id
                      WHERE discuss.course = ' . $this->courseid . ';';
        return $DB->get_records_sql($sqlquery);
    }

    /**
     * Process the received votes for a student.
     * @param object $student
     * @param object $row
     */
    private function process_received_votes(object $student, object $row) {
        // Only count received votes where the author of the post is visible.
        if (!$row->authorhidden) {
            if ($row->rating == rating_type::UPVOTE->value) {
                $student->receivedupvotes += 1;
            } else if ($row->rating == rating_type::DOWNVOTE->value) {
                $student->receiveddownvotes += 1;
            }
        }
    }

    /**
     * Process the submitted ratings from a student.
     * @param object $student
     * @param object $row
     */
    private function process_submitted_ratings($student, $row) {
        // Votes always count, the rater of a vote is never shown. A mark shows who set it (helpful marks come from the
        // questioner), so it only counts if the rater would be visible as an author in the discussion.
        $isvote = in_array((int) $row->rating, [rating_type::UPVOTE->value, rating_type::DOWNVOTE->value], true);
        $ismark = in_array((int) $row->rating, [rating_type::HELPFUL->value, rating_type::SOLVED->value], true);

        if (!array_key_exists($row->rateid, $student->ratedposts)) {
            if ($isvote || ($ismark && !$row->raterhidden)) {
                $this->increment_forumactivity($student, $row);
                $student->courseactivity++;
                $student->ratedposts[$row->rateid] = $row->rateid;
            }
        }
    }

    /**
     * Process the written posts from a student for the activity.
     * @param object $student
     * @param object $row
     */
    private function process_written_posts($student, $row) {
        // Only count a written post if its author is visible.
        if (!array_key_exists($row->postid, $student->submittedposts) && !$row->authorhidden) {
            $this->increment_forumactivity($student, $row);
            $student->courseactivity += 1;
            $student->submittedposts[$row->postid] = $row->postid;
        }
    }

    /**
     * Increments the forum activity of a student.
     * @param object $student
     * @param object $row
     */
    private function increment_forumactivity($student, $row) {
        if ($row->moodleoverflowid == $this->moodleoverflowid) {
            $student->forumactivity++;
        }
    }

    // Sort function.

    /**
     * Method to sort the userstatsdata-table.
     * @param array $sortorder The sort order array.
     * @return void
     */
    private function sort_table_data($sortorder) {
        $key = $sortorder['sortby'];
        $direction = $sortorder['sortorder'] == SORT_ASC ? 1 : -1;
        usort($this->userstatsdata, fn($a, $b) => $direction * ($a->$key <=> $b->$key));
    }

    /**
     * Adds to every row whether its post author and its rater are hidden by the anonymity of their moodleoverflow, so
     * that the activity of anonymous users does not show who they are. A missing user (no rating, removed author)
     * counts as hidden.
     * @param object[] $rows from get_rating_data()
     * @throws moodle_exception
     */
    private function add_anonymity(array $rows): void {
        $modflows = [];
        foreach ($rows as $row) {
            $modflow = $modflows[$row->moodleoverflowid] ??= moodleoverflow::from_id($row->moodleoverflowid);
            $hidden = fn($userid) => !$userid
                || permissions::is_hidden_as_author($modflow, (int) $userid, $userid == $row->discussuserid);
            $row->authorhidden = $hidden($row->postuserid);
            $row->raterhidden = $hidden($row->rateuserid);
        }
    }
}
