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
use mod_moodleoverflow\local\enum\rating_type;
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

    /** @var bool If discussion can be moved */
    public bool $movepossible;

    /** @var int The number of posts the user has not read, counted for the whole page */
    public int $unreadcount;

    /**
     * Constructor
     * @param discussion $discussion
     * @param bool $movepossible If the discussion can be moved to another moodleoverflow
     * @param int $unreadcount The number of posts the user has not read, counted for the whole page
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public function __construct(discussion $discussion, bool $movepossible, int $unreadcount) {
        $this->unreadcount = $unreadcount;
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

        // Link the marks to the first marked answer in the order of the discussion page (only answers can be marked).
        $marklink = function (string $field) use ($tree, $allratings): array {
            foreach ($tree['answers'] as $branch) {
                if ($allratings[$branch['answer']->get_id()]->$field > 0) {
                    return ['link' => $branch['answer']->get_link()->out()];
                }
            }
            return [];
        };

        // The starter and the last poster, as the user may see them.
        $userfirstpost = (new author($this->firstpost, $USER->id))->export_for_template($output);
        $userlastpost = $this->lastpost ? [
            'name' => (new author($this->lastpost, $USER->id))->export_for_template($output)['name'],
            'date' => userdate($this->lastpost->get_time_active(), get_string('strftimerecentfull')),
            'link' => $this->lastpost->get_link()->out(),
        ] : [];

        // Gather rating information about the first post.
        $ratings = $allratings[$this->firstpost->get_id()];
        $vote = rating::get_user_vote($this->firstpost, $USER->id);

        // Gather readtracking data for the readtracking template.
        $unreaddata = [
            'itemid' => 'moodleoverflow-markpostsread-' . $this->discussion->get_id(),
            'domain' => 'discussion',
            'instanceid' => $this->discussion->get_id(),
            'userid' => $USER->id,
            'unreadlink' => (new moodle_url($this->discussion->get_link(), [], 'unread'))->out(),
            'unreadamount' => $this->unreadcount,
        ];

        // Gather review data.
        $reviewposts = review::get_posts_to_review_in_discussion($this->discussion, $USER->id);
        $reviewpostcount = count($reviewposts);
        $firsttoreview = reset($reviewposts) ?: null;
        $reviewlink = $firsttoreview ? $firsttoreview->get_link() : null;

        // Miscellaneous data.
        $topicmove = ['name' => $this->discussion->name, 'id' => $this->discussion->get_id()];

        return (object) [
            'postid' => $this->firstpost->get_id(),
            'votes' => $ratings->upvotes - $ratings->downvotes,
            'userupvoted' => $vote === rating_type::UPVOTE,
            'userdownvoted' => $vote === rating_type::DOWNVOTE,
            'canchange' => rating::can_change_vote($this->firstpost, $USER->id, $vote),
            'markedhelpful' => $marklink('ishelpful'),
            'markedsolution' => $marklink('issolved'),
            'subjecttext' => format_string($this->discussion->name), // The discussion name, use format_text perhaps.
            'subjectlink' => $this->discussion->get_link()->out(),
            'userfirstpost' => $userfirstpost,
            'userlastpost' => $userlastpost,
            // The answers and comments the user sees, like the discussion page.
            'replyamount' => array_sum(array_map(fn(array $branch) => 1 + count($branch['comments']), $tree['answers'])),
            'unread' => $this->unreadcount > 0 ? $unreaddata : [],
            'canreview' => permissions::can_review_posts($this->modflow, $USER->id),
            'needreview' => $reviewpostcount,
            'reviewlink' => $reviewlink?->out(),
            'questionunderreview' => $this->firstpost->reviewed == 0,
            'canmovetopic' => $this->movepossible ? $topicmove : [],
            'subscription' => permissions::can_change_discussion_subscription($this->discussion, $USER->id) ? [
                'type' => 'discussion',
                'id' => $this->discussion->get_id(),
                'subscribed' => subscription::is_subscribed_to_discussion($this->discussion, $USER->id),
                'icononly' => true,
            ] : [],
        ];
    }
}
