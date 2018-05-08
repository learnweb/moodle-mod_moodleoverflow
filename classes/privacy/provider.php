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
use \core_privacy\local\request\helper as request_helper;
use \core_privacy\local\request\transform;
use mod_moodleoverflow\ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for mod_moodleoverflow implementing provider.
 *
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {

    use helper;

    /** Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     *
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'moodleoverflow_discussions',
            [
                'name'         => 'privacy:metadata:moodleoverflow_discussions:name',
                'userid'       => 'privacy:metadata:moodleoverflow_discussions:userid',
                'timemodified' => 'privacy:metadata:moodleoverflow_discussions:timemodified',
                'usermodified' => 'privacy:metadata:moodleoverflow_discussions:usermodified'
            ],
            'privacy:metadata:moodleoverflow_discussions');

        $collection->add_database_table(
            'moodleoverflow_posts',
            [
                'discussion' => 'privacy:metadata:moodleoverflow_posts:discussion',
                'parent'     => 'privacy:metadata:moodleoverflow_posts:parent',
                'userid'     => 'privacy:metadata:moodleoverflow_posts:userid',
                'created'    => 'privacy:metadata:moodleoverflow_posts:created',
                'modified'   => 'privacy:metadata:moodleoverflow_posts:modified',
                'message'    => 'privacy:metadata:moodleoverflow_posts:message'
            ],
            'privacy:metadata:moodleoverflow_posts');

        $collection->add_database_table(
            'moodleoverflow_read',
            [
                'userid'       => 'privacy:metadata:moodleoverflow_read:userid',
                'discussionid' => 'privacy:metadata:moodleoverflow_read:discussionid',
                'postid'       => 'privacy:metadata:moodleoverflow_read:postid',
                'firstread'    => 'privacy:metadata:moodleoverflow_read:firstread',
                'lastread'     => 'privacy:metadata:moodleoverflow_read:lastread'
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
                'userid'     => 'privacy:metadata:moodleoverflow_discuss_subs:userid',
                'discussion' => 'privacy:metadata:moodleoverflow_discuss_subs:discussion',
                'preference' => 'privacy:metadata:moodleoverflow_discuss_subs:preference'
            ],
            'privacy:metadata:moodleoverflow_discuss_subs');

        $collection->add_database_table(
            'moodleoverflow_ratings',
            [
                'userid'      => 'privacy:metadata:moodleoverflow_ratings:userid',
                'postid'      => 'privacy:metadata:moodleoverflow_ratings:postid',
                'rating'      => 'privacy:metadata:moodleoverflow_ratings:rating',
                'firstrated'  => 'privacy:metadata:moodleoverflow_ratings:firstrated',
                'lastchanged' => 'privacy:metadata:moodleoverflow_ratings:lastchanged'
            ],
            'privacy:metadata:moodleoverflow_ratings');

        $collection->add_database_table(
            'moodleoverflow_tracking',
            [
                'userid'           => 'privacy:metadata:moodleoverflow_tracking:userid',
                'moodleoverflowid' => 'privacy:metadata:moodleoverflow_tracking:moodleoverflowid'
            ],
            'privacy:metadata:moodleoverflow_tracking');

        $collection->link_subsystem('core_files',
            'privacy:metadata:core_files'
        );

        return $collection;
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
                LEFT JOIN {moodleoverflow_subscriptions} s ON s.moodleoverflow = mof.id
                LEFT JOIN {moodleoverflow_discuss_subs} ds ON ds.moodleoverflow = mof.id
                LEFT JOIN {moodleoverflow_ratings} ra ON ra.moodleoverflowid = mof.id
                LEFT JOIN {moodleoverflow_tracking} track ON track.moodleoverflowid = mof.id
                WHERE (
                    d.userid = :duserid OR 
                    d.usermodified = :dmuserid OR
                    p.userid = :puserid OR
                    r.userid = :ruserid OR 
                    s.userid = :suserid OR 
                    ds.userid = :dsuserid OR 
                    ra.userid = :rauserid OR 
                    track.userid = :userid
                )
         ";

        $params = [
            'modname'      => 'moodleoverflow',
            'contextlevel' => CONTEXT_MODULE,
            'duserid'      => $userid,
            'dmuserid'      => $userid,
            'puserid'      => $userid,
            'ruserid'      => $userid,
            'suserid'      => $userid,
            'dsuserid'     => $userid,
            'rauserid'     => $userid,
            'userid'       => $userid
        ];

        $contextlist = new \core_privacy\local\request\contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT 
                    c.id AS contextid,
                    mof.*,
                    cm.id AS cmid,
                    s.userid AS subscribed,
                    track.userid AS tracked
                FROM {context} c 
                INNER JOIN {course_modules} cm ON cm.id = c.instanceid 
                INNER JOIN {modules} m ON m.id = cm.module
                INNER JOIN {moodleoverflow} mof ON mof.id = cm.instance
                LEFT JOIN {moodleoverflow_subscriptions} s ON s.moodleoverflow = mof.id AND s.userid = :suserid
                LEFT JOIN {moodleoverflow_ratings} ra ON ra.moodleoverflowid = mof.id AND ra.userid = :rauserid
                LEFT JOIN {moodleoverflow_tracking} track ON track.moodleoverflowid = mof.id AND track.userid = :userid
                WHERE (
                    c.id {$contextsql}
                )
                ";

        $params = [
            'suserid'  => $userid,
            'rauserid' => $userid,
            'userid'   => $userid
        ];
        $params += $contextparams;

        // Keep a mapping of moodleoverflowid to contextid.
        $mappings = [];

        $forums = $DB->get_recordset_sql($sql, $params);
        foreach ($forums as $forum) {
            $mappings[$forum->id] = $forum->contextid;

            $context = \context::instance_by_id($mappings[$forum->id]);

            // Store the main moodleoverflow data.
            $data = request_helper::get_context_data($context, $user);
            writer::with_context($context)
                ->export_data([], $data);
            request_helper::export_context_files($context, $user);

            // Store relevant metadata about this forum instance.
            static::export_subscription_data($userid, $forum);
            static::export_tracking_data($userid, $forum);
        }

        $forums->close();

        if (!empty($mappings)) {
            // Store all discussion data for this moodleoverflow.
            static::export_discussion_data($userid, $mappings);
            // Store all post data for this moodleoverflow.
            static::export_all_posts($userid, $mappings);
        }
    }

    /**
     * Store all information about all discussions that we have detected this user to have access to.
     *
     * @param   int   $userid   The userid of the user whose data is to be exported.
     * @param   array $mappings A list of mappings from forumid => contextid.
     *
     * @return  array       Which forums had data written for them.
     */
    protected static function export_discussion_data(int $userid, array $mappings) {
        global $DB;
        // Find all of the discussions, and discussion subscriptions for this forum.
        list($foruminsql, $forumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    d.*,
                    dsub.preference
                  FROM {moodleoverflow} mof
            INNER JOIN {moodleoverflow_discussions} d ON d.moodleoverflow = mof.id
            LEFT JOIN {moodleoverflow_discuss_subs} dsub ON dsub.discussion = d.id
                 WHERE mof.id ${foruminsql}
                   AND (
                        d.userid    = :discussionuserid OR
                        d.usermodified = :dmuserid OR
                        dsub.userid = :dsubuserid
                   )
        ";
        $params = [
            'discussionuserid' => $userid,
            'dmuserid' => $userid,
            'dsubuserid'       => $userid,
        ];
        $params += $forumparams;

        // Keep track of the forums which have data.
        $forumswithdata = [];
        $discussions = $DB->get_recordset_sql($sql, $params);
        foreach ($discussions as $discussion) {
            $forumswithdata[$discussion->moodleoverflow] = true;
            $context = \context::instance_by_id($mappings[$discussion->moodleoverflow]);

            // Store related metadata for this discussion.
            static::export_discussion_subscription_data($userid, $context, $discussion);
            $discussiondata = (object) [
                'name'            => format_string($discussion->name, true),
                'timemodified'    => transform::datetime($discussion->timemodified),
                'creator_was_you' => transform::yesno($discussion->userid == $userid),
                'last_modifier_was_you' => transform::yesno($discussion->usermodified == $userid)
            ];
            // Store the discussion content.
            writer::with_context($context)
                ->export_data(static::get_discussion_area($discussion), $discussiondata);
            // Forum discussions do not have any files associately directly with them.
        }
        $discussions->close();

        return $forumswithdata;
    }

    /**
     * Store all information about all posts that we have detected this user to have access to.
     *
     * @param   int   $userid   The userid of the user whose data is to be exported.
     * @param   array $mappings A list of mappings from forumid => contextid.
     *
     * @return  array       Which forums had data written for them.
     */
    protected static function export_all_posts(int $userid, array $mappings) {
        global $DB;

        // Find all of the posts, and post subscriptions for this forum.
        list($foruminsql, $forumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    p.discussion AS id,
                    mof.id AS forumid,
                    d.name
                  FROM {moodleoverflow} mof
            INNER JOIN {moodleoverflow_discussions} d ON d.moodleoverflow = mof.id
            INNER JOIN {moodleoverflow_posts} p ON p.discussion = d.id
            LEFT JOIN {moodleoverflow_read} fr ON fr.postid = p.id
            LEFT JOIN {moodleoverflow_ratings} rat ON  rat.postid = p.id
                 WHERE mof.id ${foruminsql} AND
                (
                    p.userid = :postuserid OR
                    fr.userid = :readuserid OR 
                    rat.userid = :ratinguserid
                )
              GROUP BY mof.id, p.discussion, d.name
        ";
        $params = [
            'postuserid'   => $userid,
            'readuserid'   => $userid,
            'ratinguserid' => $userid,
        ];
        $params += $forumparams;

        $discussions = $DB->get_records_sql($sql, $params);
        foreach ($discussions as $discussion) {
            $context = \context::instance_by_id($mappings[$discussion->forumid]);
            static::export_all_posts_in_discussion($userid, $context, $discussion);
        }
    }

    /**
     * Store all information about all posts that we have detected this user to have access to.
     *
     * @param   int             $userid     The userid of the user whose data is to be exported.
     * @param   \context_module The         instance of the forum context.
     * @param   \stdClass       $discussion The discussion whose data is being exported.
     */
    protected static function export_all_posts_in_discussion(int $userid, \context $context, \stdClass $discussion) {
        global $DB, $USER;
        $discussionid = $discussion->id;
        // Find all of the posts, and post subscriptions for this forum.
        $sql = "SELECT
                    p.*,
                    d.moodleoverflow AS forumid,
                    fr.firstread,
                    fr.lastread,
                    fr.id AS readflag,
                    rat.userid AS hasratings
                    FROM {moodleoverflow_discussions} d
              INNER JOIN {moodleoverflow_posts} p ON p.discussion = d.id
              LEFT JOIN {moodleoverflow_read} fr ON fr.postid = p.id AND fr.userid = :readuserid
              LEFT JOIN {moodleoverflow_ratings} rat ON rat.id = (
                SELECT id FROM {moodleoverflow_ratings} ra
                WHERE ra.postid = p.id OR ra.userid = :ratinguserid
                LIMIT 1
              ) 
                   WHERE d.id = :discussionid
        ";
        $params = [
            'discussionid' => $discussionid,
            'readuserid'   => $userid,
            'ratinguserid' => $userid
        ];

        // Keep track of the forums which have data.
        $structure = (object) [
            'children' => [],
        ];
        $posts = $DB->get_records_sql($sql, $params);
        foreach ($posts as $post) {
            $post->hasdata = (isset($post->hasdata)) ? $post->hasdata : false;
            $post->hasdata = $post->hasdata || !empty($post->hasratings);
            $post->hasdata = $post->hasdata || $post->readflag;
            $post->hasdata = $post->hasdata || ($post->userid == $USER->id);

            if (0 == $post->parent) {
                $structure->children[$post->id] = $post;
            } else {
                if (empty($posts[$post->parent]->children)) {
                    $posts[$post->parent]->children = [];
                }
                $posts[$post->parent]->children[$post->id] = $post;
            }
            // Set all parents.
            if ($post->hasdata) {
                $curpost = $post;
                while ($curpost->parent != 0) {
                    $curpost = $posts[$curpost->parent];
                    $curpost->hasdata = true;
                }
            }
        }
        $discussionarea = static::get_discussion_area($discussion);
        $discussionarea[] = get_string('posts', 'mod_moodleoverflow');
        static::export_posts_in_structure($userid, $context, $discussionarea, $structure);
    }

    /**
     * Export all posts in the provided structure.
     *
     * @param   int             $userid     The userid of the user whose data is to be exported.
     * @param   \context_module The         instance of the forum context.
     * @param   array           $parentarea The subcontext fo the parent post.
     * @param   \stdClass       $structure  The post structure and all of its children
     */
    protected static function export_posts_in_structure(int $userid, \context $context, $parentarea, \stdClass $structure) {
        foreach ($structure->children as $post) {
            if (!$post->hasdata) {
                // This tree has no content belonging to the user. Skip it and all children.
                continue;
            }
            $postarea = array_merge($parentarea, static::get_post_area($post));
            // Store the post content.
            static::export_post_data($userid, $context, $postarea, $post);
            if (isset($post->children)) {
                // Now export children of this post.
                static::export_posts_in_structure($userid, $context, $postarea, $post);
            }
        }
    }

    /**
     * Export all data in the post.
     *
     * @param   int             $userid     The userid of the user whose data is to be exported.
     * @param   \context_module The         instance of the forum context.
     * @param   array           $parentarea The subcontext fo the parent post.
     * @param   \stdClass       $structure  The post structure and all of its children
     */
    protected static function export_post_data(int $userid, \context $context, $postarea, $post) {
        // Store related metadata.
        static::export_read_data($userid, $context, $postarea, $post);
        $postdata = (object) [
            'created'        => transform::datetime($post->created),
            'modified'       => transform::datetime($post->modified),
            'author_was_you' => transform::yesno($post->userid == $userid)
        ];
        $postdata->message = writer::with_context($context)
            ->rewrite_pluginfile_urls($postarea, 'mod_moodleoverflow', 'attachment', $post->id, $post->message);

        $postdata->message = format_text($postdata->message, $post->messageformat, (object) [
            'para'    => false,
            'context' => $context,
        ]);

        writer::with_context($context)
            // Store the post.
            ->export_data($postarea, $postdata)
            // Store the associated files.
            ->export_area_files($postarea, 'mod_moodleoverflow', 'attachment', $post->id);

        if ($post->userid == $userid) {
            // Store all ratings against this post as the post belongs to the user. All ratings on it are ratings of their content.
            $toexport = self::export_rating_data($post->id, false, $userid);
            writer::with_context($context)->export_related_data($postarea, 'rating', $toexport);
        }
        else {
            // Check for any ratings that the user has made on this post.
            $toexport = self::export_rating_data($post->id, true, $userid);
            writer::with_context($context)->export_related_data($postarea, 'rating', $toexport);
        }
    }

    protected static function export_rating_data($postid, $onlyuser, $userid) {
        global $DB;
        $rating = new ratings();

        $ratingpost = $rating->moodleoverflow_get_rating($postid);

        // Get the user rating.
        $sql = "SELECT id, firstrated, rating
                  FROM {moodleoverflow_ratings}
                 WHERE userid = $userid AND postid = $postid";
        $ownratings = $DB->get_records_sql($sql);
        $userratings = array();
        foreach($ownratings as $rating) {
            $userratings[] = (object) [
                'firstrated' => $rating->firstrated,
                'rating' => $rating->rating
            ];
        }

        if (!$onlyuser) {
            $ratingdata = [
                'downvotes'            => $ratingpost->downvotes,
                'upvotes'              => $ratingpost->upvotes,
                'was_rated_as_helpful' => $ratingpost->ishelpful,
                'was_rated_as_solved'  => $ratingpost->issolved
            ];
        }
        $ratingdata['your_rating'] = (object) $userratings;

        if (empty($ratingdata)) {
            return;
        }

        return (object) $ratingdata;
    }

    /**
     * Store data about whether the user subscribes to forum.
     *
     * @param   int       $userid The userid of the user whose data is to be exported.
     * @param   \stdClass $forum  The forum whose data is being exported.
     *
     * @return  bool        Whether any data was stored.
     */
    protected static function export_subscription_data(int $userid, \stdClass $forum) {
        if (null !== $forum->subscribed) {
            // The user is subscribed to this forum.
            writer::with_context(\context_module::instance($forum->cmid))
                ->export_metadata([], 'subscriptionpreference', 1, get_string('privacy:subscribedtoforum', 'mod_moodleoverflow'));

            return true;
        }

        return false;
    }

    /**
     * Store data about whether the user subscribes to this particular discussion.
     *
     * @param   int             $userid     The userid of the user whose data is to be exported.
     * @param   \context_module The         instance of the forum context.
     * @param   \stdClass       $discussion The discussion whose data is being exported.
     *
     * @return  bool        Whether any data was stored.
     */
    protected static function export_discussion_subscription_data(int $userid, \context_module $context, \stdClass $discussion) {
        $area = static::get_discussion_area($discussion);
        if (null !== $discussion->preference) {
            // The user hass a specific subscription preference for this discussion.
            $a = (object) [];
            switch ($discussion->preference) {
                case \mod_moodleoverflow\subscriptions::MOODLEOVERFLOW_DISCUSSION_UNSUBSCRIBED:
                    $a->preference = get_string('unsubscribed', 'mod_moodleoverflow');
                    break;
                default:
                    $a->preference = get_string('subscribed', 'mod_moodleoverflow');
                    break;
            }
            writer::with_context($context)
                ->export_metadata(
                    $area,
                    'subscriptionpreference',
                    $discussion->preference,
                    get_string('privacy:discussionsubscriptionpreference', 'mod_moodleoverflow', $a)
                );

            return true;
        }

        return true;
    }

    /**
     * Store forum read-tracking data about a particular forum.
     *
     * This is whether a forum has read-tracking enabled or not.
     *
     * @param   int       $userid The userid of the user whose data is to be exported.
     * @param   \stdClass $forum  The forum whose data is being exported.
     *
     * @return  bool        Whether any data was stored.
     */
    protected static function export_tracking_data(int $userid, \stdClass $forum) {
        if (null !== $forum->tracked) {
            // The user has a main preference to track all forums, but has opted out of this one.
            writer::with_context(\context_module::instance($forum->cmid))
                ->export_metadata([], 'trackreadpreference', 0, get_string('privacy:readtrackingdisabled', 'mod_moodleoverflow'));

            return true;
        }

        return false;
    }

    /**
     * Store read-tracking information about a particular forum post.
     *
     * @param   int             $userid The userid of the user whose data is to be exported.
     * @param   \context_module The     instance of the forum context.
     * @param   \stdClass       $post   The post whose data is being exported.
     *
     * @return  bool        Whether any data was stored.
     */
    protected static function export_read_data(int $userid, \context_module $context, array $postarea, \stdClass $post) {
        if (null !== $post->firstread) {
            $a = (object) [
                'firstread' => $post->firstread,
                'lastread'  => $post->lastread,
            ];
            writer::with_context($context)
                ->export_metadata(
                    $postarea,
                    'postread',
                    (object) [
                        'firstread' => $post->firstread,
                        'lastread'  => $post->lastread,
                    ],
                    get_string('privacy:postwasread', 'mod_moodleoverflow', $a)
                );

            return true;
        }

        return false;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        // Check that this is a context_module.
        if (!$context instanceof \context_module) {
            throw new \coding_exception('Unable to perform this deletion.');
        }

        // Get the course module.
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $forum = $DB->get_record('moodleoverflow', ['id' => $cm->instance]);

        $DB->delete_records('moodleoverflow_subscriptions', ['moodleoverflow' => $forum->id]);
        $DB->delete_records('moodloeverflow_read', ['moodleoverflowid' => $forum->id]);
        $DB->delete_records('moodleoverflow_tracking', ['moodleoverflowid' => $forum->id]);
        $DB->delete_records('moodleoverflow_ratings', ['moodleoverflowid' => $forum->id]);
        $DB->delete_records('moodleoverflow_discuss_subs', ['moodleoverflow' => $forum->id]);
        $DB->delete_records_select(
            'moodleoverflow_posts',
            "discussion IN (SELECT id FROM {moodleoverflow_discussions} WHERE moodleoverflow = :forum)",
            [
                'forum' => $forum->id,
            ]
        );
        $DB->delete_records('moodleoverflow_discussions', ['moodleoverflow' => $forum->id]);

        // Delete all files from the posts.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_moodleoverflow', 'attachment');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            // Get the course module
            $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
            $forum = $DB->get_record('moodleoverflow', ['id' => $cm->instance]);

            $DB->delete_records('moodleoverflow_read', [
                'moodleoverflowid' => $forum->id,
                'userid'           => $userid]);

            $DB->delete_records('moodleoverflow_subscriptions', [
                'moodleoverflow' => $forum->id,
                'userid'         => $userid]);

            $DB->delete_records('moodleoverflow_discuss_subs', [
                'moodleoverflow' => $forum->id,
                'userid'         => $userid]);

            $DB->delete_records('moodleoverflow_tracking', [
                'moodleoverflowid' => $forum->id,
                'userid'           => $userid]);

            // Do not delete ratings but reset userid.
            $ratingsql = "userid = :userid AND discussionid IN (SELECT id FROM {moodleoverflow_discussions} WHERE moodleoverflow = :forum)";
            $ratingparams = [
                'forum'  => $forum->id,
                'userid' => $userid
            ];
            $DB->set_field_select('moodleoverflow_ratings', 'userid', 0, $ratingsql, $ratingparams);

            // Do not delete forum posts.
            // Update the user id to reflect that the content has been deleted.
            $postsql = "userid = :userid AND discussion IN (SELECT id FROM {moodleoverflow_discussions} WHERE moodleoverflow = :forum)";
            $postparams = [
                'forum'  => $forum->id,
                'userid' => $userid
            ];

            $DB->set_field_select('moodleoverflow_posts', 'message', '', $postsql, $postparams);
            $DB->set_field_select('moodleoverflow_posts', 'messageformat', FORMAT_PLAIN, $postsql, $postparams);
            $DB->set_field_select('moodleoverflow_posts', 'userid', 0, $postsql, $postparams);

            // Do not delete discussions but reset userid.
            $discussionselect = "moodleoverflow = :forum AND userid = :userid";
            $disuccsionsparams = ['forum' => $forum->id, 'userid' => $userid];
            $DB->set_field_select('moodleoverflow_discussions', 'name', '', $discussionselect, $disuccsionsparams);
            $DB->set_field_select('moodleoverflow_discussions', 'userid', 0, $discussionselect, $disuccsionsparams);
            $discussionselect = "moodleoverflow = :forum AND usermodified = :userid";
            $disuccsionsparams = ['forum' => $forum->id, 'userid' => $userid];
            $DB->set_field_select('moodleoverflow_discussions', 'usermodified', 0, $discussionselect, $disuccsionsparams);

            // Delete attachments
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_moodleoverflow', 'attachment');

        }
    }
}