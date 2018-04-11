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

/**
 * Privacy Subsystem implementation for mod_moodleoverflow.
 *
 * @package    mod_moodleoverflow
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for mod_moodleoverflow implementing provider.
 *
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {

    /** Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->link_subsystem('core_files',
            'privacy:metadata:core_files'
        );

        $collection->add_database_table(
            'moodleoverflow_discussions',
            [
                'course'         => 'privacy:metadata:moodleoverflow_discussions:course',
                'moodleoverflow' => 'privacy:metadata:moodleoverflow_discussions:moodleoverflow',
                'name'           => 'privacy:metadata:moodleoverflow_discussions:name',
                'firstpost'      => 'privacy:metadata:moodleoverflow_discussions:firstpost',
                'userid'         => 'privacy:metadata:moodleoverflow_discussions:userid',
                'timemodified'   => 'privacy:metadata:moodleoverflow_discussions:timemodified',
                'timestart'      => 'privacy:metadata:moodleoverflow_discussions:timestart',
                'usermodified'   => 'privacy:metadata:moodleoverflow_discussions:usermodified'
            ],
            'privacy:metadata:moodleoverflow_discussions');

        $collection->add_database_table(
            'moodleoverflow_posts',
            [
                'discussion'    => 'privacy:metadata:moodleoverflow_posts:discussion',
                'parent'        => 'privacy:metadata:moodleoverflow_posts:parent',
                'userid'        => 'privacy:metadata:moodleoverflow_posts:userid',
                'created'       => 'privacy:metadata:moodleoverflow_posts:created',
                'mmodified'     => 'privacy:metadata:moodleoverflow_posts:modified',
                'message'       => 'privacy:metadata:moodleoverflow_posts:message',
                'messageformat' => 'privacy:metadata:moodleoverflow_posts:messageformat',
                'attachment'    => 'privacy:metadata:moodleoverflow_posts:attachment',
                'mailed'        => 'privacy:metadata:moodleoverflow_posts:mailed'
            ],
            'privacy:metadata:moodleoverflow_posts');

        $collection->add_database_table(
            'moodleoverflow_read',
            [
                'userid'           => 'privacy:metadata:moodleoverflow_read:userid',
                'moodleoverflowid' => 'privacy:metadata:moodleoverflow_read:moodleoverflowid',
                'discussionid'     => 'privacy:metadata:moodleoverflow_read:discussionid',
                'postid'           => 'privacy:metadata:moodleoverflow_read:postid',
                'firstread'        => 'privacy:metadata:moodleoverflow_read:firstread',
                'lastread'         => 'privacy:metadata:moodleoverflow_read:lastread'
            ],
            'privacy:metadata:moodleoverflow_read');

        $collection->add_database_table(
            'moodleoverflow_subscriptions',
            [
                'userid'         => 'privacy:metadata:moodleoverflow_subscriptions:userid',
                'moodleoverflow' => 'privacy:metadata:moodleoverflow_subscriptions:moodleoverflow'
            ],
            'privacy:metadata:moodleoverflow_subscriptions');

        $collection->add_database_table(
            'moodleoverflow_discuss_subs',
            [
                'userid'         => 'privacy:metadata:moodleoverflow_discuss_subs:userid',
                'moodleoverflow' => 'privacy:metadata:moodleoverflow_discuss_subs:moodleoverflow',
                'discussion'     => 'privacy:metadata:moodleoverflow_discuss_subs:discussion',
                'preference'     => 'privacy:metadata:moodleoverflow_discuss_subs:preference'
            ],
            'privacy:metadata:moodleoverflow_discuss_subs');

        $collection->add_database_table(
            'moodleoverflow_ratings',
            [
                'userid'           => 'privacy:metadata:moodleoverflow_ratings:userid',
                'postid'           => 'privacy:metadata:moodleoverflow_ratings:postid',
                'discussionid'     => 'privacy:metadata:moodleoverflow_ratings:discussionid',
                'moodleoverflowid' => 'privacy:metadata:moodleoverflow_ratings:moodleoverflowid',
                'rating'           => 'privacy:metadata:moodleoverflow_ratings:rating',
                'firstrated'       => 'privacy:metadata:moodleoverflow_ratings:firstrated',
                'lastchanged'      => 'privacy:metadata:moodleoverflow_ratings:lastchanged'
            ],
            'privacy:metadata:moodleoverflow_ratings');

        $collection->add_database_table(
            'moodleoverflow_tracking',
            [
                'userid'           => 'privacy:metadata:moodleoverflow_tracking:userid',
                'moodleoverflowid' => 'privacy:metadata:moodleoverflow_tracking:moodleoverflowid'
            ],
            'privacy:metadata:moodleoverflow_tracking');

        return $collection;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (empty($context)) {
            return;
        }
        $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
        $discussions = $DB->get_fieldset_select('moodleoverflow_discussions', 'id', 'moodleoverflow = :mid', array('mid' => $instanceid));
        $DB->delete_records('moodleoverflow_discussions', ['moodleoverflow' => $instanceid]);
        $DB->delete_records_list('moodleoverflow_posts', 'discussion', $discussions);
        $DB->delete_records('moodleoverflow_read', ['moodleoverflowid' => $instanceid]);
        $DB->delete_records('moodleoverflow_subscriptions', ['moodleoverflow' => $instanceid]);
        $DB->delete_records('moodleoverflow_discuss_subs', ['moodleoverflow' => $instanceid]);
        $DB->delete_records('moodleoverflow_ratings', ['moodleoverflowid' => $instanceid]);
        $DB->delete_records('moodleoverflow_tracking', ['moodleoverflowid' => $instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            $discussions = $DB->get_fieldset_select('moodleoverflow_discussions', 'id', 'moodleoverflow = :mid', array('mid' => $instanceid));

            $DB->execute('UPDATE {moodleoverflow_discussions} SET userid = 0, name = \''. get_string('privacy:anonym_discussion_name', 'mod_moodleoverflow') .'\' WHERE userid = :userid AND moodleoverflow = :mid',
                array('userid' => $userid, 'mid' => $instanceid));
            // TODO delete only in specific instance, create list of discussion ids
            $DB->execute('UPDATE {moodleoverflow_posts} SET userid = 0, message = \''. get_string('privacy:anonym_post_name', 'mod_moodleoverflow') .'\' WHERE userid = :userid AND discussion IN :discussionlist', array('userid' => $userid, 'discussionlist' => $discussions));

            $DB->execute('UPDATE {moodleoverflow_read} SET userid = 0 WHERE userid = :userid AND moodleoverflowid = :mid',
                array('userid' => $userid, 'mid' => $instanceid));
            $DB->execute('UPDATE {moodleoverflow_ratings} SET userid = 0 WHERE userid = :userid AND moodleoverflowid= :mid',
                array('userid' => $userid, 'mid' => $instanceid));
            $DB->delete_records('moodleoverflow_read', ['userid' => $userid, 'moodleoverflowid' => $instanceid]);
            $DB->delete_records('moodleoverflow_subscriptions', ['userid' => $userid, 'moodleoverflow' => $instanceid]);
            $DB->delete_records('moodleoverflow_discuss_subs', ['userid' => $userid, 'moodleoverflow' => $instanceid]);
            $DB->delete_records('moodleoverflow_tracking', ['userid' => $userid, 'moodleoverflowid' => $instanceid]);
            // TODO delete attachments
        }
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        // TODO: Implement export_user_data() method.
        writer::with_context($context)
            ->export_data($subcontext, $post)
            ->export_area_files($subcontext, 'mod_moodleoverflow', 'attachment', $post->id)
            ->export_metadata($subcontext, 'postread', (object) ['firstread' => $firstread], new \lang_string('privacy:export:post:postread'));
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     *
     * @return contextlist $contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // Fetch all forum discussions, forum posts, ratings, tracking settings and subscriptions.
        $sql = "SELECT c.id
                FROM {context} c 
                INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                INNER JOIN {moodleoverflow} mof ON mof.id = cm.instance
                LEFT JOIN {moodleoverflow_discussions} d ON d.moodleoverflow = mof.id
                LEFT JOIN {moodleoverflow_posts} p ON p.discussion = d.id
                LEFT JOIN {moodleoverflow_read} r ON r.moodleoverflowid = mof.id
                LEFT JOIN {moodleoverfow_subscriptions} s ON s.moodleoverflow = mof.id
                LEFT JOIN {moodleoverflow_discuss_subs} ds ON ds.moodleoverflow = mof.id
                LEFT JOIN {moodleoverflow_ratings}ra ON ra.moodleoverflowid = mof.id
                LEFT JOIN {moodleoverflow_tracking} track ON track.moodleoverflowid = mof.id
                WHERE (
                    d.userid = :userid OR 
                    r.userid = :userid OR 
                    s.userid = :userid OR 
                    ds.userid = :userid OR 
                    ra.userid = :userid OR 
                    track.userid = :userid
                )
         ";

        $params = [
            'modname'      => 'moodleoverflow',
            'contextlevel' => CONTEXT_MODULE,
            'userid'       => $userid
        ];

        $contextlist = new \core_privacy\local\request\contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }
}