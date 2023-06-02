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
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\ratings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');
require_once($CFG->libdir . '/tablelib.php');

/**
 * Table listing all user statistics of a course
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
    private $userstatsdata = array();

    /**
     * Constructor for workflow_table.
     *
     * @param int $uniqueid Unique id of this table.
     * @param int $courseid
     * @param int $moodleoverflow ID if the moodleoverflow
     * @param string $url The url of the table
     */
    public function __construct($uniqueid, $courseid, $moodleoverflow, $url) {
        global $OUTPUT;
        parent::__construct($uniqueid);

        $this->courseid = $courseid;
        $this->moodleoverflowid = $moodleoverflow;

        $this->set_attribute('class', 'moodleoverflow-statistics-table');
        $this->set_attribute('id', $uniqueid);
        $this->define_columns(['username', 'receivedupvotes', 'receiveddownvotes', 'activity', 'reputation']);
        $this->define_baseurl($url);
        $this->define_headers([get_string('fullnameuser'),
                               get_string('userstatsupvotes', 'moodleoverflow'),
                               get_string('userstatsdownvotes', 'moodleoverflow'),
                               get_string('userstatsactivity', 'moodleoverflow') . ' ' .
                                    $OUTPUT->help_icon('userstatsactivity', 'moodleoverflow'),
                               get_string('userstatsreputation', 'moodleoverflow') . ' ' .
                                    $OUTPUT->help_icon('userstatsreputation', 'moodleoverflow')
        ]);
        $this->get_table_data();
        $this->sortable(true, 'reputation', SORT_DESC);
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
        $this->text_sorting('reputation');
        $this->finish_output();
    }

    /**
     * Method to sort the userstatsdata-table.
     *
     * @param array $sortorder The sort order array.
     *
     * @return void
     */
    private function sort_table_data($sortorder) {
        $key = $sortorder['sortby'];
        // The index of each object in usertable is it's value of $key.
        $length = count($this->userstatsdata);
        if ($sortorder['sortorder'] == 4) {
            // 4 means sort in ascending order.
            usort($this->userstatsdata, function ($a, $b) use ($key) {
                return $a->$key - $b->$key;
            });
        } else if ($sortorder['sortorder'] == 3) {
            // 3 means sort in descending order.
            usort($this->userstatsdata, function ($a, $b) use ($key) {
                return $b->$key - $a->$key;
            });
        }
    }

    /**
     * Method to collect all the data.
     * Method will collect all users from the given course and will determine the user statistics
     *
     * @return 2d-array with user statistic
     */
    public function get_table_data() {
        global $DB;
        // Get all userdata from a course.
        $context = \context_course::instance($this->courseid);
        $users = get_enrolled_users($context , '',  0, $userfields = 'u.id, u.firstname, u.lastname');

        // Step 1.0: Build the datatable with all relevant Informations.
        $sqlquery = 'SELECT DISTINCT (ROW_NUMBER() OVER (ORDER BY ratings.id)) AS row_num,
                            ratings.id AS rateid,
                            discuss.userid AS discussuserid,
                            posts.id AS postid,
                            posts.userid AS postuserid,
                            ratings.rating AS rating,
                            ratings.userid AS rateuserid,
                            ratings.postid AS ratepostid,
                            discuss.id AS discussid,
                            posts.discussion AS postdiscussid,
                            ratings.discussionid AS ratediscussid,
                            posts.message AS postcontent,
                            moodleoverflow.anonymous AS anonymous
                      FROM {moodleoverflow_discussions} discuss
                      LEFT JOIN {moodleoverflow_posts} posts ON discuss.id = posts.discussion
                      LEFT JOIN {moodleoverflow_ratings} ratings ON posts.id = ratings.postid
                      LEFT JOIN {moodleoverflow} moodleoverflow ON discuss.moodleoverflow = moodleoverflow.id
                          WHERE (moodleoverflow.anonymous = 0 OR moodleoverflow.anonymous = 1) AND discuss.course = :courseid;';
        $ratingdata = $DB->get_records_sql($sqlquery, array('courseid' => $this->courseid));
        // Step 2.0: Now collect the data for every user in the course.
        foreach ($users as $user) {
            $student = new \stdClass();
            $student->id = $user->id;
            $student->name = $user->firstname . ' ' . $user->lastname;
            $linktostudent = new \moodle_url('/user/view.php', array('id' => $student->id, 'course' => $this->courseid));
            $student->link = \html_writer::link($linktostudent->out(), $student->name);
            $student->submittedposts = array(); // Key = postid, Value = postid.
            $student->ratedposts = array();     // Key = rateid, Value = rateid.
            $student->receivedupvotes = 0;
            $student->receiveddownvotes = 0;
            $student->activity = 0;
            $student->reputation = 0;
            foreach ($ratingdata as $row) {
                if ($row->postuserid !== $student->id && $row->rateuserid !== $student->id) {
                    continue;
                }
                if ($row->postuserid == $student->id && $row->rating == RATING_UPVOTE
                    && !($row->anonymous == anonymous::QUESTION_ANONYMOUS && $row->postuserid == $row->discussuserid)) {
                    $student->receivedupvotes += 1;
                }
                if ($row->postuserid == $student->id && $row->rating == RATING_DOWNVOTE
                    && !($row->anonymous == anonymous::QUESTION_ANONYMOUS && $row->postuserid == $row->discussuserid)) {
                    $student->receiveddownvotes += 1;
                }
                // A) In case the forum has anonymous questions -
                //      we do not count the ratings which are created by the (anonymous) - questioner.
                if ($row->rateuserid == $student->id
                    && !array_key_exists($row->rateid, $student->ratedposts)
                    && !($row->anonymous == anonymous::QUESTION_ANONYMOUS && $row->rateuserid == $row->discussuserid)) {
                    $student->activity += 1;
                    $student->ratedposts[$row->rateid] = $row->rateid;
                }
                // If the current user created the posts...
                // A) In case the forum has anonymous questions -
                //      we do not count the posts which are created by the (anonymous) - questioner.
                // B) The post wasn't counted
                if ($row->postuserid == $student->id && !array_key_exists($row->postid, $student->submittedposts)
                    && !($row->anonymous == anonymous::QUESTION_ANONYMOUS && $row->postuserid == $row->discussuserid)) {
                    $student->activity += 1;
                    $student->submittedposts[$row->postid] = $row->postid;
                }
            }
            // Get the user reputation from the course.
            $student->reputation = ratings::moodleoverflow_get_reputation_course($this->courseid, $student->id);
            array_push($this->userstatsdata, $student);
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
        if ($row->receivedupvotes > 0) {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-success') .
                   $row->receivedupvotes . \html_writer::end_span());
        } else {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-warning') .
                   $row->receivedupvotes . \html_writer::end_span());
        }
    }

    /**
     * downvotes column
     * @param object $row
     * @return string
     */
    public function col_receiveddownvotes($row) {
        if ($row->receiveddownvotes > 0) {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-success') .
                    $row->receiveddownvotes . \html_writer::end_span());
        } else {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-warning') .
                   $row->receiveddownvotes . \html_writer::end_span());
        }
    }

    /**
     * activity column
     * @param object $row
     * @return string
     */
    public function col_activity($row) {
        if ($row->activity > 0) {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-success') .
                   $row->activity . \html_writer::end_span());
        } else {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-warning') .
                   $row->activity . \html_writer::end_span());
        }
    }

    /**
     * reputation column
     * @param object $row
     * @return string
     */
    public function col_reputation($row) {
        if ($row->reputation > 0) {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-success') .
                   $row->reputation . \html_writer::end_span());
        } else {
            return \html_writer::tag('h5', \html_writer::start_span('badge badge-warning') .
                   $row->reputation . \html_writer::end_span());
        }
    }

    /**
     * error handling
     * @param object $colname
     * @param int    $attempt
     * @return null
     */
    public function other_cols($colname, $attempt) {
        return null;
    }
}
