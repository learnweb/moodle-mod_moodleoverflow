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
    // 0 = neutral
    // 1 = negative 2 = positive 3 = solved student 4 = correct teacher
    // 30 = remove solved  40 = remove correct
    //

    // Check if a discussion is maked solved.
    public static function moodleoverflow_discussion_is_solved($discussionid, $teacher = false) {
        global $DB;

        // Is the teachers solved-status requested?
        if ($teacher) {

            // Check if a teacher marked a solution as correct.
            if ($DB->record_exists('moodleoverflow_ratings', array('discussionid' => $discussionid, 'rating' => 4))) {

                // Return the rating record.
                return $DB->get_record('moodleoverflow_ratings', array('discussionid' => $discussionid, 'rating' => 4));
            }

            // The teacher has not marked the discussion as correct.
            return false;
        }

        // Check if the topic starter marked a solution as correct.
        if ($DB->record_exists('moodleoverflow_ratings', array('discussionid' => $discussionid, 'rating' => 3))) {

            // Return the rating record.
            return $DB->get_record('moodleoverflow_ratings', array('discussionid' => $discussionid, 'rating' => 3));
        }

        // The topic starter has not marked a solution as correct.
        return false;
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

    // Did the current user rated the post?
    public static function moodleoverflow_user_rated($postid, $userid = null) {
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

    // Add a rating.
    // This is the basic function to add or edit ratings.
    public static function moodleoverflow_add_rating($moodleoverflow, $postid, $rating, $cm, $userid = null) {
        global $DB, $USER, $PAGE, $OUTPUT, $SESSION, $CFG;

        // Has a user been submitted?
        if (!$userid) {
            $userid = $USER->id;
        }

        // Is the submitted rating valid?
        $possibleratings = array(0, 1, 2, 3, 4, 30, 40);
        if (!in_array($rating, $possibleratings)) {
            print_error('invalidratingid', 'moodleoverflow');
        }

        // Get the related discussion.
        if (! $post = $DB->get_record('moodleoverflow_posts', array('id' => $postid))) {
            print_error('invalidparentpostid', 'moodleoverflow');
        }

        if (! $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $post->discussion))) {
            print_error('notpartofdiscussion', 'moodleoverflow');
        }

        // Get the related course.
        if (! $course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
            print_error('invalidcourseid');
        }

        // Retrieve the contexts.
        $modulecontext = \context_module::instance($cm->id);
        $coursecontext = \context_course::instance($course->id);

        // Are we handling an extended rating?
        $extendedratings = array(3, 4, 30, 40);
        if (in_array($rating, $extendedratings)) {
            return self::moodleoverflow_mark_post($moodleoverflow, $discussion, $post, $rating, $modulecontext, $userid);
        }

        // Check if the user has the capabilities to rate a post.
        if (! $canrate = self::moodleoverflow_user_can_rate($moodleoverflow, $cm, $modulecontext)) {

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

    // Mark an answer as solved or correct.
    // This function is called from moodleoverflow_add_rating. It helps handling the extended functionalities.
    public static function moodleoverflow_mark_post($moodleoverflow, $discussion, $post, $rating, $modulecontext, $userid = null) {
        global $USER, $DB;

        // Is a user submitted?
        if (!$userid) {
            $userid = $USER->id;
        }

        // Check the capabilities depending on the input and create a pseudo rating.
        if ($rating == 3 OR $rating == 30) {
            // We are handling the rating of the user who started the discussion.

            // Check if the current user is the startuser.
            if ($userid != $discussion->userid) {
                print_error('notstartuser', 'moodleoverflow');
            }

            // Create a pseudo rating.
            $pseudorating = 3;

        } elseif ($rating == 4 OR $rating == 40) {
            // We are handling the rating of a teacher.

            // Check if the current user has the capabilities to do this.
            if (!has_capability('mod/moodleoverflow:ratesolved', $modulecontext)) {
                print_error('notteacher', 'moodleoverflow');
            }

            // Create a pseudo rating.
            $pseudorating = 4;

        } else {
            // If none of the cases above was triggered, something went wrong.
            print_error('invalidratingid', 'moodleoverflow');
        }

        // Check for older rating.
        $sql = "SELECT *
                  FROM {moodleoverflow_ratings}
                 WHERE discussionid = $discussion->id AND rating = $pseudorating
                 LIMIT 1";
        $oldrating = $DB->get_record_sql($sql);

        // Do we want to delete the rating?
        $deleterecord = ($pseudorating * 10 == $rating);
        if ($deleterecord) {
            return $DB->delete_records('moodleoverflow_ratings', array('id' => $oldrating->id));
        }

        // Insert the record if we do not need to update an older rating.
        if (!$oldrating) {
            return self::moodleoverflow_add_rating_record($moodleoverflow->id, $discussion->id, $post->id, $rating, $userid);
        }

        // Else we need to update an existing rating.
        return self::moodleoverflow_update_rating_record($post->id, $rating, $userid, $oldrating->id);
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
    // This function is called only from functions in which the submitted variables are verified.
    public static function moodleoverflow_update_rating_record($postid, $rating, $userid, $ratingid) {
        global $DB;

        // Update the record.
        $sql = "UPDATE {moodleoverflow_ratings}
                   SET postid = ?, userid = ?, rating=?, lastchanged = ?
                 WHERE id = ?";
        return $DB->execute($sql, array($postid, $userid, $rating, time(), $ratingid));
    }

    // Add a new rating record.
    // This function is called only from functions in which the submitted variables are verified.
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

    public static function moodleoverflow_sort_answers_by_ratings($posts) {
        global $CFG;

        // Create copies to manipulate.
        $parentcopy = $posts;
        $postscopy = $posts;

        // Create an array with all the keys of the older array.
        $oldorder = array();
        foreach ($postscopy as $postid => $post) {
            $oldorder[] = $postid;
        }

        // Create an array for the new order.
        $neworder = array();

        // The parent post stays the parent post.
        $parent = array_shift($parentcopy);
        unset($postscopy[$parent->id]);
        $discussionid = $parent->discussion;
        $neworder[] = (int) $parent->id;

        // Check if answers has been rated as correct.
        $statusstarter = self::moodleoverflow_discussion_is_solved($discussionid, false);
        $statusteacher = self::moodleoverflow_discussion_is_solved($discussionid, true);

        // The answer that is marked as correct by both is displayed first.
        if ($statusteacher AND $statusstarter) {

            // Is the same answer correct for both?
            if ($statusstarter->postid == $statusteacher->postid) {

                // Add the post to the new order and delete it from the posts array.
                $neworder[] = (int) $statusstarter->postid;
                unset($postscopy[$statusstarter->postid]);

                // Unset the stati to skip the following if-statements.
                $statusstarter = false;
                $statusteacher = false;
            }
        }

        // If the answers the teacher marks are preferred, and only
        // the teacher marked an answer as correct, display it first.
        if ($CFG->moodleoverflow_preferteachersmark AND $statusteacher) {

            // Add the post to the new order and delete it from the posts array.
            $neworder[] = (int) $statusteacher->postid;
            unset($postscopy[$statusteacher->postid]);

            // Unset the status to skip the following if-statements.
            $statusteacher = false;
        }

        // If the user who started the discussion has marked
        // an answer as correct, display this answer first.
        if ($statusstarter) {

            // Add the post to the new order and delete it from the posts array.
            $neworder[] = (int) $statusstarter->postid;
            unset($postscopy[$statusstarter->postid]);
        }

        // If a teacher has marked an answer as correct, display it next.
        if ($statusteacher) {

            // Add the post to the new order and delete it from the posts array.
            $neworder[] = (int) $statusteacher->postid;
            unset($postscopy[$statusteacher->postid]);
        }

        // All answers that are not marked as correct by someone should now be left.

        // Search for all comments.
        foreach ($postscopy as $postid => $post) {

            // Add all comments to the order.
            // They are independant from the votes.
            if ($post->parent != $parent->id) {
                $neworder[] = $postid;
                unset($postscopy[$postid]);
            }
        }

        // Sort the remaining answers by their total votes.
        $votesarray = array();
        foreach ($postscopy as $postid => $post) {
            $votesarray[$post->id] = $post->upvotes - $post->downvotes;
        }
        arsort($votesarray);

        // Add the remaining messages to the new order.
        foreach ($votesarray as $postid => $votes) {
            $neworder[] = $postid;
        }

        // The new order is determined.
        // It has to be applied now.
        $sortedposts = array();
        foreach($neworder as $k) {
            $sortedposts[$k] = $posts[$k];
        }

        // Return the sorted posts.
        return $sortedposts;
    }
}