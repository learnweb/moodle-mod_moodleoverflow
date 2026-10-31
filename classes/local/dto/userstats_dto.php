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
 * DTO for the statistics of one user that are shown on userstats.php: their activity, reputation and received votes
 * in the moodleoverflow the page was opened from and in the whole course.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstats_dto {
    /**
     * Constructor.
     *
     * @param int $userid
     * @param string $name full name of the user, as the viewer may see it
     * @param string $profileurl url to the course profile of the user
     * @param int $forumactivity written posts and given ratings in this moodleoverflow
     * @param int $forumreputation reputation in this moodleoverflow
     * @param int $forumupvotes received upvotes in this moodleoverflow
     * @param int $forumdownvotes received downvotes in this moodleoverflow
     * @param int $courseactivity written posts and given ratings in the moodleoverflows of the course
     * @param int $coursereputation reputation shared in the course (cross module reputation)
     * @param int $courseupvotes received upvotes in the moodleoverflows of the course
     * @param int $coursedownvotes received downvotes in the moodleoverflows of the course
     */
    public function __construct(
        /** @var int $userid */
        public readonly int $userid,
        /** @var string $name full name of the user, as the viewer may see it */
        public readonly string $name,
        /** @var string $profileurl url to the course profile of the user */
        public readonly string $profileurl,
        /** @var int $forumactivity written posts and given ratings in this moodleoverflow */
        public readonly int $forumactivity,
        /** @var int $forumreputation reputation in this moodleoverflow */
        public readonly int $forumreputation,
        /** @var int $forumupvotes received upvotes in this moodleoverflow */
        public readonly int $forumupvotes,
        /** @var int $forumdownvotes received downvotes in this moodleoverflow */
        public readonly int $forumdownvotes,
        /** @var int $courseactivity written posts and given ratings in the moodleoverflows of the course */
        public readonly int $courseactivity,
        /** @var int $coursereputation reputation shared in the course (cross module reputation) */
        public readonly int $coursereputation,
        /** @var int $courseupvotes received upvotes in the moodleoverflows of the course */
        public readonly int $courseupvotes,
        /** @var int $coursedownvotes received downvotes in the moodleoverflows of the course */
        public readonly int $coursedownvotes
    ) {
    }

    /**
     * Export function for the React page.
     * @return array
     */
    public function to_array(): array {
        return get_object_vars($this);
    }
}
