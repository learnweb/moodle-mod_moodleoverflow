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
 * Class for working with posts
 *
 * @package     mod_moodleoverflow
 * @copyright   2023 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow\discussion;


// Import namespace from the locallib, needs a check later which namespaces are really needed.
use mod_moodleoverflow\anonymous;

// Important namespaces.
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\review;
use mod_moodleoverflow\post\post;
use mod_moodleoverflow\capabilities;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class that represents a discussion. A discussion administrates the posts and has one parent post, that started the discussion.
 *
 * @package     mod_moodleoverflow
 * @copyright   2023 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Please be careful with functions that delete, add or edit posts and discussions.
 * Security checks for these functions were done in the post_control class and these functions should only be accessed that way.
 * Accessing these functions directly without the checks from the post control could lead to serious errors.
 */
class discussion {

    /** @var int The discussion ID */
    private $id;

    /** @var int The course ID where the discussion is located */
    private $course;

    /** @var int The moodleoverflow ID where the discussion is located*/
    private $moodleoverflow;

    /** @var string The title of the discussion, the titel of the parent post*/
    public $name;

    /** @var int The id of the parent/first post*/
    private $firstpost;

    /** @var int The user ID who started the discussion */
    private $userid;

    /** @var int Unix-timestamp of modification */
    public $timemodified;

    /** @var int Unix-timestamp of discussion creation */
    public $timestart;

    /** @var int the user ID who modified the discussion */
    public $usermodified;

    // Not Database-related attributes.

    /** @var array an Array of posts that belong to this discussion */
    public $posts;

    /** @var bool  a variable for checking if this instance has all its posts */
    public $postsbuild;

    /** @var object The moodleoverflow object where the discussion is located */
    public $moodleoverflowobject;

    /** @var object The course module object */
    public $cmobject;

    // Constructors and other builders.

    /**
     * Constructor to build a new discussion.
     * @param int   $id                 The Discussion ID.
     * @param int   $course             The course ID.
     * @param int   $moodleoverflow     The moodleoverflow ID.
     * @param char  $name               Discussion Title.
     * @param int   $firstpost          .
     * @param int   $userid  The course ID.
     * @param int   $timemodified   The course ID.
     * @param int   $timestart   The course ID.
     * @param int   $usermodified   The course ID.
     */
    public function __construct($id, $course, $moodleoverflow, $name, $firstpost,
                                $userid, $timemodified, $timestart, $usermodified) {
        $this->id = $id;
        $this->course = $course;
        $this->moodleoverflow = $moodleoverflow;
        $this->name = $name;
        $this->firstpost = $firstpost;
        $this->userid = $userid;
        $this->timemodified = $timemodified;
        $this->timestart = $timestart;
        $this->usermodified = $usermodified;
        $this->posts = [];
        $this->postsbuild = false;
    }

    /**
     * Builds a Discussion from a DB record.
     *
     * @param object   $record Data object.
     * @return object discussion instance
     */
    public static function from_record($record) {
        $id = null;
        if (object_property_exists($record, 'id') && $record->id) {
            $id = $record->id;
        }

        $course = 0;
        if (object_property_exists($record, 'course') && $record->course) {
            $course = $record->course;
        }

        $moodleoverflow = 0;
        if (object_property_exists($record, 'moodleoverflow') && $record->moodleoverflow) {
            $moodleoverflow = $record->moodleoverflow;
        }

        $name = '';
        if (object_property_exists($record, 'name') && $record->name) {
            $name = $record->name;
        }

        $firstpost = 0;
        if (object_property_exists($record, 'firstpost') && $record->firstpost) {
            $firstpost = $record->firstpost;
        }

        $userid = 0;
        if (object_property_exists($record, 'userid') && $record->userid) {
            $userid = $record->userid;
        }

        $timemodified = 0;
        if (object_property_exists($record, 'timemodified') && $record->timemodified) {
            $timemodified = $record->timemodified;
        }

        $timestart = 0;
        if (object_property_exists($record, 'timestart') && $record->timestart) {
            $timestart = $record->timestart;
        }

        $usermodified = 0;
        if (object_property_exists($record, 'usermodified') && $record->usermodified) {
            $usermodified = $record->usermodified;
        }

        $instance = new self($id, $course, $moodleoverflow, $name, $firstpost, $userid, $timemodified, $timestart, $usermodified);

        // Get all the posts so that the instance can work with it.
        $instance->moodleoverflow_get_discussion_posts();

        return $instance;
    }

