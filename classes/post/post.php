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


namespace mod_moodleoverflow\post;

// Import namespace from the locallib, needs a check later which namespaces are really needed.
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\review;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\discussion\discussion;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class that represents a post.
 *
 * Please be careful with functions that delete, add or edit posts.
 * Security checks for these functions were done in the post_control class and these functions should only be accessed that way.
 * Accessing these functions directly without the checks from the post_control could lead to serious errors.
 *
 * Most of the functions in this class are called by moodleoverflow/classes/discussion/discussion.php . The discussion class
 * manages posts in a moodleoverflow and works like a toplevel class for the post class. If you want to manipulate
 * (delete, add, edit) posts, please call the functions from the discussion class. To read and obtain information about posts
 * you are free to choose.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post {

    // Attributes. The most important attributes are private and can only be changed by internal functions.
    // Other attributes can be accessed directly.

    /** @var int The post ID */
    private $id;

    /** @var int The corresponding discussion ID */
    private $discussion;

    /** @var int The parent post ID */
    private $parent;

    /** @var int The ID of the User who wrote the post */
    private $userid;

    /** @var int Creation timestamp */
    public $created;

    /** @var int Modification timestamp */
    public $modified;

    /** @var string The message (content) of the post */
    public $message;

    /** @var int  The message format*/
    public $messageformat;

    /** @var string Attachment of the post */
    public $attachment;

    /** @var int Mailed status*/
    public $mailed;

    /** @var int Review status */
    public $reviewed;

    /** @var int The time where the post was reviewed*/
    public $timereviewed;

    // Not database related functions.

    /** @var int This variable is optional, it contains important information for the add_attachment function */
    public $formattachments;

    /** @var string The subject/title of the Discussion */
    public $subject;

    /** @var object The discussion where the post is located */
    public $discussionobject;

    /** @var object The Moodleoverflow where the post is located*/
    public $moodleoverflowobject;

    /** @var object The course module object */
    public $cmobject;

    /** @var object The parent post of an answerpost */
    public $parentpost;

    // Constructors and other builders.

    /**
     * Constructor to make a new post.
     * @param int       $id                 The post ID.
     * @param int       $discussion         The discussion ID.
     * @param int       $parent             The parent post ID.
     * @param int       $userid             The user ID that created the post.
     * @param int       $created            Creation timestamp
     * @param int       $modified           Modification timestamp
     * @param string    $message            The message (content) of the post
     * @param int       $messageformat      The message format
     * @param char      $attachment         Attachment of the post
     * @param int       $mailed             Mailed status
     * @param int       $reviewed           Review status
     * @param int       $timereviewed       The time where the post was reviewed
     * @param object    $formattachments    Information about attachments of the post_form
     */
    public function __construct($id, $discussion, $parent, $userid, $created, $modified, $message,
                                $messageformat, $attachment, $mailed, $reviewed, $timereviewed, $formattachments = false) {
        $this->id = $id;
        $this->discussion = $discussion;
        $this->parent = $parent;
        $this->userid = $userid;
        $this->created = $created;
        $this->modified = $modified;
        $this->message = $message;
        $this->messageformat = $messageformat;
        $this->attachment = $attachment;
        $this->mailed = $mailed;
        $this->reviewed = $reviewed;
        $this->timereviewed = $timereviewed;
        $this->formattachments = $formattachments;
    }

    /**
     * Builds a Post from a DB record.
     * Look up database structure for standard values.
     * @param object  $record Data object.
     * @return object post instance
     */
    public static function from_record($record) {
        $id = null;
        if (object_property_exists($record, 'id') && $record->id) {
            $id = $record->id;
        }

        $discussion = 0;
        if (object_property_exists($record, 'discussion') && $record->discussion) {
            $discussion = $record->discussion;
        }

        $parent = 0;
        if (object_property_exists($record, 'parent') && $record->parent) {
            $parent = $record->parent;
        }

        $userid = 0;
        if (object_property_exists($record, 'userid') && $record->userid) {
            $userid = $record->userid;
        }

        $created = 0;
        if (object_property_exists($record, 'created') && $record->created) {
            $created = $record->created;
        }

        $modified = 0;
        if (object_property_exists($record, 'modified') && $record->modified) {
            $modified = $record->modified;
        }

        $message = '';
        if (object_property_exists($record, 'message') && $record->message) {
            $message = $record->message;
        }

        $messageformat = 0;
        if (object_property_exists($record, 'messageformat') && $record->messageformat) {
            $messageformat = $record->messageformat;
        }

        $attachment = '';
        if (object_property_exists($record, 'attachment') && $record->attachment) {
            $attachment = $record->attachment;
        }

        $mailed = 0;
        if (object_property_exists($record, 'mailed') && $record->mailed) {
            $mailed = $record->mailed;
        }

        $reviewed = 1;
        if (object_property_exists($record, 'reviewed') && $record->reviewed) {
            $reviewed = $record->reviewed;
        }

        $timereviewed = null;
        if (object_property_exists($record, 'timereviewed') && $record->timereviewed) {
            $timereviewed = $record->timereviewed;
        }

        return new self($id, $discussion, $parent, $userid, $created, $modified, $message, $messageformat, $attachment, $mailed,
                        $reviewed, $timereviewed);
    }

    /**
     * Function to make a new post without specifying the Post ID.
     *
     * @param int       $discussion         The discussion ID.
     * @param int       $parent             The parent post ID.
     * @param int       $userid             The user ID that created the post.
     * @param int       $created            Creation timestamp
     * @param int       $modified           Modification timestamp
     * @param string    $message            The message (content) of the post
     * @param int       $messageformat      The message format
     * @param char      $attachment         Attachment of the post
     * @param int       $mailed             Mailed status
     * @param int       $reviewed           Review status
     * @param int       $timereviewed       The time where the post was reviewed
     * @param object    $formattachments    Information about attachments from the post_form
     *
     * @return object post object without id
     */
    public static function construct_without_id($discussion, $parent, $userid, $created, $modified, $message,
                                $messageformat, $attachment, $mailed, $reviewed, $timereviewed, $formattachments = false) {
        $id = null;
        return new self($id, $discussion, $parent, $userid, $created, $modified, $message, $messageformat, $attachment, $mailed,
                        $reviewed, $timereviewed, $formattachments);
    }

    // Post Functions.

    /**
     * Adds a new post in an existing discussion.
     * @return bool|int The Id of the post if operation was successful
     * @throws coding_exception
     * @throws dml_exception
     */
    public function moodleoverflow_add_new_post() {
        global $USER, $DB;

        // Add post to the database.
        $this->id = $DB->insert_record('moodleoverflow_posts', $this->build_db_object());
        $this->moodleoverflow_add_attachment($this, $this->get_moodleoverflow(), $this->get_coursemodule());

        if ($this->reviewed) {
            // Update the discussion.
            $DB->set_field('moodleoverflow_discussions', 'timemodified', $this->modified, array('id' => $this->discussion));
            $DB->set_field('moodleoverflow_discussions', 'usermodified', $this->userid, array('id' => $this->discussion));
        }

        // Mark the created post as read if the user is tracking the discussion.
        $cantrack = readtracking::moodleoverflow_can_track_moodleoverflows($this->get_moodleoverflow());
        $istracked = readtracking::moodleoverflow_is_tracked($this->get_moodleoverflow());
        if ($cantrack && $istracked) {
            // Please be aware that in future the use of get_db_object() should be replaced with only $this,
            // as the readtracking class should be refactored with the new way of working with posts.
            readtracking::moodleoverflow_mark_post_read($this->userid, $this->get_db_object());
        }

        // Return the id of the created post.
        return $this->id;
    }

    /**
     * Deletes a single moodleoverflow post.
     *
     * @param bool  $deletechildren        The child posts
     *
     * @return bool Whether the deletion was successful or not
     */
    public function moodleoverflow_delete_post($deletechildren) {
        global $DB, $USER;
        $this->existence_check();

        // Iterate through all children and delete them.
        // In case something does not work we throw the error as it should be known that something went ... terribly wrong.
        // All DB transactions are rolled back.
        try {
            $transaction = $DB->start_delegated_transaction();

            // Get the coursemoduleid for later use.
            $coursemoduleid = $this->get_coursemodule()->id;
            $childposts = $this->moodleoverflow_get_childposts();
            if ($deletechildren && $childposts) {
                foreach ($childposts as $childpost) {
                    $child = $this->from_record($childpost);
                    $child->moodleoverflow_delete_post($deletechildren);
                }
            }

            // Delete the ratings.
            $DB->delete_records('moodleoverflow_ratings', array('postid' => $this->id));

            // Delete the post.
            if ($DB->delete_records('moodleoverflow_posts', array('id' => $this->id))) {
                // Delete the read records.
                readtracking::moodleoverflow_delete_read_records(-1, $this->id);

                // Delete the attachments.
                $fs = get_file_storage();
                $context = \context_module::instance($coursemoduleid);
                $attachments = $fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment',
                    $this->id, "filename", true);
                foreach ($attachments as $attachment) {
                    // Get file.
                    $file = $fs->get_file($context->id, 'mod_moodleoverflow', 'attachment', $this->id,
                        $attachment->get_filepath(), $attachment->get_filename());
                    // Delete it if it exists.
                    if ($file) {
                        $file->delete();
                    }
                }

                // Trigger the post deletion event.
                $params = array(
                    'context' => $context,
                    'objectid' => $this->id,
                    'other' => array(
                        'discussionid' => $this->discussion,
                        'moodleoverflowid' => $this->get_moodleoverflow()->id
                    )
                );
                if ($this->userid !== $USER->id) {
                    $params['relateduserid'] = $this->userid;
                }
                $event = \mod_moodleoverflow\event\post_deleted::create($params);
                $event->trigger();

                // Set the id of this instance to null, so that working with it is not possible anymore.
                $this->id = null;

                // The post has been deleted.
                $transaction->allow_commit();
                return true;
            }
        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        // Deleting the post failed.
        return false;
    }

    /**
     * Edits the message from this instance.
     * @param int       $time               The time the post was modified (given from the discussion class).
     * @param string    $postmessage        The new message
     * @param int       $messageformat
     * @param object    $formattachments    Information about attachments from the post_form
     *
     * @return true if the post has been edited successfully
     */
    public function moodleoverflow_edit_post($time, $postmessage, $messageformat, $formattachments) {
        global $DB;
        $this->existence_check();

        // Update the attributes.
        $this->modified = $time;
        $this->message = $postmessage;
        $this->messageformat = $messageformat;
        $this->formattachments = $formattachments;

        // Update the record in the database.
        $DB->update_record('moodleoverflow_posts', $this->build_db_object());

        // Update the attachments. This happens after the DB update call, as this function changes the DB record as well.
        $this->moodleoverflow_add_attachment();

        // Mark the edited post as read.
        $this->mark_post_read();

        // The post has been edited successfully.
        return true;
    }

    /**
     * // TODO: RETHINK THIS FUNCTION.
     * Gets a post with all info ready for moodleoverflow_print_post.
     * Most of these joins are just to get the forum id.
     *
     * @return mixed array of posts or false
     */
    public function moodleoverflow_get_complete_post() {
        global $DB, $CFG;
        $this->existence_check();

        if ($CFG->branch >= 311) {
            $allnames = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        } else {
            $allnames = implode(', ', fields::get_name_fields());
        }
        $sql = "SELECT p.*, d.moodleoverflow, $allnames, u.email, u.picture, u.imagealt
                FROM {moodleoverflow_posts} p
                    JOIN {moodleoverflow_discussions} d ON p.discussion = d.id
                LEFT JOIN {user} u ON p.userid = u.id
                    WHERE p.id = " . $this->id . " ;";

        $post = $DB->get_records_sql($sql);
        if ($post->userid == 0) {
            $post->message = get_string('privacy:anonym_post_message', 'mod_moodleoverflow');
        }
        return $post;
    }

    /**
     * If successful, this function returns the name of the file
     *
     * @return bool
     */
    public function moodleoverflow_add_attachment() {
        global $DB;
        $this->existence_check();

        if (empty($this->formattachments)) {
            return true;    // Nothing to do.
        }

        $context = \context_module::instance($this->get_coursemodule()->id);
        $info = file_get_draft_area_info($this->formattachments);
        $present = ($info['filecount'] > 0) ? '1' : '';
        file_save_draft_area_files($this->formattachments, $context->id, 'mod_moodleoverflow', 'attachment', $this->id,
                                  \mod_moodleoverflow_post_form::attachment_options($this->get_moodleoverflow()));
        $DB->set_field('moodleoverflow_posts', 'attachment', $present, array('id' => $this->id));
    }

    /**
     * Returns attachments with information for the template
     *
     *
     * @return array
     */
    public function moodleoverflow_get_attachments() {
        global $CFG, $OUTPUT;
        $this->existence_check();

        if (empty($this->attachment) || (!$context = \context_module::instance($this->get_coursemodule()->id))) {
            return array();
        }

        $attachments = array();
        $fs = get_file_storage();

        // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
        // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
        $files = $fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $this->id, "filename", false);
        if ($files) {
            $i = 0;
            foreach ($files as $file) {
                $attachments[$i] = array();
                $attachments[$i]['filename'] = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $iconimage = $OUTPUT->pix_icon(file_file_icon($file),
                    get_mimetype_description($file), 'moodle',
                    array('class' => 'icon'));
                $path = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                                                         $file->get_itemid(), $file->get_filepath(), $file->get_filename());
                $attachments[$i]['icon'] = $iconimage;
                $attachments[$i]['filepath'] = $path;

                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links.
                    $attachments[$i]['image'] = true;
                } else {
                    $attachments[$i]['image'] = false;
                }
                $i += 1;
            }
        }
        return $attachments;
    }

    // Getter.

    /**
     * Getter for the postid
     * @return int $this->id    The post ID.
     */
    public function get_id() {
        $this->existence_check();
        return $this->id;
    }

    /**
     * Getter for the discussionid
     * @return int $this->discussion    The ID of the discussion where the post is located.
     */
    public function get_discussionid() {
        $this->existence_check();
        return $this->discussion;
    }

    /**
     * Getter for the parentid
     * @return int $this->parent    The ID of the parent post.
     */
    public function get_parentid() {
        $this->existence_check();
        return $this->parent;
    }

    /**
     * Getter for the userid
     * @return int $this->userid    The ID of the user who wrote the post.
     */
    public function get_userid() {
        $this->existence_check();
        return $this->userid;
    }

    /**
     * Returns the moodleoverflow where the post is located.
     * @return object $moodleoverflowobject
     */
    public function get_moodleoverflow() {
        global $DB;
        $this->existence_check();

        if (empty($this->moodleoverflowobject)) {
            $discussion = $this->get_discussion();
            $this->moodleoverflowobject = $DB->get_record('moodleoverflow', array('id' => $discussion->get_moodleoverflowid()));
        }

        return $this->moodleoverflowobject;
    }

    /**
     * Returns the discussion where the post is located.
     *
     * @return object $discussionobject.
     */
    public function get_discussion() {
        global $DB;
        $this->existence_check();

        if (empty($this->discussionobject)) {
            $record = $DB->get_record('moodleoverflow_discussions', array('id' => $this->discussion));
            $this->discussionobject = discussion::from_record($record);
        }
        return $this->discussionobject;
    }

    /**
     * Returns the coursemodule
     *
     * @return object $cmobject
     */
    public function get_coursemodule() {
        $this->existence_check();

        if (empty($this->cmobject)) {
            $this->cmobject = \get_coursemodule_from_instance('moodleoverflow', $this->get_moodleoverflow()->id);
        }

        return $this->cmobject;
    }

    /**
     * Returns the parent post
     * @return object|false $post|false
     */
    public function moodleoverflow_get_parentpost() {
        global $DB;
        $this->existence_check();

        if ($this->parent == 0) {
            // This post is the parent post.
            $this->parentpost = false;
            return false;
        }

        if (empty($this->parentpost)) {
            $parentpostrecord = $DB->get_record('moodleoverflow_post', array('id' => $this->parent));
            $this->parentpost = $this->from_record($parentpostrecord);
        }
        return $this->parentpost;
    }

    /**
     * Returns children posts (answers) as DB-records.
     *
     * @return array|false children/answer posts.
     */
    public function moodleoverflow_get_childposts() {
        global $DB;
        $this->existence_check();

        if ($childposts = $DB->get_records('moodleoverflow_posts', array('parent' => $this->id))) {
            return $childposts;
        }

        return false;
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

    // Helper Functions.

    /**
     * Calculate the ratings of a post.
     *
     * @return object $ratingsobject.
     */
    public function moodleoverflow_get_post_ratings() {
        $this->existence_check();

        $discussionid = $this->get_discussion()->id;
        $postratings = \mod_moodleoverflow\ratings::moodleoverflow_get_ratings_by_discussion($discussionid, $this->id);

        $ratingsobject = new \stdClass();
        $ratingsobject->upvotes = $postratings->upvotes;
        $ratingsobject->downvotes = $postratings->downvotes;
        $ratingsobject->votesdifference = $postratings->upvotes - $postratings->downvotes;
        $ratingsobject->markedhelpful = $postratings->ishelpful;
        $ratingsobject->markedsolution = $postratings->issolved;

        return $ratingsobject;
    }

    /**
     * Marks the post as read if the user is tracking the discussion.
     * Uses function from mod_moodleoverflow\readtracking.
     */
    public function mark_post_read() {
        global $USER;
        $cantrack = readtracking::moodleoverflow_can_track_moodleoverflows($this->get_moodleoverflow());
        $istracked = readtracking::moodleoverflow_is_tracked($this->get_moodleoverflow());
        if ($cantrack && $istracked) {
            // Please be aware that in future the use of get_db_object() should be replaced with only $this,
            // as the readtracking class should be refactored with the new way of working with posts.
            readtracking::moodleoverflow_mark_post_read($USER->id, $this->get_db_object());
        }
    }

    /**
     * Builds an object from this instance that has only DB-relevant attributes.
     * @return object $dbobject
     */
    private function build_db_object() {
        $dbobject = new \stdClass();
        $dbobject->id = $this->id;
        $dbobject->discussion = $this->discussion;
        $dbobject->parent = $this->parent;
        $dbobject->userid = $this->userid;
        $dbobject->created = $this->created;
        $dbobject->modified = $this->modified;
        $dbobject->message = $this->message;
        $dbobject->messageformat = $this->messageformat;
        $dbobject->attachment = $this->attachment;
        $dbobject->mailed = $this->mailed;
        $dbobject->reviewed = $this->reviewed;
        $dbobject->timereviewed = $this->timereviewed;

        return $dbobject;
    }

    /*
     * Count all replies of a post.
     *
     * @param bool $onlyreviewed Whether to count only reviewed posts.
     * @return int Amount of replies
     */
    public function moodleoverflow_count_replies($onlyreviewed) {
        global $DB;

        $conditions = ['parent' => $this->id];

        if ($onlyreviewed) {
            $conditions['reviewed'] = '1';
        }

        // Return the amount of replies.
        return $DB->count_records('moodleoverflow_posts', $conditions);
    }

    // Security.

    /**
     * Makes sure that the instance exists in the database. Every function in this class requires this check
     * (except the function that adds a post to the database)
     *
     * @return true
     * @throws moodle_exception
     */
    private function existence_check() {
        if (empty($this->id) || $this->id == false || $this->id == null) {
            throw new \moodle_exception('noexistingpost', 'moodleoverflow');
        }
        return true;
    }
}
