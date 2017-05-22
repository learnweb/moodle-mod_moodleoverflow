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
use core\event\user_loggedin;

defined('MOODLE_INTERNAL') || die();


/**
 * Static methods for managing the ratings of posts.
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ratings {

    //
    // 0 = neutral 1 = negative 2 = positive 3 = solved student 4 = solved teacher
    //

    // TODO: Can rate.

    // Check if a discussion is maked solved.
    public static function moodleoverflow_discussion_is_solved($discussionid, $teacher = false) {
        global $DB;

        // Is the teachers solved-status requested?
        if ($teacher) {

            // Check if a teacher marked a solution as correct.
            return $DB->record_exists('moodleoverflow_ratings', array('discussionid' => $discussionid, 'rating' => 4));
        }

        // Check if the topic starter marked a solution as correct.
        return $DB->record_exists('moodleoverflow_ratings', array('discussionid' => $discussionid, 'rating' => 3));
    }

    // Get the ratings of all posts in a discussion.
    public static function moodleoverflow_get_ratings_by_discussion($discussionid, $postid = null) {
        global $DB;

        // Get the amount of votes.
        $sql = "SELECT id as postid,
                       (SELECT COUNT(rating) FROM mdl_moodleoverflow_ratings WHERE postid=p.id AND rating = 1) AS downvotes,
	                   (SELECT COUNT(rating) FROM mdl_moodleoverflow_ratings WHERE postid=p.id AND rating = 2) AS upvotes,
                       (SELECT COUNT(rating) FROM mdl_moodleoverflow_ratings WHERE postid=p.id AND rating = 3) AS issolvedstarter,
                       (SELECT COUNT(rating) FROM mdl_moodleoverflow_ratings WHERE postid=p.id AND rating = 4) AS issolvedteacher
                  FROM mdl_moodleoverflow_posts p
                 WHERE p.discussion = $discussionid
              GROUP BY p.id";
        $votes = $DB->get_records_sql($sql);

        // A single post is requested.
        if ($postid) {
            
            // Check if the post is part of the discussion.
            if (array_key_exists($postid, $votes)) {
                return $votes[$postid];
            }

            // The requested post is not part of the discussion.
            print_error('postnotpartofdiscussion', 'moodleoverflow');
        }
        
        // Return the array.
        return $votes;
    }

    // Get the ratings of a single post.
    // This method is using 'moodleoverflow_get_ratings_by_discussion()'.
    public static function moodleoverflow_get_rating($postid) {
        global $DB;

        // Retrieve the full post.
        if (! $post = $DB->get_record('moodleoverflow_posts', array('id' => $postid))) {
            print_error('postnotexist', 'moodleoverflow');
        }

        // Get the rating for this single post.
        return self::moodleoverflow_get_ratings_by_discussion($post->discussion, $postid);
    }

    // Did the current user voted?
    public static function moodleoverflow_user_rating($postid, $userid = null) {
        global $DB, $USER;

        // Is a user submitted?
        if (!$userid) {
            $userid = $USER->id;
        }

        // Get the rating.
        $sql = "SELECT rating 
                  FROM {moodleoverflow_ratings}
                 WHERE userid = $userid AND postid = $postid AND (rating = 1 OR rating = 2)
                 LIMIT 1";
        return ($DB->get_record_sql($sql));
    }

    // Add a vote.
    public static function moodleoverflow_add_rating($moodleoverflow, $postid, $rating, $cm, $userid = null) {
        global $DB, $USER, $PAGE, $OUTPUT, $SESSION, $CFG;

        // Has a user been submitted?
        if (!$userid) {
            $userid = $USER->id;
        }

        // Is the rating correct?
        if ($rating != 1 AND $rating != 2) {
            print_error('invalidratingid', 'moodleoverflow');
        }

        // Get the related discussion.
        if (! $post = $DB->get_record('moodleoverflow_posts', array('id' => $postid))) {
            print_error('invalidparentpostid', 'moodleoverflow');
        }

        // Get the related course.
        if (! $course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
            print_error('invalidcourseid');
        }

        // Retrieve the contexts.
        $modulecontext = \context_module::instance($cm->id);
        $coursecontext = \context_course::instance($course->id);

        // Check if the user has the capabilities to rate a post.
        if (! self::moodleoverflow_user_can_rate($moodleoverflow, $cm, $modulecontext)) {

            // Catch unenrolled users.
            if (!isguestuser() AND !is_enrolled($coursecontext)) {
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array(
                        'id' => $course->id,
                        'returnurl' => '/mod/moodleoverflow/view.php?m' . $moodleoverflow->id
                )), get_string('youneedtoenrol'));
            }

            // Notify the user, that he can not post a new discussion.
            print_error('noratemoodleoverflow', 'moodleoverflow');
        }

        // Get all the existing users ratings.
        $sql = "SELECT *
                  FROM {moodleoverflow_ratings}
                 WHERE userid = $userid AND postid = $postid AND (rating = 1 OR rating = 2)
                 LIMIT 1";
        $votes = $DB->get_record_sql($sql);

        // Did the user already voted for this post?
        if ($votes) {

            // Is the user allowed to change its vote?
            if ($CFG->moodleoverflow_allowratingchange) {

                // Update the existing record.
                return self::moodleoverflow_update_rating_record($postid, $rating, $userid, $votes->id);
            } else {

                // Print the error message.
                print_error('noratingchangeallowed', 'moodleoverflow');
            }
        } else {

            // Add a new rating record.
            return self::moodleoverflow_add_rating_record($moodleoverflow->id, $post->discussion, $postid, $rating, $userid);
        }
    }

    // Check if a user can rate posts.
    public static function moodleoverflow_user_can_rate($moodleoverflow, $cm = null, $modulecontext = null) {

        // Guests and non-logged-in users can not rate.
        if (isguestuser() OR !isloggedin()) {
            return false;
        }

        // Retrieve the coursemodule.
        if (!$cm) {
            if (! $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
                pint_error('invalidcoursemodule');
            }
        }

        // Get the context if not set in the parameters.
        if (!$modulecontext) {
            $modulecontext = context_module::instance($cm->id);
        }

        // Check the capability.
        if (has_capability('mod/moodleoverflow:ratepost', $modulecontext)) {
            return true;
        } else {
            return false;
        }
    }

    // Update an existing rating record.
    public static function moodleoverflow_update_rating_record($postid, $rating, $userid, $ratingid) {
        global $DB;

        // Update the record.
        $sql = "UPDATE {moodleoverflow_ratings}
                   SET rating = ?, lastchanged = ?
                 WHERE userid = ? AND postid = ? AND id = ?";
        return $DB->execute($sql, array($rating, time(), $userid, $postid, $ratingid));
    }

    // Add a new rating record.
    public static function moodleoverflow_add_rating_record($moodleoverflowid, $discussionid, $postid, $rating, $userid) {
        global $DB;

        // Create the rating record.
        $record = new \stdClass();
        $record->userid = $userid;
        $record->postid = $postid;
        $record->discussionid = $discussionid;
        $record->moodleoverflowid = $moodleoverflowid;
        $record->rating = $rating;
        $record->firstrated = time();
        $record->lastchanged = time();

        // Add the record to the database.
        return $DB->insert_record('moodleoverflow_ratings', $record);
    }

}