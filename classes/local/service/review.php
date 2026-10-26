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

use core\exception\coding_exception;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\permissions;
use core\exception\moodle_exception;

/**
 * Service class for moodleoverflow actions regarding the review of a post.
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review {
    // Write functions that change the review status of a post.

    /**
     * Approves a post to be shown on a discussion and sets it as "reviewed successfully".
     * @param post $post
     * @param int $userid The user that approves the post
     * @return void
     * @throws moodle_exception|coding_exception
     */
    public static function approve(post $post, int $userid): void {
        permissions::ensure(permissions::can_review_post($post, $userid), 'cannotreviewpost');
        $post->mark_reviewed();
        $post->get_discussion()->update_last_activity();
    }

    /**
     * Rejects a post to be shown in a discussion and deletes it.
     * @param post $post
     * @param int $userid The user that rejects the post
     * @return void
     * @throws moodle_exception|coding_exception
     */
    public static function reject(post $post, int $userid): void {
        permissions::ensure(permissions::can_review_post($post, $userid), 'cannotreviewpost');
        $post->get_discussion()->delete_post($post, $userid);
    }

    // Read only functions.

    /**
     * Returns the posts of a discussion the user may review, ordered by id.
     *
     * @param discussion $discussion
     * @param int $userid
     * @return post[]
     */
    public static function get_posts_to_review_in_discussion(discussion $discussion, int $userid): array {
        if (!permissions::can_review_posts($discussion->get_moodleoverflow(), $userid)) {
            return [];
        }
        $posts = array_filter($discussion->get_posts(), fn(post $post) => permissions::can_review_post($post, $userid));
        ksort($posts);
        return $posts;
    }

    /**
     * Returns the next post the user may review in a moodleoverflow: the first one by discussion and id.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return post|null
     */
    public static function get_next_post_to_review(moodleoverflow $modflow, int $userid): ?post {
        return self::get_posts_to_review($modflow, $userid)[0] ?? null;
    }

    /**
     * Returns the posts the user may review, ordered by discussion and id.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return post[]
     */
    public static function get_posts_to_review(moodleoverflow $modflow, int $userid): array {
        global $DB;
        // Shortcut: users who cannot review in this moodleoverflow need no posts loaded.
        if (!permissions::can_review_posts($modflow, $userid)) {
            return [];
        }
        $sql = "SELECT p.*
              FROM {moodleoverflow_posts} p
              JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
             WHERE d.moodleoverflow = :modflowid AND p.reviewed = 0
          ORDER BY p.discussion, p.id";
        $posts = array_map(fn($record) => post::from_record($record), $DB->get_records_sql($sql, ['modflowid' => $modflow->id]));
        return array_values(array_filter($posts, fn(post $post) => permissions::can_review_post($post, $userid)));
    }
}
