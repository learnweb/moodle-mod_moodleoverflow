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

namespace mod_moodleoverflow\local\dto;

/**
 * DTO for user posts that are shown on the user.php
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userpost_dto {
    /**
     * Constructor.
     *
     * @param int $id
     * @param string $message
     * @param string $discussionsubject
     * @param string $modflow moodleoverflow name
     * @param string $course course short name
     * @param int $courseid id of the course the post was written in
     * @param int $created
     * @param string $posturl url to the post
     * @param string $discussionurl url to the discussion
     * @param string $modflowurl url to the moodleoverflow
     */
    public function __construct(
        /** @var int $id */
        public readonly int $id,
        /** @var string $message */
        public readonly string $message,
        /** @var string $discussionsubject */
        public readonly string $discussionsubject,
        /** @var string $modflow moodleoverflow name */
        public readonly string $modflow,
        /** @var string $course course short name */
        public readonly string $course,
        /** @var int $courseid id of the course the post was written in */
        public readonly int $courseid,
        /** @var int $created */
        public readonly int $created,
        /** @var string $posturl url to the post */
        public readonly string $posturl,
        /** @var string $discussionurl url to the discussion */
        public readonly string $discussionurl,
        /** @var string $modflowurl url to the moodleoverflow */
        public readonly string $modflowurl
    ) {
    }

    /**
     * Export function for the REST controller.
     * @return array
     */
    public function to_array(): array {
        return get_object_vars($this);
    }
}
