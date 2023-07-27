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


namespace mod_moodleoverflow\post\post;

// Import namespace from the locallib, needs a check later which namespaces are really needed.
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\review;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\discussion;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/lib.php');
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

    /** @var char Attachment of the post */
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
     *
     * @param object  $record Data object.
     * @return object post instance
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

        $instance = new self($id, $discussion, $parent, $userid, $created, $modified, $message,
                             $messageformat, $attachment, $mailed, $reviewed, $timereviewed);

        return $instance;
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
        $instance = new self($id, $discussion, $parent, $userid, $created, $modified, $message,
                             $messageformat, $attachment, $mailed, $reviewed, $timereviewed, $formattachments);
        return $instance;
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
        $this->id = $DB->insert_record('moodleoverflow_posts', $this);
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
            readtracking::moodleoverflow_mark_post_read($this->userid, $this);
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

            $childposts = $this->moodleoverflow_get_childposts();
            if ($deletechildren && $childposts) {
                foreach ($childposts as $childpost) {
                    $child = $this->from_record($childpost);
                    $child->moodleoverflow_delete_post();
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
                $context = context_module::instance($this->get_coursemodule()->id);
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

                // Get the context module.
                $modulecontext = context_module::instance($this->get_coursemodule()->id);

                // Trigger the post deletion event.
                $params = array(
                    'context' => $modulecontext,
                    'objectid' => $this->id,
                    'other' => array(
                        'discussionid' => $this->discussion,
                        'moodleoverflowid' => $this->get_moodleoverflow()->id
                    )
                );
                if ($this->userid !== $USER->id) {
                    $params['relateduserid'] = $this->userid;
                }
                $event = post_deleted::create($params);
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
     * @param timestamp $time               The time the post was modified (given from the discussion class).
     * @param string    $postmessage        The new message
     * @param object    $messageformat
     * @param object    $formattachments    Information about attachments from the post_form
     *
     * @return true if the post has been edited successfully
     */
    public function moodleoverflow_edit_post($time, $postmessage, $messageformat, $formattachment) {
        global $DB;
        $this->existence_check();

        // Update the attributes.
        $this->modified = $time;
        $this->message = $postmessage;
        $this->messageformat = $messageformat;
        $this->formattachment = $formattachment;    // PLEASE CHECK LATER IF THIS IS NEEDED AFTER WORKING WITH THE POST_FORM CLASS.

        // Update the record in the database.
        $DB->update_record('moodleoverflow_posts', $this);

        // Update the attachments. This happens after the DB update call, as this function changes the DB record as well.
        $this->moodleoverflow_add_attachment();

        // Mark the edited post as read.
        $this->mark_post_read();

        // The post has been edited successfully.
        return true;
    }

    /**
     * // RETHINK THIS FUNCTION.
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

        if (!$this->formattachments) {
            throw new moodle_exception('missingformattachments', 'moodleoverflow');
        }

        if (empty($this->formattachments)) {
            return true;    // Nothing to do.
        }

        $context = context_module::instance($this->get_coursemodule()->id);
        $info = file_get_draft_area_info($this->formattachments);
        $present = ($info['filecount'] > 0) ? '1' : '';
        file_save_draft_area_file($this->formattachments, $context->id, 'mod_moodleoverflow', 'attachment', $this->id,
                                  mod_moodleoverflow_post_form::attachment_options($this->get_moodleoverflow()));
        $DB->set_field('moodleoverflow_post', 'attachment', $present, array('id' => $this->id));
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

        if (empty($this->attachment) || (!$context = context_module::instance($this->get_coursemodule()->id))) {
            return array();
        }

        $attachments = array();
        $fs = get_file_storage();

        // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
        // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
        $file = $fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $this->id, "filename", false);
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
            $this->cmobject = $DB->get_coursemodule_from_instance('moodleoverflow', $this->get_moodleoverflow()->id);
        }

        return $this->cmobject;
    }

    /**
     * Returns the parent post
     * @return object $post
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
     * @return object children/answer posts.
     */
    public function moodleoverflow_get_childposts() {
        global $DB;
        $this->existence_check();

        if ($childposts = $DB->get_records('moodleoverflow_posts', array('parent' => $this->id))) {
            return $childposts;
        }

        return false;
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
            readtracking::moodleoverflow_mark_post_read($USER->id, $this);
        }
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
            throw new moodle_exception('noexistingpost', 'moodleoverflow');
        }
        return true;
    }

    // Big Functions.

    // Print Functions.

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
    public function moodleoverflow_print_posts_nested($course, &$cm, $moodleoverflow, $discussion, $parent, $istracked,
                                                      $posts, $iscomment = null, $usermapping = [], $multiplemarks = false) {

    }

    /**
     * Prints a moodleoverflow post.
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
    public function moodleoverflow_print_post($ownpost = false, $link = false, $footer = '', $highlight = '', $postisread = null,
                                              $dummyifcantsee = true, $istracked = false, $iscomment = false, $usermapping = [],
                                              $level = 0, $multiplemarks = false) {
        global $USER, $CFG, $OUTPUT, $PAGE;
        $this->existence_check();
        // Get important variables.
        $post = $this->moodleoverflow_get_complete_post();
        $discussion = $this->get_discussion();
        $moodleoverflow = $this->get_moodleoverflow();
        $cm = $DB->get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $course = $DB->get_record('course', array('id' => $moodleoverflow->course));

        // Add ratings to the post.
        $postratings = $this->moodleoverflow_get_post_ratings();
        $post->upvotes = $postratings->upvotes;
        $post->downvotes = $postratings->downvotes;
        $post->votesdifference = $postratings->votesdifference;
        $post->markedhelpful = $postratings->markedhelpful;
        $post->markedsolution = $postratings->markedsolution;

        // Add other important stuff.
        $post->subject = $this->subject;

        // Requiere the filelib.
        require_once($CFG->libdir . '/filelib.php');

        // String cahe.
        static $str;

        // Print the 'unread' only on time.
        static $firstunreadanchorprinted = false;

        // Declare the modulecontext.
        $modulecontext = context_module::instance($cm->id);

        // Add some information to the post.
        $post->courseid = $course->id;
        $post->moodleoverflowid = $moodleoverflow->id;
        $mcid = $modulecontext->id;
        $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $mcid,
                                                      'mod_moodleoverflow', 'post', $post->id);

        // Check if the user has the capability to see posts.
        if (!moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm)) {
            // No dummy message is requested.
            if (!$dummyifcantsee) {
                echo '';
                return;
            }

            // Include the renderer to display the dummy content.
            $renderer = $PAGE->get_renderer('mod_moodleoverflow');

            // Collect the needed data being submitted to the template.
            $mustachedata = new stdClass();

            // Print the template.
            return $renderer->render_post_dummy_cantsee($mustachedata);
        }

        // Check if the strings have been cached.
        if (empty($str)) {
            $str = new stdClass();
            $str->edit = get_string('edit', 'moodleoverflow');
            $str->delete = get_string('delete', 'moodleoverflow');
            $str->reply = get_string('reply', 'moodleoverflow');
            $str->replyfirst = get_string('replyfirst', 'moodleoverflow');
            $str->parent = get_string('parent', 'moodleoverflow');
            $str->markread = get_string('markread', 'moodleoverflow');
            $str->markunread = get_string('markunread', 'moodleoverflow');
            $str->marksolved = get_string('marksolved', 'moodleoverflow');
            $str->alsomarksolved = get_string('alsomarksolved', 'moodleoverflow');
            $str->marknotsolved = get_string('marknotsolved', 'moodleoverflow');
            $str->markhelpful = get_string('markhelpful', 'moodleoverflow');
            $str->alsomarkhelpful = get_string('alsomarkhelpful', 'moodleoverflow');
            $str->marknothelpful = get_string('marknothelpful', 'moodleoverflow');
        }

        // Get the current link without unnecessary parameters.
        $discussionlink = new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $post->discussion));

        // Build the object that represents the posting user.
        $postinguser = new stdClass();
        if ($CFG->branch >= 311) {
            $postinguserfields = \core_user\fields::get_picture_fields();
        } else {
            $postinguserfields = explode(',', user_picture::fields());
        }
        $postinguser = username_load_fields_from_object($postinguser, $post, null, $postinguserfields);

        // Post was anonymized.
        if (anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)) {
            $postinguser->id = null;
            if ($post->userid == $USER->id) {
                $postinguser->fullname = get_string('anonym_you', 'mod_moodleoverflow');
                $postinguser->profilelink = new moodle_url('/user/view.php', array('id' => $post->userid, 'course' => $course->id));
            } else {
                $postinguser->fullname = $usermapping[(int) $post->userid];
                $postinguser->profilelink = null;
            }
        } else {
            $postinguser->fullname = fullname($postinguser, capabilities::has('moodle/site:viewfullnames', $modulecontext));
            $postinguser->profilelink = new moodle_url('/user/view.php', array('id' => $post->userid, 'course' => $course->id));
            $postinguser->id = $post->userid;
        }

        // Prepare an array of commands.
        $commands = array();

        // Create a permalink.
        $permalink = new moodle_url($discussionlink);
        $permalink->set_anchor('p' . $post->id);

        // Check if multiplemarks are allowed. If so, check if there are already marked posts.
        $helpfulposts = false;
        $solvedposts = false;
        if ($multiplemarks) {
            $helpfulposts = \mod_moodleoverflow\ratings::moodleoverflow_discussion_is_solved($discussion->id, false);
            $solvedposts = \mod_moodleoverflow\ratings::moodleoverflow_discussion_is_solved($discussion->id, true);
        }

        // If the user has started the discussion, he can mark the answer as helpful.
        $canmarkhelpful = (($USER->id == $discussion->userid) && ($USER->id != $post->userid) &&
                           ($iscomment != $post->parent) && !empty($post->parent));
        if ($canmarkhelpful) {
            // When the post is already marked, remove the mark instead.
            $link = '/mod/moodleoverflow/discussion.php';
            if ($post->markedhelpful) {
                $commands[] = html_writer::tag('a', $str->marknothelpful, array('class' => 'markhelpful onlyifreviewed',
                                                                                'role' => 'button',
                                                                                'data-moodleoverflow-action' => 'helpful'));
            } else {
                // If there are already marked posts, change the string of the button.
                if ($helpfulposts) {
                    $commands[] = html_writer::tag('a', $str->alsomarkhelpful, array('class' => 'markhelpful onlyifreviewed',
                                                                                     'role' => 'button',
                                                                                     'data-moodleoverflow-action' => 'helpful'));
                } else {
                    $commands[] = html_writer::tag('a', $str->markhelpful, array('class' => 'markhelpful onlyifreviewed',
                                                                                 'role' => 'button',
                                                                                 'data-moodleoverflow-action' => 'helpful'));
                }
            }
        }

        // A teacher can mark an answer as solved.
        $canmarksolved = (($iscomment != $post->parent) && !empty($post->parent) &&
                           capabilities::has(capabilities::MARK_SOLVED, $modulecontext));
        if ($canmarksolved) {
            // When the post is already marked, remove the mark instead.
            $link = '/mod/moodleoverflow/discussion.php';
            if ($post->markedsolution) {
                $commands[] = html_writer::tag('a', $str->marknotsolved, array('class' => 'marksolved onlyifreviewed',
                                                                               'role' => 'button',
                                                                               'data-moodleoverflow-action' => 'solved'));
            } else {
                // If there are already marked posts, change the string of the button.
                if ($solvedposts) {
                    $commands[] = html_writer::tag('a', $str->alsomarksolved, array('class' => 'marksolved onlyifreviewed',
                                                                                    'role' => 'button',
                                                                                    'data-moodleoverflow-action' => 'solved'));
                } else {
                    $commands[] = html_writer::tag('a', $str->marksolved, array('class' => 'marksolved onlyifreviewed',
                                                                                'role' => 'button',
                                                                                'data-moodleoverflow-action' => 'solved'));
                }
            }
        }

        // Calculate the age of the post.
        $age = time() - $post->created;

        // Make a link to edit your own post within the given time and not already reviewed.
        if (($ownpost && ($age < get_config('moodleoverflow', 'maxeditingtime'))
                      && (!review::should_post_be_reviewed($post, $moodleoverflow) || !$post->reviewed))
            || capabilities::has(capabilities::EDIT_ANY_POST, $modulecontext)) {

            $editurl = new moodle_url('/mod/moodleoverflow/post.php', array('edit' => $post->id));
            $commands[] = array('url' => $editurl, 'text' => $str->edit);
        }

        // Give the option to delete a post.
        $notold = ($age < get_config('moodleoverflow', 'maxeditingtime'));
        if (($ownpost && $notold && capabilities::has(capabilities::DELETE_OWN_POST, $modulecontext)) ||
            capabilities::has(capabilities::DELETE_ANY_POST, $modulecontext)) {

            $link = '/mod/moodleoverflow/post.php';
            $commands[] = array('url' => new moodle_url($link, array('delete' => $post->id)), 'text' => $str->delete);
        }

        // Give the option to reply to a post.
        if (moodleoverflow_user_can_post($modulecontext, $post, false)) {

            $attributes = [
                    'class' => 'onlyifreviewed'
            ];

            // Answer to the parent post.
            if (empty($post->parent)) {
                $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $post->id));
                $commands[] = array('url' => $replyurl, 'text' => $str->replyfirst, 'attributes' => $attributes);

                // If the post is a comment, answer to the parent post.
            } else if (!$iscomment) {
                $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $post->id));
                $commands[] = array('url' => $replyurl, 'text' => $str->reply, 'attributes' => $attributes);

                // Else simple respond to the answer.
            } else {
                $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $iscomment));
                $commands[] = array('url' => $replyurl, 'text' => $str->reply, 'attributes' => $attributes);
            }
        }

        // Begin of mustache data collecting.

        // Initiate the output variables.
        $mustachedata = new stdClass();
        $mustachedata->istracked = $istracked;
        $mustachedata->isread = false;
        $mustachedata->isfirstunread = false;
        $mustachedata->isfirstpost = false;
        $mustachedata->iscomment = (!empty($post->parent) && ($iscomment == $post->parent));
        $mustachedata->permalink = $permalink;

        // Get the ratings.
        $mustachedata->votes = $post->upvotes - $post->downvotes;

        // Check if the post is marked.
        $mustachedata->markedhelpful = $post->markedhelpful;
        $mustachedata->markedsolution = $post->markedsolution;

        // Did the user rated this post?
        $rating = \mod_moodleoverflow\ratings::moodleoverflow_user_rated($post->id);

         // Initiate the variables.
        $mustachedata->userupvoted = false;
        $mustachedata->userdownvoted = false;
        $mustachedata->canchange = $USER->id != $post->userid;

        // Check the actual rating.
        if ($rating) {

            // Convert the object.
            $rating = $rating->rating;

            // Did the user upvoted or downvoted this post?
            // The user upvoted the post.
            if ($rating == 1) {
                $mustachedata->userdownvoted = true;
            } else if ($rating == 2) {
                $mustachedata->userupvoted = true;
            }
        }

        // Check the reading status of the post.
        $postclass = '';
        if ($istracked) {
            if ($postisread) {
                $postclass .= ' read';
                $mustachedata->isread = true;
            } else {
                $postclass .= ' unread';

                // Anchor the first unread post of a discussion.
                if (!$firstunreadanchorprinted) {
                    $mustachedata->isfirstunread = true;
                    $firstunreadanchorprinted = true;
                }
            }
        }
        if ($post->markedhelpful) {
            $postclass .= ' markedhelpful';
        }
        if ($post->markedsolution) {
            $postclass .= ' markedsolution';
        }
        $mustachedata->postclass = $postclass;

        // Is this the firstpost?
        if (empty($post->parent)) {
            $mustachedata->isfirstpost = true;
        }

        // Create an element for the user which posted the post.
        $postbyuser = new stdClass();
        $postbyuser->post = $post->subject;

        // Anonymization already handled in $postinguser->fullname.
        $postbyuser->user = $postinguser->fullname;

        $mustachedata->discussionby = get_string('postbyuser', 'moodleoverflow', $postbyuser);

        // Set basic variables of the post.
        $mustachedata->postid = $post->id;
        $mustachedata->subject = format_string($post->subject);

        // Post was anonymized.
        if (!anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)) {
            // User picture.
            $mustachedata->picture = $OUTPUT->user_picture($postinguser, ['courseid' => $course->id]);
        }

        // The rating of the user.
        if (anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)) {
            $postuserrating = null;
        } else {
            $postuserrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $postinguser->id);
        }

        // The name of the user and the date modified.
        $mustachedata->bydate = userdate($post->modified);
        $mustachedata->byshortdate = userdate($post->modified, get_string('strftimedatetimeshort', 'core_langconfig'));
        $mustachedata->byname = $postinguser->profilelink ?
            html_writer::link($postinguser->profilelink, $postinguser->fullname)
            : $postinguser->fullname;
        $mustachedata->byrating = $postuserrating;
        $mustachedata->byuserid = $postinguser->id;
        $mustachedata->showrating = $postuserrating !== null;
        if (get_config('moodleoverflow', 'allowdisablerating') == 1) {
            $mustachedata->showvotes = $moodleoverflow->allowrating;
            $mustachedata->showreputation = $moodleoverflow->allowreputation;
        } else {
            $mustachedata->showvotes = MOODLEOVERFLOW_RATING_ALLOW;
            $mustachedata->showreputation = MOODLEOVERFLOW_REPUTATION_ALLOW;
        }
        $mustachedata->questioner = $post->userid == $discussion->userid ? 'questioner' : '';

        // Set options for the post.
        $options = new stdClass();
        $options->para = false;
        $options->trusted = false;
        $options->context = $modulecontext;

        $reviewdelay = get_config('moodleoverflow', 'reviewpossibleaftertime');
        $mustachedata->reviewdelay = format_time($reviewdelay);
        $mustachedata->needsreview = !$post->reviewed;
        $reviewable = time() - $post->created > $reviewdelay;
        $mustachedata->canreview = capabilities::has(capabilities::REVIEW_POST, $modulecontext);
        $mustachedata->withinreviewperiod = $reviewable;

        // Prepare the post.
        $mustachedata->postcontent = format_text($post->message, $post->messageformat, $options, $course->id);

        // Load the attachments.
        $mustachedata->attachments = get_attachments($post, $cm);

        // Output the commands.
        $commandhtml = array();
        foreach ($commands as $command) {
            if (is_array($command)) {
                $commandhtml[] = html_writer::link($command['url'], $command['text'], $command['attributes'] ?? null);
            } else {
                $commandhtml[] = $command;
            }
        }
        $mustachedata->commands = implode('', $commandhtml);

        // Print a footer if requested.
        $mustachedata->footer = $footer;

        // Mark the forum post as read.
        if ($istracked && !$postisread) {
            readtracking::moodleoverflow_mark_post_read($USER->id, $post);
        }

        $mustachedata->iscomment = $level == 2;

        // Include the renderer to display the dummy content.
        $renderer = $PAGE->get_renderer('mod_moodleoverflow');

        // Render the different elements.
        return $renderer->render_post($mustachedata);
    }

}
