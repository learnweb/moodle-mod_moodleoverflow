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
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\subscriptions;

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
        $subject = trim($content->subject ?? '');
        if ($subject === '') {
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
            $discussion->add($content, (!$modflow->requires_review(true) || permissions::can_review_posts($modflow, $userid)));
            subscriptions::post_subscription($modflow, $discussion, $modflow->get_context(), $userid);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return $discussion;
    }
}
