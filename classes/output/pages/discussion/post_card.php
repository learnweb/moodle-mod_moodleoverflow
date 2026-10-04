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

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use html_writer;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\ratings;
use mod_moodleoverflow\readtracking;
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

    /** @var bool If the post is the first unread post of the discussion */
    public bool $isfirstunread;

    /** @var \context_module The context. Is given by the printing function.*/
    public \context_module $context;

    /**
     * Constructor.
     * @param post $post
     * @param bool $isfirstunread if the post is the first unread post in the discussion.
     * @param \context_module $context
     */
    public function __construct(post $post, bool $isfirstunread, \context_module $context) {
        $this->post = $post;
        $this->isfirstunread = $isfirstunread;
        $this->context = $context;
    }


    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/discussion/post_card';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $USER;

        // Get important variables for later checks.
        $parentpost = $this->post->get_parentpost();
        $discussion = $this->post->get_discussion();
        $moodleoverflow = $discussion->get_moodleoverflow();

        // Build the postclass, which has additional css classes that show if a post solution/helpful marks and readtracking status.
        $ratings = ratings::moodleoverflow_get_rating($this->post->get_id());
        $isread = readtracking::is_post_read($this->post->get_moodleoverflow(), $this->post->get_id(), $USER->id);
        $issolved = $ratings->issolved > 0 ? 'markedsolution' : '';
        $ishelpful = $ratings->ishelpful > 0 ? 'markedhelpful' : '';

        // Get voting data for the voting template as well as reputation rating.
        $ratings = $this->post->get_ratings();
        $userrating = ratings::user_rated($this->post->get_id());
        $showvotes = $moodleoverflow->is_rating_enabled() ? [
            'postid' => $this->post->get_id(),
            'votes' => $ratings->votesdifference,
            'userupvoted' => $userrating && $userrating->rating == RATING_UPVOTE,
            'userdownvoted' => $userrating && $userrating->rating == RATING_DOWNVOTE,
            'canchange' => permissions::can_vote($this->post, $USER->id),
        ] : [];
        $showreputation = permissions::can_view_reputation($this->post, $USER->id) ? [
            'userid' => $this->post->get_userid(),
            'userreputation' => ratings::get_reputation($moodleoverflow->id, $this->post->get_userid()),
        ] : [];

        // Review data.
        $reviewdelay = $moodleoverflow->get_review_delay();
        $needsreview = $this->post->reviewed === 0;

        // Links.
        $discusspath = '/mod/moodleoverflow/discussion.php';

        // Build the mustache data.
        return (object) [
            'isfirstunread' => $this->isfirstunread,
            'postid' => $this->post->get_id(),
            'postclass' => ' ' . ($isread ? 'read' : 'unread') . ' ' . $ishelpful . ' ' . $issolved,
            'permalink' => (new moodle_url($discusspath, ['d' => $discussion->get_id()], 'p' . $this->post->get_id()))->out(),
            'postcontent' => $this->post->get_message_formatted(),
            'attachments' => $this->post->get_attachments($output),
            'authorname' => $this->post->get_userlink()['fullname'],
            'authorlink' => $this->post->get_userlink()['link'],
            'authorpicture' => $this->post->get_userpicture(),
            'iscomment' => $parentpost !== null && $parentpost->get_id() != $this->post->get_discussion()->get_firstpostid(),
            'isfirstpost' => $this->post->get_id() == $this->post->get_discussion()->get_firstpostid(),
            'date' => userdate($this->post->modified),
            'shortdate' => userdate($this->post->modified, get_string('strftimedatetimeshort', 'core_langconfig')),
            'questioner' => $this->post->get_userid() == $this->post->get_discussion()->get_userid() ? 'questioner' : '',
            'showvotes' => $showvotes,
            'showreputation' => $showreputation,
            'needsreview' => $needsreview ? [
                'withinreviewperiod' => (time() - $this->post->created) > $reviewdelay,
                'reviewdelay' => $reviewdelay,
            ] : [],
            'canreview' => $needsreview && permissions::can_review_posts($moodleoverflow, $USER->id),
            'canreviewnow' => permissions::can_review_post($this->post, $USER->id),
            'commands' => $this->build_commands(),
        ];
    }

    /**
     * Builds the HTML string of action commands shown below a post (mark helpful/solved, edit, delete, reply).
     * LEARNWEB-TODO: refactor further. Do not render html tags directly here. Move it to mustache.
     */
    private function build_commands(): string {
        global $USER, $OUTPUT;

        $discussion   = $this->post->get_discussion();
        $moodleoverflow = $discussion->get_moodleoverflow();
        $parentpost   = $this->post->get_parentpost();
        $ratings       = $this->post->get_ratings();

        $commands = [];

        // Mark helpful — discussion starter only, direct answers only.
        if (permissions::can_mark_helpful($this->post, $USER->id)) {
            if ($ratings->markedhelpful) {
                $label = get_string('marknothelpful', 'moodleoverflow');
            } else if (ratings::discussion_is_solved($discussion->get_id(), false)) {
                $label = get_string('alsomarkhelpful', 'moodleoverflow');
            } else {
                $label = get_string('markhelpful', 'moodleoverflow');
            }
            $commands[] = html_writer::tag('a', $label, [
                'class' => 'markhelpful onlyifreviewed',
                'role' => 'button',
                'data-moodleoverflow-action' => 'helpful',
            ]);
        }

        // Mark solved — teachers only, direct answers only.
        if (permissions::can_mark_solved($this->post, $USER->id)) {
            if ($ratings->markedsolution) {
                $label = get_string('marknotsolved', 'moodleoverflow');
            } else if (ratings::discussion_is_solved($discussion->get_id(), true)) {
                $label = get_string('alsomarksolved', 'moodleoverflow');
            } else {
                $label = get_string('marksolved', 'moodleoverflow');
            }
            $commands[] = html_writer::tag('a', $label, [
                'class' => 'marksolved onlyifreviewed',
                'role' => 'button',
                'data-moodleoverflow-action' => 'solved',
            ]);
        }

        // Edit.
        if (permissions::can_edit_post($this->post, $USER->id)) {
            $commands[] = html_writer::link(
                new moodle_url('/mod/moodleoverflow/post.php', ['edit' => $this->post->get_id()]),
                get_string('edit', 'moodleoverflow')
            );
        }

        // Delete.
        if (permissions::can_delete_post($this->post, $USER->id)) {
            $commands[] = html_writer::link(
                new moodle_url('/mod/moodleoverflow/post.php', ['delete' => $this->post->get_id()]),
                get_string('delete', 'moodleoverflow')
            );
        }

        // Reply.
        $replytarget = (!$this->post->is_comment()) ? $this->post : $parentpost;
        $windowopen = $moodleoverflow->is_answer_window_open();

        if (permissions::can_reply($replytarget, $USER->id)) {
            $link = html_writer::link(
                new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', ['reply' => $replytarget->get_id()]),
                get_string($this->post->is_question() ? 'replyfirst' : 'reply', 'moodleoverflow'),
                ['class' => 'onlyifreviewed']
            );
            if (!$windowopen) {
                // Allowed although the window is closed: the teacher bypass, so explain it.
                $link .= '    ' . $OUTPUT->help_icon('la_teacher_helpicon', 'moodleoverflow');
            }
            $commands[] = $link;
        } else if ($this->post->is_question() && !$windowopen) {
            // Tell students that they can answer once the window opens.
            $helpicon = $OUTPUT->help_icon('la_student_helpicon', 'moodleoverflow');
            $commands[] = html_writer::tag(
                'span',
                get_string('replyfirst', 'moodleoverflow') . '    ' . $helpicon,
                ['class' => 'onlyifreviewed text-muted']
            );
        }

        return implode('', $commands);
    }
}
