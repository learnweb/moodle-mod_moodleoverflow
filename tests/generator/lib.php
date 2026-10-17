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
 * mod_moodleoverflow data generator
 *
 * @package    mod_moodleoverflow
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\subscriptions;

/**
 * Moodleoverflow module data generator class
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_generator extends testing_module_generator {
    /**
     * @var int keep track of how many moodleoverflow discussions have been created.
     */
    protected $discussioncount = 0;

    /**
     * @var int keep track of how many moodleoverflow posts have been created.
     */
    protected $postcount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->discussioncount = 0;
        $this->postcount = 0;
        // The static caches of subscriptions survive between tests.
        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();
        parent::reset();
    }

    /**
     * Creates a moodleoverflow instance.
     *
     * @param null       $record
     * @param array|null $options
     *
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        // Transform the record and set default values if not provided.
        $record = (object) array_merge([
            'name' => 'Test MO Instance',
            'intro' => 'Test Intro',
            'introformat' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'forcesubscribe' => subscription_mode::OPTIONAL->value,
        ], (array) $record);

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Creates a discussion with its question.
     *
     * @param moodleoverflow|stdClass $modflow The moodleoverflow to post in.
     * @param stdClass $author The author of the question.
     * @param array $record Further fields for the discussion: 'name' and 'message'.
     * @return post The question, the first post of the discussion.
     */
    public function create_question(moodleoverflow|stdClass $modflow, stdClass $author, array $record = []): post {
        $this->discussioncount++;
        $modflow = moodleoverflow::from_id($modflow->id);
        $time = time();
        $discussion = discussion::construct_without_id(
            $modflow->course,
            $modflow->id,
            $record['name'] ?? 'Discussion ' . $this->discussioncount,
            0,
            $author->id,
            $time,
            0,
            $author->id
        );
        $prepost = (object) [
            'userid' => $author->id,
            'timenow' => $time,
            'message' => $record['message'] ?? '<p>Message for discussion ' . $this->discussioncount . '</p>',
            'messageformat' => FORMAT_HTML,
            'reviewed' => $this->is_reviewed($modflow, $author, true),
            'formattachments' => null,
            'modulecontext' => $modflow->get_context(),
        ];
        $discussion->add($prepost);
        return post::from_id($discussion->get_firstpostid());
    }

    /**
     * Creates a reply to a post. It is added through its discussion, so the last post of the discussion is updated.
     *
     * @param post $parent The post to reply to.
     * @param stdClass $author The author of the reply.
     * @param array $record Further fields for the post: 'message'.
     * @return post
     */
    public function create_reply(post $parent, stdClass $author, array $record = []): post {
        $this->postcount++;
        // Replies are created one second apart, so they have a stable order.
        $time = time() + $this->postcount;
        $prepost = (object) [
            'parentid' => $parent->get_id(),
            'userid' => $author->id,
            'timenow' => $time,
            'message' => $record['message'] ?? '<p>Forum message post ' . $this->postcount . '</p>',
            'messageformat' => FORMAT_MOODLE,
            'reviewed' => $this->is_reviewed($this->get_moodleoverflow($parent), $author, false),
            'formattachments' => null,
        ];
        return post::from_id(discussion::from_id($parent->get_discussionid())->add_new_post($prepost));
    }

    /**
     * Creates a rating of a post.
     *
     * @param post $post The rated post.
     * @param stdClass $rater The user who rates.
     * @param rating_type $rating
     * @return stdClass The rating record.
     */
    public function create_rating(post $post, stdClass $rater, rating_type $rating): stdClass {
        global $DB;
        $time = time();
        $record = (object) [
            'rating' => $rating->value,
            'postid' => $post->get_id(),
            'discussionid' => $post->get_discussionid(),
            'moodleoverflowid' => $this->get_moodleoverflow($post)->id,
            'userid' => $rater->id,
            'firstrated' => $time,
            'lastchanged' => $time,
        ];
        $record->id = $DB->insert_record('moodleoverflow_ratings', $record);
        return $record;
    }

    /**
     * Creates a file of a post. For the attachment area the post is marked as having attachments; load the post again
     * to see the flag.
     *
     * @param post $post The post the file belongs to.
     * @param string $filearea 'attachment' or 'post' (embedded in the message).
     * @param string $filename
     * @param string $content
     * @return stored_file
     */
    public function create_post_file(
        post $post,
        string $filearea,
        string $filename = 'file.txt',
        string $content = 'content'
    ): stored_file {
        global $DB;
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $this->get_moodleoverflow($post)->get_context()->id,
            'component' => 'mod_moodleoverflow',
            'filearea' => $filearea,
            'itemid' => $post->get_id(),
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        if ($filearea === 'attachment') {
            $DB->set_field('moodleoverflow_posts', 'attachment', 1, ['id' => $post->get_id()]);
        }
        return $file;
    }

    /**
     * Record variant of create_question() for tests that work with database records.
     *
     * @param moodleoverflow|stdClass $modflow The moodleoverflow to post in.
     * @param stdClass $author The author of the question.
     * @param array|stdClass|null $record Further fields for the discussion.
     * @return stdClass[] The discussion record and the record of its question.
     */
    public function post_to_forum(moodleoverflow|stdClass $modflow, stdClass $author, array|stdClass|null $record = null): array {
        $question = $this->create_question($modflow, $author, (array) $record);
        return [$question->get_discussion()->get_db_object(), $question->get_db_object()];
    }

    /**
     * Record variant of create_reply() for tests that work with database records.
     *
     * @param stdClass $parent The record of the post to reply to.
     * @param stdClass $author The author of the reply.
     * @return stdClass The record of the reply.
     */
    public function reply_to_post(stdClass $parent, stdClass $author): stdClass {
        return $this->create_reply(post::from_id($parent->id), $author)->get_db_object();
    }

    /**
     * Whether a new post starts as reviewed: if the moodleoverflow does not require a review for it, or if the author is
     * a reviewer.
     *
     * @param moodleoverflow $modflow
     * @param stdClass $author
     * @param bool $isquestion
     * @return int 1 or 0, as stored in the reviewed column.
     */
    private function is_reviewed(moodleoverflow $modflow, stdClass $author, bool $isquestion): int {
        return (int) (!$modflow->requires_review($isquestion) || permissions::can_review_posts($modflow, $author->id));
    }

    /**
     * Loads the moodleoverflow of a post without using the post's getters: they cache a discussion on the post, whose
     * list of posts would lack the posts created afterwards.
     *
     * @param post $post
     * @return moodleoverflow
     */
    private function get_moodleoverflow(post $post): moodleoverflow {
        global $DB;
        $conditions = ['id' => $post->get_discussionid()];
        return moodleoverflow::from_id($DB->get_field('moodleoverflow_discussions', 'moodleoverflow', $conditions, MUST_EXIST));
    }
}
