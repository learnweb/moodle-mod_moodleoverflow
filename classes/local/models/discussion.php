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

// Important namespaces.
use cm_info;
use core\exception\coding_exception;
use dml_exception;
use Exception;
use mod_moodleoverflow\event\discussion_deleted;
use mod_moodleoverflow\event\discussion_moved;
use mod_moodleoverflow\event\discussion_created;
use mod_moodleoverflow\local\service\readtracking;
use core\exception\moodle_exception;
use moodle_url;
use Throwable;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class for working with posts
 * This class represents a discussion. A discussion administrates the posts and has one parent post, that started the discussion.
 *
 * @package     mod_moodleoverflow
 * @copyright   2023 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion {
    // The database related attributes are promoted in the constructor below.

    // Not Database-related attributes.

    /** @var post[] an Array of posts that belong to this discussion */
    public array $posts;

    /** @var bool  a variable for checking if this instance has all its posts */
    public bool $postsbuild;

    /** @var moodleoverflow The moodleoverflow object where the discussion is located */
    public moodleoverflow $moodleoverflowobject;

    // Constructors and other builders.

    /**
     * Constructor to build a new discussion.
     * @param ?int $id The Discussion ID.
     * @param int $course The course ID.
     * @param int $moodleoverflow The moodleoverflow ID.
     * @param string $name Discussion Title.
     * @param int $firstpost ID of the first post.
     * @param int $userid The course ID.
     * @param int $timemodified The course ID.
     * @param int $timestart The course ID.
     * @param int $usermodified The course ID.
     */
    public function __construct(
        /** @var ?int The discussion ID */
        private ?int $id,
        /** @var int The course ID where the discussion is located */
        private int $course,
        /** @var int The moodleoverflow ID where the discussion is located */
        private int $moodleoverflow,
        /** @var string The title of the discussion, the title of the parent post */
        public string $name,
        /** @var int The id of the parent/first post */
        private int $firstpost,
        /** @var int The user ID who started the discussion */
        private int $userid,
        /** @var int Unix-timestamp of modification */
        public int $timemodified,
        /** @var int Unix-timestamp of discussion creation */
        public int $timestart,
        /** @var int the user ID who modified the discussion */
        public int $usermodified
    ) {
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
        $instance->get_posts();

        return $instance;
    }

    /**
     * Function to build a new discussion without specifying the Discussion ID.
     * @param int $course The course ID.
     * @param int $moodleoverflow The moodleoverflow ID.
     * @param string $name Discussion Title.
     * @param int $firstpost ID of the first post.
     * @param int $userid The user that started the discussion.
     * @param int $timemodified Time the discussion was last modified.
     * @param int $timestart Time the discussion was created.
     * @param int $usermodified User that last modified the discussion.
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

    /**
     * Build discussion instance from id.
     * @param int $id
     * @return self
     * @throws dml_exception
     */
    public static function from_id(int $id): self {
        global $DB;
        return self::from_record($DB->get_record('moodleoverflow_discussions', ['id' => $id], '*', MUST_EXIST));
    }

    // Discussion Functions.

    /**
     * Adds a new Discussion with a post.
     *
     * @param post_content $content The message and files of the question.
     * @param bool $reviewed Whether the question starts as reviewed. Not reviewed discussions/posts need to be approved first.
     * @return int
     * @throws Throwable
     */
    public function add(post_content $content, bool $reviewed): int {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        try {
            // Add the discussion to the Database.
            $this->id = $DB->insert_record('moodleoverflow_discussions', $this->build_db_object());

            // Create the first/parent post for the new discussion.
            $post = post::construct_without_id(
                $this->id,
                0,
                $this->userid,
                $this->timestart,
                $this->timestart,
                $content->message,
                $content->messageformat,
                "",
                0,
                (int) $reviewed,
                null
            );

            // Add it to the DB and save the id of the first/parent post.
            $this->firstpost = $post->add($content->messagedraftid, $content->attachmentdraftid);
            $DB->set_field('moodleoverflow_discussions', 'firstpost', $this->firstpost, ['id' => $this->id]);
            $this->posts[$this->firstpost] = $post;
            $this->postsbuild = true;
            $this->update_last_activity();

            // Trigger creation event.
            discussion_created::create([
                'context' => $this->get_moodleoverflow()->get_context(),
                'objectid' => $this->id,
            ])->trigger();

            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
        return $this->id;
    }

    /**
     * Deletes this discussion with all its posts and the data that belongs to it.
     *
     * @param int $userid The user who deletes the discussion.
     * @return void
     * @throws Throwable
     */
    public function delete(int $userid): void {
        global $DB;
        $this->existence_check();
        $this->posts_check();
        $context = $this->get_moodleoverflow()->get_context();

        $transaction = $DB->start_delegated_transaction();
        try {
            // All other posts are replies to the question and are deleted with it.
            $this->posts[$this->firstpost]->delete($userid);
            readtracking::delete_read_records(discussid: $this->id);
            $DB->delete_records('moodleoverflow_discuss_subs', ['discussion' => $this->id]);
            $DB->delete_records('moodleoverflow_mail_info', ['forumdiscussionid' => $this->id]);
            $DB->delete_records('moodleoverflow_discussions', ['id' => $this->id]);

            discussion_deleted::create(['objectid' => $this->id, 'context' => $context])->trigger();
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }

        // Working with this instance is not possible anymore.
        $this->id = null;
        $this->posts = [];
    }

    /**
     * Adds a reply to a post from this discussion.
     * @param post $parent The post that gets replied to
     * @param int $userid The user that replies
     * @param post_content $content The content from the post form
     * @param bool $reviewed If the post should be reviewed or not.
     * @return post
     * @throws Throwable
     */
    public function add_reply(post $parent, int $userid, post_content $content, bool $reviewed): post {
        global $DB;
        $this->existence_check();
        $this->posts_check();
        $this->post_exists_check($parent->get_id());

        // Build the new post record.
        $time = time();
        $post = post::construct_without_id(
            $this->id,
            $parent->get_id(),
            $userid,
            $time,
            $time,
            $content->message,
            $content->messageformat,
            "",
            0,
            (int) $reviewed,
            null
        );

        // Add the post to the DB.
        $transaction = $DB->start_delegated_transaction();
        try {
            $postid = $post->add($content->messagedraftid, $content->attachmentdraftid);

            // Add the post to the $posts array and update the last post of the discussion.
            $this->posts[$postid] = $post;
            $this->update_last_activity();

            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
        return $post;
    }

    /**
     * Deletes a post of this discussion with its replies. Deleting the question deletes the whole discussion.
     *
     * @param post $post The post to delete.
     * @param int $userid The user who deletes the post.
     * @return bool True if the whole discussion was deleted.
     * @throws Throwable
     */
    public function delete_post(post $post, int $userid): bool {
        global $DB;
        $this->existence_check();
        $this->posts_check();
        $this->post_exists_check($post->get_id());

        if ($post->get_id() === $this->firstpost) {
            $this->delete($userid);
            return true;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $this->posts[$post->get_id()]->delete($userid);
            $this->update_last_activity();
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }

        // Remove the post and its replies from the list (comments have no replies of their own).
        foreach ($this->posts as $id => $other) {
            if ($id === $post->get_id() || $other->get_parentid() === $post->get_id()) {
                unset($this->posts[$id]);
            }
        }
        return false;
    }

    /**
     * Edits a post of this discussion.
     *
     * @param post $post The post to edit.
     * @param int $userid The user who edits the post.
     * @param post_content $content The new content.
     * @return void
     * @throws Throwable
     */
    public function edit_post(post $post, int $userid, post_content $content): void {
        global $DB;
        $this->existence_check();
        $this->posts_check();
        $this->post_exists_check($post->get_id());

        // Work on the copy in this discussion, so its list of posts stays up to date.
        $post = $this->posts[$post->get_id()];

        $transaction = $DB->start_delegated_transaction();
        try {
            if ($post->get_id() === $this->firstpost && $content->subject !== null) {
                $this->name = trim($content->subject);
                $DB->set_field('moodleoverflow_discussions', 'name', $this->name, ['id' => $this->id]);
            }
            $post->edit($userid, $content);

            // An edit of an unreviewed post is no activity, so its editor does not count.
            $this->update_last_activity($post->reviewed ? $userid : null);
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Sets the last post of the discussion (timemodified and usermodified).
     *
     * Only reviewed posts count. The time of a post is when it joined the discussion (created, or approved later)
     * or when it was last edited, whichever is later. The posts do not store who edited them, so the user is the
     * author of that post, unless the caller passes the user whose change is the newest (an edit of a reviewed post).
     *
     * @param int|null $userid The user who made the newest change, if it was not the author.
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public function update_last_activity(?int $userid = null): void {
        global $DB;
        $this->existence_check();

        // Load the posts freshly: the posts of this object may be stale (e.g. the approved post still unreviewed).
        $records = $DB->get_records('moodleoverflow_posts', ['discussion' => $this->id, 'reviewed' => 1]);
        $lastpost = self::find_last_post(array_map(fn($record) => post::from_record($record), $records));
        if (!$lastpost) {
            // No reviewed post yet: keep the values from the creation of the discussion.
            return;
        }

        $this->timemodified = $lastpost->get_time_active();
        $this->usermodified = $userid ?? $lastpost->get_userid();
        // Only write the two fields, so values of this object that were loaded earlier (e.g. the name) are not written back.
        $DB->update_record('moodleoverflow_discussions', (object) [
            'id' => $this->id,
            'timemodified' => $this->timemodified,
            'usermodified' => $this->usermodified,
        ]);
    }

    /**
     * Moves discussion from one moodleoverflow to another.
     * @param moodleoverflow $destination The moodleoverflow where the discussion is moved to.
     * @return void
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public function move_discussion(moodleoverflow $destination): void {
        global $DB;
        $this->existence_check();
        $source = $this->get_moodleoverflow();

        $transaction = $DB->start_delegated_transaction();
        try {
            // Files are stored per context, and both file areas use the post id as item id: 'attachment' for the
            // attachments, 'post' for images embedded in the message. Moving per post id only touches this discussion.
            $fs = get_file_storage();
            foreach (array_keys($this->get_posts()) as $postid) {
                foreach (['attachment', 'post'] as $filearea) {
                    $fs->move_area_files_to_new_context(
                        $source->get_context()->id,
                        $destination->get_context()->id,
                        'mod_moodleoverflow',
                        $filearea,
                        $postid
                    );
                }
            }

            // Adapt the following tables that store the moodleoverflow id of the discussion.
            $DB->set_field('moodleoverflow_ratings', 'moodleoverflowid', $destination->id, ['discussionid' => $this->id]);
            $DB->set_field('moodleoverflow_discuss_subs', 'moodleoverflow', $destination->id, ['discussion' => $this->id]);
            $DB->set_field('moodleoverflow_mail_info', 'forumid', $destination->id, ['forumdiscussionid' => $this->id]);
            $DB->set_field('moodleoverflow_read', 'moodleoverflowid', $destination->id, ['discussionid' => $this->id]);

            // Adapt the discussion itself.
            $this->moodleoverflow = $destination->id;
            $this->moodleoverflowobject = $destination;
            $DB->update_record('moodleoverflow_discussions', $this->build_db_object());

            // The loaded posts may still have the source cached as their moodleoverflow; load them again when needed.
            $this->posts = [];
            $this->postsbuild = false;

            // Log the move. The event is only delivered to observers when the outermost transaction commits.
            discussion_moved::create([
                'objectid' => $this->id,
                'context' => $destination->get_context(),
                'other' => ['frommoodleoverflowid' => $source->id, 'tomoodleoverflowid' => $destination->id],
            ])->trigger();

            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
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
     * Get all posts from this Discussion.
     *
     * @return post[] $posts     Array ob posts objects
     * @throws moodle_exception
     */
    public function get_posts(): array {
        global $DB;
        $this->existence_check();

        // Check if the posts array are build yet. If not, build it.
        if (!$this->postsbuild) {
            // Get the posts from the DB. Get the parent post first.
            $sql = 'SELECT *
                    FROM {moodleoverflow_posts} posts
                    WHERE discussion = ' . $this->id . ';';
            $posts = $DB->get_records_sql($sql);
            foreach ($posts as $postrecord) {
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
     * Returns the last post of the discussion: the reviewed post that was active last.
     * @return post|null Null while no post is reviewed (the question waits for its review).
     * @throws moodle_exception
     */
    public function get_last_post(): ?post {
        $this->existence_check();
        $this->posts_check();
        return self::find_last_post($this->posts);
    }

    /**
     * Return the first post of the discussion.
     * @throws moodle_exception
     */
    public function get_first_post(): post {
        $this->existence_check();
        $this->posts_check();
        return array_values(array_filter($this->posts, fn($post) => $post->get_id() == $this->firstpost))[0];
    }

    /**
     * Returns all posts except the first post.
     * @return post[]
     * @throws moodle_exception
     */
    public function get_answerposts(): array {
        $this->existence_check();
        $this->posts_check();
        return array_filter($this->posts, fn($post) => $post->get_id() != $this->firstpost);
    }

    /**
     * Returns the moodleoverflowobject
     *
     * @return moodleoverflow $moodleoverflowobject
     * @throws dml_exception|moodle_exception
     */
    public function get_moodleoverflow(): moodleoverflow {
        $this->existence_check();
        return $this->moodleoverflowobject ??= moodleoverflow::from_id($this->moodleoverflow);
    }

    /**
     * Returns the coursemodule
     *
     * @return cm_info
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_coursemodule(): cm_info {
        $this->existence_check();
        return $this->get_moodleoverflow()->get_cm();
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

    /**
     * Returns the link to this discussion
     * @return moodle_url
     * @throws \core\exception\moodle_exception
     * @throws moodle_exception
     */
    public function get_link(): moodle_url {
        $this->existence_check();
        return new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $this->id]);
    }

    // Helper functions.

    /**
     * Builds an object from this instance that has only DB-relevant attributes.
     * As this is an private function, it doesn't need an existence check.
     * @return object $dbobject
     */
    private function build_db_object(): object {
        return (object) [
            'id' => $this->id,
            'course' => $this->course,
            'moodleoverflow' => $this->moodleoverflow,
            'name' => $this->name,
            'firstpost' => $this->firstpost,
            'userid' => $this->userid,
            'timemodified' => $this->timemodified,
            'timestart' => $this->timestart,
            'usermodified' => $this->usermodified,
        ];
    }

    /**
     * Finds the last post: unreviewed posts are not part of the discussion yet.
     * @param post[] $posts
     * @return post|null
     */
    private static function find_last_post(array $posts): ?post {
        $reviewed = array_filter($posts, fn(post $post) => $post->reviewed == 1);
        usort($reviewed, fn(post $a, post $b) =>
            [$b->get_time_active(), $b->get_id()] <=> [$a->get_time_active(), $a->get_id()]);
        return reset($reviewed) ?: null;
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
        if (!isset($this->posts[$postid])) {
            throw new moodle_exception('postnotpartofdiscussion', 'moodleoverflow');
        }
    }
}
