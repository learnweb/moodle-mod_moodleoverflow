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

namespace mod_moodleoverflow\local\service;

use coding_exception;
use dml_exception;
use mod_moodleoverflow\event\readtracking_disabled;
use mod_moodleoverflow\event\readtracking_enabled;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use moodle_exception;

/**
 * Service class for moodleoverflow actions regarding the tracking or read posts and discussions.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class readtracking {
    // Functions for use-cases. Represent a specific action.

    /**
     * Start to track a moodleoverflow instance.
     *
     * @param moodleoverflow $modflow The instance that gets tracked
     * @param int $userid The user that starts to track the instance
     *
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function start_tracking(moodleoverflow $modflow, int $userid): void {
        global $DB;
        permissions::ensure(permissions::can_change_tracking($modflow, $userid), 'cannotchangetracking');

        $DB->delete_records('moodleoverflow_tracking', ['userid' => $userid, 'moodleoverflowid' => $modflow->id]);
        self::trigger_tracking_event(readtracking_enabled::class, $modflow, $userid);
    }

    /**
     * Stop to track a moodleoverflow instance.
     *
     * @param moodleoverflow $modflow The moodleoverflow
     * @param int $userid The user that stops to track the instance
     *
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function stop_tracking(moodleoverflow $modflow, int $userid): void {
        global $DB;
        permissions::ensure(permissions::can_change_tracking($modflow, $userid), 'cannotchangetracking');

        // Check if the user already stopped to track the moodleoverflow.
        $params = ['userid' => $userid, 'moodleoverflowid' => $modflow->id];
        if (!$DB->record_exists('moodleoverflow_tracking', $params)) {
            $DB->insert_record('moodleoverflow_tracking', $params);
        }

        // Delete all connected read records.
        self::delete_read_records(userid: $userid, modflowid: $modflow->id);
        self::trigger_tracking_event(readtracking_disabled::class, $modflow, $userid);
    }

    /**
     * Marks a specific moodleoverflow instance as read by a specific user.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return void
     */
    public static function mark_moodleoverflow_read(moodleoverflow $modflow, int $userid): void {
        permissions::ensure(permissions::can_view_moodleoverflow($modflow, $userid), 'noviewdiscussionspermission');
        self::mark_posts_read($modflow, $userid, 'd.moodleoverflow = :modflowid', ['modflowid' => $modflow->id]);
    }

    /**
     * Marks a specific discussion as read by a specific user.
     *
     * @param discussion $discussion The discussion object
     * @param int $userid
     * @return void
     * @throws moodle_exception
     */
    public static function mark_discussion_read(discussion $discussion, int $userid): void {
        permissions::ensure(permissions::can_view_discussion($discussion, $userid), 'noviewdiscussionspermission');

        self::mark_posts_read(
            $discussion->get_moodleoverflow(),
            $userid,
            'd.id = :discussionid',
            ['discussionid' => $discussion->get_id()]
        );
    }

    /**
     * Function to mark a single post as read.
     * @param post $post The post that gets marked as read
     * @param int $userid The user for whom the post is "read"
     * @return void
     * @throws moodle_exception
     */
    public static function mark_post_read(post $post, int $userid): void {
        if (self::is_tracked($post->get_moodleoverflow(), $userid)) {
            self::add_read_record($post, $userid);
        }
    }

    /**
     * Deletes read records. No permission checks: only call it as a consequence of an action that was already
     * authorised (deleting a post, discussion or instance, stopping to track). At least one parameter must be given.
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

    // Check functions.

    /**
     * Tells whether a specific moodleoverflow is tracked by the user.
     *
     * @param moodleoverflow $moodleoverflow
     * @param int $userid
     * @return bool
     * @throws dml_exception
     * @throws coding_exception|moodle_exception
     */
    public static function is_tracked(moodleoverflow $moodleoverflow, int $userid): bool {
        global $DB;

        // The moodleoverflow should be generally trackable.
        if (!permissions::can_track($moodleoverflow, $userid)) {
            return false;
        }

        // Check the settings of the moodleoverflow instance.
        $trackingtype = $moodleoverflow->get_tracking_type();
        $params = ['userid' => $userid, 'moodleoverflowid' => $moodleoverflow->id];
        return $trackingtype->is_forced()
            || ($trackingtype->users_can_choose() && !$DB->record_exists('moodleoverflow_tracking', $params));
    }

    /**
     * Checks whether a specific post has been read by a user.
     * Posts older than the configured cutoff date are always considered read.
     *
     * @param post $post
     * @param int $userid
     * @return bool True if read (or old), false if unread.
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function is_post_read(post $post, int $userid): bool {
        global $DB;
        if (!self::is_tracked($post->get_moodleoverflow(), $userid)) {
            return true;
        }

        $cutoffdate = time() - moodleoverflow::get_old_post_age();

        $sql = "SELECT p.id
                  FROM {moodleoverflow_posts} p
             LEFT JOIN {moodleoverflow_read} r ON (r.postid = p.id AND r.userid = :userid)
                 WHERE p.id = :postid
                   AND p.modified >= :cutoffdate
                   AND r.id IS NULL";

        return !$DB->record_exists_sql($sql, ['userid' => $userid, 'postid' => $post->get_id(), 'cutoffdate' => $cutoffdate]);
    }

    /**
     * Get number of unread posts in a moodleoverflow instance.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return int
     */
    public static function count_unread_posts_moodleoverflow(moodleoverflow $modflow, int $userid): int {
        if (!self::is_tracked($modflow, $userid)) {
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
        if (!self::is_tracked($discussion->get_moodleoverflow(), $userid)) {
            return 0;
        }
        return count(self::get_unread_posts($userid, 'd.id = :discussionid', ['discussionid' => $discussion->get_id()]));
    }

    /**
     * Returns the id of the first unread post in a discussion. Useful to point to the post in a discussion that is unread.
     * @param discussion $discussion
     * @param int $userid
     * @return int
     * @throws moodle_exception
     */
    public static function get_first_unread_post_id(discussion $discussion, int $userid): int {
        $posts = self::get_unread_posts($userid, 'd.id = :discussionid', ['discussionid' => $discussion->get_id()]);
        usort($posts, fn($a, $b) => $a->created <=> $b->created);
        return $posts ? $posts[0]->get_id() : -1;
    }

    // Cron functions.

    /**
     * Deletes all read records that are related to posts that are older than the cutoffdate.
     * This function is only called by the plugins' cronjob.
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
        if (!self::is_tracked($modflow, $userid)) {
            return;
        }
        foreach (self::get_unread_posts($userid, $where, $params) as $post) {
            self::add_read_record($post, $userid);
        }
    }

    /**
     * Mark a post as read by a user. Core function of the readtracking system. Needs to be private as it needs permission checks
     * beforehand depending on the case.
     *
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    private static function add_read_record(post $post, int $userid): bool {
        global $DB;

        // Get the current time and the cutoffdate.
        $now = time();
        $cutoffdate = $now - moodleoverflow::get_old_post_age();

        // Check for read records for this user an this post.
        $oldrecord = $DB->get_record('moodleoverflow_read', ['postid' => $post->get_id(), 'userid' => $userid]);
        if (!$oldrecord) {
            // If there are no old records, create a new one.
            $sql = "INSERT INTO {moodleoverflow_read} (userid, postid, discussionid, moodleoverflowid, firstread, lastread)
                 SELECT ?, p.id, p.discussion, d.moodleoverflow, ?, ?
                   FROM {moodleoverflow_posts} p
                        JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                  WHERE p.id = ? AND p.modified >= ?";

            return $DB->execute($sql, [$userid, $now, $now, $post->get_id(), $cutoffdate]);
        }

        // Else update the existing one.
        $sql = "UPDATE {moodleoverflow_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";

        return $DB->execute($sql, [$now, $userid, $post->get_id()]);
    }

    /**
     * Fires readtracking_enabled / readtracking_disabled events.
     * @param string $eventclass
     * @param moodleoverflow $modflow
     * @param int $userid
     */
    private static function trigger_tracking_event(string $eventclass, moodleoverflow $modflow, int $userid): void {
        $eventclass::create([
            'context' => $modflow->get_context(),
            'relateduserid' => $userid,
            'other' => ['moodleoverflowid' => $modflow->id],
        ])->trigger();
    }
}
