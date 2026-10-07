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

use coding_exception;
use dml_exception;
use mod_moodleoverflow\local\enum\tracking_type;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use moodle_exception;

/**
 * Static methods for managing the tracking of read posts and discussions.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class readtracking {
    /**
     * Tells whether a specific moodleoverflow is tracked by the user.
     *
     * @param moodleoverflow $moodleoverflow
     * @param int $userid
     * @return bool
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function moodleoverflow_is_tracked(moodleoverflow $moodleoverflow, int $userid): bool {
        global $DB;

        // The moodleoverflow should be generally trackable.
        if (!permissions::can_track($moodleoverflow, $userid)) {
            return false;
        }

        // Check the settings of the moodleoverflow instance.
        $type = $moodleoverflow->get_tracking_type();
        $params = ['userid' => $userid, 'moodleoverflowid' => $moodleoverflow->id];
        return $type === tracking_type::FORCED
            || ($type === tracking_type::OPTIONAL && !$DB->record_exists('moodleoverflow_tracking', $params));
    }

    /**
     * Marks a specific moodleoverflow instance as read by a specific user.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return void
     */
    public static function mark_moodleoverflow_read(moodleoverflow $modflow, int $userid): void {
        self::mark_posts_read($modflow, $userid, 'd.moodleoverflow = :modflowid', ['modflowid' => $modflow->id]);
    }

    /**
     * Marks a specific discussion as read by a specific user.
     *
     * @param discussion $discussion The discussion object
     * @param int $userid
     * @return void
     */
    public static function mark_discussion_read(discussion $discussion, int $userid): void {
        self::mark_posts_read($discussion->get_moodleoverflow(), $userid, 'd.id = :did', ['did' => $discussion->get_id()]);
    }

    /**
     * Mark a post as read by a user.
     *
     * @param int $userid
     * @param int $postid
     *
     * @return bool
     */
    public static function add_read_record(int $userid, int $postid): bool {
        global $DB;

        // Get the current time and the cutoffdate.
        $now = time();
        $cutoffdate = $now - moodleoverflow::get_old_post_age();

        // Check for read records for this user an this post.
        $oldrecord = $DB->get_record('moodleoverflow_read', ['postid' => $postid, 'userid' => $userid]);
        if (!$oldrecord) {
            // If there are no old records, create a new one.
            $sql = "INSERT INTO {moodleoverflow_read} (userid, postid, discussionid, moodleoverflowid, firstread, lastread)
                 SELECT ?, p.id, p.discussion, d.moodleoverflow, ?, ?
                   FROM {moodleoverflow_posts} p
                        JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                  WHERE p.id = ? AND p.modified >= ?";

            return $DB->execute($sql, [$userid, $now, $now, $postid, $cutoffdate]);
        }

        // Else update the existing one.
        $sql = "UPDATE {moodleoverflow_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";

        return $DB->execute($sql, [$now, $userid, $postid]);
    }

    /**
     * Deletes read record for the specified index. At least one parameter must be specified.
     * @param int|null $userid
     * @param int|null $postid
     * @param int|null $discussid
     * @param int|null $modflowid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function delete_read_records(
        ?int $userid = null,
        ?int $postid = null,
        ?int $discussid = null,
        ?int $modflowid = null
    ): void {
        global $DB;

        $conditions = array_filter(
            ['userid' => $userid, 'postid' => $postid, 'discussionid' => $discussid, 'moodleoverflowid' => $modflowid],
            fn($value) => $value !== null
        );
        if (!$conditions) {
            throw new coding_exception('delete_read_records() needs at least one condition');
        }
        $DB->delete_records('moodleoverflow_read', $conditions);
    }

    /**
     * Deletes all read records that are related to posts that are older than the cutoffdate.
     * This function is only called by the modules cronjob.
     * @return void
     * @throws dml_exception
     */
    public static function clean_read_records(): void {
        global $DB;

        // Stop if there cannot be old posts.
        $maxage = moodleoverflow::get_old_post_age();
        if (!$maxage) {
            return;
        }

        // Delete the read records of posts that are older than allowed.
        $DB->delete_records_select(
            'moodleoverflow_read',
            'postid IN (SELECT p.id FROM {moodleoverflow_posts} p WHERE p.modified < :cutoff)',
            ['cutoff' => time() - $maxage]
        );
    }

    /**
     * Stop to track a moodleoverflow instance.
     *
     * @param moodleoverflow $modflow The moodleoverflow
     * @param int $userid           The user ID
     *
     * @return bool Whether the deletion was successful
     */
    public static function stop_tracking(moodleoverflow $modflow, int $userid) {
        global $DB;
        // Check if the user already stopped to track the moodleoverflow.
        $params = ['userid' => $userid, 'moodleoverflowid' => $modflow->id];

        // Stop tracking the moodleoverflow if not already stopped.
        if (!$DB->record_exists('moodleoverflow_tracking', $params)) {
            // Insert into the database.
            $DB->insert_record('moodleoverflow_tracking', $params);
        }
        // Delete all connected read records and return whether the deletion was successful.
        self::delete_read_records(userid: $userid, modflowid: $modflow->id);
        return true;
    }

    /**
     * Start to track a moodleoverflow instance.
     *
     * @param moodleoverflow $modflow
     * @param int $userid The user ID
     *
     * @return bool Whether the deletion was successful
     * @throws dml_exception
     */
    public static function start_tracking(moodleoverflow $modflow, int $userid) {
        global $DB;
        // Delete the tracking setting of this user for this moodleoverflow.
        return $DB->delete_records('moodleoverflow_tracking', ['userid' => $userid, 'moodleoverflowid' => $modflow->id]);
    }

    /**
     * Get number of unread posts in a moodleoverflow instance.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return int
     */
    public static function count_unread_posts_moodleoverflow(moodleoverflow $modflow, int $userid): int {
        if (!self::moodleoverflow_is_tracked($modflow, $userid)) {
            return 0;
        }
        return count(self::get_unread_posts($userid, 'd.moodleoverflow = :modflowid', ['modflowid' => $modflow->id]));
    }

    /**
     * Get number of unread posts in a discussion
     * @param discussion $discussion
     * @param int $userid
     * @return int
     */
    public static function count_unread_posts_discussion(discussion $discussion, int $userid): int {
        if (!self::moodleoverflow_is_tracked($discussion->get_moodleoverflow(), $userid)) {
            return 0;
        }
        return count(self::get_unread_posts($userid, 'd.id = :discussionid', ['discussionid' => $discussion->get_id()]));
    }

    /**
     * Checks whether a specific post has been read by a user.
     * Posts older than the configured cutoff date are always considered read.
     *
     * @param moodleoverflow $moodleoverflow
     * @param int $postid
     * @param int $userid
     * @return bool True if read (or old), false if unread.
     */
    public static function is_post_read(moodleoverflow $moodleoverflow, int $postid, int $userid): bool {
        global $DB;
        if (!self::moodleoverflow_is_tracked($moodleoverflow, $userid)) {
            return true;
        }

        $cutoffdate = time() - moodleoverflow::get_old_post_age();

        $sql = "SELECT p.id
                  FROM {moodleoverflow_posts} p
             LEFT JOIN {moodleoverflow_read} r ON (r.postid = p.id AND r.userid = :userid)
                 WHERE p.id = :postid
                   AND p.modified >= :cutoffdate
                   AND r.id IS NULL";

        return !$DB->record_exists_sql($sql, ['userid' => $userid, 'postid' => $postid, 'cutoffdate' => $cutoffdate]);
    }

    /**
     * Returns the id of the first unread post in a discussion. Useful to point to the post in a discussion that is unread.
     * @param int $discussionid
     * @param int $userid
     * @return int
     * @throws coding_exception|dml_exception
     */
    public static function get_first_unread_post_id(int $discussionid, int $userid): int {
        $posts = self::get_unread_posts($userid, 'd.id = :discussionid', ['discussionid' => $discussionid]);
        usort($posts, fn($a, $b) => $a->created <=> $b->created);
        return $posts ? $posts[0]->get_id() : -1;
    }

    // Private Helper functions.

    /**
     * Returns the unread posts a user can see, in a moodleoverflow or a discussion.
     * @param int $userid
     * @param string $where condition on p (posts) or d (discussions)
     * @param array $params parameters of the condition
     * @return post[]
     */
    private static function get_unread_posts(int $userid, string $where, array $params): array {
        global $DB;
        $sql = "SELECT p.*
              FROM {moodleoverflow_posts} p
              JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
         LEFT JOIN {moodleoverflow_read} r ON r.postid = p.id AND r.userid = :userid
             WHERE $where AND p.modified >= :cutoff AND r.id IS NULL";
        $params += ['userid' => $userid, 'cutoff' => time() - moodleoverflow::get_old_post_age()];
        $posts = array_map(fn($record) => post::from_record($record), $DB->get_records_sql($sql, $params));
        return array_filter($posts, fn($post) => permissions::can_view_post($post, $userid));
    }

    /**
     * Marks the unread posts a user can see as read, in a moodleoverflow or a discussion.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @param string $where
     * @param array $params
     * @return void
     * @throws coding_exception|dml_exception|moodle_exception
     */
    private static function mark_posts_read(moodleoverflow $modflow, int $userid, string $where, array $params): void {
        if (!self::moodleoverflow_is_tracked($modflow, $userid)) {
            return;
        }
        foreach (self::get_unread_posts($userid, $where, $params) as $post) {
            self::add_read_record($userid, $post->get_id());
        }
    }
}