    /**
     * Function to build a new discussion without specifying the Discussion ID.
     * @param int   $course             The course ID.
     * @param int   $moodleoverflow     The moodleoverflow ID.
     * @param char  $name               Discussion Title.
     * @param int   $firstpost          .
     * @param int   $userid  The course ID.
     * @param int   $timemodified   The course ID.
     * @param int   $timestart   The course ID.
     * @param int   $usermodified   The course ID.
     *
     * @return object discussion object without id.
     */
    public static function construct_without_id($course, $moodleoverflow, $name, $firstpost,
                                       $userid, $timemodified, $timestart, $usermodified) {
        $id = null;
        $instance = new self($id, $course, $moodleoverflow, $name, $firstpost, $userid, $timemodified, $timestart, $usermodified);
        return $instance;
    }

    // Discussion Functions.

    /**
     * Adds a new Discussion with a post.
     *
     * @param object $prepost The prepost object from the post_control. Has information about the post and other important stuff.
     */
    public function moodleoverflow_add_discussion($prepost) {
        global $DB;

        // Add the discussion to the Database.
        $this->id = $DB->insert_record('moodleoverflow_discussions', $this->build_db_object());

        // Create the first/parent post for the new discussion and add it do the DB.
        $post = post::construct_without_id($this->id, 0, $prepost->userid, $prepost->timenow, $prepost->timenow, $prepost->message,
                                           $prepost->messageformat, "", 0, $prepost->reviewed, null, $prepost->formattachments);
        // Add it to the DB and save the id of the first/parent post.
        $this->firstpost = $post->moodleoverflow_add_new_post();

        // Save the id of the first/parent post in the DB.
        $DB->set_field('moodleoverflow_discussions', 'firstpost', $this->firstpost, ['id' => $this->id]);

        // Add the parent post to the $posts array.
        $this->posts[$this->firstpost] = $post;
        $this->postsbuild = true;

        // Trigger event.
        $params = [
            'context' => $prepost->modulecontext,
            'objectid' => $this->id,
        ];
        // LEARNWEB-TODO: check if the event functions.
        $event = \mod_moodleoverflow\event\discussion_viewed::create($params);
        $event->trigger();

        // Return the id of the discussion.
        return $this->id;
    }

