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

namespace mod_moodleoverflow\local\enum;

/**
 * The read tracking type of a moodleoverflow instance.
 *
 * The values are the ones stored in the column moodleoverflow.trackingtype. The effective type also depends on the
 * global settings trackreadposts and allowforcedreadtracking, which are evaluated in the moodleoverflow model.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum tracking_type: int {
    // Read tracking is switched off for everybody.
    case OFF = 0;

    // Users decide themselves whether they track this instance.
    case OPTIONAL = 1;

    // Read tracking is switched on for everybody and cannot be switched off.
    case FORCED = 2;

    /**
     * Whether users can switch read tracking on and off themselves in this type.
     *
     * @return bool
     */
    public function users_can_choose(): bool {
        return $this === self::OPTIONAL;
    }

    /**
     * Whether posts are tracked in this type without the user having chosen it.
     *
     * @return bool
     */
    public function tracks_by_default(): bool {
        return $this === self::FORCED;
    }
}
