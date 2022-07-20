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
class review {

    /**
     * Nothing has to be reviewed.
     */
    const NOTHING = 0;
    /**
     * New questions (= discussions) have to be reviewed.
     */
    const QUESTIONS = 1;
    /**
     * Everything has to be reviewed.
     */
    const EVERYTHING = 2;

    /**
     * Returns the review level of the given moodleoverflow instance, considering the global allowreview setting.
     * @param object $moodleoverflow
     * @return int
     */
    public static function get_review_level(object $moodleoverflow): int {
        if (get_config('moodleoverflow', 'allowreview') == '1') {
            return $moodleoverflow->needsreview;
        } else {
            return self::NOTHING;
        }
    }

    /**
     * Returns a short review info for the discussion.
     * @param int $discussionid The discussionid.
     * @return object {"count": amount needing review (int) , "first": first postid needing review (int)}
     */
    public static function get_short_review_info_for_discussion(int $discussionid) {
        global $DB;

        return $DB->get_record_sql(
            'SELECT COUNT(*) as count, MIN(id) AS first ' .
                'FROM {moodleoverflow_posts} ' .
                'WHERE discussion = :discussionid AND reviewed = 0',
            ['discussionid' => $discussionid]
        );
    }

    /**
     * @param $afterpostid
     * @return string|null
     */
    public static function get_first_review_post($afterpostid = null) {
        global $DB;

        $params = [];
        $orderby = '';
        $addwhere = '';

        if ($afterpostid) {
            $afterdiscussionid = $DB->get_field('moodleoverflow_posts', 'discussion', ['id' => $afterpostid],
                MUST_EXIST);

            $orderby = 'CASE WHEN (discussion > :afterdiscussionid OR (discussion = :afterdiscussionid2 AND id > :afterpostid)) THEN 0 ELSE 1 END, ';
            $params['afterdiscussionid'] = $afterdiscussionid;
            $params['afterdiscussionid2'] = $afterdiscussionid;
            $params['afterpostid'] = $afterpostid;

            $addwhere = ' AND id != :notpostid ';
            $params['notpostid'] = $afterpostid;
        }
        $record = $DB->get_record_sql(
            'SELECT id as postid, discussion as discussionid FROM {moodleoverflow_posts} ' .
            "WHERE reviewed = 0 $addwhere " .
            "ORDER BY $orderby discussion, id " .
            'LIMIT 1',
            $params
        );
        if ($record) {
            return (new \moodle_url('/mod/moodleoverflow/discussion.php', [
                'd' => $record->discussionid
            ], 'p' . $record->postid))->out(false);
        } else {
            return null;
        }
    }


}
