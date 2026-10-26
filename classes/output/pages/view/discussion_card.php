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
use mod_moodleoverflow\local\service\rating;
use mod_moodleoverflow\local\service\readtracking;
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
    /** @var \context_module The context. Is given by the printing function.*/
    public \context_module $context;

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

    /** @var array All important base links*/
    private array $l = [
        'disc' => '/mod/moodleoverflow/discussion.php',
    ];

    /**
     * Constructor
     * @param discussion $discussion
     * @param \context_module $context
     * @param bool $movepossible If the discussion can be moved to another moodleoverflow
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public function __construct(discussion $discussion, \context_module $context, bool $movepossible) {
        $this->context = $context;
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
        // Check if the post was marked as helpful or as solution.
        $helpful = rating::get_marked_post_ids($this->discussion, rating_type::HELPFUL);
        if ($helpful) {
            $link = new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()], "#p{$helpful[0]}");
            $helpful = ['link' => $link->out()];
        }
        $solution = rating::get_marked_post_ids($this->discussion, rating_type::SOLVED);
        if ($solution) {
            $link = new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()], "#p{$solution[0]}");
            $solution = ['link' => $link->out()];
        }

        // The starter and the last poster, as the user may see them.
        $userfirstpost = (new author($this->firstpost, $USER->id))->export_for_template($output);
        $userlastpost = $this->lastpost ? [
            'name' => (new author($this->lastpost, $USER->id))->export_for_template($output)['name'],
            'date' => userdate($this->lastpost->get_time_active(), get_string('strftimerecentfull')),
            'link' => $this->lastpost->get_link()->out(),
        ] : [];

        // Gather rating information about the first post.
        $ratings = rating::get_post_ratings($this->discussion)[$this->firstpost->get_id()];
        $vote = rating::get_user_vote($this->firstpost, $USER->id);

        // Gather readtracking data for the readtracking template.
        $unreadcount = readtracking::count_unread_posts_discussion($this->discussion, $USER->id);
        $unreaddata = [
            'itemid' => 'moodleoverflow-markpostsread-' . $this->discussion->get_id(),
            'domain' => 'discussion',
            'instanceid' => $this->discussion->get_id(),
            'userid' => $USER->id,
            'unreadlink' => (new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()], '#unread'))->out(),
            'unreadamount' => $unreadcount,
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
            'canchange' => rating::can_change_vote($this->firstpost, $USER->id),
            'markedhelpful' => $helpful,
            'markedsolution' => $solution,
            'subjecttext' => format_string($this->discussion->name), // The discussion name, use format_text perhaps.
            'subjectlink' => (new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()]))->out(),
            'userfirstpost' => $userfirstpost,
            'userlastpost' => $userlastpost,
            'replyamount' => count($this->discussion->get_posts()),
            'unread' => $unreadcount > 0 ? $unreaddata : [],
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
