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

namespace mod_moodleoverflow\local;

use coding_exception;
use core_availability\info_module;
use dml_exception;
use mod_moodleoverflow\local\enum\tracking_type;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use moodle_exception;

/**
 * Stateless class that bundles permissions checks. Functions check with capabilities and moodleoverflow rules if actions are
 * possible or if elements are visible to a user.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permissions {
    /**
     * Throws if a permission check failed.
     * @param bool $allowed result of a can_*() check
     * @param string $errorcode error string in lang/en/moodleoverflow.php
     * @throws moodle_exception
     */
    public static function ensure(bool $allowed, string $errorcode): void {
        if (!$allowed) {
            throw new moodle_exception($errorcode, 'moodleoverflow');
        }
    }

    // View permissions.

    /**
     * If a user can see the moodleoverflow.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    public static function can_view_moodleoverflow(moodleoverflow $modflow, int $userid): bool {
        // User should be in the course (or access it), the module should be visible to it and the capability should be there.
        return can_access_course($modflow->get_course(), $userid, '', true)
            && info_module::is_user_visible($modflow->get_cm(), $userid, false)
            && has_capability('mod/moodleoverflow:viewdiscussion', $modflow->get_context(), $userid);
    }

    /**
     * If a user can see the post. A user can see the post if the discussion's question is visible and the post passes
     * the review check.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws moodle_exception
     */
    public static function can_view_post(post $post, int $userid): bool {
        return self::can_view_discussion($post->get_discussion(), $userid) && self::review_allows_view($post, $userid);
    }

    /**
     * If a user can see the discussion.
     * @param discussion $discussion
     * @param int $userid
     * @return bool
     * @throws moodle_exception
     */
    public static function can_view_discussion(discussion $discussion, int $userid): bool {
        return self::can_view_moodleoverflow($discussion->get_moodleoverflow(), $userid)
            && self::review_allows_view($discussion->get_first_post(), $userid);
    }

    /**
     * If a user can see the author of a post.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws moodle_exception
     */
    public static function can_view_author(post $post, int $userid): bool {
        $ownpost = $post->get_userid() === $userid;
        $isquestioner = $post->get_userid() === $post->get_discussion()->get_userid();
        return self::can_view_post($post, $userid)
            && ($ownpost || !$post->get_moodleoverflow()->is_author_anonymous($isquestioner));
    }

    /**
     * If a user can view the reputation of a post.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws moodle_exception
     */
    public static function can_view_reputation(post $post, int $userid): bool {
        return $post->get_moodleoverflow()->is_reputation_enabled() && self::can_view_author($post, $userid);
    }

    /**
     * If a user can view the user statistics of a moodleoverflow.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function can_view_userstats(moodleoverflow $modflow, int $userid): bool {
        return self::can_view_moodleoverflow($modflow, $userid)
            && has_capability('mod/moodleoverflow:viewanyrating', $modflow->get_context(), $userid)
            && get_config('moodleoverflow', 'showuserstats');
    }

    /**
     * A post is "accepted" for view by the review domain if the post is reviewed, it is the own post or if the user
     * is a reviewer.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws coding_exception|moodle_exception
     */
    private static function review_allows_view(post $post, int $userid): bool {
        return $post->reviewed === 1
            || $post->get_userid() === $userid
            || has_capability('mod/moodleoverflow:reviewpost', $post->get_context(), $userid);
    }

    // Posting permissions.

    /**
     * If a user can start a discussion.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function can_start_discussion(moodleoverflow $modflow, int $userid): bool {
        return self::can_view_moodleoverflow($modflow, $userid)
            && has_capability('mod/moodleoverflow:startdiscussion', $modflow->get_context(), $userid);
    }

    /**
     * If a user can reply to a post.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws coding_exception|moodle_exception
     */
    public static function can_reply(post $post, int $userid): bool {
        return self::can_view_post($post, $userid)
            && has_capability('mod/moodleoverflow:replypost', $post->get_context(), $userid)
            && $post->reviewed === 1
            && (
                $post->get_moodleoverflow()->is_answer_window_open()
                || has_capability('mod/moodleoverflow:addinstance', $post->get_context(), $userid)
            );
    }

    /**
     * If a user can edit a post.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws moodle_exception
     */
    public static function can_edit_post(post $post, int $userid): bool {
        if (!self::can_view_post($post, $userid)) {
            return false;
        }
        $context = $post->get_context();

        // Users like teacher with the capability to edit any post.
        if (has_capability('mod/moodleoverflow:editanypost', $context, $userid)) {
            return true;
        }

        // Users like students can only edit an own post under certain circumstances.
        $isownpost = $post->get_userid() === $userid;
        $cancreate = has_capability(
            $post->is_question() ? 'mod/moodleoverflow:startdiscussion' : 'mod/moodleoverflow:replypost',
            $context,
            $userid
        );
        $lockedbyreview = $post->get_moodleoverflow()->requires_review($post->is_question()) && $post->reviewed === 1;

        return $isownpost && $cancreate && $post->in_edit_window() && !$lockedbyreview;
    }

    /**
     * If a user can delete a post.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws coding_exception|moodle_exception
     */
    public static function can_delete_post(post $post, int $userid): bool {
        // Only two kind of users can delete a post. A user with right to delete any post or the author of the post.
        $context = $post->get_context();
        $candeleteanypost = has_capability('mod/moodleoverflow:deleteanypost', $context, $userid);
        $candeleteownpost = $post->get_userid() === $userid
            && has_capability('mod/moodleoverflow:deleteownpost', $context, $userid)
            && $post->in_edit_window()
            && $post->count_replies(false) === 0;

        return self::can_view_post($post, $userid) && ($candeleteownpost || $candeleteanypost);
    }

    /**
     * If an attachment can be added.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws coding_exception
     */
    public static function can_add_attachments(moodleoverflow $modflow, int $userid): bool {
        ['maxattachments' => $maxattachments, 'maxbytes' => $maxbytes] = $modflow->get_attachment_limits();
        // Maxbytes == 1 means no attachments at all.
        return $maxattachments > 0
            && $maxbytes !== 1
            && has_capability('mod/moodleoverflow:createattachment', $modflow->get_context(), $userid);
    }

    // Rating permissions.

    /**
     * If a user can vote a post.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws moodle_exception
     */
    public static function can_vote(post $post, int $userid): bool {
        return self::can_view_post($post, $userid)
            && $post->get_moodleoverflow()->is_rating_enabled()
            && has_capability('mod/moodleoverflow:ratepost', $post->get_context(), $userid)
            && $post->get_userid() !== $userid
            && $post->reviewed === 1;
    }

    /**
     * If a user can mark a post as helpful.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws moodle_exception
     */
    public static function can_mark_helpful(post $post, int $userid): bool {
        // Only the user that started the discussion can mark posts as helpful, but the post can not be an own post.
        // The post must be a direct answer of a discussion starter post. Comments can not be marked.
        return self::can_view_post($post, $userid)
            && $post->reviewed === 1
            && $post->is_direct_answer()
            && $post->get_discussion()->get_userid() === $userid
            && $post->get_userid() !== $userid
            && has_capability('mod/moodleoverflow:ratepost', $post->get_context(), $userid);
    }

    /**
     * If a user can mark a post as solved.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws coding_exception|moodle_exception
     */
    public static function can_mark_solved(post $post, int $userid): bool {
        // Only teachers can mark posts as solved. The post must be a direct reply, comments can not be marked.
        return self::can_view_post($post, $userid)
            && $post->reviewed === 1
            && $post->is_direct_answer()
            && has_capability('mod/moodleoverflow:marksolved', $post->get_context(), $userid);
    }

    // Review permissions.

    /**
     * If a user can review all posts in a moodleoverflow.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function can_review_posts(moodleoverflow $modflow, int $userid): bool {
        return self::can_view_moodleoverflow($modflow, $userid)
            && has_capability('mod/moodleoverflow:reviewpost', $modflow->get_context(), $userid);
    }

    /**
     * If a post can be reviewed by the user.
     * @param post $post
     * @param int $userid
     * @return bool
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function can_review_post(post $post, int $userid): bool {
        return self::can_view_post($post, $userid)
            && has_capability('mod/moodleoverflow:reviewpost', $post->get_context(), $userid)
            && (time() - $post->created) > $post->get_moodleoverflow()->get_review_delay()
            && $post->reviewed === 0;
    }

    // Discussion permissions.

    /**
     * If a user can move a discussion to another moodleoverflow.
     * @param discussion $discussion
     * @param moodleoverflow $destination
     * @param int $userid
     * @return bool
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public static function can_move_discussion(discussion $discussion, moodleoverflow $destination, int $userid): bool {
        $source = $discussion->get_moodleoverflow();
        return $destination->id !== $source->id
            && $destination->course === $source->course
            && !$destination->get_cm()->deletioninprogress
            && $destination->get_anonymity()->value >= $source->get_anonymity()->value
            && self::can_view_discussion($discussion, $userid)
            && self::can_view_moodleoverflow($destination, $userid)
            && has_capability('mod/moodleoverflow:movetopic', $source->get_context(), $userid)
            && has_capability('mod/moodleoverflow:movetopic', $destination->get_context(), $userid);
    }

    // Read tracking permissions.

    /**
     * If a user can track a moodleoverflow.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    public static function can_track(moodleoverflow $modflow, int $userid): bool {
        return $userid !== 0
            && !isguestuser($userid)
            && self::can_view_moodleoverflow($modflow, $userid)
            && $modflow->get_tracking_type() !== tracking_type::OFF;
    }

    /**
     * If a user can change their readtracking setting in a moodleoverflow.
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    public static function can_change_tracking(moodleoverflow $modflow, int $userid): bool {
        return self::can_track($modflow, $userid) && $modflow->get_tracking_type()->users_can_choose();
    }
}
