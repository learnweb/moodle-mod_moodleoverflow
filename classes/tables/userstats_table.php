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
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow\tables;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');
require_once($CFG->libdir . '/tablelib.php');
use mod_moodleoverflow\output\helpicon;
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

    /** @var \stdClass Help icon for amountofactivity-column.*/
    private $helpactivity;

    /**
     * Constructor for workflow_table.
     *
     * @param int $uniqueid Unique id of this table.
     * @param int $courseid
     * @param int $moodleoverflow ID if the moodleoverflow
     * @param string $url The url of the table
     */
    public function __construct($uniqueid, $courseid, $moodleoverflow, $url) {
        global $PAGE;
        parent::__construct($uniqueid);
        $PAGE->requires->js_call_amd('mod_moodleoverflow/activityhelp', 'init');

        $this->courseid = $courseid;
        $this->moodleoverflowid = $moodleoverflow;
        $this->set_helpactivity();

        $this->set_attribute('class', 'moodleoverflow-statistics-table');
        $this->set_attribute('id', $uniqueid);
        $this->define_columns(['username', 'receivedupvotes', 'receiveddownvotes', 'activity', 'reputation']);
        $this->define_baseurl($url);
        $this->define_headers([get_string('fullnameuser'),
                               get_string('userstatsupvotes', 'moodleoverflow'),
                               get_string('userstatsdownvotes', 'moodleoverflow'),
                               (get_string('userstatsactivity', 'moodleoverflow') . $this->helpactivity->object),
                               get_string('userstatsreputation', 'moodleoverflow')]);
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
        global $DB;
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
            $this->quick_usertable_sort(0, $length - 1, $key, 'asc');
        } else if ($sortorder['sortorder'] == 3) {
            // 3 means sort in descending order.
            $this->quick_usertable_sort(0, $length - 1, $key, 'desc');
        }
    }

    /**
     * Sorts userstatsdata with quicksort algorithm.
     *
     * @param int    $low       index for quicksort.
     * @param int    $high      index for quicksort.
     * @param int    $key       the column that is being sorted (upvotes, downvotes etc.).
     * @param string $order     sort in ascending or descending order.
     *
     * @return void
     */
    private function quick_usertable_sort($low, $high, $key, $order) {
        if ($low >= $high) {
            return;
        }
        $left = $low;
        $right = $high;
        $pivot = $this->userstatsdata[intval(($low + $high) / 2)];
        $pivot = $pivot->$key;
        do {
            if ($order == 'asc') {
                while ($this->userstatsdata[$left]->$key < $pivot) {
                    $left++;
                }
                while ($this->userstatsdata[$right]->$key > $pivot) {
                    $right--;
                }
            } else if ($order == 'desc') {
                while ($this->userstatsdata[$left]->$key > $pivot) {
                    $left++;
                }
                while ($this->userstatsdata[$right]->$key < $pivot) {
                    $right--;
                }
            }
            if ($left <= $right) {
                $temp = $this->userstatsdata[$right];
                $this->userstatsdata[$right] = $this->userstatsdata[$left];
                $this->userstatsdata[$left] = $temp;
                $right--;
                $left++;
            }
        } while ($left <= $right);
        if ($low < $right) {
            if ($order == 'asc') {
                $this->quick_usertable_sort($low, $right, $key, 'asc');
            } else if ($order == 'desc') {
                $this->quick_usertable_sort($low, $right, $key, 'desc');
            }
        }
        if ($high > $left) {
            if ($order == 'asc') {
                $this->quick_usertable_sort($left, $high, $key, 'desc');
            } else if ($order == 'desc') {
                $this->quick_usertable_sort($left, $high, $key, 'desc');
            }
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
        $sqlquery = 'SELECT (ROW_NUMBER() OVER (ORDER BY ratings.id)) AS row_num,
                            ratings.id AS rateid,
                            discuss.userid AS discussuserid,
                            posts.id AS postid,
                            posts.userid AS postuserid,
                            ratings.rating AS rating,
                            ratings.userid AS rateuserid,
                            ratings.postid AS ratepostid,
                            discuss.id AS discussid,
                            posts.discussion AS postdiscussid,
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
            $student->ratedposts = array();     // Key = rateid, Value = rateid.
            $student->receivedupvotes = 0;
            $student->receiveddownvotes = 0;
            $student->activity = 0;
            $student->reputation = 0;
            foreach ($ratingdata as $row) {
                if ($row->postuserid !== $student->id && $row->rateuserid !== $student->id) {
                    continue;
                }
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
            array_push($this->userstatsdata, $student);
        }
    }

    /**
     * Return the userstatsdata-table.
     */
    public function get_usertable() {
        return $this->userstatsdata;
    }

    /**
     * Setup the help icon for amount of activity
     */
    public function set_helpactivity() {
        $htmlclass = 'helpactivityclass btn btn-link';
        $content = get_string('helpamountofactivity', 'moodleoverflow');
        $helpobject = new helpicon($htmlclass, $content);
        $this->helpactivity = new \stdClass();
        $this->helpactivity->object = $helpobject->get_helpicon();
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
