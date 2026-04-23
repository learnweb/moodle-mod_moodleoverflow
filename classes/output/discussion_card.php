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

namespace mod_moodleoverflow\output;

use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\review;
use mod_moodleoverflow\subscriptions;
use moodle_exception;
use core\output\renderable;
use core\output\named_templatable;
use core\output\renderer_base;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\ratings;
use mod_moodleoverflow\models\discussion;
use mod_moodleoverflow\models\post;
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

    /** @var post The most recently modified post */
    public post $lastpost;

    /** @var bool If discussion can be moved */
    public bool $movepossible;

    /** @var array All important base links*/
    private array $l = [
        'view' => '/mod/moodleoverflow/view.php',
        'disc' => '/mod/moodleoverflow/discussion.php',
        'post' => '/mod/moodleoverflow/post.php',
        'user' => '/user/view.php',
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
        $this->lastpost = $this->discussion->get_newest_post();
        $this->modflow = $this->discussion->get_moodleoverflow();
        $this->movepossible = $movepossible;
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/view/discussion_card';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $USER, $DB;
        $comp = 'mod_moodleoverflow';
        $isloggedin = (!is_guest($this->context, $USER) && isloggedin());
        $viewdiscussion = has_capability('mod/moodleoverflow:viewdiscussion', $this->context);
        $canmovetopic = has_capability('mod/moodleoverflow:movetopic', $this->context);
        $issubcribable = subscriptions::is_subscribable($this->modflow, $this->context);

        // Check if the post was marked as helpful or as solution.
        $helpful = array_values(ratings::moodleoverflow_discussion_is_solved($this->discussion->get_id()));
        if ($helpful) {
            $link = new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()], "#p{$helpful[0]->postid}");
            $helpful = ['link' => $link->out()];
        }
        $solution = array_values(ratings::moodleoverflow_discussion_is_solved($this->discussion->get_id(), true));
        if ($solution) {
            $link = new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()], "#p{$solution[0]->postid}");
            $solution = ['link' => $link->out()];
        }

        // Gather information about the discussion starter and the last user that posted something.
        $startuser = $DB->get_record('user', ['id' => $this->discussion->get_userid()]);
        $lastpostuser = $DB->get_record('user', ['id' => $this->lastpost->get_userid()]);
        $startername = fullname($startuser, has_capability('moodle/site:viewfullnames', $this->context));
        $lastpostname = fullname($lastpostuser, has_capability('moodle/site:viewfullnames', $this->context));
        $isstarter = ($this->modflow->anonymous != anonymous::NOT_ANONYMOUS) && ($USER->id == $this->firstpost->get_userid());
        $islastpost = ($this->modflow->anonymous != anonymous::NOT_ANONYMOUS) && ($USER->id == $this->lastpost->get_userid());
        $starterl = new moodle_url($this->l['user'], ['id' => $this->discussion->get_userid(), 'course' => $this->modflow->course]);
        $lastpostl = new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id(), 'parent' => $this->lastpost->get_id()]);
        $isnotanon = $this->modflow->anonymous == anonymous::NOT_ANONYMOUS;

        $userfirstpost = [
            'name' => $isnotanon ? $startername : get_string(($isstarter ? 'anonym_you' : 'privacy:anonym_user_name'), $comp),
            'picture' => $isnotanon ? $this->firstpost->get_userpicture() : '',
            'link' => $isnotanon ? $starterl->out() : '',
        ];
        $userlastpost = [
            'name' => $isnotanon ? $lastpostname : get_string(($islastpost ? 'anonym_you' : 'privacy:anonym_user_name'), $comp),
            'date' => userdate($this->discussion->timemodified, get_string('strftimerecentfull')),
            'link' => $lastpostl->out(),
        ];

        // Gather rating information about the first post.
        $ratings = $this->firstpost->moodleoverflow_get_post_ratings();
        $userrating = ratings::moodleoverflow_user_rated($this->firstpost->get_id());
        $ratingability = ratings::moodleoverflow_user_can_rate($this->firstpost->get_db_object(), $this->context);

        // Gather readtracking data for the readtracking template.
        $unreadcount = readtracking::moodleoverflow_count_unread_posts_discussion($this->discussion->get_id(), $USER->id);
        $unreaddata = [
            'itemid' => 'moodleoverflow-markpostsread-' . $this->discussion->get_id(),
            'domain' => 'discussion',
            'instanceid' => $this->discussion->get_id(),
            'userid' => $USER->id,
            'unreadlink' => (new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()], '#unread'))->out(),
            'unreadamount' => $unreadcount,
        ];

        // Gather review data.
        $reviewinfo = review::get_short_review_info_for_discussion($this->discussion->get_id());
        $reviewlink = new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()], 'p' . $reviewinfo->first);

        // Miscellaneous data.
        $topicmove = ['name' => $this->discussion->name, 'id' => $this->discussion->get_id()];
        $subicon = subscriptions::get_discussion_subscription_icon($this->modflow, $this->context, $this->discussion->get_id());

        return (object) [
            'postid' => $this->firstpost->get_id(),
            'votes' => $ratings->votesdifference,
            'userupvoted' => $userrating && $userrating->rating == RATING_UPVOTE,
            'userdownvoted' => $userrating && $userrating->rating == RATING_DOWNVOTE,
            'canchange' => $ratingability && $this->firstpost->get_userid() != $USER->id,
            'markedhelpful' => $helpful,
            'markedsolution' => $solution,
            'subjecttext' => format_string($this->discussion->name), // The discussion name, use format_text perhaps.
            'subjectlink' => (new moodle_url($this->l['disc'], ['d' => $this->discussion->get_id()]))->out(),
            'userfirstpost' => $userfirstpost,
            'userlastpost' => $userlastpost,
            'replyamount' => count($this->discussion->moodleoverflow_get_discussion_posts()),
            'unread' => $unreadcount > 0 ? $unreaddata : [],
            'canreview' => has_capability('mod/moodleoverflow:reviewpost', $this->context),
            'needreview' => $reviewinfo->count > 0,
            'reviewlink' => $reviewlink->out(),
            'questionunderreview' => $this->firstpost->reviewed == 0,
            'canmovetopic' => ($isloggedin && $canmovetopic && $this->movepossible) ? $topicmove : [],
            'cansubtodiscussion' => ($isloggedin && $viewdiscussion && $issubcribable) ? ['discussionsubicon' => $subicon] : [],
        ];
    }
}
