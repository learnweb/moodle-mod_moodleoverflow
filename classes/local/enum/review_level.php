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
 * The review level of a moodleoverflow instance.
 *
 * The values are the ones stored in the column moodleoverflow.needsreview. The effective level also depends on the
 * global setting allowreview, which is evaluated in the moodleoverflow model.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum review_level: int {
    // Posts are visible as soon as they are written.
    case NONE = 0;

    // Only questions have to be reviewed before they become visible.
    case QUESTIONS = 1;

    // Every post has to be reviewed before it becomes visible.
    case EVERYTHING = 2;

    /**
     * Whether a post has to be reviewed before it becomes visible.
     *
     * @param bool $isstarter Whether the post is the discussion starter.
     * @return bool
     */
    public function requires_review(bool $isstarter): bool {
        return match ($this) {
            self::NONE => false,
            self::QUESTIONS => $isstarter,
            self::EVERYTHING => true,
        };
    }
}
