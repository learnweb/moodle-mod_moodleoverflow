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
 * The anonymity setting of a moodleoverflow instance.
 *
 * The values are the ones stored in the column moodleoverflow.anonymous.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum anonymity: int {
    // Everybody is shown with their name.
    case NONE = 0;

    // Only the author of a question is anonymous, answers and comments are not.
    case QUESTIONS = 1;

    // Every author in the instance is anonymous.
    case EVERYTHING = 2;

    /**
     * Whether the author of a post is anonymous by this setting.
     *
     * Only the setting is evaluated. Whether a specific user may see the author anyway (capability) is decided
     * in the access layer.
     *
     * @param bool $isquestioner Whether the author of the post also started the discussion.
     * @return bool
     */
    public function hides_author(bool $isquestioner): bool {
        return match ($this) {
            self::NONE => false,
            self::QUESTIONS => $isquestioner,
            self::EVERYTHING => true,
        };
    }
}
