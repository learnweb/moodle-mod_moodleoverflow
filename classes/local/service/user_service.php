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
use mod_moodleoverflow\local\dto\userpost_dto;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
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
     * @param int $userid The user whose posts are shown.
     * @param int $viewerid The user who wants to see the posts
     * @return array
     * @throws moodle_exception
     */
    public static function get_all_user_posts(int $userid, int $viewerid): array {
        global $DB;

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

        // Build every moodleoverflow and discussion once; the posts are taken from their discussion.
        $moodleoverflows = array_map(
            fn($record) => moodleoverflow::from_record($record),
            $DB->get_records_list('moodleoverflow', 'id', array_unique(array_column($records, 'modflowid')))
        );
        $discussions = array_map(
            fn($record) => discussion::from_record($record, $moodleoverflows[$record->moodleoverflow]),
            $DB->get_records_list('moodleoverflow_discussions', 'id', array_unique(array_column($records, 'discussid')))
        );
        $userposts = [];
        // Build the dto's. Filter out posts that the viewer can't see.
        foreach ($records as $record) {
            $post = $discussions[$record->discussid]->get_posts()[$record->id];

            if (!permissions::can_view_post($post, $viewerid) || !permissions::can_view_author($post, $viewerid)) {
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
                $post->get_link()->out(false),
                $discussions[$record->discussid]->get_link()->out(false),
                $moodleoverflows[$record->modflowid]->get_link()->out(false),
            );
            $userposts[] = $dto->to_array();
        }
        return $userposts;
    }
}
