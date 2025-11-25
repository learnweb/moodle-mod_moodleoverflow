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
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../locallib.php');

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
            'forcesubscribe' => MOODLEOVERFLOW_CHOOSESUBSCRIBE,
        ], (array) $record);

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Creates a moodleoverflow discussion.
     *
     * @param null $record The discussion record.
     * @param null $forum The moodleoverflow to insert the discussion.
     *
     * @return mixed The new discussion record.
     * @throws coding_exception
     */
    public function create_discussion($record = null, $forum = null) {
        global $DB;

        // Increment the discussion count.
        $this->discussioncount++;
        $record = (array) $record;
        // Ensure required fields are set.
        foreach (['course', 'moodleoverflow', 'userid'] as $field) {
            if (empty($record[$field])) {
                throw new coding_exception("$field must be present in phpunit_util:create_discussion() $record");
            }
        }

        // Set default values.
        $record = array_merge([
            'name' => 'Discussion ' . $this->discussioncount,
            'message' => html_writer::tag('p', 'Message for discussion ' . $this->discussioncount),
            'messageformat' => editors_get_preferred_format(),
            'timestart' => "0",
            'timeend' => "0",
            'attachments' => null,
            'draftideditor' => -1,
        ], (array) $record);

        // Extract optional fields.
        $mailed = $record['mailed'] ?? null;
        $timemodified = $record['timemodified'] ?? null;

        // Convert the record to an object.
        $record = (object) $record;

        // Get the module context.
        $cm = get_coursemodule_from_instance('moodleoverflow', $forum->id);

        $modulecontext = \context_module::instance($cm->id);

        // Add the discussion.
        $record->id = moodleoverflow_add_discussion($record, $modulecontext, $record->userid);

        if (isset($timemodified) || isset($mailed)) {
            $post = $DB->get_record('moodleoverflow_posts', ['discussion' => $record->id]);

            if (isset($mailed)) {
                $post->mailed = $mailed;
            }

            if (isset($timemodified)) {
                $record->timemodified = $timemodified;
                $post->modified = $post->created = $timemodified;
                $DB->update_record('moodleoverflow_discussions', $record);
            }

            $DB->update_record('moodleoverflow_discussions', $record);
        }

        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $record->id]);

        // Return the discussion object.
        return $discussion;
    }

    /**
     * Function to create a dummy post.
     *
     * @param array|stdClass $record
     * @param bool $straighttodb
     *
     * @return stdClass the post object
     */
    public function create_post($record = null, $straighttodb = true) {
        global $DB;

        // Increment the forum post count and set the current time.
        $this->postcount++;
        $time = time() + $this->postcount;

        // Ensure required fields are set and provide default values.
        $record = (object) array_merge([
            'parent' => 0,
            'message' => html_writer::tag('p', 'Forum message post ' . $this->postcount),
            'created' => $time,
            'modified' => $time,
            'mailed' => 0,
            'messageformat' => 0,
            'attachment' => "",
        ], (array) $record);

        if (empty($record->discussion)) {
            throw new coding_exception('discussion must be present in phpunit_util::create_post() $record');
        }
        if (empty($record->userid)) {
            throw new coding_exception('userid must be present in phpunit_util::create_post() $record');
        }

        // Add the post.
        if ($straighttodb) {
            $record->id = $DB->insert_record('moodleoverflow_posts', $record);
        } else {
            $record->draftideditor = -1;
            $record->id = moodleoverflow_add_new_post($record);
        }

        // Update the last post.
        moodleoverflow_discussion_update_last_post($record->discussion);

        return $record;
    }

    /**
     * Function to create a dummy rating.
     *
     * @param array|stdClass $record
     *
     * @return stdClass the post object
     */
    public function create_rating($record = null) {
        global $DB;

        $time = time();
        $record = array_merge([
            'rating' => 1,
            'firstrated' => $time,
            'lastchanged' => $time,
        ], (array) $record);

        // Ensure required fields are set.
        foreach (['moodleoverflowid', 'discussionid', 'userid', 'postid'] as $field) {
            if (empty($record[$field])) {
                throw new coding_exception("$field must be present in phpunit_util::create_rating() \$record");
            }
        }

        $record = (object) $record;
        // Add the rating.
        $record->id = $DB->insert_record('moodleoverflow_ratings', $record);

        return $record;
    }


    /**
     * Create a new discussion and post within the specified forum, as the
     * specified author.
     *
     * @param stdClass $forum  The forum to post in
     * @param stdClass $author The author to post as
     * @param          array   An array containing the discussion object, and the post object
     */
    /**
     * Create a new discussion and post within the specified forum, as the
     * specified author.
     *
     * @param stdClass $forum   The moodleoverflow to post in
     * @param stdClass $author  The author to post as
     * @param stdClass|null $record Fields for the discussion
     *
     * @return array The discussion and the post record.
     */
    public function post_to_forum($forum, $author, $record = null) {
        global $DB;
        // Create a discussion in the forum, and then add a post to that discussion.
        if (!$record) {
            $record = new stdClass();
        }
        $record->course = $forum->course;
        $record->userid = $author->id;
        $record->moodleoverflow = $forum->id;
        $discussion = $this->create_discussion($record, $forum, $record);
        // Retrieve the post which was created by create_discussion.
        $post = $DB->get_record('moodleoverflow_posts', ['discussion' => $discussion->id]);

        return [$discussion, $post];
    }

    /**
     * Update the post time for the specified post by $factor.
     *
     * @param stdClass $post   The post to update
     * @param int      $factor The amount to update by
     */
    public function update_post_time($post, $factor) {
        global $DB;
        // Update the post to have a created in the past.
        $DB->set_field('moodleoverflow_posts', 'created', $post->created + $factor, ['id' => $post->id]);
    }

    /**
     * Update the subscription time for the specified user/discussion by $factor.
     *
     * @param stdClass $user       The user to update
     * @param stdClass $discussion The discussion to update for this user
     * @param int      $factor     The amount to update by
     */
    public function update_subscription_time($user, $discussion, $factor) {
        global $DB;
        $sub = $DB->get_record('moodleoverflow_discuss_subs', ['userid' => $user->id, 'discussion' => $discussion->id]);
        // Update the subscription to have a preference in the past.
        $DB->set_field('moodleoverflow_discuss_subs', 'preference', $sub->preference + $factor, ['id' => $sub->id]);
    }

    /**
     * Create a new post within an existing discussion, as the specified author.
     *
     * @param stdClass $forum      The forum to post in
     * @param stdClass $discussion The discussion to post in
     * @param stdClass $author     The author to post as
     *
     * @return stdClass The forum post
     */
    public function post_to_discussion($forum, $discussion, $author) {
        // Add a post to the discussion.
        $record = new stdClass();
        $record->course = $forum->course;
        $record->userid = $author->id;
        $record->moodleoverflow = $forum->id;
        $record->discussion = $discussion->id;
        $post = $this->create_post($record);

        return $post;
    }

    /**
     * Create a new post within an existing discussion, as the specified author.
     *
     * @param stdClass $parent  The parent post
     * @param stdClass $author  The author to post as
     * @param bool $straighttodb
     *
     * @return stdClass The new moodleoverflow post
     */
    public function reply_to_post($parent, $author, $straighttodb = true) {
        // Add a post to the discussion.
        $record = (object) [
            'discussion' => $parent->discussion,
            'parent' => $parent->id,
            'userid' => $author->id,
        ];
        return $this->create_post($record, $straighttodb);
    }
}
