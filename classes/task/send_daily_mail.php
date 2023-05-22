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
        // Go through each user that has unread posts.
        foreach ($users as $user) {
            // Sorts the records with "Order by courseid".
            $userdata = $DB->get_records('moodleoverflow_mail_info', array('userid' => $user->userid), 'courseid, forumid');
            $mail = array();
            // Fill the $mail array.
            foreach ($userdata as $row) {
                $currentcourse = $DB->get_record('course', array('id' => $row->courseid), 'fullname, id');
                $currentforum = $DB->get_record('moodleoverflow', array('id' => $row->forumid), 'name, id');
                $coursemoduleid = get_coursemodule_from_instance('moodleoverflow', $row->forumid);
                $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $row->forumdiscussionid), 'name, id');
                $unreadposts = $row->numberofposts;

                // Build url to the course, forum, and discussion.
                $linktocourse = new \moodle_url('/course/view.php', array('id' => $currentcourse->id));
                $linktoforum = new \moodle_url('/mod/moodleoverflow/view.php', array('id' => $coursemoduleid->id));
                $linktodiscussion = new \moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $discussion->id));

                // Now change the url to a clickable html link.
                $linktocourse = \html_writer::link($linktocourse->out(), $currentcourse->fullname);
                $linktoforum = \html_writer::link($linktoforum->out(), $currentforum->name);
                $linktodiscussion = \html_writer::link($linktodiscussion->out(), $discussion->name);

                // Build a single line string with the digest information and add it to the mailarray.
                $string = get_string('digestunreadpost', 'mod_moodleoverflow', array('linktocourse' => $linktocourse,
                                                                                     'linktoforum' => $linktoforum,
                                                                                     'linktodiscussion' => $linktodiscussion,
                                                                                     'unreadposts' => $unreadposts));
                array_push($mail, $string);
            }
            // Build the final message and send it to user. Then remove the sent records.
            $message = implode('<br>', $mail);
            $userto = $DB->get_record('user', array('id' => $user->userid));
            $from = \core_user::get_noreply_user();
            $subject = get_string('tasksenddailymail', 'mod_moodleoverflow');
            email_to_user($userto, $from, $subject, $message);
            $DB->delete_records('moodleoverflow_mail_info', array('userid' => $user->userid));
        }
    }
}
