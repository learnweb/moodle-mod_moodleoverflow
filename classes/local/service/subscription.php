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
use core\exception\moodle_exception;
use dml_exception;
use mod_moodleoverflow\event\discussion_subscription_created;
use mod_moodleoverflow\event\discussion_subscription_deleted;
use mod_moodleoverflow\event\subscription_created;
use mod_moodleoverflow\event\subscription_deleted;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use Throwable;

/**
 * Service class for subscriptions to moodleoverflows and discussions.
 *
 * A row in moodleoverflow_subscriptions or moodleoverflow_discuss_subs is the choice of a user: opt-in or opt-out. The
 * subscription mode of the moodleoverflow gives the default for everyone without a choice.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription {
    /** @var int Stored preference of a user that subscribed. */
    public const OPTIN = 1;

    /** @var int Stored preference of a user that unsubscribed. */
    public const OPTOUT = -1;

    // Functions for use-cases. Represent a specific action.

    /**
     * Subscribes a user to a moodleoverflow. Replaces the choices of the user for single discussions.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function subscribe(moodleoverflow $modflow, int $userid): void {
        permissions::ensure(permissions::can_change_subscription($modflow, $userid), 'cannotchangesubscription');
        self::save_moodleoverflow_choice($modflow, $userid, self::OPTIN);
    }

    /**
     * Unsubscribes a user from a moodleoverflow. Replaces the choices of the user for single discussions.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function unsubscribe(moodleoverflow $modflow, int $userid): void {
        permissions::ensure(permissions::can_change_subscription($modflow, $userid), 'cannotchangesubscription');
        self::save_moodleoverflow_choice($modflow, $userid, self::OPTOUT);
    }

    /**
     * Subscribes a user to a single discussion.
     *
     * @param discussion $discussion
     * @param int $userid
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function subscribe_discussion(discussion $discussion, int $userid): void {
        permissions::ensure(permissions::can_change_discussion_subscription($discussion, $userid), 'cannotchangesubscription');
        self::save_discussion_choice($discussion, $userid, self::OPTIN);
    }

    /**
     * Unsubscribes a user from a single discussion.
     *
     * @param discussion $discussion
     * @param int $userid
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function unsubscribe_discussion(discussion $discussion, int $userid): void {
        permissions::ensure(permissions::can_change_discussion_subscription($discussion, $userid), 'cannotchangesubscription');
        self::save_discussion_choice($discussion, $userid, self::OPTOUT);
    }

    /**
     * Lets a user follow the discussion they posted in. No permission checks: only call it after the post was created.
     * Nothing happens if the user is already subscribed, chose something for this discussion before or does not want to
     * follow discussions (profile setting autosubscribe).
     *
     * @param discussion $discussion
     * @param int $userid The author of the post
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function follow_on_post(discussion $discussion, int $userid): void {
        global $DB;
        $key = ['userid' => $userid, 'discussion' => $discussion->get_id()];
        if (
            !permissions::can_subscribe($discussion->get_moodleoverflow(), $userid)
            || self::is_subscribed_to_discussion($discussion, $userid)
            || $DB->record_exists('moodleoverflow_discuss_subs', $key)
            || !$DB->get_field('user', 'autosubscribe', ['id' => $userid])
        ) {
            return;
        }
        self::save_discussion_choice($discussion, $userid, self::OPTIN);
    }

    /**
     * Deletes subscription choices. No permission checks: only call it as a consequence of an action that was already
     * authorised (changing the subscription mode, deleting a discussion or instance, unenrolling a user). At least one
     * parameter must be given; several parameters are combined (e.g. userid and modflowid: the choices of one user in
     * one moodleoverflow).
     *
     * @param int|null $userid Delete all the choices of this user, for moodleoverflows and discussions.
     * @param int|null $discussid Delete all the choices for this discussion. Only touches moodleoverflow_discuss_subs: the
     *                            choices for the whole moodleoverflow are not bound to a discussion and stay.
     * @param int|null $modflowid Delete all the choices for this moodleoverflow and all of its discussions.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function delete_subscriptions(?int $userid = null, ?int $discussid = null, ?int $modflowid = null): void {
        global $DB;

        $conditions = array_filter(
            ['userid' => $userid, 'discussion' => $discussid, 'moodleoverflow' => $modflowid],
            fn($value) => $value !== null
        );
        if (!$conditions) {
            throw new coding_exception('delete_subscriptions() needs at least one condition');
        }
        $DB->delete_records('moodleoverflow_discuss_subs', $conditions);
        if ($discussid === null) {
            $DB->delete_records('moodleoverflow_subscriptions', $conditions);
        }
    }

    // Check functions.

    /**
     * Tells whether a user is subscribed to a moodleoverflow.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    public static function is_subscribed(moodleoverflow $modflow, int $userid): bool {
        return self::resolve($modflow, null, $userid);
    }

    /**
     * Tells whether a user is subscribed to a discussion, i.e. gets mails about its posts.
     *
     * @param discussion $discussion
     * @param int $userid
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    public static function is_subscribed_to_discussion(discussion $discussion, int $userid): bool {
        return self::resolve($discussion->get_moodleoverflow(), $discussion, $userid);
    }

    /**
     * Returns the users that are subscribed to a discussion. Whether a recipient may see a single post is not checked.
     *
     * @param discussion $discussion
     * @return int[] The ids of the subscribed users
     * @throws dml_exception|moodle_exception
     */
    public static function get_recipients(discussion $discussion): array {
        global $DB;
        $modflow = $discussion->get_moodleoverflow();

        // Without a default, only users with a choice can be subscribed.
        $candidates = [];
        if ($modflow->get_subscription_mode()->subscribes_by_default()) {
            $candidates = array_keys(get_enrolled_users($modflow->get_context(), '', 0, 'u.id', null, 0, 0, true));
        }
        $candidates = array_merge(
            $candidates,
            $DB->get_fieldset_select('moodleoverflow_subscriptions', 'userid', 'moodleoverflow = ?', [$modflow->id]),
            $DB->get_fieldset_select('moodleoverflow_discuss_subs', 'userid', 'discussion = ?', [$discussion->get_id()])
        );
        $candidates = array_unique(array_map('intval', $candidates));

        return array_values(array_filter(
            $candidates,
            fn($userid) => self::is_subscribed_to_discussion($discussion, $userid)
        ));
    }

    // Helper functions.

    /**
     * The one rule for "is subscribed". A choice for the discussion counts before a choice for the moodleoverflow, which
     * counts before the default of the subscription mode.
     *
     * @param moodleoverflow $modflow
     * @param discussion|null $discussion null to only look at the moodleoverflow
     * @param int $userid
     * @return bool
     * @throws dml_exception|moodle_exception
     */
    private static function resolve(moodleoverflow $modflow, ?discussion $discussion, int $userid): bool {
        global $DB;
        if (!permissions::can_subscribe($modflow, $userid)) {
            return false;
        }

        $mode = $modflow->get_subscription_mode();
        $isdefault = $mode->subscribes_by_default() && permissions::is_default_subscriber($modflow, $userid);

        // Default subscribers of a forced moodleoverflow cannot choose, so their rows do not count.
        if ($isdefault && $mode === subscription_mode::FORCED) {
            return true;
        }

        $preference = false;
        if ($discussion) {
            $params = ['userid' => $userid, 'discussion' => $discussion->get_id()];
            $preference = $DB->get_field('moodleoverflow_discuss_subs', 'preference', $params);
        }
        if ($preference === false) {
            $params = ['userid' => $userid, 'moodleoverflow' => $modflow->id];
            $preference = $DB->get_field('moodleoverflow_subscriptions', 'preference', $params);
        }
        return $preference === false ? $isdefault : (int) $preference === self::OPTIN;
    }

    /**
     * Saves the choice of a user for a whole moodleoverflow and deletes their choices for its discussions.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @param int $preference self::OPTIN or self::OPTOUT
     * @return void
     * @throws dml_exception|moodle_exception
     */
    private static function save_moodleoverflow_choice(moodleoverflow $modflow, int $userid, int $preference): void {
        global $DB;
        $key = ['userid' => $userid, 'moodleoverflow' => $modflow->id];

        $transaction = $DB->start_delegated_transaction();
        try {
            $id = self::save_choice('moodleoverflow_subscriptions', $key, $preference);
            $DB->delete_records('moodleoverflow_discuss_subs', $key);

            $transaction->allow_commit();
        } catch (Throwable $t) {
            $transaction->rollback($t);
        }

        if ($id !== null) {
            $event = $preference === self::OPTIN ? subscription_created::class : subscription_deleted::class;
            $event::create([
                'context' => $modflow->get_context(),
                'objectid' => $id,
                'relateduserid' => $userid,
                'other' => ['moodleoverflowid' => $modflow->id],
            ])->trigger();
        }
    }

    /**
     * Saves the choice of a user for a single discussion.
     *
     * @param discussion $discussion
     * @param int $userid
     * @param int $preference self::OPTIN or self::OPTOUT
     * @return void
     * @throws dml_exception|moodle_exception
     */
    private static function save_discussion_choice(discussion $discussion, int $userid, int $preference): void {
        $key = ['userid' => $userid, 'discussion' => $discussion->get_id()];
        $fields = ['moodleoverflow' => $discussion->get_moodleoverflowid()];
        $id = self::save_choice('moodleoverflow_discuss_subs', $key, $preference, $fields);

        if ($id !== null) {
            $event = $preference === self::OPTIN ? discussion_subscription_created::class : discussion_subscription_deleted::class;
            $event::create([
                'context' => $discussion->get_moodleoverflow()->get_context(),
                'objectid' => $id,
                'relateduserid' => $userid,
                'other' => ['moodleoverflowid' => $discussion->get_moodleoverflowid(), 'discussion' => $discussion->get_id()],
            ])->trigger();
        }
    }

    /**
     * Inserts or updates a choice.
     *
     * @param string $table moodleoverflow_subscriptions or moodleoverflow_discuss_subs
     * @param array $key The fields of the unique key of the table
     * @param int $preference self::OPTIN or self::OPTOUT
     * @param array $fields Further fields for a new row
     * @return int|null The id of the row, null if the row already had this preference
     * @throws dml_exception
     */
    private static function save_choice(string $table, array $key, int $preference, array $fields = []): ?int {
        global $DB;
        $row = $DB->get_record($table, $key, 'id, preference');
        if (!$row) {
            return $DB->insert_record($table, $key + $fields + ['preference' => $preference]);
        }
        if ((int) $row->preference === $preference) {
            return null;
        }
        $DB->set_field($table, 'preference', $preference, ['id' => $row->id]);
        return (int) $row->id;
    }
}
