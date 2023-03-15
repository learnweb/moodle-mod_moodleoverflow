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


    private $cid;
    private $context;
    /**
     * Constructor for workflow_table.
     * @param int $uniqueid Unique id of this table.
     */
    public function __construct($uniqueid, $courseid, $coursecontext) {
        parent::__construct($uniqueid);
        global $PAGE;
        $this->cid = $courseid;
        $this->context = $coursecontext;
        $this->set_attribute('class', 'statistics-table');
        $this->set_attribute('id', $uniqueid);
        $this->define_columns(['username', 'getrating', 'activity', 'reputation', 'score']);
        $this->define_baseurl($PAGE->url);
        $this->define_headers(['user', 'received votes', 'user reputation', 'amount of activity', 'user score']);
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
        $arr[0]['getrating'] = 1;
        $arr[0]['activity'] = 4;
        $arr[0]['reputation'] = 50;
        $arr[0]['score'] = 30;

        $arr[1]['username'] = 'Telica';
        $arr[1]['getrating'] = 3;
        $arr[1]['activity'] = 25;
        $arr[1]['reputation'] = 400;
        $arr[1]['score'] = -10;

        $arr[2]['username'] = 'Walter';
        $arr[2]['getrating'] = 4;
        $arr[2]['activity'] = 23;
        $arr[2]['reputation'] = 40;
        $arr[2]['score'] = -50;
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

        /*$users = $DB->get_records_sql('SELECT u.lastname FROM mdl_role_assignments AS asg
                                       JOIN mdl_context AS context ON asg.contextid = context.id AND context.contextlevel = 50
                                       JOIN mdl_user AS u ON u.id = asg.userid
                                       JOIN mdl_course AS course ON context.instanceid = course.id
                                       WHERE asg.roleid = 5 AND course.id = :courseid', array('courseid' => $courseid));*/
        $users = get_enrolled_users($context , '',  0, $userfields = 'u.username');
        var_dump($users);
    }



    // Functions that show the data.
    public function col_username($row) {
        return $row->username;
    }

    public function col_getrating($row) {
        return $row->getrating;
    }

    public function col_activity($row) {
        return $row->activity;
    }

    public function col_reputation($row) {
        return $row->reputation;
    }

    public function col_score($row) {
        return $row->score;
    }

    public function other_cols($colname, $attempt) {
        return null;
    }
}
