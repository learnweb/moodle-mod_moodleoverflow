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

use core\exception\coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use Exception;
use mod_moodleoverflow\event\rating_created;
use mod_moodleoverflow\event\rating_deleted;
use mod_moodleoverflow\event\rating_updated;
use mod_moodleoverflow\local\enum\rating_preference;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;

/**
 * Service class for the rating domain. Includes voting, marking posts helpful/solved, reputation and grade.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating {
    // Functions for use-cases. Represent a specific action.

    /**
     * Function to vote a post up or down.
     * @param post $post
     * @param int $userid
     * @param rating_type $vote
     * @return void
     * @throws \dml_exception|moodle_exception
     */
    public static function vote(post $post, int $userid, rating_type $vote): void {
        global $DB;
        if (!$vote->is_vote()) {
            throw new coding_exception('vote() only accepts votes, use mark() for marks.');
        }
        permissions::ensure(permissions::can_vote($post, $userid), 'noratemoodleoverflow');

        $oldvote = self::get_vote_record($post, $userid);
        if ($oldvote && $oldvote->rating == $vote->value) {
            return;
        }
        if ($oldvote && !$post->get_moodleoverflow()->allows_rating_change()) {
            throw new moodle_exception('noratingchangeallowed', 'moodleoverflow');
        }

        // Update the vote or insert a new one, then update the grades.
        $transaction = $DB->start_delegated_transaction();
        try {
            $oldvote ? self::update_rating($oldvote, $vote, $post) : self::insert_rating($post, $userid, $vote);
            self::update_grades($post->get_moodleoverflow(), [$post->get_userid(), $userid]);
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
        $transaction->allow_commit();
    }

    /**
     * Remove a vote from a post.
     * @param post $post
     * @param int $userid
     * @return void
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function remove_vote(post $post, int $userid): void {
        global $DB;
        permissions::ensure(permissions::can_vote($post, $userid), 'noratemoodleoverflow');

        $vote = self::get_vote_record($post, $userid);
        if (!$vote) {
            return;
        }
        if (!$post->get_moodleoverflow()->allows_rating_change()) {
            throw new moodle_exception('noratingchangeallowed', 'moodleoverflow');
        }

        // Delete the vote and update the user grades.
        $transaction = $DB->start_delegated_transaction();
        try {
            self::delete_rating($vote, $post, $userid);
            self::update_grades($post->get_moodleoverflow(), [$post->get_userid(), $userid]);
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
        $transaction->allow_commit();
    }

    /**
     * Marks a post as solved or helpful. Without multiple marks, the mark moves from the other posts of the discussion.
     * @param post $post
     * @param int $userid
     * @param rating_type $mark SOLVED or HELPFUL
     * @return void
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function mark(post $post, int $userid, rating_type $mark): void {
        global $DB;
        self::ensure_can_mark($post, $userid, $mark);

        // Marking the same post changes nothing.
        $params = ['discussionid' => $post->get_discussionid(), 'rating' => $mark->value];
        $oldmarks = $DB->get_records('moodleoverflow_ratings', $params);
        if (in_array($post->get_id(), array_column($oldmarks, 'postid'))) {
            return;
        }

        // Add the mark as a new rating. If multiple marks of a kind are not allowed delete all marks first and then mark the post.
        $transaction = $DB->start_delegated_transaction();
        try {
            $authors = [$post->get_userid()];
            if (!$post->get_moodleoverflow()->allows_multiple_marks()) {
                foreach ($oldmarks as $oldmark) {
                    $oldpost = post::from_id($oldmark->postid);
                    self::delete_rating($oldmark, $oldpost, $userid);
                    $authors[] = $oldpost->get_userid();
                }
            }
            self::insert_rating($post, $userid, $mark);
            self::update_grades($post->get_moodleoverflow(), $authors);
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
        $transaction->allow_commit();
    }

    /**
     * Removes a mark (solved or helpful) from a post, no matter who set it.
     * @param post $post
     * @param int $userid
     * @param rating_type $mark SOLVED or HELPFUL
     * @return void
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function unmark(post $post, int $userid, rating_type $mark): void {
        global $DB;
        self::ensure_can_mark($post, $userid, $mark);

        // Several records are possible: earlier versions could mark a post twice.
        $marks = $DB->get_records('moodleoverflow_ratings', ['postid' => $post->get_id(), 'rating' => $mark->value]);
        if (!$marks) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($marks as $record) {
                self::delete_rating($record, $post, $userid);
            }
            self::update_grades($post->get_moodleoverflow(), [$post->get_userid()]);
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
        $transaction->allow_commit();
    }

    // Read functions.

    /**
     * Returns the users reputation.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return int
     * @throws dml_exception|coding_exception
     */
    public static function get_reputation(moodleoverflow $modflow, int $userid): int {
        return $modflow->is_reputation_course_wide()
            ? self::get_course_reputation($modflow->course, $userid)
            : self::get_instance_reputation($modflow, $userid);
    }

    /**
     * Returns the user's reputation summed over the moodleoverflows of a course that share their reputation.
     * @param int $courseid
     * @param int $userid
     * @return int
     * @throws coding_exception|dml_exception
     */
    public static function get_course_reputation(int $courseid, int $userid): int {
        global $DB;
        // Only instances that share their reputation with the course count.
        return array_reduce(
            $DB->get_records('moodleoverflow', ['course' => $courseid, 'coursewidereputation' => 1]),
            fn(int $sum, $record) => $sum + self::get_instance_reputation(moodleoverflow::from_record($record), $userid),
            0
        );
    }

    /**
     * Returns the users reputation of a single moodleoverflow instance.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return int
     * @throws dml_exception
     */
    public static function get_instance_reputation(moodleoverflow $modflow, int $userid): int {
        global $DB;
        // Ratings the user received. Own ratings on own posts never count.
        $sql = "SELECT r.id, r.rating, d.userid AS questionerid
              FROM {moodleoverflow_ratings} r
              JOIN {moodleoverflow_posts} p ON p.id = r.postid
              JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
             WHERE r.moodleoverflowid = :modflowid AND p.userid = :author AND r.userid <> :rater";
        $received = $DB->get_records_sql($sql, ['modflowid' => $modflow->id, 'author' => $userid, 'rater' => $userid]);

        $reputation = 0;
        foreach ($received as $record) {
            if (!permissions::is_hidden_as_author($modflow, $userid, $userid == $record->questionerid)) {
                $reputation += rating_type::from($record->rating)->get_author_reputation();
            }
        }

        // Ratings the user gave.
        $given = $DB->get_records('moodleoverflow_ratings', ['moodleoverflowid' => $modflow->id, 'userid' => $userid]);
        foreach ($given as $record) {
            $reputation += rating_type::from($record->rating)->get_rater_reputation();
        }
        return $modflow->allows_negative_reputation() ? $reputation : max(0, $reputation);
    }

    /**
     * Whether the user can still use the vote buttons of a post: they may vote and have not voted yet, or may change
     * their vote (allowratingchange).
     * @param post $post
     * @param int $userid
     * @param rating_type|null $vote The current vote of the user on the post (get_user_vote() or get_user_votes()).
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    public static function can_change_vote(post $post, int $userid, ?rating_type $vote): bool {
        return permissions::can_vote($post, $userid) && ($post->get_moodleoverflow()->allows_rating_change() || $vote === null);
    }

    /**
     * Returns the vote of a user on a post.
     * @param post $post
     * @param int $userid
     * @return rating_type|null UPVOTE or DOWNVOTE, null if the user has not voted.
     * @throws dml_exception|moodle_exception
     */
    public static function get_user_vote(post $post, int $userid): ?rating_type {
        $record = self::get_vote_record($post, $userid);
        return $record ? rating_type::from($record->rating) : null;
    }

    /**
     * Returns the votes of a user on the posts of a discussion, in one query.
     * @param discussion $discussion
     * @param int $userid
     * @return rating_type[] UPVOTE or DOWNVOTE, indexed by post id; posts without a vote of the user are missing.
     * @throws dml_exception|moodle_exception
     */
    public static function get_user_votes(discussion $discussion, int $userid): array {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal([rating_type::DOWNVOTE->value, rating_type::UPVOTE->value], SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            'moodleoverflow_ratings',
            "userid = :userid AND discussionid = :discussionid AND rating $insql",
            ['userid' => $userid, 'discussionid' => $discussion->get_id()] + $inparams,
            'id',
            'id, postid, rating'
        );
        $votes = [];
        foreach ($records as $record) {
            $votes[$record->postid] ??= rating_type::from($record->rating);
        }
        return $votes;
    }

    /**
     * Counts the ratings of every post in a discussion. Counts for every post their upvotes, downvotes, solved and helpful marks.
     * @param discussion $discussion
     * @return object[] Indexed by post id: postid, upvotes, downvotes, issolved, ishelpful. Example: $ratings[postid]->upvotes.
     * @throws dml_exception|moodle_exception
     */
    public static function get_post_ratings(discussion $discussion): array {
        global $DB;
        $sql = 'SELECT p.id AS postid,
                       SUM(CASE WHEN r.rating = :up THEN 1 ELSE 0 END) AS upvotes,
                       SUM(CASE WHEN r.rating = :down THEN 1 ELSE 0 END) AS downvotes,
                       SUM(CASE WHEN r.rating = :solved THEN 1 ELSE 0 END) AS issolved,
                       SUM(CASE WHEN r.rating = :helpful THEN 1 ELSE 0 END) AS ishelpful
                  FROM {moodleoverflow_posts} p
             LEFT JOIN {moodleoverflow_ratings} r ON r.postid = p.id
                 WHERE p.discussion = :discussionid
              GROUP BY p.id';
        $records = $DB->get_records_sql($sql, [
            'up' => rating_type::UPVOTE->value,
            'down' => rating_type::DOWNVOTE->value,
            'solved' => rating_type::SOLVED->value,
            'helpful' => rating_type::HELPFUL->value,
            'discussionid' => $discussion->get_id(),
        ]);
        return array_map(fn($record) => (object) array_map('intval', (array) $record), $records);
    }

    /**
     * Returns the posts of a discussion that have a mark, in the order they were marked.
     * @param discussion $discussion
     * @param rating_type $mark SOLVED or HELPFUL
     * @return int[] Post ids
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function get_marked_post_ids(discussion $discussion, rating_type $mark): array {
        global $DB;
        if (!$mark->is_mark()) {
            throw new coding_exception('get_marked_post_ids() only accepts marks.');
        }
        $params = ['discussionid' => $discussion->get_id(), 'rating' => $mark->value];
        $records = $DB->get_records('moodleoverflow_ratings', $params, 'id', 'id, postid');
        return array_values(array_unique(array_map(fn($record) => (int) $record->postid, $records)));
    }

    /**
     * Sorts the answers of a discussion: marked answers first, then by votes, then by time of modification.
     * @param object[] $answers Objects with id, modified, votesdifference, markedsolution and markedhelpful
     * @param rating_preference $preference Which mark comes first when an answer has only one of them
     * @return object[] The sorted answers, indexed by id
     */
    public static function sort_answers(array $answers, rating_preference $preference): array {
        $preferred = $preference === rating_preference::TEACHER ? 'markedsolution' : 'markedhelpful';
        // Group 0: both marks, 1: only the preferred mark, 2: only the other mark, 3: no mark.
        $group = fn($answer) => match (true) {
            $answer->markedsolution > 0 && $answer->markedhelpful > 0 => 0,
            $answer->$preferred > 0 => 1,
            $answer->markedsolution > 0 || $answer->markedhelpful > 0 => 2,
            default => 3,
        };
        usort($answers, fn($a, $b) => $group($a) <=> $group($b)
            ?: $b->votesdifference <=> $a->votesdifference
            ?: $a->modified <=> $b->modified);
        return array_column($answers, null, 'id');
    }

    // Grade functions. No permission checks: only called after an authorized action.

    /**
     * Recomputes the grades of users from their reputation in the instance and sends them to the gradebook.
     * @param moodleoverflow $modflow
     * @param int[] $userids Users whose reputation may have changed; 0 (author removed by the privacy provider) is skipped.
     * @return void
     * @throws dml_exception
     */
    public static function update_grades(moodleoverflow $modflow, array $userids): void {
        global $CFG, $DB;
        if (!$modflow->is_graded()) {
            return;
        }
        require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');
        foreach (array_unique(array_filter($userids)) as $userid) {
            $reputation = self::get_instance_reputation($modflow, $userid);
            $grade = min($reputation / $modflow->gradescalefactor, $modflow->grademaxgrade);

            $params = ['userid' => $userid, 'moodleoverflowid' => $modflow->id];
            if ($id = $DB->get_field('moodleoverflow_grades', 'id', $params)) {
                $DB->set_field('moodleoverflow_grades', 'grade', $grade, ['id' => $id]);
            } else {
                $DB->insert_record('moodleoverflow_grades', (object) ($params + ['grade' => $grade]));
            }
            moodleoverflow_update_grades($modflow->get_db_object(), $userid);
        }
    }

    /**
     * Recomputes the grades of every user who wrote or rated something in the instance.
     * @param moodleoverflow $modflow
     * @return void
     * @throws dml_exception
     */
    public static function update_all_grades(moodleoverflow $modflow): void {
        global $DB;
        $sql = 'SELECT p.userid
              FROM {moodleoverflow_posts} p
              JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
             WHERE d.moodleoverflow = ?';
        $authors = $DB->get_fieldset_sql($sql, [$modflow->id]);
        $raters = $DB->get_fieldset_select('moodleoverflow_ratings', 'userid', 'moodleoverflowid = ?', [$modflow->id]);
        self::update_grades($modflow, array_merge($authors, $raters));
    }

    /**
     * Recomputes the grades of every graded instance of the site. Is called from the admin settings.
     * @return void
     * @throws dml_exception|coding_exception
     */
    public static function update_all_instances_grades(): void {
        global $DB;
        foreach ($DB->get_records_select('moodleoverflow', 'grademaxgrade > 0 AND gradescalefactor > 0') as $record) {
            self::update_all_grades(moodleoverflow::from_record($record));
        }
    }

    // Private helper functions.

    /**
     * Returns the vote of a user on a post. A user has at most one vote per post, either up or down.
     *
     * @param post $post
     * @param int $userid
     * @return object|false The rating record of the vote, false if the user has not voted.
     * @throws dml_exception|coding_exception|moodle_exception
     */
    private static function get_vote_record(post $post, int $userid): object|false {
        global $DB;
        $votes = [rating_type::DOWNVOTE->value, rating_type::UPVOTE->value];
        [$insql, $inparams] = $DB->get_in_or_equal($votes, SQL_PARAMS_NAMED, 'rating');
        return $DB->get_record_select(
            'moodleoverflow_ratings',
            "userid = :userid AND postid = :postid AND rating $insql",
            ['userid' => $userid, 'postid' => $post->get_id()] + $inparams,
            '*',
            IGNORE_MULTIPLE
        );
    }

    /**
     * Ensures that a user may set or remove a mark on a post.
     * @param post $post
     * @param int $userid
     * @param rating_type $mark
     * @return void
     * @throws coding_exception|moodle_exception
     */
    private static function ensure_can_mark(post $post, int $userid, rating_type $mark): void {
        match ($mark) {
            rating_type::SOLVED => permissions::ensure(permissions::can_mark_solved($post, $userid), 'notteacher'),
            rating_type::HELPFUL => permissions::ensure(permissions::can_mark_helpful($post, $userid), 'notstartuser'),
            default => throw new coding_exception('Only marks can be set or removed, use vote() for votes.'),
        };
    }

    // CRUD Operations persistence layer.

    /**
     * Inserts a rating of a post and triggers the event rating_created.
     * @param post $post The rated post
     * @param int $userid The user who rates
     * @param rating_type $rating
     * @return int The id of the new rating record
     * @throws dml_exception|coding_exception|moodle_exception
     */
    private static function insert_rating(post $post, int $userid, rating_type $rating): int {
        global $DB;
        $time = time();
        $record = (object) [
            'userid' => $userid,
            'postid' => $post->get_id(),
            'discussionid' => $post->get_discussionid(),
            'moodleoverflowid' => $post->get_moodleoverflow()->id,
            'rating' => $rating->value,
            'firstrated' => $time,
            'lastchanged' => $time,
        ];
        $record->id = $DB->insert_record('moodleoverflow_ratings', $record);

        $event = rating_created::create(['objectid' => $record->id, 'context' => $post->get_context(), 'userid' => $userid]);
        $event->add_record_snapshot('moodleoverflow_ratings', $record);
        $event->trigger();
        return $record->id;
    }

    /**
     * Changes the value of an existing rating and triggers the event rating_updated. The record is updated in place.
     * @param object $record The rating record to change
     * @param rating_type $rating The new value
     * @param post $post The rated post
     * @return void
     * @throws dml_exception|coding_exception|moodle_exception
     */
    private static function update_rating(object $record, rating_type $rating, post $post): void {
        global $DB;
        $record->rating = $rating->value;
        $record->lastchanged = time();
        $DB->update_record('moodleoverflow_ratings', $record);

        $event = rating_updated::create([
            'objectid' => $record->id,
            'context' => $post->get_context(),
            'userid' => $record->userid,
        ]);
        $event->add_record_snapshot('moodleoverflow_ratings', $record);
        $event->trigger();
    }

    /**
     * Deletes a rating from the db.
     * @param object $record The rating record.
     * @param post $post The associated post.
     * @param int $userid The user that deletes the rating.
     * @return void
     * @throws coding_exception|dml_exception|moodle_exception
     */
    private static function delete_rating(object $record, post $post, int $userid): void {
        global $DB;
        $DB->delete_records('moodleoverflow_ratings', ['id' => $record->id]);
        $event = rating_deleted::create([
            'objectid' => $record->id,
            'context' => $post->get_context(),
            'userid' => $userid,
        ]);
        $event->add_record_snapshot('moodleoverflow_ratings', $record);
        $event->trigger();
    }
}
