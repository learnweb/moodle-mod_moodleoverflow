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

namespace mod_moodleoverflow\local\service;

use core\exception\moodle_exception;
use dml_exception;
use mod_moodleoverflow\local\dto\userstats_dto;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use moodle_url;

/**
 * Service class for the user statistics of a course: how active the participants are, which votes they received and
 * how much reputation they have.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstats {
    /**
     * Returns the statistics of every participant of a moodleoverflow, for this moodleoverflow and for the course.
     *
     * - Activity: every reviewed post and every given rating counts one point. Unreviewed posts are not published yet.
     * - The anonymity of a moodleoverflow is kept: posts and marks of hidden authors and the votes they received do not
     *   count, so the numbers do not reveal who wrote a post. Votes always count for the rater, voters are never shown.
     * - The course numbers add up the moodleoverflows of the course whose statistics the viewer may see. The course
     *   reputation is the reputation shared in the course (rating::get_course_reputations()), the same number that is
     *   shown next to the posts.
     *
     * @param moodleoverflow $modflow The moodleoverflow the statistics were opened from
     * @param int $viewerid The user who sees the statistics
     * @return userstats_dto[] One per participant, ordered by name
     * @throws dml_exception|moodle_exception
     */
    public static function get_statistics(moodleoverflow $modflow, int $viewerid): array {
        global $DB;
        permissions::ensure(permissions::can_view_userstats($modflow, $viewerid), 'cannotviewuserstats');

        // The moodleoverflows whose numbers the viewer may see, this one included.
        $modflows = array_filter(
            array_map(
                fn($record) => moodleoverflow::from_record($record),
                $DB->get_records('moodleoverflow', ['course' => $modflow->course])
            ),
            fn(moodleoverflow $other) => $other->id === $modflow->id || permissions::can_view_userstats($other, $viewerid)
        );
        $counts = array_map(fn(moodleoverflow $other) => self::count_activity($other), $modflows);
        $forumreputations = rating::get_instance_reputations($modflow);
        $coursereputations = rating::get_course_reputations($modflow->course);
        $viewfullnames = permissions::can_view_fullnames($modflow, $viewerid);

        $statistics = [];
        foreach ($modflow->get_participants() as $user) {
            $userid = (int) $user->id;
            $forum = fn(string $field) => $counts[$modflow->id][$userid][$field] ?? 0;
            $course = fn(string $field) => array_sum(array_map(fn($other) => $other[$userid][$field] ?? 0, $counts));
            $profileurl = new moodle_url('/user/view.php', ['id' => $userid, 'course' => $modflow->course]);
            $statistics[] = new userstats_dto(
                $userid,
                fullname($user, $viewfullnames),
                $profileurl->out(false),
                $forum('activity'),
                $forumreputations[$userid] ?? 0,
                $forum('upvotes'),
                $forum('downvotes'),
                $course('activity'),
                $coursereputations[$userid] ?? 0,
                $course('upvotes'),
                $course('downvotes')
            );
        }
        return $statistics;
    }

    /**
     * Counts the activity and the received votes of every user in a moodleoverflow.
     * @param moodleoverflow $modflow
     * @return array[] By user id: ['activity' => int, 'upvotes' => int, 'downvotes' => int]
     * @throws dml_exception|moodle_exception
     */
    private static function count_activity(moodleoverflow $modflow): array {
        global $DB;
        $counts = [];
        $add = function (int $userid, string $field) use (&$counts): void {
            $counts[$userid] ??= ['activity' => 0, 'upvotes' => 0, 'downvotes' => 0];
            $counts[$userid][$field]++;
        };
        $hidden = [];
        $ishidden = function (int $userid, bool $isquestioner) use ($modflow, &$hidden): bool {
            return $hidden[$userid][(int) $isquestioner] ??= permissions::is_hidden_as_author($modflow, $userid, $isquestioner);
        };

        // Written posts. A removed author (userid 0, set by the privacy provider) is nobody's activity.
        $sql = "SELECT p.id, p.userid, d.userid AS questionerid
                  FROM {moodleoverflow_posts} p
                  JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                 WHERE d.moodleoverflow = :modflowid AND p.reviewed = 1 AND p.userid <> 0";
        foreach ($DB->get_records_sql($sql, ['modflowid' => $modflow->id]) as $post) {
            $authorid = (int) $post->userid;
            if (!$ishidden($authorid, $authorid === (int) $post->questionerid)) {
                $add($authorid, 'activity');
            }
        }

        // Ratings. A mark shows who set it (helpful marks come from the questioner), so it only counts as activity if
        // the rater would be visible as an author in the discussion.
        $sql = "SELECT r.id, r.userid AS raterid, r.rating, p.userid AS authorid, d.userid AS questionerid
                  FROM {moodleoverflow_ratings} r
                  JOIN {moodleoverflow_posts} p ON p.id = r.postid
                  JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                 WHERE r.moodleoverflowid = :modflowid";
        foreach ($DB->get_records_sql($sql, ['modflowid' => $modflow->id]) as $rating) {
            $type = rating_type::tryFrom((int) $rating->rating);
            $raterid = (int) $rating->raterid;
            $authorid = (int) $rating->authorid;
            $questionerid = (int) $rating->questionerid;
            if (!$type) {
                continue;
            }
            if ($raterid && ($type->is_vote() || !$ishidden($raterid, $raterid === $questionerid))) {
                $add($raterid, 'activity');
            }
            if ($type->is_vote() && $authorid && $authorid !== $raterid && !$ishidden($authorid, $authorid === $questionerid)) {
                $add($authorid, $type === rating_type::UPVOTE ? 'upvotes' : 'downvotes');
            }
        }
        return $counts;
    }
}
