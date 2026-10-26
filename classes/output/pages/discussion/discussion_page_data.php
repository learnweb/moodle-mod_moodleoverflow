<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\output\pages\discussion;

use core\exception\moodle_exception;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;

/**
 * The data of a discussion page that every post card needs, loaded once for the whole discussion instead of once per
 * post: the ratings, the votes and the unread posts of the user, and the reputations of the authors.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_page_data {
    /** @var object[] The ratings of every post, from service\rating::get_post_ratings() */
    private array $ratings;

    /** @var rating_type[] The votes of the user, indexed by post id */
    private array $votes;

    /** @var int[] The ids of the posts the user has not read yet, oldest first */
    private array $unread;

    /** @var int[] The reputations of the authors that were asked for, indexed by user id */
    private array $reputations = [];

    /**
     * Constructor.
     * @param discussion $discussion
     * @param int $userid The user who sees the page
     * @throws moodle_exception
     */
    public function __construct(
        /** @var discussion The discussion of the page */
        private discussion $discussion,
        int $userid
    ) {
        $this->ratings = service\rating::get_post_ratings($discussion);
        $this->votes = service\rating::get_user_votes($discussion, $userid);
        $this->unread = service\readtracking::get_unread_post_ids($discussion, $userid);
    }

    /**
     * Returns the ratings of a post.
     * @param post $post
     * @return object upvotes, downvotes, issolved, ishelpful
     */
    public function get_ratings(post $post): object {
        return $this->ratings[$post->get_id()];
    }

    /**
     * Whether any post of the discussion has a mark.
     * @param rating_type $mark HELPFUL or SOLVED
     * @return bool
     */
    public function has_mark(rating_type $mark): bool {
        $field = $mark === rating_type::HELPFUL ? 'ishelpful' : 'issolved';
        return (bool) array_filter($this->ratings, fn(object $ratings) => $ratings->$field > 0);
    }

    /**
     * Returns the vote of the user on a post.
     * @param post $post
     * @return rating_type|null UPVOTE or DOWNVOTE, null if the user has not voted.
     */
    public function get_vote(post $post): ?rating_type {
        return $this->votes[$post->get_id()] ?? null;
    }

    /**
     * Whether the user has read a post.
     * @param post $post
     * @return bool
     */
    public function is_read(post $post): bool {
        return !in_array($post->get_id(), $this->unread, true);
    }

    /**
     * Whether the post is the first one the user has not read.
     * @param post $post
     * @return bool
     */
    public function is_first_unread(post $post): bool {
        return ($this->unread[0] ?? null) === $post->get_id();
    }

    /**
     * Returns the reputation of an author, computed once per author.
     * @param int $userid
     * @return int
     * @throws moodle_exception
     */
    public function get_reputation(int $userid): int {
        return $this->reputations[$userid] ??= service\rating::get_reputation($this->discussion->get_moodleoverflow(), $userid);
    }
}
