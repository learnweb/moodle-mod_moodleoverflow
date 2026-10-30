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
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\permissions;
use Throwable;

/**
 * Core service for all use cases that write posts: create a discussion, reply, edit and delete.
 *
 * @package     mod_moodleoverflow
 * @copyright   2026 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class posting {
    /**
     * Create a new discussion with the first post.
     * @param moodleoverflow $modflow The moodleoverflow where the discussion is added
     * @param int $userid The user that creates the discussion
     * @param post_content $content The content from the post form
     * @return discussion
     *
     * @throws moodle_exception|dml_exception|coding_exception
     */
    public static function create_discussion(moodleoverflow $modflow, int $userid, post_content $content): discussion {
        global $DB;
        if (($subject = trim($content->subject ?? '')) === '') {
            throw new coding_exception('An empty subject is not allowed');
        }

        // Check if the user is allowed to post.
        permissions::ensure(permissions::can_start_discussion($modflow, $userid), 'nopostmoodleoverflow');

        // Build the discussion record.
        $time = time();
        $discussion = discussion::construct_without_id($modflow->course, $modflow->id, $subject, 0, $userid, $time, $time, $userid);

        // Start the transaction to build the object and the first post and save them into the DB.
        $transaction = $DB->start_delegated_transaction();
        try {
            $reviewed = !$modflow->requires_review(true) || permissions::can_review_posts($modflow, $userid);
            $discussion->add($content, $reviewed);
            subscription::follow_on_post($discussion, $userid);
            readtracking::mark_post_read($discussion->get_first_post(), $userid);
            if ($reviewed) {
                mail::queue_notification($discussion->get_first_post());
            } else {
                // The subscribers are notified when the question is approved.
                mail::queue_review_request($discussion->get_first_post());
            }
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
        return $discussion;
    }

    /**
     * Reply to an existing post.
     * @param post $parent The post that gets replied to
     * @param int $userid The user that replies.
     * @param post_content $content The content from the post form.
     * @return post
     * @throws moodle_exception|Throwable
     */
    public static function reply(post $parent, int $userid, post_content $content): post {
        global $DB;
        permissions::ensure(permissions::can_reply($parent, $userid), 'cannotreply');

        $discussion = $parent->get_discussion();
        $modflow = $discussion->get_moodleoverflow();
        $reviewed = !$modflow->requires_review(false) || permissions::can_review_posts($modflow, $userid);

        $transaction = $DB->start_delegated_transaction();
        try {
            $post = $discussion->add_reply($parent, $userid, $content, $reviewed);
            subscription::follow_on_post($discussion, $userid);
            readtracking::mark_post_read($post, $userid);
            if ($reviewed) {
                mail::queue_notification($post);
            } else {
                // The subscribers are notified when the reply is approved.
                mail::queue_review_request($post);
            }
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }

        return $post;
    }

    /**
     * Edits a post.
     *
     * @param post $post The post to edit.
     * @param int $userid The user who edits the post.
     * @param post_content $content The new content.
     * @return post The edited post.
     * @throws moodle_exception|coding_exception|Throwable
     */
    public static function edit(post $post, int $userid, post_content $content): post {
        global $DB;
        permissions::ensure(permissions::can_edit_post($post, $userid), 'cannotupdatepost');
        if ($post->get_parentid() == 0 && trim($content->subject ?? '') === '') {
            throw new coding_exception('An empty subject is not allowed');
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $post->get_discussion()->edit_post($post, $userid, $content);
            readtracking::mark_post_read($post, $userid);
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
        return $post;
    }

    /**
     * Deletes a post with its replies; deleting the question deletes the whole discussion.
     *
     * The authors and raters of the deleted posts lose reputation, so their grades are updated.
     *
     * @param post $post The post to delete.
     * @param int $userid The user who deletes the post.
     * @return bool True if the whole discussion was deleted.
     * @throws moodle_exception|Throwable
     */
    public static function delete(post $post, int $userid): bool {
        global $DB;
        permissions::ensure(permissions::can_delete_post($post, $userid), 'cannotdeletepost');

        $discussion = $post->get_discussion();
        $modflow = $discussion->get_moodleoverflow();

        // Collect the affected users before the posts are gone to update their grades.
        $postids = $post->is_question()
            ? $DB->get_fieldset_select('moodleoverflow_posts', 'id', 'discussion = ?', [$discussion->get_id()])
            : [$post->get_id(), ...array_keys($post->get_childposts())];
        [$insql, $params] = $DB->get_in_or_equal($postids);
        $affectedusers = array_unique(array_merge(
            $DB->get_fieldset_select('moodleoverflow_posts', 'userid', "id $insql", $params),
            $DB->get_fieldset_select('moodleoverflow_ratings', 'userid', "postid $insql", $params)
        ));

        $transaction = $DB->start_delegated_transaction();
        try {
            $isdeleted = $discussion->delete_post($post, $userid);
            rating::update_grades($modflow, $affectedusers);
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
        return $isdeleted;
    }
}
