<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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

namespace mod_moodleoverflow\local\service;

use core_search\manager;
use dml_exception;
use Exception;
use mod_moodleoverflow\local\models;
use mod_moodleoverflow\local\permissions;
use core\exception\moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Service class for use cases that concern a discussion as a whole.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion {
    /**
     * Returns all discussions in a moodleoverflow that the user can see.
     * @param models\moodleoverflow $modflow
     * @param int $userid
     * @return models\discussion[]
     * @throws moodle_exception
     */
    public static function get_discussions(models\moodleoverflow $modflow, int $userid): array {
        global $DB;
        $records = $DB->get_records('moodleoverflow_discussions', ['moodleoverflow' => $modflow->id], 'timestart DESC, id DESC');
        return array_filter(
            array_map(fn($record) => models\discussion::from_record($record), $records),
            fn(models\discussion $discussion) => permissions::can_view_discussion($discussion, $userid)
        );
    }

    /**
     * Moves a discussion to another moodleoverflow of the same course.
     *
     * The discussion model moves its own data (files, ratings, read records, discussion subscriptions, mail digest quere)
     * and triggers the event. This method adds what lies outside the discussion: the grades of both instances and the search index.
     *
     * @param models\discussion $discussion The discussion to move
     * @param models\moodleoverflow $destination The moodleoverflow the discussion is moved to
     * @param int $userid The user who moves the discussion
     * @return void
     * @throws dml_exception|moodle_exception
     */
    public static function move(models\discussion $discussion, models\moodleoverflow $destination, int $userid): void {
        global $DB;
        permissions::ensure(permissions::can_move_discussion($discussion, $destination, $userid), 'invalidmovedestination');
        $source = $discussion->get_moodleoverflow();

        $transaction = $DB->start_delegated_transaction();
        try {
            $discussion->move_discussion($destination);

            // The reputation of authors and raters changes in both instances, so their grades change as well.
            $affectedusers = array_unique(
                array_merge(
                    $DB->get_fieldset_select('moodleoverflow_posts', 'userid', 'discussion = ?', [$discussion->get_id()]),
                    $DB->get_fieldset_select('moodleoverflow_ratings', 'userid', 'discussionid = ?', [$discussion->get_id()])
                )
            );
            foreach ([$source, $destination] as $modflow) {
                rating::update_grades($modflow, $affectedusers);
            }
            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }
        // The indexed posts keep the context of the source; reindexing the destination replaces them.
        manager::request_index($destination->get_context(), 'mod_moodleoverflow-post');
    }

    /**
     * Returns the posts of a discussion that the user can see, in the order of the discussion page.
     *
     * Below the question are the direct answers, ranked by marks and votes (rating::sort_answers()), and below each
     * answer its comments, oldest first. A reply to a comment counts as a comment of its answer. Comments of an answer
     * the user cannot see are left out with it.
     *
     * @param models\discussion $discussion
     * @param int $userid
     * @return array{question: models\post, answers: array{answer: models\post, comments: models\post[]}[]}
     * @throws moodle_exception|dml_exception
     */
    public static function get_posts_tree(models\discussion $discussion, int $userid): array {
        permissions::ensure(permissions::can_view_discussion($discussion, $userid), 'noviewdiscussionspermission');
        $posts = $discussion->get_posts();
        $visible = array_filter($posts, fn(models\post $post) => permissions::can_view_post($post, $userid));

        // Rank the direct answers; the result is indexed by post id.
        $ratings = rating::get_post_ratings($discussion);
        $sortdata = [];
        foreach (array_filter($visible, fn(models\post $post) => $post->is_direct_answer()) as $answer) {
            $rating = $ratings[$answer->get_id()];
            $sortdata[] = (object) [
                'id' => $answer->get_id(),
                'modified' => $answer->modified,
                'votesdifference' => $rating->upvotes - $rating->downvotes,
                'markedsolution' => $rating->issolved,
                'markedhelpful' => $rating->ishelpful,
            ];
        }
        $ranked = rating::sort_answers($sortdata, $discussion->get_moodleoverflow()->get_rating_preference());
        $answers = array_map(fn($row) => ['answer' => $visible[$row->id], 'comments' => []], $ranked);

        // Append each comment to its answer, oldest first.
        $comments = array_filter($visible, fn(models\post $post) => $post->is_comment());
        usort($comments, fn($a, $b) => $a->created <=> $b->created ?: $a->get_id() <=> $b->get_id());
        foreach ($comments as $comment) {
            $answerid = self::get_answer_id($comment, $posts);
            if (isset($answers[$answerid])) {
                $answers[$answerid]['comments'][] = $comment;
            }
        }

        return ['question' => $posts[$discussion->get_firstpostid()], 'answers' => array_values($answers)];
    }

    /**
     * Returns the moodleoverflows of the same course that the user can move discussions of the source to.
     * @param models\moodleoverflow $source
     * @param int $userid
     * @return models\moodleoverflow[]
     * @throws moodle_exception
     */
    public static function get_move_destinations(models\moodleoverflow $source, int $userid): array {
        global $DB;
        $records = $DB->get_records('moodleoverflow', ['course' => $source->course], 'id');
        return array_values(array_filter(
            array_map(fn($record) => models\moodleoverflow::from_record($record), $records),
            fn(models\moodleoverflow $destination) => permissions::can_move_discussions($source, $destination, $userid)
        ));
    }

    /**
     * Returns the id of the direct answer a comment belongs to, also for replies to comments.
     * @param models\post $comment
     * @param models\post[] $posts All posts of the discussion, indexed by id
     * @return int|null Null if the chain is broken
     * @throws moodle_exception
     */
    private static function get_answer_id(models\post $comment, array $posts): ?int {
        $post = $comment;
        while ($post !== null && $post->is_comment()) {
            $post = $posts[$post->get_parentid()] ?? null;
        }
        return $post?->get_id();
    }
}
