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

use context;
use core_table\dynamic as dynamic_table;
use core_table\local\filter\filterset;
use core_user\output\status_field;
use core_user\table\participants_search;
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

    private $courseid;            // Course ID.
    private $course;
    private $context;
    private $moodleoverflowid;    // Moodleoverflow that started the printing of statistics.
    private $usertable = array(); // Usertable will have objects with every user and his statistics.
    /**
     * Constructor for workflow_table.
     * @param int $uniqueid Unique id of this table.
     */
    public function __construct($uniqueid, $courseid, $coursecontext, $moodleoverflow) {
        parent::__construct($uniqueid);
        global $PAGE;
        $this->courseid = $courseid;
        $this->course = $this->courseid;
        $this->context = $coursecontext;
        $this->moodleoverflowid = $moodleoverflow;
        $this->set_attribute('class', 'statistics-table');
        $this->set_attribute('id', $uniqueid);
        $this->define_columns(['id', 'username', 'upvotes', 'downvotes', 'activity', 'reputation']);
        $this->define_baseurl($PAGE->url);
        $this->define_headers(['User ID', 'User', 'Received upvotes', 'Received downvotes', 'Amount of activity', 'User reputation']);
        $this->get_table_data($this->context);
        $this->sortable(true, 'id', SORT_ASC);
        $this->setup();
    }

    /**
     * Method to display the table.
     * @return void
     */
    public function out() {
        global $DB;
        $this->start_output();
        $this->format_and_add_array_of_rows($this->usertable, true);
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

        // Step 1.0: Build the datatable with all relevant Informations.
        $sqlquery = 'SELECT  ratings.id AS rateid,
                             discuss.id AS discussid,
                             discuss.userid AS discussuserid,
                             posts.id AS postid,
                             posts.userid AS postuserid,
                             posts.discussion AS postdiscussid,
                             ratings.rating AS rating,
                             ratings.userid AS rateuserid,
                             ratings.postid AS ratepostid,
                             ratings.discussionid AS ratediscussid
                      FROM {moodleoverflow_discussions} discuss
                      LEFT JOIN {moodleoverflow_posts} posts ON discuss.id = posts.discussion
                      LEFT JOIN {moodleoverflow_ratings} ratings ON posts.id = ratings.postid
                      WHERE discuss.course = ' . $this->courseid . ';';
        $ratingdata = $DB->get_records_sql($sqlquery);
        // Step 2.0: Now collect the data for every user in the course.
        foreach ($users as $user) {
            $student = new \stdClass();
            $student->id = $user->id;
            $student->name = $user->firstname . ' ' . $user->lastname;
            $linktostudent = new \moodle_url('/user/view.php', array('id' => $student->id, 'course' => $this->courseid));
            $student->link = \html_writer::link($linktostudent->out(), $student->name);
            $student->submittedposts = array(); // Key = postid, Value = postid.
            $student->ratedposts = array();     // Key = rateod, Value = rateid.
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
                if ($row->rateuserid == $student->id && !array_key_exists($row->rateid, $student->ratedposts)) {
                    $student->activity += 1;
                    $student->ratedposts[$row->rateid] = $row->rateid;
                }
                if ($row->postuserid == $student->id && !array_key_exists($row->postid, $student->submittedposts)) {
                    $student->activity += 1;
                    $student->submittedposts[$row->postid] = $row->postid;
                }
            }
            // Get the user reputation from the course.
            $student->reputation = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($this->moodleoverflowid, 
                                                                                              $student->id);


            // add badges, and HEADINGS. => TODO
            if($student->receivedupvotes > 0) {
                $student->receivedupvotes = \html_writer::start_span('badge badge-success') . $student->receivedupvotes
                                            . \html_writer::end_span();
            }
            else {
                $student->receivedupvotes = \html_writer::start_span('badge badge-warning') . $student->receivedupvotes
                                            . \html_writer::end_span();
            }
            if($student->receiveddownvotes > 0) {
                $student->receiveddownvotes = \html_writer::start_span('badge badge-success') . $student->receiveddownvotes
                                            . \html_writer::end_span();
            }
            else {
                $student->receiveddownvotes = \html_writer::start_span('badge badge-warning') . $student->receiveddownvotes
                                            . \html_writer::end_span();
            }
            if($student->activity > 0) {
                $student->activity = \html_writer::start_span('badge badge-success') . $student->activity
                                            . \html_writer::end_span();
            }
            else {
                $student->activity = \html_writer::start_span('badge badge-warning') . $student->activity
                                            . \html_writer::end_span();
            }
            if($student->reputation > 0) {
                $student->reputation = \html_writer::start_span('badge badge-success') . $student->reputation
                                            . \html_writer::end_span();
            }
            else {
                $student->reputation = \html_writer::start_span('badge badge-warning') . $student->reputation
                                            . \html_writer::end_span();
            }
            array_push($this->usertable, $student);
        }
    }



    // Functions that show the data.
    public function col_userid($row) {
        return $row->id;
    }
    public function col_username($row) {
        return $row->link;
    }

    public function col_upvotes($row) {
        return $row->receivedupvotes;
    }

    public function col_downvotes($row) {
        return $row->receiveddownvotes;
    }

    public function col_activity($row) {
        return $row->activity;
    }

    public function col_reputation($row) {
        return $row->reputation;
    }  

    public function other_cols($colname, $attempt) {
        return null;
    }
}
