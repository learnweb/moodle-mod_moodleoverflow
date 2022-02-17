<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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

/**
 * Moodleoverflow anonymous related class.
 *
 * @package   mod_moodleoverflow
 * @copyright 2021 Justus Dieckmann WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

/**
 * Class for Moodleoverflow anonymity
 *
 * @package   mod_moodleoverflow
 * @copyright 2021 Justus Dieckmann WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class anonymous {

    /**
     * Used if nothing is anonymous.
     */
    const NOT_ANONYMOUS = 0;
    /**
     * Used if question is anonymous.
     */
    const QUESTION_ANONYMOUS = 1;
    /**
     * Used if whole post is anonymous.
     */
    const EVERYTHING_ANONYMOUS = 2;

    /**
     * Checks if post is anonymous.
     *
     * @param object $post              moodleoverflow post object
     * @param object $moodleoverflow
     * @param int $postinguserid        user id of posting user
     *
     * @return bool true if user is not logged in, everything is marked anonymous
     * and if the question is anonymous and there are no answers yet, else false
     */
    public static function is_post_anonymous($post, $moodleoverflow, $postinguserid): bool {
        if ($postinguserid == 0) {
            return true;
        }

        if ($moodleoverflow->anonymous == self::EVERYTHING_ANONYMOUS) {
            return true;
        }

        if ($moodleoverflow->anonymous == self::QUESTION_ANONYMOUS) {
            return $post->parent == 0;
        }

        return false;
    }

}
