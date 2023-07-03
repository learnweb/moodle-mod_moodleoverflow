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

// Import namespace from the locallib, needs a check later which namespaces are really needed
// use mod_moodleoverflow\anonymous;
// use mod_moodleoverflow\capabilities;
// use mod_moodleoverflow\review;
use mod_moodleoverflow\readtracking;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Class that represents a post.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post {

    /** @var int The post ID */
    private $id;

    /** @var int The corresponding discussion ID */
    private $discussion;

    /** @var int The parent post ID */
    private $parent;

    /** @var int The ID of the User who wrote the post */
    private $userid;

    /** @var int Creation timestamp */
    private $created;

    /** @var int Modification timestamp */
    private $modified;

    /** @var string The message (content) of the post */
    private $message;

    /** @var int  The message format*/
    private $messageformat;

    /** @var char Attachment of the post */
    private $attachment;

    /** @var int Mailed status*/
    private $mailed;

    /** @var int Review status */
    private $reviewed;

    /** @var int The time where the post was reviewed*/
    private $timereviewed;


    /** 
     * Constructor to make a new post
     */
    public function __construct($id, $discussion, $parent, $userid, $created, $modified, $message, $messageformat, $attachment, $mailed, $reviewed, $timereviewed) {
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
    }


    /**
     * Creates a Post from a DB record.
     * 
     * @param object $record Data object.
     * @return object post
     */
    public static function from_record($record) {
        $id = null;
        if (object_property_exists($record, 'id') && $record->id) {
            $id = $record->id;
        }

        $discussion = null;
        if (object_property_exists($record, 'discussion') && $record->discussion) {
            $discussion = $record->discussion;
        }

        $parent = null;
        if (object_property_exists($record, 'parent') && $record->parent) {
            $parent = $record->parent;
        }

        $userid = null;
        if (object_property_exists($record, 'userid') && $record->userid) {
            $userid = $record->userid;
        }

        $created = null;
        if (object_property_exists($record, 'created') && $record->created) {
            $created = $record->created;
        }

        $modified = null;
        if (object_property_exists($record, 'modified') && $record->modified) {
            $modified = $record->modified;
        }

        $message = null;
        if (object_property_exists($record, 'message') && $record->message) {
            $message = $record->message;
        }

        $messageformat = null;
        if (object_property_exists($record, 'messageformat') && $record->messageformat) {
            $message = $record->messageformat;
        }

        $attachment = null;
        if (object_property_exists($record, 'attachment') && $record->attachment) {
            $attachment = $record->attachment;
        }

        $mailed = null;
        if (object_property_exists($record, 'mailed') && $record->mailed) {
            $mailed = $record->mailed;
        }

        $reviewed = null;
        if (object_property_exists($record, 'reviewed') && $record->reviewed) {
            $reviewed = $record->reviewed;
        }

        $timereviewed = null;
        if (object_property_exists($record, 'timereviewed') && $record->timereviewed) {
            $timereviewed = $record->timereviewed;
        }

        $instance = new self($id, $discussion, $parent, $userid, $created, $modified, $message, $messageformat, $attachment, $mailed, $reviewed, $timereviewed);

        return $instance;
    }

    // Post Functions:

    /**
     * Adds a new post in an existing discussion.
     * @return bool|int The Id of the post if operation was successful
     * @throws coding_exception
     * @throws dml_exception
     */
    public function moodleoverflow_add_new_post() {
        global $USER, $DB;

        $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $this->discussion));
        $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow));
        $cm = $DB->get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);


        // Add post to the database.
        $DB->insert_record('moodleoverflow_posts', $this);
        // Soll hier die Message extra mit $DB->set_field('moodleoverflow_post...) nochmal gesetzt/eingefügt werden?.
        $this->moodleoverflow_add_attachment($this, $moodleoverflow, $cm);

        if ($this->reviewed) {
            // Update the discussion.
            $DB->set_field('moodleoverflow_discussions', 'timemodified', $this->modified, array('id' => $this->discussion));
            $DB->set_field('moodleoverflow_discussions', 'usermodified', $this->userid, array('id' => $this->discussion));
        }


        // Mark the created post as read if the user is tracking the discussion.
        $cantrack = readtracking::moodleoverflow_can_track_moodleoverflows($moodleoverflow);
        $istracked = readtracking::moodleoverflow_is_tracked($moodleoverflow);
        if ($cantrack && $istracked) {
            readtracking::moodleoverflow_mark_post_read($this->userid, $this);
        }

        // Return the id of the created post.
        return $this->id;
    }

    /**
     * Deletes a single moodleoverflow post.
     *
     * @param object $post                  The post
     * @param bool   $deletechildren        The child posts
     * @param object $cm                    The course module
     * @param object $moodleoverflow        The moodleoverflow
     *
     * @return bool Whether the deletion was successful
     */
    public function moodleoverflow_delete_post($post, $deletechildren, $cm, $moodleoverflow) {

    }

    /**
     * Gets a post with all info ready for moodleoverflow_print_post.
     * Most of these joins are just to get the forum id.
     *
     * @param int $postid
     *
     * @return mixed array of posts or false
     */
    public function moodleoverflow_get_post_full($postid) {

    }


    /**
     * If successful, this function returns the name of the file
     *
     * @param object $post is a full post record, including course and forum
     * @param object $forum
     * @param object $cm
     *
     * @return bool
     */
    public function moodleoverflow_add_attachment($post, $forum, $cm) {

    }

    /**
     * Returns attachments with information for the template
     *
     * @param object $post
     * @param object $cm
     *
     * @return array
     */
    public function moodleoverflow_get_attachments($post, $cm) {

    }

    /**
     * Prints a moodleoverflow post.
     * @param object $post
     * @param object $discussion
     * @param object $moodleoverflow
     * @param object $cm
     * @param object $course
     * @param object $ownpost
     * @param bool $link
     * @param string $footer
     * @param string $highlight
     * @param bool $postisread
     * @param bool $dummyifcantsee
     * @param bool $istracked
     * @param bool $iscomment
     * @param array $usermapping
     * @param int $level
     * @param bool $multiplemarks setting of multiplemarks
     * @return void|null
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function moodleoverflow_print_post($post, $discussion, $moodleoverflow, $cm, $course,
                                                     $ownpost = false, $link = false,
                                                     $footer = '', $highlight = '', $postisread = null,
                                                     $dummyifcantsee = true, $istracked = false,
                                                     $iscomment = false, $usermapping = [], $level = 0, $multiplemarks = false) {

    }

    /**
     * Prints all posts of the discussion in a nested form.
     *
     * @param object $course         The course object
     * @param object $cm
     * @param object $moodleoverflow The moodleoverflow object
     * @param object $discussion     The discussion object
     * @param object $parent         The object of the parent post
     * @param bool   $istracked      Whether the user tracks the discussion
     * @param array  $posts          Array of posts within the discussion
     * @param bool   $iscomment      Whether the current post is a comment
     * @param array $usermapping
     * @param bool  $multiplemarks
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function moodleoverflow_print_posts_nested($course, &$cm, $moodleoverflow, $discussion, $parent,
                                                             $istracked, $posts, $iscomment = null, $usermapping = [], $multiplemarks = false) {

    }
    
    public function moodleoverflow_get_parentpost($postid) {

    }

    public function moodleoverflow_get_moodleoverflow() {

    }

    public function moodleoverflow_get_discussion() {
        
    }

}
