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

namespace mod_moodleoverflow;

use mod_moodleoverflow\models\post;

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
     * Checks if post is anonymous. This function only checks if the moodleoverflow anonymous status. It does not check
     * if a post is seen as "anonymous" or not to a user.
     *
     * @param object $discussion
     * @param object $moodleoverflow
     * @param int $postinguserid The user id of the post that is being checked.
     *
     * @return bool true if user is not logged in, everything is marked anonymous
     * and if the question is anonymous and there are no answers yet, else false
     */
    public static function is_post_anonymous($discussion, $moodleoverflow, $postinguserid): bool {
        if ($postinguserid == 0) {
            return true;
        }

        if ($moodleoverflow->anonymous == self::EVERYTHING_ANONYMOUS) {
            return true;
        }

        if ($moodleoverflow->anonymous == self::QUESTION_ANONYMOUS) {
            return $discussion->userid == $postinguserid;
        }

        return false;
    }

    /**
     * Checks if a user can see the full post. This does not check if the post itself is anonymous in the moodleoverflow. It just
     * checks if from a user point of view.
     *
     * @param post $post The post that wants to be seen
     * @param int $userid The user that wants to see the post
     * @return bool
     */
    public static function user_can_see_post(post $post, int $userid): bool {
        $anonymous = (int) $post->get_moodleoverflow()->anonymous;
        $discussion = $post->get_discussion();
        return match ($anonymous) {
            self::NOT_ANONYMOUS => true,
            self::QUESTION_ANONYMOUS => $post->get_userid() == $userid || $post->get_userid() != $discussion->get_userid(),
            self::EVERYTHING_ANONYMOUS => $post->get_userid() == $userid,
        };
    }

    /**
     * Returns a usermapping for the Moodleoverflow, where each anonymized userid is replaced by an int, to form the
     * new name, e.g. Answerer #4.
     *
     * @param \stdClass $moodleoverflow
     * @param int $discussionid
     */
    public static function get_userid_mapping($moodleoverflow, $discussionid) {
        global $DB;
        if ($moodleoverflow->anonymous == self::NOT_ANONYMOUS) {
            return [];
        }
        if ($moodleoverflow->anonymous == self::QUESTION_ANONYMOUS) {
            return [
                $DB->get_field(
                    'moodleoverflow_posts',
                    'userid',
                    ['parent' => 0, 'discussion' => $discussionid]
                ) => get_string('questioner', 'mod_moodleoverflow'),
            ];
        }

        $userids = $DB->get_records_sql(
            'SELECT userid ' .
            'FROM {moodleoverflow_posts} ' .
            'WHERE discussion = :discussion ' .
            'GROUP BY userid ' .
            'ORDER BY MIN(created) ASC;',
            ['discussion' => $discussionid]
        );

        $mapping = [];
        $questioner = array_shift($userids);
        $mapping[$questioner->userid] = get_string('questioner', 'moodleoverflow');
        $i = 1;
        foreach ($userids as $user) {
            $mapping[$user->userid] = get_string('answerer', 'moodleoverflow', $i);
            $i++;
        }
        return $mapping;
    }
}
