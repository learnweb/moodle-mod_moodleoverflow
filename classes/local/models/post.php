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

namespace mod_moodleoverflow\local\models;

use cm_info;
use core\exception\coding_exception;
use context_module;
use core\output\renderer_base;
use core_files\external\stored_file_exporter;
use dml_exception;
use mod_moodleoverflow\event\post_created;
use mod_moodleoverflow\event\post_deleted;
use mod_moodleoverflow\event\post_updated;
use mod_moodleoverflow\local\service\readtracking;
use core\exception\moodle_exception;
use moodle_url;
use stdClass;
use Throwable;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Class that represents a post.
 *
 * Most of the functions in this class are called by the discussion model. The discussion class
 * manages posts in a moodleoverflow and works like a toplevel class for the post class. If you want to manipulate
 * (delete, add, edit) posts, please call the functions from the discussion class. To read and obtain information about posts
 * you are free to choose.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post {
    // Not database related attributes.

    /** @var discussion|null The discussion of the post, shared with the other posts that were loaded with it */
    private ?discussion $discussionobject = null;

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
     */
    public function __construct(
        /** @var ?int The post ID */
        private ?int $id,
        /** @var int The corresponding discussion ID */
        private int $discussion,
        /** @var int The parent post ID */
        private int $parent,
        /** @var int The ID of the User who wrote the post */
        private int $userid,
        /** @var int Creation timestamp */
        public int $created,
        /** @var int Modification timestamp */
        public int $modified,
        /** @var string The message (content) of the post */
        public string $message,
        /** @var int The message format */
        public int $messageformat,
        /** @var string Attachment of the post */
        public string $attachment,
        /** @var int Mailed status */
        public int $mailed,
        /** @var int Review status */
        public int $reviewed,
        /** @var ?int The time when the post was reviewed */
        public ?int $timereviewed
    ) {
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
        $reviewed = $record->reviewed ?? 1;
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
        ?int $timereviewed
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
            $timereviewed
        );
    }

    /**
     * Build post instance from post id.
     * @param int $id
     * @return self
     * @throws dml_exception
     */
    public static function from_id(int $id): self {
        global $DB;
        return self::from_record($DB->get_record('moodleoverflow_posts', ['id' => $id], '*', MUST_EXIST));
    }

    // Post Functions.

    /**
     * Adds a new post in an existing discussion.
     * @param ?int $draftitemid
     * @param ?int $attachmentdraftid
     * @return bool|int|null The Id of the post if operation was successful
     * @throws coding_exception
     * @throws dml_exception|moodle_exception
     */
    public function add(?int $draftitemid = null, ?int $attachmentdraftid = null): bool|int|null {
        global $DB;

        // Add post to the database.
        $this->id = $DB->insert_record('moodleoverflow_posts', $this->build_db_object());

        // Save draft files to permanent file area.
        $this->save_draft_files($draftitemid);
        $this->add_attachment($attachmentdraftid);

        $DB->update_record('moodleoverflow_posts', $this->build_db_object());

        // Trigger Event. The question is created with its discussion, which fires discussion_created.
        if ($this->parent != 0) {
            post_created::create([
                'context' => $this->get_context(),
                'objectid' => $this->id,
                'other' => ['discussionid' => $this->discussion, 'moodleoverflowid' => $this->get_moodleoverflow()->id],
            ])->trigger();
        }
        return $this->id;
    }

    /**
     * Deletes this post with its replies, their ratings, read records, digest entries and files (attachments, embedded files).
     *
     * The discussion updates its last post afterward; the question is only deleted through discussion::delete().
     *
     * @param int $userid The user who deletes the post.
     * @return void
     * @throws Throwable
     */
    public function delete(int $userid): void {
        global $DB;
        $this->existence_check();
        $context = $this->get_context();
        $modflowid = $this->get_moodleoverflow()->id;

        $transaction = $DB->start_delegated_transaction();
        try {
            // Delete the replies.
            foreach ($this->get_childposts() as $record) {
                self::from_record($record)->delete($userid);
            }

            // Delete all associated ratings.
            $DB->delete_records('moodleoverflow_ratings', ['postid' => $this->id]);
            readtracking::delete_read_records(postid: $this->id);
            $DB->delete_records('moodleoverflow_digest_queue', ['postid' => $this->id]);

            // Delete all files.
            $fs = get_file_storage();
            foreach (['attachment', 'post'] as $filearea) {
                $fs->delete_area_files($context->id, 'mod_moodleoverflow', $filearea, $this->id);
            }

            // Delete the post.
            $DB->delete_records('moodleoverflow_posts', ['id' => $this->id]);

            // The question is deleted with its discussion, which fires discussion_deleted (as on creation).
            if ($this->parent != 0) {
                post_deleted::create([
                    'context' => $context,
                    'objectid' => $this->id,
                    'other' => ['discussionid' => $this->discussion, 'moodleoverflowid' => $modflowid],
                    ...($this->userid !== $userid ? ['relateduserid' => $this->userid] : []),
                ])->trigger();
            }
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }

        // Working with this instance is not possible anymore.
        $this->id = null;
    }

    /**
     * Edits the message and the files of this post.
     *
     * @param int $userid The user who edits the post.
     * @param post_content $content The new content; its subject belongs to the discussion and is not read here.
     * @return void
     * @throws moodle_exception
     */
    public function edit(int $userid, post_content $content): void {
        global $DB;
        $this->existence_check();

        $this->modified = time();
        $this->message = $content->message;
        $this->messageformat = $content->messageformat;
        $this->save_draft_files($content->messagedraftid);
        $this->add_attachment($content->attachmentdraftid);

        $DB->update_record('moodleoverflow_posts', $this->build_db_object());

        // Trigger the event.
        post_updated::create([
            'context' => $this->get_context(),
            'objectid' => $this->id,
            'other' => ['discussionid' => $this->discussion, 'moodleoverflowid' => $this->get_moodleoverflow()->id],
            ...($this->userid !== $userid ? ['relateduserid' => $this->userid] : []),
        ])->trigger();
    }

    /**
     * If successful, this function returns the name of the file
     *
     * @param ?int $attachmentdraftid
     * @return void
     * @throws moodle_exception
     */
    public function add_attachment(?int $attachmentdraftid = null): void {
        $this->existence_check();

        if (empty($attachmentdraftid)) {
            return;
        }

        $context = context_module::instance($this->get_coursemodule()->id);
        $info = file_get_draft_area_info($attachmentdraftid);
        $this->attachment = ($info['filecount'] > 0) ? '1' : '';
        file_save_draft_area_files(
            $attachmentdraftid,
            $context->id,
            'mod_moodleoverflow',
            'attachment',
            $this->id,
            $this->get_moodleoverflow()->get_attachment_options()
        );
    }

    /**
     * Marks the post as reviewed.
     * @return void
     * @throws moodle_exception|dml_exception
     */
    public function mark_reviewed(): void {
        global $DB;
        $this->existence_check();
        $this->reviewed = 1;
        $this->timereviewed = time();
        // Only write the review fields, so a concurrent edit of the post is not overwritten.
        $DB->update_record('moodleoverflow_posts', (object) [
            'id' => $this->id,
            'reviewed' => $this->reviewed,
            'timereviewed' => $this->timereviewed,
        ]);
    }

    /**
     * Returns the module context of this post.
     *
     * @return context_module
     * @throws moodle_exception
     */
    public function get_context(): context_module {
        $this->existence_check();
        return context_module::instance($this->get_coursemodule()->id);
    }

    /**
     * Returns the attachment files of this post, sorted by filename.
     *
     * @return \stored_file[]
     * @throws moodle_exception
     */
    public function get_attachment_files(): array {
        $this->existence_check();

        if (empty($this->attachment)) {
            return [];
        }

        return array_values(get_file_storage()->get_area_files(
            $this->get_context()->id,
            'mod_moodleoverflow',
            'attachment',
            $this->id,
            'filename',
            false
        ));
    }

    /**
     * Returns the attachments of this post exported for a template.
     *
     * @param renderer_base $output the renderer used to export the stored files
     * @return array
     * @throws moodle_exception
     */
    public function get_attachments(renderer_base $output): array {
        $context = $this->get_context();

        return array_map(
            fn($file) => (new stored_file_exporter($file, ['context' => $context]))->export($output),
            $this->get_attachment_files()
        );
    }

    /**
     * Saves draft files from the message form permanently and updates the post message.
     * @param ?int $draftitemid
     * @return void
     * @throws moodle_exception
     */
    public function save_draft_files(?int $draftitemid = null): void {
        $this->existence_check();
        if (empty($draftitemid)) {
            return;
        }
        $context = context_module::instance($this->get_coursemodule()->id);
        $this->message = file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_moodleoverflow',
            'post',
            $this->id,
            $this->get_moodleoverflow()->get_editor_options($this->id),
            $this->message
        );
    }

    /**
     * Return the link to the post inside the discussion view.
     * Note: this is not the link to make an action, as it directs to the discussion.php, not the post.php.
     * @throws \core\exception\moodle_exception
     * @throws moodle_exception
     * @return moodle_url
     */
    public function get_link(): moodle_url {
        $this->existence_check();
        return new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $this->discussion], 'p' . $this->id);
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

    /**
     * Returns when the post was last active in its discussion: when it joined (created, or approved later)
     * or when it was last edited, whichever is later.
     * @return int
     */
    public function get_time_active(): int {
        return max($this->modified, $this->timereviewed ?? 0);
    }

    /**
     * Checks if the post is in the window to be editable.
     * @return bool
     * @throws moodle_exception
     */
    public function in_edit_window(): bool {
        return (time() - $this->created) < $this->get_moodleoverflow()->get_edit_window();
    }

    /**
     * If the post is the discussion starter in the discussion.
     * @return bool
     * @throws moodle_exception
     */
    public function is_question(): bool {
        return $this->get_parentid() === 0;
    }

    /**
     * If the post is a direct answer to the first post of the discussion.
     * @return bool
     * @throws moodle_exception
     */
    public function is_direct_answer(): bool {
        return $this->get_parentid() === $this->get_discussion()->get_firstpostid();
    }

    /**
     * If the post is a comment (an answer to a direct answer).
     * @return bool
     * @throws moodle_exception
     */
    public function is_comment(): bool {
        return !$this->is_question() && !$this->is_direct_answer();
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
     * @return moodleoverflow
     * @throws moodle_exception
     */
    public function get_moodleoverflow(): moodleoverflow {
        $this->existence_check();
        return $this->get_discussion()->get_moodleoverflow();
    }

    /**
     * Returns the discussion where the post is located. A post loaded with its discussion shares that discussion object
     * with the other posts; a post loaded on its own loads the discussion, which then uses this object for the post.
     *
     * @return discussion
     * @throws moodle_exception
     */
    public function get_discussion(): discussion {
        $this->existence_check();
        if ($this->discussionobject === null) {
            discussion::from_id($this->discussion)->adopt_post($this);
        }
        return $this->discussionobject;
    }

    /**
     * Links the post to the discussion object it belongs to. Only called by the discussion model.
     *
     * @param discussion $discussion
     * @return void
     * @throws coding_exception|moodle_exception
     */
    public function set_discussion(discussion $discussion): void {
        if ($discussion->get_id() !== $this->discussion) {
            throw new coding_exception('The post does not belong to this discussion.');
        }
        $this->discussionobject = $discussion;
    }

    /**
     * Returns the coursemodule
     *
     * @return cm_info
     * @throws moodle_exception
     */
    public function get_coursemodule(): cm_info {
        $this->existence_check();
        return $this->get_moodleoverflow()->get_cm();
    }

    /**
     * Returns the parent post from the posts of the discussion.
     * @return post|null Null for the question.
     * @throws moodle_exception
     */
    public function get_parentpost(): ?post {
        $this->existence_check();
        return $this->parent === 0 ? null : $this->get_discussion()->get_posts()[$this->parent];
    }

    /**
     * Returns children posts (answers) as DB-records.
     *
     * @return array children/answer posts.
     * @throws moodle_exception
     */
    public function get_childposts(): array {
        global $DB;
        $this->existence_check();
        return $DB->get_records('moodleoverflow_posts', ['parent' => $this->id]);
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
     * Builds an object from this instance that has only DB-relevant attributes.
     * @return object $dbobject
     */
    private function build_db_object(): object {
        return (object) [
            'id' => $this->id,
            'discussion' => $this->discussion,
            'parent' => $this->parent,
            'userid' => $this->userid,
            'created' => $this->created,
            'modified' => $this->modified,
            'message' => $this->message,
            'messageformat' => $this->messageformat,
            'attachment' => $this->attachment,
            'mailed' => $this->mailed,
            'reviewed' => $this->reviewed,
            'timereviewed' => $this->timereviewed,
        ];
    }

    /**
     * Counts the direct replies of the post, reviewed or not, from the posts of the discussion.
     *
     * @return int
     * @throws moodle_exception
     */
    public function count_replies(): int {
        $this->existence_check();
        return count(array_filter($this->get_discussion()->get_posts(), fn(post $post) => $post->get_parentid() === $this->id));
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