    /**
     * Delete a discussion with all of it's posts
     * @param object $prepost Information about the post from the post_control
     * @return bool Wether deletion was successful of not
     */
    public function moodleoverflow_delete_discussion($prepost) {
        global $DB;
        $this->existence_check();
        $this->posts_check();

        // Delete a discussion with all of it's posts.
        // In case something does not work we throw the error as it should be known that something went ... terribly wrong.
        // All DB transactions are rolled back.
        try {
            $transaction = $DB->start_delegated_transaction();

            // Delete every post of this discussion.
            foreach ($this->posts as $post) {
                $post->moodleoverflow_delete_post(false);
            }

            // Delete the read-records for the discussion.
            readtracking::moodleoverflow_delete_read_records(-1, -1, $this->id);

            // Remove the subscriptions for the discussion.
            $DB->delete_records('moodleoverflow_discuss_subs', ['discussion' => $this->id]);

            // Delete the discussion from the database.
            $DB->delete_records('moodleoverflow_discussions', ['id' => $this->id]);

            // Trigger the discussion deleted event.
            $params = [
                'objectid' => $this->id,
                'context' => $prepost->modulecontext,
            ];

            $event = \mod_moodleoverflow\event\discussion_deleted::create($params);
            $event->trigger();

            // Set the id of this instance to null, so that working with it is not possible anymore.
            $this->id = null;

            // The discussion has been deleted.
            $transaction->allow_commit();
            return true;

        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        // Deleting the discussion has failed.
        return false;
    }

    /**
     * Adds a new post to this discussion and the DB.
     *
     * @param object $prepost The prepost object from the post_control. Has Information about the post and other important stuff.
     */
    public function moodleoverflow_add_post_to_discussion($prepost) {
        global $DB;
        $this->existence_check();
        $this->posts_check();

        // Create the post that will be added to the new discussion.
        $post = post::construct_without_id($this->id, $prepost->parentid, $prepost->userid, $prepost->timenow, $prepost->timenow,
                                           $prepost->message, $prepost->messageformat, "", 0, $prepost->reviewed, null,
                                           $prepost->formattachments);
        // Add the post to the DB.
        $postid = $post->moodleoverflow_add_new_post();

        // Add the post to the $posts array and update the timemodified in the DB.
        $this->posts[$postid] = $post;
        $this->timemodified = $prepost->timenow;
        $this->usermodified = $prepost->userid;
        $DB->update_record('moodleoverflow_discussions', $this->build_db_object());

        // Return the id of the added post.
        return $postid;
    }

    /**
     * Deletes a post that is in this discussion from the DB.
     * @param object $prepost The prepost object from the post_control. Has Information about the post and other important stuff.
     * @return bool Wether the deletion was possible
     * @throws moodle_exception if post is not in this discussion or something failed.
     */
    public function moodleoverflow_delete_post_from_discussion($prepost) {
        $this->existence_check();
        $this->posts_check();

        // Check if the posts exists in this discussion.
        $this->post_exists_check($prepost->postid);

        // Access the post and delete it.
        $post = $this->posts[$prepost->postid];
        if (!$post->moodleoverflow_delete_post($prepost->deletechildren)) {
            // Deletion failed.
            return false;
        }

        // Check for the new last post of the discussion.
        $this->moodleoverflow_discussion_adapt_to_last_post();

        // Delete the post from the post array.
        unset($this->posts[$prepost->postid]);

        return true;
    }

    /**
     * Edits the message of a post from this discussion.
     * @param object $prepost The prepost object from the post_control. Has Information about the post and other important stuff.
     */
    public function moodleoverflow_edit_post_from_discussion($prepost) {
        global $DB;
        $this->existence_check();
        $this->posts_check();

        // Check if the posts exists in this discussion.
        $this->post_exists_check($prepost->postid);

        // Access the post.
        $post = $this->posts[$prepost->postid];

        // If the post is the firstpost, then update the name of this discussion and the post. If not, only update the post.
        if ($prepost->postid == array_key_first($this->posts)) {
            $this->name = $prepost->subject;
            $this->usermodified = $prepost->userid;
            $this->timemodified = $prepost->timenow;
            $DB->update_record('moodleoverflow_discussions', $this->build_db_object());
        }
        $post->moodleoverflow_edit_post($prepost->timenow, $prepost->message, $prepost->messageformat, $prepost->formattachments);

        // The post has been edited successfully.
        return true;
    }

    /**
     * This Function checks, what the last added or edited post is. If it changed by a delete function,
     * the timemodified and the usermodified need to be adapted to the last added or edited post.
     *
     * @return bool true if the DB needed to be adapted. false if it didn't change.
     */
    public function moodleoverflow_discussion_adapt_to_last_post() {
        global $DB;
        $this->existence_check();

        // Find the last reviewed post of the discussion (even if the user has review capability, because it's written to DB).
        $sql = 'SELECT *
                FROM {moodleoverflow_posts}
                WHERE discussion = ' . $this->id .
                  ' AND reviewed = 1
                    AND modified = (SELECT MAX(modified) as modified
                                    FROM {moodleoverflow_posts}
                                    WHERE discussion = ' . $this->id . ');';
        $record = $DB->get_record_sql($sql);
        $lastpost = post::from_record($record);

        // Check if the last post changed. If it changed, then update the DB-record of this discussion.
        if ($lastpost->modified != $this->timemodified || $lastpost->get_userid() != $this->usermodified) {
            $this->timemodified = $lastpost->modified;
            $this->usermodified = $lastpost->get_userid();
            $DB->update_record('moodleoverflow_discussions', $this->build_db_object());

            // Return that the discussion needed an update.
            return true;
        }

        // Return that the discussion didn't need an update.
        return false;
    }

    // Getter.

    /**
     * Getter for the post ID
     * @return int $this->id    The post ID.
     */
    public function get_id() {
        $this->existence_check();
        return $this->id;
    }

    /**
     * Getter for the courseid
     * @return int $this->course    The ID of the course where the discussion is located.
     */
    public function get_courseid() {
        $this->existence_check();
        return $this->course;
    }

    /**
     * Getter for the moodleoverflowid
     * @return int $this->moodleoverflow    The ID of the moodleoverflow where the discussion is located.
     */
    public function get_moodleoverflowid() {
        $this->existence_check();
        return $this->moodleoverflow;
    }

    /**
     * Getter for the firstpostid
     * @return int $this->firstpost   The ID of the first post.
     */
    public function get_firstpostid() {
        $this->existence_check();
        return $this->firstpost;
    }

    /**
     * Getter for the userid
     * @return int $this->userid    The ID of the user who wrote the first post.
     */
    public function get_userid() {
        $this->existence_check();
        return $this->userid;
    }

    /**
     * Returns the ratings from this discussion.
     * @return array of votings
     */
    public function moodleoverflow_get_discussion_ratings() {
        $this->existence_check();
        $this->posts_check();

        $discussionratings = \mod_moodleoverflow\ratings::moodleoverflow_get_ratings_by_discussion($this->id);
        return $discussionratings;
    }

    /**
     * Get all posts from this Discussion.
     * The first/parent post is on the first position in the array.
     *
     * @return array $posts     Array ob posts objects
     */
    public function moodleoverflow_get_discussion_posts() {
        global $DB;
        $this->existence_check();

        // Check if the posts array are build yet. If not, build it.
        if (!$this->postsbuild) {
            // Get the posts from the DB. Get the parent post first.
            $firstpostsql = 'SELECT * FROM {moodleoverflow_posts} posts
                            WHERE discussion = ' . $this->id . ' AND parent = 0;';
            $otherpostssql = 'SELECT * FROM {moodleoverflow_posts} posts
                            WHERE discussion = ' . $this->id . ' AND parent != 0;';
            $firstpostrecord = $DB->get_record_sql($firstpostsql);
            $otherpostsrecord = $DB->get_records_sql($otherpostssql);

            // Add the first/parent post to the array, then add the other posts.
            $firstpost = post::from_record($firstpostrecord);
            $this->posts[$firstpost->get_id()] = $firstpost;

            foreach ($otherpostsrecord as $postrecord) {
                $post = post::from_record($postrecord);
                $this->posts[$post->get_id()] = $post;
            }

            // Now the posts are built.
            $this->postsbuild = true;
        }

        // Return the posts array.
        return $this->posts;
    }


    /**
     * Returns the moodleoverflowobject
     *
     * @return object $moodleoverflowobject
     */
    public function get_moodleoverflow() {
        global $DB;
        $this->existence_check();

        if (empty($this->moodleoverflowobject)) {
            $this->moodleoverflowobject = $DB->get_records('moodleoverflow', ['id' => $this->moodleoverflow]);
        }

        return $this->moodleoverflowobject;
    }

    /**
     * Returns the coursemodule
     *
     * @return object $cmobject
     */
    public function get_coursemodule() {
        global $DB;
        $this->existence_check();

        if (empty($this->cmobject)) {
            if (!$this->cmobject = $DB->get_coursemodule_from_instance('moodleoverflow', $this->get_moodleoverflow()->id,
                                                                                         $this->get_moodleoverflow()->course)) {
                throw new \moodle_exception('invalidcoursemodule');
            }
        }

        return $this->cmobject;
    }

    /**
     * This getter works as an help function in case another file/function needs the db-object of this instance (as the function
     * is not adapted/refactored to the new way of working with discussion).
     * @return object
     */
    public function get_db_object() {
        $this->existence_check();
        return $this->build_db_object();
    }

    // Helper functions.

    /**
     * Builds an object from this instance that has only DB-relevant attributes.
     * As this is an private function, it doesn't need an existence check.
     * @return object $dbobject
     */
    private function build_db_object() {
        $dbobject = new \stdClass();
        $dbobject->id = $this->id;
        $dbobject->course = $this->course;
        $dbobject->moodleoverflow = $this->moodleoverflow;
        $dbobject->name = $this->name;
        $dbobject->firstpost = $this->firstpost;
        $dbobject->userid = $this->userid;
        $dbobject->timemodified = $this->timemodified;
        $dbobject->timestart = $this->timestart;
        $dbobject->usermodified = $this->usermodified;

        return $dbobject;
    }

    // Security.

    /**
     * Makes sure that the instance exists in the database. Every function in this class requires this check
     * (except the function that adds the discussion to the database)
     *
     * @return true
     * @throws moodle_exception
     */
    private function existence_check() {
        if (empty($this->id) || $this->id == false || $this->id == null) {
            throw new \moodle_exception('noexistingdiscussion', 'moodleoverflow');
        }
        return true;
    }

    /**
     * Makes sure that the instance knows all of its posts (That all posts of the db are in the local array).
     * Not all functions need this check.
     * @return true
     * @throws moodle_exception
     */
    private function posts_check() {
        if (!$this->postsbuild) {
            throw new \moodle_exception('notallpostsavailable', 'moodleoverflow');
        }
        return true;
    }

    /**
     * Check, if certain posts really exists in this discussion.
     *
     * @param int $postid   The ID of the post that is being checked.
     * @return true
     * @throws moodle_exception;
     */
    private function post_exists_check($postid) {
        if (!$this->posts[$postid]) {
            throw new \moodle_exception('postnotpartofdiscussion', 'moodleoverflow');
        }

        return true;
    }
}
