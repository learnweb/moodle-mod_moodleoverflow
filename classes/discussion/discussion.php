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

// Important namespaces.
use coding_exception;
use dml_exception;
use Exception;
use mod_moodleoverflow\event\discussion_viewed;
use mod_moodleoverflow\ratings;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\post\post;
use moodle_exception;

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
    /** @var ?int The discussion ID */
    private ?int $id;

    /** @var int The course ID where the discussion is located */
    private int $course;

    /** @var int The moodleoverflow ID where the discussion is located*/
    private int $moodleoverflow;

    /** @var string The title of the discussion, the titel of the parent post*/
    public string $name;

    /** @var int The id of the parent/first post*/
    private int $firstpost;

    /** @var int The user ID who started the discussion */
    private int $userid;

    /** @var int Unix-timestamp of modification */
    public int $timemodified;

    /** @var int Unix-timestamp of discussion creation */
    public int $timestart;

    /** @var int the user ID who modified the discussion */
    public int $usermodified;

    // Not Database-related attributes.

    /** @var post[] an Array of posts that belong to this discussion */
    public array $posts;

    /** @var bool  a variable for checking if this instance has all its posts */
    public bool $postsbuild;

    /** @var object The moodleoverflow object where the discussion is located */
    public object $moodleoverflowobject;

    /** @var object The course module object */
    public object $cmobject;

    // Constructors and other builders.

    /**
     * Constructor to build a new discussion.
     * @param ?int  $id                 The Discussion ID.
     * @param int       $course             The course ID.
     * @param int       $moodleoverflow     The moodleoverflow ID.
     * @param string    $name               Discussion Title.
     * @param int       $firstpost          .
     * @param int       $userid  The course ID.
     * @param int       $timemodified   The course ID.
     * @param int       $timestart   The course ID.
     * @param int       $usermodified   The course ID.
     */
    public function __construct(
        ?int $id,
        int $course,
        int $moodleoverflow,
        string $name,
        int $firstpost,
        int $userid,
        int $timemodified,
        int $timestart,
        int $usermodified
    ) {
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
     * @param object $record Data object.
     * @return discussion discussion instance
     */
    public static function from_record(object $record): discussion {
        $id = !empty($record->id) ? $record->id : null;
        $course = !empty($record->course) ? $record->course : 0;
        $moodleoverflow = !empty($record->moodleoverflow) ? $record->moodleoverflow : 0;
        $name = !empty($record->name) ? $record->name : '';
        $firstpost = !empty($record->firstpost) ? $record->firstpost : 0;
        $userid = !empty($record->userid) ? $record->userid : 0;
        $timemodified = !empty($record->timemodified) ? $record->timemodified : 0;
        $timestart = !empty($record->timestart) ? $record->timestart : 0;
        $usermodified = !empty($record->usermodified) ? $record->usermodified : 0;

        $instance = new self($id, $course, $moodleoverflow, $name, $firstpost, $userid, $timemodified, $timestart, $usermodified);

        // Get all the posts so that the instance can work with it.
        $instance->moodleoverflow_get_discussion_posts();

        return $instance;
    }

    /**
     * Function to build a new discussion without specifying the Discussion ID.
     * @param int $course             The course ID.
     * @param int $moodleoverflow     The moodleoverflow ID.
     * @param string $name               Discussion Title.
     * @param int $firstpost          .
     * @param int $userid  The course ID.
     * @param int $timemodified   The course ID.
     * @param int $timestart   The course ID.
     * @param int $usermodified   The course ID.
     *
     * @return object discussion object without id.
     */
    public static function construct_without_id(
        int $course,
        int $moodleoverflow,
        string $name,
        int $firstpost,
        int $userid,
        int $timemodified,
        int $timestart,
        int $usermodified
    ): object {
        $id = null;
        return new self($id, $course, $moodleoverflow, $name, $firstpost, $userid, $timemodified, $timestart, $usermodified);
    }

    // Discussion Functions.

    /**
     * Adds a new Discussion with a post.
     *
     * @param object $prepost The prepost object from the post_control. Has information about the post and other important stuff.
     * @return bool|?int
     * @throws dml_exception
     * @throws coding_exception
     */
    public function moodleoverflow_add_discussion(object $prepost): bool|int|null {
        global $DB;

        // Add the discussion to the Database.
        $this->id = $DB->insert_record('moodleoverflow_discussions', $this->build_db_object());

        // Create the first/parent post for the new discussion and add it do the DB.
        $post = post::construct_without_id(
            $this->id,
            0,
            $prepost->userid,
            $prepost->timenow,
            $prepost->timenow,
            $prepost->message,
            $prepost->messageformat,
            "",
            0,
            $prepost->reviewed,
            null,
            $prepost->formattachments
        );
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
        $event = discussion_viewed::create($params);
        $event->trigger();

        // Return the id of the discussion.
        return $this->id;
    }

    /**
     * Delete a discussion with all of it's posts
     * @param object $prepost Information about the post from the post_control
     * @return bool Wether deletion was successful of not
     * @throws moodle_exception
     */
    public function moodleoverflow_delete_discussion(object $prepost): bool {
        global $DB;
        $this->existence_check();
        $this->posts_check();

        // Delete a discussion with all of it's posts.
        // In case something does not work we throw the error as it should be known that something went ... terribly wrong.
        // All DB transactions are rolled back.
        try {
            $transaction = $DB->start_delegated_transaction();

            // Delete every post of this discussion.
            $firstpost = $this->posts[$this->firstpost];
            $firstpost->moodleoverflow_delete_post(true);

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
     * @throws dml_exception|moodle_exception
     */
    public function moodleoverflow_add_post_to_discussion(object $prepost) {
        global $DB;
        $this->existence_check();
        $this->posts_check();

        // Create the post that will be added to the new discussion.
        $post = post::construct_without_id(
            $this->id,
            $prepost->parentid,
            $prepost->userid,
            $prepost->timenow,
            $prepost->timenow,
            $prepost->message,
            $prepost->messageformat,
            "",
            0,
            $prepost->reviewed,
            null,
            $prepost->formattachments
        );
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
     * @return bool If the deletion was possible
     * @throws moodle_exception
     */
    public function moodleoverflow_delete_post_from_discussion(object $prepost): bool {
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
     * @throws dml_exception|moodle_exception
     */
    public function moodleoverflow_edit_post_from_discussion(object $prepost): bool {
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
     * @throws dml_exception|moodle_exception
     */
    public function moodleoverflow_discussion_adapt_to_last_post(): bool {
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
     * @return ?int $this->id    The post ID.
     * @throws moodle_exception
     */
    public function get_id(): ?int {
        $this->existence_check();
        return $this->id;
    }

    /**
     * Getter for the courseid
     * @return int $this->course    The ID of the course where the discussion is located.
     * @throws moodle_exception
     */
    public function get_courseid(): int {
        $this->existence_check();
        return $this->course;
    }

    /**
     * Getter for the moodleoverflowid
     * @return int $this->moodleoverflow    The ID of the moodleoverflow where the discussion is located.
     * @throws moodle_exception
     */
    public function get_moodleoverflowid(): int {
        $this->existence_check();
        return $this->moodleoverflow;
    }

    /**
     * Getter for the firstpostid
     * @return int $this->firstpost   The ID of the first post.
     * @throws moodle_exception
     */
    public function get_firstpostid(): int {
        $this->existence_check();
        return $this->firstpost;
    }

    /**
     * Getter for the userid
     * @return int $this->userid    The ID of the user who wrote the first post.
     * @throws moodle_exception
     */
    public function get_userid(): int {
        $this->existence_check();
        return $this->userid;
    }

    /**
     * Returns the ratings from this discussion.
     * @return array of votings
     * @throws moodle_exception
     */
    public function moodleoverflow_get_discussion_ratings(): array {
        $this->existence_check();
        $this->posts_check();

        return ratings::moodleoverflow_get_ratings_by_discussion($this->id);
    }

    /**
     * Get all posts from this Discussion.
     * The first/parent post is on the first position in the array.
     *
     * @return array $posts     Array ob posts objects
     * @throws moodle_exception
     */
    public function moodleoverflow_get_discussion_posts(): array {
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
     * @throws dml_exception|moodle_exception
     */
    public function get_moodleoverflow(): object {
        global $DB;
        $this->existence_check();

        if (empty($this->moodleoverflowobject)) {
            $this->moodleoverflowobject = $DB->get_record('moodleoverflow', ['id' => $this->moodleoverflow]);
        }

        return $this->moodleoverflowobject;
    }

    /**
     * Returns the coursemodule
     *
     * @return object $cmobject
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_coursemodule(): object {
        $this->existence_check();

        if (empty($this->cmobject)) {
            if (
                !$this->cmobject = get_coursemodule_from_instance(
                    'moodleoverflow',
                    $this->get_moodleoverflow()->id,
                    $this->get_moodleoverflow()->course
                )
            ) {
                throw new moodle_exception('invalidcoursemodule');
            }
        }

        return $this->cmobject;
    }

    /**
     * This getter works as an help function in case another file/function needs the db-object of this instance (as the function
     * is not adapted/refactored to the new way of working with discussion).
     * @return object
     * @throws moodle_exception
     */
    public function get_db_object(): object {
        $this->existence_check();
        return $this->build_db_object();
    }

    // Helper functions.

    /**
     * Builds an object from this instance that has only DB-relevant attributes.
     * As this is an private function, it doesn't need an existence check.
     * @return object $dbobject
     */
    private function build_db_object(): object {
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
     * @return void
     * @throws moodle_exception
     */
    private function existence_check(): void {
        if (empty($this->id)) {
            throw new moodle_exception('noexistingdiscussion', 'moodleoverflow');
        }
    }

    /**
     * Makes sure that the instance knows all of its posts (That all posts of the db are in the local array).
     * Not all functions need this check.
     * @return void
     * @throws moodle_exception
     */
    private function posts_check(): void {
        if (!$this->postsbuild) {
            throw new moodle_exception('notallpostsavailable', 'moodleoverflow');
        }
    }

    /**
     * Check, if certain posts really exists in this discussion.
     *
     * @param int $postid   The ID of the post that is being checked.
     * @return void
     * @throws moodle_exception;
     */
    private function post_exists_check(int $postid): void {
        if (!$this->posts[$postid]) {
            throw new moodle_exception('postnotpartofdiscussion', 'moodleoverflow');
        }
    }
}
