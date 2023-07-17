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

require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class that represents a discussion.
 * A discussion administrates the posts and has one parent post, that started the discussion.
 *
 * @package     mod_moodleoverflow
 * @copyright   2023 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion {

    /** @var int The discussion ID */
    private $id;

    /** @var int The course ID where the discussion is located */
    private $course;

    /** @var int The moodleoverflow ID where the discussion is located*/
    private $moodleoverflow;

    /** @var char The title of the discussion, the titel of the parent post*/
    private $name;

    /** @var int The id of the parent/first post*/
    private $firstpost;

    /** @var int The user ID who started the discussion */
    private $userid;

    /** @var int Unix-timestamp of modification */
    private $timemodified;

    /** @var int Unix-timestamp of discussion creation */
    private $timestart;

    /** @var int the user ID who modified the discussion */
    private $usermodified;

    // Not Database-related attributes.

    /** @var array an Array of posts that belong to this discussion */
    private $posts;

    /** @var object The moodleoverflow object where the discussion is located */
    private $moodleoverflowobject;

    /** @var object The course module object */
    private $cmobject;

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
        $this->posts = array();
    }

    /**
     * Builds a Discussion from a DB record.
     *
     * @param object   $record Data object.
     * @return object discussion instance
     */
    public static function from_record($record) {
        $id = null;
        if (object__property_exists($record, 'id') && $record->id) {
            $id = $record->id;
        }

        $course = null;
        if (object__property_exists($record, 'course') && $record->course) {
            $course = $record->course;
        }

        $moodleoverflow = null;
        if (object__property_exists($record, 'moodleoverflow') && $record->moodleoverflow) {
            $moodleoverflow = $record->moodleoverflow;
        }

        $name = null;
        if (object__property_exists($record, 'name') && $record->name) {
            $name = $record->name;
        }

        $firstpost = null;
        if (object__property_exists($record, 'firstpost') && $record->firstpost) {
            $firstpost = $record->firstpost;
        }

        $userid = null;
        if (object__property_exists($record, 'userid') && $record->userid) {
            $userid = $record->userid;
        }

        $timemodified = null;
        if (object__property_exists($record, 'timemodified') && $record->timemodified) {
            $timemodified = $record->timemodified;
        }

        $timestart = null;
        if (object__property_exists($record, 'timestart') && $record->timestart) {
            $timestart = $record->timestart;
        }

        $usermodified = null;
        if (object__property_exists($record, 'usermodified') && $record->usermodified) {
            $usermodified = $record->usermodified;
        }

        $instance = new self($id, $course, $moodleoverflow, $name, $firstpost, $userid, $timemodified, $timestart, $usermodified);

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

        // Get the current time.
        $timenow = time();

        // Retrieve the module instance.
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $this->moodleoverflow))) {
            return false;
        }

        // Add the discussion to the Database.
        $this->id = $DB->insert_record('moodleoverflow_discussions', $this);

        // Create the first/parent post for the new discussion and add it do the DB.
        $post = post::construct_without_id($this->id, 0, $prepost->userid, $prepost->created, $prepost->modified,
                                            $preposts->message, $prepost->messageformat, $prepost->attachment, $prepost->mailed,
                                            $prepost->reviewed, $prepost->timereviewed, $prepost->formattachments);
        // Add it to the DB and save the id of the first/parent post.
        $this->firstpost = $post->moodleoverflow_add_new_post();

        // Save the id of the first/parent post in the DB.
        $DB->set_field('moodleoverflow_discussions', 'firstpost', $this->firstpost, array('id' => $this->id));

        // Add the parent post to the $posts array.
        $this->posts[$this->firstpost] = $post;

        // Trigger event.
        $params = array(
            'context' => $prepost->modulecontext,
            'objectid' => $post->discussion,
        );
        $event = \mod_moodleoverflow\event\discussion_viewed::create($params);
        $event->trigger();

        // Return the id of the discussion.
        return $this->id;
    }

    /**
     * Delete a discussion with all of it's posts
     *
     * @return bool Wether deletion was successful of not
     */
    public function moodleoverflow_delete_discussion() {
        global $DB;
        $this->existence_check();

        // Delete a discussion with all of it's posts.
        // In case something does not work we throw the error as it should be known that something went ... terribly wrong.
        // All DB transactions are rolled back.
        try {
            $transaction = $DB->start_delegated_transaction();

            // Delete every post of this discussion.
            foreach ($posts as $post) {
                $post->moodleoverflow_delete_post(false);
            }

            // Delete the read-records for the discussion.
            readtracking::moodleoverflow_delete_read_records(-1, -1, $this->id);

            // Remove the subscriptions for the discussion.
            $DB->delete_records('moodleoverflow_discuss_subs', array('discussion' => $this->id));

            // Delete the discussion from the database.
            $DB->delete_records('moodleoverflow_discussions', array('id' => $this->id));

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

    }

    public function moodleoverflow_delete_post_from_discussion() {

    }
    public function moodleoverflow_get_discussion_ratings() {

    }
    public function moodleoverflow_get_discussion_posts() {

    }
    public function moodleoverflow_discussion_update_last_post() {
        // This function has something to do with updating the attribute "timemodified".
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
            $this->moodleoverflowobject = $DB->get_records('moodleoverflow', array('id' => $this->moodleoverflow));
        }

        return $this->moodleoverflowobject;
    }

    /**
     * Returns the coursemodule
     *
     * @return object $cmobject
     */
    public function get_coursemodule() {
        $this->existence_check();

        if (empty($this->cmobject)) {
            if (!$this->cmobject = $DB->get_coursemodule_from_instance('moodleoverflow', $this->get_moodleoverflow()->id,
                                                                                         $this->get_moodleoverflow()->course)) {
                throw new moodle_exception('invalidcoursemodule');
            }

        }

        return $this->cmobject;
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
            throw new moodle_exception('noexistingdiscussion', 'moodleoverflow');
        }
        return true;
    }

}
