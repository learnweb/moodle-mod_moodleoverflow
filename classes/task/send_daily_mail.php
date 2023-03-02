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
 * Task schedule configuration for the plugintype_pluginname plugin.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023, Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow\task;
defined('MOODLE_INTERNAL') || die();
/**
 * This task sends a daily mail of unread posts
 */
class send_daily_mail extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksenddailymail', 'mod_moodleoverflow');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;
        // Call your own api.
        $users = $DB->get_records_sql('SELECT DISTINCT userid FROM {moodleoverflow_mail_info}');
        if (empty($users)) {
            mtrace('No daily mail to send.');
            return;
        }
        foreach ($users as $user) {
            $userdata = $DB->get_records('moodleoverflow_mail_info', array('userid' => $user->userid), 'courseid, forumid'); // order by courseid
            $mail = array();
            foreach ($userdata as $row) {
                $currentcourse = $DB->get_record('course', array('id' => $row->courseid), 'fullname');
                $currentforum = $DB->get_record('moodleoverflow', array('id' => $row->forumid), 'name');
                $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $row->forumdiscussionid), 'name');
                $unreadposts = $row->numberofposts;
                $string = get_string('digestunreadpost', 'mod_moodleoverflow', array('currentcourse' => $currentcourse->fullname,
                                                                                     'currentforum' => $currentforum->name,
                                                                                     'discussion' => $discussion->name,
                                                                                     'unreadposts' => $unreadposts));
                array_push($mail, $string);
            }
            $message = implode('<br>', $mail);
            // mtrace($message);.
            // send message to user.
            $userto = $DB->get_record('user', array('id' => $user->userid));
            $from = \core_user::get_noreply_user();
            $subject = get_string('tasksenddailymail', 'mod_moodleoverflow');

            email_to_user($userto, $from, $subject, $message);
        }
    }
}
