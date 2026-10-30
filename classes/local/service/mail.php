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

use core\exception\moodle_exception;
use core\task\manager;
use core_user;
use dml_exception;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\output\mail\digest;
use mod_moodleoverflow\output\mail\message;
use mod_moodleoverflow\output\mail\notification;
use mod_moodleoverflow\output\mail\rejection;
use mod_moodleoverflow\output\mail\review_request;
use mod_moodleoverflow\task\send_post_notification;
use mod_moodleoverflow\task\send_review_request;
use stdClass;
use Throwable;

/**
 * Service class for the mails of the plugin: who gets which message and when.
 *
 * Mails are sent through the message API from the no-reply user. A notification or review request is queued as an
 * ad hoc task with the post id only, a post for a digest waits in the table moodleoverflow_digest_queue until the
 * daily task. Both are built when they are sent, so they always show the current state of the post. What a message
 * says is prepared by the classes in output\mail and written in the templates in templates/mail.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mail {
    // Notifications about new posts.

    /**
     * Queues the notification about a post. It is sent once the author can no longer edit the post, so subscribers
     * get its final version. No permission checks: only call it after the post was created or approved.
     *
     * @param post $post
     * @return void
     * @throws moodle_exception
     */
    public static function queue_notification(post $post): void {
        $task = new send_post_notification();
        $task->set_custom_data(['postid' => $post->get_id()]);
        $task->set_next_run_time(max(time(), $post->created + $post->get_moodleoverflow()->get_edit_window()));
        manager::queue_adhoc_task($task);
    }

    /**
     * Sends the notification about a post to everybody who is subscribed to its discussion and may see the post.
     * Users who chose a digest in their profile get the post in their daily summary instead. Posts that wait for a
     * review, and posts that became visible longer ago than the setting "maxmailingtime" allows, are not sent.
     *
     * A recipient for whom sending fails does not stop the others. The failures are returned instead of thrown, so the
     * calling task ends normally and is not run again: nobody gets the mail twice.
     *
     * @param post $post
     * @return string[] The reasons sending failed, indexed by the id of the recipient
     * @throws dml_exception|moodle_exception
     */
    public static function send_notification(post $post): array {
        global $DB;
        $modflow = $post->get_moodleoverflow();
        $maxage = $modflow->get_edit_window() + (int) get_config('moodleoverflow', 'maxmailingtime') * HOURSECS;
        if ($post->reviewed !== 1 || $post->get_time_active() < time() - $maxage) {
            return [];
        }

        $recipientids = array_filter(
            subscription::get_recipients($post->get_discussion()),
            fn(int $userid) => $userid !== $post->get_userid() && permissions::can_view_post($post, $userid)
        );
        $failures = [];
        foreach ($DB->get_records_list('user', 'id', $recipientids) as $recipient) {
            try {
                if ($recipient->maildigest) {
                    self::add_to_digest($post, (int) $recipient->id);
                    continue;
                }

                $result = self::send('posts', new notification($post, $recipient), $recipient);
                if (!$result) {
                    $failures[$recipient->id] = 'The message API did not send the message.';
                }
            } catch (Throwable $e) {
                $failures[$recipient->id] = $e->getMessage();
            }
        }
        return $failures;
    }

    // Review requests and rejections.

    /**
     * Queues the request to review a post. It is sent once the review delay is over, so the reviewers can act on it.
     * No permission checks: only call it after a post was created that waits for its review.
     *
     * @param post $post
     * @return void
     * @throws moodle_exception|\dml_write_exception
     */
    public static function queue_review_request(post $post): void {
        $task = new send_review_request();
        $task->set_custom_data(['postid' => $post->get_id()]);
        // A post can be reviewed only after the delay is over, not at its last second.
        $task->set_next_run_time(max(time(), $post->created + $post->get_moodleoverflow()->get_review_delay() + 1));
        manager::queue_adhoc_task($task);
    }

    /**
     * Sends the request to review a post to the users who are enrolled in the course and may review it. Users with the
     * capability from outside the course (e.g. managers) may review it too, but get no mail. Nothing is sent if the
     * post was reviewed in the meantime.
     * Like the notifications, a recipient for whom sending fails does not stop the others, and the failures are
     * returned instead of thrown.
     *
     * @param post $post
     * @return string[] The reasons sending failed, indexed by the id of the recipient
     * @throws moodle_exception
     */
    public static function send_review_request(post $post): array {
        if ($post->reviewed !== 0) {
            return [];
        }

        $failures = [];
        foreach (get_enrolled_users($post->get_context(), 'mod/moodleoverflow:reviewpost') as $recipient) {
            try {
                if (!permissions::can_review_post($post, (int) $recipient->id)) {
                    continue;
                }
                $result = self::send('reviewneeded', new review_request($post, $recipient), $recipient);
                if (!$result) {
                    $failures[$recipient->id] = 'The message API did not send the message.';
                }
            } catch (Throwable $e) {
                $failures[$recipient->id] = $e->getMessage();
            }
        }
        return $failures;
    }

    /**
     * Sends the author of a rejected post the mail about it. Call it in the transaction that deletes the post, before
     * the post is deleted: the message API holds the mail back until the transaction is committed, so the author only
     * gets it if the post was deleted. A post whose author was removed (user id 0) gets no mail.
     *
     * @param post $post
     * @param string|null $reason The reason the reviewer gave, if any
     * @return void
     * @throws moodle_exception
     */
    public static function send_rejection(post $post, ?string $reason): void {
        $recipient = $post->get_userid() ? core_user::get_user($post->get_userid()) : false;
        if (!$recipient || $recipient->deleted) {
            return;
        }
        self::send('rejected', new rejection($post, $recipient, $reason), $recipient);
    }

    // Daily digests.

    /**
     * Sends every user with queued posts their daily digest and empties the queue.
     *
     * Every post is checked again, as it may have changed since it was queued: deleted posts and posts the user can no
     * longer see are left out. A user who switched the digest off in their profile in the meantime gets nothing. Like
     * the notifications, a digest that fails is not sent again: the failures are returned instead of thrown.
     *
     * @return string[] The reasons sending failed, indexed by the id of the recipient
     * @throws dml_exception
     */
    public static function send_digests(): array {
        global $DB;
        $failures = [];
        foreach ($DB->get_fieldset_sql('SELECT DISTINCT userid FROM {moodleoverflow_digest_queue}') as $userid) {
            try {
                $postids = $DB->get_fieldset_select('moodleoverflow_digest_queue', 'postid', 'userid = ?', [$userid]);
                // Emptied before sending, so a failed digest is not sent again with the next one.
                $DB->delete_records('moodleoverflow_digest_queue', ['userid' => $userid]);

                $recipient = core_user::get_user($userid);
                if (!$recipient || $recipient->deleted || !$recipient->maildigest) {
                    continue;
                }

                // Posts deleted since then are not found anymore.
                $posts = [];
                foreach ($DB->get_records_list('moodleoverflow_posts', 'id', $postids, 'created') as $record) {
                    $post = post::from_record($record);
                    if (permissions::can_view_post($post, (int) $userid)) {
                        $posts[] = $post;
                    }
                }
                // Nothing left to tell the user, so no empty digest.
                if (!$posts) {
                    continue;
                }
                if (!self::send('digests', new digest($posts, $recipient), $recipient)) {
                    $failures[$userid] = 'The message API did not send the message.';
                }
            } catch (Throwable $e) {
                $failures[$userid] = $e->getMessage();
            }
        }
        return $failures;
    }

    // Private helper functions.

    /**
     * Sends a message to a user in their language. Every kind of mail is sent through this function: it only uses
     * what the interface output\mail\message offers, not which mail it is.
     *
     * @param string $provider The message provider of the plugin, see db/messages.php
     * @param message $mail Any kind of mail (notification, digest, ...)
     * @param stdClass $recipient
     * @return bool Whether the message API sent the message
     * @throws moodle_exception
     */
    private static function send(string $provider, message $mail, stdClass $recipient): bool {
        // Build the message in the recipient's language.
        $previouslanguage = force_current_language($recipient->lang);
        try {
            $message = new \core\message\message();
            $message->component = 'mod_moodleoverflow';
            $message->name = $provider;
            $message->notification = 1;
            $message->userfrom = core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->courseid = $mail->get_courseid();
            $message->subject = $mail->get_subject();
            $message->smallmessage = $message->subject;
            $message->fullmessage = $mail->render_text();
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->contexturl = $mail->get_url()->out(false);
            $message->contexturlname = $message->subject;
        } finally {
            force_current_language($previouslanguage);
        }
        return message_send($message) !== false;
    }

    /**
     * Queues a post for the daily digest of a user. A post that is already queued for the user is not added again.
     *
     * @param post $post
     * @param int $userid
     * @return void
     * @throws dml_exception|moodle_exception
     */
    private static function add_to_digest(post $post, int $userid): void {
        global $DB;
        $record = ['userid' => $userid, 'postid' => $post->get_id()];
        if (!$DB->record_exists('moodleoverflow_digest_queue', $record)) {
            $DB->insert_record('moodleoverflow_digest_queue', (object) $record);
        }
    }
}
