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
use core\output\help_icon;
use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\output\author;
use moodle_url;

/**
 * Class that gathers data for the post card template used in the discussion.php.
 * This class represents a single post that in the discussion view.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_card implements named_templatable, renderable {
    /** @var post The post that gets printed */
    public post $post;

    /** @var discussion_page_data The data of the discussion page, loaded once for all posts */
    public discussion_page_data $data;

    /** @var object The ratings of the post: upvotes, downvotes, issolved, ishelpful */
    private object $ratings;

    /**
     * Constructor.
     * @param post $post
     * @param discussion_page_data $data The data of the discussion page, loaded once for all posts
     */
    public function __construct(post $post, discussion_page_data $data) {
        $this->post = $post;
        $this->data = $data;
        $this->ratings = $data->get_ratings($post);
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/discussion/post_card';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $USER;

        // Get important variables for later checks.
        $discussion = $this->post->get_discussion();
        $moodleoverflow = $discussion->get_moodleoverflow();

        // Build the postclass, which has additional css classes that show if a post solution/helpful marks and readtracking status.
        $isread = $this->data->is_read($this->post);
        $issolved = $this->ratings->issolved > 0 ? 'markedsolution' : '';
        $ishelpful = $this->ratings->ishelpful > 0 ? 'markedhelpful' : '';

        // Get voting data for the voting template as well as reputation rating.
        $vote = $this->data->get_vote($this->post);
        $showvotes = $moodleoverflow->is_rating_enabled() ? [
            'postid' => $this->post->get_id(),
            'votes' => $this->ratings->upvotes - $this->ratings->downvotes,
            'userupvoted' => $vote === rating_type::UPVOTE,
            'userdownvoted' => $vote === rating_type::DOWNVOTE,
            'canchange' => service\rating::can_change_vote($this->post, $USER->id, $vote),
        ] : [];
        $showreputation = permissions::can_view_reputation($this->post, $USER->id) ? [
            'userid' => $this->post->get_userid(),
            'userreputation' => $this->data->get_reputation($this->post->get_userid()),
        ] : [];

        // Review data.
        $reviewdelay = $moodleoverflow->get_review_delay();
        $needsreview = $this->post->reviewed === 0;

        // Links.
        $discusspath = '/mod/moodleoverflow/discussion.php';

        // Build the mustache data.
        return (object) [
            'isfirstunread' => $this->data->is_first_unread($this->post),
            'postid' => $this->post->get_id(),
            'postclass' => ' ' . ($isread ? 'read' : 'unread') . ' ' . $ishelpful . ' ' . $issolved,
            'permalink' => (new moodle_url($discusspath, ['d' => $discussion->get_id()], 'p' . $this->post->get_id()))->out(),
            'postcontent' => $this->post->get_message_formatted(),
            'attachments' => $this->post->get_attachments($output),
            'author' => (new author($this->post, $USER->id))->export_for_template($output),
            'iscomment' => $this->post->is_comment(),
            'isfirstpost' => $this->post->is_question(),
            'date' => userdate($this->post->modified),
            'shortdate' => userdate($this->post->modified, get_string('strftimedatetimeshort', 'core_langconfig')),
            'questioner' => $this->post->get_userid() == $discussion->get_userid() ? 'questioner' : '',
            'showvotes' => $showvotes,
            'showreputation' => $showreputation,
            'needsreview' => $needsreview ? [
                'withinreviewperiod' => (time() - $this->post->created) > $reviewdelay,
                'reviewdelay' => $reviewdelay,
            ] : [],
            'canreview' => $needsreview && permissions::can_review_posts($moodleoverflow, $USER->id),
            'canreviewnow' => permissions::can_review_post($this->post, $USER->id),
            'commands' => $this->get_commands($output),
        ];
    }

    /**
     * Returns the commands below the post that the user may use: mark helpful / solved, edit, delete, reply.
     * @param renderer_base $output
     * @return array
     * @throws moodle_exception
     */
    private function get_commands(renderer_base $output): array {
        global $USER;
        $commands = [];
        if (permissions::can_mark_helpful($this->post, $USER->id)) {
            $commands['markhelpful'] = ['label' => $this->get_mark_label(rating_type::HELPFUL)];
        }
        if (permissions::can_mark_solved($this->post, $USER->id)) {
            $commands['marksolved'] = ['label' => $this->get_mark_label(rating_type::SOLVED)];
        }
        if (permissions::can_edit_post($this->post, $USER->id)) {
            $commands['editurl'] = (new moodle_url('/mod/moodleoverflow/post.php', ['edit' => $this->post->get_id()]))->out(false);
        }
        if (permissions::can_delete_post($this->post, $USER->id)) {
            $commands['delete'] = ['postid' => $this->post->get_id(), 'replies' => $this->post->count_replies()];
        }

        // A reply to a comment goes to its answer.
        $replytarget = $this->post->is_comment() ? $this->post->get_parentpost() : $this->post;
        $windowopen = $this->post->get_moodleoverflow()->is_answer_window_open();
        $helpicon = fn(string $identifier) => (new help_icon($identifier, 'moodleoverflow'))->export_for_template($output);
        if (permissions::can_reply($replytarget, $USER->id)) {
            $url = new moodle_url('/mod/moodleoverflow/post.php', ['reply' => $replytarget->get_id()], 'mformmoodleoverflow');
            $commands['reply'] = [
                'url' => $url->out(false),
                'label' => get_string($this->post->is_question() ? 'replyfirst' : 'reply', 'moodleoverflow'),
                // Allowed although the window is closed: the teacher bypass, so explain it.
                'helpicon' => $windowopen ? null : $helpicon('la_teacher_helpicon'),
            ];
        } else if ($this->post->is_question() && !$windowopen) {
            // Tell students that they can answer once the window opens.
            $commands['replylater'] = ['helpicon' => $helpicon('la_student_helpicon')];
        }
        return $commands;
    }

    /**
     * Returns the label of a mark command: unmark, mark, or "also mark" if another post has the mark already. Without
     * multiple marks, marking another post moves the mark, so there is no "also mark". rating.js uses the same strings.
     * @param rating_type $mark HELPFUL or SOLVED
     * @return string
     * @throws moodle_exception
     */
    private function get_mark_label(rating_type $mark): string {
        [$action, $marked] = $mark === rating_type::HELPFUL
            ? ['helpful', $this->ratings->ishelpful > 0]
            : ['solved', $this->ratings->issolved > 0];
        $multiplemarks = $this->post->get_moodleoverflow()->allows_multiple_marks();
        if ($marked) {
            $key = 'marknot' . $action;
        } else if ($multiplemarks && $this->data->has_mark($mark)) {
            $key = 'alsomark' . $action;
        } else {
            $key = 'mark' . $action;
        }
        return get_string($key, 'moodleoverflow');
    }
}
