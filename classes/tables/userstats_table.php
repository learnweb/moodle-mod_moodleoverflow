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
 * Prints a particular instance of moodleoverflow
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow\tables;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_login();

/**
 * Table listing all user statistics of a course
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstats_table extends \flexible_table {


    private $courseid;   // Course ID.
    private $context;
    /**
     * Constructor for workflow_table.
     * @param int $uniqueid Unique id of this table.
     */
    public function __construct($uniqueid, $courseid, $coursecontext) {
        parent::__construct($uniqueid);
        global $PAGE;
        $this->courseid = $courseid;
        $this->context = $coursecontext;
        $this->set_attribute('class', 'statistics-table');
        $this->set_attribute('id', $uniqueid);
        $this->define_columns(['username', 'upvotes', 'downvotes', 'activity', 'reputation']);
        $this->define_baseurl($PAGE->url);
        $this->define_headers(['user', 'received upvotes', 'received downvotes', 'amount of activity', 'user reputation']);
        $this->sortable(false);
        $this->setup();
    }

    /**
     * Method to display the table.
     * @return void
     */
    public function out() {
        global $DB;
        $this->start_output();
        $arr = array(array());
        $arr[0]['username'] = 'Tamaro';
        $arr[0]['upvotes'] = 1;
        $arr[0]['activity'] = 4;
        $arr[0]['reputation'] = 50;
        $arr[0]['downvotes'] = 30;

        $arr[1]['username'] = 'Telica';
        $arr[1]['upvotes'] = 3;
        $arr[1]['activity'] = 25;
        $arr[1]['reputation'] = 400;
        $arr[1]['downvotes'] = -10;

        $arr[2]['username'] = 'Walter';
        $arr[2]['upvotes'] = 4;
        $arr[2]['activity'] = 23;
        $arr[2]['reputation'] = 40;
        $arr[2]['downvotes'] = -50;
        // DB aufruf: $records = $DB->get_records('course', array('id' => $id), 'id, fullname, shortname');.
        $this->format_and_add_array_of_rows($arr, true);
        $this->get_table_data($this->context);
        $this->finish_output();
    }

    /**
     * Method to collect all the data.
     * Method will collect all users from the given course and will determine the user statistics
     *
     * @return 2d-array with user statistic
     */
    public function get_table_data($context) {
        global $DB;
        // Get all userdata from a course.
        $users = get_enrolled_users($context , '',  0, $userfields = 'u.id, u.firstname, u.lastname');

        $usertable = array(); // Usertable will have objects with every user and his statistics.

        // Step 1.0: Build the datatable with all relevant Informations.
        $sqlquery = 'SELECT discuss.id AS discussid,
                             discuss.userid AS discussuserid,
                             posts.id AS postid,
                             posts.userid AS postuserid,
                             posts.discussion AS postdiscussid,
                             ratings.id AS rateid,
                             ratings.rating AS rating,
                             ratings.userid AS rateuserid,
                             ratings.postid AS ratepostid,
                             ratings.discussionid AS ratediscussid
                      FROM {moodleoverflow_discussions} discuss
                      JOIN {moodleoverflow_posts} posts ON discuss.id = posts.discussion
                      LEFT JOIN {moodleoverflow_ratings} ratings ON posts.id = ratings.postid OR
                                                                       discuss.id = ratings.discussionid
                      WHERE discuss.course = ' . $this->courseid . ';';
        $ratingdata = $DB->get_records_sql($sqlquery);

        // Step 2.0: Now collect the data for every user in the course.
        foreach ($users as $user) {
            $student = new \stdClass();
            $student->id = $user->id;
            $student->name = $user->firstname . ' ' . $user->lastname;
            $student->submittedposts = array();
            $student->receivedupvotes = 0;
            $student->receiveddownvotes = 0;
            $student->activity = 0;
            $student->reputation = 0;
            foreach ($ratingdata as $row) {
                if ($row->postuserid == $student->id && $row->rating == RATING_UPVOTE) {
                    $student->receivedupvotes += 1;
                }
                if ($row->postuserid == $student->id && $row->rating == RATING_DOWNVOTE) {
                    $student->receiveddownvotes += 1;
                }
                if ($row->rateuserid == $student->id) {
                    $student->activity += 1;
                }
                if ($row->postuserid == $student->id && !array_key_exists($row->postid, $student->submittedposts)) {
                    $student->activity += 1;
                    $student->submittedposts[$row->postid] = $row->postid;
                }
                // Reputation.
            }

            array_push($usertable, $student);
        }
    }



    // Functions that show the data.
    public function col_username($row) {
        return $row->username;
    }

    public function col_upvotes($row) {
        return $row->upvotes;
    }

    public function col_activity($row) {
        return $row->activity;
    }

    public function col_reputation($row) {
        return $row->reputation;
    }

    public function col_downvotes($row) {
        return $row->downvotes;
    }

    public function other_cols($colname, $attempt) {
        return null;
    }
}
