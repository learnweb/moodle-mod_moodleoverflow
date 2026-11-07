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
 * Data transfer object for the data that userstats.php shows: the statistics of every participant and the activity
 * timeline of every moodleoverflow of the course whose statistics the viewer may see.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstats_page_dto {
    /**
     * Constructor.
     *
     * @param userstats_dto[] $users the statistics of every participant, ordered by name
     * @param activity_day_dto[][] $timeline the days with activity, by moodleoverflow id
     */
    public function __construct(
        /** @var userstats_dto[] $users the statistics of every participant, ordered by name */
        public readonly array $users,
        /** @var activity_day_dto[][] $timeline the days with activity, by moodleoverflow id */
        public readonly array $timeline
    ) {
    }

    /**
     * Export function for the React page.
     * @return array
     */
    public function to_array(): array {
        $timeline = [];
        foreach ($this->timeline as $modflowid => $days) {
            $timeline[$modflowid] = array_map(fn(activity_day_dto $day) => $day->to_array(), $days);
        }
        return [
            'users' => array_map(fn(userstats_dto $user) => $user->to_array(), $this->users),
            // An object, so that the moodleoverflow ids stay keys in JSON instead of becoming a list.
            'timeline' => (object) $timeline,
        ];
    }
}
