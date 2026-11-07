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
use DateTimeImmutable;
use DateTimeZone;
use dml_exception;
use mod_moodleoverflow\local\dto\userstats\activity_day_dto;
use mod_moodleoverflow\local\dto\userstats\userstats_dto;
use mod_moodleoverflow\local\dto\userstats\userstats_page_dto;
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
    /** @var int[] The counts of a user without any activity, see count_activity(). */
    private const NO_ACTIVITY = ['activity' => 0, 'upvotes' => 0, 'downvotes' => 0];

    /**
     * Returns the statistics of every user of a moodleoverflow, for this moodleoverflow and for the course.
     * - Activity: every reviewed post and every given rating counts one point.
     * - Up/Downvotes: Received up/downvotes. Anonymity is considered. Received votes on anonym posts do not count.
     *
     * The course numbers add up the moodleoverflows of the course whose statistics the viewer may see. The course
     * reputation is the reputation shared in the course (rating::get_course_reputations()), the same number that is
     * shown next to the posts.
     *
     * @param moodleoverflow $modflow The moodleoverflow the statistics were opened from
     * @param int $viewerid The user who sees the statistics
     * @return userstats_dto[] One per participant, ordered by name
     * @throws dml_exception|moodle_exception
     */
    public static function get_statistics(moodleoverflow $modflow, int $viewerid): array {
        permissions::ensure(permissions::can_view_userstats($modflow, $viewerid), 'cannotviewuserstats');
        return self::build_statistics($modflow, self::get_visible_modflows($modflow, $viewerid), $viewerid);
    }

    /**
     * Returns everything userstats.php shows: the statistics of every user (see get_statistics()) and how many posts
     * were written and how many votes were given per day, in every moodleoverflow of the course whose statistics the
     * viewer may see.
     *
     * @param moodleoverflow $modflow The moodleoverflow the statistics were opened from
     * @param int $viewerid The user who sees the statistics
     * @param DateTimeZone $timezone The timezone in which the days start, the one of the viewer
     * @return userstats_page_dto
     * @throws dml_exception|moodle_exception
     */
    public static function get_page_data(moodleoverflow $modflow, int $viewerid, DateTimeZone $timezone): userstats_page_dto {
        permissions::ensure(permissions::can_view_userstats($modflow, $viewerid), 'cannotviewuserstats');
        $modflows = self::get_visible_modflows($modflow, $viewerid);
        return new userstats_page_dto(
            self::build_statistics($modflow, $modflows, $viewerid),
            self::count_days(array_keys($modflows), $timezone)
        );
    }

    /**
     * Builds the statistics of every participant of a moodleoverflow, see get_statistics().
     * @param moodleoverflow $modflow The moodleoverflow the statistics were opened from
     * @param moodleoverflow[] $modflows The moodleoverflows of the course whose statistics the viewer may see, by id
     * @param int $viewerid The user who sees the statistics
     * @return userstats_dto[] One per participant, ordered by name
     * @throws dml_exception|moodle_exception
     */
    private static function build_statistics(moodleoverflow $modflow, array $modflows, int $viewerid): array {
        $counts = array_map(fn(moodleoverflow $instance) => self::count_activity($instance), $modflows);
        $forumcounts = $counts[$modflow->id] ?? [];
        $coursecounts = self::sum_counts($counts);
        $forumreputations = rating::get_instance_reputations($modflow);
        $coursereputations = rating::get_course_reputations($modflow->course);
        $viewfullnames = permissions::can_view_fullnames($modflow, $viewerid);

        $statistics = [];
        foreach ($modflow->get_participants() as $user) {
            $userid = (int) $user->id;
            $forum = ($forumcounts[$userid] ?? []) + self::NO_ACTIVITY;
            $course = ($coursecounts[$userid] ?? []) + self::NO_ACTIVITY;
            $profileurl = new moodle_url('/user/view.php', ['id' => $userid, 'course' => $modflow->course]);
            $postsurl = new moodle_url('/mod/moodleoverflow/user.php', ['user' => $userid, 'course' => $modflow->course]);
            $statistics[] = new userstats_dto(
                userid: $userid,
                name: fullname($user, $viewfullnames),
                profileurl: $profileurl->out(false),
                postsurl: $postsurl->out(false),
                forumactivity: $forum['activity'],
                forumreputation: $forumreputations[$userid] ?? 0,
                forumupvotes: $forum['upvotes'],
                forumdownvotes: $forum['downvotes'],
                courseactivity: $course['activity'],
                coursereputation: $coursereputations[$userid] ?? 0,
                courseupvotes: $course['upvotes'],
                coursedownvotes: $course['downvotes']
            );
        }
        return $statistics;
    }

    /**
     * Adds up the counts of several moodleoverflows.
     * @param array[] $counts The result of count_activity() for each moodleoverflow
     * @return array[] By user id: ['activity' => int, 'upvotes' => int, 'downvotes' => int], fields without activity
     *                 may be missing
     */
    private static function sum_counts(array $counts): array {
        $sum = [];
        foreach ($counts as $modflowcounts) {
            foreach ($modflowcounts as $userid => $usercounts) {
                foreach ($usercounts as $field => $count) {
                    $sum[$userid][$field] = ($sum[$userid][$field] ?? 0) + $count;
                }
            }
        }
        return $sum;
    }

    /**
     * Returns the moodleoverflows of the course whose statistics the viewer may see, the given one included.
     * @param moodleoverflow $modflow
     * @param int $viewerid
     * @return moodleoverflow[] By id
     * @throws dml_exception|moodle_exception
     */
    private static function get_visible_modflows(moodleoverflow $modflow, int $viewerid): array {
        global $DB;
        $records = $DB->get_records('moodleoverflow', ['course' => $modflow->course]);
        $modflows = array_map(fn($record) => moodleoverflow::from_record($record), $records);
        return array_filter($modflows, fn(moodleoverflow $instance) => permissions::can_view_userstats($instance, $viewerid));
    }

    /**
     * Counts the reviewed posts and the given votes per day in each of some moodleoverflows. Every reviewed post counts,
     * also the ones of hidden authors, because only the amounts are shown.
     *
     * Only days with activity are listed, ordered by date. A moodleoverflow without activity has an empty list.
     * The days are assigned in PHP, because a day is not always 24 hours long (daylight saving time).
     *
     * @param int[] $modflowids
     * @param DateTimeZone $timezone
     * @return activity_day_dto[][] By moodleoverflow id
     * @throws dml_exception
     */
    private static function count_days(array $modflowids, DateTimeZone $timezone): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($modflowids, SQL_PARAMS_NAMED, 'modflow');
        [$votesql, $voteparams] = $DB->get_in_or_equal(
            [rating_type::DOWNVOTE->value, rating_type::UPVOTE->value],
            SQL_PARAMS_NAMED,
            'vote'
        );
        $activities = [
            'posts' => $DB->get_recordset_sql(
                "SELECT p.id, d.moodleoverflow AS modflowid, p.created AS activitytime
                   FROM {moodleoverflow_posts} p
                   JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                  WHERE d.moodleoverflow $insql AND p.reviewed = 1",
                $params
            ),
            'votes' => $DB->get_recordset_sql(
                "SELECT r.id, r.moodleoverflowid AS modflowid, r.firstrated AS activitytime
                   FROM {moodleoverflow_ratings} r
                  WHERE r.moodleoverflowid $insql AND r.rating $votesql AND r.firstrated > 0",
                $params + $voteparams
            ),
        ];

        // One timeline per moodleoverflow: [modflowid => [day => ['posts' => int, 'votes' => int]]]. Every
        // moodleoverflow gets one, so that one without activity is listed with an empty timeline.
        $timelines = array_fill_keys($modflowids, []);
        foreach ($activities as $field => $records) {
            foreach ($records as $record) {
                $day = (new DateTimeImmutable('@' . $record->activitytime))->setTimezone($timezone)->format('Y-m-d');
                $timelines[$record->modflowid][$day] ??= ['posts' => 0, 'votes' => 0];
                $timelines[$record->modflowid][$day][$field]++;
            }
            $records->close();
        }

        $result = [];
        foreach ($timelines as $modflowid => $days) {
            ksort($days);
            $result[$modflowid] = [];
            foreach ($days as $day => $counts) {
                $result[$modflowid][] = new activity_day_dto($day, $counts['posts'], $counts['votes']);
            }
        }
        return $result;
    }

    /**
     * Counts the activity and the received votes of every user in a moodleoverflow.
     * @param moodleoverflow $modflow
     * @return array[] By user id: ['activity' => int, 'upvotes' => int, 'downvotes' => int], fields without activity
     *                 are missing
     * @throws dml_exception|moodle_exception
     */
    private static function count_activity(moodleoverflow $modflow): array {
        global $DB;
        $counts = [];

        // Written posts. A removed author (userid 0, set by the privacy provider) is nobody's activity.
        $sql = "SELECT p.id, p.userid, d.userid AS questionerid
                  FROM {moodleoverflow_posts} p
                  JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                 WHERE d.moodleoverflow = :modflowid AND p.reviewed = 1 AND p.userid <> 0";
        foreach ($DB->get_records_sql($sql, ['modflowid' => $modflow->id]) as $post) {
            $authorid = (int) $post->userid;
            if (!permissions::is_hidden_as_author($modflow, $authorid, $authorid === (int) $post->questionerid)) {
                $counts[$authorid]['activity'] = ($counts[$authorid]['activity'] ?? 0) + 1;
            }
        }

        // Ratings.
        $sql = "SELECT r.id, r.userid AS raterid, r.rating, p.userid AS authorid, d.userid AS questionerid
                  FROM {moodleoverflow_ratings} r
                  JOIN {moodleoverflow_posts} p ON p.id = r.postid
                  JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                 WHERE r.moodleoverflowid = :modflowid";
        foreach ($DB->get_records_sql($sql, ['modflowid' => $modflow->id]) as $rating) {
            $type = rating_type::tryFrom((int) $rating->rating);
            if (!$type) {
                continue;
            }
            $isvote = $type->is_vote();
            $raterid = (int) $rating->raterid;
            $authorid = (int) $rating->authorid;
            $questionerid = (int) $rating->questionerid;

            // Votes are anonymous, so a vote always counts as activity of the rater. A mark shows who set it (helpful
            // marks come from the questioner), so it only counts if the rater would be visible as an author.
            $israteractive = $raterid
                && ($isvote || !permissions::is_hidden_as_author($modflow, $raterid, $raterid === $questionerid));
            if ($israteractive) {
                $counts[$raterid]['activity'] = ($counts[$raterid]['activity'] ?? 0) + 1;
            }

            // A vote is received by the author of the post, unless they voted on their own post or are hidden.
            $isreceived = $isvote && $authorid && $authorid !== $raterid
                && !permissions::is_hidden_as_author($modflow, $authorid, $authorid === $questionerid);
            if ($isreceived) {
                $field = $type === rating_type::UPVOTE ? 'upvotes' : 'downvotes';
                $counts[$authorid][$field] = ($counts[$authorid][$field] ?? 0) + 1;
            }
        }
        return $counts;
    }
}
