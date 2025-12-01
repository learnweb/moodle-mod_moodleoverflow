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

use coding_exception;
use context_module;
use core\output\html_writer;
use core_user\fields;
use dml_exception;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\event\post_deleted;
use mod_moodleoverflow\ratings;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\discussion\discussion;
use mod_moodleoverflow_post_form;
use moodle_exception;
use moodle_url;
use stdClass;

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

    /** @var ?int The post ID */
    private ?int $id;

    /** @var int The corresponding discussion ID */
    private int $discussion;

    /** @var int The parent post ID */
    private int $parent;

    /** @var int The ID of the User who wrote the post */
    private int $userid;

    /** @var int Creation timestamp */
    public int $created;

    /** @var int Modification timestamp */
    public int $modified;

    /** @var string The message (content) of the post */
    public string $message;

    /** @var int  The message format*/
    public int $messageformat;

    /** @var string Attachment of the post */
    public string $attachment;

    /** @var int Mailed status*/
    public int $mailed;

    /** @var int Review status */
    public int $reviewed;

    /** @var ?int The time when the post was reviewed*/
    public ?int $timereviewed;

    // Not database related functions.

    /** @var ?int This variable is optional, it contains important information for the add_attachment function */
    public ?int $formattachments;

    /** @var string The subject/title of the Discussion */
    public string $subject;

    /** @var discussion The discussion where the post is located */
    public discussion $discussionobject;

    /** @var object The Moodleoverflow where the post is located*/
    public object $moodleoverflowobject;

    /** @var object The course module object */
    public object $cmobject;

    /** @var ?object The parent post of an answerpost */
    public ?object $parentpost;

    // Constructors and other builders.

    /**
     * Constructor to make a new post.
     * @param ?int $id The post ID.
     * @param int $discussion The discussion ID.
     * @param int $parent The parent post ID.
     * @param int $userid The user ID that created the post.
     * @param int $created Creation timestamp
     * @param int $modified Modification timestamp
     * @param string $message The message (content) of the post
     * @param int $messageformat The message format
     * @param string $attachment Attachment of the post
     * @param int $mailed Mailed status
     * @param int $reviewed Review status
     * @param ?int $timereviewed The time when the post was reviewed
     * @param ?int $formattachments Information about attachments of the post_form
     */
    public function __construct(
        ?int $id,
        int $discussion,
        int $parent,
        int $userid,
        int $created,
        int $modified,
        string $message,
        int $messageformat,
        string $attachment,
        int $mailed,
        int $reviewed,
        ?int $timereviewed,
        ?int $formattachments = null
    ) {
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
     * @param object $record Data object.
     * @return post post instance
     */
    public static function from_record(object $record): post {
        $id = !empty($record->id) ? $record->id : null;
        $discussion = !empty($record->discussion) ? $record->discussion : 0;
        $parent = !empty($record->parent) ? $record->parent : 0;
        $userid = !empty($record->userid) ? $record->userid : 0;
        $created = !empty($record->created) ? $record->created : 0;
        $modified = !empty($record->modified) ? $record->modified : 0;
        $message = !empty($record->message) ? $record->message : '';
        $messageformat = !empty($record->messageformat) ? $record->messageformat : 0;
        $attachment = !empty($record->attachment) ? $record->attachment : '';
        $mailed = !empty($record->mailed) ? $record->mailed : 0;
        $reviewed = !empty($record->reviewed) ? $record->reviewed : 1;
        $timereviewed = !empty($record->timereviewed) ? $record->timereviewed : null;

        return new self(
            $id,
            $discussion,
            $parent,
            $userid,
            $created,
            $modified,
            $message,
            $messageformat,
            $attachment,
            $mailed,
            $reviewed,
            $timereviewed
        );
    }

    /**
     * Function to make a new post without specifying the Post ID.
     *
     * @param int $discussion         The discussion ID.
     * @param int $parent             The parent post ID.
     * @param int $userid             The user ID that created the post.
     * @param int $created            Creation timestamp
     * @param int $modified           Modification timestamp
     * @param string $message            The message (content) of the post
     * @param int $messageformat      The message format
     * @param string $attachment         Attachment of the post
     * @param int $mailed             Mailed status
     * @param int $reviewed           Review status
     * @param ?int $timereviewed       The time when the post was reviewed
     * @param ?int $formattachments    Information about attachments from the post_form
     *
     * @return object post object without id
     */
    public static function construct_without_id(
        int $discussion,
        int $parent,
        int $userid,
        int $created,
        int $modified,
        string $message,
        int $messageformat,
        string $attachment,
        int $mailed,
        int $reviewed,
        ?int $timereviewed,
        ?int $formattachments = null
    ): object {
        $id = null;
        return new self(
            $id,
            $discussion,
            $parent,
            $userid,
            $created,
            $modified,
            $message,
            $messageformat,
            $attachment,
            $mailed,
            $reviewed,
            $timereviewed,
            $formattachments
        );
    }

    // Post Functions.

    /**
     * Adds a new post in an existing discussion.
     * @return bool|int|null The Id of the post if operation was successful
     * @throws coding_exception
     * @throws dml_exception|moodle_exception
     */
    public function moodleoverflow_add_new_post(): bool|int|null {
        global $DB;

        // Add post to the database.
        $this->id = $DB->insert_record('moodleoverflow_posts', $this->build_db_object());

        // Save draft files to permanent file area.
        $context = \context_module::instance($this->get_coursemodule()->id);
        $draftid = file_get_submitted_draft_itemid('introeditor');
        $this->message = file_save_draft_area_files(
            $draftid,
            $context->id,
            'mod_moodleoverflow',
            'post',
            $this->id,
            mod_moodleoverflow_post_form::editor_options($context, $this->id),
            $this->message
        );
        $DB->update_record('moodleoverflow_posts', $this->build_db_object());

        // Update the attachments. This happens after the DB update call, as this function changes the DB record as well.
        $this->moodleoverflow_add_attachment();

        if ($this->reviewed) {
            // Update the discussion.
            $DB->set_field('moodleoverflow_discussions', 'timemodified', $this->modified, ['id' => $this->discussion]);
            $DB->set_field('moodleoverflow_discussions', 'usermodified', $this->userid, ['id' => $this->discussion]);
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
     * @param bool $deletechildren The child posts
     *
     * @return bool Whether the deletion was successful or not
     * @throws moodle_exception
     */
    public function moodleoverflow_delete_post(bool $deletechildren): bool {
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
                    $child->moodleoverflow_delete_post(true);
                }
            }

            // Delete the ratings.
            $DB->delete_records('moodleoverflow_ratings', ['postid' => $this->id]);

            // Delete the post.
            if ($DB->delete_records('moodleoverflow_posts', ['id' => $this->id])) {
                // Delete the read records.
                readtracking::moodleoverflow_delete_read_records(-1, $this->id);

                // Delete the attachments.
                $fs = get_file_storage();
                $context = \context_module::instance($coursemoduleid);
                $attachments = $fs->get_area_files(
                    $context->id,
                    'mod_moodleoverflow',
                    'attachment',
                    $this->id,
                    "filename",
                    true
                );
                foreach ($attachments as $attachment) {
                    // Get file.
                    $file = $fs->get_file(
                        $context->id,
                        'mod_moodleoverflow',
                        'attachment',
                        $this->id,
                        $attachment->get_filepath(),
                        $attachment->get_filename()
                    );
                    // Delete it if it exists.
                    if ($file) {
                        $file->delete();
                    }
                }

                // Trigger the post deletion event.
                $params = [
                    'context' => $context,
                    'objectid' => $this->id,
                    'other' => [
                        'discussionid' => $this->discussion,
                        'moodleoverflowid' => $this->get_moodleoverflow()->id,
                    ],
                ];
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
     * @param int $time The time the post was modified (given from the discussion class).
     * @param string $postmessage The new message
     * @param int $messageformat
     * @param ?int $formattachments Information about attachments from the post_form
     *
     * @return true if the post has been edited successfully
     * @throws moodle_exception
     */
    public function moodleoverflow_edit_post(int $time, string $postmessage, int $messageformat, ?int $formattachments): bool {
        global $DB;
        $this->existence_check();

        // Update the attributes.
        $this->modified = $time;
        $this->messageformat = $messageformat;
        $this->formattachments = $formattachments;

        // Update the message and save draft files to permanent file area.
        $context = \context_module::instance($this->get_coursemodule()->id);
        $draftid = file_get_submitted_draft_itemid('introeditor');
        $this->message = file_save_draft_area_files(
            $draftid,
            $context->id,
            'mod_moodleoverflow',
            'post',
            $this->id,
            mod_moodleoverflow_post_form::editor_options($context, $this->id),
            $postmessage
        );

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
     * // NOTE: This function replaces the get_post_full() function but is not used until the print and print-related function for
     * // printing the discussion and a post are adapted to the new post and discussion class.
     * Gets a post with all info ready for moodleoverflow_print_post.
     * Most of these joins are just to get the forum id.
     *
     * @return object array of posts or false
     * @throws moodle_exception
     */
    public function moodleoverflow_get_complete_post(): object {
        global $DB;
        $this->existence_check();

        $allnames = fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT p.*, d.moodleoverflow, $allnames, u.email, u.picture, u.imagealt
                FROM {moodleoverflow_posts} p
                    JOIN {moodleoverflow_discussions} d ON p.discussion = d.id
                LEFT JOIN {user} u ON p.userid = u.id
                    WHERE p.id = " . $this->id . " ;";

        $post = $DB->get_record_sql($sql);
        if ($post->userid == 0) {
            $post->message = get_string('privacy:anonym_post_message', 'mod_moodleoverflow');
        }
        return $post;
    }

    /**
     * If successful, this function returns the name of the file
     *
     * @return bool
     * @throws moodle_exception
     */
    public function moodleoverflow_add_attachment(): void {
        global $DB;
        $this->existence_check();

        if (empty($this->formattachments)) {
            return;    // Nothing to do.
        }

        $context = \context_module::instance($this->get_coursemodule()->id);
        $info = file_get_draft_area_info($this->formattachments);
        $present = ($info['filecount'] > 0) ? '1' : '';
        file_save_draft_area_files(
            $this->formattachments,
            $context->id,
            'mod_moodleoverflow',
            'attachment',
            $this->id,
            \mod_moodleoverflow_post_form::attachment_options($this->get_moodleoverflow())
        );
        $DB->set_field('moodleoverflow_posts', 'attachment', $present, ['id' => $this->id]);
    }

    /**
     * Returns attachments with information for the template
     *
     * @return array
     * @throws moodle_exception
     */
    public function moodleoverflow_get_attachments(): array {
        global $CFG, $OUTPUT;
        $this->existence_check();

        if (empty($this->attachment) || (!$context = \context_module::instance($this->get_coursemodule()->id))) {
            return [];
        }

        $attachments = [];
        $fs = get_file_storage();

        // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
        // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
        $files = $fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $this->id, "filename", false);
        if ($files) {
            $i = 0;
            foreach ($files as $file) {
                $attachments[$i] = [];
                $attachments[$i]['filename'] = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $iconimage = $OUTPUT->pix_icon(
                    file_file_icon($file),
                    get_mimetype_description($file),
                    'moodle',
                    ['class' => 'icon']
                );
                $path = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $attachments[$i]['icon'] = $iconimage;
                $attachments[$i]['filepath'] = $path;

                if (in_array($mimetype, ['image/gif', 'image/jpeg', 'image/png'])) {
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

    /**
     * Get a link to the users profile.
     * Returns a html link embedded in the users name.
     * @return moodle_url
     * @throws moodle_exception
     */
    public function get_userlink(): string {
        global $USER, $DB;
        $this->existence_check();

        $courseid = $this->get_discussion()->get_courseid();
        $modulecontext = context_module::instance($this->get_coursemodule()->id);
        $userid = $this->get_userid();

        if (anonymous::is_post_anonymous($this->get_discussion()->get_db_object(), $this->get_moodleoverflow(), $userid)) {
            if ($userid == $USER->id) {
                $fullname = get_string('anonym_you', 'mod_moodleoverflow');
                $profilelink = new moodle_url('/user/view.php', ['id' => $userid, 'course' => $courseid]);
                return html_writer::link($profilelink, $fullname);
            } else {
                $usermapping = anonymous::get_userid_mapping($this->get_moodleoverflow(), $this->get_discussionid());
                return $usermapping[$userid];
            }
        }
        $user = $DB->get_record('user', ['id' => $userid]);
        $fullname = fullname($user, capabilities::has('moodle/site:viewfullnames', $modulecontext));
        $profilelink = new moodle_url('/user/view.php', ['id' => $userid, 'course' => $courseid]);
        return html_writer::link($profilelink, $fullname);
    }

    /**
     * Returns the post message in a formatted way ready to display.
     * @return string
     * @throws moodle_exception
     */
    public function get_message_formatted(): string {
        $context = context_module::instance($this->get_coursemodule()->id);
        $message = file_rewrite_pluginfile_urls(
            $this->message,
            'pluginfile.php',
            $context->id,
            'mod_moodleoverflow',
            'post',
            $this->get_id(),
            ['includetoken' => true]
        );
        $options = new stdClass();
        $options->para = true;
        $options->context = $context;
        return format_text($message, $this->messageformat, $options);
    }

    // Getter.

    /**
     * Getter for the postid
     * @return ?int $this->id    The post ID.
     * @throws moodle_exception
     */
    public function get_id(): ?int {
        $this->existence_check();
        return $this->id;
    }

    /**
     * Getter for the discussionid
     * @return int $this->discussion    The ID of the discussion where the post is located.
     * @throws moodle_exception
     */
    public function get_discussionid(): int {
        $this->existence_check();
        return $this->discussion;
    }

    /**
     * Getter for the parentid
     * @return int $this->parent    The ID of the parent post.
     * @throws moodle_exception
     */
    public function get_parentid(): int {
        $this->existence_check();
        return $this->parent;
    }

    /**
     * Getter for the userid
     * @return int $this->userid    The ID of the user who wrote the post.
     * @throws moodle_exception
     */
    public function get_userid(): int {
        $this->existence_check();
        return $this->userid;
    }

    /**
     * Returns the moodleoverflow where the post is located.
     * @return object $moodleoverflowobject
     * @throws moodle_exception
     */
    public function get_moodleoverflow(): object {
        global $DB;
        $this->existence_check();

        if (empty($this->moodleoverflowobject)) {
            $discussion = $this->get_discussion();
            $this->moodleoverflowobject = $DB->get_record('moodleoverflow', ['id' => $discussion->get_moodleoverflowid()]);
        }

        return $this->moodleoverflowobject;
    }

    /**
     * Returns the discussion where the post is located.
     *
     * @return discussion $discussionobject.
     * @throws moodle_exception
     */
    public function get_discussion(): discussion {
        global $DB;
        $this->existence_check();

        if (empty($this->discussionobject)) {
            $record = $DB->get_record('moodleoverflow_discussions', ['id' => $this->discussion]);
            $this->discussionobject = discussion::from_record($record);
        }
        return $this->discussionobject;
    }

    /**
     * Returns the coursemodule
     *
     * @return object $cmobject
     * @throws moodle_exception
     */
    public function get_coursemodule(): object {
        $this->existence_check();

        if (empty($this->cmobject)) {
            $this->cmobject = \get_coursemodule_from_instance('moodleoverflow', $this->get_moodleoverflow()->id);
        }

        return $this->cmobject;
    }

    /**
     * Returns the parent post
     * @return post|null $post|false
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function moodleoverflow_get_parentpost(): post|null {
        global $DB;
        $this->existence_check();

        if ($this->parent == 0) {
            // This post is the parent post.
            $this->parentpost = null;
            return null;
        }

        if (empty($this->parentpost)) {
            $parentpostrecord = $DB->get_record('moodleoverflow_post', ['id' => $this->parent]);
            $this->parentpost = $this->from_record($parentpostrecord);
        }
        return $this->parentpost;
    }

    /**
     * Returns children posts (answers) as DB-records.
     *
     * @return array children/answer posts.
     * @throws moodle_exception
     */
    public function moodleoverflow_get_childposts(): array {
        global $DB;
        $this->existence_check();

        if ($childposts = $DB->get_records('moodleoverflow_posts', ['parent' => $this->id])) {
            return $childposts;
        }

        return [];
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

    // Helper Functions.

    /**
     * Calculate the ratings of a post.
     *
     * @return object $ratingsobject.
     * @throws moodle_exception
     */
    public function moodleoverflow_get_post_ratings(): object {
        $this->existence_check();

        $discussionid = $this->get_discussion()->get_id();
        $postratings = ratings::moodleoverflow_get_ratings_by_discussion($discussionid, $this->id);

        return (object) [
            'upvotes' => $postratings->upvotes,
            'downvotes' => $postratings->downvotes,
            'votesdifference' => $postratings->upvotes - $postratings->downvotes,
            'markedhelpful' => $postratings->ishelpful,
            'markedsolution' => $postratings->issolved,
        ];
    }

    /**
     * Marks the post as read if the user is tracking the discussion.
     * Uses function from mod_moodleoverflow\readtracking.
     * @return void
     * @throws moodle_exception
     */
    public function mark_post_read(): void {
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
    private function build_db_object(): object {
        $dbobject = new stdClass();
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

    /**
     * Count all replies of a post.
     *
     * @param bool $onlyreviewed Whether to count only reviewed posts.
     * @return int Amount of replies
     * @throws dml_exception
     */
    public function moodleoverflow_count_replies(bool $onlyreviewed): int {
        global $DB;

        $conditions = ['parent' => $this->id] + ($onlyreviewed ? ['reviewed' => '1'] : []);

        // Return the amount of replies.
        return $DB->count_records('moodleoverflow_posts', $conditions);
    }

    // Security.

    /**
     * Makes sure that the instance exists in the database. Every function in this class requires this check
     * (except the function that adds a post to the database)
     *
     * @return void
     * @throws moodle_exception
     */
    private function existence_check(): void {
        if (empty($this->id)) {
            throw new moodle_exception('noexistingpost', 'moodleoverflow');
        }
    }
}
