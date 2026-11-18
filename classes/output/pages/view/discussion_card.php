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

namespace mod_moodleoverflow\output\pages\view;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\local\service\rating;
use mod_moodleoverflow\local\service\review;
use mod_moodleoverflow\local\service\subscription;
use mod_moodleoverflow\output\author;
use core\exception\moodle_exception;
use moodle_url;

/**
 * Class that gathers data for the discussion card template used in the view.php.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_card implements named_templatable, renderable {
    /** @var object The moodleoverflow database object */
    public object $modflow;

    /** @var discussion The discussion that gets printed */
    public discussion $discussion;

    /** @var post The first post in the discussion */
    public post $firstpost;

    /** @var post|null The last post, null while the question waits for its review */
    public ?post $lastpost;

    /** @var string The search of the forum page, empty: no search; the discussion page highlights its words */
    private string $query;

    /** @var bool If discussion can be moved */
    public bool $movepossible;

    /** @var int The number of posts the user has not read, counted for the whole page */
    public int $unreadcount;

    /**
     * Constructor
     * @param discussion $discussion
     * @param bool $movepossible If the discussion can be moved to another moodleoverflow
     * @param int $unreadcount The number of posts the user has not read, counted for the whole page
     * @param string $query The search of the forum page, empty: no search
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public function __construct(discussion $discussion, bool $movepossible, int $unreadcount, string $query = '') {
        $this->unreadcount = $unreadcount;
        $this->query = $query;
        $this->discussion = $discussion;
        $this->firstpost = $this->discussion->get_first_post();
        $this->lastpost = $this->discussion->get_last_post();
        $this->modflow = $this->discussion->get_moodleoverflow();
        $this->movepossible = $movepossible;
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/view/discussion_card';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $USER;
        $allratings = rating::get_post_ratings($this->discussion);
        $tree = service\discussion::get_posts_tree($this->discussion, $USER->id, $allratings);

        // Link the marks to the first marked post in the order of the discussion page.
        $marklink = function (string $field) use ($tree, $allratings): array {
            foreach ($tree['answers'] as $branch) {
                foreach ([$branch['answer'], ...$branch['comments']] as $post) {
                    if ($allratings[$post->get_id()]->$field > 0) {
                        return ['link' => $post->get_link()->out()];
                    }
                }
            }
            return [];
        };

        // The questioner, and the last post once there are answers or comments, as the user may see them.
        $replyamount = array_sum(array_map(fn(array $branch) => 1 + count($branch['comments']), $tree['answers']));
        $question = [
            'author' => (new author($this->firstpost, $USER->id))->export_for_template($output),
            'time' => $this->firstpost->modified,
        ];
        $lastpost = $this->lastpost && $replyamount > 0 ? [
            'name' => (new author($this->lastpost, $USER->id))->get_name(),
            'time' => $this->lastpost->get_time_active(),
            'link' => $this->lastpost->get_link()->out(),
        ] : [];

        // The votes of the question; the list shows them, voting is on the discussion page.
        $ratings = $allratings[$this->firstpost->get_id()];

        // The unread badge and "Mark as read" in the menu.
        $unreaddata = [
            'itemid' => 'moodleoverflow-markpostsread-' . $this->discussion->get_id(),
            'domain' => 'discussion',
            'instanceid' => $this->discussion->get_id(),
            'unreadlink' => (new moodle_url($this->discussion->get_link(), [], 'unread'))->out(),
            'unreadamount' => $this->unreadcount,
        ];

        // The posts a reviewer can review: their number leads to the first of them.
        $reviewposts = permissions::can_review_posts($this->modflow, $USER->id)
            ? review::get_posts_to_review_in_discussion($this->discussion, $USER->id)
            : [];
        $review = $reviewposts ? ['count' => count($reviewposts), 'link' => reset($reviewposts)->get_link()->out()] : [];
        // The own posts of the user that wait for a review, the question or answers.
        $ownpending = count(array_filter(
            $this->discussion->get_posts(),
            fn(post $post) => $post->reviewed === 0 && $post->get_userid() === (int) $USER->id
        ));

        // Miscellaneous data.
        $topicmove = ['name' => $this->discussion->name, 'id' => $this->discussion->get_id()];

        // The subscription toggle of the menu and the bell that shows a subscription.
        $subscription = permissions::can_change_discussion_subscription($this->discussion, $USER->id) ? [
            'type' => 'discussion',
            'id' => $this->discussion->get_id(),
            'subscribed' => subscription::is_subscribed_to_discussion($this->discussion, $USER->id),
        ] : [];

        // Deleting the question deletes the discussion with all its posts.
        $delete = permissions::can_delete_post($this->firstpost, $USER->id) ? [
            'postid' => $this->firstpost->get_id(),
            'replies' => count($this->discussion->get_posts()) - 1,
        ] : [];

        return (object) [
            'postid' => $this->firstpost->get_id(),
            'votes' => $ratings->upvotes - $ratings->downvotes,
            'markedhelpful' => $marklink('ishelpful'),
            'markedsolution' => $marklink('issolved'),
            'subjecttext' => format_string($this->discussion->name),
            'subjectlink' => (new moodle_url($this->discussion->get_link(), array_filter(['q' => $this->query], 'strlen')))->out(),
            'question' => $question,
            'lastpost' => $lastpost,
            // The answers and comments the user sees, like the discussion page.
            'replyamount' => $replyamount,
            'replytitle' => get_string($replyamount === 1 ? 'replycountone' : 'replycount', 'moodleoverflow', $replyamount),
            'unread' => $this->unreadcount > 0 ? $unreaddata : [],
            'review' => $review,
            'ownpending' => $ownpending,
            'questionunderreview' => $this->firstpost->reviewed == 0,
            'canmovetopic' => $this->movepossible ? $topicmove : [],
            'subscription' => $subscription,
            'delete' => $delete,
            // The menu holds "Mark as read", the subscription, "Move discussion" and "Delete discussion"; without any of them
            // it is left out.
            'hasmenu' => $this->unreadcount > 0 || $subscription || $this->movepossible || $delete,
        ];
    }
}
