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

namespace mod_moodleoverflow\local\dto\userstats;

/**
 * Data transfer object for one day of the activity timeline of the user statistics: how many posts were written and
 * how many votes were given. It holds no information about the authors.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_day_dto {
    /**
     * Constructor.
     *
     * @param string $day the date as YYYY-MM-DD
     * @param int $posts reviewed posts written on the day
     * @param int $votes up- and downvotes given on the day
     */
    public function __construct(
        /** @var string $day the date as YYYY-MM-DD */
        public readonly string $day,
        /** @var int $posts reviewed posts written on the day */
        public readonly int $posts,
        /** @var int $votes up- and downvotes given on the day */
        public readonly int $votes
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
