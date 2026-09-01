<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\local\service;

use core\exception\moodle_exception;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\local\dto\userpost_dto;
use mod_moodleoverflow\models\discussion;
use mod_moodleoverflow\models\post;
use moodle_url;

/**
 * Service class for moodle-wide moodleoverflow requests regarding a single user.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_service {
    /**
     * Returns all posts a user has written throughout all moodleoverflows
     * @param int $userid
     * @return array
     * @throws moodle_exception
     */
    public static function get_all_user_posts(int $userid): array {
        global $DB, $USER;

        // Search all the user posts.
        $sql = "SELECT post.*, course.id AS courseid, course.shortname AS coursename, d.name AS discussionsubject,
                       d.id AS discussid, m.name AS modflow, m.id AS modflowid
                FROM {moodleoverflow_posts} post
                JOIN {moodleoverflow_discussions} d ON d.id = post.discussion
                JOIN {moodleoverflow} m ON m.id = d.moodleoverflow
                JOIN {course} course ON course.id = m.course
                WHERE post.userid = :userid
                ORDER BY post.created DESC";
        $records = $DB->get_records_sql($sql, ['userid' => $userid]);

        if (!$records) {
            return [];
        }

        // Build caches for everything the loop needs, keyed by id, in three queries. This reduces DB calls from the post object.
        $discussions = $DB->get_records_list('moodleoverflow_discussions', 'id', array_unique(array_column($records, 'discussid')));
        $moodleoverflows = $DB->get_records_list('moodleoverflow', 'id', array_unique(array_column($records, 'modflowid')));
        $courses = $DB->get_records_list('course', 'id', array_unique(array_column($records, 'courseid')));
        $hascourseaccess = [];
        $modinfos = [];

        $userposts = [];
        $path = '/mod/moodleoverflow/';
        // Build the dto's. Filter out posts that the current user can't see.
        foreach ($records as $record) {
            $hascourseaccess[$record->courseid] ??= can_access_course($courses[$record->courseid], $USER->id, '', true);
            if (!$hascourseaccess[$record->courseid]) {
                continue;
            }

            $modinfos[$record->courseid] ??= get_fast_modinfo($record->courseid, $USER->id);
            $cm = $modinfos[$record->courseid]->get_instance_of('moodleoverflow', $record->modflowid);
            if (!$cm) {
                continue;
            }

            $post = post::from_record($record);
            $post->cmobject = $cm;
            $post->moodleoverflowobject = $moodleoverflows[$record->modflowid];
            $post->discussionobject = discussion::from_record($discussions[$record->discussid]);

            if (!moodleoverflow_user_can_see_post($post, $cm, $USER->id) || !anonymous::user_can_see_post($post, $USER->id)) {
                continue;
            }

            $dto = new userpost_dto(
                $post->get_id(),
                $post->get_message_formatted(),
                $record->discussionsubject,
                $record->modflow,
                $record->coursename,
                $record->courseid,
                $post->created,
                (new moodle_url($path . 'discussion.php', ['d' => $record->discussid], 'p' . $post->get_id()))->out(),
                (new moodle_url($path . 'discussion.php', ['d' => $record->discussid]))->out(),
                (new moodle_url($path . 'view.php', ['m' => $record->modflowid]))->out(),
            );
            $userposts[] = $dto->to_array();
        }
        return $userposts;
    }
}
