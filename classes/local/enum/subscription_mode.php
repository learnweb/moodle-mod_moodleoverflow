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
 * The subscription mode of a moodleoverflow instance.
 *
 * The values are the ones stored in the column moodleoverflow.forcesubscribe.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum subscription_mode: int {
    // Users decide themselves, nobody is subscribed at the beginning.
    case OPTIONAL = 0;

    // Everybody is subscribed and cannot unsubscribe.
    case FORCED = 1;

    // Everybody is subscribed at the beginning, but can unsubscribe.
    case AUTO = 2;

    // Nobody can subscribe.
    case DISABLED = 3;

    /**
     * Whether users can subscribe and unsubscribe themselves in this mode.
     *
     * @return bool
     */
    public function users_can_choose(): bool {
        return $this === self::OPTIONAL || $this === self::AUTO;
    }

    /**
     * Whether users are subscribed in this mode without having chosen it.
     *
     * @return bool
     */
    public function subscribes_by_default(): bool {
        return $this === self::FORCED || $this === self::AUTO;
    }
}
