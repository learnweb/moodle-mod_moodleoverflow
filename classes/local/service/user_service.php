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

use mod_moodleoverflow\models\post;

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
     * @return post[]
     */
    public static function get_all_user_posts(int $userid): array {
        global $DB;

        $sql = "SELECT post.*
                FROM {moodleoverflow_posts} post
                JOIN {user} u ON u.id = post.userid";
        $records = $DB->get_records_sql($sql);
        return array_map(fn($record) => post::from_record($record), $records);
    }
}