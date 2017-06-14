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

// TODO: Use this?
// use core\event\user_loggedin;

defined('MOODLE_INTERNAL') || die();


/**
 * Static methods for managing the tracking of read posts and discussions.
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class readtracking {

    /**
     * Determine if a user can track moodleoverflows and optionally a particular moodleoverflow instance.
     * Checks the site settings and the moodleoverflow settings (if requested).
     *
     * @param object $moodleoverflow
     * @return boolean
     */
    public static function moodleoverflow_can_track_moodleoverflows($moodleoverflow = null) {
        global $USER, $CFG;

        // Check if readtracking is disabled for the module.
        if (empty($CFG->moodleoverflow_trackreadposts)) {
            return false;
        }

        // Guests are not allowed to track moodleoverflows.
        if (isguestuser($USER) OR empty($USER->id)) {
            return false;
        }

        // If no specific moodleoverflow is submitted, check the modules basic settings.
        if (is_null($moodleoverflow)) {
            return (bool)$CFG->moodleoverflow_allowforcedreadtracking;
        }

        // Check the settings of the moodleoverflow instance.
        $allowed = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_OPTIONAL);
        $forced  = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_FORCED);

        // Return a boolean whether read tracking is allowed/forced.
        return ($allowed || $forced);
    }

    /**
     * Tells whether a specific moodleoverflow is tracked by the user.
     *
     * @param object $moodleoverflow
     * @return bool
     */
    public static function moodleoverflow_is_tracked($moodleoverflow) {
        global $USER, $CFG, $DB;

        // Guests cannot track a moodleoverflow.
        if (isguestuser($USER) OR empty($USER->id)) {
            return false;
        }

        // Check if the moodleoverflow can be generally tracked.
        if (!self::moodleoverflow_can_track_moodleoverflows($moodleoverflow)) {
            return false;
        }

        // Check the settings of the moodleoverflow instance.
        $allowed = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_OPTIONAL);
        $forced  = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_FORCED);
        $userpreference = $DB->get_record('moodleoverflow_subscriptions',
            array('userid' => $USER->id, 'moodleoverflow' => $moodleoverflow->id));

        // Return the boolean.
        if ($CFG->moodleoverflow_allowforcedreadtracking) {
            return ($forced || ($allowed && $userpreference !== false));
        } else {
            return (($allowed || $forced) && $userpreference !== false);
        }
    }

    /**
     * Marks a specific moodleoverflow instance as read by a specific user.
     *
     * @param $moodleoverflowid
     * @param $courseid
     * @param null $userid
     */
    public static function moodleoverflow_mark_moodleoverflow_read($cm, $userid = null) {
        global $USER;

        // If no user is submitted, use the current one.
        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // Get all the discussions with unread messages in this moodleoverflow instance.
        $discussions = moodleoverflow_get_discussions_unread($cm);

        // Iterate through all of this discussions.
        foreach ($discussions as $discussionid => $amount) {

            // Mark the discussion as read.
            if (!self::moodleoverflow_mark_discussion_read($discussionid, $userid)) {
                print_error('markreadfailed', 'moodleoverflow');
                return false;
            }
        }

        return true;
    }

    /**
     * Marks a specific discussion as read by a specific user.
     *
     * @param $discussionid
     * @param int $view
     * @param null $userid
     */
    public static function moodleoverflow_mark_discussion_read($discussionid, $userid = null) {
        global $USER;

        // Get all posts.
        $posts = moodleoverflow_get_all_discussion_posts($discussionid, true);

        // If no user is submitted, use the current one.
        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // Iterate through all posts of the discussion.
        foreach ($posts as $post) {

            // Ignore already read posts.
            if (!is_null($post->postread)) {
                continue;
            }

            // Mark the post as read.
            if (!self::moodleoverflow_mark_post_read($userid, $post)) {
                print_error('markreadfailed', 'moodleoverflow');
                return false;
            }
        }

        // Else return true.
        return true;
    }

    /**
     * Marks a specific post as read by a specific user.
     *
     * @param $userid
     * @param $post
     * @return bool
     */
    public static function moodleoverflow_mark_post_read($userid, $post) {

        // If the post is older than the limit.
        if (self::moodleoverflow_is_old_post($post)) {
            return true;
        }

        // Create a new read record.
        return self::moodleoverflow_add_read_record($userid, $post->id);
    }

    /**
     * Checks if a post is older than the limit.
     *
     * @param $post
     * @return bool
     */
    public static function moodleoverflow_is_old_post($post) {
        global $CFG;

        // Transform objects into arrays.
        $post = (array) $post;

        // Get the current time.
        $currenttimestamp = time();

        // Calculate the time, where older posts are considered read.
        $oldposttimestamp = $currenttimestamp - ($CFG->moodleoverflow_oldpostdays * 24 * 3600);

        // Return if the post is newer than that time.
        return ($post['modified'] < $oldposttimestamp);
    }

    /**
     * Mark a post as read by a user.
     *
     * @param $userid
     * @param $postid
     * @return bool
     */
    public static function moodleoverflow_add_read_record($userid, $postid) {
        global $CFG, $DB;

        // Get the current time and the cutoffdate.
        $now = time();
        $cutoffdate = $now - ($CFG->moodleoverflow_oldpostdays * 24 * 3600);

        // Check for read records for this user an this post.
        if (! $oldrecord = $DB->get_record('moodleoverflow_read', array('postid' => $postid, 'userid' => $userid))) {

            // If there are no old records, create a new one.
            $sql = "INSERT INTO {moodleoverflow_read} (userid, postid, discussionid, moodleoverflowid, firstread, lastread)
                 SELECT ?, p.id, p.discussion, d.moodleoverflow, ?, ?
                   FROM {moodleoverflow_posts} p
                        JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                  WHERE p.id = ? AND p.modified >= ?";
            return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));
        }

        // Else update the existing one.
        $sql = "UPDATE {moodleoverflow_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }

    /**
     * Deletes read record for the specified index.
     * At least one parameter must be specified.
     *
     * @param int $userid
     * @param int $postid
     * @param int $discussionid
     * @param int $moodleoverflowid
     * @return bool
     */
    public static function moodleoverflow_delete_read_records($userid = -1, $postid = -1, $discussionid = -1, $overflowid = -1) {
        global $DB;

        // Initiate variables.
        $params = array();
        $select = '';

        // Create the sql-Statement depending on the submitted parameters.
        if ($userid > -1) {
            if ($select != '') $select .= ' AND ';
            $select .= 'userid = ?';
            $params[] = $userid;
        }
        if ($postid > -1) {
            if ($select != '') $select .= ' AND ';
            $select .= 'postid = ?';
            $params[] = $postid;
        }
        if ($discussionid > -1) {
            if ($select != '') $select .= ' AND ';
            $select .= 'discussionid = ?';
            $params[] = $discussionid;
        }
        if ($overflowid > -1) {
            if ($select != '') $select .= ' AND ';
            $select .= 'moodleoverflowid = ?';
            $params[] = $overflowid;
        }

        // Check if at least one parameter was specified.
        if ($select == '') {
            return false;
        } else {
            return $DB->delete_records_select('moodleoverflow_read', $select, $params);
        }
    }
}